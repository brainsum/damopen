<?php

namespace Drupal\damopen_assets_download\Service;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\file\FileInterface;
use SplFileInfo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FileResponseBuilder.
 *
 * @package Drupal\damopen_assets_download\Response
 */
class FileResponseBuilder {

  /**
   * Transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  private $transliteration;

  /**
   * FileResponseBuilder constructor.
   *
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   Transliteration service.
   */
  public function __construct(
    TransliterationInterface $transliteration
  ) {
    $this->transliteration = $transliteration;
  }

  /**
   * Builds a BinaryFileResponse from a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   * @param string $description
   *   Optional description.
   * @param string|null $fileName
   *   Filename used for downloads.
   * @param string|null $fallbackName
   *   Filename fallback used for downloads if $fileName fails for any reason.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The file response.
   */
  public function build(
    FileInterface $file,
    string $description = 'Media Library assets download',
    ?string $fileName = NULL,
    ?string $fallbackName = NULL
  ): BinaryFileResponse {
    $fileInfo = new SplFileInfo($file->getFileUri());
    $response = new BinaryFileResponse(
      $fileInfo,
      Response::HTTP_OK,
      [
        'Content-Description' => $description,
      ]
    );

    if ($fileName === NULL) {
      $fileName = $file->getFilename();
    }

    if ($fallbackName === NULL) {
      $fallbackName = $fileInfo->getFilename();
    }

    $response->setContentDisposition(
      'attachment',
      $this->transliteration->transliterate($fileName),
      $this->transliteration->transliterate($fallbackName)
    );

    return $response;
  }

}
