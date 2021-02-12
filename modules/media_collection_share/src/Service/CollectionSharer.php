<?php

namespace Drupal\media_collection_share\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media_collection\Entity\MediaCollectionInterface;
use Drupal\media_collection\Entity\MediaCollectionItemInterface;
use Drupal\media_collection\Service\CollectionHandler;
use Drupal\media_collection\Service\FileHandler\CollectionFileHandler;
use Drupal\media_collection_share\Entity\SharedMediaCollectionInterface;
use RuntimeException;
use function array_udiff;
use function count;

/**
 * Class CollectionSharer.
 *
 * @package Drupal\media_collection_share\Service
 */
final class CollectionSharer {

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  private $dateFormatter;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $time;

  /**
   * Storage for "Shared media collection" entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $sharedCollectionStorage;

  /**
   * Storage for "Media collection item" entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $collectionItemStorage;

  private $collectionHandler;

  private $collectionFileHandler;

  /**
   * CollectionShare constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\media_collection\Service\CollectionHandler $collectionHandler
   *   Collection handler service.
   * @param \Drupal\media_collection\Service\FileHandler\CollectionFileHandler $collectionFileHandler
   *   Download handler.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
    CollectionHandler $collectionHandler,
    CollectionFileHandler $collectionFileHandler
  ) {
    $this->sharedCollectionStorage = $entityTypeManager->getStorage('shared_media_collection');
    $this->collectionItemStorage = $entityTypeManager->getStorage('media_collection_item');

    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
    $this->collectionHandler = $collectionHandler;
    $this->collectionFileHandler = $collectionFileHandler;
  }

  /**
   * Create a shared collection for a given user by ID.
   *
   * @param int $userId
   *   The user ID.
   *
   * @return \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface
   *   The Shared collection entity.
   *
   * @throws \RuntimeException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createSharedCollectionForUser(int $userId): SharedMediaCollectionInterface {
    $collection = $this->collectionHandler->loadCollectionForUser($userId);

    if ($collection === NULL) {
      throw new RuntimeException('Could not load a collection for the given user.');
    }

    return $this->createSharedCollection($collection);
  }

  /**
   * Create a shared collection from the given one.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   Collection to share.
   *
   * @return \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface
   *   The Shared collection entity.
   *
   * @throws \RuntimeException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createSharedCollection(MediaCollectionInterface $collection): SharedMediaCollectionInterface {
    if ($sharedCollection = $this->loadMatchingSharedCollection($collection)) {
      return $sharedCollection;
    }

    /** @var \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $sharedCollection */
    $sharedCollection = $this->sharedCollectionStorage->create();
    $sharedCollection->setOwner($collection->getOwner());
    $sharedCollection->setShareUrl($this->generateShareUrl($sharedCollection));
    // Save to prevent infinite save loops when cloning.
    $sharedCollection->save();
    $sharedCollection->setItems($this->cloneItems($collection, $sharedCollection));
    $sharedCollection->setArchive($this->generateAssetsArchive($sharedCollection));
    $sharedCollection->save();
    return $sharedCollection;
  }

  /**
   * Loads a matching shared collection for the given collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The collection.
   *
   * @return \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface|null
   *   The shared collection or NULL none can be found.
   */
  private function loadMatchingSharedCollection(MediaCollectionInterface $collection): ?SharedMediaCollectionInterface {
    /** @var \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface[] $sharedCollections */
    $sharedCollections = $this->sharedCollectionStorage->loadByProperties([
      $this->sharedCollectionStorage->getEntityType()->getKey('owner') => $collection->getOwnerId(),
    ]);

    // Latest shares are in the front, no additional sorting needed.
    foreach ($sharedCollections as $sharedCollection) {
      if ($this->compareCollections($collection, $sharedCollection)) {
        return $sharedCollection;
      }
    }

    return NULL;
  }

  /**
   * Returns whether the two collections match or not.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $reference
   *   The reference collection.
   * @param \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $test
   *   The test collection.
   *
   * @return bool
   *   TRUE when the two collections match, FALSE otherwise.
   */
  private function compareCollections(MediaCollectionInterface $reference, SharedMediaCollectionInterface $test): bool {
    if ($reference->getOwnerId() !== $test->getOwnerId()) {
      return FALSE;
    }

    return $this->compareCollectionsByItems($reference, $test);
  }

  /**
   * Compares collection item arrays.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $reference
   *   The reference collection.
   * @param \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $test
   *   The test collection.
   *
   * @return bool
   *   TRUE if they match, FALSE otherwise.
   */
  private function compareCollectionsByItems(MediaCollectionInterface $reference, SharedMediaCollectionInterface $test): bool {
    if ($reference->itemCount() !== $test->itemCount()) {
      return FALSE;
    }

    // Count of every reference item that's not among the test items.
    if (count(array_udiff($reference->items(), $test->items(), [CollectionHandler::class, 'compareItemsByValue'])) !== 0) {
      return FALSE;
    }

    // Count of every test item that's not among the reference items.
    return count(array_udiff($test->items(), $reference->items(), [CollectionHandler::class, 'compareItemsByValue'])) === 0;
  }

  /**
   * Generate an archive for the collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The collection.
   *
   * @return \Drupal\file\FileInterface
   *   The archive file.
   *
   * @throws \RuntimeException
   */
  private function generateAssetsArchive(MediaCollectionInterface $collection): FileInterface {
    $archive = $this->collectionFileHandler->generateArchiveEntity($collection);

    // @todo: Generate an empty archive instead..
    if ($archive === NULL) {
      throw new RuntimeException('Creating a zipped archive for the given collection failed.');
    }

    return $archive;
  }

  /**
   * Clone the items of a collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $source
   *   The collection from which to clone items.
   * @param \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $target
   *   The collection to which to clone items.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionItemInterface[]
   *   An array of new media collection items.
   */
  private function cloneItems(MediaCollectionInterface $source, SharedMediaCollectionInterface $target): array {
    $newItems = [];

    foreach ($source->items() as $item) {
      $newItems[] = $this->cloneItem($item, $target);
    }

    return $newItems;
  }

  /**
   * Clone an item.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The item.
   * @param \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $newParent
   *   The new parent collection.
   *
   * @return \Drupal\media_collection\Entity\MediaCollectionItemInterface
   *   The cloned item.
   */
  private function cloneItem(MediaCollectionItemInterface $item, SharedMediaCollectionInterface $newParent): MediaCollectionItemInterface {
    /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface $newItem */
    $newItem = $this->collectionItemStorage->create();
    $newItem->setOwner($item->getOwner());
    $newItem->setMedia($item->media());

    if ($style = $item->style()) {
      $newItem->setStyle($style);
    }

    $newItem->set('parent', NULL);
    $newItem->set('shared_parent', $newParent);

    return $newItem;
  }

  /**
   * Generate a relative Share URL.
   *
   * @param \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $collection
   *   To collection to share.
   *
   * @return string
   *   The URL.
   *
   * @todo: Consider moving this to a default value callback.
   */
  private function generateShareUrl(SharedMediaCollectionInterface $collection): string {
    $hash = $collection->uuid();
    $date = $this->dateFormatter->format($this->time->getRequestTime(), 'custom', 'Y-m-d');

    return "/collection/shared/{$date}/{$hash}";
  }

}
