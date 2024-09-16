<?php

namespace PL\Tests\Robo\Task\Testor;

use Aws\S3\S3Client;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use League\Container\DefinitionContainerInterface;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Robo\Collection\CollectionBuilder;
use Robo\Robo;
use Robo\Task\Base\Exec;
use Robo\TaskAccessor;
use Symfony\Component\Console\Output\NullOutput;

class TestorTestCase extends MockeryTestCase implements ContainerAwareInterface
{
    use \phpmock\phpunit\PHPMock;
    use \Robo\Task\Base\Tasks;
    use \PL\Robo\Task\Testor\Tasks;
    use TaskAccessor;
    use ContainerAwareTrait;

    protected LegacyMockInterface|S3Client|MockInterface $mockS3Client;

    function setUp(): void
    {
        // Set up the Robo container so that we can create tasks in our tests.
        $container = Robo::createDefaultContainer(null, new NullOutput());

        // Set up test dependencies.
        $container->add('testorConfig', new \Consolidation\Config\Config(['pantheon' => ['site' => 'performant-labs'], 's3' => ['config' => '**DUMMY**', 'bucket' => 'snapshot'], 'tugboat' => ['repo' => '1reporepo1']]));
        $container->add('s3Client', $this->mockS3Client = $this->mockS3Client());
        $container->add('s3Bucket', 'snapshot');

        $this->setContainer($container);
    }

    public function collectionBuilder(): CollectionBuilder
    {
        // Scaffold the collection builder
        $emptyRobofile = new \Robo\Tasks;
        // We have to have non-null builder in Robofile to avoid
        // exception during task assessing in Robo internals
        $emptyRobofile->setBuilder(new CollectionBuilder($emptyRobofile));
        return CollectionBuilder::create($this->getContainer(), $emptyRobofile);
    }

    /**
     * @param mixed ...$result_args argiments for \Robo\Result.
     * @return Exec&MockInterface&LegacyMockInterface
     */
    public function mockTaskExec(...$result_args): MockInterface&\Robo\Task\Base\Exec&LegacyMockInterface
    {
        $mockExec = \Mockery::mock(\Robo\Task\Base\Exec::class);
        if ($result_args[0] instanceof \Robo\Result) {
            $result = $result_args[0];
        } else {
            $result = new \Robo\Result(...$result_args);
            $result->provideOutputdata();
        }
        // We can call printOutput but don't care about its argument so far.
        $mockExec->shouldReceive('printOutput')->andReturn($mockExec);
        $mockExec->shouldReceive('run')->once()->andReturn($result);
        return $mockExec;
    }

    /**
     * Mock collection builder with the ultimate goal to mock taskExec
     * (or other tasks that are used in the task under test).
     *
     * @return CollectionBuilder|MockInterface|LegacyMockInterface
     */
    public function mockCollectionBuilder(): CollectionBuilder|MockInterface|LegacyMockInterface
    {
        // Mock taskExec to verify executed commands.
        // taskExec is received through builder.
        // So, we must replace builder with a mock.
        // And then this builder will execute mock itself.
        $mockBuilder = \Mockery::mock(CollectionBuilder::class);

        // At BuilderAwareTrait, builder is received as
        // `$this->getBuilder()->newBuilder()->inflect($this)->inflect($io)`
        // so we must mock all the methods in the chain to finally
        // return our mock.
        $mockBuilder->shouldReceive('newBuilder')->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('inflect')->andReturn($mockBuilder);
        return $mockBuilder;
    }

    public function mockS3Client(): S3Client|MockInterface|LegacyMockInterface
    {
        $this->mockS3Client ??= \Mockery::mock(S3Client::class);
        return $this->mockS3Client;
    }

    public function mockBuiltIn($function)
    {
        // "Native" php-mock-phpunit MockObject
        $mock = $this->getFunctionMock('\\PL\\Robo\\Task\\Testor\\', $function);

        // Our wrapper
        $mock = new Helper\MockBuiltIn($mock, $function);

        // Steal 'registerForTeardown' method to register our mock's assertion as well
        $this->registerForTearDown($mock);
        return $mock;
    }
}