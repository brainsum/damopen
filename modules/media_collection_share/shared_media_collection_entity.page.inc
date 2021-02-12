<?php

/**
 * @file
 * Contains shared_media_collection.page.inc.
 *
 * Page callback for Media collection (shared) entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Media collection (shared) templates.
 *
 * Default template: shared_media_collection.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_shared_media_collection(array &$variables) {
  // Fetch SharedMediaCollectionEntity Entity Object.
  $shared_media_collection = $variables['elements']['#shared_media_collection'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
