<?php

namespace PL\Robo\Task\Testor {
    trait Tasks
    {
        protected function taskTestorConfigInit()
        {
            return $this->task(TestorConfigInit::class);
        }

        protected function taskTestorCustomCommand()
        {
            return $this->task(TestorCustomCommand::class);
        }

        protected function taskSnapshotCreate(array $opts): \Robo\Collection\CollectionBuilder
        {
            return $this->task(SnapshotCreate::class, $opts);
        }

        protected function taskSnapshotList(array $opts): \Robo\Collection\CollectionBuilder
        {
            return $this->task(SnapshotList::class, $opts);
        }

        protected function taskSnapshotGet(array $opts): \Robo\Collection\CollectionBuilder
        {
            return $this->task(SnapshotGet::class, $opts);
        }
    }
}
