<?php

namespace PL\Robo\Plugin;

use PL\Robo\Contract\DataTransformerInterface;
use Robo\Result;

/**
 * Base class for plugins that transform data in a database.
 */
abstract class BaseDataTransformer extends BasePlugin implements DataTransformerInterface {
    /**
     * {@inheritdoc}
     */
    public function transform(array $options = []): Result {
        // Default implementation returns success.
        // Subclasses should override this method.
        return Result::success($this, 'Data transformation executed successfully.');
    }
}
