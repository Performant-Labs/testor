<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;

class SnapshotCreate extends TestorTask implements TestorConfigAwareInterface {
  use TestorConfigAwareTrait;

  protected string $element;
  protected string $env;
  protected string $filename;
  protected bool $ispantheon;
  /**
   * @var bool
   * Skip GZIP.
   * This is in case we'll download a snapshot from Panteon
   * and right after this import it to local database (for
   * sanitization.)
   * Will skip gzip / gunzip in this case, but mostly not for
   * performance reason but to bypass
   * {@link https://news-web.php.net/php.bugs/207523}
   * (bug is open from 2017, version 7.0.16. PHP is awesome...)
   */
  protected bool $gzip;

  public function __construct(array $opts) {
    parent::__construct();
    $this->env = $opts['env'];
    $this->element = $opts['element'];
    $this->filename = $opts['filename'];
    $this->ispantheon = $opts['ispantheon'];
    $this->gzip = $opts['gzip'] ?? true;
  }

  public function run(): \Robo\Result {
    if ($this->ispantheon && !$this->checkTerminus()) {
      return $this->fail();
    }

    $element = $this->element;
    $site = $this->testorConfig->get('pantheon.site');
    $env = $this->env;
    $filename = $this->filename;

    if ($element === 'database') {
      // Theoretically, we can use --gzip option here, but actually it
      // produce a corrupted archive.
      if ($this->ispantheon) {
        $command = "terminus remote:drush $site.$env -- sql:dump";
      }
      else {
        $command = "drush sql:dump";
      }
      $result = $this->exec("{$command} > $filename.sql");
      if ($result->getExitCode() !== 0) {
        return $result;
      }

      if ($this->gzip) {
        /** @var \Robo\Result $result */
        $result = $this->collectionBuilder()->taskArchivePack($filename, "$filename.sql")->rmOrig()->run();
        if (!$result->wasSuccessful()) {
          return $result;
        }
      }
    }
    else {
//            TODO
      throw new \BadMethodCallException('Not implemented.');
    }

    return \Robo\Result::success($this);
  }

}