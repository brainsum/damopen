<?php

namespace Drupal\damopen_assets_thumbnails\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\damopen_common\Service\DamoFileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use InvalidArgumentException;
use RuntimeException;
use SplFileInfo;
use function str_replace;

/**
 * Class VideoThumbnail.
 *
 * @package Drupal\damopen_assets_thumbnails\Service
 */
class VideoThumbnail {

  public const BASE_FOLDER_NAME = 'video-thumbnail';

  public const EXTENSION = 'jpeg';

  /**
   * FFMpeg.
   *
   * @var \FFMpeg\FFMpeg
   */
  protected $ffMpeg;

  /**
   * File system.
   *
   * @var \Drupal\damopen_common\Service\DamoFileSystemInterface
   */
  protected $fileSystem;

  /**
   * File entity storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The system URI scheme (e.g private, s3).
   *
   * @var string
   */
  protected $uriScheme;

  /**
   * Stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * File URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * VideoThumbnail constructor.
   *
   * @param \FFMpeg\FFMpeg $ffMpeg
   *   FFMpeg service.
   * @param \Drupal\damopen_common\Service\DamoFileSystemInterface $fileSystem
   *   FileSystem.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   Stream wrapper manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    FFMpeg $ffMpeg,
    DamoFileSystemInterface $fileSystem,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory,
    StreamWrapperManagerInterface $streamWrapperManager,
    FileUrlGeneratorInterface $fileUrlGenerator
  ) {
    $this->ffMpeg = $ffMpeg;
    $this->fileSystem = $fileSystem;
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->currentUser = $currentUser;
    $this->uriScheme = $configFactory
      ->get('system.file')
      ->get('default_scheme');
    $this->streamWrapperManager = $streamWrapperManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * Returns the base folder for thumbnails.
   *
   * @return string
   *   The base folder for thumbnails (including the uri scheme).
   */
  private function baseTargetFolder(): string {
    // @todo: This seems to be wrong occasionally.
    return "{$this->uriScheme}://" . static::BASE_FOLDER_NAME;
  }

  /**
   * Returns the folder for thumbnails.
   *
   * @param string $baseFolder
   *   The base folder.
   *
   * @return string
   *   The thumbnail folder.
   */
  private function thumbnailTargetFolder(string $baseFolder): string {
    return $baseFolder . '/' . (new DrupalDateTime())->format('Y-m');
  }

  /**
   * Ensure that the target base folder exists.
   *
   * @param string $folderName
   *   The name of the folder.
   *
   * @throws \RuntimeException
   */
  private function ensureFolder(string $folderName): void {
    $result = $this->fileSystem->safeMkdir($folderName);

    if ($result === FALSE) {
      throw new RuntimeException("The {$folderName} folder could not be created.");
    }
  }

  /**
   * Extracts the thumbnail file from the video.
   *
   * @param string $videoUri
   *   Absolute URI to the video file.
   * @param string $thumbnailFileName
   *   Desired filename of the extracted thumbnail.
   *
   * @return string
   *   The path to the extracted thumbnail.
   *
   * @note: This function extracts the thumbnail to the filesystem.
   */
  protected function extractThumbnailFromVideo($videoUri, $thumbnailFileName): string {
    // Due to ffmpeg this needs to be a local path.
    $temporaryPath = $this->fileSystem->realpath('temporary://video-file-thumbnail--' . $thumbnailFileName);
    /** @var \FFMpeg\Media\Video $video */
    $video = $this->ffMpeg->open($videoUri);
    $frame = $video->frame(TimeCode::fromSeconds(0));
    $frame->save($temporaryPath, TRUE);
    // @todo: Handle exceptions, errors, double-check that the thumbnail was created.
    $targetFolder = $this->thumbnailTargetFolder($this->baseTargetFolder());
    $this->ensureFolder($targetFolder);
    $targetPath = "{$targetFolder}/{$thumbnailFileName}";
    return $this->fileSystem->move($temporaryPath, $targetPath);
  }

  /**
   * Generates the filename for the thumbnail.
   *
   * @param string $videoUri
   *   The absolute uri to the video.
   *
   * @return string
   *   The thumbnail filename.
   */
  protected function generateThumbnailFileName(string $videoUri): string {
    $videoInfo = new SplFileInfo($videoUri);
    $thumbnailName = str_replace(".{$videoInfo->getExtension()}", '', $videoInfo->getFilename());
    return $thumbnailName . '.' . static::EXTENSION;
  }

  /**
   * Return the video source URI to be used with ffmpeg.
   *
   * @param \Drupal\file\FileInterface $video
   *   The video file.
   *
   * @return string
   *   The source uri (either a URL or a file path).
   */
  protected function sourceUri(FileInterface $video): string {
    $scheme = $this->streamWrapperManager->getViaUri($video->getFileUri());

    if ($scheme === FALSE) {
      // @todo: Maybe throw an exception.
      return $video->getFileUri();
    }

    if ($scheme instanceof LocalStream) {
      return $video->getFileUri();
    }

    return $this->fileUrlGenerator
      ->generateAbsoluteString($video->getFileUri());
  }

  /**
   * Generates a thumbnail from a video file.
   *
   * @param \Drupal\file\FileInterface $video
   *  The video file entity.
   *
   * @return \Drupal\file\FileInterface
   *   The Thumbnail file entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function generateFileThumbnail(FileInterface $video): FileInterface {
    // @todo: Error handling.
    $thumbnailFileName = $this->generateThumbnailFileName($video->getFileUri());
    $destinationPath = $this->extractThumbnailFromVideo($this->sourceUri($video), $thumbnailFileName);
    // For the actual file entity we have to use the wrapped path.
    // Realpath is going to break things.
    $thumbnailInfo = new SplFileInfo($destinationPath);
    /** @var \Drupal\file\FileInterface $thumbnailFile */
    $thumbnailFile = $this->fileStorage->create([
      'uri' => $thumbnailInfo->getPathname(),
      'filename' => $thumbnailFileName,
      'filesize' => $thumbnailInfo->getSize(),
      'uid' => $this->currentUser->id(),
    ]);
    $thumbnailFile->save();
    return $thumbnailFile;
  }

  /**
   * Generates a thumbnail for a video asset.
   *
   * @param \Drupal\media\MediaInterface $media
   *  The video media entity.
   *
   * @return \Drupal\file\FileInterface
   *   The Thumbnail file entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function generateAssetThumbnail(MediaInterface $media): FileInterface {
    if ($media->bundle() !== 'video_file') {
      throw new InvalidArgumentException("The asset '{$media->getName()}' ({$media->id()}) is not supported for automatic thumbnail generation.");
    }

    /** @var \Drupal\file\FileInterface|null $videoFile */
    $videoFile = $media->get('field_video_file')->entity;

    if ($videoFile === NULL) {
      throw new RuntimeException("The asset '{$media->getName()}' ({$media->id()}) does not contain a valid video file.");
    }

    return $this->generateFileThumbnail($videoFile);
  }

}
