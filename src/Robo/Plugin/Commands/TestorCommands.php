<?php

namespace PL\Robo\Plugin\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use PL\Robo\Common\TestorDependencyInjectorTrait;
use PL\Robo\DO\Snapshot;
use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;
use PL\Robo\Task\Testor\Tasks;
use Robo\Result;

class TestorCommands extends \Robo\Tasks implements TestorConfigAwareInterface
{
    use Tasks;
    use TestorConfigAwareTrait;
    use TestorDependencyInjectorTrait;

    public function __construct()
    {
        $this->injectTestorDependencies();
    }

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
     * @option $env It can be either '@self' (current database), a Drush alias,
     * or Pantheon env
     * @option $name Name of the snapshot, such as "developer" or "preview",
     * will be prefixed to the real unique snapshot name (it can be thought
     * as a folder)
     * @option $do-not-sanitize Skip database sanitize command
     * @option $element Element to backup (code, database, files)
     *
     */
    public function snapshotCreate(array $opts = ['env' => '@self', 'name' => '', 'element' => 'database', 'do-not-sanitize' => false]): Result
    {
        $task = $this->collectionBuilder();

        $env = $opts['env'];
        $ispantheon = !str_starts_with($env, '@');
        $dosanitize = $this->testorConfig->has('sanitize.command') && !$opts['do-not-sanitize'];

        // Normalize element.
        list($element, $opts) = $this->normalizeElement($opts);

        // Make a target file name (without extension, it can be .sql.gz, tar.gz later than).
        $filename = $this->getSnapshotFilename($ispantheon ? $env : 'local', $element);
        $opts['filename'] = $filename;

        if ($element == 'database') {
            if ($ispantheon) {
                // Create a snapshot remotly via `terminus drush ...`
                $task->taskSnapshotCreate([...$opts, 'ispantheon' => true, 'gzip' => !$dosanitize]);

                if (!$dosanitize) {
                    return $task
                        // Sanitize here to show "Skip..." message.
                        ->taskDbSanitize($opts)
                        ->taskSnapshotPut($opts)
                        ->run();
                }

                // Import snapshot to the local database
                $task->taskSnapshotImport([...$opts, 'gzip' => false]);
            } elseif ($env != '@self') {
                // Sync given database to $self
                // (Drush can only do sanitization against the local db).
                $task->taskDbSync($env, '@self');
            }

            // Do sanitize.
            $task->taskDbSanitize($opts);

            // Create a snapshot locally.
            $task->taskSnapshotCreate([...$opts, 'ispantheon' => false]);

            // Put the snapshot to the storage.
            $task->taskSnapshotPut($opts);
        } else {
            // Handle files and code (should be enough to just copy via SFTP...)
            // But not clear how to gzip them over SFTP.
            // So fallback to old good backups.
            $task->taskSnapshotViaBackup($opts)
                ->taskSnapshotPut($opts);
        }

        return $task->run();
    }

    /**
     * Put snapshot from the local file system to the storage.
     *
     * @param string $file Local file name
     * @param array $opts
     * @option $name Name of the snapshot, such as "developer" or "preview",
     * will be prefixed to the real unique snapshot name (it can be thought as
     * a folder)
     * @option $element Element to put (database, code, files). Must be
     * consistent with the content of the file!
     * @return Result
     */
    public function snapshotPut(string $file, array $opts = ['name' => '', 'element' => 'database'])
    {
        $task = $this->collectionBuilder();

        list($element, $opts) = $this->normalizeElement($opts);
        $filename = $this->getSnapshotFilename('local', $element);
        $opts['filename'] = $filename;

        preg_match('/(.*?)(\.tar|\.sql)?(\.gz)?$/', $file, $m);
        $localfilename = $m[1];
        $opts['localfilename'] = $localfilename;
        if ($m[3]) {
        } elseif ($m[2] == '.sql') {
            $task->taskArchivePack($localfilename, $file);
        } else {
            return Result::error($task, "file must be .gz | .sql");
        }

        return$task->taskSnapshotPut($opts)->run();
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

    /**
     * @param array $opts
     * @return array
     */
    protected function normalizeElement(array $opts): array
    {
        $element = $opts['element'];
        $element = match ($element) {
            'database', 'db' => 'database',
            'code' => 'code',
            'files' => 'files',
        };
        $opts['element'] = $element;
        return array($element, $opts);
    }

    /**
     * @param string $env
     * @param string $element
     * @return string
     */
    protected function getSnapshotFilename(string $env, string $element): string
    {
        return implode('_', [
            $this->testorConfig->get('pantheon.site'),
            $env,
            date_format(new \DateTime(), 'Y-m-d\\TH-m-s_T'),
            $element
        ]);
    }
}