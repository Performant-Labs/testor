<?php

namespace PL\Tests\Robo\Task\Testor;

use PL\Robo\Task\Testor\TugboatPreviewDelete;

class TugboatPreviewDeleteTest extends TestorTestCase {
  public function testTugboatPreviewDeleteAll() {
    $mockShellExec = $this->mockBuiltIn('shell_exec');
    $mockShellExec->expects(self::once())
      ->with('which tugboat')
      ->willReturn('/usr/bin/tugboat');

    $mockFileExists = $this->mockBuiltIn('file_exists');
    $mockFileExists->expects(self::once())
      ->with(getenv('HOME') . '/.tugboat.yml')
      ->willReturn(true);

    /** @var TugboatPreviewDelete $tugboatPreviewDelete */
    $tugboatPreviewDelete = $this->taskTugboatPreviewDelete('all');

    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat ls previews repo=1reporepo1 --json')
      ->andReturn($this->mockTaskExec($tugboatPreviewDelete, 0, '[{"preview":"1PR1"}, {"preview":"2PR1"}, {"preview":"3PR1"}]'));
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat delete 1PR1')
      ->andReturn($this->mockTaskExec($tugboatPreviewDelete, 0, 'OK'));
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat delete 2PR1')
      ->andReturn($this->mockTaskExec($tugboatPreviewDelete, 0, 'OK'));
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat delete 3PR1')
      ->andReturn($this->mockTaskExec($tugboatPreviewDelete, 0, 'OK'));
    $tugboatPreviewDelete->setBuilder($mockBuilder);

    $result = $tugboatPreviewDelete->run();
    self::assertEquals(0, $result->getExitCode());
  }

  public function testTugboatPreviewDeleteSingle() {
    $mockShellExec = $this->mockBuiltIn('shell_exec');
    $mockShellExec->expects(self::once())
      ->with('which tugboat')
      ->willReturn('/usr/bin/tugboat');

    $mockFileExists = $this->mockBuiltIn('file_exists');
    $mockFileExists->expects(self::once())
      ->with(getenv('HOME') . '/.tugboat.yml')
      ->willReturn(true);

    /** @var TugboatPreviewDelete $tugboatPreviewDelete */
    $tugboatPreviewDelete = $this->taskTugboatPreviewDelete('111preview111');

    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskExec')->once()
      ->with('tugboat delete 111preview111')
      ->andReturn($this->mockTaskExec($tugboatPreviewDelete, 0, 'OK'));
    $tugboatPreviewDelete->setBuilder($mockBuilder);

    $result = $tugboatPreviewDelete->run();
    self::assertEquals(0, $result->getExitCode());
  }

  public function testTugboatNotConfigured() {
//        TODO
  }

  public function testTugboatNotFound() {
//        TODO
  }

}