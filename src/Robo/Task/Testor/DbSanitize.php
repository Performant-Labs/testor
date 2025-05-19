<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;
use PL\Robo\Plugin\PluginManager;
use Robo\Result;

class DbSanitize extends TestorTask implements TestorConfigAwareInterface
{
    use TestorConfigAwareTrait;

    /**
     * Environment options.
     *
     * @var array
     */
    protected array $env;

    /**
     * Plugin manager.
     *
     * @var PluginManager|null
     */
    protected ?PluginManager $pluginManager = null;

    /**
     * DbSanitize constructor.
     *
     * @param array $env
     *   Environment options.
     */
    public function __construct(array $env)
    {
        parent::__construct();
        $this->env = $env;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): Result
    {
        // Skip sanitization if explicitly disabled
        if ($this->env['do-not-sanitize']) {
            $this->message = 'Skip sanitization, because of --do-not-sanitize option';
            return $this->pass();
        }

        // Initialize the plugin manager
        $this->pluginManager = new PluginManager();
        
        // Execute data transformers from plugins
        $result = $this->pluginManager->executeDataTransformers($this->env);
        if (!$result->wasSuccessful()) {
            return $result;
        }
        
        // Execute hook commands from configuration
        $result = $this->executeHookCommands();
        if (!$result->wasSuccessful()) {
            return $result;
        }
        
        // Execute the default sanitization command if configured
        if ($this->testorConfig->has('sanitize.command')) {
            $command = $this->testorConfig->get('sanitize.command');
            $result = $this->exec($command);
            if (!$result->wasSuccessful()) {
                return $result;
            }
        } else {
            $this->printTaskInfo('No default sanitization command configured.');
        }
        
        // Execute SQL files if configured
        $result = $this->executeSqlFiles();
        if (!$result->wasSuccessful()) {
            return $result;
        }
        
        return Result::success($this, 'Sanitization completed successfully.');
    }
    
    /**
     * Executes hook commands from configuration.
     *
     * @return Result
     *   The result of the operation.
     */
    protected function executeHookCommands(): Result
    {
        if (!$this->testorConfig->has('hooks.data_transform.commands')) {
            return Result::success($this);
        }
        
        $commands = $this->testorConfig->get('hooks.data_transform.commands');
        if (!is_array($commands)) {
            return Result::success($this);
        }
        
        foreach ($commands as $command) {
            $this->printTaskInfo("Executing hook command: {$command}");
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
     * @return Result
     *   The result of the operation.
     */
    protected function executeSqlFiles(): Result
    {
        if (!$this->testorConfig->has('hooks.data_transform.sql_files')) {
            return Result::success($this);
        }
        
        $files = $this->testorConfig->get('hooks.data_transform.sql_files');
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