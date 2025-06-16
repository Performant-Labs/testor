<?php

namespace PL\Robo\Common;

trait TestorConfigAwareTrait {
  protected TestorConfig $testorConfig;

  public function getTestorConfig(): TestorConfig {
    return $this->testorConfig;
  }

  public function setTestorConfig(TestorConfig $testorConfig): static {
    $this->testorConfig = $testorConfig;
    return $this;
  }

}