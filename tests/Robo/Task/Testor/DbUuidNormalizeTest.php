<?php

namespace PL\Tests\Robo\Task\Testor {

  use PL\Robo\Task\Testor\DbUuidNormalize;

  /**
   * Tests for {@see DbUuidNormalize}, the consumer-side Drupal site-UUID
   * massaging step (issue #25).
   *
   * The task normalizes the active `system.site:uuid` in a restored database
   * so that a Pantheon-prod snapshot imports cleanly into a differently-UUID'd
   * Drupal environment (a fresh Flotilla preview). See docs/handoffs for the
   * reproduced failure ("Site UUID in source storage does not match the target
   * storage.").
   */
  class DbUuidNormalizeTest extends TestorTestCase {

    /**
     * With no target uuid resolvable at all, the task skips (like DbSanitize
     * skips when sanitize.command is unset) — it must never invent a uuid.
     */
    public function testSkipsWhenNoUuidConfigured() {
      $mockBuilder = $this->mockCollectionBuilder();
      $dbUuidNormalize = $this->taskDbUuidNormalize([]);
      $mockBuilder->shouldReceive('taskExec')->never();
      $dbUuidNormalize->setBuilder($mockBuilder);

      $result = $dbUuidNormalize->run();
      self::assertEquals(0, $result->getExitCode());
      self::assertStringContainsString('Skip', $result->getMessage());
    }

    /**
     * The `--uuid` option supplies the target uuid; with no custom command
     * configured the task runs the safe default `drush config:set`, with the
     * uuid substituted in. This is the decisive assertion: the EXACT uuid the
     * caller asked for must reach the executed command.
     */
    public function testRunsDefaultCommandWithOptionUuid() {
      $uuid = 'a0030de7-5e41-4016-908d-3e0845e30acb';
      $mockBuilder = $this->mockCollectionBuilder();
      $dbUuidNormalize = $this->taskDbUuidNormalize(['uuid' => $uuid]);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with("drush config:set system.site uuid $uuid -y")
        ->andReturn($this->mockTaskExec($dbUuidNormalize, 0, 'OK'));
      $dbUuidNormalize->setBuilder($mockBuilder);

      $result = $dbUuidNormalize->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * A site may configure its own massaging command via `uuid.command`,
     * using the `%uuid%` placeholder. The task substitutes the resolved target
     * uuid into every occurrence of the placeholder. This is what keeps the
     * task generic (config-driven, not performantlabs.com-hardcoded) and lets a
     * site use raw SQL when drush isn't bootstrappable.
     */
    public function testSubstitutesUuidIntoConfiguredCommand() {
      $uuid = '5d00dfdf-9614-48ed-91ba-d902cbb96b05';
      /** @var \Consolidation\Config\Config $testorConfig */
      $testorConfig = $this->getContainer()->get('testorConfig');
      $testorConfig->set(
        'uuid.command',
        'drush sql:query "UPDATE config SET data = REPLACE(data, uuid_placeholder, %uuid%) WHERE name = \'system.site\'"'
      );
      $mockBuilder = $this->mockCollectionBuilder();
      $dbUuidNormalize = $this->taskDbUuidNormalize(['uuid' => $uuid]);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with("drush sql:query \"UPDATE config SET data = REPLACE(data, uuid_placeholder, $uuid) WHERE name = 'system.site'\"")
        ->andReturn($this->mockTaskExec($dbUuidNormalize, 0, 'OK'));
      $dbUuidNormalize->setBuilder($mockBuilder);

      $result = $dbUuidNormalize->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * The target uuid may also come from config (`uuid.value`) rather than the
     * option, e.g. a project pins the uuid its committed config/sync expects.
     * The explicit option still wins over config (tested below); here config is
     * the only source.
     */
    public function testResolvesUuidFromConfigValue() {
      $uuid = 'deadbeef-0000-4000-8000-000000000000';
      /** @var \Consolidation\Config\Config $testorConfig */
      $testorConfig = $this->getContainer()->get('testorConfig');
      $testorConfig->set('uuid.value', $uuid);
      $mockBuilder = $this->mockCollectionBuilder();
      $dbUuidNormalize = $this->taskDbUuidNormalize([]);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with("drush config:set system.site uuid $uuid -y")
        ->andReturn($this->mockTaskExec($dbUuidNormalize, 0, 'OK'));
      $dbUuidNormalize->setBuilder($mockBuilder);

      $result = $dbUuidNormalize->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * The explicit `--uuid` option overrides a configured `uuid.value`.
     */
    public function testOptionUuidOverridesConfigValue() {
      $configUuid = 'deadbeef-0000-4000-8000-000000000000';
      $optionUuid = 'a0030de7-5e41-4016-908d-3e0845e30acb';
      /** @var \Consolidation\Config\Config $testorConfig */
      $testorConfig = $this->getContainer()->get('testorConfig');
      $testorConfig->set('uuid.value', $configUuid);
      $mockBuilder = $this->mockCollectionBuilder();
      $dbUuidNormalize = $this->taskDbUuidNormalize(['uuid' => $optionUuid]);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with("drush config:set system.site uuid $optionUuid -y")
        ->andReturn($this->mockTaskExec($dbUuidNormalize, 0, 'OK'));
      $dbUuidNormalize->setBuilder($mockBuilder);

      $result = $dbUuidNormalize->run();
      self::assertEquals(0, $result->getExitCode());
    }

    /**
     * A malformed uuid must be rejected before it ever reaches the shell —
     * this both catches operator error and blocks command injection through
     * the substituted value.
     */
    public function testRejectsMalformedUuid() {
      $mockBuilder = $this->mockCollectionBuilder();
      $dbUuidNormalize = $this->taskDbUuidNormalize(['uuid' => 'not-a-uuid; rm -rf /']);
      $mockBuilder->shouldReceive('taskExec')->never();
      $dbUuidNormalize->setBuilder($mockBuilder);

      $result = $dbUuidNormalize->run();
      self::assertEquals(1, $result->getExitCode());
      self::assertStringContainsString('uuid', strtolower($result->getMessage()));
    }

    /**
     * A failing massaging command propagates its non-zero exit code and error
     * text (mirrors DbSanitize / SnapshotCreate error propagation).
     */
    public function testPropagatesCommandFailure() {
      $uuid = 'a0030de7-5e41-4016-908d-3e0845e30acb';
      $mockBuilder = $this->mockCollectionBuilder();
      $dbUuidNormalize = $this->taskDbUuidNormalize(['uuid' => $uuid]);
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with("drush config:set system.site uuid $uuid -y")
        ->andReturn($this->mockTaskExec(new \Robo\Result($dbUuidNormalize, 1, 'SPOOKY SCARY ERROR')));
      $dbUuidNormalize->setBuilder($mockBuilder);

      $result = $dbUuidNormalize->run();
      self::assertEquals(1, $result->getExitCode());
      self::assertStringContainsString('SPOOKY SCARY ERROR', $result->getMessage());
    }

  }
}
