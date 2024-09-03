<?php

namespace PL\Robo\Task\Testor {

    use PL\Robo\Common\S3BucketAwareTrait;
    use PL\Robo\Common\S3ClientAwareTrait;
    use PL\Robo\Contract\S3BucketAwareInterface;
    use PL\Robo\Contract\S3ClientAwareInterface;

    class SnapshotList extends TestorTask
        implements S3ClientAwareInterface, S3BucketAwareInterface
    {
        use S3ClientAwareTrait;
        use S3BucketAwareTrait;

        protected string $name;

        function __construct(array $args)
        {
            parent::__construct();
            $this->name = $args['name'];
        }

        public function run(): \Robo\Result
        {
            $result = $this->s3Client->listObjects(array(
                'Bucket' => $this->s3Bucket,
                'Delimiter' => ':',
                'Prefix' => $this->name
            ));

            // Format result in
            // | Name    | Date      | Size   |
            // | ...     | ...       | ...    |
            if (empty($result['Contents'])) {
                $this->printTaskWarning("There are no snapshots by name \"$this->name\"");
                $table = [];
            } else {
                $table = array_map(
                    fn($item) => array('Name' => $item['Key'], 'Date' => $item['LastModified'], 'Size' => $item['Size']),
                    $result['Contents']
                );
                usort($table, fn($a, $b) => $b['Date']->getTimestamp() - $a['Date']->getTimestamp());
            }
            return new \Robo\Result($this, 0, '', array('table' => $table));
        }
    }
}