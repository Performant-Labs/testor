<?php

namespace PL\Robo\Common;

use PL\Robo\Contract\S3BucketAwareInterface;
use PL\Robo\Contract\S3ClientAwareInterface;
use PL\Robo\Contract\StorageAwareInterface;
use PL\Robo\Contract\TestorConfigAwareInterface;
use PL\Robo\Testor;

trait TestorDependencyInjectorTrait {
  public function injectTestorDependencies() {
    // We have BaseTask->injectDependencies() which passes dependencies
    // created by Robo like a waterfall from parent to child.
    // Since it's not clear how to add dependencies specific to the
    // custom task, like in our case (S3, Tugboat, Pantheon etc.),
    // and also `inflector()` makes no affect, let inject them here.
    // Call this method on Task or other appropriate object constructor.
    $container = \Robo\Robo::getContainer();
    Testor::configureContainer($container);
    if ($this instanceof TestorConfigAwareInterface) {
      $this->setTestorConfig(Testor::getTestorConfig());
    }
    if ($this instanceof S3ClientAwareInterface) {
      $this->setS3Client(Testor::getS3Client());
    }
    if ($this instanceof S3BucketAwareInterface) {
      $this->setS3Bucket(Testor::getS3Bucket());
    }
    if ($this instanceof StorageAwareInterface) {
      $this->setStorage(Testor::getStorage());
    }
  }

}