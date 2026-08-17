<?php

namespace PL\Tests\Robo\Task\Testor {

  use PL\Robo\Task\Testor\SnapshotRefresh;

  /**
   * Tests for {@see SnapshotRefresh}, the generic producer orchestration
   * (issue #27): (optionally) pull → sanitize → put, driven entirely by config.
   *
   * The chain is exercised with faked collaborators (a mocked collection
   * builder returning real sub-tasks whose taskExec / S3 client are mocked),
   * the same way {@see SnapshotGetTest} fakes SnapshotList.
   *
   * Genericness is proven by {@see SnapshotRefreshGenericConfigTest}, which runs
   * the same chain against a second, deliberately non-performantlabs.com config
   * fixture and asserts the fixture's own bucket/site reach the executed
   * commands.
   */
  class SnapshotRefreshTest extends TestorTestCase {

    public function tearDown(): void {
      parent::tearDown();
      foreach (['__test_refresh.sql', '__test_refresh.tar', '__test_refresh.tar.gz'] as $f) {
        if (file_exists($f)) {
          unlink($f);
        }
      }
    }

    /**
     * Full pull path against a local (@self) env: SnapshotCreate (drush
     * sql:dump + archive) → DbSanitize → SnapshotPut. All three stages run and
     * the chain succeeds.
     */
    public function testFullPullLocalRunsCreateSanitizePut() {
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

      // Sub-tasks the refresh chain will drive.
      $snapshotCreate = $this->taskSnapshotCreate([...$opts, 'ispantheon' => false]);
      $dbSanitize = $this->taskDbSanitize($opts);
      $snapshotPut = $this->taskSnapshotPut($opts);

      // Stage 1: local dump (drush sql:dump) — SnapshotCreate uses taskExec then
      // taskArchivePack. We create a real dump file so ArchivePack has input.
      file_put_contents('__test_refresh.sql', 'select 1+1;');
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:dump > __test_refresh.sql')
        ->andReturn($this->mockTaskExec($snapshotCreate, 0, 'OK'));
      $mockBuilder->shouldReceive('taskArchivePack')
        ->once()
        ->andReturnUsing(fn(...$args) => $this->taskArchivePack(...$args));

      // The refresh task itself drives sub-tasks via the mocked builder.
      $mockBuilder->shouldReceive('taskSnapshotCreate')
        ->once()
        ->andReturn($snapshotCreate);
      $mockBuilder->shouldReceive('taskDbSanitize')
        ->once()
        ->andReturn($dbSanitize);
      $mockBuilder->shouldReceive('taskSnapshotPut')
        ->once()
        ->andReturn($snapshotPut);

      // Stage 2: sanitize.
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize')
        ->andReturn($this->mockTaskExec($dbSanitize, 0, 'OK'));

      // Stage 3: put — SnapshotPut uses the S3 client directly.
      $this->mockS3Client->shouldReceive('putObject')
        ->once()
        ->with([
          'Bucket' => 'snapshot',
          'Key' => 'test/__test_refresh.tar.gz',
          'SourceFile' => '__test_refresh.tar.gz',
        ])
        ->andReturn(new \Aws\Result());

      $snapshotCreate->setBuilder($mockBuilder);
      $dbSanitize->setBuilder($mockBuilder);
      $snapshotPut->setBuilder($mockBuilder);
      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * Full pull path against a Pantheon env: the backup-based path
     * (SnapshotViaBackup: terminus backup:create/list/get) is used instead of a
     * local dump, then sanitize → put. Proves the env branch selects the
     * Pantheon puller.
     */
    public function testFullPullPantheonUsesViaBackup() {
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

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      $snapshotViaBackup = $this->taskSnapshotViaBackup($opts);
      $dbSanitize = $this->taskDbSanitize($opts);
      $snapshotPut = $this->taskSnapshotPut($opts);

      // Stage 1: Pantheon backup path — three terminus commands.
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

      $mockBuilder->shouldReceive('taskSnapshotViaBackup')
        ->once()
        ->andReturn($snapshotViaBackup);
      $mockBuilder->shouldReceive('taskDbSanitize')
        ->once()
        ->andReturn($dbSanitize);
      $mockBuilder->shouldReceive('taskSnapshotPut')
        ->once()
        ->andReturn($snapshotPut);

      // Stage 2: sanitize.
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize')
        ->andReturn($this->mockTaskExec($dbSanitize, 0, 'OK'));

      // Stage 3: put.
      $this->mockS3Client->shouldReceive('putObject')
        ->once()
        ->with([
          'Bucket' => 'snapshot',
          'Key' => 'test/__test_refresh.tar.gz',
          'SourceFile' => '__test_refresh.tar.gz',
        ])
        ->andReturn(new \Aws\Result());

      $snapshotViaBackup->setBuilder($mockBuilder);
      $dbSanitize->setBuilder($mockBuilder);
      $snapshotPut->setBuilder($mockBuilder);
      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * Skip-download path: with --skip-download, the pull stage must NOT run at
     * all (no terminus, no drush sql:dump) — only sanitize → put run against the
     * already-local dump.
     *
     * The decisive guarantee is that NO pull sub-task is invoked: neither
     * taskSnapshotViaBackup nor taskSnapshotCreate is set up on the mock, so if
     * the code tried to pull, Mockery would fail the test on the unexpected
     * call.
     */
    public function testSkipDownloadRunsOnlySanitizeAndPut() {
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

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      $dbSanitize = $this->taskDbSanitize($opts);
      $snapshotPut = $this->taskSnapshotPut($opts);

      // NO taskSnapshotViaBackup / taskSnapshotCreate expectations: any pull
      // attempt is an unexpected call and fails the test.
      $mockBuilder->shouldReceive('taskDbSanitize')
        ->once()
        ->andReturn($dbSanitize);
      $mockBuilder->shouldReceive('taskSnapshotPut')
        ->once()
        ->andReturn($snapshotPut);

      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize')
        ->andReturn($this->mockTaskExec($dbSanitize, 0, 'OK'));

      $this->mockS3Client->shouldReceive('putObject')
        ->once()
        ->with([
          'Bucket' => 'snapshot',
          'Key' => 'test/__test_refresh.tar.gz',
          'SourceFile' => '__test_refresh.tar.gz',
        ])
        ->andReturn(new \Aws\Result());

      $dbSanitize->setBuilder($mockBuilder);
      $snapshotPut->setBuilder($mockBuilder);
      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * Short-circuit: if SANITIZE fails, PUT must NEVER run — pushing an
     * unsanitized dump to storage is the exact failure this guards against.
     *
     * The decisive guarantee: taskSnapshotPut is asserted `never()`, and the S3
     * client's putObject has no expectation, so any push after a failed
     * sanitize fails the test.
     *
     * Mutation evidence (documented in the PR): removing the
     * `if (!$result->wasSuccessful()) return $result;` guard after the sanitize
     * stage in SnapshotRefresh::run() makes this test go RED (taskSnapshotPut is
     * then invoked); restoring it makes it GREEN.
     */
    public function testSanitizeFailureShortCircuitsPut() {
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

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      $dbSanitize = $this->taskDbSanitize($opts);

      $mockBuilder->shouldReceive('taskDbSanitize')
        ->once()
        ->andReturn($dbSanitize);
      // Put must never be constructed nor run.
      $mockBuilder->shouldReceive('taskSnapshotPut')
        ->never();

      // Sanitize fails.
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize')
        ->andReturn($this->mockTaskExec(new \Robo\Result($dbSanitize, 1, 'SANITIZE BLEW UP')));

      // No putObject expectation: a push here is an unexpected call.

      $dbSanitize->setBuilder($mockBuilder);
      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(1, $result->getExitCode());
      self::assertStringContainsString('SANITIZE BLEW UP', $result->getMessage());
    }

    /**
     * Short-circuit: if the PULL stage fails, neither sanitize nor put runs.
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
      // Neither sanitize nor put may run.
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
