<?php

namespace PL\Robo\Task\Testor {

    use Consolidation\Config\ConfigInterface;
    use PL\Robo\Contract\S3BucketAwareInterface;
    use PL\Robo\Contract\S3ClientAwareInterface;
    use PL\Robo\Contract\TestorConfigAwareInterface;
    use Robo\Common\BuilderAwareTrait;
    use Robo\Result;
    use Robo\Task\Base\Exec;

    abstract class TestorTask extends \Robo\Task\BaseTask implements \Robo\Contract\BuilderAwareInterface, \Robo\Contract\TaskInterface
    {

        use BuilderAwareTrait;

        protected string $message;

        function __construct()
        {
            // We have BaseTask->injectDependencies() which passes dependencies
            // created by Robo like a waterfall from parent to child.
            // Since it's not clear how to add dependencies specific to the
            // custom task, like in our case (S3, Tugboat, Pantheon etc.),
            // and also `inflector()` makes no affect, let inject them here
            // in the constructor
            $container = \Robo\Robo::getContainer();
            if ($this instanceof TestorConfigAwareInterface) {
                $this->setTestorConfig($container->get('testorConfig'));
            }
            if ($this instanceof S3ClientAwareInterface) {
                $this->setS3Client($container->get('s3Client'));
            }
            if ($this instanceof S3BucketAwareInterface) {
                $this->setS3Bucket($container->get('s3Bucket'));
            }
        }

        /**
         * @param $filename string program which we wish to `exec`.
         * @return bool If it is available and executable.
         */
        public function isExecutable(string $filename): bool
        {
            return !empty(shell_exec("which $filename"));
        }

        /** Execute command line.
         *
         * If you want to stop a task at the first unsuccessful command,
         * it should be used like following:
         * ```
         * $result = $this->exec("my/command/line blah-blah-blah");
         * if ($result->getExitCode() != 0) {
         *     return $result;
         * }
         * // do following steps
         * ```
         * @param string $command command line
         * @param string|null $output reference to command's stdout, if needed
         * @return Result command's result
         */
        public function exec(string $command, string &$output = null): Result
        {
            /** @var Exec $taskExec */
            $taskExec = $this->collectionBuilder()->taskExec($command);

            // Robo seem to either capture output or print it.
            // But never both.
            // So as a tradeoff let capture it if $output reference is passed
            // and print otherwise.
            $printOutput = count(func_get_args()) == 1;
            $result = $taskExec->printOutput($printOutput)->run();
            // Save to the variable.
            $outputData = $result->getOutputData();
            $output = $outputData;
            return $result;
        }

        protected function initTugboat(): bool
        {
            $repo = $this->testorConfig->get('tugboat.repo');
            if (empty($repo)) {
                $this->message = "Please configure `tugboat.repo` (Use `tugboat ls repos` or dashboard.tugboatqa.com)";
                return false;
            }

            if (!$this->isExecutable('tugboat')) {
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

        protected function checkRclone(): bool
        {
            if (!$this->isExecutable('rclone')) {
                $this->message = "Please install rclone (see https://docs.google.com/document/d/1tmISRP4ZpvVAKrR15Mi33nAXdQndyIrdHb58SrhMbmY/edit)";
                return false;
            }
            return true;
        }

        protected function checkTerminus(): bool
        {
            if (!$this->isExecutable('terminus')) {
                $this->message = "Please install and configure terminus";
                return false;
            }
            return true;
        }
    }
}
