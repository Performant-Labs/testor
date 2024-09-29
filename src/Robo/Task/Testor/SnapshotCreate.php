<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\StorageAwareTrait;
use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\StorageAwareInterface;
use PL\Robo\Contract\TestorConfigAwareInterface;
use Robo\Result;

class SnapshotCreate extends TestorTask
    implements TestorConfigAwareInterface, StorageAwareInterface
{
    use TestorConfigAwareTrait;
    use StorageAwareTrait;

    protected string $env;
    protected string $name;
    protected string $element;

    function __construct(array $opts)
    {
        parent::__construct();
        $this->env = $opts['env'];
        $this->name = $opts['name'];
        $this->element = $opts['element'];
    }

    // define public methods as commands

    function run(): Result
    {
        if (!$this->checkTerminus()) return $this->fail();
        $site = $this->testorConfig->get('pantheon.site');
        $env = $this->env;

        $result = $this->exec("terminus backup:create $site.$env --element=$this->element");
        if ($result->getExitCode() != 0) {
            return $result;
        }
        $result = $this->exec("terminus backup:list $site.$env --format=json", $output);
        if ($result->getExitCode() != 0) {
            return $result;
        }
        $backups = json_decode($result->getMessage());
        $array = (array)$backups;
        $file = reset($array)->file;

        $result = $this->exec("terminus backup:get $site.$env --file=$file --to=$file");
        if ($result->getExitCode() != 0) {
            return $result;
        }

        $name = "$this->name/$file";
        $this->storage->put($file, $name);
        $this->message = "Uploaded $name";

        return $this->pass();
    }
}
