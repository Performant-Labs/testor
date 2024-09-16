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
        protected string $element;

        function __construct(array $args)
        {
            parent::__construct();
            $this->name = $args['name'];
            $this->element = $args['element'];
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

            // Filter out elements
            $table = array_values(array_filter($table, fn($value) => str_contains($value['Name'], $this->element)));

            return new \Robo\Result($this, 0, '', array('table' => $table));
        }
    }
}