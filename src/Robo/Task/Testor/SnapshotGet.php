<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\StorageAwareTrait;
use PL\Robo\Contract\StorageAwareInterface;
use Robo\Common\BuilderAwareTrait;

class SnapshotGet extends TestorTask
  implements StorageAwareInterface {
  use BuilderAwareTrait;
  use StorageAwareTrait;

  protected string $name;
  protected ?string $filename;
  protected string $element;

  function __construct(array $args) {
    parent::__construct();
    $this->name = $args['name'];
    $this->filename = $args['output'];
    $this->element = $args['element'];
  }

  public function run() {
    /** @var SnapshotList $taskSnapshotList */
    $taskSnapshotList = $this->collectionBuilder()->taskSnapshotList(array('name' => $this->name, 'element' => $this->element));
    $result = $taskSnapshotList->run();
    if (!(bool) $result['table']) {
      return $this->fail();
    }
    // SnapshotList task returns `table` which
    // contains a datetime-sorted array of objects.
    // Key is the name of the first object.
    $name = $result['table'][0]['Name'];
    $array = explode('/', $name);
    $this->filename = $this->filename ?? end($array);
    $this->storage->get($name, $this->filename);
    $this->message = "Downloaded $name => $this->filename";
    return $this->pass();
  }

}