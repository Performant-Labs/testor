<?php

namespace PL\Tests\Robo\Task\Testor;

use Aws\S3\S3Client;
use PL\Tests\Robo\Task\Testor\TestorTestCase;

class SnapshotListTest extends TestorTestCase
{
    public function testSnapshotList()
    {
        $snapshotList = $this->taskSnapshotList(['name' => 'test']);

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
                        'Size' => '1324'
                    ),
                    array(
                        'Key' => 'test/2222.sql.gz',
                        'LastModified' => new \DateTime('2024-09-02'),
                        'Size' => '2134'
                    )
                )
            ));
        $snapshotList->setS3Client($mockS3Client);
        $result = $snapshotList->run();
        $this->assertEquals(0, $result->getExitCode());
        $this->assertEquals([
            [
                'Name' => 'test/2222.sql.gz',
                'Date' => new \DateTime('2024-09-02'),
                'Size' => '2134'
            ],
            [
                'Name' => 'test/1111.sql.gz',
                'Date' => new \DateTime('2024-09-01'),
                'Size' => '1324'
            ]
        ], $result['table']);
    }

    public function testSnapshotListEmpty()
    {
        $snapshotList = $this->taskSnapshotList(['name' => 'test']);

        // Mock S3Client.
        $mockS3Client = \Mockery::mock(S3Client::class);
        $mockS3Client->shouldReceive('listObjects')
            ->once()
            ->with(array(
                'Bucket' => 'snapshot',
                'Delimiter' => ':',
                'Prefix' => 'test'
            ))
            ->andReturn(array('Contents' => null));
        $snapshotList->setS3Client($mockS3Client);
        $result = $snapshotList->run();
        $this->assertEquals(0, $result->getExitCode());
        $this->assertEquals([], $result['table']);
//        TODO assert warning message.
    }
}