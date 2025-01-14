<?php

namespace PL\Robo\DO;

class Snapshot {
  public SnapshotLocal $local;
  public SnapshotRemote $remote;

  public function __construct() {
    $this->local = new SnapshotLocal();
    $this->remote = new SnapshotRemote();
  }

}
