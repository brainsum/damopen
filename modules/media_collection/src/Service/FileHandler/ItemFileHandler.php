<?php

namespace Drupal\media_collection\Service\FileHandler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\damopen_common\Helper\FieldManager;
use Drupal\damopen_common\Service\DamoFileSystemInterface;
use Drupal\damopen_assets_download\Service\AssetArchiver;
use Drupal\damopen_assets_download\Service\FileManager;
use Drupal\file\FileInterface;
use Drupal\media_collection\Entity\MediaCollectionItemInterface;
use Drupal\media_collection\Service\EntityProcessor\CollectionItemProcessor;
use Drupal\user\UserInterface;
use SplFileInfo;
use function count;
use function reset;

/**
 * Class itemFileHandler.
 *
 * @package Drupal\media_collection\Service
 */
final class ItemFileHandler {

  /**
   * The file system.
   *
   * @var \Drupal\damopen_common\Service\DamoFileSystemInterface
   */
  private $fileSystem;

  /**
   * Collection item processor.
   *
   * @var \Drupal\media_collection\Service\EntityProcessor\CollectionItemProcessor
   */
  private $itemProcessor;

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
   * ItemFileHandler constructor.
   *
   * @param \Drupal\damopen_common\Service\DamoFileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\media_collection\Service\EntityProcessor\CollectionItemProcessor $itemProcessor
   *   Processor for collection items.
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
    CollectionItemProcessor $itemProcessor,
    AssetArchiver $archiver,
    TimeInterface $time,
    DateFormatterInterface $dateFormatter,
    FileManager $fileManager
  ) {
    $this->fileSystem = $fileSystem;
    $this->itemProcessor = $itemProcessor;
    $this->archiver = $archiver;
    $this->fileManager = $fileManager;
    // @todo: Maybe move to a custom service, these 2 need to be together.
    $this->time = $time;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Generate a downloadable file for an item.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The item.
   *
   * @return \Drupal\file\FileInterface|null
   *   The downloadable file or NULL.
   */
  public function generateDownloadableFile(MediaCollectionItemInterface $item): ?FileInterface {
    $fileData = $this->itemProcessor->process($item);
    $fileCount = count($fileData);

    if ($fileCount <= 0) {
      return NULL;
    }

    if ($fileCount === 1) {
      return reset($fileData)->file;
    }

    return $this->doGenerateArchiveEntity($fileData, $this->archiveTargetPath($item), $item->getOwner());
  }

  /**
   * Generate a temporary archive file entity for a given item.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The item.
   *
   * @return \Drupal\file\FileInterface|null
   *   The archive file or NULL on failure.
   */
  public function generateArchiveEntity(MediaCollectionItemInterface $item): ?FileInterface {
    return $this->doGenerateArchiveEntity(
      $this->itemProcessor->process($item),
      $this->archiveTargetPath($item),
      $item->getOwner()
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
  protected function doGenerateArchiveEntity(array $fileData, string $archiveLocation, UserInterface $owner): ?FileInterface {
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

    return $this->fileManager->createArchiveEntity($owner, new SplFileInfo($archiveLocation));
  }

  /**
   * Generate path to the temporary archive file.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   The item.
   *
   * @return string|null
   *   The absolute path or NULL on failure.
   */
  private function archiveTargetPath(MediaCollectionItemInterface $item): ?string {
    // @todo: Add an asset_archive field to the item instead.
    $fileDir = FieldManager::determineUploadLocation($item->parent(), 'assets_archive');

    if (!$this->fileSystem->safeMkdir($fileDir)) {
      return NULL;
    }

    return "{$fileDir}/{$this->archiveTargetName($item)}";
  }

  /**
   * Generate archive name for an item.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionItemInterface $item
   *   Item.
   *
   * @return string
   *   The archive name.
   */
  private function archiveTargetName(MediaCollectionItemInterface $item): string {
    $archiveTargetName = $item->media()->getName();

    if ($style = $item->style()) {
      $archiveTargetName .= "_{$style->getName()}";
    }

    $archiveTargetName .= "_{$this->currentDate()}";

    return "{$archiveTargetName}.zip";
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
