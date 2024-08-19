<?php

namespace PL\Robo\Task\Testor {
    trait Tasks
    {
        /**
         * Task to export snapshot from a given Pantheon env,
         * upload it to MinIO, and optionally use for new previews.
         *
         * @param string $env
         * @return SnapshotCreate
         */
        protected function taskSnapshotCreate($opts = ['env' => 'dev'])
        {
            return $this->task(SnapshotCreate::class, $opts['env']);
        }
    }
}
