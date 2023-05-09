<?php

namespace Drupal\media_collection\Service;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\media_collection\Entity\MediaCollectionInterface;
use Drupal\media_collection\Service\FileHandler\CollectionFileHandler;
use Drupal\damopen_assets_download\Service\FileResponseBuilder;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Response;
use function file_exists;

/**
 * Class DownloadHandler.
 */
final class DownloadHandler {

  /**
   * The collection handler.
   *
   * @var \Drupal\media_collection\Service\CollectionHandler
   */
  private $collectionHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private $currentUser;

  /**
   * Download handler for collections.
   *
   * @var \Drupal\media_collection\Service\FileHandler\CollectionFileHandler
   */
  private $collectionFileHandler;

  /**
   * The file response builder.
   *
   * @var \Drupal\damopen_assets_download\Service\FileResponseBuilder
   */
  private $fileResponseBuilder;

  /**
   * DownloadHandler constructor.
   *
   * @param \Drupal\media_collection\Service\CollectionHandler $collectionHandler
   *   The collection handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\media_collection\Service\FileHandler\CollectionFileHandler $collectionFileHandler
   *   Download handler for collections.
   * @param \Drupal\damopen_assets_download\Service\FileResponseBuilder $fileResponseBuilder
   *   The file response builder.
   */
  public function __construct(
    CollectionHandler $collectionHandler,
    AccountProxyInterface $currentUser,
    CollectionFileHandler $collectionFileHandler,
    FileResponseBuilder $fileResponseBuilder
  ) {
    $this->collectionHandler = $collectionHandler;
    $this->currentUser = $currentUser;

    $this->collectionFileHandler = $collectionFileHandler;
    $this->fileResponseBuilder = $fileResponseBuilder;
  }

  /**
   * Handler for downloading the latest asset for the current user.
   *
   * @return \Symfony\Component\HttpFoundation\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
   *   TBD.
   */
  public function currentUserDownload($collectionId) {
    return $this->downloadLatestArchiveForUser($this->currentUser->id(), $collectionId);
  }

  /**
   * Handler for downloading the latest asset for a given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The given user.
   *
   * @return \Symfony\Component\HttpFoundation\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
   *   TBD.
   */
  public function givenUserDownload(UserInterface $user) {
    return $this->downloadLatestArchiveForUser($user->id());
  }

  /**
   * Download an asset.
   *
   * @param \Drupal\media_collection\Entity\MediaCollectionInterface $collection
   *   Collection from which to download the asset.
   *
   * @return \Symfony\Component\HttpFoundation\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The file download response.
   */
  public function downloadLatestArchive(MediaCollectionInterface $collection) {
    $archive = $this->collectionFileHandler->generateArchiveEntity($collection);

    if ($archive === NULL) {
      return new Response(NULL, Response::HTTP_NOT_FOUND);
    }

    if (!file_exists($archive->getFileUri())) {
      return new Response(NULL, Response::HTTP_NOT_FOUND);
    }

    return $this->fileResponseBuilder->build($archive);
  }

  /**
   * Handler for downloading the latest asset for a given user.
   *
   * @param int $userId
   *   The given user ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
   *   TBD.
   */
  public function downloadLatestArchiveForUser(int $userId, $collectionId = NULL) {
    if (!$collectionId) {
      $collection = $this->collectionHandler->loadCollectionForUser($userId);
    }
    else {
      $collection = \Drupal::entityTypeManager()->getStorage('media_collection')->load($collectionId);
      // $collection = $this->collectionHandler->loadCollection($collectionId);
    }

    if ($collection === NULL) {
      return new Response(NULL, Response::HTTP_NOT_FOUND);
    }

    return $this->downloadLatestArchive($collection);
  }

}
