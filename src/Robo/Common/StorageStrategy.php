<?php

namespace PL\Robo\Common;

use PL\Robo\Contract\StorageInterface;
use PL\Robo\Contract\TestorConfigAwareInterface;

class StorageStrategy implements StorageInterface, TestorConfigAwareInterface
{
    use TestorDependencyInjectorTrait;
    use TestorConfigAwareTrait;

    public StorageInterface $storage;

    public function __construct()
    {
        $this->injectTestorDependencies();

        $supported = ['sftp', 's3'];
        $configured = array_filter($supported, fn($s) => $this->testorConfig->get($s));
        $storage = $this->testorConfig->get('storage');

        if ($storage && !in_array($storage, $supported)) {
            throw new TestorConfigException("storage=$storage not supported!");
        }
        if ($storage && !in_array($storage, $configured)) {
            throw new TestorConfigException("storage=$storage not configured!");
        }
        if (!$storage) {
            if (count($configured) == 0) {
                throw new TestorConfigException("None of storages are configured.");
            }
            if (count($configured) > 1) {
                throw new TestorConfigException("More than one storage configured. Specify storage to select.");
            }
            $storage = end($configured);
        }

        switch ($storage) {
            case 'sftp':
                $this->storage = new StorageSFTP();
                break;
            case 's3':
                $this->storage = new StorageS3();
                break;
        }
    }

    function put(string $source, string $destination): void
    {
        $this->storage->put($source, $destination);
    }

    function list(string $prefix): array
    {
        return $this->storage->list($prefix);
    }

    function get(string $source, string $destination): void
    {
        $this->storage->get($source, $destination);
    }
}