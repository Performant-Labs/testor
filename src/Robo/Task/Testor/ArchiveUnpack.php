<?php

namespace PL\Robo\Task\Testor;

class ArchiveUnpack extends TestorTask
{
    protected string $archive;
    protected string $dir;

    public function __construct(string $archive, string $dir = '.')
    {
        parent::__construct();
        $this->archive = $archive;
        $this->dir = $dir;
    }

    public function run(): \Robo\Result
    {
        try {
            $filename = $this->archive;
            $phar = new \PharData("$filename.tar.gz");
            $phar->extractTo($this->dir);
        } catch (\Exception $exception) {
            $this->message = $exception->getMessage();
            return $this->fail();
        }

        return $this->pass();
    }
}