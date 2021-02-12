<?php

namespace Drupal\media_collection\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Media collection item entities.
 *
 * @ingroup media_collection
 */
interface MediaCollectionItemInterface extends ContentEntityInterface, EntityOwnerInterface, EntityCreatedInterface {

  /**
   * Returns the referenced media entity.
   *
   * @return \Drupal\media\MediaInterface
   *   The media entity.
   */
  public function media(): MediaInterface;

  /**
   * Sets a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return static
   *   The item instance.
   */
  public function setMedia(MediaInterface $media): MediaCollectionItemInterface;

  /**
   * Returns the referenced image style entity.
   *
   * @return \Drupal\image\ImageStyleInterface|null
   *   The image style entity, if set.
   */
  public function style(): ?ImageStyleInterface;

  /**
   * Sets an image style.
   *
   * @param \Drupal\image\ImageStyleInterface $style
   *   The image style.
   *
   * @return static
   *   The item instance.
   */
  public function setStyle(ImageStyleInterface $style): MediaCollectionItemInterface;

  /**
   * Returns the parent collection.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionInterface
   *   The parent collection.
   */
  public function parent(): MediaCollectionInterface;

  /**
   * Sets the parent collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $parent
   *   The parent collection.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionItemInterface
   *   The item instance.
   */
  public function setParent(MediaCollectionInterface $parent): MediaCollectionItemInterface;

}
