<?php

namespace Drupal\damopen_assets_download\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\damopen_assets_download\Service\AssetDownloadHandler;
use Drupal\damopen_assets_download\Service\FileResponseBuilder;
use SplFileInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use function str_replace;

/**
 * Class DownloadController.
 *
 * @package Drupal\damopen_assets_download\Controller
 *
 * @todo: Tune performance for non-local file systems (e.g s3), if needed/possible.
 */
class DownloadController extends ControllerBase {

  private $downloadHandler;

  private $fileResponseBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->downloadHandler = $container->get('damopen_assets_download.asset_download_handler');
    $instance->fileResponseBuilder = $container->get('damopen_assets_download.file_response_builder');
    return $instance;
  }

  /**
   * Download handler for media entities.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Response.
   */
  public function download(MediaInterface $media): Response {
    $downloadableFile = $this->downloadHandler->generateDownloadableFile($media);

    if ($downloadableFile === NULL) {
      throw new HttpException(500, 'Generating a downloadable file failed.');
    }

    // @todo: Customize download name like in ::styledDownload.
    return $this->fileResponseBuilder->build($downloadableFile);
  }

  /**
   * Download handler for styled media entities.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   * @param \Drupal\image\ImageStyleInterface $style
   *   The image style to apply.
   *
   * @return \Symfony\Component\HttpFoundation\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Response.
   */
  public function styledDownload(MediaInterface $media, ImageStyleInterface $style): Response {
    $downloadableFile = $this->downloadHandler->generateDownloadableStyledFile($media, $style);

    if ($downloadableFile === NULL) {
      throw new HttpException(500, 'Generating a downloadable file failed.');
    }

    $fileInfo = new SplFileInfo($downloadableFile->getFileUri());
    $styledFilename = str_replace(".{$fileInfo->getExtension()}", '', $downloadableFile->getFilename());
    $styledFilename .= '_' . $style->id() . ".{$fileInfo->getExtension()}";

    return $this->fileResponseBuilder->build(
      $downloadableFile,
      $media->getName() . '(' . $style->getName() . ')',
      $styledFilename
    );
  }

}
