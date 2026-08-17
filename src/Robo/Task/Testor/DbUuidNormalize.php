<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfigAwareTrait;
use PL\Robo\Contract\TestorConfigAwareInterface;

/**
 * Normalize the Drupal site UUID in a restored database.
 *
 * Drupal's config-sync system refuses to import configuration whose
 * `system.site:uuid` differs from the target (active) storage —
 * `\Drupal\Core\Config\StorageComparer::validateSiteUuid()` requires
 * `$source['uuid'] === $target['uuid']`, and `drush config:import` aborts with
 * "Site UUID in source storage does not match the target storage." (fatal).
 *
 * A Pantheon-prod snapshot carries prod's uuid; a fresh Flotilla preview (or
 * any differently-installed environment) has a different active uuid. This task
 * is the consumer-side massaging step that reconciles them: it overwrites the
 * active `system.site:uuid` with a caller-supplied target uuid so the target
 * config (typically the repo's committed `config/sync`) can be imported.
 *
 * The massaging lives on the CONSUMER (restore) side, not the producer side,
 * because the correct uuid is a property of the target environment, not of the
 * snapshot: the same snapshot legitimately restores into many targets, each of
 * which may want a different resulting uuid. See the issue #25 write-up.
 *
 * Mirrors {@see DbSanitize}: a config-driven database transformation that is a
 * no-op unless configured — it never invents a uuid.
 */
class DbUuidNormalize extends TestorTask implements TestorConfigAwareInterface {
  use TestorConfigAwareTrait;

  /**
   * Placeholder in a configured `uuid.command` that is replaced with the
   * resolved target uuid before execution.
   */
  protected const PLACEHOLDER = '%uuid%';

  /**
   * Default massaging command when the project configures no `uuid.command`.
   * Safe and surgical: `drush config:set` updates only the `system.site` uuid
   * key rather than blindly rewriting the serialized `config` blob.
   */
  protected const DEFAULT_COMMAND = 'drush config:set system.site uuid ' . self::PLACEHOLDER . ' -y';

  /**
   * RFC-4122-shaped uuid. Deliberately strict: the resolved value is
   * interpolated into a shell command, so anything that is not a bare uuid must
   * be rejected before it can reach the shell (both operator-error and
   * injection defence).
   */
  protected const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

  protected string|null $uuid;

  public function __construct(array $opts) {
    parent::__construct();
    $this->uuid = $opts['uuid'] ?? null;
  }

  public function run(): \Robo\Result {
    // Resolve the target uuid: explicit --uuid option wins over config default.
    $uuid = $this->uuid ?? $this->testorConfig->get('uuid.value');

    if ($uuid === null || $uuid === '') {
      $this->message = 'Skip UUID normalization, because no target uuid is set '
        . '(pass --uuid or set uuid.value in .testor.yml)';
      return $this->pass();
    }

    if (!is_string($uuid) || !preg_match(self::UUID_PATTERN, $uuid)) {
      $this->message = "Refusing to normalize: '$uuid' is not a valid uuid";
      return $this->fail();
    }

    $command = $this->testorConfig->get('uuid.command') ?? self::DEFAULT_COMMAND;
    $command = str_replace(self::PLACEHOLDER, $uuid, $command);

    return $this->exec($command);
  }

}
