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
 * primitives — {@see SnapshotViaBackup}/{@see SnapshotCreate}, {@see SnapshotImport},
 * {@see DbSanitize}, {@see SnapshotCreate} (re-export), {@see SnapshotPut} — into
 * one repeatable, config-driven command. Everything (Pantheon site, sanitization
 * rules, bucket) comes from `.testor.yml`; nothing is hardcoded to any particular
 * project.
 *
 * SECURITY-CRITICAL ORDERING (issue #33). The pulled dump is raw production data.
 * `drush sql:sanitize` only ever mutates the database drush is *connected* to — it
 * cannot reach into a `.sql`/`.tar.gz` file on disk. So a chain of
 * pull → sanitize → put sanitizes some unrelated already-connected local DB and
 * then pushes the UNTOUCHED raw download — leaking every prod email/password hash.
 * The only correct order is therefore:
 *
 *   pull → IMPORT the pulled dump into the DB → sanitize THAT DB → RE-EXPORT the
 *   now-sanitized DB back into the file → put.
 *
 * The import (`sql.command < file.sql`, via {@see SnapshotImport}) and re-export
 * (`sqldump.command > file.sql` + pack, via {@see SnapshotCreate}'s local branch)
 * are the two stages that make the sanitize operate on — and the push carry — the
 * pulled data. This holds identically for the Pantheon and the local/`@self`
 * puller: both land a raw `.tar.gz` that is only sanitized once it has been
 * imported, sanitized in place, and re-exported.
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
 * returns immediately so that no later stage runs. A failed pull, import,
 * sanitize, OR re-export must never let an unsanitized (or corrupted) file reach
 * {@see SnapshotPut}.
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

    // Stage 2: import the pulled RAW dump into the database, so that the
    // sanitize in stage 3 has real data to act on. Without this, sanitize
    // mutates some unrelated already-connected DB and the raw download is
    // pushed untouched (issue #33).
    //
    // `gzip` tells SnapshotImport whether it must unpack a tar+gzip archive
    // first, or whether the pull already left a plain `.sql` on disk:
    //   - Local puller (SnapshotCreate) always packs its `.sql` into a real
    //     `…tar.gz` -> needs unpacking (`gzip => true`).
    //   - `--skip-download` always reuses an already-local `…tar.gz` (the
    //     format every earlier pull in this codebase leaves behind) -> needs
    //     unpacking (`gzip => true`).
    //   - Pantheon `database` pulls (SnapshotViaBackup) decompress terminus's
    //     plain-gzip download straight to `.sql` themselves (issue #35 — a
    //     Pantheon database backup has no tar layer at all) -> the archive is
    //     already `.sql`, so import must NOT try to unpack it (`gzip =>
    //     false`), or it fails trying to open real SQL content as a tar file.
    $env = $opts['env'];
    $ispantheon = !str_starts_with($env, '@');
    $alreadySql = !$this->skipDownload && $ispantheon && ($opts['element'] ?? 'database') === 'database';
    $result = $this->collectionBuilder()
      ->taskSnapshotImport([...$opts, 'gzip' => !$alreadySql])
      ->run();
    if (!$result->wasSuccessful()) {
      // Short-circuit: a failed import must not let a raw dump reach the push.
      return $result;
    }

    // Stage 3: sanitize the just-imported database in place.
    $result = $this->collectionBuilder()->taskDbSanitize($opts)->run();
    if (!$result->wasSuccessful()) {
      // Short-circuit: never push an unsanitized dump.
      return $result;
    }

    // Stage 4: re-export the now-sanitized database back into the file that
    // stage 5 pushes. Reuses SnapshotCreate's local branch
    // (`sqldump.command > file.sql`, then pack to `file.tar.gz`). Without this,
    // the file on disk is still the raw pre-sanitize dump from stage 1.
    $result = $this->collectionBuilder()
      ->taskSnapshotCreate([...$opts, 'ispantheon' => false])
      ->run();
    if (!$result->wasSuccessful()) {
      // Short-circuit: a failed/partial re-export must not reach the push.
      return $result;
    }

    // Stage 5: push the sanitized, re-exported result to the configured storage.
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
