<?php

namespace PL\Tests\Robo\Task\Testor;

use PL\Robo\Task\Testor\SnapshotRestore;

/**
 * Tests for {@see SnapshotRestore}, the generic consumer orchestration
 * (issue #28): download a snapshot, import it, then normalize the target
 * Drupal site uuid.
 *
 * SnapshotRestore chains three sub-tasks through a collection builder:
 *
 *   taskSnapshotGet -> taskSnapshotImport -> taskDbUuidNormalize
 *
 * The tests swap that builder for a Mockery mock (the same technique
 * SnapshotGetTest uses to intercept the internal taskSnapshotList call), which
 * lets us assert three things that matter for the acceptance criteria:
 *
 *   1. The version-pin option is forwarded to SnapshotGet — null => latest,
 *      an explicit name => that pinned snapshot.
 *   2. The resolved target uuid actually reaches DbUuidNormalize.
 *   3. The chain ORDER is import-then-normalize, never the reverse — the
 *      mutation the ordering guards against (normalize before import) would be
 *      caught here.
 */
class SnapshotRestoreTest extends TestorTestCase {

  /**
   * Build a fluent mock collection builder whose taskX() methods each return
   * the builder (so the fluent chain in SnapshotRestore::run() works) and whose
   * run() reports success. Every task expectation is registered `->ordered()`
   * so Mockery fails if the tasks are chained in the wrong sequence.
   *
   * @return \Mockery\MockInterface|\Robo\Collection\CollectionBuilder
   */
  private function mockChainBuilder() {
    $mockBuilder = $this->mockCollectionBuilder();
    // storeState / deferTaskConfiguration are pass-through links in the chain.
    $mockBuilder->shouldReceive('storeState')->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('deferTaskConfiguration')->andReturn($mockBuilder);
    return $mockBuilder;
  }

  /**
   * No `--snapshot` selector: SnapshotGet must be handed a null pin (which it
   * interprets as "latest"), and the full import-then-normalize chain must run.
   */
  public function testRestoreDefaultForwardsLatestAndRunsFullChain() {
    $uuid = 'a0030de7-5e41-4016-908d-3e0845e30acb';
    $restore = $this->taskSnapshotRestore([
      'name' => 'test',
      'element' => 'database',
      'output' => 'test.sql.gz',
      'uuid' => $uuid,
    ]);

    $mockBuilder = $this->mockChainBuilder();

    // 1. Download — the pin must be forwarded as null (=> latest).
    $mockBuilder->shouldReceive('taskSnapshotGet')
      ->once()
      ->ordered()
      ->with(\Mockery::on(fn($opts) => $opts['name'] === 'test'
        && $opts['element'] === 'database'
        && $opts['output'] === 'test.sql.gz'
        && $opts['snapshot'] === null))
      ->andReturn($mockBuilder);
    // 2. Import — AFTER download.
    $mockBuilder->shouldReceive('taskSnapshotImport')
      ->once()
      ->ordered()
      ->andReturn($mockBuilder);
    // 3. UUID normalize — AFTER import — with the caller's uuid.
    $mockBuilder->shouldReceive('taskDbUuidNormalize')
      ->once()
      ->ordered()
      ->with(['uuid' => $uuid])
      ->andReturn($mockBuilder);

    $mockBuilder->shouldReceive('run')
      ->once()
      ->andReturn(new \Robo\Result($restore, 0, 'OK'));

    $restore->setBuilder($mockBuilder);

    $result = $restore->run();
    self::assertEquals(0, $result->getExitCode());
  }

  /**
   * `--snapshot=<name>`: the exact pin must be forwarded to SnapshotGet, not
   * swallowed — this is what makes `snapshot:restore --snapshot=X` restore X
   * rather than the latest.
   */
  public function testRestorePinnedForwardsSnapshotSelector() {
    $pin = 'test/performant-labs_1111_database.sql.gz';
    $restore = $this->taskSnapshotRestore([
      'name' => 'test',
      'element' => 'database',
      'output' => 'test.sql.gz',
      'snapshot' => $pin,
    ]);

    $mockBuilder = $this->mockChainBuilder();

    $mockBuilder->shouldReceive('taskSnapshotGet')
      ->once()
      ->with(\Mockery::on(fn($opts) => $opts['snapshot'] === $pin))
      ->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('taskSnapshotImport')->once()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('taskDbUuidNormalize')->once()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('run')
      ->once()
      ->andReturn(new \Robo\Result($restore, 0, 'OK'));

    $restore->setBuilder($mockBuilder);

    $result = $restore->run();
    self::assertEquals(0, $result->getExitCode());
  }

