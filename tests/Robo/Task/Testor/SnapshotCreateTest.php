<?php

namespace PL\Tests\Robo\Task\Testor {

    use PL\Robo\Common\StorageS3;
    use PL\Robo\Task\Testor\SnapshotCreate;

    class SnapshotCreateTest extends TestorTestCase
    {
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
            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => '', 'element' => 'database']);
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
            // Mock shell_exec (for `isExecutable`)
            $mockShellExec = $this->mockBuiltIn('shell_exec');
            $mockShellExec->expects(self::once())
                ->with('which terminus')
                ->willReturn('/usr/bin/terminus');

            $mockBuilder = $this->mockCollectionBuilder();

            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => 'test', 'element' => 'database']);
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
                ->andReturn($this->mockTaskExec($snapshotCreate, 0, '{"2": {"file": "performant-labs_11111_database.sql.gz"}, "1": {"file": "performant-labs_22222_database.sql.gz"}}'));
            // Command #3
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:get performant-labs.dev --file=performant-labs_11111_database.sql.gz --to=performant-labs_11111_database.sql.gz')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            $snapshotCreate->setBuilder($mockBuilder);

            // Mock S3Client.
            $this->mockS3Client
                ->shouldReceive('putObject')
                ->once()
                ->with(array(
                    'Bucket' => 'snapshot',
                    'Key' => 'test/performant-labs_11111_database.sql.gz',
                    'SourceFile' => 'performant-labs_11111_database.sql.gz'
                    ))
                ->andReturn(new \Aws\Result());
            $result = $snapshotCreate->run();
            $this->assertEquals(0, $result->getExitCode());
        }

        public function testSnapshotCreateFiles()
        {
            // Mock shell_exec (for `isExecutable`)
            $mockShellExec = $this->mockBuiltIn('shell_exec');
            $mockShellExec->expects(self::once())
                ->with('which terminus')
                ->willReturn('/usr/bin/terminus');

            $mockBuilder = $this->mockCollectionBuilder();

            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => 'test', 'element' => 'files']);
            // Command #1
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:create performant-labs.dev --element=files')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            // Command #2
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:list performant-labs.dev --format=json')
                ->andReturn($this->mockTaskExec($snapshotCreate, 0, '{"2": {"file": "performant-labs_11111_files.sql.gz"}, "1": {"file": "performant-labs_22222_files.sql.gz"}}'));
            // Command #3
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:get performant-labs.dev --file=performant-labs_11111_files.sql.gz --to=performant-labs_11111_files.sql.gz')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            $snapshotCreate->setBuilder($mockBuilder);

            // Mock S3Client.
            $this->mockS3Client
                ->shouldReceive('putObject')
                ->once()
                ->with(array(
                    'Bucket' => 'snapshot',
                    'Key' => 'test/performant-labs_11111_files.sql.gz',
                    'SourceFile' => 'performant-labs_11111_files.sql.gz'
                ))
                ->andReturn(new \Aws\Result());
            $result = $snapshotCreate->run();
            $this->assertEquals(0, $result->getExitCode());
        }

        public function testTerminusNotFound()
        {
            $mockShellExec = $this->mockBuiltIn('shell_exec');
            $mockShellExec->expects(self::once())
                ->with('which terminus')
                ->willReturn('');

            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => '', 'element' => 'database']);
            $result = $snapshotCreate->run();
            $this->assertEquals(1, $result->getExitCode());
            $this->assertStringContainsString('Please install and configure terminus', $result->getMessage());
        }

        public function testTerminusError()
        {
            // Mock shell_exec (for `isExecutable`)
            $mockShellExec = $this->mockBuiltIn('shell_exec');
            $mockShellExec->expects(self::once())
                ->with('which terminus')
                ->willReturn('/usr/bin/terminus');

            $mockBuilder = $this->mockCollectionBuilder();

            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => 'test', 'element' => 'database']);
            // Command #1
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:create performant-labs.dev --element=database')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 1, 'SPOOKY SCARY ERROR')));
            $snapshotCreate->setBuilder($mockBuilder);

            $result = $snapshotCreate->run();
            $this->assertEquals(1, $result->getExitCode());
            $this->assertStringContainsString('SPOOKY SCARY ERROR', $result->getMessage());
        }
    }
}
