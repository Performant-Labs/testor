<?php

namespace PL\Robo\Contract;

/**
 * Interface for plugins that run after a snapshot is imported.
 */
interface PostImportHandlerInterface extends PluginInterface {
    /**
     * Executes after a snapshot is imported.
     *
     * @param array $options
     *   The import options.
     * @return \Robo\Result
     *   The result of the operation.
     */
    public function postImport(array $options = []): \Robo\Result;
}
