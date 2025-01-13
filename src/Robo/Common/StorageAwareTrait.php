<?php

namespace PL\Robo\Common;

use PL\Robo\Contract\StorageInterface;

trait StorageAwareTrait {
  protected StorageInterface $storage;

  public function getStorage(): StorageInterface {
    return $this->storage;
  }

  public function setStorage(StorageInterface $storage): static {
    $this->storage = $storage;
    return $this;
  }

}