<?php

declare(strict_types=1);

namespace Drupal\damopen_assets;

use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @todo Add interface description.
 */
interface HelperInterface {

  /**
   * Add list of media entities.
   *
   * @param array $variables
   *   The variables.
   */
  public function entityAddList(array &$variables): void;

  /**
   * Prepare links for non-image media types.
   *
   * @param array $bundles
   *   The bundles.
   */
  public function orderBundles(array &$bundles);

  /**
   * Returns ordering of bundles.
   *
   * Intended to be used in menus.
   *
   * @return array
   *   The types with weights.
   *
   * @todo: Add 3rd party settings to media types, order by that instead.
   */
  function assetsTypeOrdering(): array;

  /**
   * Change local tasks to the menu.
   *
   * @param array $variables
   *   The variables.
   */
  public function menuLocalTasks(array &$variables): void;

  /**
   * Return the view array of a given field.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   * @param string $name
   *   The field name.
   * @param array $options
   *   Render options.
   *
   * @return array|null
   *   The render array or NULL.
   */
  public function generateFieldView(MediaInterface $media, string $name, array $options): ?array;

  /**
   * Return the media type from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string|null
   *   The type or NULL.
   */
  public function requestToType(Request $request);

  /**
   * Render array for usage guide.
   *
   * @param string|null $type
   *   The given media type, if available.
   *
   * @return array
   *   The render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function usageGuideMarkup(?string $type): array;

}
