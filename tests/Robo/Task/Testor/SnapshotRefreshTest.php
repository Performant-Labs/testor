<?php

namespace PL\Tests\Robo\Task\Testor {

  use PL\Robo\Task\Testor\SnapshotRefresh;

  /**
   * Tests for {@see SnapshotRefresh}, the generic producer orchestration
   * (issue #27, hardened for the security fix in issue #33).
   *
   * The SECURITY-CRITICAL chain is:
   *
   *   (optional) pull -> IMPORT -> sanitize -> RE-EXPORT -> put
   *
   * The import and re-export are what make the sanitize operate on — and the
   * push carry — the *pulled* production data. Without them the sanitize would
   * mutate some unrelated already-connected local DB while the raw prod
   * download was pushed untouched (issue #33: real staff PII + password hashes
   * leaked to shared storage).
   *
   * The chain is exercised with faked collaborators (a mocked collection
   * builder returning real sub-tasks whose taskExec / S3 client / Phar files are
   * real or mocked), the same way {@see SnapshotGetTest} fakes SnapshotList and
   * the pre-#33 version of this test did.
   *
   * Genericness is proven by {@see SnapshotRefreshGenericConfigTest}, which runs
   * the same chain against a second, deliberately non-performantlabs.com config
   * fixture and asserts the fixture's own bucket/site reach the executed
   * commands.
   */
  class SnapshotRefreshTest extends TestorTestCase {

    public function tearDown(): void {
      parent::tearDown();
      foreach ([
        '__test_refresh.sql',
        '__test_refresh.tar',
        '__test_refresh.tar.gz',
      ] as $f) {
        if (file_exists($f)) {
          // For .tar.gz, evict the per-process Phar registration WHILE the file
          // still exists (Phar::unlinkArchive needs a readable file); a bare
          // unlink() strands the registration and makes the next test's pack in
          // this same PHPUnit process collide.
          if (str_ends_with($f, '.tar.gz')) {
            \Phar::unlinkArchive($f);
          }
          else {
            unlink($f);
          }
        }
      }
    }

    /**
     * Build a real `__test_refresh.tar.gz` (containing a one-line `.sql`) so
     * the import stage's ArchiveUnpack has a real archive to extract. Mirrors
     * what a pull stage would have produced on disk.
     */
    private function seedArchive(string $base, string $sql): void {
      file_put_contents("$base.sql", $sql);
      $phar = new \PharData("$base.tar");
      $phar->addFile("$base.sql");
      $phar->compress(\Phar::GZ);
      unlink("$base.tar");
      // Remove the loose .sql so it mirrors what a real pull leaves on disk
      // (ArchivePack->rmOrig() deletes it). ArchiveUnpack extracts WITHOUT an
      // overwrite flag, so a pre-existing .sql would make the import's unpack
      // fail with "path already exists" instead of exercising the real path.
      unlink("$base.sql");
    }

