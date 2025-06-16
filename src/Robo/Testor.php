<?php

namespace PL\Robo;

use Consolidation\Config\Loader\ConfigProcessor;
use PL\Robo\Common\StorageStrategy;
use PL\Robo\Common\TestorConfig;
use Psr\Container\ContainerInterface;
use Robo\Robo;

/**
 * A bunch of static methods that extend corresponding Robo methods.
 */
class Testor {
  public static bool $isConfigured = false;

  /**
   * @return ContainerInterface|\League\Container\Container
   */
  static function createContainer(): ContainerInterface|\League\Container\Container {
    $container = Robo::createDefaultContainer();
    self::configureContainer($container);

    // Robo requires this for some reason.
    Robo::finalizeContainer($container);

    return $container;
  }

  /**
   * @param ContainerInterface|\League\Container\Container|null $container
   * @return void
   */
  public static function configureContainer(ContainerInterface|\League\Container\Container|null $container): void {
    if (self::$isConfigured) {
      return;
    }

    // TestorConfig (which is different from Config. Config has task and
    // its default arguments configuration, while TestorConig contains
    // common configuration for the domain which Testor is used for.)
    // TestorConfig will be initialized once and for all.
    $testorConfig = self::createConfiguration(['.testor.yml', '.testor_secret.yml']);
    Robo::addShared($container, 'testorConfig', $testorConfig);

    // Register Testor-specific services.
    Robo::addShared($container, 's3Client', \Aws\S3\S3Client::class)
      ->addArgument($testorConfig->get('s3.config'));
    Robo::addShared($container, 's3Bucket', $testorConfig->get('s3.bucket'));
    Robo::addShared($container, 'storage', StorageStrategy::class);

//        TODO Figure out how they work so we can get rid of injectTestorDependencies()
    // Register Testor-specific inflectors.
    $container->inflector(\PL\Robo\Contract\TestorConfigAwareInterface::class)
      ->invokeMethod('setTestorConfig', ['testorConfig']);
    $container->inflector(\PL\Robo\Contract\S3ClientAwareInterface::class)
      ->invokeMethod('setS3Client', ['s3Client']);
    $container->inflector(\PL\Robo\Contract\S3BucketAwareInterface::class)
      ->invokeMethod('setS3Bucket', ['s3Bucket']);

    self::$isConfigured = true;
  }

  public static function getTestorConfig() {
    return Robo::getContainer()->get('testorConfig');
  }

  public static function getS3Client() {
    return Robo::getContainer()->get('s3Client');
  }

  public static function getS3Bucket() {
    return Robo::getContainer()->get('s3Bucket');
  }

  public static function getStorage() {
    return Robo::getContainer()->get('storage');
  }

  /**
   * @param $paths
   * @return \Consolidation\Config\ConfigInterface|\Robo\Config\Config
   */
  public static function createConfiguration($paths): \Robo\Config\Config|\Consolidation\Config\ConfigInterface {
    $config = new TestorConfig();
    Robo::loadConfiguration($paths, $config);

    // Merge all env variables to achieve var substitution.
    $processor = new ConfigProcessor();
    $processor->add($config->export());
    $processor->add(getenv());
    $config->import($processor->export());
    return $config;
  }

}