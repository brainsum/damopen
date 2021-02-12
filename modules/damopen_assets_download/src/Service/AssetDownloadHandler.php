<?php

namespace Drupal\damopen_assets_download\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\damopen_common\Service\DamoFileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use RuntimeException;
use SplFileInfo;
use function count;
use function reset;

/**
 * Class AssetDownloadHandler.
 *
 * @package Drupal\damopen_assets_download\Service
 */
class AssetDownloadHandler {

  private $fileHandler;

  private $archiver;

  private $fileManager;

  /**
   * The file system.
   *
   * @var \Drupal\damopen_common\Service\DamoFileSystemInterface
   */
  private $fileSystem;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $time;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  private $dateFormatter;

  /**
   * AssetDownloadHandler constructor.
   *
   * @param \Drupal\damopen_assets_download\Service\AssetFileHandler $fileHandler
   *   File handler service.
   * @param \Drupal\damopen_assets_download\Service\AssetArchiver $archiver
   *   File archiver.
   * @param \Drupal\damopen_assets_download\Service\FileManager $fileManager
   *   File manager.
   * @param \Drupal\damopen_common\Service\DamoFileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter.
   */
  public function __construct(
    AssetFileHandler $fileHandler,
    AssetArchiver $archiver,
    FileManager $fileManager,
    DamoFileSystemInterface $fileSystem,
    TimeInterface $time,
    DateFormatterInterface $dateFormatter
  ) {
    $this->fileHandler = $fileHandler;
    $this->archiver = $archiver;
    $this->fileManager = $fileManager;
    $this->fileSystem = $fileSystem;
    $this->time = $time;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Generates a downloadable file for the media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file or NULL.
   */
  public function generateDownloadableFile(MediaInterface $media): ?FileInterface {
    $fileData = $this->fileHandler->mediaFilesData($media);
    $fileCount = count($fileData);

    if ($fileCount <= 0) {
      return NULL;
    }

    if ($fileCount === 1) {
      return reset($fileData)->file;
    }

    $archiveLocation = $this->archiver->createFileArchive($fileData);

    if ($archiveLocation === NULL) {
      return NULL;
    }

    // @todo: Copy to the desired place: $this->archiveTargetPath($media);

    return $this->fileManager->createArchiveEntity($media->getOwner(), new SplFileInfo($archiveLocation));
  }

  /**
   * Generates a downloadable file for the media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   * @param \Drupal\image\ImageStyleInterface $style
   *   The style.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file or NULL.
   */
  public function generateDownloadableStyledFile(MediaInterface $media, ImageStyleInterface $style): ?FileInterface {
    if ($media->bundle() !== 'image') {
      throw new RuntimeException('Only image assets can be styled.');
    }

    $downloadableFile = $this->generateDownloadableFile($media);

    if ($downloadableFile === NULL) {
      return NULL;
    }

    $styledUri = $style->buildUri($downloadableFile->getFileUri());
    return $this->fileManager->createArchiveEntity($media->getOwner(), new SplFileInfo($styledUri));
  }

  /**
   * Returns the desired path to the media archive.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   *
   * @return string|null
   *   Tha path, or NULL on failure.
   */
  private function archiveTargetPath(MediaInterface $media): ?string {
    $basePath = $this->fileSystem->realpath('private://');
    $fileDir = "{$basePath}/tmp/media/{$media->bundle()}/{$media->uuid()}";

    if (!$this->fileSystem->safeMkdir($fileDir)) {
      return NULL;
    }

    return "{$fileDir}/{$this->archiveTargetName($media)}";
  }

  /**
   * Generate archive name for a media.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return string
   *   The archive name.
   */
  private function archiveTargetName(MediaInterface $media): string {
    return "{$media->getName()}_{$this->currentDate()}.zip";
  }

  /**
   * Returns the current date in the desired format.
   *
   * @return string
   *   The properly formatted current date.
   *
   * @todo: Move to service?
   */
  private function currentDate(): string {
    return $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'Y-m-d');
  }

}
