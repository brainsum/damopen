<?php

/**
 * @file
 * Contains media_collection_item.page.inc.
 *
 * Page callback for Media collection item entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Media collection item templates.
 *
 * Default template: media_collection_item.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_media_collection_item(array &$variables) {
  // Fetch MediaCollectionItem Entity Object.
  $media_collection_item = $variables['elements']['#media_collection_item'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
