<?php

namespace PL\Tests\Robo\Task\Testor;

use PL\Robo\Task\Testor\TugboatPreviewSet;
use function PHPUnit\Framework\logicalOr;

class TugboatPreviewSetTest extends TestorTestCase {
  /**
   * @param $initialConfig string initial content of playwright.config.js
   * @param $initialAtkConfig string initial content of playwright.atk.config.js
   * @param $expectedConfig string expected content of playwright.config.js after modification
   * @param $expectedAtkConfig string expected content of playwright.atk.config.js after modification
   * @dataProvider datasetTugboatPreviewSet
   * @return void
   */
  public function testTugboatPreviewSet($initialConfig, $initialAtkConfig, $expectedConfig, $expectedAtkConfig) {
    $mockShellExec = $this->mockBuiltIn('shell_exec');
    $mockShellExec->expects(self::once())
      ->with('which tugboat')
      ->willReturn('/usr/bin/tugboat');

    $mockFileExists = $this->mockBuiltIn('file_exists');
    $mockFileExists->expects(self::once())
      ->with(getenv('HOME') . '/.tugboat.yml')
      ->willReturn(true);

    $mockFileGetContents = $this->mockBuiltIn('file_get_contents');
    $mockFileGetContents->expects(self::exactly(2))
      ->withReturnMap([
        ['playwright.config.js', $initialConfig],
        ['playwright.atk.config.js', $initialAtkConfig],
      ]);
//        $mockFileGetContents->expects(self::once())
//            ->with('playwright.config.js')
//            ->willReturn($initialConfig);
//        $mockFileGetContents->expects(self::once())
//            ->with('playwright.atk.config.js')
//            ->willReturn($initialAtkConfig);

    $mockFilePutContents = $this->mockBuiltIn('file_put_contents');
    $mockFilePutContents->expects(self::exactly(2))
      ->withReturnMap([
        ['playwright.config.js', $expectedConfig, strlen($expectedConfig)],
        ['playwright.atk.config.js', $expectedAtkConfig, strlen($expectedAtkConfig)]
      ]);
//        $mockFilePutContents->expects(self::once())
//            ->with('playwright.config.js', $expectedConfig)
//            ->willReturn(strlen($expectedConfig));
//        $mockFilePutContents->expects(self::once())
//            ->with('playwright.atk.config.js', $expectedAtkConfig)
//            ->willReturn(strlen($expectedAtkConfig));

    /** @var TugboatPreviewSet $tugboatPreviewSet */
    $tugboatPreviewSet = $this->taskTugboatPreviewSet('12prepre21');

    $mockBuilder = $this->mockCollectionBuilder();
    $previewJson = '[{"id":"11mysql123","name":"mysql","service":"11mysql123","urls":[]},{"id":"11php12345","name":"php","service":"11php12345","urls":["https://tugboatqa.com/test"]}]';
    $mockBuilder->shouldReceive('taskExec')
      ->once()
      ->with('tugboat ls services preview=12prepre21 --json')
      ->andReturn($this->mockTaskExec($tugboatPreviewSet, 0, $previewJson));
    $tugboatPreviewSet->setBuilder($mockBuilder);
    $result = $tugboatPreviewSet->run();
    self::assertEquals(0, $result->getExitCode());
  }

  public static function datasetTugboatPreviewSet(): array {
    return [
      [
        // playwright.config.js
        "import { defineConfig, devices } from '@playwright.test';
                
                export default defineConfig({
                  testDir: './tests',
                  /* other parameters that we are not interested in */
                  blabla: 'blablablaj',
                  /* Base URL */
                  use: {
                    baseURL: 'https://old.url.here',
                  }
                });
                ",
        // playwright.atk.config.js
        "export default {
                  testDir: \"tests\",
                  pantheon: {
                    isTarget: false,
                    site: \"aSite\",
                    environment: \"dev\"
                  },
                  tugboat: {
                    isTarget: false,
                    service: \"<id>\"
                  }
                }",
        // playwright.config.js **after**
        "import { defineConfig, devices } from '@playwright.test';
                
                export default defineConfig({
                  testDir: './tests',
                  /* other parameters that we are not interested in */
                  blabla: 'blablablaj',
                  /* Base URL */
                  use: {
                    baseURL: 'https://tugboatqa.com/test/',
                  }
                });
                ",
        // playwright.atk.config.js **after**
        "export default {
                  testDir: \"tests\",
                  pantheon: {
                    isTarget: false,
                    site: \"aSite\",
                    environment: \"dev\"
                  },
                  tugboat: {
                    isTarget: true,
                    service: \"11php12345\"
                  }
                }\n"
      ],
      [
        // playwright.config.js -- encommented
        "import { defineConfig, devices } from '@playwright.test';
                
                export default defineConfig({
                  testDir: './tests',
                  /* other parameters that we are not interested in */
                  blabla: 'blablablaj',
                  /* Base URL */
                  use: {
//                    baseURL: 'https://old.url.here',
                  }
                });
                ",
        // playwright.atk.config.js -- pantheon is set
        "export default {
                  testDir: \"tests\",
                  pantheon: {
                    isTarget: true,
                    site: \"aSite\",
                    environment: \"dev\"
                  },
                  tugboat: {
                    isTarget: false,
                    service: \"<id>\"
                  }
                }",
        // playwright.config.js **after**
        "import { defineConfig, devices } from '@playwright.test';
                
                export default defineConfig({
                  testDir: './tests',
                  /* other parameters that we are not interested in */
                  blabla: 'blablablaj',
                  /* Base URL */
                  use: {
baseURL: 'https://tugboatqa.com/test/',
                  }
                });
                ",
        // playwright.atk.config.js **after**
        "export default {
                  testDir: \"tests\",
                  pantheon: {
                    isTarget: false,
                    site: \"aSite\",
                    environment: \"dev\"
                  },
                  tugboat: {
                    isTarget: true,
                    service: \"11php12345\"
                  }
                }\n"
      ],
      [
        // playwright.config.js
        "import { defineConfig, devices } from '@playwright.test';
                
                export default defineConfig({
                  testDir: './tests',
                  /* other parameters that we are not interested in */
                  blabla: 'blablablaj',
                  /* Base URL */
                  use: {
                    baseURL: 'https://old.url.here',
                  }
                });
                ",
        // playwright.atk.config.js -- tugboatis set
        "export default {
                  testDir: \"tests\",
                  pantheon: {
                    isTarget: false,
                    site: \"aSite\",
                    environment: \"dev\"
                  },
                  tugboat: {
                    isTarget: true,
                    service: \"11oldidold\"
                  }
                }",
        // playwright.config.js **after**
        "import { defineConfig, devices } from '@playwright.test';
                
                export default defineConfig({
                  testDir: './tests',
                  /* other parameters that we are not interested in */
                  blabla: 'blablablaj',
                  /* Base URL */
                  use: {
                    baseURL: 'https://tugboatqa.com/test/',
                  }
                });
                ",
        // playwright.atk.config.js **after**
        "export default {
                  testDir: \"tests\",
                  pantheon: {
                    isTarget: false,
                    site: \"aSite\",
                    environment: \"dev\"
                  },
                  tugboat: {
                    isTarget: true,
                    service: \"11php12345\"
                  }
                }\n"
      ]
    ];
  }

}