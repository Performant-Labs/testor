<?php

namespace PL\Robo\Common;

use Aws\S3\S3Client;

trait S3ClientAwareTrait {
  protected S3Client $s3Client;

  public function getS3Client(): S3Client {
    return $this->s3Client;
  }

  public function setS3Client(S3Client $s3Client): static {
    $this->s3Client = $s3Client;
    return $this;
  }

}