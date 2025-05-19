<?php

namespace PL\Robo\Plugin;

use PL\Robo\Contract\PluginInterface;
use PL\Robo\Common\TestorDependencyInjectorTrait;
use PL\Robo\Task\Testor\TestorTask;

/**
 * Base class for Testor plugins.
 */
abstract class BasePlugin extends TestorTask implements PluginInterface {
    use TestorDependencyInjectorTrait;

    /**
     * The plugin ID.
     *
     * @var string
     */
    protected string $id;

    /**
     * The plugin label.
     *
     * @var string
     */
    protected string $label;

    /**
     * The plugin description.
     *
     * @var string
     */
    protected string $description;

    /**
     * BasePlugin constructor.
     *
     * @param string $id
     *   The plugin ID.
     * @param string $label
     *   The plugin label.
     * @param string $description
     *   The plugin description.
     */
    public function __construct(string $id, string $label, string $description) {
        parent::__construct();
        $this->id = $id;
        $this->label = $label;
        $this->description = $description;
        $this->injectTestorDependencies();
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(): string {
        return $this->label;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string {
        return $this->description;
    }
}
