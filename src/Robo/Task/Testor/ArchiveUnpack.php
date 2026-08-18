<?php

namespace PL\Robo\Task\Testor;

class ArchiveUnpack extends TestorTask {
  protected string $archive;
  protected string $dir;

  public function __construct(string $archive, string $dir = '.') {
    parent::__construct();
    $this->archive = $archive;
    $this->dir = $dir;
  }

  public function run(): \Robo\Result {
    try {
      $filename = $this->archive;

      // Gunzip to a plain tar first, then extract via PharData — NOT
      // `new \PharData("$filename.tar.gz")->extractTo()` directly.
      // PharData's own gzip round-trip (compress/decompress-on-open) has
      // been confirmed (issue #37) to silently write 0-byte extracted
      // content on both macOS and Linux PHP 8.3.x, despite reporting success
      // and correct archive metadata. Plain (uncompressed) PharData tar
      // extraction is unaffected, so gzip is handled outside it entirely
      // (see gzipFile()/gunzipFile() in TestorTask).
      if (file_exists("$filename.tar")) {
        \Phar::unlinkArchive("$filename.tar");
      }
      if (!$this->gunzipFile("$filename.tar.gz", "$filename.tar")) {
        return $this->fail();
      }

      $phar = new \PharData("$filename.tar");
      $phar->extractTo($this->dir);
      unset($phar);
      unlink("$filename.tar");
    } catch (\Exception $exception) {
      $this->message = $exception->getMessage();
      return $this->fail();
    }

    return $this->pass();
  }

}