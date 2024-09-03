<?php

namespace PL\Robo\Task\Testor {

    use Aws\S3\S3Client;
    use PL\Robo\Common\S3BucketAwareTrait;
    use PL\Robo\Common\S3ClientAwareTrait;
    use PL\Robo\Common\TestorConfigAwareTrait;
    use PL\Robo\Contract\S3BucketAwareInterface;
    use PL\Robo\Contract\S3ClientAwareInterface;
    use PL\Robo\Contract\TestorConfigAwareInterface;
    use Robo\Result;

    class SnapshotCreate extends TestorTask
        implements TestorConfigAwareInterface, S3ClientAwareInterface, S3BucketAwareInterface
    {
        use TestorConfigAwareTrait;
        use S3ClientAwareTrait;
        use S3BucketAwareTrait;

        protected string $env;
        protected string $name;

        function __construct(array $opts)
        {
            parent::__construct();
            $this->env = $opts['env'];
            $this->name = $opts['name'];
        }

        // define public methods as commands

        function run(): Result
        {
            if (!$this->checkTerminus()) return new Result($this, 1, $this->message);
            $site = $this->testorConfig->get('pantheon.site');
            $env = $this->env;

            $result = $this->exec("terminus backup:create $site.$env --element=database");
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
            $this->s3Client->putObject(array(
                'Bucket' => $this->s3Bucket,
                'Key' => $name,
                'SourceFile' => $file
            ));
            $this->message = "Uploaded $this->s3Bucket::$name";

            $this->printTaskSuccess($this->message);
            return new Result($this, 0, $this->message);
        }
    }
}
