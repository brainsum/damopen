<?php

namespace Drupal\damopen_assets_download\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;
use SplFileInfo;
use function array_keys;
use function str_replace;
use function strpos;

/**
 * Class FileManager.
 *
 * @package Drupal\damopen_assets_download\Service
 */
final class FileManager {

  /**
   * Field storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  private $fileStorage;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  private $streamWrapperManager;

  /**
   * FileManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    StreamWrapperManagerInterface $streamWrapperManager
  ) {
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->streamWrapperManager = $streamWrapperManager;
  }

  /**
   * Create a file entity for a user from an archive.
   *
   * @param \Drupal\user\UserInterface $user
   *   Desired user of the entity.
   * @param \SplFileInfo $archiveInfo
   *   Archive file info.
   *
   * @return \Drupal\file\FileInterface|null
   *   The archive file or NULL on failure.
   */
  public function createArchiveEntity(UserInterface $user, SplFileInfo $archiveInfo): ?FileInterface {
    /** @var \Drupal\file\FileInterface $archiveFile */
    $archiveFile = $this->fileStorage->create([
      'uri' => $this->absolutePathToStream($archiveInfo->getPathname()),
      'status' => 1,
      'uid' => $user->id(),
      'filename' => $archiveInfo->getFilename(),
      'filesize' => $archiveInfo->getSize(),
      'filemime' => 'application/zip',
    ]);

    return $archiveFile;
  }

  /**
   * Turn an absolute file path into a stream one, if possible.
   *
   * @param string $pathName
   *   The path.
   *
   * @return string
   *   The stream path, or the original path if no stream could be found for it.
   */
  private function absolutePathToStream(string $pathName): string {
    foreach (array_keys($this->streamWrapperManager->getWrappers()) as $type) {
      $stream = $this->streamWrapperManager->getViaScheme($type);

      if (!$stream instanceof LocalStream) {
        continue;
      }

      if (
        strpos($pathName, $stream->realpath()) === 0
        || strpos($pathName, $stream->getDirectoryPath()) === 0
      ) {
        return $this->streamWrapperManager->normalizeUri(str_replace([
          $stream->realpath(),
          $stream->getDirectoryPath(),
        ], $stream->getUri(), $pathName));
      }
    }

    // @todo: What if it's still not a stream path?
    return $pathName;
  }

}
