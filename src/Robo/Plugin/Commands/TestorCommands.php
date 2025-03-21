<?php

namespace PL\Robo\Plugin\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\UnstructuredData;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use PL\Robo\Common\TestorDependencyInjectorTrait;
use PL\Robo\DO\Snapshot;
use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;
use PL\Robo\Task\Testor\Tasks;
use Robo\Result;
use Robo\Symfony\ConsoleIO;

class TestorCommands extends \Robo\Tasks implements TestorConfigAwareInterface {
  use Tasks;
  use TestorConfigAwareTrait;
  use TestorDependencyInjectorTrait;

  public function __construct() {
    $this->injectTestorDependencies();
  }

  /**
   * Initialize Testor.
   *
   * Use this command to
   *  - create example configuration;
   *  - create ddev custom command, if you use ddev environment.
   *
   * @return Result
   */
  public function selfInit(): Result {
    return $this->collectionBuilder()
      ->taskTestorConfigInit()
      ->taskTestorCustomCommand()
      ->run();
  }

  /**
   * Task to export snapshot from a given Pantheon env,
   * upload it to the S3-compatible storage.
   *
   * @param array $opts
   * @return Result
   * @option $env It can be either '@self' (current database), a Drush alias,
   * or Pantheon env
   * @option $name Name of the snapshot, such as "developer" or "preview",
   * will be prefixed to the real unique snapshot name (it can be thought
   * as a folder)
   * @option $do-not-sanitize Skip database sanitize command
   * @option $element Element to backup (code, database, files)
   * @option $put Put (upload) a snapshot to the storage after creation
   *
   */
  public function snapshotCreate(array $opts = ['env' => '@self', 'name' => 'default', 'element' => 'database', 'do-not-sanitize' => false, 'put' => false]): Result {
    $task = $this->collectionBuilder();

    $env = $opts['env'];
    $ispantheon = !str_starts_with($env, '@');
    $dosanitize = $this->testorConfig->has('sanitize.command') && !$opts['do-not-sanitize'];

    // Normalize element.
    $element = $this->normalizeElement($opts);

    // Make a target file name (without extension, it can be .sql.gz, tar.gz later than).
    $filename = $this->getSnapshotFilename($ispantheon ? $env : 'local', $element);
    $opts['filename'] = $filename;

    if ($element === 'database') {
      if ($ispantheon) {
        // Create a snapshot remotly via `terminus drush ...`
        $task->taskSnapshotCreate([...$opts, 'ispantheon' => true, 'gzip' => !$dosanitize]);

        if (!$dosanitize) {
          $task
            // Sanitize here to show "Skip..." message.
            ->taskDbSanitize($opts);
          if ($opts['put']) {
            $task
              ->taskSnapshotPut($opts);
          }
          return $task
            ->run();
        }

        // Import snapshot to the local database
        $task->taskSnapshotImport([...$opts, 'gzip' => false]);
      }
      elseif ($env !== '@self') {
        // Sync given database to $self
        // (Drush can only do sanitization against the local db).
        $task->taskDbSync($env, '@self');
      }

      // Do sanitize.
      $task->taskDbSanitize($opts);

      // Create a snapshot locally.
      $task->taskSnapshotCreate([...$opts, 'ispantheon' => false]);

      // Put the snapshot to the storage.
      if ($opts['put']) {
        $task->taskSnapshotPut($opts);
      }
    }
    else {
      // Handle files and code (should be enough to just copy via SFTP...)
      // But not clear how to gzip them over SFTP.
      // So fallback to old good backups.
      $task->taskSnapshotViaBackup($opts);
      if ($opts['put']) {
        $task
          ->taskSnapshotPut($opts);
      }
    }

    return $task->run();
  }

  /**
   * Put snapshot from the local file system to the storage.
   *
   * @param string $file Local file name
   * @param array $opts
   * @option $name Name of the snapshot, such as "developer" or "preview",
   * will be prefixed to the real unique snapshot name (it can be thought as
   * a folder)
   * @option $element Element to put (database, code, files). Must be
   * consistent with the content of the file!
   * @return Result
   */
  public function snapshotPut(string $file, array $opts = ['name' => 'default', 'element' => 'database']) {
    $task = $this->collectionBuilder();

    $element = $this->normalizeElement($opts);
    $filename = $this->getSnapshotFilename('local', $element);
    $opts['filename'] = $filename;

    preg_match('/(.*?)(\.tar|\.sql)?(\.gz)?$/', $file, $m);
    $localfilename = $m[1];
    $opts['localfilename'] = $localfilename;
    if ($m[3] ?? '') {
    }
    elseif (($m[2] ?? '') === '.sql') {
      $task->taskArchivePack($localfilename, $file);
    }
    else {
      return Result::error($task, "file must be .gz | .sql");
    }

    return $task->taskSnapshotPut($opts)->run();
  }

