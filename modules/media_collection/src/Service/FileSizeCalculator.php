<?php

namespace Drupal\media_collection\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\media_collection\Entity\MediaCollectionInterface;
use SplFileInfo;
use function file_exists;
use function format_size;

/**
 * Class FileSizeCalculator.
 *
 * @package Drupal\media_collection\Service
 */
final class FileSizeCalculator {

  /**
   * Cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private $cache;

  /**
   * FileSizeCalculator constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache backend.
   */
  public function __construct(
    CacheBackendInterface $cache
  ) {
    $this->cache = $cache;
  }

  /**
   * Return the formatted/human readable collection size.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The collection.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The formatted size.
   */
  public function formattedCollectionSize(MediaCollectionInterface $collection): TranslatableMarkup {
    $size = $this->collectionSize($collection);

    return $size
      ? format_size($size, $collection->getOwner()->getPreferredLangcode())
      : new TranslatableMarkup('Not yet available');
  }

  /**
   * Determine the assets size of a collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The collection.
   *
   * @return int|null
   *   The file size in bytes or NULL, if it can't be determined or is 0.
   */
  public function collectionSize(MediaCollectionInterface $collection): ?int {
    $cacheId = "collection.file_size.{$collection->uuid()}";
    $cached = $this->cache->get($cacheId);

    /** @var int|null $fileSize */
    $fileSize = NULL;

    if ($cached === FALSE) {
      /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $collectionAssets */
      $collectionAssets = $collection->get('assets_archive');
      /** @var \Drupal\file\FileInterface|null $assetsFile */
      $assetsFile = $collectionAssets->entity;

      $fileSize = $assetsFile === NULL
        ? $this->collectionItemsSize($collection)
        : $this->fileSize($assetsFile);

      $this->cache->set(
        $cacheId,
        $fileSize,
        Cache::PERMANENT,
        $collection->getCacheTags()
      );
    }
    else {
      $fileSize = $cached->data;
    }

    return $fileSize;
  }

  /**
   * Calculate the size of an archive file.
   *
   * @param \Drupal\file\FileInterface $file
   *   Archive file.
   *
   * @return int
   *   The size in bytes.
   */
  public function fileSize(FileInterface $file): int {
    return $this->fileSizeByPath($file->getFileUri());
  }

  /**
   * Calculate collection size from collection items.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The media collection.
   *
   * @return int|null
   *   The size in bytes or NULL.
   */
  private function collectionItemsSize(MediaCollectionInterface $collection): ?int {
    $size = 0;

    /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface $item */
    foreach ($collection->items() as $item) {
      // @todo: Add media_predelete and remove items?
      $media = $item->media();

      if ($media->bundle() === 'image') {
        /** @var \Drupal\image\ImageStyleInterface $style */
        $style = $item->get('style')->entity ?? NULL;
        // @todo: Check for the existence of style. If not there, that's an inconsistent state.
        $size += $this->calculateImageMediaSize($media, $style);
        continue;
      }

      $size += $this->calculateMediaSize($media);
    }

    return $size > 0 ? $size : NULL;
  }

  /**
   * Calculate file size of a styled image media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param \Drupal\image\ImageStyleInterface $imageStyle
   *   The image style.
   *
   * @return int
   *   The file size.
   *
   * @todo: Move to damopen_assets_download?
   */
  private function calculateImageMediaSize(MediaInterface $media, ImageStyleInterface $imageStyle): int {
    $size = 0;

    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    foreach ($media->get('field_image') as $item) {
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $item->entity;

      if ($file === NULL) {
        continue;
      }

      $filePath = $file->getFileUri();
      $stylePath = $imageStyle->buildUri($filePath);

      if (!file_exists($stylePath)) {
        // @todo: Log.
        continue;
      }

      if ($imageStyle->createDerivative($filePath, $stylePath) === FALSE) {
        // @todo: Log.
        continue;
      }

      $size += $this->fileSizeByPath($stylePath);
    }

    return $size;
  }

  /**
   * Calculate file size of a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return int
   *   The file size.
   *
   * @todo: Move to damopen_assets_download?
   */
  private function calculateMediaSize(MediaInterface $media): int {
    // @todo: Maybe get these dynamically.
    static $fieldNames = [
      'field_files',
      'field_images',
      'field_file',
      'field_template_file',
      'field_video_file',
    ];

    /* @todo:
     * // TBD: type video: field_video, field_source, field_id?
     */

    $size = 0;

    foreach ($fieldNames as $fieldName) {
      if (!$media->hasField($fieldName)) {
        continue;
      }

      /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $fileField */
      $fileField = $media->get($fieldName);

      /** @var \Drupal\file\Plugin\Field\FieldType\FileItem|\Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      foreach ($fileField as $item) {
        /** @var \Drupal\file\FileInterface|null $file */
        $file = $item->entity;

        if ($file === NULL) {
          continue;
        }

        $size += $this->fileSizeByPath($file->getFileUri());
      }
    }

    return $size;
  }

  /**
   * Calculate file size from file path.
   *
   * @param string $path
   *   Absolute path to the file.
   *
   * @return int
   *   The file size, or 0 on error.
   *
   * @todo: Move to damopen_assets_download?
   */
  private function fileSizeByPath(string $path): int {
    $file = new SplFileInfo($path);

    if (!($file->isFile() && $file->isReadable())) {
      return 0;
    }

    // Technically, this should be int only, but might return FALSE, too...
    $fileSize = $file->getSize();

    return (is_int($fileSize) && $fileSize > 0) ? $fileSize : 0;
  }

}
