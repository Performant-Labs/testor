<?php

namespace PL\Robo\Plugin;

use PL\Robo\Contract\PreSnapshotHandlerInterface;
use Robo\Result;

/**
 * Base class for plugins that run before a snapshot is created.
 */
abstract class BasePreSnapshotHandler extends BasePlugin implements PreSnapshotHandlerInterface {
    /**
     * {@inheritdoc}
     */
    public function preSnapshot(array $options = []): Result {
        // Default implementation returns success.
        // Subclasses should override this method.
        return Result::success($this, 'Pre-snapshot handler executed successfully.');
    }
}
