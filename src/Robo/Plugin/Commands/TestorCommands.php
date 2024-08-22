<?php

namespace PL\Robo\Plugin\Commands;

use League\Container\ContainerAwareTrait;
use PL\Robo\Task\Testor\SnapshotCreate;
use PL\Robo\Task\Testor\Tasks;
use Robo\Symfony\ConsoleIO;
use Robo\TaskAccessor;

class TestorCommands extends \Robo\Tasks
{
    use Tasks;

    /**
     * Task to export snapshot from a given Pantheon env,
     * upload it to MinIO, and optionally use for new previews.
     *
     * @param array $opts
     * @option $env Pantheon env
     * @option $useOnPreview Use this snapshot for newly created previews.
     *
     * @return SnapshotCreate
     */
    public function snapshotCreate(ConsoleIO $io, array $opts = ['env' => 'dev', 'useOnPreview' => false])
    {
        return$this->taskSnapshotCreate($opts)->run();
    }
}