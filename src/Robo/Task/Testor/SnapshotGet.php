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
        protected ?string $filename;
        protected string $element;

        function __construct(array $args)
        {
            parent::__construct();
            $this->name = $args['name'];
            $this->filename = $args['output'];
            $this->element = $args['element'];
        }

        public function run()
        {
            /** @var SnapshotList $taskSnapshotList */
            $taskSnapshotList = $this->collectionBuilder()->taskSnapshotList(array('name' => $this->name, 'element' => $this->element));
            $result = $taskSnapshotList->run();
            if (empty($result['table'])) {
                return $this->fail();
            }
            // SnapshotList task returns `table` which
            // contains a datetime-sorted array of objects.
            // Key is the name of the first object.
            $key = $result['table'][0]['Name'];
            $array = explode('/', $key);
            $this->filename = $this->filename ?? end($array);
            $this->s3Client->getObject(array(
                'Bucket' => $this->s3Bucket,
                'Key' => $key,
                'SaveAs' => $this->filename
            ));
            $this->message = "Downloaded $key => $this->filename";
            return $this->pass();
        }
    }
}