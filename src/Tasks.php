<?php

namespace PL\Robo\Task\Testor {
    trait Tasks
    {
        /**
         * Task to export snapshot from a given Pantheon env,
         * upload it to MinIO, and optionally use for new previews.
         *
         * @param array $opts
         * --env Pantheon env
         * --use-on-preview Use this snapshot for newly created previews.
         * @return SnapshotCreate
         */
        protected function taskSnapshotCreate($opts = ['env' => 'dev', 'useOnPreview' => false])
        {
            return $this->task(SnapshotCreate::class, $opts);
        }
    }
}
