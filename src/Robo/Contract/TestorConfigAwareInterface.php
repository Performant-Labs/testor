<?php

namespace PL\Robo\Contract;

use Consolidation\Config\ConfigInterface;

interface TestorConfigAwareInterface {
  /**
   * Set Testor config.
   *
   * @param ConfigInterface $config
   * @return TestorConfigAwareInterface
   */
  function setTestorConfig(ConfigInterface $config): static;

  /**
   * Get Testor config.
   *
   * @return ConfigInterface
   */
  function getTestorConfig(): ConfigInterface;

}