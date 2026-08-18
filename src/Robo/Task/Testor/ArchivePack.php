<?php

namespace PL\Robo\Task\Testor;

class ArchivePack extends TestorTask {
  protected string $archive;
  /**
   * Files to add to the archive.
   * @var string[]
   */
  protected array $files;
  protected bool $rm_orig;

  public function __construct(string $archive, string...$files) {
    parent::__construct();
    $this->archive = $archive;
    $this->files = $files;
    $this->rm_orig = false;
  }

  public function rmOrig($rm_orig = true): static {
    $this->rm_orig = $rm_orig;
    return $this;
  }

  public function run(): \Robo\Result {
    try {
      $archive = $this->archive;

      // Evict any Phar the current PHP process has already cached for the
      // target "$archive.tar.gz" path. Phar keeps a per-process registry keyed
      // by filename, and compress() refuses to overwrite a path that is still
      // registered ("a phar with that name already exists"). This bites within
      // a single `snapshot:refresh` run: the pull packs "$archive.tar.gz", the
      // import unpacks it (registering that path), and the re-export then packs
      // the SAME path again — which would fail without this eviction. Deleting
      // the archive here is safe because we are about to recreate it.
      if (file_exists("$archive.tar.gz")) {
        \Phar::unlinkArchive("$archive.tar.gz");
      }

      $phar = new \PharData("$archive.tar");
      foreach ($this->files as $file) {
        $phar->addFile($file);
      }
      $phar->compress(\Phar::GZ);
      if ($this->rm_orig) foreach ($this->files as $file) {
        unlink($file);
      }
      unlink("$archive.tar");
    } catch (\Exception $exception) {
      $this->message = $exception->getMessage();
      return $this->fail();
    }

    return $this->pass();
  }

}