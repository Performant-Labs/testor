<?php

namespace PL\Tests\Robo\Task\Testor {

    use Aws\S3\S3Client;
    use League\Container\ContainerAwareInterface;
    use League\Container\ContainerAwareTrait;
    use Mockery\LegacyMockInterface;
    use Mockery\MockInterface;
    use PL\Robo\Task\Testor\SnapshotCreate;
    use Robo\Collection\CollectionBuilder;
    use Robo\Robo;
    use Robo\Task\Base\Exec;
    use Robo\TaskAccessor;
    use Symfony\Component\Console\Output\NullOutput;

    class SnapshotCreateTest extends TestorTestCase
    {

//        public function testInjected()
//        {
//            $this->assertSame($this->mockS3, $this->mockSnapshotCreate()->getS3Client());
//        }

        /**
         * @param $command
         * @dataProvider providerCommand
         * @return void
         */
        public function testExec($command)
        {
            // Test that exec() method actually executes command
            // and returns its return code and output.
            /** @var SnapshotCreate $snapshotCreate */
            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => '']);
            $result = $snapshotCreate->exec($command, $output);

            // Reference result through built-in exec.
            \exec($command, $lines, $code);
            // We can get text result either as $output, or as $result->getMessage().
            // $ouptut work in the actual task but doesn't work here.
            // I have no idea why.
            $this->assertEquals(implode("\n", $lines), $result->getMessage());
            $this->assertEquals($code, $result->getExitCode());
        }

        /**
         * @dataProvider
         */
        public static function providerCommand(): array
        {
            return [
                // this should work both on Linux and Windows
                ['hostname'],
                // exit with error and print usage
                ['hostname --malformed'],
                // non-existing command
                ['non-existing-command'],
            ];
        }

        public function testSnapshotCreate()
        {
            $mockBuilder = $this->mockCollectionBuilder();

            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => 'test']);
            // Command #1
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:create performant-labs.dev --element=database')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            // Command #2
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:list performant-labs.dev --format=json')
                ->andReturn($this->mockTaskExec($snapshotCreate, 0, '{"2": {"file": "11111.sql.gz"}, "1": {"file": "22222.sql.gz"}}'));
            // Command #3
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:get performant-labs.dev --file=11111.sql.gz --to=11111.sql.gz')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            $snapshotCreate->setBuilder($mockBuilder);

            // Mock S3Client.
            $mockS3Client = \Mockery::mock(S3Client::class);
            $mockS3Client
                ->shouldReceive('putObject')
                ->with(array(
                    'Bucket' => 'snapshot',
                    'Key' => 'test/11111.sql.gz',
                    'SourceFile' => '11111.sql.gz'
                    ))
                ->andReturn(new \Aws\Result());
            $snapshotCreate->setS3Client($mockS3Client);
            $result = $snapshotCreate->run();
            $this->assertEquals(0, $result->getExitCode());
        }

        public function testTerminusNotFound()
        {
//            TODO
        }

        public function testTerminusError()
        {
//            TODO
        }
    }
}
