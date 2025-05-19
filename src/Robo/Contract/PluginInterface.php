<?php

namespace PL\Robo\Contract;

/**
 * Base interface for all Testor plugins.
 */
interface PluginInterface {
    /**
     * Returns the plugin ID.
     *
     * @return string
     *   The plugin ID.
     */
    public function getId(): string;

    /**
     * Returns the plugin label.
     *
     * @return string
     *   The human-readable name of the plugin.
     */
    public function getLabel(): string;

    /**
     * Returns the plugin description.
     *
     * @return string
     *   A brief description of what the plugin does.
     */
    public function getDescription(): string;
}
