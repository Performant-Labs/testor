<?php

namespace PL\Robo\Task\Testor {

    use League\Container\ContainerAwareInterface;
    use League\Container\ContainerAwareTrait;
    use Symfony\Component\Console\Output\NullOutput;
    use Robo\TaskAccessor;
    use Robo\Robo;
    use Robo\Collection\CollectionBuilder;

    require_once 'tests/MockExec.php';
    require_once 'tests/MockIsExecutable.php';

    class SnapshotCreateTest extends \PHPUnit\Framework\TestCase implements ContainerAwareInterface
    {
        use \PL\Robo\Task\Testor\Tasks;
        use TaskAccessor;
        use ContainerAwareTrait;

        private MockExec $mock;
        private MockIsExecutable $mockIsExecutable;

        function setup(): void
        {
            // Set up the Robo container so that we can create tasks in our tests.
            $container = Robo::createDefaultContainer(null, new NullOutput());
            $this->setContainer($container);

            // Set up `exec` mock.
            $this->mock = new MockExec();
            $this->mockIsExecutable = new MockIsExecutable();
        }

        public function collectionBuilder(): CollectionBuilder
        {
            // Scaffold the collection builder
            $emptyRobofile = new \Robo\Tasks;
            return CollectionBuilder::create($this->getContainer(), $emptyRobofile);
        }

        public function testSnapshotCreate()
        {
            $this->mockIsExecutable->set('terminus', true);
            $this->mock->on('terminus backup:create', 'OK', 0);
            $this->mock->on('terminus backup:list', '[{"file": "11111.sql.gz"}, {"file": "22222.sql.gz"}]', 0);
            $this->mock->on('terminus backup:get', 'OK', 0);
            $this->taskSnapshotCreate()->run();
            $this->assertEquals(array(
                'terminus backup:create performant-labs.dev --element=database',
                'terminus backup:list performant-labs.dev --format=json',
                'terminus backup:get performant-labs.dev --file=11111.sql.gz --to=11111.sql.gz'
            ), $this->mock->getCallList());
        }
    }
}
