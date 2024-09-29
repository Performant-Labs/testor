<?php

namespace PL\Robo\Contract;

interface StorageAwareInterface
{
    function getStorage(): StorageInterface;

    function setStorage(StorageInterface $storage): static;
}