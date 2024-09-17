<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;
use PL\Robo\Contract\TugboatTaskInterface;

abstract class TugboatTask extends TestorTask
    implements TugboatTaskInterface, TestorConfigAwareInterface
{
    use TestorConfigAwareTrait;

    protected string $tugboat = 'tugboat';  // Tugboat command
    protected string $repo;

    public function initTugboat(): bool
    {
        $repo = $this->testorConfig->get('tugboat.repo');
        if (empty($repo)) {
            $this->message = "Please configure `tugboat.repo` (Use `tugboat ls repos` or dashboard.tugboatqa.com)";
            return false;
        }
        $this->repo = $repo;

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

            // Symfony's ignoring changed PATH, so update $command instead.
            $this->tugboat = './tugboat';

            // Authorize tugboat (can be done either by `tugboat auth` or directly editing the config...)
            $tugboatToken = $this->testorConfig->get('tugboat.token', getenv('TUGBOAT_TOKEN'));
            if (empty($tugboatToken)) {
                $this->message = "Please configure tugboat.token";
                return false;
            }
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