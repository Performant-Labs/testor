<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;
use Robo\Common\BuilderAwareTrait;
use Robo\Result;

/**
 * Generic producer orchestration: (optionally) pull a fresh snapshot from the
 * source environment, sanitize it, then push it to the configured storage.
 *
 * This is the producer half of the epic-#24 pipeline. It chains the existing
 * primitives — {@see SnapshotViaBackup}/{@see SnapshotCreate}, {@see DbSanitize},
 * {@see SnapshotPut} — into one repeatable, config-driven command. Everything
 * (Pantheon site, sanitization rules, bucket) comes from `.testor.yml`; nothing
 * is hardcoded to any particular project.
 *
 * Deliberately NO UUID-normalization step. Per issue #25's settled design, the
 * Drupal site-UUID massaging ({@see DbUuidNormalize}) is a CONSUMER-side
 * (restore-time), per-target concern: the correct uuid is a property of the
 * target environment, not of the snapshot, since the same snapshot legitimately
 * restores into many differently-UUID'd targets. Adding it here would build a
 * parallel path that contradicts that design. It belongs to the consumer
 * command (issue #28) instead.
 *
 * The chain short-circuits: each stage's result is checked, and a failure
 * returns immediately so that no later stage runs (e.g. a failed sanitize must
 * never push an unsanitized dump to storage).
 *
 * Mirrors {@see SnapshotGet}'s orchestration style: it drives sub-tasks through
 * `collectionBuilder()->taskX()->run()` and inspects each Result, rather than
 * shelling out to the `testor` binary from within itself.
 */
class SnapshotRefresh extends TestorTask implements TestorConfigAwareInterface {
  use BuilderAwareTrait;
  use TestorConfigAwareTrait;

  /**
   * When true, the pull stage is skipped and the chain operates on an
   * already-local dump (re-sanitize / re-push without hitting the source
   * environment again).
   */
  protected bool $skipDownload;

  /**
   * Full option bag, forwarded to each sub-task (env, name, element,
   * do-not-sanitize, filename, …). Sub-tasks read only the keys they need.
   *
   * @var array
   */
  protected array $opts;

  public function __construct(array $opts) {
    parent::__construct();
    $this->skipDownload = (bool) ($opts['skip-download'] ?? false);
    $this->opts = $opts;
  }

  public function run(): Result {
    $opts = $this->opts;

    // Stage 1 (optional): pull a fresh snapshot from the source environment.
    if (!$this->skipDownload) {
      $result = $this->pull($opts);
      if (!$result->wasSuccessful()) {
        return $result;
      }
    }

    // Stage 2: sanitize the (local) dump.
    $result = $this->collectionBuilder()->taskDbSanitize($opts)->run();
    if (!$result->wasSuccessful()) {
      // Short-circuit: never push an unsanitized dump.
      return $result;
    }

    // Stage 3: push the sanitized result to the configured storage.
    return $this->collectionBuilder()->taskSnapshotPut($opts)->run();
  }

  /**
   * Pull a fresh snapshot from the source environment.
   *
   * A Pantheon env (name not starting with '@') uses the backup-based path
   * ({@see SnapshotViaBackup}); a local/Drush-alias env uses a local dump
   * ({@see SnapshotCreate}). This mirrors the branch already in
   * {@see \PL\Robo\Plugin\Commands\TestorCommands::snapshotCreate()}.
   */
  protected function pull(array $opts): Result {
    $env = $opts['env'];
    $ispantheon = !str_starts_with($env, '@');

    if ($ispantheon) {
      return $this->collectionBuilder()->taskSnapshotViaBackup($opts)->run();
    }

    return $this->collectionBuilder()
      ->taskSnapshotCreate([...$opts, 'ispantheon' => false])
      ->run();
  }

}
