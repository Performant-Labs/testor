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
  protected ?string $snapshot;

  function __construct(array $args) {
    parent::__construct();
    $this->name = $args['name'];
    $this->filename = $args['output'] ?? null;
    $this->element = $args['element'];
    // Optional version pin. When null (default), the latest snapshot is
    // used, preserving pre-existing behavior. When set, it must match a
    // snapshot's `Name` exactly, or the task fails loudly (no silent
    // fallback to latest).
    $this->snapshot = $args['snapshot'] ?? null;
  }

  public function run() {
    /** @var SnapshotList $taskSnapshotList */
    $taskSnapshotList = $this->collectionBuilder()->taskSnapshotList(array('name' => $this->name, 'element' => $this->element));
    $result = $taskSnapshotList->run();
    if (!$result->wasSuccessful()) {
      return $result;
    }
    if (!(bool) $result['table']) {
      return $this->fail();
    }
    // SnapshotList task returns `table` which
    // contains a datetime-sorted array of objects (newest first).
    if ($this->snapshot === null) {
      // Default: take the latest (newest) snapshot — table[0].
      $name = $result['table'][0]['Name'];
    }
    else {
      // Pinned: select the snapshot whose `Name` matches exactly.
      // An exact match is deliberate — a forgiving substring/prefix match
      // could silently grab the wrong snapshot, which is strictly worse
      // than a clear "not found" error.
      $name = null;
      foreach ($result['table'] as $row) {
        if ($row['Name'] === $this->snapshot) {
          $name = $row['Name'];
          break;
        }
      }
      if ($name === null) {
        // Fail loudly — never silently fall back to latest.
        $this->message = "No snapshot named \"$this->snapshot\" found for name \"$this->name\" (element \"$this->element\").";
        return $this->fail();
      }
    }
    $array = explode('/', $name);
    $this->filename = $this->filename ?? end($array);
    $this->storage->get($name, $this->filename);
    $this->message = "Downloaded $name => $this->filename";
    return $this->pass(['filename' => $this->filename]);
  }

}