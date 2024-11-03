<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\StorageAwareTrait;
use PL\Robo\Contract\StorageAwareInterface;
use Robo\Result;

class SnapshotPut extends TestorTask implements StorageAwareInterface
{
    use StorageAwareTrait;

    protected string $name;
    protected string $file;

    function __construct(array $opts)
    {
        parent::__construct();
        $this->name = $opts['name'];
        $this->file = "{$opts['filename']}.tar.gz";
    }

    function run(): Result
    {
        $file = $this->file;
        $name = "$this->name/$file";
        $this->storage->put($file, $name);
        $this->message = "Uploaded $name";

        return $this->pass();
    }
}
