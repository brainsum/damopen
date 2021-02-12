<?php

/**
 * @file
 * Contains media_collection.page.inc.
 *
 * Page callback for Media collection entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Media collection templates.
 *
 * Default template: media_collection.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_media_collection(array &$variables) {
  // Fetch MediaCollection Entity Object.
  $media_collection = $variables['elements']['#media_collection'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
