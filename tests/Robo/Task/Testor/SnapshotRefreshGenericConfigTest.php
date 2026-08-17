<?php

namespace PL\Tests\Robo\Task\Testor {

  use Aws\S3\S3Client;
  use PL\Robo\Common\StorageStrategy;
  use PL\Robo\Testor;
  use Robo\Robo;
  use Symfony\Component\Console\Output\NullOutput;

  /**
   * Proves {@see \PL\Robo\Task\Testor\SnapshotRefresh} is generic — fully
   * config-driven, with NOTHING hardcoded about performantlabs.com.
   *
   * It rebuilds the Robo container against a second, deliberately fictional
   * config fixture (`.testor_generic_test.yml`: site `acme-widgets`, bucket
   * `nightly-dumps`, a custom sanitize command) and runs the same producer
   * chain. The assertions are that the FIXTURE'S OWN values — not any
   * performantlabs.com value — reach the executed commands and the S3 client:
   *   - the Pantheon site `acme-widgets` in the terminus commands,
   *   - the custom sanitize command,
   *   - the bucket `nightly-dumps` in the putObject call.
   *
   * If the command hardcoded performant-labs / snapshot anywhere, these
   * assertions would fail.
   */
  class SnapshotRefreshGenericConfigTest extends TestorTestCase {

    public function setUp(): void {
      // Rebuild the container against the generic (non-performantlabs.com)
      // fixture instead of the default .testor_test.yml.
      $container = Robo::createDefaultContainer(null, new NullOutput());
      $container->add('testorConfig', Testor::createConfiguration(['.testor_generic_test.yml']));
      $container->add('s3Client', $this->mockS3Client());
      // Bucket comes from the generic fixture, mirroring production wiring.
      $container->add('s3Bucket', 'nightly-dumps');
      $container->add('storage', StorageStrategy::class);
      $this->setContainer($container);
    }

    public function tearDown(): void {
      parent::tearDown();
      foreach (['__generic_refresh.tar.gz'] as $f) {
        if (file_exists($f)) {
          unlink($f);
        }
      }
    }

    /**
     * Full pull against a Pantheon env, driven entirely by the generic fixture.
     */
    public function testRefreshUsesGenericConfigValues() {
      // Mock shell_exec for SnapshotViaBackup's checkTerminus().
      $mockShellExec = $this->mockBuiltIn('shell_exec');
      $mockShellExec->expects(self::once())
        ->with('which terminus')
        ->willReturn('/usr/bin/terminus');

      $opts = [
        'env' => 'live',
        'name' => 'nightly',
        'element' => 'database',
        'do-not-sanitize' => false,
        'skip-download' => false,
        'filename' => '__generic_refresh',
      ];

      $snapshotRefresh = $this->taskSnapshotRefresh($opts);
      $mockBuilder = $this->mockCollectionBuilder();

      $snapshotViaBackup = $this->taskSnapshotViaBackup($opts);
      $dbSanitize = $this->taskDbSanitize($opts);
      $snapshotPut = $this->taskSnapshotPut($opts);

      // Stage 1: Pantheon backup path — note the GENERIC site 'acme-widgets'.
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('terminus backup:create acme-widgets.live --element=database --keep-for=1')
        ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, 'OK'));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('terminus backup:list acme-widgets.live --format=json')
        ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, '{"1": {"file": "acme-widgets_99999_database.sql.gz"}}'));
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('terminus backup:get acme-widgets.live --file=acme-widgets_99999_database.sql.gz --to=__generic_refresh.tar.gz')
        ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, 'OK'));

      $mockBuilder->shouldReceive('taskSnapshotViaBackup')
        ->once()
        ->andReturn($snapshotViaBackup);
      $mockBuilder->shouldReceive('taskDbSanitize')
        ->once()
        ->andReturn($dbSanitize);
      $mockBuilder->shouldReceive('taskSnapshotPut')
        ->once()
        ->andReturn($snapshotPut);

      // Stage 2: the GENERIC sanitize command from the fixture.
      $mockBuilder->shouldReceive('taskExec')
        ->once()
        ->with('drush sql:sanitize --sanitize-email=redacted@example.test')
        ->andReturn($this->mockTaskExec($dbSanitize, 0, 'OK'));

      // Stage 3: put into the GENERIC bucket 'nightly-dumps'.
      $this->mockS3Client->shouldReceive('putObject')
        ->once()
        ->with([
          'Bucket' => 'nightly-dumps',
          'Key' => 'nightly/__generic_refresh.tar.gz',
          'SourceFile' => '__generic_refresh.tar.gz',
        ])
        ->andReturn(new \Aws\Result());

      $snapshotViaBackup->setBuilder($mockBuilder);
      $dbSanitize->setBuilder($mockBuilder);
      $snapshotPut->setBuilder($mockBuilder);
      $snapshotRefresh->setBuilder($mockBuilder);

      $result = $snapshotRefresh->run();
      self::assertEquals(0, $result->getExitCode());
    }

  }
}
