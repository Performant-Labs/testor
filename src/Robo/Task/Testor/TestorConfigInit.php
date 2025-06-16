<?php

namespace PL\Robo\Task\Testor;

use PL\Robo\Common\TestorConfig;

class TestorConfigInit extends \Robo\Task\BaseTask {
  public function run(): \Robo\Result {
    if (file_exists(".testor.yml")) {
      $this->printTaskInfo(".testor.yml already exists, skip");
    }
    else {
      file_put_contents(".testor.yml", TestorConfig::PLACEHOLDER);
      $this->printTaskInfo(".testor.yml created! Edit configuration and add it to the version control.");
    }

    if (file_exists(".testor_secret.yml")) {
      $this->printTaskInfo(".testor_secret.yml already exists, skip");
    }
    else {
      file_put_contents(".testor_secret.yml", TestorConfig::SECRET_PLACEHOLDER);
      $this->printTaskInfo(".testor_secret.yml created! Edit configuration and keep it on your machine.");
      if (file_exists(".gitignore")) {
        file_put_contents(".gitignore", TestorConfig::GITIGNORE,
          FILE_APPEND);
      }
    }

    return new \Robo\Result($this, 0);
  }

}