<?php

namespace PL\Robo\Task\Testor {
    class SnapshotCreate extends \Robo\Task\BaseTask implements \Robo\Contract\TaskInterface
    {
        protected string $env;

        function __construct(string $env)
        {
            $this->env = $env;
        }

        // define public methods as commands

        function run(): void
        {
            if (!$this->checkTerminus()) return;
            $site = 'performant-labs';  //TODO use config
            $env = $this->env;

            exec("terminus backup:create $site.$env --element=database");
            exec("terminus backup:list $site.$env --format=json", $output);
            $backups = json_decode(implode("\n", $output));
            print_r($backups);
            $file = $backups[0]->file;

            exec("terminus backup:get $site.$env --file=$file --to=$file");

//        TODO upload
        }

        protected function checkTerminus(): bool
        {
            if (!is_executable('terminus')) {
                $this->printTaskError("Please install and configure terminus");
                return false;
            }
            return true;
        }

        protected function checkRclone(): bool
        {
            if (!is_executable('rclone')) {
                $this->printTaskError("Please install rclone (see https://docs.google.com/document/d/1tmISRP4ZpvVAKrR15Mi33nAXdQndyIrdHb58SrhMbmY/edit)");
                return false;
            }
            return true;
        }
    }
}
