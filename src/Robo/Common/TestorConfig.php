<?php

namespace PL\Robo\Common;

use Robo\Config\Config;
use Symfony\Component\Yaml\Yaml;

class TestorConfig extends Config {
  const PLACEHOLDER = "# Add this config to the version control.
pantheon:
  site: '[your Pantheon site name]'
sql:
  command: '$(drush sql:connect)'
sqldump:
  command: 'drush sql:dump'
sanitize:
  command: 'drush sql:sanitize'
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
  # if key is set, it's password to the key, otherwise password to the server
  password: ''
  root: 'sftp/upload'
";
  const SECRET_PLACEHOLDER = "# Don't add this config to the version control.
s3_secret: '[secret key]'
";
  const GITIGNORE = "
# Testor.
.testor_secret.yml
";
  protected array $placeholderDefaults;

  public function __construct(array $data = []) {
    parent::__construct($data);
    $this->placeholderDefaults = (array) Yaml::parse(self::PLACEHOLDER);
  }

  /**
   * Fetch a configuration value and raise an exception if it's missing.
   *
   * @throws TestorConfigException If value is missing.
   */
  public function getOrDie(string $key): mixed {
    $value = $this->get($key);
    if ($value === null) {
      $message = "Configuration is missing: $key";

      $placeholder = $this->getPlaceholder($key);
      if ($placeholder !== null) {
        $message .= "\nYou may want to add following to your .testor.yml:\n\n$placeholder\n\n";
      }

      throw new TestorConfigException($message);
    }
    return $value;
  }

  protected function getPlaceholder(string $key): string|null {
    // Copy the requested $key only.
    $copy = [];
    $srcRef = &$this->placeholderDefaults;
    $dstRef = &$copy;
    foreach (explode('.', $key) as $part) {
      if (!is_array($srcRef) || !array_key_exists($part, $srcRef)) {
        return null;
      }
      $dstRef[$part] = $srcRef[$part];
      $srcRef = &$srcRef[$part];
      $dstRef = &$dstRef[$part];
    }

    return Yaml::dump($copy, 2, 2);
  }

}