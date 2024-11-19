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

        protected function taskArchivePack(string $archive, string... $files): \Robo\Collection\CollectionBuilder
        {
            return $this->task(ArchivePack::class, $archive, ...$files);
        }

        protected function taskArchiveUnpack(string $archive, string $dir = '.'): \Robo\Collection\CollectionBuilder
        {
            return $this->task(ArchiveUnpack::class, $archive, $dir);
        }

        protected function taskDbSync(string $source, string $target): \Robo\Collection\CollectionBuilder
        {
            return $this->task(DbSync::class, $source, $target);
        }

        protected function taskDbSanitize(array $opts): \Robo\Collection\CollectionBuilder
        {
            return $this->task(DbSanitize::class, $opts);
        }

        protected function taskSnapshotCreate(array $opts): \Robo\Collection\CollectionBuilder
        {
            return $this->task(SnapshotCreate::class, $opts);
        }

        protected function taskSnapshotImport(array $opts): \Robo\Collection\CollectionBuilder
        {
            return $this->task(SnapshotImport::class, $opts);
        }

        protected function taskSnapshotPut(array $opts): \Robo\Collection\CollectionBuilder
        {
            return $this->task(SnapshotPut::class, $opts);
        }

        protected function taskSnapshotViaBackup(array $opts): \Robo\Collection\CollectionBuilder
        {
            return $this->task(SnapshotViaBackup::class, $opts);
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
