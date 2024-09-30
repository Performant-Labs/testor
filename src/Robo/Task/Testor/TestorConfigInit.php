<?php

namespace PL\Robo\Task\Testor;

class TestorConfigInit extends \Robo\Task\BaseTask
{
    private string $CONFIG = "# Add this config to the version control.
pantheon:
  site: '[your Pantheon site name]'
storage: '[s3|sftp]'
s3:
  config:
    version: 'latest'
    region: ''
    endpoint: '[cluster URL]'
    credentials:
      key: '[access key]'
      secret: '\${s3_secret}'
  bucket: '[bucket name]'
sftp:
  host: '[host]'
  user: 'sftpuser'
  key: '/path/to/private/key'
  # is key is set, it's password to the key, otherwise password to the server
  password: ''
  root: 'sftp/upload'
";
    private string $SECRET_CONFIG = "# Don't add this config to the version control.
s3_secret: '[secret key]'
";
    private string $GITIGNORE = "
# Testor.
.testor_secret.yml
";

    public function run(): \Robo\Result
    {
        if (file_exists(".testor.yml")) {
            $this->printTaskInfo(".testor.yml already exists, skip");
        } else {
            file_put_contents(".testor.yml", $this->CONFIG);
            $this->printTaskInfo(".testor.yml created! Edit configuration and add it to the version control.");
        }

        if (file_exists(".testor_secret.yml")) {
            $this->printTaskInfo(".testor_secret.yml already exists, skip");
        } else {
            file_put_contents(".testor_secret.yml", $this->SECRET_CONFIG);
            $this->printTaskInfo(".testor_secret.yml created! Edit configuration and keep it on your machine.");
            if (file_exists(".gitignore")) {
                file_put_contents(".gitignore", $this->GITIGNORE,
                FILE_APPEND);
            }
        }

        return new \Robo\Result($this, 0);
    }
}