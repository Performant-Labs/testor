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
    protected string $localfile;

    function __construct(array $opts)
    {
        parent::__construct();
        $this->name = $opts['name'];
        $localfilename = $opts['localfilename'] ?: $opts['filename'];
        $this->localfile = "{$localfilename}.tar.gz";
        $this->file = "{$opts['filename']}.tar.gz";
    }

    function run(): Result
    {
        $file = $this->localfile;
        $name = "$this->name/$this->file";
        $this->storage->put($file, $name);
        $this->message = "Uploaded $file => $name";

        return $this->pass();
    }
}
