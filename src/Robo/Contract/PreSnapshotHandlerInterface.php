<?php

namespace PL\Robo\Contract;

/**
 * Interface for plugins that run before a snapshot is created.
 */
interface PreSnapshotHandlerInterface extends PluginInterface {
    /**
     * Executes before a snapshot is created.
     *
     * @param array $options
     *   The snapshot options.
     * @return \Robo\Result
     *   The result of the operation.
     */
    public function preSnapshot(array $options = []): \Robo\Result;
}
