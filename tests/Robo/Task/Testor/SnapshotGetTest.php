<?php

namespace PL\Tests\Robo\Task\Testor;

use PL\Robo\Task\Testor\SnapshotGet;

class SnapshotGetTest extends TestorTestCase {
  public function testSnapshotGet() {
    /** @var SnapshotGet $snapshotGet */
    $snapshotGet = $this->taskSnapshotGet(['name' => 'test', 'output' => 'test.sql.gz', 'element' => 'database']);

    // Mock S3Client.
    $this->mockS3Client->shouldReceive('listObjects')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Delimiter' => ':',
        'Prefix' => 'test'
      ))
      ->andReturn(array(
        'Contents' => array(
          array(
            'Key' => 'test/performant-labs_1111_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-01'),
            'Size' => '1234'
          ),
          array(
            'Key' => 'test/performant-labs_2222_files.sql.gz',
            'LastModified' => new \DateTime('2024-09-02'),
            'Size' => '1324'
          )
        )
      ));
    $this->mockS3Client->shouldReceive('getObject')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Key' => 'test/performant-labs_1111_database.sql.gz',
        'SaveAs' => 'test.sql.gz'
      ))
      ->andReturn(array());

    // Now things are going tricky, since SnapshotGet uses
    // SnapshotList and it's not available because Testor
    // is not installed in the test environment.
    // So, we must mock builder once again (like in SnapshotCreateTest),
    // and make it return SnapshotList available here.
    $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'database']);
    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskSnapshotList')
      ->once()
      ->with(['name' => 'test', 'element' => 'database'])
      ->andReturn($snapshotList);
    $snapshotGet->setBuilder($mockBuilder);

    $result = $snapshotGet->run();
    self::assertEquals(0, $result->getExitCode());
  }

  public function testSnapshotGetOutputNotSet() {
    /** @var SnapshotGet $snapshotGet */
    $snapshotGet = $this->taskSnapshotGet(['name' => 'test', 'element' => 'database']);

    // Mock S3Client.
    $this->mockS3Client->shouldReceive('listObjects')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Delimiter' => ':',
        'Prefix' => 'test'
      ))
      ->andReturn(array(
        'Contents' => array(
          array(
            'Key' => 'test/performant-labs_1111_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-01'),
            'Size' => '1234'
          ),
          array(
            'Key' => 'test/performant-labs_2222_files.sql.gz',
            'LastModified' => new \DateTime('2024-09-02'),
            'Size' => '1324'
          )
        )
      ));
    $this->mockS3Client->shouldReceive('getObject')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Key' => 'test/performant-labs_1111_database.sql.gz',
        'SaveAs' => 'performant-labs_1111_database.sql.gz'
      ))
      ->andReturn(array());

    // Now things are going tricky, since SnapshotGet uses
    // SnapshotList and it's not available because Testor
    // is not installed in the test environment.
    // So, we must mock builder once again (like in SnapshotCreateTest),
    // and make it return SnapshotList available here.
    $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'database']);
    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskSnapshotList')
      ->once()
      ->with(['name' => 'test', 'element' => 'database'])
      ->andReturn($snapshotList);
    $snapshotGet->setBuilder($mockBuilder);

    $result = $snapshotGet->run();
    self::assertEquals(0, $result->getExitCode());
  }

  public function testSnapshotGetFiles() {
    /** @var SnapshotGet $snapshotGet */
    $snapshotGet = $this->taskSnapshotGet(['name' => 'test', 'output' => 'test.sql.gz', 'element' => 'files']);

    // Mock S3Client.
    $this->mockS3Client->shouldReceive('listObjects')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Delimiter' => ':',
        'Prefix' => 'test'
      ))
      ->andReturn(array(
        'Contents' => array(
          array(
            'Key' => 'test/performant-labs_1111_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-01'),
            'Size' => '1234'
          ),
          array(
            'Key' => 'test/performant-labs_2222_files.sql.gz',
            'LastModified' => new \DateTime('2024-09-02'),
            'Size' => '1324'
          )
        )
      ));
    $this->mockS3Client->shouldReceive('getObject')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Key' => 'test/performant-labs_2222_files.sql.gz',
        'SaveAs' => 'test.sql.gz'
      ))
      ->andReturn(array());

    // Now things are going tricky, since SnapshotGet uses
    // SnapshotList and it's not available because Testor
    // is not installed in the test environment.
    // So, we must mock builder once again (like in SnapshotCreateTest),
    // and make it return SnapshotList available here.
    $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'files']);
    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskSnapshotList')
      ->once()
      ->with(['name' => 'test', 'element' => 'files'])
      ->andReturn($snapshotList);
    $snapshotGet->setBuilder($mockBuilder);

    $result = $snapshotGet->run();
    self::assertEquals(0, $result->getExitCode());
  }

  /**
   * Default path (no --snapshot selector) must still return the latest
   * (newest-dated) snapshot, exactly as before this feature existed.
   *
   * Fixture has THREE differently-dated database entries so that
   * "picked table[0]" (latest, correct) is distinguishable from
   * "picked some other entry". The newest is 2024-09-03 (the 3333 key),
   * and that is the one that must be downloaded.
   */
  public function testSnapshotGetDefaultReturnsLatest() {
    /** @var SnapshotGet $snapshotGet */
    $snapshotGet = $this->taskSnapshotGet(['name' => 'test', 'output' => 'test.sql.gz', 'element' => 'database']);

    $this->mockS3Client->shouldReceive('listObjects')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Delimiter' => ':',
        'Prefix' => 'test'
      ))
      ->andReturn(array(
        'Contents' => array(
          // Deliberately NOT in date order in the raw listing —
          // StorageS3::list() sorts newest-first.
          array(
            'Key' => 'test/performant-labs_1111_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-01'),
            'Size' => '1234'
          ),
          array(
            'Key' => 'test/performant-labs_3333_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-03'),
            'Size' => '3333'
          ),
          array(
            'Key' => 'test/performant-labs_2222_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-02'),
            'Size' => '2222'
          )
        )
      ));
    // Latest by date is the 3333 key; that is what must be downloaded.
    $this->mockS3Client->shouldReceive('getObject')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Key' => 'test/performant-labs_3333_database.sql.gz',
        'SaveAs' => 'test.sql.gz'
      ))
      ->andReturn(array());

    $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'database']);
    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskSnapshotList')
      ->once()
      ->with(['name' => 'test', 'element' => 'database'])
      ->andReturn($snapshotList);
    $snapshotGet->setBuilder($mockBuilder);

    $result = $snapshotGet->run();
    self::assertEquals(0, $result->getExitCode());
  }

  /**
   * Pinned path: an explicit --snapshot selector must fetch THAT specific
   * object, not the latest. Here the latest is the 3333 key (2024-09-03)
   * but we pin the 1111 key (2024-09-01) — so a "picked table[0] by accident"
   * bug would download 3333 and fail this test.
   */
  public function testSnapshotGetPinnedReturnsSelected() {
    /** @var SnapshotGet $snapshotGet */
    $snapshotGet = $this->taskSnapshotGet([
      'name' => 'test',
      'output' => 'test.sql.gz',
      'element' => 'database',
      'snapshot' => 'test/performant-labs_1111_database.sql.gz',
    ]);

    $this->mockS3Client->shouldReceive('listObjects')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Delimiter' => ':',
        'Prefix' => 'test'
      ))
      ->andReturn(array(
        'Contents' => array(
          array(
            'Key' => 'test/performant-labs_1111_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-01'),
            'Size' => '1234'
          ),
          array(
            'Key' => 'test/performant-labs_3333_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-03'),
            'Size' => '3333'
          ),
          array(
            'Key' => 'test/performant-labs_2222_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-02'),
            'Size' => '2222'
          )
        )
      ));
    // The pinned 1111 key must be downloaded, NOT the newest 3333.
    $this->mockS3Client->shouldReceive('getObject')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Key' => 'test/performant-labs_1111_database.sql.gz',
        'SaveAs' => 'test.sql.gz'
      ))
      ->andReturn(array());

    $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'database']);
    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskSnapshotList')
      ->once()
      ->with(['name' => 'test', 'element' => 'database'])
      ->andReturn($snapshotList);
    $snapshotGet->setBuilder($mockBuilder);

    $result = $snapshotGet->run();
    self::assertEquals(0, $result->getExitCode());
  }

  /**
   * Not-found path: a --snapshot selector that matches nothing in the bucket
   * must fail loudly (non-zero exit) and NEVER silently fall back to latest.
   * The critical guarantee is that getObject is never called — if it were,
   * the operator would think they restored the pinned version but actually
   * got latest. Mockery verifies getObject is not invoked (no expectation set).
   */
  public function testSnapshotGetPinnedNotFoundFailsLoudly() {
    /** @var SnapshotGet $snapshotGet */
    $snapshotGet = $this->taskSnapshotGet([
      'name' => 'test',
      'output' => 'test.sql.gz',
      'element' => 'database',
      'snapshot' => 'test/performant-labs_9999_database.sql.gz',
    ]);

    $this->mockS3Client->shouldReceive('listObjects')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Delimiter' => ':',
        'Prefix' => 'test'
      ))
      ->andReturn(array(
        'Contents' => array(
          array(
            'Key' => 'test/performant-labs_1111_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-01'),
            'Size' => '1234'
          ),
          array(
            'Key' => 'test/performant-labs_2222_database.sql.gz',
            'LastModified' => new \DateTime('2024-09-02'),
            'Size' => '2222'
          )
        )
      ));
    // NO getObject expectation: if run() falls back to latest and downloads,
    // Mockery will fail the test on the unexpected call.

    $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'database']);
    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskSnapshotList')
      ->once()
      ->with(['name' => 'test', 'element' => 'database'])
      ->andReturn($snapshotList);
    $snapshotGet->setBuilder($mockBuilder);

    $result = $snapshotGet->run();
    self::assertEquals(1, $result->getExitCode());
  }

  public function testSnapshotGetNoResult() {
    /** @var SnapshotGet $snapshotGet */
    $snapshotGet = $this->taskSnapshotGet(['name' => 'test', 'output' => 'test.sql.gz', 'element' => 'database']);

    // Mock S3Client.
    $this->mockS3Client->shouldReceive('listObjects')
      ->once()
      ->with(array(
        'Bucket' => 'snapshot',
        'Delimiter' => ':',
        'Prefix' => 'test'
      ))
      ->andReturn(array(
        'Contents' => null
      ));

    // Now things are going tricky, since SnapshotGet uses
    // SnapshotList and it's not available because Testor
    // is not installed in the test environment.
    // So, we must mock builder once again (like in SnapshotCreateTest),
    // and make it return SnapshotList available here.
    $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'database']);
    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskSnapshotList')
      ->once()
      ->with(['name' => 'test', 'element' => 'database'])
      ->andReturn($snapshotList);
    $snapshotGet->setBuilder($mockBuilder);

    $result = $snapshotGet->run();
    self::assertEquals(1, $result->getExitCode());
  }

}