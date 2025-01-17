<?php

namespace PL\Robo\Task\Testor;

class TugboatPreviewCreate extends TugboatTask {

  protected string|null $base;

  public function __construct(array $opts) {
    parent::__construct();
    $this->base = $opts['base'];
  }

  public function run() {
    if (!$this->initTugboat()) {
      return $this->fail();
    }

    $runDate = date("Y-m-d H:i:s");
    $githubBranch = getenv('GITHUB_BRANCH');
    if (!(bool) $githubBranch) {
      $result = $this->exec('git branch --no-color --show-current', $githubBranch);
      if ($result->getExitCode() !== 0) {
        return $result;
      }
      $githubBranch = trim($githubBranch);
    }
    $label = "Branch:$githubBranch $runDate";

    $this->printTaskInfo("Creating preview ($label).");
    $command = "$this->tugboat create preview \"$githubBranch\"";
    if ($this->base) {
      $command .= " base=$this->base";
    }
    $command .= " repo=$this->repo label=\"$label\" output=json";
    $result = $this->exec($command, $output);
    if ($result->getExitCode() !== 0) {
      return $result;
    }
    $result['preview'] = json_decode($output, true);
    return $result;
  }

}