<?php

namespace Drupal\media_collection\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\media_collection\Entity\MediaCollectionInterface;
use Drupal\media_collection\Entity\MediaCollectionItemInterface;
use Drupal\user\UserInterface;
use RuntimeException;
use function reset;

/**
 * Class CollectionHandler.
 *
 * @package Drupal\media_collection\Service
 */
final class CollectionHandler {

  private $translation;

  private $collectionStorage;

  private $collectionViewBuilder;

  private $cacheInvalidator;

  /**
   * CollectionHandler constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   Translation service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheInvalidator
   *   Cache tags invalidator.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    TranslationInterface $translation,
    EntityTypeManagerInterface $entityTypeManager,
    CacheTagsInvalidatorInterface $cacheInvalidator
  ) {
    $this->translation = $translation;
    $this->collectionStorage = $entityTypeManager->getStorage('media_collection');
    $this->collectionViewBuilder = $entityTypeManager->getViewBuilder('media_collection');
    $this->cacheInvalidator = $cacheInvalidator;
  }

  /**
   * Create or load a collection, add the item to it.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The collection item.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addItem(MediaCollectionItemInterface $item): void {
    // @todo: Cleanup.
    if ($item->hasField('shared_parent') && $item->get('shared_parent')->entity !== NULL) {
      return;
    }

    $user = $item->getOwner();
    $collection = $this->loadCollectionForUser((int) $user->id());

    if ($collection === NULL) {
      // @todo: Protect again spammed add-to-collection.
      /** @var \Drupal\media_collection\Entity\MediaCollectionInterface $collection */
      $collection = $this->collectionStorage->create();
      $collection->setOwner($user);
    }

    if ($collection->hasItem($item)) {
      return;
    }

    $collection->addItem($item);
    $collection->save();

    $this->cacheInvalidator->invalidateTags($this->itemCacheTags($item));
  }

  /**
   * Remove the item from the collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The collection item.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeItem(MediaCollectionItemInterface $item): void {
    $user = $item->getOwner();
    $collection = $this->loadCollectionForUser((int) $user->id());

    if ($collection === NULL) {
      $this->cacheInvalidator->invalidateTags($this->itemCacheTags($item));
      return;
    }

    $collection->removeItem($item);
    $collection->save();

    $this->cacheInvalidator->invalidateTags($this->itemCacheTags($item));
  }

  /**
   * Return an item with the given media and style entities.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The collection to check.
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param \Drupal\image\ImageStyleInterface|null $style
   *   (Optional) the style entity.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionItemInterface|null
   *   The matching Collection Item, or NULL.
   */
  public function itemWithGivenEntities(
    MediaCollectionInterface $collection,
    MediaInterface $media,
    ?ImageStyleInterface $style = NULL
  ): ?MediaCollectionItemInterface {
    /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface $item */
    foreach ($collection->items() as $item) {
      // @todo: Maybe use static::compareItemsByValue (or similar).
      if ($item->media()->id() !== $media->id()) {
        continue;
      }

      if ($style === NULL) {
        return $item;
      }

      if (
        $item->style() === NULL
        || $item->style()->id() !== $style->id()
      ) {
        continue;
      }

      return $item;
    }

    return NULL;
  }

  /**
   * Return the collection.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   *
   * @return array
   *   Render array.
   */
  public function renderCollection(
    UserInterface $user
  ): array {
    $collection = $this->loadCollectionForUser((int) $user->id());

    if ($collection === NULL) {
      return [
        '#markup' => $this->translation->translate("You don't currently have a collection. Add items to start one!"),
      ];
    }

    return $this->collectionViewBuilder->view($collection);
  }

  /**
   * Loads available collection for the user.
   *
   * @param int $userId
   *   ID of the user.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionInterface|null
   *   The Collection or NULL if it can't be loaded.
   */
  public function loadCollectionForUser(int $userId): ?MediaCollectionInterface {
    /** @var \Drupal\media_collection\Entity\MediaCollectionInterface[] $collections */
    $collections = $this->collectionStorage->loadByProperties([
      $this->collectionStorage->getEntityType()->getKey('owner') => $userId,
    ]);
    /** @var \Drupal\media_collection\Entity\MediaCollectionInterface|false $collection */
    $collection = reset($collections);
    // @todo: What if there are more than 1 (=== inconsistent state)?
    return $collection === FALSE ? NULL : $collection;
  }

  /**
   * Clear the collection for a given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The given user.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionInterface
   *   The cleared collection.
   *
   * @throws \RuntimeException
   */
  public function clearCollectionForUser(UserInterface $user, $collectionId): MediaCollectionInterface {
    if ($collectionId) {
      $collection = $this->collectionStorage->load($collectionId);
    }
    else {
      $collection = $this->loadCollectionForUser($user->id());
    }

    if ($collection === NULL) {
      /** @var \Drupal\media_collection\Entity\MediaCollectionInterface $collection */
      $collection = $this->collectionStorage->create();
      $collection->setOwner($user);
    }

    $cacheTags = $this->collectionCacheTags($collection);

    foreach ($collection->items() as $item) {
      try {
        $item->delete();
      }
      catch (EntityStorageException $exception) {
        // @todo: log.
      }
    }

    $collection->setItems([]);

    try {
      $collection->save();
    }
    catch (EntityStorageException $exception) {
      throw new RuntimeException("Clearing the collection for the current user (ID: {$user->id()}) failed.");
    }

    $this->cacheInvalidator->invalidateTags($cacheTags);

    return $collection;
  }

  /**
   * Returns tags for a collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The collection.
   *
   * @return string[]
   *   The tags.
   */
  private function collectionCacheTags(MediaCollectionInterface $collection): array {
    $tags = [];

    foreach ($collection->items() as $item) {
      $tags = Cache::mergeTags($tags, $this->itemCacheTags($item));
    }

    return $tags;
  }

  /**
   * Returns tags for an item.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The item.
   *
   * @return string[]
   *   Tags array.
   */
  private function itemCacheTags(MediaCollectionItemInterface $item): array {
    return $item->media()->getCacheTags();
  }

  /**
   * Compares two items based on their values.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $first
   *   The first item.
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $second
   *   The second item.
   *
   * @return int
   *   -1, 0 or 1 if $first is respectively less than, equal to
   *   or greater than $second.
   *
   * @see value_compare_func
   */
  public static function compareItemsByValue(MediaCollectionItemInterface $first, MediaCollectionItemInterface $second): int {
    if ($first->media()->id() !== $second->media()->id()) {
      return $first->media()->id() < $second->media()->id() ? -1 : 1;
    }

    $firstStyle = $first->style() ? $first->style()->id() : NULL;
    $secondStyle = $second->style() ? $second->style()->id() : NULL;

    if ($firstStyle !== $secondStyle) {

      // If only one of them is NULL, put the non-NULL first.
      if ($firstStyle === NULL) {
        return -1;
      }

      // If only one of them is NULL, put the non-NULL first.
      if ($secondStyle === NULL) {
        return -1;
      }

      return $firstStyle < $secondStyle ? -1 : 1;
    }

    return 0;
  }

}