    /**
     * Register the mock-builder expectations for the IMPORT + RE-EXPORT stages
     * that are common to every full-chain test. Returns the two real sub-tasks
     * so the caller can attach the builder to them.
     *
     * IMPORT   : ArchiveUnpack("$base.tar.gz") then `sql.command < $base.sql`.
     * RE-EXPORT: `sqldump.command > $base.sql` then ArchivePack -> "$base.tar.gz".
     *
     * @return array{0: \PL\Robo\Task\Testor\SnapshotImport, 1: \PL\Robo\Task\Testor\SnapshotCreate}
     */
    private function expectImportAndReexport($mockBuilder, array $opts, string $base) {
      $snapshotImport = $this->taskSnapshotImport([...$opts, 'gzip' => true]);
      $snapshotReexport = $this->taskSnapshotCreate([...$opts, 'ispantheon' => false]);

      // The refresh chain constructs the import + re-export sub-tasks.
      $mockBuilder->shouldReceive('taskSnapshotImport')
        ->once()
        ->andReturn($snapshotImport);
      $mockBuilder->shouldReceive('taskSnapshotCreate')
        ->once()
        ->andReturn($snapshotReexport);

      // IMPORT: unpack is a real Phar op (no taskExec); then the load command.
      $mockBuilder->shouldReceive('taskArchiveUnpack')
        ->once()
        ->andReturnUsing(fn(...$args) => $this->taskArchiveUnpack(...$args));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('$(drush sql:connect) < ' . "$base.sql")
        ->andReturn($this->mockTaskExec($snapshotImport, 0, 'imported'));

      // RE-EXPORT: dump the (now-sanitized) DB, then pack it.
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:dump > ' . "$base.sql")
        ->andReturnUsing(function () use ($snapshotReexport, $base) {
          // Emulate the dump writing a fresh (sanitized) .sql to disk so the
          // subsequent real ArchivePack has input.
          file_put_contents("$base.sql", 'sanitized dump');
          return $this->mockTaskExec($snapshotReexport, 0, 'dumped');
        });
      $mockBuilder->shouldReceive('taskArchivePack')
        ->once()
        ->andReturnUsing(fn(...$args) => $this->taskArchivePack(...$args));

      $snapshotImport->setBuilder($mockBuilder);
      $snapshotReexport->setBuilder($mockBuilder);

      return [$snapshotImport, $snapshotReexport];
    }

