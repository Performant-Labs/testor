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
      // target "$archive.tar"/"$archive.tar.gz" paths. Phar keeps a
      // per-process registry keyed by filename, and creating/converting a
      // Phar refuses to overwrite a path that is still registered ("a phar
      // with that name already exists"). This bites within a single
      // `snapshot:refresh` run: the pull packs "$archive.tar.gz", the import
      // unpacks it (registering "$archive.tar"), and the re-export then packs
      // the SAME paths again — which would fail without this eviction.
      // Deleting the archives here is safe because we are about to recreate
      // them.
      foreach (["$archive.tar", "$archive.tar.gz"] as $existing) {
        if (file_exists($existing)) {
          \Phar::unlinkArchive($existing);
        }
      }

      // Build the plain tar via PharData (reliable — see gzipFile()'s doc
      // comment for why gzip is NOT done via PharData::compress(Phar::GZ),
      // issue #37).
      $phar = new \PharData("$archive.tar");
      foreach ($this->files as $file) {
        $phar->addFile($file);
      }
      // Release the Phar handle before gzip-compressing the tar as a plain
      // file, so nothing still has it open when it's read below.
      unset($phar);

      if (!$this->gzipFile("$archive.tar", "$archive.tar.gz")) {
        return $this->fail();
      }

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