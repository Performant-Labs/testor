<?php

namespace PL\Tests\Robo\Task\Testor\Helper;

/**
 * The throwaway "robofile" TestorTestCase::collectionBuilder() builds a real
 * CollectionBuilder against, for a task run WITHOUT a mock builder attached.
 *
 * Needs Testor's own task trait, not just Robo's built-ins: a task like
 * SnapshotCreate calls nested Testor tasks (taskArchivePack, taskArchiveUnpack)
 * during its real run() — every EXISTING test reaches those calls only via a
 * mocked builder that intercepts them before they touch this object, so
 * plain `\Robo\Tasks` (Robo's built-ins only) was never actually exercised
 * for a real, unmocked execution until PostgresSnapshotTest needed one and
 * hit "class Robo\Tasks does not have a method taskArchivePack".
 */
class EmptyRobofile extends \Robo\Tasks {
  use \PL\Robo\Task\Testor\Tasks;
}
