<?php

namespace PL\Robo\Task\Testor;

class TestorCustomCommand extends TestorTask
{
    private string $SCRIPT = "#!/bin/bash

## Description: Execute Robo (and therefore Testor)
## Usage: robo
## Example: ddev robo list

robo
";

    public function run(): \Robo\Result
    {
        // This will create a custom ddev command.
        // TODO change robo to testor after converting Testor to an application.

        // If we aren't under ddev, silently skip it.
        if (!$this->isDdev()) {
            return new \Robo\Result($this, 0);
        }

        file_put_contents('.ddev/commands/web/testor.sh', $this->SCRIPT);
        $this->printTaskInfo('DDEV custom command created. Use `ddev robo`.');
        return new \Robo\Result($this, 0);
    }

    protected function isDdev(): bool
    {
        // Check if we are within ddev environment.
        return !empty(getenv('IS_DDEV_PROJECT'));
    }
}