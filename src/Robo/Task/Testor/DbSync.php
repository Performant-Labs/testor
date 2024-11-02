<?php

namespace PL\Robo\Task\Testor;

class DbSync extends TestorTask
{
    protected string $source;
    protected string $target;

    public function __construct(string $source, string $target)
    {
        parent::__construct();
        $this->source = $source;
        $this->target = $target;
    }

    public function run(): \Robo\Result
    {
        return $this->exec("drush sql:sync $this->source $this->target");
    }
}