<?php

namespace PL\Robo\Task\Testor;

class TugboatPreviewDeleteAll extends TugboatTask {
  public function run(): \Robo\Result {
    if (!$this->initTugboat()) {
      return $this->fail();
    }

    $result = $this->exec("$this->tugboat ls previews repo=$this->repo --json", $output);
    if ($result->getExitCode() !== 0) {
      return $result;
    }

    $previews = json_decode($output, true);
    foreach ($previews as $preview) {
      if ((bool) $preview['anchor']) {
        $this->printTaskInfo("Skip deleting anchor preview {$preview['preview']}");
        continue;
      }
      $result = $this->exec("$this->tugboat delete {$preview['preview']}");
      if ($result->getExitCode() !== 0) {
        return $result;
      }
    }

    return $this->pass();
  }

}