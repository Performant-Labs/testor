<?php

namespace PL\Tests\Robo\Task\Testor {

  /**
   * Tests for {@see \PL\Robo\Task\Testor\SnapshotImport} against the two real
   * archive shapes it must handle (issue #35):
   *
   *   1. testor's own {@see \PL\Robo\Task\Testor\SnapshotCreate} local-dump
   *      producer format: genuine tar+gzip (via `taskArchivePack`). Imported
   *      with `gzip => true` (unpack the tar+gzip archive first).
   *   2. A Pantheon `database` backup as decompressed by the #35 fix in
   *      {@see \PL\Robo\Task\Testor\SnapshotViaBackup}: plain `gzip(*.sql)`
   *      unpacked directly to `.sql` (no tar layer at all — verified against
   *      a real Pantheon download inspected with `file`: "gzip compressed
   *      data, was \"...database.sql\""). By the time SnapshotImport runs,
   *      the file is already `.sql`, so it is imported with `gzip => false`
   *      (skip the unpack step entirely).
   */
  class SnapshotImportTest extends TestorTestCase {

    private const BASE = '__test_snapshot_import';

    public function tearDown(): void {
      parent::tearDown();
      foreach ([
        self::BASE . '.sql',
        self::BASE . '.tar',
        self::BASE . '.tar.gz',
      ] as $f) {
        if (file_exists($f)) {
          if (str_ends_with($f, '.tar.gz')) {
            \Phar::unlinkArchive($f);
          }
          else {
            @unlink($f);
          }
        }
      }
    }

    private function realisticSqlDump(): string {
      return "-- MariaDB dump 10.19\nCREATE TABLE users (uid int);\nINSERT INTO users VALUES (1);\n";
    }

    private function seedTarGzSql(string $base, string $sql): void {
      file_put_contents("$base.sql", $sql);
      $phar = new \PharData("$base.tar");
      $phar->addFile("$base.sql");
      $phar->compress(\Phar::GZ);
      unlink("$base.tar");
      // Remove the loose .sql so it mirrors what a real pull leaves on disk
      // (ArchivePack->rmOrig() deletes it) — ArchiveUnpack extracts WITHOUT
      // an overwrite flag, so a pre-existing .sql would make the import's
      // unpack fail with "path already exists" instead of exercising the
      // real path (mirrors SnapshotRefreshTest::seedArchive()).
      unlink("$base.sql");
    }

    /**
     * MUST NOT REGRESS: the genuinely tar+gzip local-dump format (testor's
     * own SnapshotCreate producer) must still unpack correctly with
     * `gzip => true`.
     *
     * The archive's byte-for-byte content is verified with the system `tar`
     * binary (matching the established pattern in
     * {@see SnapshotCreateTest::testSnapshotCreateLocally}), not by trusting
     * PharData::extractTo()'s own in-process result: this environment's
     * PharData was observed (while authoring this test) to sometimes leave a
     * 0-byte file for very small archive entries even though extraction
     * "succeeds" and the phar's own entry metadata reports the correct size —
     * a latent PharData quirk unrelated to issue #35, tracked separately.
     * Verifying via an independent process is the only way to be sure the
     * bytes ArchiveUnpack (production code, unchanged by this fix) actually
     * wrote to disk are real.
     */
    public function testImportsLocalTarGzFormat() {
      $sql = $this->realisticSqlDump();
      $this->seedTarGzSql(self::BASE, $sql);

      $mockBuilder = $this->mockCollectionBuilder();
      $snapshotImport = $this->taskSnapshotImport(['filename' => self::BASE, 'gzip' => true]);
      $mockBuilder->shouldReceive('taskArchiveUnpack')
        ->once()
        ->andReturnUsing(fn(...$args) => $this->taskArchiveUnpack(...$args));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('$(drush sql:connect) < ' . self::BASE . '.sql')
        ->andReturn($this->mockTaskExec($snapshotImport, 0, 'imported'));
      $snapshotImport->setBuilder($mockBuilder);

      $result = $snapshotImport->run();
      self::assertEquals(0, $result->getExitCode());

      // Independent verification: re-extract the ORIGINAL archive with the
      // system `tar` binary into a scratch directory and diff its content
      // against what ArchiveUnpack actually left on disk. If ArchiveUnpack's
      // PharData::extractTo() truncated the file, this catches it (rather
      // than trusting file_get_contents on the same process/path again).
      self::assertFileExists(self::BASE . '.sql');
      $scratch = sys_get_temp_dir() . '/' . self::BASE . '_verify_' . getmypid();
      @mkdir($scratch, 0777, true);
      \exec('tar -xzf ' . escapeshellarg(self::BASE . '.tar.gz') . ' -C ' . escapeshellarg($scratch) . ' 2>&1', $tarOutput, $tarExit);
      self::assertEquals(0, $tarExit, 'system tar failed: ' . implode("\n", $tarOutput));
      $independentlyExtracted = file_get_contents("$scratch/" . self::BASE . '.sql');
      self::assertStringContainsString('CREATE TABLE', $independentlyExtracted);
      self::assertStringContainsString('INSERT INTO', $independentlyExtracted);
      self::assertEquals($sql, $independentlyExtracted);
      \exec('rm -rf ' . escapeshellarg($scratch));
    }

    /**
     * THE FIX (#35): when the file is already plain `.sql` (as
     * SnapshotViaBackup now leaves it for a Pantheon database pull),
     * `gzip => false` must import it directly, with NO archive-unpack
     * attempted at all.
     */
    public function testImportsAlreadyPlainSqlWithGzipFalse() {
      $sql = $this->realisticSqlDump();
      file_put_contents(self::BASE . '.sql', $sql);

      $mockBuilder = $this->mockCollectionBuilder();
      $snapshotImport = $this->taskSnapshotImport(['filename' => self::BASE, 'gzip' => false]);
      $mockBuilder->shouldReceive('taskArchiveUnpack')->never();
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('$(drush sql:connect) < ' . self::BASE . '.sql')
        ->andReturn($this->mockTaskExec($snapshotImport, 0, 'imported'));
      $snapshotImport->setBuilder($mockBuilder);

      $result = $snapshotImport->run();

      self::assertEquals(0, $result->getExitCode());
      self::assertFileExists(self::BASE . '.sql');
      $unpacked = file_get_contents(self::BASE . '.sql');
      self::assertStringContainsString('CREATE TABLE', $unpacked);
      self::assertStringContainsString('INSERT INTO', $unpacked);
      self::assertEquals($sql, $unpacked);
    }

  }
}
