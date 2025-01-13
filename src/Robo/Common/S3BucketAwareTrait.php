<?php

namespace PL\Robo\Common;

trait S3BucketAwareTrait {
  protected string $s3Bucket;

  public function getS3Bucket(): string {
    return $this->s3Bucket;
  }

  public function setS3Bucket(string $s3Bucket): static {
    $this->s3Bucket = $s3Bucket;
    return $this;
  }

}