<?php

namespace PL\Tests\Robo\Task\Testor {

  /**
   * Tests for {@see \PL\Robo\Task\Testor\SnapshotViaBackup} (issue #35).
   *
   * Real Pantheon `database` backups are a PLAIN gzip of a raw `.sql` file —
   * verified against a real download inspected with `file`: "gzip compressed
   * data, was \"performant-labs_live_..._database.sql\"". There is no tar
   * layer at all. `files`/`code` backups, in contrast, genuinely are
   * tar+gzip, so that branch is untouched by this fix (covered by
   * {@see SnapshotCreateTest::testSnapshotCreateViaBackup}).
   *
   * Before the fix, SnapshotViaBackup unconditionally downloaded to
   * `{filename}.tar.gz` regardless of element, and the downstream import
   * stage's `taskArchiveUnpack` tried to tar-extract the `database` backup's
   * real SQL bytes, failing with a "corrupted tar file" checksum-mismatch
   * error. The fix downloads the `database` element to `.gz` and gunzips it
   * directly to `.sql` itself, so there is no tar-unpack step for this path
   * at all.
   */
  class SnapshotViaBackupTest extends TestorTestCase {

    private const BASE = '__test_snapshot_via_backup';

    public function tearDown(): void {
      parent::tearDown();
      foreach ([
        self::BASE . '.sql',
        self::BASE . '.gz',
        self::BASE . '.tar.gz',
      ] as $f) {
        if (file_exists($f)) {
          @unlink($f);
        }
      }
    }

    /**
     * A realistic MySQL/MariaDB dump: header comments, a CREATE TABLE, and an
     * INSERT — enough to prove the decompressed content is genuine SQL and
     * not just "exit code 0".
     */
    private function realisticSqlDump(): string {
      return <<<SQL
      -- MariaDB dump 10.19  Distrib 10.6.16-MariaDB, for Linux (x86_64)
      --
      -- Host: localhost    Database: performant_labs_live
      -- ------------------------------------------------------
      -- Server version	10.6.16-MariaDB

      SET NAMES utf8mb4;

      CREATE TABLE `users` (
        `uid` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `mail` varchar(254) DEFAULT NULL,
        PRIMARY KEY (`uid`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

      INSERT INTO `users` VALUES (1,'aangel@performantlabs.com');

      -- Dump completed
      SQL;
    }

    /**
     * THE BUG (#35): a real Pantheon `database` backup — plain gzip(*.sql),
     * no tar layer — must be downloaded and decompressed straight to `.sql`
     * by SnapshotViaBackup itself, with no downstream tar-unpack step needed.
     */
    public function testDatabaseElementDownloadsAndGunzipsDirectlyToSql() {
      $mockShellExec = $this->mockBuiltIn('shell_exec');
      $mockShellExec->expects(self::once())
        ->with('which terminus')
        ->willReturn('/usr/bin/terminus');

      $sql = $this->realisticSqlDump();

      $mockBuilder = $this->mockCollectionBuilder();
      $snapshotViaBackup = $this->taskSnapshotViaBackup([
        'env' => 'live',
        'element' => 'database',
        'name' => 'test',
        'filename' => self::BASE,
      ]);

      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('terminus backup:create performant-labs.live --element=database --keep-for=1')
        ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, 'OK'));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('terminus backup:list performant-labs.live --format=json')
        ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, '{"1": {"file": "performant-labs_11111_database.sql.gz"}}'));
      // Real terminus download: plain gzip(*.sql), no tar container.
      // Downloaded to an honest `.gz` name (not `.tar.gz`).
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('terminus backup:get performant-labs.live --file=performant-labs_11111_database.sql.gz --to=' . self::BASE . '.gz')
        ->andReturnUsing(function () use ($snapshotViaBackup, $sql) {
          file_put_contents(self::BASE . '.gz', gzencode($sql, 9));
          return $this->mockTaskExec($snapshotViaBackup, 0, 'OK');
        });
      $snapshotViaBackup->setBuilder($mockBuilder);

      $result = $snapshotViaBackup->run();

      self::assertEquals(0, $result->getExitCode());

      // The .gz intermediate must be gone and a real, importable .sql left
      // behind — inspect actual content, not just the exit code.
      self::assertFileDoesNotExist(self::BASE . '.gz');
      self::assertFileExists(self::BASE . '.sql');
      $decompressed = file_get_contents(self::BASE . '.sql');
      self::assertStringContainsString('CREATE TABLE', $decompressed);
      self::assertStringContainsString('INSERT INTO', $decompressed);
      self::assertStringContainsString('MariaDB dump', $decompressed);
      self::assertEquals($sql, $decompressed);
    }

  }
}
