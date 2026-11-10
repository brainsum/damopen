<?php

namespace Drupal\media_collection\Service\EntityProcessor;

use Drupal\media_collection\Entity\MediaCollectionItemInterface;

/**
 * Class CollectionItemProcessor.
 *
 * @package Drupal\media_collection\Service\EntityProcessor
 */
class CollectionItemProcessor {

  /**
   * Media entity processor.
   *
   * @var \Drupal\media_collection\Service\EntityProcessor\MediaEntityProcessor
   */
  private $mediaEntityProcessor;

  /**
   * CollectionItemProcessor constructor.
   *
   * @param \Drupal\media_collection\Service\EntityProcessor\MediaEntityProcessor $mediaEntityProcessor
   *   Processor for media entities.
   */
  public function __construct(
    MediaEntityProcessor $mediaEntityProcessor
  ) {
    $this->mediaEntityProcessor = $mediaEntityProcessor;
  }

  /**
   * Process a collection item.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The collection item.
   *
   * @return \Drupal\damopen_assets_download\Model\FileArchivingData[]
   *   Array of processed entity data.
   */
  public function process(MediaCollectionItemInterface $item): array {
    $media = $item->media();
    if (\Drupal::hasService('damopen_consent.policy') && !\Drupal::service('damopen_consent.policy')->deliverable($media) && !$media->access('view')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('An asset in this collection is not available.');
    }

    if ($media->bundle() === 'image') {
      /** @var \Drupal\image\ImageStyleInterface $style */
      $style = $item->style();

      // @todo: Check for the existence of style. If not there, that's an inconsistent state.
      return $this->mediaEntityProcessor->processImageMediaEntity($media, $style);
    }

    return $this->mediaEntityProcessor->processMediaEntity($media);
  }

}
