<?php

namespace PL\Robo\Contract;

use PL\Robo\Common\TestorConfig;

interface TestorConfigAwareInterface {
  /**
   * Set Testor config.
   *
   * @param TestorConfig $config
   * @return TestorConfigAwareInterface
   */
  function setTestorConfig(TestorConfig $config): static;

  /**
   * Get Testor config.
   *
   * @return TestorConfig
   */
  function getTestorConfig(): TestorConfig;

}