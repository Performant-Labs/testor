<?php

namespace PL\Robo\Contract;

use Aws\S3\S3Client;

interface S3ClientAwareInterface
{
    /**
     * Set S3Client to the task.
     *
     * @param S3Client $s3Client
     * @return S3ClientAwareInterface
     */
    function setS3Client(\Aws\S3\S3Client $s3Client): static;

    /**
     * Get current S3Client.
     *
     * @return \Aws\S3\S3Client
     */
    function getS3Client(): \Aws\S3\S3Client;
}
