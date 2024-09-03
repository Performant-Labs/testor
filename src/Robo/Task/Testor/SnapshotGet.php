<?php

namespace PL\Robo\Task\Testor {

    use PL\Robo\Common\S3BucketAwareTrait;
    use PL\Robo\Common\S3ClientAwareTrait;
    use PL\Robo\Contract\S3BucketAwareInterface;
    use PL\Robo\Contract\S3ClientAwareInterface;
    use Robo\Common\BuilderAwareTrait;

    class SnapshotGet extends TestorTask
        implements S3ClientAwareInterface, S3BucketAwareInterface
    {
        use BuilderAwareTrait;
        use S3ClientAwareTrait;
        use S3BucketAwareTrait;

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
            $this->filename = $this->filename ?? $key;
            $this->s3Client->getObject(array(
                'Bucket' => $this->s3Bucket,
                'Key' => $key,
                'SaveAs' => $this->filename
            ));
            $this->message = "Downloaded $key => $this->filename";
            $this->printTaskSuccess($this->message);
            return new \Robo\Result($this, 0, $this->message);
        }
    }
}