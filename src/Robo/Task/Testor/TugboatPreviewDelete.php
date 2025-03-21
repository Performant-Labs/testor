<?php

namespace PL\Robo\Task\Testor;

class TugboatPreviewDelete extends TugboatTask {
  protected string $preview;

  public function __construct(string $preview) {
    parent::__construct();
    $this->preview = $preview;
  }

  public function run(): \Robo\Result {
    if (!$this->initTugboat()) {
      return $this->fail();
    }

    if ($this->preview !== 'all') {
      return $this->deleteSingle($this->preview);
    }

    $result = $this->exec("$this->tugboat ls previews repo=$this->repo --json", $output);
    if ($result->getExitCode() !== 0) {
      return $result;
    }

    $previews = json_decode($output, true);
    foreach ($previews as $preview) {
      if ((bool) ($preview['anchor'] ?? null)) {
        $this->printTaskInfo("Skip deleting anchor preview {$preview['preview']}");
        continue;
      }
      $result = $this->deleteSingle($preview['preview']);
      if ($result->getExitCode() !== 0) {
        return $result;
      }
    }

    return $this->pass();
  }

  /**
   * @param $preview
   * @return \Robo\Result
   */
  public function deleteSingle($preview): \Robo\Result {
    return $this->exec("$this->tugboat delete {$preview}");
  }

}