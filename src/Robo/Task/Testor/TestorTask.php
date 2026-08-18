<?php

namespace PL\Robo\Task\Testor;

use Consolidation\Config\ConfigInterface;
use PL\Robo\Common\TestorDependencyInjectorTrait;
use PL\Robo\Contract\S3BucketAwareInterface;
use PL\Robo\Contract\S3ClientAwareInterface;
use PL\Robo\Contract\TestorConfigAwareInterface;
use PL\Robo\Testor;
use Robo\Common\BuilderAwareTrait;
use Robo\Result;
use Robo\Task\Base\Exec;

abstract class TestorTask extends \Robo\Task\BaseTask implements \Robo\Contract\BuilderAwareInterface, \Robo\Contract\TaskInterface {

  use BuilderAwareTrait;
  use TestorDependencyInjectorTrait;

  protected string $message = '';

  function __construct() {
    $this->injectTestorDependencies();
  }

  /**
   * @param $filename string program which we wish to `exec`.
   * @return bool If it is available and executable.
   */
  public function isExecutable(string $filename): bool {
    return (bool) shell_exec("which $filename");
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
  public function exec(string $command, string &$output = null): Result {
    /** @var Exec $taskExec */
    $taskExec = $this->collectionBuilder()->taskExec($command);

    // Robo seem to either capture output or print it.
    // But never both.
    // So as a tradeoff let capture it if $output reference is passed
    // and print otherwise.
    $printOutput = count(func_get_args()) === 1;
    $result = $taskExec->printOutput($printOutput)->run();
    // Save to the variable.
    $outputData = $result->getOutputData();
    $output = $outputData;
    return $result;
  }

  /**
   * Shortcut to reduce boilerplate a little bit.
   *
   * E.g. if there is method `foo()` that initializes some object,
   * and returns `false` on fail, it can set `message` within it, then
   * `run()` can call it as following:
   * ```
   * if (!($foo = $this->foo())) {
   *     return $this->fail();
   * }
   * ```
   *
   * @return Result
   */
  public function fail(): Result {
    return new Result($this, 1, $this->message);
  }

  /**
   * Shortcut to print and return successful result.
   *
   * Can be used as following:
   * ```
   * $this->message = 'Happiness rainbow unicorns!';
   * return $this->pass();
   * ```
   *
   * @param array $data
   * @return Result
   */
  public function pass(array $data = []): Result {
    // Print message, because by default Robo doesn't print successful
    // messages, and empty output may confuse user.
    $this->printTaskSuccess($this->message ?? 'passed');
    return new Result($this, 0, $this->message, $data);
  }

  protected function checkRclone(): bool {
    if (!$this->isExecutable('rclone')) {
      $this->message = "Please install rclone (see https://docs.google.com/document/d/1tmISRP4ZpvVAKrR15Mi33nAXdQndyIrdHb58SrhMbmY/edit)";
      return false;
    }
    return true;
  }

  protected function checkTerminus(): bool {
    if (!$this->isExecutable('terminus')) {
      $this->message = "Please install and configure terminus";
      return false;
    }
    return true;
  }

  /**
   * Gzip-compress a file to a new path via PHP's zlib streams, WITHOUT going
   * through `PharData::compress(Phar::GZ)`. `PharData`'s own gzip round-trip
   * (compress then re-open + extractTo) has been confirmed (issue #37) to
   * silently write 0-byte extracted content on both macOS and Linux PHP
   * 8.3.x, despite reporting success and correct archive metadata — plain
   * (uncompressed) `PharData` tar handling is unaffected, so gzip is kept
   * entirely outside it.
   *
   * @param string $source Path to the file to compress.
   * @param string $destination Path to write the gzip-compressed file to.
   * @return bool True on success (sets `$this->message` on failure).
   */
  protected function gzipFile(string $source, string $destination): bool {
    $in = @fopen($source, 'rb');
    if ($in === false) {
      $this->message = "Could not open $source for gzip";
      return false;
    }

    $out = @gzopen($destination, 'wb9');
    if ($out === false) {
      fclose($in);
      $this->message = "Could not open $destination for writing";
      return false;
    }

    while (!feof($in)) {
      $chunk = fread($in, 1024 * 1024);
      if ($chunk === false) {
        fclose($in);
        gzclose($out);
        $this->message = "Failed reading data from $source";
        return false;
      }
      gzwrite($out, $chunk);
    }

    fclose($in);
    gzclose($out);

    return true;
  }

  /**
   * Gunzip a file to a new path via PHP's zlib streams, the mirror of
   * {@see gzipFile} — see its doc comment for why this bypasses `PharData`.
   *
   * @param string $source Path to the `.gz` file to decompress.
   * @param string $destination Path to write the decompressed content to.
   * @return bool True on success (sets `$this->message` on failure).
   */
  protected function gunzipFile(string $source, string $destination): bool {
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

    return true;
  }

}
