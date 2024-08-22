<?php

namespace PL\Tests\Robo\Task\Testor {

    use Aws\S3\S3Client;
    use League\Container\ContainerAwareInterface;
    use League\Container\ContainerAwareTrait;
    use PL\Robo\Task\Testor\SnapshotCreate;
    use Robo\Collection\CollectionBuilder;
    use Robo\Robo;
    use Robo\TaskAccessor;
    use Symfony\Component\Console\Output\NullOutput;

    class SnapshotCreateTest extends \PHPUnit\Framework\TestCase implements ContainerAwareInterface
    {
        use \PL\Robo\Task\Testor\Tasks;
        use TaskAccessor;
        use ContainerAwareTrait;

        private MockExec $mock;
        private MockIsExecutable $mockIsExecutable;
        /**
         * @var S3Client|(S3Client&object&\PHPUnit\Framework\MockObject\MockObject)|(S3Client&\PHPUnit\Framework\MockObject\MockObject)|(object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject
         */
        private \PHPUnit\Framework\MockObject\MockObject $mockS3;

        function setup(): void
        {
            // Set up `exec` mock.
            $this->mock = new MockExec();
            $this->mockIsExecutable = new MockIsExecutable();

            // $this->createMock(...) doesn't see method
            $this->mockS3 = $this->getMockBuilder(S3Client::class)->disableOriginalConstructor()->addMethods(array('putObject'))->getMock();

            // Set up the Robo container so that we can create tasks in our tests.
            $container = Robo::createDefaultContainer(null, new NullOutput());
            $this->setContainer($container);
        }

        public function collectionBuilder(): CollectionBuilder
        {
            // Scaffold the collection builder
            $emptyRobofile = new \Robo\Tasks;
            return CollectionBuilder::create($this->getContainer(), $emptyRobofile);
        }
//
//        public function testInjected()
//        {
//            $this->assertSame($this->mockS3, $this->mockSnapshotCreate()->getS3Client());
//        }

        public function testSnapshotCreate()
        {
            // Mock assertions turned upside down in phpunit :(
            $this->mockS3->expects($this->once())
                ->method('putObject')
                ->with(array(
                    'Bucket' => 'snapshot',
                    'Key' => '11111.sql.gz',
                    'SourceFile' => '11111.sql.gz'
                    ))
                ->willReturn(new \Aws\Result());

            $this->mockIsExecutable->set('terminus', true);
            $this->mock->on('terminus backup:create', 'OK', 0);
            $this->mock->on('terminus backup:list', '{"2": {"file": "11111.sql.gz"}, "1": {"file": "22222.sql.gz"}}', 0);
            $this->mock->on('terminus backup:get', 'OK', 0);
            $result = $this->mockSnapshotCreate()->run();
            $this->assertEquals(0, $result->getExitCode());
            $this->assertEquals(array(
                'terminus backup:create performant-labs.dev --element=database',
                'terminus backup:list performant-labs.dev --format=json',
                'terminus backup:get performant-labs.dev --file=11111.sql.gz --to=11111.sql.gz'
            ), $this->mock->getCallList());
        }

        /**
         * @return SnapshotCreate
         */
        public function mockSnapshotCreate(): CollectionBuilder
        {
//          TODO remove this method, inject dependencies properly via Container
            $snapshotCreate = $this->taskSnapshotCreate();
            $snapshotCreate->setS3Client($this->mockS3);
            return $snapshotCreate;
        }
    }
}
