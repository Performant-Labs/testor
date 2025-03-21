<?php

namespace PL\Robo\Common;

use PL\Robo\Contract\S3BucketAwareInterface;
use PL\Robo\Contract\S3ClientAwareInterface;
use PL\Robo\Contract\StorageInterface;

class StorageS3 implements StorageInterface, S3ClientAwareInterface, S3BucketAwareInterface {
  use TestorDependencyInjectorTrait;
  use S3ClientAwareTrait;
  use S3BucketAwareTrait;

  public function __construct() {
    $this->injectTestorDependencies();
  }

  function put(string $source, string $destination): void {
    $this->s3Client->putObject([
      'Bucket' => $this->s3Bucket,
      'Key' => $destination,
      'SourceFile' => $source
    ]);
  }

  function list(string $prefix): array {
    $result = $this->s3Client->listObjects([
      'Bucket' => $this->s3Bucket,
      'Delimiter' => ':',
      'Prefix' => $prefix
    ]);

    // Format result in
    // | Name    | Date      | Size   |
    // | ...     | ...       | ...    |
    if (!(bool) $result['Contents']) {
      return [];
    }
    $table = array_map(
      fn($item) => ['Name' => $item['Key'], 'Date' => $item['LastModified'], 'Size' => $item['Size']],
      $result['Contents']
    );
    usort($table, fn($a, $b) => $b['Date']->getTimestamp() - $a['Date']->getTimestamp());
    return $table;
  }

  function get(string $source, string $destination): void {
    $this->s3Client->getObject([
      'Bucket' => $this->s3Bucket,
      'Key' => $source,
      'SaveAs' => $destination
    ]);
  }

  function delete(array $names): void {
    $this->s3Client->deleteObjects([
      'Bucket' => $this->s3Bucket,
      'Delete' => [
        'Objects' => array_map(fn($name) => ['Key' => $name], $names),
      ],
    ]);
  }

}