<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;
use PL\Robo\Plugin\PluginManager;
use Robo\Result;

class SnapshotImport extends TestorTask implements TestorConfigAwareInterface
{
    use TestorConfigAwareTrait;

    /**
     * The filename of the snapshot to import.
     *
     * @var string
     */
    protected string $filename;

    /**
     * Whether the snapshot is gzipped.
     *
     * @var bool
     * Same as in {@link SnapshotCreate}
     */
    protected bool $gzip;

    /**
     * All options passed to the task.
     *
     * @var array
     */
    protected array $options;

    /**
     * Plugin manager.
     *
     * @var PluginManager|null
     */
    protected ?PluginManager $pluginManager = null;

    /**
     * SnapshotImport constructor.
     *
     * @param array $opts
     *   Options for the import.
     */
    public function __construct(array $opts)
    {
        parent::__construct();
        $this->filename = $opts['filename'];
        $this->gzip = $opts['gzip'] ?? true;
        $this->options = $opts;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): Result
    {
        $filename = $this->filename;

        if ($this->gzip) {
            // Extract .sql file.
            /** @var \Robo\Result $result */
            $result = $this->collectionBuilder()->taskArchiveUnpack($filename)->run();
            if (!$result->wasSuccessful()) {
                return $result;
            }
        }

        // Import the database
        $result = $this->exec("$(drush sql:connect) < $filename.sql");
        if (!$result->wasSuccessful()) {
            return $result;
        }

        // Execute post-import plugins and hooks
        $result = $this->executePostImportPlugins();
        if (!$result->wasSuccessful()) {
            return $result;
        }

        return Result::success($this, 'Database import completed successfully.');
    }

    /**
     * Executes post-import plugins and hooks.
     *
     * @return \Robo\Result
     *   The result of the operation.
     */
    protected function executePostImportPlugins(): Result
    {
        // Initialize plugin manager
        $this->pluginManager = new PluginManager();

        // Execute post-import plugins
        $result = $this->pluginManager->executePostImportHandlers($this->options);
        if (!$result->wasSuccessful()) {
            return $result;
        }

        // Execute post-import hook commands
        $result = $this->executeHookCommands();
        if (!$result->wasSuccessful()) {
            return $result;
        }

        // Execute post-import SQL files
        $result = $this->executeSqlFiles();
        if (!$result->wasSuccessful()) {
            return $result;
        }

        return Result::success($this);
    }

    /**
     * Executes hook commands from configuration.
     *
     * @return \Robo\Result
     *   The result of the operation.
     */
    protected function executeHookCommands(): Result
    {
        if (!$this->testorConfig->has('hooks.post_import.commands')) {
            return Result::success($this);
        }

        $commands = $this->testorConfig->get('hooks.post_import.commands');
        if (!is_array($commands)) {
            return Result::success($this);
        }

        foreach ($commands as $command) {
            $this->printTaskInfo("Executing post-import command: {$command}");
            $result = $this->exec($command);
            if (!$result->wasSuccessful()) {
                return $result;
            }
        }

        return Result::success($this);
    }

    /**
     * Executes SQL files from configuration.
     *
     * @return \Robo\Result
     *   The result of the operation.
     */
    protected function executeSqlFiles(): Result
    {
        if (!$this->testorConfig->has('hooks.post_import.sql_files')) {
            return Result::success($this);
        }

        $files = $this->testorConfig->get('hooks.post_import.sql_files');
        if (!is_array($files)) {
            return Result::success($this);
        }

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->printTaskWarning("SQL file not found: {$file}");
                continue;
            }

            $this->printTaskInfo("Executing SQL file: {$file}");
            $result = $this->exec("$(drush sql:connect) < {$file}");
            if (!$result->wasSuccessful()) {
                return $result;
            }
        }

        return Result::success($this);
    }
}