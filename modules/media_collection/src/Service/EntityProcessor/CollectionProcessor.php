<?php

namespace Drupal\media_collection\Service\EntityProcessor;

use Drupal\media_collection\Entity\MediaCollectionInterface;

/**
 * Class CollectionProcessor.
 *
 * @package Drupal\media_collection\Service\EntityProcessor
 */
class CollectionProcessor {

  /**
   * Collection item processor.
   *
   * @var \Drupal\media_collection\Service\EntityProcessor\CollectionItemProcessor
   */
  private $itemProcessor;

  /**
   * CollectionProcessor constructor.
   *
   * @param \Drupal\media_collection\Service\EntityProcessor\CollectionItemProcessor $itemProcessor
   *   Item processor.
   */
  public function __construct(CollectionItemProcessor $itemProcessor) {
    $this->itemProcessor = $itemProcessor;
  }

  /**
   * Process a collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The collection.
   *
   * @return \Drupal\damopen_assets_download\Model\FileArchivingData[]
   *   Array of processed entity data.
   */
  public function process(MediaCollectionInterface $collection): array {
    $fileData = [];

    foreach ($collection->items() as $item) {
      $fileData[] = $this->itemProcessor->process($item);
    }

    return array_merge(...$fileData);
  }

}
