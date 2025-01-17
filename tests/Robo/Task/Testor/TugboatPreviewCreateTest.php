<?php

namespace PL\Tests\Robo\Task\Testor;

use PL\Robo\Task\Testor\TugboatPreviewCreate;

class TugboatPreviewCreateTest extends TestorTestCase {
  public function testTugboatPreviewCreate() {
    $mockShellExec = $this->mockBuiltIn('shell_exec');
    $mockShellExec->expects(self::once())
      ->with('which tugboat')
      ->willReturn('/usr/bin/tugboat');

    $mockFileExists = $this->mockBuiltIn('file_exists');
    $mockFileExists->expects(self::once())
      ->with(getenv('HOME') . '/.tugboat.yml')
      ->willReturn(true);

    $mockDate = $this->mockBuiltIn('date');
    $mockDate->expects(self::once())
      ->willReturn('1970-01-01 00:00:00');

    /** @var TugboatPreviewCreate $tugboatPreviewCreate */
    $tugboatPreviewCreate = $this->taskTugboatPreviewCreate(['base' => null]);

    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('git branch --no-color --show-current')
      ->andReturn($this->mockTaskExec($tugboatPreviewCreate, 0, 'IL/test-branch'));
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat create preview "IL/test-branch" repo=1reporepo1 label="Branch:IL/test-branch 1970-01-01 00:00:00" output=json')
      ->andReturn($this->mockTaskExec($tugboatPreviewCreate, 0, '{"preview":"newly"}'));
    $tugboatPreviewCreate->setBuilder($mockBuilder);

    $result = $tugboatPreviewCreate->run();
    self::assertEquals(0, $result->getExitCode());
    self::assertEquals(['preview' => 'newly'], $result['preview']);
  }

  public function testTugboatPreviewCreateBaseFalse() {
    $mockShellExec = $this->mockBuiltIn('shell_exec');
    $mockShellExec->expects(self::once())
      ->with('which tugboat')
      ->willReturn('/usr/bin/tugboat');

    $mockFileExists = $this->mockBuiltIn('file_exists');
    $mockFileExists->expects(self::once())
      ->with(getenv('HOME') . '/.tugboat.yml')
      ->willReturn(true);

    $mockDate = $this->mockBuiltIn('date');
    $mockDate->expects(self::once())
      ->willReturn('1970-01-01 00:00:00');

    /** @var TugboatPreviewCreate $tugboatPreviewCreate */
    $tugboatPreviewCreate = $this->taskTugboatPreviewCreate(['base' => 'false']);

    $mockBuilder = $this->mockCollectionBuilder();
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('git branch --no-color --show-current')
      ->andReturn($this->mockTaskExec($tugboatPreviewCreate, 0, 'IL/test-branch'));
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat create preview "IL/test-branch" base=false repo=1reporepo1 label="Branch:IL/test-branch 1970-01-01 00:00:00" output=json')
      ->andReturn($this->mockTaskExec($tugboatPreviewCreate, 0, '{"preview":"newly"}'));
    $tugboatPreviewCreate->setBuilder($mockBuilder);

    $result = $tugboatPreviewCreate->run();
    self::assertEquals(0, $result->getExitCode());
    self::assertEquals(['preview' => 'newly'], $result['preview']);
  }

}