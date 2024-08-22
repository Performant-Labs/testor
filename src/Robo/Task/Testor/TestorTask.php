<?php

namespace PL\Robo\Task\Testor {

    use Consolidation\Config\ConfigInterface;
    use Robo\Robo;

    abstract class TestorTask extends \Robo\Task\BaseTask
    {
        protected ConfigInterface $testorConfig;

        function __construct()
        {
            $this->testorConfig = Robo::createConfiguration(['.testor.yml']);
        }
    }
}
