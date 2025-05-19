<?php

namespace PL\Robo\Plugin;

use PL\Robo\Contract\PostImportHandlerInterface;
use Robo\Result;

/**
 * Base class for plugins that run after a snapshot is imported.
 */
abstract class BasePostImportHandler extends BasePlugin implements PostImportHandlerInterface {
    /**
     * {@inheritdoc}
     */
    public function postImport(array $options = []): Result {
        // Default implementation returns success.
        // Subclasses should override this method.
        return Result::success($this, 'Post-import handler executed successfully.');
    }
}
