<?php

namespace PL\Robo\Task\Testor {

    class SnapshotList extends TestorTask implements \Robo\Contract\TaskInterface
    {
        protected string $name;

        function __construct(array $args)
        {
            parent::__construct();
            $this->name = $args['name'];
        }

        public function run(): \Robo\Result
        {
            $client = $this->getS3Client();
            $bucket = $this->testorConfig->get('s3.bucket');
            $result = $client->listObjects(array(
                'Bucket' => $bucket,
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