<?php

namespace PL\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
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
     * Initialize Testor.
     *
     * Use this command to
     *  - create example configuration;
     *  - create ddev custom command, if you use ddev environment.
     *
     * @return Result
     */
    public function selfInit(): Result
    {
        return $this->collectionBuilder()
            ->taskTestorConfigInit()
            ->taskTestorCustomCommand()
            ->run();
    }

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
     * @option $element Element to backup (code, database, files)
     *
     */
    public function snapshotCreate(array $opts = ['env' => 'dev', 'name' => '', 'element' => 'database'] ): Result
    {
        return$this->taskSnapshotCreate($opts)->run();
    }

    /** List snapshots from the storage.
     *
     * @param array $opts
     * @option $name Name of the snapshot, such as "developer" or "preview",
     * which is a prefix for an exact snapshot name (it can be thought as a folder)
     * @option $element Element to list backups for (code, database, files)
     * @return RowsOfFields
     */
    public function snapshotList(array $opts = ['name' => '', 'element' => 'database']): RowsOfFields
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
     * @option $element Element to get backups for (code, database, files)
     * @return Result
     */
    public function snapshotGet(array $opts = ['name' => '', 'output|o' => null, 'element' => 'database']): Result
    {
        return$this->taskSnapshotGet($opts)->run();
    }

    /**
     * Delete all previews on Tugboat within project's repo.
     *
     * @return Result
     */
    public function previewDeleteAll(): Result
    {
        return $this->taskTugboatPreviewDeleteAll()->run();
    }

    /**
     * Create a new preview on Tugboat.
     *
     * @return UnstructuredListData Preview in Tugboat's format
     */
    public function previewCreate(): UnstructuredListData
    {
        $result = $this->taskTugboatPreviewCreate()->run();
        return $result['preview'] ? new UnstructuredListData($result['preview']) : $result;
    }

    /**
     * Change ATK configs to run tests against given preview.
     *
     * @param string $preview
     * @return Result
     */
    public function previewSet(string $preview): Result
    {
        return $this->taskTugboatPreviewSet($preview)->run();
    }
}