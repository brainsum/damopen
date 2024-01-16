<?php

namespace Drupal\media_collection\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Class HeaderMarkupGenerator.
 *
 * @package Drupal\media_collection\Service
 */
final class HeaderMarkupGenerator {

  /**
   * Path to the media_collection module.
   *
   * @var string
   */
  private $modulePath;

  /**
   * Translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  private $translation;

  /**
   * Media collection handler.
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
   * Module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * File URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * HeaderMarkupGenerator constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   Translation service.
   * @param \Drupal\media_collection\Service\CollectionHandler $handler
   *   Collection handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user.
   */
  public function __construct(
    TranslationInterface $translation,
    CollectionHandler $handler,
    AccountProxyInterface $currentUser,
    ModuleExtensionList $moduleExtensionList,
    FileUrlGeneratorInterface $fileUrlGenerator
  ) {
    $this->translation = $translation;
    $this->collectionHandler = $handler;
    $this->currentUser = $currentUser;
    $this->moduleExtensionList = $moduleExtensionList;
    $this->fileUrlGenerator = $fileUrlGenerator;

    $this->modulePath = $this->moduleExtensionList
      ->getPath('media_collection');
  }

  /**
   * Returns the render array for the empty collection header link.
   *
   * @return array
   *   The render array.
   */
  public function emptyMediaCollectionLink(): array {
    $collectionContent = [
      [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => $this->generateFileUri("{$this->modulePath}/assets/collection-icon.png"),
          'class' => [
            'empty-collection-icon',
          ],
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->translation->translate('Your collection is empty'),
        '#attributes' => [
          'class' => [
            'empty-collection-text',
          ],
        ],
      ],
    ];

    $build = $this->buildLink($collectionContent);
    $build['#attributes']['class'][] = 'collection-header-empty';
    $build['#attributes']['id'] = 'media-collection--empty-collection';

    return $build;
  }

  /**
   * Returns the render array for the collection header link with items.
   *
   * @return array
   *   The render array.
   */
  public function withItemsMediaCollectionLink(): array {
    $collection = $this->collectionHandler->loadCollectionForUser($this->currentUser->id());

    $collectionContent = [
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $collection === NULL ? 0 : $collection->itemCount(),
        '#attributes' => [
          'class' => [
            'collection-item-number',
          ],
        ],
        '#prefix' => '<span class="collection-item-number-wrapper">',
        '#suffix' => '</span>',
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => $this->generateFileUri("{$this->modulePath}/assets/collection-icon-blue.png"),
          'class' => [
            'collection-icon',
          ],
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->translation->translate('Items in collection'),
        '#attributes' => [
          'class' => [
            'collection-text',
          ],
        ],
      ],
    ];

    $build = $this->buildLink($collectionContent);
    $build['#attributes']['class'][] = 'collection-header';
    $build['#attributes']['id'] = 'media-collection--collection';

    if ($collection !== NULL) {
      $build['#cache']['tags'] = Cache::mergeTags($build['#cache']['tags'], $collection->getCacheTags());
      $build['#cache']['contexts'] = Cache::mergeTags($build['#cache']['contexts'], $collection->getCacheContexts());
    }

    return $build;
  }

  /**
   * Build a link with the given content.
   *
   * @param array $content
   *   The link content.
   *
   * @return array
   *   The built link.
   */
  private function buildLink(array $content): array {
    $url = Url::fromRoute('media_collection.collection.current_user');

    if (!$url->access($this->currentUser)) {
      return [];
    }

    return [
      '#type' => 'link',
      '#url' => $url,
      '#title' => $content,
      '#attributes' => [
        'id' => 'media-collection--collection',
        'class' => [
          'dam-media-collection-link',
        ],
        'style' => [
          'display: none',
        ],
      ],
      '#cache' => $this->cacheDefaults(),
    ];
  }

  /**
   * Returns cache defaults.
   *
   * @return array
   *   Cache defaults.
   */
  private function cacheDefaults(): array {
    return [
      'contexts' => [
        'user',
      ],
      'tags' => [],
      // 1 day.
      'max-age' => 86400,
    ];
  }

  /**
   * Generates an absolute URL to the file.
   *
   * @param string $filePath
   *   Local path of the file.
   *
   * @return string
   *   Generated URI.
   */
  private function generateFileUri($filePath): string {
    return Url::fromUri($this->fileUrlGenerator->generate($filePath))->getUri();
  }

}
