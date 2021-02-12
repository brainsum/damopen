<?php

namespace Drupal\media_collection\Service\EntityProcessor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\damopen_assets_download\Model\FileArchivingData;

/**
 * Class MediaEntityProcessor.
 *
 * @package Drupal\media_collection\Service\EntityProcessor
 */
class MediaEntityProcessor {

  /**
   * The file storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  private $fileStorage;

  /**
   * Media type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $mediaTypeStorage;

  /**
   * MediaEntityProcessor constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->mediaTypeStorage = $entityTypeManager->getStorage('media_type');
    $this->fileStorage = $entityTypeManager->getStorage('file');
  }

  /**
   * Process an image media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param \Drupal\image\ImageStyleInterface $imageStyle
   *   Image style.
   *
   * @return \Drupal\damopen_assets_download\Model\FileArchivingData[]
   *   Array of processed entity data.
   */
  public function processImageMediaEntity(MediaInterface $media, ImageStyleInterface $imageStyle): array {
    $entityData = [];

    $styleLabel = $imageStyle->label();
    $mediaLabel = $media->bundle();
    /** @var \Drupal\media\MediaTypeInterface $mediaType */
    $mediaType = $this->mediaTypeStorage->load($mediaLabel);

    if ($mediaType !== NULL) {
      // The label is human-readable, the bundle is just a machine name.
      $mediaLabel = $mediaType->label();
    }

    $archiveDirectory = "/{$mediaLabel}/{$styleLabel}";

    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    foreach ($media->get('field_image') as $item) {
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $item->entity;

      if ($file === NULL) {
        continue;
      }

      $filePath = $file->getFileUri();
      $stylePath = $imageStyle->buildUri($filePath);

      if ($imageStyle->createDerivative($filePath, $stylePath) === FALSE) {
        // @todo: Log.
        continue;
      }

      $styleFile = $this->fileStorage->create([
        'uri' => $stylePath,
        'status' => 1,
        'uid' => $media->getOwner()->id(),
        'filename' => "{$styleLabel}_{$file->getFilename()}",
      ]);

      /* @todo:
       * targetPath should likely be this:
       * - (media bundle)/(media name)/(style name).(media extension)
       */
      $entityData[] = new FileArchivingData([
        'file' => $styleFile,
        'systemPath' => $stylePath,
        'archiveTargetPath' => "{$archiveDirectory}/{$file->getFilename()}",
      ]);
    }

    return $entityData;
  }

  /**
   * Process a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Drupal\damopen_assets_download\Model\FileArchivingData[]
   *   Array of processed entity data.
   */
  public function processMediaEntity(MediaInterface $media): array {
    $entityData = [];

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

        $entityData[] = new FileArchivingData([
          'file' => $file,
          'systemPath' => $file->getFileUri(),
          'archiveTargetPath' => "/{$media->bundle()}/{$file->getFilename()}",
        ]);
      }
    }

    return $entityData;
  }

}