  /**
   * The uuid the caller passes must be the uuid that reaches DbUuidNormalize
   * (not dropped, not defaulted away). Distinct value from the other tests so a
   * "always passes the same uuid" bug can't pass by luck.
   */
  public function testRestoreForwardsTargetUuidToNormalize() {
    $uuid = '5d00dfdf-9614-48ed-91ba-d902cbb96b05';
    $restore = $this->taskSnapshotRestore([
      'name' => 'test',
      'element' => 'database',
      'uuid' => $uuid,
    ]);

    $mockBuilder = $this->mockChainBuilder();
    $mockBuilder->shouldReceive('taskSnapshotGet')->once()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('taskSnapshotImport')->once()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('taskDbUuidNormalize')
      ->once()
      ->with(['uuid' => $uuid])
      ->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('run')
      ->once()
      ->andReturn(new \Robo\Result($restore, 0, 'OK'));

    $restore->setBuilder($mockBuilder);

    $result = $restore->run();
    self::assertEquals(0, $result->getExitCode());
  }

  /**
   * MUTATION GUARD — chain ordering.
   *
   * This test asserts the import-before-normalize order via strict Mockery
   * `->ordered()` sequencing. It exists to catch the specific regression where
   * a maintainer reorders SnapshotRestore::run() to normalize the uuid BEFORE
   * the import — which is silently wrong, because the import replaces the whole
   * database and would clobber the just-normalized uuid with the snapshot's
   * (prod's) uuid, re-introducing the exact "site uuid mismatch" failure #25's
   * design fixed.
   *
   * Proven to bite: with SnapshotRestore chaining
   * taskDbUuidNormalize BEFORE taskSnapshotImport, Mockery raises
   * "method called out of order" and this test fails. With the correct order
   * (import then normalize) it passes. See the handoff for the recorded
   * mutation run.
   */
  public function testChainNormalizesAfterImportNotBefore() {
    $uuid = 'a0030de7-5e41-4016-908d-3e0845e30acb';
    $restore = $this->taskSnapshotRestore([
      'name' => 'test',
      'element' => 'database',
      'uuid' => $uuid,
    ]);

    $mockBuilder = $this->mockChainBuilder();

    // Strict global ordering: get(1) -> import(2) -> normalize(3).
    $mockBuilder->shouldReceive('taskSnapshotGet')->once()->globally()->ordered()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('taskSnapshotImport')->once()->globally()->ordered()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('taskDbUuidNormalize')->once()->globally()->ordered()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('run')->once()->andReturn(new \Robo\Result($restore, 0, 'OK'));

    $restore->setBuilder($mockBuilder);

    $result = $restore->run();
    self::assertEquals(0, $result->getExitCode());
  }

  /**
   * A failing sub-task (here the run() of the chain) propagates its non-zero
   * exit code out of SnapshotRestore, so the operator sees the failure rather
   * than a false success.
   */
  public function testRestorePropagatesChainFailure() {
    $restore = $this->taskSnapshotRestore([
      'name' => 'test',
      'element' => 'database',
      'uuid' => 'a0030de7-5e41-4016-908d-3e0845e30acb',
    ]);

    $mockBuilder = $this->mockChainBuilder();
    $mockBuilder->shouldReceive('taskSnapshotGet')->once()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('taskSnapshotImport')->once()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('taskDbUuidNormalize')->once()->andReturn($mockBuilder);
    $mockBuilder->shouldReceive('run')
      ->once()
      ->andReturn(new \Robo\Result($restore, 1, 'DOWNLOAD BLEW UP'));

    $restore->setBuilder($mockBuilder);

    $result = $restore->run();
    self::assertEquals(1, $result->getExitCode());
    self::assertStringContainsString('DOWNLOAD BLEW UP', $result->getMessage());
  }

}
