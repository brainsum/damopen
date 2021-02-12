<?php

namespace Drupal\media_collection_share\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media_collection\Service\FileHandler\ItemFileHandler;
use Drupal\damopen_assets_download\Service\FileResponseBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function file_exists;
use function reset;

/**
 * Class DownloadHandler.
 */
final class DownloadHandler {

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * The storage for shared collections.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $sharedCollectionStorage;

  /**
   * The storage for collection items.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $itemStorage;

  /**
   * Handler for items.
   *
   * @var \Drupal\media_collection\Service\FileHandler\ItemFileHandler
   */
  private $itemFileHandler;

  /**
   * The file response builder.
   *
   * @var \Drupal\damopen_assets_download\Service\FileResponseBuilder
   */
  private $fileResponseBuilder;

  /**
   * DownloadHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   * @param \Drupal\media_collection\Service\FileHandler\ItemFileHandler $fileHandler
   *   File handler for items.
   * @param \Drupal\damopen_assets_download\Service\FileResponseBuilder $fileResponseBuilder
   *   The file response builder.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    FileSystemInterface $fileSystem,
    ItemFileHandler $fileHandler,
    FileResponseBuilder $fileResponseBuilder
  ) {
    $this->fileSystem = $fileSystem;

    $this->sharedCollectionStorage = $entityTypeManager->getStorage('shared_media_collection');
    $this->itemStorage = $entityTypeManager->getStorage('media_collection_item');
    $this->itemFileHandler = $fileHandler;
    $this->fileResponseBuilder = $fileResponseBuilder;
  }

  /**
   * Handler for downloading the assets for a given shared collection.
   *
   * @param string $date
   *   Date from URL.
   * @param string $uuid
   *   UUID of the shared collection.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The file download response.
   */
  public function downloadSharedCollection(string $date, string $uuid): BinaryFileResponse {
    /** @var \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface[] $collections */
    $collections = $this->sharedCollectionStorage->loadByProperties([
      'uuid' => $uuid,
    ]);
    $collection = reset($collections);

    if ($collection === FALSE) {
      throw new NotFoundHttpException("No shared collection was found for {$date}.");
    }

    $archive = $collection->archiveFile();

    if (!file_exists($archive->getFileUri())) {
      // @todo: Generate instead.
      throw new NotFoundHttpException('No downloadable asset archive was found.');
    }

    return $this->fileResponseBuilder->build($archive);
  }

  /**
   * Handler for downloading the assets for a given shared item.
   *
   * @param string $uuid
   *   UUID of the item.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The download response.
   */
  public function downloadSharedItem(string $uuid): BinaryFileResponse {
    /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface[] $items */
    $items = $this->itemStorage->loadByProperties(['uuid' => $uuid]);
    $item = reset($items);

    if ($item === FALSE) {
      throw new NotFoundHttpException('No downloadable asset was found.');
    }

    $file = $this->itemFileHandler->generateDownloadableFile($item);

    if ($file === NULL) {
      throw new HttpException(500, 'Generating a downloadable file failed.');
    }

    return $this->fileResponseBuilder->build($file);
  }

}
