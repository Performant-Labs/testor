<?php

namespace PL\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use League\Container\ContainerAwareTrait;
use PL\Robo\Task\Testor\SnapshotCreate;
use PL\Robo\Task\Testor\Tasks;
use Robo\Result;
use Robo\Symfony\ConsoleIO;
use Robo\TaskAccessor;

class TestorCommands extends \Robo\Tasks
{
    use Tasks;

    /**
     * Task to export snapshot from a given Pantheon env,
     * upload it to the S3-compatible storage.
     *
     * @param array $opts
     * @return Result
     * @option $env Pantheon env
     * @option $name Name of the snapshot, such as "developer" or "preview",
     * will be prefixed to the real unique snapshot name (it can be thought
     * as a folder)
     *
     */
    public function snapshotCreate(array $opts = ['env' => 'dev', 'name' => '']): Result
    {
        return$this->taskSnapshotCreate($opts)->run();
    }

    /** List snapshots from the storage.
     *
     * @param array $opts
     * @option $name Name of the snapshot, such as "developer" or "preview",
     * which is a prefix for an exact snapshot name (it can be thought as a folder)
     * @return RowsOfFields
     */
    public function snapshotList(array $opts = ['name' => '']): RowsOfFields
    {
        $result = $this->taskSnapshotList($opts)->run();
        return new RowsOfFields($result['table']);
    }

    /**
     * Get (download) snapshot from the storage.
     *
     * @param array $opts
     * @option $name Name of the snapshot, can be either exact name, or prefix
     * like "developer" or "preview". In the latter case, the last snapshot with
     * this prefix will be gotten.
     * @option $output Output file. If not specified, original file name will be kept.
     * @return Result
     */
    public function snapshotGet(array $opts = ['name' => '', 'output|o' => null]): Result
    {
        return$this->taskSnapshotGet($opts)->run();
    }
}