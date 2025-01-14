<?php

namespace PL\Tests\Robo\Task\Testor;

use PL\Robo\Task\Testor\TugboatPreviewDeleteAll;

class TugboatPreviewDeleteAllTest extends TestorTestCase {
  public function testTugboatPreviewDeleteAll() {
    $mockShellExec = $this->mockBuiltIn('shell_exec');
    $mockShellExec->expects(self::once())
      ->with('which tugboat')
      ->willReturn('/usr/bin/tugboat');

    $mockFileExists = $this->mockBuiltIn('file_exists');
    $mockFileExists->expects(self::once())
      ->with(getenv('HOME') . '/.tugboat.yml')
      ->willReturn(true);

    /** @var TugboatPreviewDeleteAll $tugboatPreviewDeleteAll */
    $tugboatPreviewDeleteAll = $this->taskTugboatPreviewDeleteAll();

    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat ls previews repo=1reporepo1 --json')
      ->andReturn($this->mockTaskExec($tugboatPreviewDeleteAll, 0, '[{"preview":"1PR1"}, {"preview":"2PR1"}, {"preview":"3PR1"}]'));
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat delete 1PR1')
      ->andReturn($this->mockTaskExec($tugboatPreviewDeleteAll, 0, 'OK'));
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat delete 2PR1')
      ->andReturn($this->mockTaskExec($tugboatPreviewDeleteAll, 0, 'OK'));
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat delete 3PR1')
      ->andReturn($this->mockTaskExec($tugboatPreviewDeleteAll, 0, 'OK'));
    $tugboatPreviewDeleteAll->setBuilder($mockBuilder);

    $result = $tugboatPreviewDeleteAll->run();
    self::assertEquals(0, $result->getExitCode());
  }

  public function testTugboatNotConfigured() {
//        TODO
  }

  public function testTugboatNotFound() {
//        TODO
  }

}