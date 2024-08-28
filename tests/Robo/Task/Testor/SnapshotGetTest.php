<?php

namespace PL\Tests\Robo\Task\Testor;

use Aws\S3\S3Client;
use PL\Tests\Robo\Task\Testor\TestorTestCase;

class SnapshotGetTest extends TestorTestCase
{
    public function testSnapshotGet()
    {
        $snapshotGet = $this->taskSnapshotGet(['name' => 'test', 'output' => 'test.sql.gz']);

        // Mock S3Client.
        $mockS3Client = \Mockery::mock(S3Client::class);
        $mockS3Client->shouldReceive('listObjects')
            ->once()
            ->with(array(
                'Bucket' => 'snapshot',
                'Delimiter' => ':',
                'Prefix' => 'test'
            ))
            ->andReturn(array(
                'Contents' => array(
                    array(
                        'Key' => 'test/1111.sql.gz',
                        'LastModified' => new \DateTime('2024-09-01'),
                        'Size' => '1234'
                    ),
                    array(
                        'Key' => 'test/2222.sql.gz',
                        'LastModified' => new \DateTime('2024-09-02'),
                        'Size' => '1324'
                    )
                )
            ));
        $mockS3Client->shouldReceive('getObject')
            ->once()
            ->with(array(
                'Bucket' => 'snapshot',
                'Key' => 'test/2222.sql.gz',
                'SaveAs' => 'test.sql.gz'
            ))
            ->andReturn(array());
        $snapshotGet->setS3Client($mockS3Client);

        // Now things are going tricky, since SnapshotGet uses
        // SnapshotList and it's not available because Testor
        // is not installed in the test environment.
        // So, we must mock builder once again (like in SnapshotCreateTest),
        // and make it return SnapshotList available here.
        $snapshotList = $this->taskSnapshotList(['name' => 'test']);
        $snapshotList->setS3Client($mockS3Client);
        $mockBuilder = $this->mockCollectionBuilder();
        $mockBuilder->shouldReceive('taskSnapshotList')
            ->once()
            ->with(['name' => 'test'])
            ->andReturn($snapshotList);
        $snapshotGet->setBuilder($mockBuilder);

        $result = $snapshotGet->run();
        $this->assertEquals(0, $result->getExitCode());
    }
}