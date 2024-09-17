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

        protected function taskTugboatPreviewDeleteAll(): \Robo\Collection\CollectionBuilder
        {
            return $this->task(TugboatPreviewDeleteAll::class);
        }

        protected function taskTugboatPreviewCreate(): \Robo\Collection\CollectionBuilder
        {
            return $this->task(TugboatPreviewCreate::class);
        }

        protected function taskTugboatPreviewSet(string $preview): \Robo\Collection\CollectionBuilder
        {
            return $this->task(TugboatPreviewSet::class, $preview);
        }
    }
}
