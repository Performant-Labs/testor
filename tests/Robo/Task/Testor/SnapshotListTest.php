<?php

namespace PL\Tests\Robo\Task\Testor;

use PL\Robo\Task\Testor\SnapshotList;

class SnapshotListTest extends TestorTestCase
{
    public function testSnapshotList()
    {
        /** @var SnapshotList $snapshotList */
        $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'database']);

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
                        'Size' => '1324'
                    ),
                    array(
                        'Key' => 'test/performant-labs_2222_files.sql.gz',
                        'LastModified' => new \DateTime('2024-09-02'),
                        'Size' => '2134'
                    ),
                    array(
                        'Key' => 'test/other-site_3333_database.sql.gz',
                        'LastModified' => new \DateTime('2024-09-03'),
                        'Size' => '2134'
                    ),
                    array(
                        'Key' => 'test/performant-labs_4444_database.sql.gz',
                        'LastModified' => new \DateTime('2024-09-04'),
                        'Size' => '2134'
                    )
                )
            ));
        $result = $snapshotList->run();
        $this->assertEquals(0, $result->getExitCode());
        $this->assertEquals([
            [
                'Name' => 'test/performant-labs_4444_database.sql.gz',
                'Date' => new \DateTime('2024-09-04'),
                'Size' => '2134'
            ],
            [
                'Name' => 'test/performant-labs_1111_database.sql.gz',
                'Date' => new \DateTime('2024-09-01'),
                'Size' => '1324'
            ]
        ], $result['table']);
    }

    public function testSnapshotListFiles()
    {
        /** @var SnapshotList $snapshotList */
        $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'files']);

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
                        'Size' => '1324'
                    ),
                    array(
                        'Key' => 'test/performant-labs_2222_files.sql.gz',
                        'LastModified' => new \DateTime('2024-09-02'),
                        'Size' => '2134'
                    )
                )
            ));
        $result = $snapshotList->run();
        $this->assertEquals(0, $result->getExitCode());
        $this->assertEquals([
            [
                'Name' => 'test/performant-labs_2222_files.sql.gz',
                'Date' => new \DateTime('2024-09-02'),
                'Size' => '2134'
            ],
        ], $result['table']);
    }

    public function testSnapshotListEmpty()
    {
        $snapshotList = $this->taskSnapshotList(['name' => 'test', 'element' => 'database']);

        // Mock S3Client.
        $this->mockS3Client->shouldReceive('listObjects')
            ->once()
            ->with(array(
                'Bucket' => 'snapshot',
                'Delimiter' => ':',
                'Prefix' => 'test'
            ))
            ->andReturn(array('Contents' => null));
        $result = $snapshotList->run();
        $this->assertEquals(0, $result->getExitCode());
        $this->assertEquals([], $result['table']);
//        TODO assert warning message.
    }
}