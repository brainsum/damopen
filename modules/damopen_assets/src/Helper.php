<?php

declare(strict_types=1);

namespace Drupal\damopen_assets;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper class for DAM Open Assets.
 */
final class Helper implements HelperInterface {

  /**
   * Constructs a Helper object.
   */
  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly RequestStack $requestStack,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}


  /**
   * {@inheritdoc}
   */
  public function entityAddList(array &$variables): void {
    $route_name = $this->routeMatch->getRouteName();
    if ($route_name !== 'entity.media.add_page') {
      return;
    }

    // @todo: Fix "media bulk" page ordering, too?
    if (isset($variables['bundles']['image']['add_link'])) {
      $imageBulkAddUrl = new Url('damopen_upload.upload');
      // The 'add_link' is an instance of \Drupal\Core\Link.
      $variables['bundles']['image']['add_link']->setUrl($imageBulkAddUrl);
    }

    foreach ($variables['bundles'] as $bundle_name => &$bundle) {
      unset($variables['bundles'][$bundle_name]['description']);
      /** @var \Drupal\Core\Link $link */
      $link = &$bundle['add_link'];
      $link->getUrl()->setOption('query', ['destination' => '/media/add']);
    }

    $this->orderBundles($variables['bundles']);
  }

  /**
   * {@inheritdoc}
   */
  public function orderBundles(array &$bundles): void {
    $ordering = $this->assetsTypeOrdering();

    uksort($bundles, static function ($first, $second) use ($ordering) {
      if (!isset($ordering[$first], $ordering[$second])) {
        return 0;
      }

      if ($ordering[$first] === $ordering[$second]) {
        return 0;
      }

      return $ordering[$first] > $ordering[$second] ? 1 : -1;
    });
  }

  /**
   * {@inheritDoc}
   */
  function assetsTypeOrdering(): array {
    return [
      'image' => 0,
      'video' => 10,
      'video_file' => 10,
      'audio' => 20,
      'template' => 30,
      'logo' => 40,
      'guideline' => 50,
      'icon' => 60,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function menuLocalTasks(array &$variables): void {
    $routes = [
      'media.tasks:entity.media.edit_form' => NULL,
      'media.tasks:entity.media.delete_form' => '/',
    ];
    foreach ($routes as $route => $destination) {
      if (isset($variables['primary'][$route])) {
        $url = $variables['primary'][$route]['#link']['url'] ?? NULL;
        if (!is_a($url, 'Drupal\Core\Url')) {
          return;
        }
        if ($destination === NULL) {
          $destination = $this->requestStack->getCurrentRequest()->getRequestUri();
        }
        $url->setOption('query', ['destination' => $destination]);
        $variables['primary'][$route]['#link']['url'] = $url;
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function generateFieldView(MediaInterface $media, string $name, array $options): ?array {
    if (
      $media->hasField($name)
      && ($field = $media->get($name))
      && !$field->isEmpty()
    ) {
      return $field->view($options);
    }

    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function requestToType(Request $request) {
    if ($media = $request->attributes->get('media', NULL)) {
      /** @var \Drupal\media\MediaInterface $media */
      return $media->bundle();
    }

    // We select the first from bundles.
    $mediaTypes = $request->query->get('type');

    if ($mediaTypes === NULL) {
      return 'image';
    }

    $types = explode(' ', strtolower($mediaTypes));
    $type = reset($types);

    return $type ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function usageGuideMarkup(?string $type): array {
    $build = [
      '#type' => 'markup',
      '#prefix' => '<div class="dam-media-description-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($type === NULL) {
      return $build;
    }

    $mediaTypeStorage = $this->entityTypeManager->getStorage('media_type');

    /** @var \Drupal\media\MediaTypeInterface $mediaType */
    $mediaType = $mediaTypeStorage->load($type);

    if ($mediaType !== NULL) {
      $build['content'] = [
        'title' => [
          '#prefix' => '<span class="dam-media-description-title">',
          '#suffix' => '</span>',
          '#markup' => (new TranslatableMarkup('General usage advice'))->render(),
        ],
        'description' => [
          '#prefix' => '<span class="dam-media-description">',
          '#suffix' => '</span>',
          '#markup' => Markup::create($mediaType->getDescription()),
        ],
      ];
    }

    return $build;
  }

}
