<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;
use PL\Robo\Contract\TugboatTaskInterface;

abstract class TugboatTask extends TestorTask
  implements TugboatTaskInterface, TestorConfigAwareInterface {
  use TestorConfigAwareTrait;

  protected string $tugboat = 'tugboat';  // Tugboat command
  protected string $repo;

  public function initTugboat(): bool {
    $repo = $this->testorConfig->getOrDie('tugboat.repo');
    $this->repo = $repo;

    if (!$this->isExecutable('tugboat')) {
      if (!file_put_contents('tugboat.tar.gz', file_get_contents('https://dashboard.tugboatqa.com/cli/linux/tugboat.tar.gz'))) {
        $this->message = "Failed to download https://dashboard.tugboatqa.com/cli/linux/tugboat.tar.gz";
        return false;
      }
      try {
        $archive = new \PharData('tugboat.tar.gz');
        $archive->extractTo('.', null, true);
      } catch (\Exception $exception) {
        $this->message = "Failed to extract tugboat.tar.gz: " . $exception->getMessage();
        return false;
      }

      // Symfony's ignoring changed PATH, so update $command instead.
      $this->tugboat = './tugboat';

      // If in the context of GitHub Actions, save PATH for further steps
      $githubPath = getenv('GITHUB_PATH');
      if ((bool) $githubPath) {
        file_put_contents($githubPath, getcwd() . "\n", FILE_APPEND);
      }
    }

    if (!file_exists(getenv('HOME') . '/.tugboat.yml')) {
      // Authorize tugboat (can be done either by `tugboat auth` or directly editing the config...)
      $tugboatToken = $this->testorConfig->get('tugboat.token', getenv('TUGBOAT_TOKEN'));
      if (!(bool) $tugboatToken) {
        $this->message = "Tugboat must be authorized in one of the following ways:\n
        - token in ~/.tugboat.yml
        - tugboat.token in .testor_secret.yml
        - TUGBOAT_TOKEN env variable";
        return false;
      }
      file_put_contents(getenv('HOME') . '/.tugboat.yml', "token: $tugboatToken");
    }

    return true;
  }

}