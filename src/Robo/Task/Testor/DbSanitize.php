<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;

class DbSanitize extends TestorTask implements TestorConfigAwareInterface
{
    use TestorConfigAwareTrait;

    protected array $env;

    public function __construct(array $env)
    {
        parent::__construct();
        $this->env = $env;
    }

    public function run(): \Robo\Result
    {
        if (!$this->testorConfig->has('sanitize.command')) {
            $this->message = 'Skip sanitization, because sanitize.command is not set';
            return $this->pass();
        }

        if ($this->env['do-not-sanitize']) {
            $this->message = 'Skip sanitization, because of --do-not-sanitize option';
            return $this->pass();
        }

        $command = $this->testorConfig->get('sanitize.command');
        return $this->exec($command);
    }
}