    /**
     * Full pull path against a local (@self) env: SnapshotCreate (drush
     * sql:dump + archive) -> IMPORT -> DbSanitize -> RE-EXPORT -> SnapshotPut.
     * All five stages run and the chain succeeds.
     */
    public function testFullPullLocalRunsCreateImportSanitizeReexportPut() {
      /** @var \Consolidation\Config\Config $testorConfig */
      $testorConfig = $this->getContainer()->get('testorConfig');
      $testorConfig->set('sanitize.command', 'drush sql:sanitize');

      $opts = [
        'env' => '@self',
        'name' => 'test',
        'element' => 'database',
        'do-not-sanitize' => false,
        'skip-download' => false,
        'filename' => '__test_refresh',
      ];

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      // The local path exercises SnapshotCreate + ArchivePack TWICE in one run:
      // once for the pull (drush sql:dump -> pack) and once for the re-export
      // (dump the sanitized DB -> pack). Register those two primitives with a
      // shared expectation each so the double use is unambiguous.
      $snapshotCreatePull = $this->taskSnapshotCreate([...$opts, 'ispantheon' => false]);
      $snapshotReexport = $this->taskSnapshotCreate([...$opts, 'ispantheon' => false]);
      $snapshotCreatePull->setBuilder($mockBuilder);
      $snapshotReexport->setBuilder($mockBuilder);
      $mockBuilder->shouldReceive('taskSnapshotCreate')
        ->twice()
        ->andReturn($snapshotCreatePull, $snapshotReexport);

      // Both dumps issue the identical `drush sql:dump > …sql`; each writes a
      // fresh .sql so the following real ArchivePack has input. (The pull dump
      // carries a sentinel prod email; the re-export dump is the sanitized one.)
      $mockBuilder->shouldReceive('taskExec')
        ->twice()
        ->with('drush sql:dump > __test_refresh.sql')
        ->andReturnUsing(function () use ($snapshotCreatePull) {
          file_put_contents('__test_refresh.sql', 'dump: aangel@performantlabs.com');
          return $this->mockTaskExec($snapshotCreatePull, 0, 'OK');
        });
      // Two packs: pull-pack and re-export-pack, both real (they exercise the
      // Phar::unlinkArchive eviction the fix added to ArchivePack).
      $mockBuilder->shouldReceive('taskArchivePack')
        ->twice()
        ->andReturnUsing(fn(...$args) => $this->taskArchivePack(...$args));

      // IMPORT stage (unpack the pull's archive, then load it).
      $snapshotImport = $this->taskSnapshotImport([...$opts, 'gzip' => true]);
      $snapshotImport->setBuilder($mockBuilder);
      $mockBuilder->shouldReceive('taskSnapshotImport')
        ->once()
        ->andReturn($snapshotImport);
      $mockBuilder->shouldReceive('taskArchiveUnpack')
        ->once()
        ->andReturnUsing(fn(...$args) => $this->taskArchiveUnpack(...$args));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('$(drush sql:connect) < __test_refresh.sql')
        ->andReturn($this->mockTaskExec($snapshotImport, 0, 'imported'));

      // Stage 3: sanitize.
      $dbSanitize = $this->taskDbSanitize($opts);
      $mockBuilder->shouldReceive('taskDbSanitize')
        ->once()
        ->andReturn($dbSanitize);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize')
        ->andReturn($this->mockTaskExec($dbSanitize, 0, 'OK'));
      $dbSanitize->setBuilder($mockBuilder);

      // Stage 5: put.
      $snapshotPut = $this->taskSnapshotPut($opts);
      $mockBuilder->shouldReceive('taskSnapshotPut')
        ->once()
        ->andReturn($snapshotPut);
      $this->mockS3Client->shouldReceive('putObject')
        ->once()
        ->with([
          'Bucket' => 'snapshot',
          'Key' => 'test/__test_refresh.tar.gz',
          'SourceFile' => '__test_refresh.tar.gz',
        ])
        ->andReturn(new \Aws\Result());
      $snapshotPut->setBuilder($mockBuilder);

      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * Full pull path against a Pantheon env: SnapshotViaBackup (terminus
     * backup:create/list/get, landing a raw .tar.gz) -> IMPORT -> DbSanitize ->
     * RE-EXPORT -> SnapshotPut. Proves the env branch selects the Pantheon
     * puller AND that the raw download is imported/sanitized/re-exported before
     * the push — the exact leak in issue #33.
     */
    public function testFullPullPantheonImportsSanitizesReexportsBeforePut() {
      /** @var \Consolidation\Config\Config $testorConfig */
      $testorConfig = $this->getContainer()->get('testorConfig');
      $testorConfig->set('sanitize.command', 'drush sql:sanitize');

      // Mock shell_exec for SnapshotViaBackup's checkTerminus().
      $mockShellExec = $this->mockBuiltIn('shell_exec');
      $mockShellExec->expects(self::once())
        ->with('which terminus')
        ->willReturn('/usr/bin/terminus');

      $opts = [
        'env' => 'dev',
        'name' => 'test',
        'element' => 'database',
        'do-not-sanitize' => false,
        'skip-download' => false,
        'filename' => '__test_refresh',
      ];

      // Seed the raw download the Pantheon puller would have produced, so the
      // import stage has a real archive to unpack. Contains a sentinel prod
      // email — the analogue of the real leak.
      $this->seedArchive('__test_refresh', 'raw prod dump: aangel@performantlabs.com');

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      // Stage 1: Pantheon backup path — three terminus commands.
      $snapshotViaBackup = $this->taskSnapshotViaBackup($opts);
      $mockBuilder->shouldReceive('taskSnapshotViaBackup')
        ->once()
        ->andReturn($snapshotViaBackup);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('terminus backup:create performant-labs.dev --element=database --keep-for=1')
        ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, 'OK'));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('terminus backup:list performant-labs.dev --format=json')
        ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, '{"1": {"file": "performant-labs_11111_database.sql.gz"}}'));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('terminus backup:get performant-labs.dev --file=performant-labs_11111_database.sql.gz --to=__test_refresh.tar.gz')
        ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, 'OK'));
      $snapshotViaBackup->setBuilder($mockBuilder);

      // Stages 2 + 4: import and re-export.
      $this->expectImportAndReexport($mockBuilder, $opts, '__test_refresh');

      // Stage 3: sanitize.
      $dbSanitize = $this->taskDbSanitize($opts);
      $mockBuilder->shouldReceive('taskDbSanitize')
        ->once()
        ->andReturn($dbSanitize);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize')
        ->andReturn($this->mockTaskExec($dbSanitize, 0, 'OK'));
      $dbSanitize->setBuilder($mockBuilder);

      // Stage 5: put.
      $snapshotPut = $this->taskSnapshotPut($opts);
      $mockBuilder->shouldReceive('taskSnapshotPut')
        ->once()
        ->andReturn($snapshotPut);
      $this->mockS3Client->shouldReceive('putObject')
        ->once()
        ->with([
          'Bucket' => 'snapshot',
          'Key' => 'test/__test_refresh.tar.gz',
          'SourceFile' => '__test_refresh.tar.gz',
        ])
        ->andReturn(new \Aws\Result());
      $snapshotPut->setBuilder($mockBuilder);

      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * Skip-download path: with --skip-download the pull stage must NOT run at
     * all (no terminus, no drush sql:dump), but the SECURITY stages still do —
     * the already-local dump is imported, sanitized, and re-exported before
     * being pushed. (Re-pushing a stale raw dump untouched would re-leak.)
     *
     * The decisive guarantee that no pull runs: neither taskSnapshotViaBackup
     * nor a *pull* taskSnapshotCreate is set up as more than the single
     * re-export SnapshotCreate — so an extra pull would be an unexpected call.
     */
    public function testSkipDownloadStillImportsSanitizesReexportsPut() {
      /** @var \Consolidation\Config\Config $testorConfig */
      $testorConfig = $this->getContainer()->get('testorConfig');
      $testorConfig->set('sanitize.command', 'drush sql:sanitize');

      $opts = [
        'env' => '@self',
        'name' => 'test',
        'element' => 'database',
        'do-not-sanitize' => false,
        'skip-download' => true,
        'filename' => '__test_refresh',
      ];

      // Already-local raw dump to operate on.
      $this->seedArchive('__test_refresh', 'stale raw dump: info@performantlabs.com');

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      // NO taskSnapshotViaBackup expectation: any Pantheon pull is unexpected.
      $mockBuilder->shouldReceive('taskSnapshotViaBackup')->never();

      // Stages 2 + 4: import and re-export. NOTE taskSnapshotCreate is expected
      // exactly ONCE here (the re-export) — a second call (an erroneous pull)
      // would exceed the ->once() and fail the test.
      $this->expectImportAndReexport($mockBuilder, $opts, '__test_refresh');

      // Stage 3: sanitize.
      $dbSanitize = $this->taskDbSanitize($opts);
      $mockBuilder->shouldReceive('taskDbSanitize')
        ->once()
        ->andReturn($dbSanitize);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize')
        ->andReturn($this->mockTaskExec($dbSanitize, 0, 'OK'));
      $dbSanitize->setBuilder($mockBuilder);

      // Stage 5: put.
      $snapshotPut = $this->taskSnapshotPut($opts);
      $mockBuilder->shouldReceive('taskSnapshotPut')
        ->once()
        ->andReturn($snapshotPut);
      $this->mockS3Client->shouldReceive('putObject')
        ->once()
        ->with([
          'Bucket' => 'snapshot',
          'Key' => 'test/__test_refresh.tar.gz',
          'SourceFile' => '__test_refresh.tar.gz',
        ])
        ->andReturn(new \Aws\Result());
      $snapshotPut->setBuilder($mockBuilder);

      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * MUTATION GUARD — the NEW import short-circuit (issue #33).
     *
     * If the IMPORT stage fails, nothing after it may run — most importantly no
     * re-export and no put. A failed import means the DB never received the
     * pulled data, so sanitizing + pushing would either push a raw dump or a
     * corrupted one. Mirrors testSanitizeFailureShortCircuitsPut.
     *
     * Mutation evidence (recorded in the PR): deleting the
     * `if (!$result->wasSuccessful()) return $result;` guard after the import
     * stage makes taskDbSanitize / taskSnapshotCreate(re-export) / taskSnapshotPut
     * run and this test goes RED; restoring the guard makes it GREEN.
     */
    public function testImportFailureShortCircuitsRest() {
      /** @var \Consolidation\Config\Config $testorConfig */
      $testorConfig = $this->getContainer()->get('testorConfig');
      $testorConfig->set('sanitize.command', 'drush sql:sanitize');

      $opts = [
        'env' => '@self',
        'name' => 'test',
        'element' => 'database',
        'do-not-sanitize' => false,
        'skip-download' => true,
        'filename' => '__test_refresh',
      ];

      $this->seedArchive('__test_refresh', 'raw dump');

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      $snapshotImport = $this->taskSnapshotImport([...$opts, 'gzip' => true]);
      $mockBuilder->shouldReceive('taskSnapshotImport')
        ->once()
        ->andReturn($snapshotImport);
      // Unpack succeeds (real Phar), but the load command fails.
      $mockBuilder->shouldReceive('taskArchiveUnpack')
        ->once()
        ->andReturnUsing(fn(...$args) => $this->taskArchiveUnpack(...$args));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('$(drush sql:connect) < __test_refresh.sql')
        ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotImport, 1, 'IMPORT BLEW UP')));
      $snapshotImport->setBuilder($mockBuilder);

      // Nothing after import may run.
      $mockBuilder->shouldReceive('taskDbSanitize')->never();
      $mockBuilder->shouldReceive('taskSnapshotCreate')->never();
      $mockBuilder->shouldReceive('taskSnapshotPut')->never();

      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(1, $result->getExitCode());
      self::assertStringContainsString('IMPORT BLEW UP', $result->getMessage());
    }

    /**
     * MUTATION GUARD — the NEW re-export short-circuit (issue #33).
     *
     * If the RE-EXPORT stage fails, the put must NEVER run: the file on disk is
     * then either the stale raw pre-sanitize dump or a partial/corrupted one,
     * and pushing it would either re-leak prod PII or publish a broken snapshot.
     *
     * Mutation evidence (recorded in the PR): deleting the guard after the
     * re-export stage makes taskSnapshotPut run and this test goes RED;
     * restoring it makes it GREEN.
     */
    public function testReexportFailureShortCircuitsPut() {
      /** @var \Consolidation\Config\Config $testorConfig */
      $testorConfig = $this->getContainer()->get('testorConfig');
      $testorConfig->set('sanitize.command', 'drush sql:sanitize');

      $opts = [
        'env' => '@self',
        'name' => 'test',
        'element' => 'database',
        'do-not-sanitize' => false,
        'skip-download' => true,
        'filename' => '__test_refresh',
      ];

      $this->seedArchive('__test_refresh', 'raw dump');

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      // Import succeeds.
      $snapshotImport = $this->taskSnapshotImport([...$opts, 'gzip' => true]);
      $mockBuilder->shouldReceive('taskSnapshotImport')->once()->andReturn($snapshotImport);
      $mockBuilder->shouldReceive('taskArchiveUnpack')
        ->once()
        ->andReturnUsing(fn(...$args) => $this->taskArchiveUnpack(...$args));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('$(drush sql:connect) < __test_refresh.sql')
        ->andReturn($this->mockTaskExec($snapshotImport, 0, 'imported'));
      $snapshotImport->setBuilder($mockBuilder);

      // Sanitize succeeds.
      $dbSanitize = $this->taskDbSanitize($opts);
      $mockBuilder->shouldReceive('taskDbSanitize')->once()->andReturn($dbSanitize);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize')
        ->andReturn($this->mockTaskExec($dbSanitize, 0, 'OK'));
      $dbSanitize->setBuilder($mockBuilder);

      // Re-export FAILS on its dump command.
      $snapshotReexport = $this->taskSnapshotCreate([...$opts, 'ispantheon' => false]);
      $mockBuilder->shouldReceive('taskSnapshotCreate')->once()->andReturn($snapshotReexport);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:dump > __test_refresh.sql')
        ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotReexport, 1, 'REEXPORT BLEW UP')));
      $snapshotReexport->setBuilder($mockBuilder);

      // Put must never run.
      $mockBuilder->shouldReceive('taskSnapshotPut')->never();

      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(1, $result->getExitCode());
      self::assertStringContainsString('REEXPORT BLEW UP', $result->getMessage());
    }

    /**
     * Short-circuit: if SANITIZE fails, RE-EXPORT and PUT must NEVER run —
     * pushing an unsanitized dump to storage is the exact failure this guards
     * against.
     *
     * Mutation evidence (recorded in the PR): removing the guard after the
     * sanitize stage makes taskSnapshotCreate(re-export)/taskSnapshotPut run and
     * this test goes RED; restoring it makes it GREEN.
     */
    public function testSanitizeFailureShortCircuitsRest() {
      /** @var \Consolidation\Config\Config $testorConfig */
      $testorConfig = $this->getContainer()->get('testorConfig');
      $testorConfig->set('sanitize.command', 'drush sql:sanitize');

      $opts = [
        'env' => '@self',
        'name' => 'test',
        'element' => 'database',
        'do-not-sanitize' => false,
        'skip-download' => true,
        'filename' => '__test_refresh',
      ];

      $this->seedArchive('__test_refresh', 'raw dump');

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      // Import succeeds.
      $snapshotImport = $this->taskSnapshotImport([...$opts, 'gzip' => true]);
      $mockBuilder->shouldReceive('taskSnapshotImport')->once()->andReturn($snapshotImport);
      $mockBuilder->shouldReceive('taskArchiveUnpack')
        ->once()
        ->andReturnUsing(fn(...$args) => $this->taskArchiveUnpack(...$args));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('$(drush sql:connect) < __test_refresh.sql')
        ->andReturn($this->mockTaskExec($snapshotImport, 0, 'imported'));
      $snapshotImport->setBuilder($mockBuilder);

      $dbSanitize = $this->taskDbSanitize($opts);
      $mockBuilder->shouldReceive('taskDbSanitize')->once()->andReturn($dbSanitize);
      // Re-export and put must never be constructed nor run.
      $mockBuilder->shouldReceive('taskSnapshotCreate')->never();
      $mockBuilder->shouldReceive('taskSnapshotPut')->never();

      // Sanitize fails.
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize')
        ->andReturn($this->mockTaskExec(new \Robo\Result($dbSanitize, 1, 'SANITIZE BLEW UP')));
      $dbSanitize->setBuilder($mockBuilder);

      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(1, $result->getExitCode());
      self::assertStringContainsString('SANITIZE BLEW UP', $result->getMessage());
    }

    /**
     * Short-circuit: if the PULL stage fails, none of import/sanitize/re-export/
     * put runs.
     */
    public function testPullFailureShortCircuitsRest() {
      $opts = [
        'env' => '@self',
        'name' => 'test',
        'element' => 'database',
        'do-not-sanitize' => false,
        'skip-download' => false,
        'filename' => '__test_refresh',
      ];

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      $snapshotCreate = $this->taskSnapshotCreate([...$opts, 'ispantheon' => false]);

      // Pull (local dump) fails on the very first command.
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:dump > __test_refresh.sql')
        ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 1, 'DUMP FAILED')));

      $mockBuilder->shouldReceive('taskSnapshotCreate')
        ->once()
        ->andReturn($snapshotCreate);
      // Nothing downstream may run.
      $mockBuilder->shouldReceive('taskSnapshotImport')->never();
      $mockBuilder->shouldReceive('taskDbSanitize')->never();
      $mockBuilder->shouldReceive('taskSnapshotPut')->never();

      $snapshotCreate->setBuilder($mockBuilder);
      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(1, $result->getExitCode());
      self::assertStringContainsString('DUMP FAILED', $result->getMessage());
    }

  }
}
