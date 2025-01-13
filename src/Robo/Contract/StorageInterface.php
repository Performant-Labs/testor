<?php

namespace PL\Robo\Contract;

/**
 * An abstract storage.
 */
interface StorageInterface {
  /**
   * Put an object into storage.
   *
   * @param string $source File containing the object in a local file system.
   * @param string $destination Object key in the storage.
   * @return void
   */
  function put(string $source, string $destination): void;

  /**
   * List objects in the storage.
   *
   * @param string $prefix Prefix, or directory, of the objects to list.
   * @return array Array of the objects, sorted from the newest to oldest,
   * in format
   *    | Name    | Date      | Size   |
   *    | ...     | ...       | ...    |
   */
  function list(string $prefix): array;

  /**
   * Get an object from the storage.
   *
   * @param string $source Name (probably partial) of the object.
   * If multiple objects match this name, the newest will be gotten.
   * @param string $destination Destination file in the local file system.
   * @return void
   */
  function get(string $source, string $destination): void;

}