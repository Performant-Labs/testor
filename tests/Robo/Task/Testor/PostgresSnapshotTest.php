<?php

namespace PL\Tests\Robo\Task\Testor {

  use PL\Robo\Common\StorageStrategy;
  use PL\Robo\Testor;
  use PL\Tests\Robo\Task\Testor\Helper\PostgresTestHarness;
  use Robo\Robo;
  use Symfony\Component\Console\Output\NullOutput;

  /**
   * Real-Postgres proof for issue #40 (epic #39): SnapshotCreate and
   * SnapshotImport work against Postgres with ZERO production code changes,
   * because `sqldump.command`/`sql.command` are already plain shell-command
   * config strings (see the doc comment on .testor_postgres_test.yml).
   *
   * Unlike every other test in this suite, this one does NOT mock the
   * collection builder/taskExec — SnapshotCreate/SnapshotImport run for
   * REAL, shelling out to real `pg_dump`/`psql` against a real Postgres
   * server (PostgresTestHarness), packing/unpacking a real .tar.gz. The
   * proof that matters is content, not just an exit code: a row seeded into
   * a SOURCE database is read back from a completely separate TARGET
   * database after a real dump -> pack -> unpack -> import round-trip.
   *
   * S3 put/get (snapshot:put/snapshot:get) are deliberately NOT exercised
   * here — those are already covered generically (any file content) by
   * SnapshotCreateTest/SnapshotGetTest's mocked S3Client, and are unrelated
   * to which database engine produced the file. This test's whole point is
   * the two commands that ARE database-specific.
   */
  class PostgresSnapshotTest extends TestorTestCase {

    private const SOURCE_DB = 'testor_pg_source_test';
    private const TARGET_DB = 'testor_pg_target_test';
    private const FILENAME = '__pg_snapshot_test';

    private PostgresTestHarness $pg;

    public function setUp(): void {
      PostgresTestHarness::ensureAvailable();

      $this->pg = new PostgresTestHarness();
      $this->pg->createDatabase(self::SOURCE_DB);
      $this->pg->createDatabase(self::TARGET_DB);
      $this->pg->seedFixtureTable(self::SOURCE_DB);

      // Build the container directly against the real-Postgres fixture,
      // mirroring SnapshotRefreshGenericConfigTest's established pattern
      // for a non-default config -- deliberately NOT calling
      // parent::setUp() first: Robo::createDefaultContainer() refuses to
      // build a second container once one already exists for this test
      // process, so a prior parent::setUp() call would silently make this
      // one a no-op (confirmed empirically: the task received
      // .testor_test.yml's `drush sql:dump` instead of this fixture's
      // `pg_dump` command).
      $container = Robo::createDefaultContainer(null, new NullOutput());
      $container->add('testorConfig', Testor::createConfiguration(['.testor_postgres_test.yml']));
      $container->add('s3Client', $this->mockS3Client());
      $container->add('s3Bucket', 'snapshot');
      $container->add('storage', StorageStrategy::class);
      $this->setContainer($container);
    }

    public function tearDown(): void {
      parent::tearDown();
      foreach ([self::FILENAME . '.sql', self::FILENAME . '.tar', self::FILENAME . '.tar.gz'] as $f) {
        if (file_exists($f)) {
          if (str_ends_with($f, '.tar.gz')) {
            // Evict the per-process Phar registration while the file still
            // exists (same reason as SnapshotRefreshTest::tearDown).
            \Phar::unlinkArchive($f);
          }
          else {
            unlink($f);
          }
        }
      }
      if (isset($this->pg)) {
        $this->pg->dropDatabase(self::SOURCE_DB);
        $this->pg->dropDatabase(self::TARGET_DB);
      }
    }

    public function testCreateAndImportRoundTripAgainstRealPostgres(): void {
      // Real pg_dump, real ArchivePack -- no mock builder.
      $snapshotCreate = $this->taskSnapshotCreate([
        'env' => '@self',
        'name' => 'test',
        'element' => 'database',
        'filename' => self::FILENAME,
        'ispantheon' => false,
      ]);
      $result = $snapshotCreate->run();
      self::assertEquals(0, $result->getExitCode(), $result->getMessage());
      self::assertFileExists(self::FILENAME . '.tar.gz', 'SnapshotCreate should have produced a real archive from a real pg_dump.');

      // Real ArchiveUnpack, real psql import into the TARGET database.
      $snapshotImport = $this->taskSnapshotImport(['filename' => self::FILENAME]);
      $result = $snapshotImport->run();
      self::assertEquals(0, $result->getExitCode(), $result->getMessage());

      // The actual proof: the row seeded into the SOURCE database is now
      // readable from the TARGET database, having round-tripped through a
      // real Postgres dump/pack/unpack/import with zero Testor code changes.
      $value = $this->pg->queryScalar(self::TARGET_DB, 'select name from widgets where id = 1');
      self::assertSame('real-postgres-fixture', $value);
    }

  }
}
