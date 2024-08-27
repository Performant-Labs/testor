<?php

namespace PL\Robo\Task\Testor {

    use Robo\Common\BuilderAwareTrait;

    class SnapshotGet extends TestorTask implements \Robo\Contract\TaskInterface, \Robo\Contract\BuilderAwareInterface
    {
        use BuilderAwareTrait;

        protected string $name;
        protected string $filename;

        function __construct(array $args)
        {
            parent::__construct();
            $this->name = $args['name'];
            $this->filename = $args['output'];
        }

        public function run()
        {
            /** @var SnapshotList $taskSnapshotList */
            $taskSnapshotList = $this->collectionBuilder()->taskSnapshotList(array('name' => $this->name));
            $result = $taskSnapshotList->run();
            if (empty($result['table'])) {
                return new \Robo\Result($this, 1);
            }
            // SnapshotList task returns `table` which
            // contains a datetime-sorted array of objects.
            // Key is the name of the first object.
            $key = $result['table'][0]['Name'];
            $bucket = $this->testorConfig->get('s3.bucket');
            $client = $this->getS3Client();
            $this->filename = $this->filename ?? $key;
            $client->getObject(array(
                'Bucket' => $bucket,
                'Key' => $key,
                'SaveAs' => $this->filename
            ));
            $this->message = "Downloaded $key => $this->filename";
            $this->printTaskSuccess($this->message);
            return new \Robo\Result($this, 0, $this->message);
        }
    }
}