<?php

namespace PL\Robo;

use Robo\Robo;
use Robo\Runner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestorApplication {
  const APPLICATION_NAME = 'Testor';
  const REPOSITORY = 'Performant-Labs/testor';
  const PACKAGENAME = 'performant-labs/testor';

  private Runner $runner;
  private $consoleOutput;

  public function __construct($classLoader) {
// Theoretically, that is how we should set a custom container.
// But in practice, Robo skips a whole bunch of usefulnesses if
// container is set from the user code and not created while run()
// so let skip it and set out dependencies later on. E.g. within
// Task constructor, because this one Robo won't be able to bypass...
//        // Create and configure container.
//        $container = Testor::createContainer();

    // Initialize Robo Runner.
//        $this->consoleOutput = $container->get('output');
    if (getenv('ROBO_CONFIG')) {
      $configFilePath = getenv('ROBO_CONFIG');
    }
    else {
      $configFilePath = getenv('HOME') . '/.robo/robo.yml';
    }
    $runner = new \Robo\Runner(\PL\Robo\Plugin\Commands\TestorCommands::class);
    $runner
      ->setRelativePluginNamespace('Robo\Plugin')
      ->setSelfUpdateRepository(self::REPOSITORY)
      ->setConfigurationFilename($configFilePath)
      ->setEnvConfigPrefix('ROBO')
      ->setClassLoader($classLoader);

    // Befriend container with runner.
//        $runner->setContainer($container);

    $this->runner = $runner;
  }

  public function run($argv): int {
    $version = \Composer\InstalledVersions::getVersion(self::PACKAGENAME);
    $statusCode = $this->runner->execute($argv, self::APPLICATION_NAME, $version);
    return $statusCode;
  }

}