<?php

namespace PL\Robo;

use Robo\Robo;
use Robo\Runner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestorApplication
{
    const APPLICATION_NAME = 'Testor';
    const REPOSITORY = 'Performant-Labs/testor';

    private Runner $runner;
    private $consoleOutput;

    public function __construct($classLoader)
    {
        // Create and configure container.
        $container = Testor::createContainer();

        // Initialize Robo Runner.
        $this->consoleOutput = $container->get('output');
        $configFilePath = getenv('ROBO_CONFIG') ?: getenv('HOME') . '/.robo/robo.yml';
        $runner = new \Robo\Runner(\PL\Robo\Plugin\Commands\TestorCommands::class);
        $runner
            ->setRelativePluginNamespace('Robo\Plugin')
            ->setSelfUpdateRepository(self::REPOSITORY)
            ->setConfigurationFilename($configFilePath)
            ->setEnvConfigPrefix('ROBO')
            ->setClassLoader($classLoader);

        // Befriend container with runner.
        $runner->setContainer($container);

        $this->runner = $runner;
    }

    public function run($argv): int
    {
        $version = \Composer\InstalledVersions::getVersion('performantlabs/testor');
        $statusCode = $this->runner->execute($argv, self::APPLICATION_NAME, $version, $this->consoleOutput);
        return $statusCode;
    }
}