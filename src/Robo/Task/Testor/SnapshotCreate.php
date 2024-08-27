<?php

namespace PL\Robo\Task\Testor {

    use Aws\S3\S3Client;
    use Robo\Result;

    class SnapshotCreate extends TestorTask implements \Robo\Contract\TaskInterface
    {
        protected string $env;
        protected string $name;

        function __construct(array $opts, S3Client $client = null)
        {
            parent::__construct();
            $this->env = $opts['env'];
            $this->name = $opts['name'];
            $this->s3Client = $client;
        }

        // define public methods as commands

        function run(): Result
        {
            if (!$this->checkTerminus()) return new Result($this, 1, $this->message);
            $site = $this->testorConfig->get('pantheon.site');
            $env = $this->env;

            exec("terminus backup:create $site.$env --element=database");
            exec("terminus backup:list $site.$env --format=json", $output);
            $backups = json_decode(implode("\n", $output));
            $array = (array)$backups;
            $file = reset($array)->file;

            exec("terminus backup:get $site.$env --file=$file --to=$file");

            $client = $this->getS3Client();
            $bucket = $this->testorConfig->get('s3.bucket');
            $name = $this->name;
            $client->putObject(array(
                'Bucket' => $bucket,
                'Key' => "$name/$file",
                'SourceFile' => $file
            ));
            $this->message = "Uploaded $bucket::$name/$file";

            $this->printTaskSuccess($this->message);
            return new Result($this, 0, $this->message);
        }
    }
}
