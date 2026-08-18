<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;

class SnapshotViaBackup extends TestorTask implements TestorConfigAwareInterface {
  use TestorConfigAwareTrait;

  protected string $env;
  protected string $element;
  protected string $name;
  protected string $filename;

  public function __construct(array $opts) {
    parent::__construct();
    $this->env = $opts['env'];
    $this->element = $opts['element'];
    $this->name = $opts['name'];
    $this->filename = $opts['filename'];
  }

  public function run(): \Robo\Result {
    if (!$this->checkTerminus()) {
      return $this->fail();
    }

    $site = $this->testorConfig->get('pantheon.site');
    $env = $this->env;
    $result = $this->exec("terminus backup:create $site.$env --element=$this->element --keep-for=1");
    if ($result->getExitCode() !== 0) {
      return $result;
    }
    $result = $this->exec("terminus backup:list $site.$env --format=json", $output);
    if ($result->getExitCode() !== 0) {
      return $result;
    }
    $backups = json_decode($result->getMessage());
    $array = (array) $backups;
    $file = reset($array)->file;

    // Pantheon's `database` backups are a PLAIN gzip of a raw `.sql` file
    // (verified via `file` against a real download: "gzip compressed data,
    // was ...database.sql") — there is no tar layer at all. `files`/`code`
    // backups, in contrast, genuinely are tar+gzip. So only the `database`
    // element gets the plain-gunzip treatment here; download to `.gz` (an
    // honest name for what terminus actually delivers) and decompress it
    // directly to `.sql`, skipping the tar-unpack step entirely for this
    // path (issue #35 — the old unconditional `.tar.gz` naming made
    // SnapshotImport's taskArchiveUnpack try to open a real SQL dump as a
    // tar archive and fail with a "corrupted tar file" checksum error).
    if ($this->element === 'database') {
      $result = $this->exec("terminus backup:get $site.$env --file=$file --to={$this->filename}.gz");
      if ($result->getExitCode() !== 0) {
        return $result;
      }

      if (!$this->gunzipToSql("{$this->filename}.gz", "{$this->filename}.sql")) {
        return $this->fail();
      }

      return $this->pass();
    }

    $result = $this->exec("terminus backup:get $site.$env --file=$file --to={$this->filename}.tar.gz");
    if ($result->getExitCode() !== 0) {
      return $result;
    }

    return $this->pass();
  }

  /**
   * Decompress a plain gzip file straight to its target path, without
   * assuming (or requiring) any tar layer.
   *
   * @param string $source Path to the `.gz` file to decompress.
   * @param string $destination Path to write the decompressed content to.
   * @return bool True on success.
   */
  protected function gunzipToSql(string $source, string $destination): bool {
    $in = @gzopen($source, 'rb');
    if ($in === false) {
      $this->message = "Could not open $source for gunzip";
      return false;
    }

    $out = @fopen($destination, 'wb');
    if ($out === false) {
      gzclose($in);
      $this->message = "Could not open $destination for writing";
      return false;
    }

    while (!gzeof($in)) {
      $chunk = gzread($in, 1024 * 1024);
      if ($chunk === false) {
        gzclose($in);
        fclose($out);
        $this->message = "Failed reading gzip data from $source";
        return false;
      }
      fwrite($out, $chunk);
    }

    gzclose($in);
    fclose($out);
    unlink($source);

    return true;
  }

}