  /** List snapshots from the storage.
   *
   * @param array $opts
   * @option $name Name of the snapshot, such as "developer" or "preview",
   * which is a prefix for an exact snapshot name (it can be thought as a folder)
   * @option $element Element to list backups for (code, database, files)
   * @return RowsOfFields
   */
  public function snapshotList(array $opts = ['name' => '', 'element' => 'database']): RowsOfFields {
    $result = $this->taskSnapshotList($opts)->run();
    return new RowsOfFields($result['table']);
  }

  /**
   * Get (download) snapshot from the storage.
   *
   * @param array $opts
   * @option $name Name of the snapshot, can be either exact name, or prefix
   * like "developer" or "preview". In the latter case, the last snapshot with
   * this prefix will be gotten.
   * @option $output Output file. If not specified, original file name will be kept.
   * @option $element Element to get backups for (code, database, files)
   * @option $import Import database after download
   * @return Result
   */
  public function snapshotGet(array $opts = ['name' => '', 'output|o' => null, 'element' => 'database', 'import' => false]): Result {
    $task = $this->collectionBuilder()->taskSnapshotGet($opts);
    if ($opts['import']) {
      $task
        ->storeState('filename', 'filename')
        ->taskSnapshotImport($opts)
        ->deferTaskConfiguration('filename', 'filename');
    }
    return $task->run();
  }

  /**
   * Delete snapshot(s) from the storage.
   *
   * @option $name Name or prefix of snapshot(s)
   * @option $yes Confirm deletion
   *
   */
  public function snapshotDelete(ConsoleIO $io, array $opts = ['name' => '', 'yes|y' => false]): \Robo\ResultData|Result {
    $result = $this->taskSnapshotList($opts)->run();

    if (!$result->wasSuccessful()) {
      return $result;
    }

    $table = $result['table'];
    $count = count($table);
    if ($opts['yes'] || $io->confirm("Do you want to delete $count objects")) {
      $names = array_map(fn($item) => $item['Name'], $table);
      return $this->taskSnapshotDelete(...$names)->run();
    }

    return Result::cancelled();
  }

  /**
   * Create a new preview on Tugboat.
   *
   * @param array $opts
   * @option $base Specify which Preview should be used as the Base Preview.
   * If there are any anchored Base Previews, those will be
   * used by default. Specify the ID of another Preview to use
   * it as the Base Preview, or set this to false to build a
   * Preview without a Base Preview.
   * @option $set Change ATK configs to run test against a new preview.
   * @return UnstructuredData Preview in Tugboat's format
   */
  public function previewCreate(array $opts = ['base' => null, 'set' => false]): UnstructuredData|Result {
    $task = $this->collectionBuilder()->taskTugboatPreviewCreate($opts);
    if ($opts['set']) {
      $task->storeState('preview', 'preview')
        ->taskTugboatPreviewSet()
        ->deferTaskConfiguration('preview', 'preview');
    }
    $result = $task->run();
    return isset($result['preview']) ? new UnstructuredData($result['preview']) : $result;
  }

  /**
   * Change ATK configs to run tests against given preview.
   *
   * @param string $preview
   * @return Result
   */
  public function previewSet(string $preview): Result {
    return $this->taskTugboatPreviewSet($preview)->run();
  }

  /**
   * Delete one or all previews within project's repo.
   *
   * @param  string $preview Preview ID, or "all" to delete all.
   */
  public function previewDelete(string $preview): Result {
    return $this->taskTugboatPreviewDelete($preview)->run();
  }

  /**
   * @param array $opts
   * @return string
   */
  protected function normalizeElement(array &$opts): string {
    $element = $opts['element'];
    $element = match ($element) {
      'database', 'db' => 'database',
      'code' => 'code',
      'files' => 'files',
    };
    $opts['element'] = $element;
    return $element;
  }

  /**
   * @param string $env
   * @param string $element
   * @return string
   */
  protected function getSnapshotFilename(string $env, string $element): string {
    return implode('_', [
      $this->testorConfig->get('pantheon.site'),
      $env,
      date_format(new \DateTime(), 'Y-m-d\\TH-m-s_T'),
      $element
    ]);
  }

}