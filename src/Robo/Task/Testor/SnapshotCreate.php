<?php

namespace PL\Robo\Task\Testor {

    use Aws\S3\S3Client;
    use Robo\Result;

    class SnapshotCreate extends TestorTask implements \Robo\Contract\TaskInterface
    {
        protected string $message;
        protected string $env;
        protected bool $useOnPreviews;
        protected ?S3Client $s3Client;

        function __construct(array $opts, S3Client $client = null)
        {
            parent::__construct();
            $this->env = $opts['env'];
            $this->useOnPreviews = $opts['useOnPreview'];
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
            $file = reset($backups)->file;

            exec("terminus backup:get $site.$env --file=$file --to=$file");

            $client = $this->s3Client ?? new S3Client($this->testorConfig->get('s3.config'));
            $bucket = $this->testorConfig->get('s3.bucket');
            $client->putObject(array(
                'Bucket' => $bucket,
                'Key' => "$file",
                'SourceFile' => $file
            ));
            $this->message = "Uploaded $bucket::$file";

            if ($this->useOnPreviews) {
                if (!$this->initTugboat()) {
                    return new Result($this, 1, $this->message);
                }
                $repo = $this->testorConfig->get('tugboat.repo');
                exec("tugboat find $repo --json", $output);
                $repoObj = json_decode(implode("\n", $output));
                $envList = $repoObj->envvars;
                $envMap = array_combine(
                    array_map(fn($env) => $env->name, $envList),
                    array_map(fn($env) => $env->value, $envList)
                );
                $envMap['SNAPSHOT_KEY'] = $file;
                $envVars = implode(',', array_map(fn($key) => "$key=$envMap[$key]", array_keys($envMap)));
                exec("tugboat update $repo envvars=\"$envVars\"");

                $this->message .= ' and set as default for previews';
            }

            return new Result($this, 0, $this->message);
        }

        protected function checkTerminus(): bool
        {
            if (!is_executable('terminus')) {
                $this->message = "Please install and configure terminus";
                return false;
            }
            return true;
        }

        public function getS3Client(): ?S3Client
        {
            return $this->s3Client;
        }

        public function setS3Client(?S3Client $s3Client): void
        {
            $this->s3Client = $s3Client;
        }

        protected function checkRclone(): bool
        {
            if (!is_executable('rclone')) {
                $this->message = "Please install rclone (see https://docs.google.com/document/d/1tmISRP4ZpvVAKrR15Mi33nAXdQndyIrdHb58SrhMbmY/edit)";
                return false;
            }
            return true;
        }

        protected function initTugboat(): bool
        {
            $repo = $this->testorConfig->get('tugboat.repo');
            if (empty($repo)) {
                $this->message = "Please configure `tugboat.repo` (Use `tugboat ls repos` or dashboard.tugboatqa.com)";
                return false;
            }

            if (!is_executable('tugboat')) {
                if (!file_put_contents('tugboat.tar.gz', file_get_contents('https://dashboard.tugboatqa.com/cli/linux/tugboat.tar.gz'))) {
                    $this->message = "Failed to download https://dashboard.tugboatqa.com/cli/linux/tugboat.tar.gz";
                    return false;
                }
                try {
                    $archive = new \PharData('tugboat.tar.gz');
                    $archive->extractTo('.');
                } catch (\Exception $exception) {
                    $this->message = "Failed to extract tugboat.tar.gz: " . $exception->getMessage();
                    return false;
                }

                // Update PATH to execute tugboat without ./
                putenv('PATH=' . getenv('PATH') . ':' . getcwd());

                // Authorize tugboat (can be done either by `tugboat auth` or directly editing the config...)
                $tugboatToken = $this->testorConfig->get('tugboat.token', getenv('TUGBOAT_TOKEN'));
                file_put_contents(getenv('HOME') . '/.tugboat.yml', "token: $tugboatToken");

                // If in the context of GitHub Actions, save PATH for further steps
                $githubPath = getenv('GITHUB_PATH');
                if (!empty($githubPath)) {
                    file_put_contents($githubPath, getcwd() . "\n", FILE_APPEND);
                }
            }

            return true;
        }
    }
}
