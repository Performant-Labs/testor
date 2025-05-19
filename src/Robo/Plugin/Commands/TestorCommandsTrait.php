<?php

namespace PL\Robo\Plugin\Commands;

use PL\Robo\Plugin\PluginManager;
use Robo\Result;

/**
 * Trait for integrating plugins with Testor commands.
 */
trait TestorCommandsTrait {
    /**
     * Plugin manager instance.
     *
     * @var \PL\Robo\Plugin\PluginManager|null
     */
    protected ?PluginManager $pluginManager = null;

    /**
     * Gets the plugin manager.
     *
     * @return \PL\Robo\Plugin\PluginManager
     *   The plugin manager.
     */
    protected function getPluginManager(): PluginManager {
        if ($this->pluginManager === null) {
            $this->pluginManager = new PluginManager();
        }
        return $this->pluginManager;
    }

    /**
     * Executes pre-snapshot plugins and hooks.
     *
     * @param array $options
     *   The snapshot options.
     * @return \Robo\Result|null
     *   The result of the operation, or null if successful.
     */
    protected function executePreSnapshotPlugins(array $options): ?Result {
        // Execute pre-snapshot plugins
        $result = $this->getPluginManager()->executePreSnapshotHandlers($options);
        if (!$result->wasSuccessful()) {
            return $result;
        }

        // Execute pre-snapshot hook commands
        if ($this->testorConfig->has('hooks.pre_snapshot.commands')) {
            $commands = $this->testorConfig->get('hooks.pre_snapshot.commands');
            if (is_array($commands)) {
                foreach ($commands as $command) {
                    $result = $this->taskExec($command)->run();
                    if (!$result->wasSuccessful()) {
                        return $result;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Executes post-import plugins and hooks.
     *
     * @param array $options
     *   The import options.
     * @return \Robo\Result|null
     *   The result of the operation, or null if successful.
     */
    protected function executePostImportPlugins(array $options): ?Result {
        // Execute post-import plugins
        $result = $this->getPluginManager()->executePostImportHandlers($options);
        if (!$result->wasSuccessful()) {
            return $result;
        }

        // Execute post-import hook commands
        if ($this->testorConfig->has('hooks.post_import.commands')) {
            $commands = $this->testorConfig->get('hooks.post_import.commands');
            if (is_array($commands)) {
                foreach ($commands as $command) {
                    $result = $this->taskExec($command)->run();
                    if (!$result->wasSuccessful()) {
                        return $result;
                    }
                }
            }
        }

        // Execute post-import SQL files
        if ($this->testorConfig->has('hooks.post_import.sql_files')) {
            $files = $this->testorConfig->get('hooks.post_import.sql_files');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        $result = $this->taskExec("$(drush sql:connect) < {$file}")->run();
                        if (!$result->wasSuccessful()) {
                            return $result;
                        }
                    } else {
                        $this->say("SQL file not found: {$file}");
                    }
                }
            }
        }

        return null;
    }
}
