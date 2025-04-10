<?php

namespace PL\Robo\Task\Testor;

use Robo\Task\BaseTask;

class Echo_ extends BaseTask
{

  protected \Robo\Result $result;

  public function __construct(\Robo\Result $result)
  {
    $this->result = $result;
  }

  /**
   * @inheritDoc
   */
  public function run(): \Robo\Result
  {
    $this->getOutput()->writeln($this->result->getMessage());
    return $this->result;
  }
}