<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;

class SnapshotViaBackup extends TestorTask implements TestorConfigAwareInterface {
  use TestorConfigAwareTrait;

  protected string $env;
  protected string $element;
  protected string $name;
  protected string $filename;

  public function __construct(array $opts) {
    parent::__construct();
    $this->env = $opts['env'];
    $this->element = $opts['element'];
    $this->name = $opts['name'];
    $this->filename = $opts['filename'];
  }

  public function run(): \Robo\Result {
    if (!$this->checkTerminus()) {
      return $this->fail();
    }

    $site = $this->testorConfig->get('pantheon.site');
    $env = $this->env;
    $result = $this->exec("terminus backup:create $site.$env --element=$this->element --keep-for=1");
    if ($result->getExitCode() !== 0) {
      return $result;
    }
    $result = $this->exec("terminus backup:list $site.$env --format=json", $output);
    if ($result->getExitCode() !== 0) {
      return $result;
    }
    $backups = json_decode($result->getMessage());
    $array = (array) $backups;
    $file = reset($array)->file;

    $result = $this->exec("terminus backup:get $site.$env --file=$file --to={$this->filename}.tar.gz");
    if ($result->getExitCode() !== 0) {
      return $result;
    }

    return $this->pass();
  }

}