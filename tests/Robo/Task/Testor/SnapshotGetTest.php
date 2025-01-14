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