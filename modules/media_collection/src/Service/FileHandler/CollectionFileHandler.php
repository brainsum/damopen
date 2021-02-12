<?php

namespace Drupal\media_collection\Service\FileHandler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\damopen_common\Helper\FieldManager;
use Drupal\damopen_common\Service\DamoFileSystemInterface;
use Drupal\damopen_assets_download\Service\AssetArchiver;
use Drupal\damopen_assets_download\Service\FileManager;
use Drupal\file\FileInterface;
use Drupal\media_collection\Entity\MediaCollectionInterface;
use Drupal\media_collection\Service\EntityProcessor\CollectionProcessor;
use Drupal\user\UserInterface;
use SplFileInfo;
use function is_dir;

/**
 * Class CollectionFileHandler.
 *
 * @todo: Maybe rename to file handler?
 * @todo: Add real download handler (binary file response factory basically).
 * @package Drupal\media_collection\Service\DownloadHandler
 *
 */
final class CollectionFileHandler {

  /**
   * The file system.
   *
   * @var \Drupal\damopen_common\Service\DamoFileSystemInterface
   */
  private $fileSystem;

  /**
   * Collection processor.
   *
   * @var \Drupal\media_collection\Service\EntityProcessor\CollectionProcessor
   */
  private $collectionProcessor;

  /**
   * Archive manager.
   *
   * @var \Drupal\damopen_assets_download\Service\AssetArchiver
   */
  private $archiver;

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
   * File manager service.
   *
   * @var \Drupal\damopen_assets_download\Service\FileManager
   */
  private $fileManager;

  /**
   * CollectionFileHandler constructor.
   *
   * @param \Drupal\damopen_common\Service\DamoFileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\media_collection\Service\EntityProcessor\CollectionProcessor $collectionProcessor
   *   Processor for collections.
   * @param \Drupal\damopen_assets_download\Service\AssetArchiver $archiver
   *   Archive manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter.
   * @param \Drupal\damopen_assets_download\Service\FileManager $fileManager
   *   File manager service.
   */
  public function __construct(
    DamoFileSystemInterface $fileSystem,
    CollectionProcessor $collectionProcessor,
    AssetArchiver $archiver,
    TimeInterface $time,
    DateFormatterInterface $dateFormatter,
    FileManager $fileManager
  ) {
    $this->collectionProcessor = $collectionProcessor;
    $this->archiver = $archiver;

    $this->fileSystem = $fileSystem;
    $this->fileManager = $fileManager;
    // @todo: Maybe move to a custom service, these 2 need to be together.
    $this->time = $time;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Generate a downloadable file for a collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The collection.
   *
   * @return \Drupal\file\FileInterface|null
   *   The downloadable file or NULL.
   */
  public function generateDownloadableFile(MediaCollectionInterface $collection): ?FileInterface {
    $fileData = $this->collectionProcessor->process($collection);
    $fileCount = count($fileData);

    if ($fileCount <= 0) {
      return NULL;
    }

    if ($fileCount === 1) {
      return reset($fileData)->file;
    }

    $collectionArchivePath = $this->archiveTargetPath($collection);
    // @todo: Check that $collectionArchivePath exists.
    return $this->doGenerateArchiveEntity($fileData, $collectionArchivePath, $collection->getOwner());
  }

  /**
   * Generate a temporary archive file entity for a given collection.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The collection.
   *
   * @return \Drupal\file\FileInterface|null
   *   The archive file or NULL on failure.
   */
  public function generateArchiveEntity(MediaCollectionInterface $collection): ?FileInterface {
    return $this->doGenerateArchiveEntity(
      $this->collectionProcessor->process($collection),
      $this->archiveTargetPath($collection),
      $collection->getOwner()
    );
  }

  /**
   * Generate archive entity.
   *
   * @param \Drupal\damopen_assets_download\Model\FileArchivingData[] $fileData
   *   Archival data.
   * @param string $archiveLocation
   *   The desired location of the archive.
   * @param \Drupal\user\UserInterface $owner
   *   Desired owner of the archive entity.
   *
   * @return \Drupal\file\FileInterface|null
   */
  public function doGenerateArchiveEntity(array $fileData, string $archiveLocation, UserInterface $owner): ?FileInterface {
    if (!is_dir($this->fileSystem->dirname($archiveLocation))) {
      // @todo: Throw exception.
      return NULL;
    }

    $temporaryArchivePath = $this->archiver->createFileArchive($fileData);

    if ($temporaryArchivePath === NULL) {
      return NULL;
    }

    // @todo: Double-check that this works with s3.
    // @todo: Error handling.
    $this->fileSystem->move($temporaryArchivePath, $archiveLocation);

    /* @todo:
     * Maybe if a "SharedCollectionItem" entity is a added with a storage field
     * move archive to that?
     */
    return $this->fileManager->createArchiveEntity($owner, new SplFileInfo($archiveLocation));
  }

  /**
   * Generate path to the temporary archive file.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   The media collection.
   *
   * @return string|null
   *   The absolute path or NULL on failure.
   *
   * @see collectionArchivePath
   */
  private function archiveTargetPath(MediaCollectionInterface $collection): ?string {
    $fileDir = FieldManager::determineUploadLocation($collection, 'assets_archive');

    if (!$this->fileSystem->safeMkdir($fileDir)) {
      return NULL;
    }

    return "{$fileDir}/{$this->archiveTargetName()}";
  }

  /**
   * Generate archive name for a collection.
   *
   * @return string
   *   The archive name.
   *
   * @see collectionArchiveName
   */
  private function archiveTargetName(): string {
    return "Media library collection_{$this->currentDate()}.zip";
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
