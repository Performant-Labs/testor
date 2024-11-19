<?php

namespace PL\Robo\Task\Testor;

class ArchivePack extends TestorTask
{
    protected string $archive;
    /**
     * Files to add to the archive.
     * @var string[]
     */
    protected array $files;
    protected bool $rm_orig;

    public function __construct(string $archive, string... $files)
    {
        parent::__construct();
        $this->archive = $archive;
        $this->files = $files;
        $this->rm_orig = false;
    }

    public function rmOrig($rm_orig = true): static
    {
        $this->rm_orig = $rm_orig;
        return $this;
    }

    public function run(): \Robo\Result
    {
        try {
            $archive = $this->archive;
            $phar = new \PharData("$archive.tar");
            foreach ($this->files as $file) {
                $phar->addFile($file);
            }
            $phar->compress(\Phar::GZ);
            if ($this->rm_orig) foreach ($this->files as $file) {
                unlink($file);
            }
            unlink("$archive.tar");
        } catch (\Exception $exception) {
            $this->message = $exception->getMessage();
            return $this->fail();
        }

        return $this->pass();
    }
}