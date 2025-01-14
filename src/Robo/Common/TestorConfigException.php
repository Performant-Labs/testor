<?php

namespace PL\Robo\Common;

use Throwable;

class TestorConfigException extends \Exception {
  public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null) {
    parent::__construct($message, $code, $previous);
  }

}