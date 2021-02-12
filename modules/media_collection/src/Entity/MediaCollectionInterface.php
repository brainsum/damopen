<?php

namespace Drupal\media_collection\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\FileInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Media collection entities.
 *
 * @ingroup media_collection
 */
interface MediaCollectionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityCreatedInterface {

  /**
   * Checks if an item is in the collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The item.
   *
   * @return bool
   *   TRUE, if the collection has the item.
   */
  public function hasItem(MediaCollectionItemInterface $item): bool;

  /**
   * Return the index/key of the item.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The item.
   *
   * @return int|null
   *   The key/index of the given item or NULL, if not found.
   */
  public function itemIndex(MediaCollectionItemInterface $item): ?int;

  /**
   * Return the items in the collection.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionItemInterface[]
   *   The items.
   */
  public function items(): array;

  /**
   * Returns the count of items.
   *
   * @return int
   *   The item count.
   */
  public function itemCount(): int;

  /**
   * Set items of the collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface[] $items
   *   Array of items.
   *
   * @return static
   *   The called entity.
   */
  public function setItems(array $items): MediaCollectionInterface;

  /**
   * Add an item to the collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The item to add.
   *
   * @return static
   *   The called entity.
   */
  public function addItem(MediaCollectionItemInterface $item): MediaCollectionInterface;

  /**
   * Remove an item from the collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The item to remove.
   *
   * @return static
   *   The called entity.
   */
  public function removeItem(MediaCollectionItemInterface $item): MediaCollectionInterface;

  /**
   * Sets the Archived assets field.
   *
   * @param \Drupal\file\FileInterface $file
   *   The archive file.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionInterface
   *   The entity.
   */
  public function setArchive(FileInterface $file): MediaCollectionInterface;

  /**
   * Returns the archived assets file.
   *
   * @return \Drupal\file\FileInterface
   *   The file.
   */
  public function archiveFile(): FileInterface;

}
