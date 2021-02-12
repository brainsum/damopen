<?php

namespace Drupal\media_collection\Entity;

/**
 * Interface EntityCreatedInterface.
 *
 * @package Drupal\media_collection\Entity
 */
interface EntityCreatedInterface {

  /**
   * Gets the Media collection creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Media collection.
   */
  public function getCreatedTime();

  /**
   * Sets the Media collection creation timestamp.
   *
   * @param int $timestamp
   *   The Media collection creation timestamp.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionInterface
   *   The called Media collection entity.
   */
  public function setCreatedTime($timestamp);

}
