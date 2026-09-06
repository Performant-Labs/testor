<?php

namespace PL\Tests\Robo\Task\Testor\Helper;

/**
 * The Postgres test harness for issue #40 (epic #39) — sets up/tears down
 * real throwaway databases on a real Postgres server for
 * PostgresSnapshotTest, and provides a verification query helper.
 *
 * Deliberately shells out to `psql`/`createdb`/`dropdb` (the same libpq
 * client tools `sqldump.command`/`sql.command` already use) rather than
 * connecting via PHP's pdo_pgsql driver — this keeps the harness's only
 * dependency identical to Testor's own runtime dependency (a `psql`-family
 * binary on PATH), instead of adding a PHP extension requirement nothing
 * else in this repo needs.
 *
 * Connection details come from the same TESTOR_PG_HOST/TESTOR_PG_PORT/
 * TESTOR_PG_USER/PGPASSWORD environment variables .testor_postgres_test.yml
 * substitutes into `sqldump.command`/`sql.command` — one source of truth for
 * "which Postgres server" between the fixture and the harness.
 */
class PostgresTestHarness {

  /**
   * Confirms the environment is actually configured for a real Postgres
   * run, with a clear skip (not a cryptic connection-refused failure) when
   * it isn't. CI always sets these (.github/workflows/php.yml); a
   * contributor running the suite without a local Postgres set up gets a
   * readable reason instead of a stack trace.
   */
  public static function ensureAvailable(): void {
    foreach (['TESTOR_PG_HOST', 'TESTOR_PG_PORT', 'TESTOR_PG_USER'] as $var) {
      if (getenv($var) === false || getenv($var) === '') {
        \PHPUnit\Framework\Assert::markTestSkipped(
          "PostgresSnapshotTest needs a real Postgres server: $var is not set. " .
          "See tests/Robo/Task/Testor/Helper/PostgresTestHarness.php for the required env vars, " .
          "or run against .github/workflows/php.yml's postgres service."
        );
      }
    }
  }

  private function host(): string {
    return getenv('TESTOR_PG_HOST');
  }

  private function port(): string {
    return getenv('TESTOR_PG_PORT');
  }

  private function user(): string {
    return getenv('TESTOR_PG_USER');
  }

  /** Base psql/createdb/dropdb connection flags, shared by every call. */
  private function connFlags(): string {
    return sprintf('-h %s -p %s -U %s', escapeshellarg($this->host()), escapeshellarg($this->port()), escapeshellarg($this->user()));
  }

  /**
   * Runs a shell command and throws with its combined output on failure —
   * every harness call is setup/teardown/verification for a test, not the
   * thing under test, so a silent partial failure here must not masquerade
   * as a passing (or confusingly failing) SnapshotCreate/Import assertion.
   */
  private function run(string $command): string {
    exec($command . ' 2>&1', $lines, $exitCode);
    $output = implode("\n", $lines);
    if ($exitCode !== 0) {
      throw new \RuntimeException("PostgresTestHarness command failed ($exitCode): $command\n$output");
    }
    return $output;
  }

  public function createDatabase(string $name): void {
    $this->dropDatabase($name);
    $this->run(sprintf('createdb %s %s', $this->connFlags(), escapeshellarg($name)));
  }

  public function dropDatabase(string $name): void {
    $this->run(sprintf('dropdb --if-exists %s %s', $this->connFlags(), escapeshellarg($name)));
  }

  /**
   * Seeds one small table with one row, distinctive enough that reading it
   * back on the OTHER side of a real dump/import round-trip is genuine
   * proof of content transfer, not a false-positive from a pre-existing
   * table.
   */
  public function seedFixtureTable(string $dbName): void {
    $sql = "create table widgets (id integer primary key, name text not null); "
      . "insert into widgets (id, name) values (1, 'real-postgres-fixture');";
    $this->run(sprintf(
      'psql %s -d %s -v ON_ERROR_STOP=1 -c %s',
      $this->connFlags(),
      escapeshellarg($dbName),
      escapeshellarg($sql),
    ));
  }

  /** Runs a scalar query against $dbName and returns the trimmed text result. */
  public function queryScalar(string $dbName, string $sql): string {
    return trim($this->run(sprintf(
      'psql %s -d %s -t -A -v ON_ERROR_STOP=1 -c %s',
      $this->connFlags(),
      escapeshellarg($dbName),
      escapeshellarg($sql),
    )));
  }

}
