<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\StorageAwareTrait;
use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\StorageAwareInterface;
use PL\Robo\Contract\TestorConfigAwareInterface;

class SnapshotList extends TestorTask
  implements StorageAwareInterface, TestorConfigAwareInterface {
  use StorageAwareTrait;
  use TestorConfigAwareTrait;

  protected string $name;
  protected string $element;

  function __construct(array $args) {
    parent::__construct();
    $this->name = $args['name'];
    $this->element = $args['element'];
  }

  public function run(): \Robo\Result {
    $table = $this->storage->list($this->name);

    if (count($table) === 0) {
      $this->printTaskWarning("There are no snapshots by name \"$this->name\"");
    }
    // Filter out elements
    $table = array_values(array_filter($table, fn($value) => str_contains($value['Name'], $this->element) && str_contains($value['Name'], $this->testorConfig->get('pantheon.site', ''))));
    return new \Robo\Result($this, 0, '', array('table' => $table));
  }

}