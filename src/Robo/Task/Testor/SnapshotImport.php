<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;

class SnapshotImport extends TestorTask implements TestorConfigAwareInterface {
  use TestorConfigAwareTrait;

  protected string|null $filename;
  /**
   * @var bool
   * Same as in {@link SnapshotCreate}
   */
  protected bool $gzip;

  public function __construct(array $opts) {
    parent::__construct();
    $this->filename = $opts['filename'] ?? null;
    $this->gzip = $opts['gzip'] ?? true;
  }

  /**
   * Configure filename.
   *
   * @param string $filename
   * @return void
   */
  public function filename(string $filename): void {
    // Cut off extension if any.
    $this->filename = preg_replace('/(\.tar\.gz|\.sql)$/', '', $filename);
  }

  public function run(): \Robo\Result {
    $filename = $this->filename;

    if ($this->gzip) {
      // Extract .sql file.
      /** @var \Robo\Result $result */
      $result = $this->collectionBuilder()->taskArchiveUnpack($filename)->run();
      if (!$result->wasSuccessful()) {
        return $result;
      }
    }

    $command = $this->testorConfig->getOrDie('sql.command');
    $result = $this->exec("$command < $filename.sql");
    return $result;
  }

}