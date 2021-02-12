<?php

namespace Drupal\damopen_assets_download\Render;

use Drupal;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;

/**
 * Class MediaDownloadButton.
 *
 * @package Drupal\damopen_assets\Render
 */
class MediaDownloadButton {

  /**
   * Returns the render array for the button.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   *
   * @return array
   *   The render array.
   */
  public static function build(MediaInterface $media): array {
    $downloadUrl = Url::fromRoute(
      'damopen_assets_download.asset_download',
      [
        'media' => $media->id(),
      ]
    );

    if (!$downloadUrl->access(Drupal::currentUser())) {
      return [];
    }

    return [
      '#type' => 'link',
      '#title' => new TranslatableMarkup('Download'),
      '#url' => $downloadUrl,
      '#attributes' => [
        'download' => '',
        'class' => [
          'button',
          'button--green',
          'download-btn',
        ],
      ],
      '#weight' => 0,
    ];
  }

}
