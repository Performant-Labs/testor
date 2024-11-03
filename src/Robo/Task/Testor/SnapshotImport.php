<?php

namespace PL\Robo\Task\Testor;

class SnapshotImport extends TestorTask
{
    protected string $filename;
    /**
     * @var bool
     * Same as in {@link SnapshotCreate}
     */
    protected bool $gzip;

    public function __construct(array $opts)
    {
        parent::__construct();
        $this->filename = $opts['filename'];
        $this->gzip = $opts['gzip'] ?? true;
    }

    public function run(): \Robo\Result
    {
        $filename = $this->filename;

        if ($this->gzip) {
            // Extract .sql file.
            try {
                $phar = new \PharData("$filename.tar.gz");
                $phar->extractTo('.');
            } catch (\Exception $exception) {
                $this->message = $exception->getMessage();
                return $this->fail();
            }
        }

        $result = $this->exec("$(drush sql:connect) < $filename.sql");
        return $result;
    }
}