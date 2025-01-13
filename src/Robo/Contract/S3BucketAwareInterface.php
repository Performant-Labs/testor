<?php

namespace PL\Robo\Contract;

interface S3BucketAwareInterface {
  /**
   * Set S3 bucket name.
   *
   * @param string $bucket
   * @return S3BucketAwareInterface
   */
  function setS3Bucket(string $bucket): static;

  /**
   * Get S3 bucket name.
   *
   * @return string
   */
  function getS3Bucket(): string;

}