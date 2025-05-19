<?php

namespace PL\Robo\Plugin;

use PL\Robo\Contract\PluginInterface;
use PL\Robo\Contract\PreSnapshotHandlerInterface;
use PL\Robo\Contract\PostImportHandlerInterface;
use PL\Robo\Contract\DataTransformerInterface;
use PL\Robo\Common\TestorDependencyInjectorTrait;
use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;
use Robo\Result;

/**
 * Manages the discovery and loading of Testor plugins.
 */
class PluginManager implements TestorConfigAwareInterface {
    use TestorDependencyInjectorTrait;
    use TestorConfigAwareTrait;

    /**
     * The loaded plugins.
     *
     * @var array
     */
    protected array $plugins = [];

    /**
     * PluginManager constructor.
     */
    public function __construct() {
        $this->injectTestorDependencies();
        $this->discoverPlugins();
    }

    /**
     * Discovers and loads plugins.
     */
    protected function discoverPlugins(): void {
        // Load plugins from configuration
        $this->loadConfiguredPlugins();
        
        // Load plugins from .testor/plugins directory
        $this->loadCustomPlugins();
    }

    /**
     * Loads plugins configured in .testor.yml.
     */
    protected function loadConfiguredPlugins(): void {
        if (!$this->testorConfig->has('plugins')) {
            return;
        }

        $plugins = $this->testorConfig->get('plugins');
        foreach ($plugins as $pluginId => $pluginConfig) {
            if (isset($pluginConfig['class']) && class_exists($pluginConfig['class'])) {
                $className = $pluginConfig['class'];
                $plugin = new $className(
                    $pluginId,
                    $pluginConfig['label'] ?? $pluginId,
                    $pluginConfig['description'] ?? ''
                );
                
                if ($plugin instanceof PluginInterface) {
                    $this->plugins[$pluginId] = $plugin;
                }
            }
        }
    }

    /**
     * Loads custom plugins from .testor/plugins directory.
     */
    protected function loadCustomPlugins(): void {
        $pluginsDir = getcwd() . '/.testor/plugins';
        if (!is_dir($pluginsDir)) {
            return;
        }

        // Auto-load PHP files in the plugins directory
        $files = glob($pluginsDir . '/*.php');
        foreach ($files as $file) {
            require_once $file;
        }

        // Find classes that implement PluginInterface
        $declaredClasses = get_declared_classes();
        foreach ($declaredClasses as $className) {
            $reflectionClass = new \ReflectionClass($className);
            
            // Skip abstract classes and classes that don't implement PluginInterface
            if ($reflectionClass->isAbstract() || !$reflectionClass->implementsInterface(PluginInterface::class)) {
                continue;
            }
            
            // Skip classes we've already loaded
            $pluginId = basename($file, '.php');
            if (isset($this->plugins[$pluginId])) {
                continue;
            }
            
            // Create an instance of the plugin
            $plugin = new $className(
                $pluginId,
                $pluginId, // Default label is the plugin ID
                '' // Default description is empty
            );
            
            $this->plugins[$pluginId] = $plugin;
        }
    }

    /**
     * Gets all plugins of a specific type.
     *
     * @param string $interfaceName
     *   The interface name to filter by.
     * @return array
     *   An array of plugins that implement the specified interface.
     */
    public function getPluginsByType(string $interfaceName): array {
        return array_filter($this->plugins, function ($plugin) use ($interfaceName) {
            return $plugin instanceof $interfaceName;
        });
    }

    /**
     * Gets all pre-snapshot handlers.
     *
     * @return array
     *   An array of pre-snapshot handlers.
     */
    public function getPreSnapshotHandlers(): array {
        return $this->getPluginsByType(PreSnapshotHandlerInterface::class);
    }

    /**
     * Gets all post-import handlers.
     *
     * @return array
     *   An array of post-import handlers.
     */
    public function getPostImportHandlers(): array {
        return $this->getPluginsByType(PostImportHandlerInterface::class);
    }

    /**
     * Gets all data transformers.
     *
     * @return array
     *   An array of data transformers.
     */
    public function getDataTransformers(): array {
        return $this->getPluginsByType(DataTransformerInterface::class);
    }

    /**
     * Executes all pre-snapshot handlers.
     *
     * @param array $options
     *   The snapshot options.
     * @return Result
     *   The result of the operation.
     */
    public function executePreSnapshotHandlers(array $options = []): Result {
        $handlers = $this->getPreSnapshotHandlers();
        foreach ($handlers as $handler) {
            $result = $handler->preSnapshot($options);
            if (!$result->wasSuccessful()) {
                return $result;
            }
        }
        
        return Result::success($this, 'All pre-snapshot handlers executed successfully.');
    }

    /**
     * Executes all post-import handlers.
     *
     * @param array $options
     *   The import options.
     * @return Result
     *   The result of the operation.
     */
    public function executePostImportHandlers(array $options = []): Result {
        $handlers = $this->getPostImportHandlers();
        foreach ($handlers as $handler) {
            $result = $handler->postImport($options);
            if (!$result->wasSuccessful()) {
                return $result;
            }
        }
        
        return Result::success($this, 'All post-import handlers executed successfully.');
    }

    /**
     * Executes all data transformers.
     *
     * @param array $options
     *   The transformation options.
     * @return Result
     *   The result of the operation.
     */
    public function executeDataTransformers(array $options = []): Result {
        $transformers = $this->getDataTransformers();
        foreach ($transformers as $transformer) {
            $result = $transformer->transform($options);
            if (!$result->wasSuccessful()) {
                return $result;
            }
        }
        
        return Result::success($this, 'All data transformers executed successfully.');
    }
}
