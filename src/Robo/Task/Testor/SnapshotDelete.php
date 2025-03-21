<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\StorageAwareTrait;
use PL\Robo\Contract\StorageAwareInterface;
use Robo\Symfony\ConsoleIO;

class SnapshotDelete extends TestorTask implements StorageAwareInterface {
  use StorageAwareTrait;

  /**
   * @var string[] Names of objects to delete.
   */
  protected array $names;

  public function __construct(string... $names) {
    parent::__construct();
    $this->names = $names;
  }

  public function run(): \Robo\Result {
    $this->storage->delete($this->names);
    $count = count($this->names);
    $this->message = "Deleted {$count} objects";
    return $this->pass();
  }

}