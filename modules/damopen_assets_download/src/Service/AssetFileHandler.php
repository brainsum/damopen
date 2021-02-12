<?php

namespace Drupal\damopen_assets_download\Service;

use Drupal\media\MediaInterface;
use Drupal\damopen_assets_download\Model\FileArchivingData;

/**
 * Class AssetFileHandler.
 *
 * @package Drupal\damopen_assets_download\Service
 */
class AssetFileHandler {

  /**
   * Fields containing downloadable media files.
   *
   * @var string[]
   *
   * @todo: Maybe get these dynamically.
   */
  private static $mediaFields = [
    'field_image',
    'field_images',
    'field_file',
    'field_files',
    'field_template_file',
    'field_video_file',
  ];

  /**
   * Returns files belonging to the media.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   *
   * @return \Drupal\file\FileInterface[]
   *   Array of files.
   */
  public function mediaFiles(MediaInterface $media): array {
    $files = [];

    foreach (static::$mediaFields as $fieldName) {
      if (!$media->hasField($fieldName)) {
        continue;
      }

      foreach ($media->get($fieldName) as $item) {
        /** @var \Drupal\file\FileInterface|null $file */
        $file = $item->entity;

        if ($file === NULL) {
          continue;
        }

        $files[] = $file;
      }
    }

    return $files;
  }

  /**
   * Returns media files as FileArchivingData instances.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Drupal\damopen_assets_download\Model\FileArchivingData[]
   *   Files data array.
   */
  public function mediaFilesData(MediaInterface $media): array {
    $filesData = [];

    foreach ($this->mediaFiles($media) as $file) {
      $filesData[] = new FileArchivingData([
        'file' => $file,
        'systemPath' => $file->getFileUri(),
        'archiveTargetPath' => "/{$media->bundle()}/{$file->getFilename()}",
      ]);
    }

    return $filesData;
  }

}
