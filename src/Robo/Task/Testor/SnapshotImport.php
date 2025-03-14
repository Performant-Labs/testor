<?php

namespace PL\Robo\Task\Testor;

class SnapshotImport extends TestorTask {
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

    $result = $this->exec("$(drush sql:connect) < $filename.sql");
    return $result;
  }

}