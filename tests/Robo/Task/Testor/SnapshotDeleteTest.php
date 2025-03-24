<?php

namespace PL\Tests\Robo\Task\Testor;

class SnapshotDeleteTest extends TestorTestCase {

  public function testSnapshotDelete() {
    $snapshotDelete = $this->taskSnapshotDelete('fooo', 'barr');

    // MocK S3 Client.
    $this->mockS3Client->shouldReceive('deleteObjects')
      ->once()
      ->with([
        'Bucket' => 'snapshot',
        'Delete' => [
          'Objects' => [
            ['Key' => 'fooo'], ['Key' => 'barr'],
          ]
        ]
      ]);

    $result = $snapshotDelete->run();
    self::assertEquals(0, $result->getExitCode());
  }
}