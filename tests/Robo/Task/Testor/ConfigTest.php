<?php

namespace PL\Tests\Robo\Task\Testor;

use PHPUnit\Framework\TestCase;
use PL\Robo\Testor;

class ConfigTest extends TestCase {

  function testValueFromEnv() {
    putenv('TEST_VAR1=value');
    $config = Testor::createConfiguration(['.testor_test.yml']);
    self::assertEquals('value', $config->get('test.var1'));
    self::assertEquals('value', $config->get('test.var2'));
    self::assertEquals(['var1' => 'value', 'var2' => 'value'], $config->get('test'));
  }
}