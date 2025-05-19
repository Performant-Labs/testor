<?php

namespace PL\Robo\Contract;

/**
 * Interface for plugins that transform data in a database.
 * 
 * This can include sanitization, anonymization, or other data modifications.
 */
interface DataTransformerInterface extends PluginInterface {
    /**
     * Transforms data in the database.
     *
     * @param array $options
     *   Options for the transformation.
     * @return \Robo\Result
     *   The result of the operation.
     */
    public function transform(array $options = []): \Robo\Result;
}
