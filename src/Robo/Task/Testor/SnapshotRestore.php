<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;
use Robo\Common\BuilderAwareTrait;

/**
 * Generic consumer orchestration: pull a snapshot from the storage and
 * restore it into the target database.
 *
 * Chains three existing primitives, in this order:
 *
 *   1. {@see SnapshotGet}       — download the snapshot object. With no pin
 *                                 the latest (newest-dated) object is fetched
 *                                 (issue #26 default); with `snapshot` set,
 *                                 that exact object is fetched instead.
 *   2. {@see SnapshotImport}    — unpack it and load the SQL into the target
 *                                 database (`sql.command < file.sql`).
 *   3. {@see DbUuidNormalize}   — reconcile the restored Drupal `system.site`
 *                                 uuid with the TARGET environment's uuid.
 *
 * Ordering is load-bearing. UUID normalization MUST run AFTER the import: the
 * import overwrites the target database wholesale, so normalizing before it
 * would massage a uuid that the import then clobbers with the snapshot's
 * (prod's) uuid — leaving the target unimportable, the exact failure #25's
 * design set out to prevent. Per #25, the correct uuid belongs to the target,
 * not the snapshot, so the reconciliation is a consumer-side (restore-time),
 * per-target step — which is why it lives here and not in the producer.
 *
 * Everything is config-driven (bucket, `sql.command`, `uuid.*`) — no
 * project-specific values are baked in. Like {@see DbUuidNormalize} itself,
 * step 3 is a no-op unless a target uuid is resolvable (via `--uuid` or
 * `uuid.value`), so a non-Drupal or uuid-agnostic project restores cleanly
 * with steps 1–2 only.
 */
class SnapshotRestore extends TestorTask
  implements TestorConfigAwareInterface {
  use BuilderAwareTrait;
  use TestorConfigAwareTrait;

  protected string $name;
  protected string $element;
  protected ?string $outputFilename;
  protected ?string $snapshot;
  protected ?string $uuid;
  protected bool $gzip;

  public function __construct(array $args) {
    parent::__construct();
    $this->name = $args['name'];
    $this->element = $args['element'];
    $this->outputFilename = $args['output'] ?? null;
    // Optional version pin, forwarded to SnapshotGet. Null => latest.
    $this->snapshot = $args['snapshot'] ?? null;
    // Optional target uuid, forwarded to DbUuidNormalize. Null => the task
    // falls back to `uuid.value` config, and skips if neither is set.
    $this->uuid = $args['uuid'] ?? null;
    $this->gzip = $args['gzip'] ?? true;
  }

  public function run(): \Robo\Result {
    $task = $this->collectionBuilder();

    // 1. Download (latest, or the pinned snapshot).
    $task
      ->taskSnapshotGet([
        'name' => $this->name,
        'element' => $this->element,
        'output' => $this->outputFilename,
        'snapshot' => $this->snapshot,
      ])
      // SnapshotGet returns the concrete downloaded filename; hand it to
      // SnapshotImport (whose filename() setter strips the extension).
      ->storeState('filename', 'filename')
      // 2. Load it into the target database.
      ->taskSnapshotImport(['gzip' => $this->gzip])
      ->deferTaskConfiguration('filename', 'filename')
      // 3. Reconcile the target's Drupal site uuid — AFTER the import, since
      //    the import replaces the database the previous step would have
      //    massaged. A no-op when no target uuid is resolvable.
      ->taskDbUuidNormalize(['uuid' => $this->uuid]);

    return $task->run();
  }

}
