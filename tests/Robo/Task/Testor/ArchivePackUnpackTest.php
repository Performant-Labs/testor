<?php

namespace PL\Tests\Robo\Task\Testor {

  /**
   * Tests for {@see \PL\Robo\Task\Testor\ArchivePack} and
   * {@see \PL\Robo\Task\Testor\ArchiveUnpack} (issue #37).
   *
   * `PharData::compress(Phar::GZ)` followed by re-opening the resulting
   * `.tar.gz` and calling `extractTo()` silently writes a 0-byte file for
   * the extracted entry — `extractTo()` returns `true`, the archive's own
   * entry metadata (via RecursiveIteratorIterator) correctly reports the
   * original size, but the bytes on disk are gone. Confirmed reproducible
   * on macOS PHP 8.3.31 (Homebrew) AND Linux PHP 8.3.33 (Debian bookworm,
   * matching this repo's CI OS/PHP family) — not an environment quirk.
   * Plain (uncompressed) PharData tar extraction is unaffected.
   *
   * This exercises the REAL (non-mocked) ArchivePack -> ArchiveUnpack
   * round-trip end to end and asserts real byte-for-byte content, rather
   * than trusting exit codes or archive metadata — the same discipline
   * that caught #33.
   */
  class ArchivePackUnpackTest extends TestorTestCase {

    private const BASE = '__test_archive_packunpack';

    public function tearDown(): void {
      parent::tearDown();
      $targz = self::BASE . '.tar.gz';
      if (file_exists($targz)) {
        // Evict the per-process Phar registration before removing the file
        // so a later test's archive at the same path doesn't collide.
        \Phar::unlinkArchive($targz);
      }
      foreach ([self::BASE . '.sql', self::BASE . '.tar', $targz] as $f) {
        if (file_exists($f)) {
          unlink($f);
        }
      }
    }

    /**
     * A realistic MySQL/MariaDB dump: header comments, a CREATE TABLE, and an
     * INSERT — enough to prove the round-tripped content is genuine SQL and
     * not just "exit code 0" / correct archive metadata.
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
     * THE BUG (#37): ArchivePack -> ArchiveUnpack must round-trip real
     * content byte-for-byte. Before the fix, this passes a false green (exit
     * code 0, no exception) while silently writing a 0-byte file.
     */
    public function testPackThenUnpackRoundTripsRealContent() {
      $sql = $this->realisticSqlDump();
      file_put_contents(self::BASE . '.sql', $sql);

      $pack = $this->taskArchivePack(self::BASE, self::BASE . '.sql')->rmOrig(true);
      $packResult = $pack->run();
      self::assertEquals(0, $packResult->getExitCode(), $packResult->getMessage());
      self::assertFileExists(self::BASE . '.tar.gz');
      self::assertFileDoesNotExist(self::BASE . '.sql', 'ArchivePack must remove the original after packing');

      $unpack = $this->taskArchiveUnpack(self::BASE);
      $unpackResult = $unpack->run();
      self::assertEquals(0, $unpackResult->getExitCode(), $unpackResult->getMessage());

      self::assertFileExists(self::BASE . '.sql');
      $roundTripped = file_get_contents(self::BASE . '.sql');
      self::assertStringContainsString('CREATE TABLE', $roundTripped);
      self::assertStringContainsString('INSERT INTO', $roundTripped);
      self::assertStringContainsString('MariaDB dump', $roundTripped);
      self::assertEquals($sql, $roundTripped, 'Unpacked content must exactly match the original dump, not be truncated');
    }

  }
}
