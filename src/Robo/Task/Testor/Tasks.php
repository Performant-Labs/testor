<?php

namespace PL\Robo\Task\Testor {
    trait Tasks
    {
        protected function taskSnapshotCreate($opts = ['env' => 'dev', 'useOnPreview' => false])
        {
            return $this->task(SnapshotCreate::class, $opts);
        }
    }
}
