<?php

namespace PL\Robo\Common;

use Consolidation\Config\ConfigInterface;

trait TestorConfigAwareTrait
{
    protected ConfigInterface $testorConfig;

    public function getTestorConfig(): ConfigInterface
    {
        return $this->testorConfig;
    }

    public function setTestorConfig(ConfigInterface $testorConfig): static
    {
        $this->testorConfig = $testorConfig;
        return $this;
    }
}