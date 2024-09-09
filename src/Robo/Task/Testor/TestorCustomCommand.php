<?php

namespace PL\Robo\Task\Testor;

class TestorCustomCommand extends \Robo\Task\BaseTask
{
    private string $SCRIPT = "#!/bin/bash

## Description: Execute Testor
## Usage: testor
## Example: ddev testor list

testor $@
";

    public function run(): \Robo\Result
    {
        // If we aren't under ddev, silently skip it.
        if (!$this->isDdev()) {
            return new \Robo\Result($this, 0);
        }

        // This will create a custom ddev command.
        file_put_contents('.ddev/commands/web/testor.sh', $this->SCRIPT);
        $this->printTaskInfo('DDEV custom command created. Use `ddev testor`.');
        return new \Robo\Result($this, 0);
    }

    protected function isDdev(): bool
    {
        // Check if we are within ddev environment.
        return !empty(getenv('IS_DDEV_PROJECT'));
    }
}