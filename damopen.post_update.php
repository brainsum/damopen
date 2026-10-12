<?php

/**
 * @file
 * Post update functions for the DAMopen profile.
 */

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;

/**
 * Search image alt text and the IPTC caption, by-line and object name.
 */
function damopen_post_update_search_alt_text_and_iptc(): string {
  $index = Index::load('database_media');
  if (!$index) {
    return 'There is no database_media index; nothing changed.';
  }

  $storage = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('media');
  $new = [
    'image_alt' => ['Image alternative text', 'field_image:alt'],
    'iptc_by_line' => ['IPTC by-line', 'field_iptc_by_line'],
    'iptc_caption' => ['IPTC caption', 'field_iptc_caption'],
    'iptc_object_name' => ['IPTC object name', 'field_iptc_object_name'],
  ];
  $added = [];
  foreach ($new as $id => [$label, $path]) {
    if ($index->getField($id) || !isset($storage[explode(':', $path)[0]])) {
      continue;
    }
    $field = new Field($index, $id);
    $field->setLabel($label)
      ->setDatasourceId('entity:media')
      ->setPropertyPath($path)
      ->setType('text');
    $index->addField($field);
    $added[] = $id;
  }
  if (!$added) {
    return 'Alt text and IPTC fields were already indexed.';
  }
  $index->save();

  // The asset search box lists the fields it searches.
  if ($view = \Drupal::entityTypeManager()->getStorage('view')->load('asset_search')) {
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      foreach ($display['display_options']['filters'] ?? [] as $id => $filter) {
        if (($filter['plugin_id'] ?? '') === 'search_api_fulltext' && !empty($filter['fields'])) {
          $fields = array_values(array_unique([...$filter['fields'], ...$added]));
          sort($fields);
          $display['display_options']['filters'][$id]['fields'] = $fields;
        }
      }
    }
    $view->set('display', $displays)->save();
  }

  $index->reindex();
  return 'Asset search now includes ' . implode(', ', $added) . '. The database_media index is scheduled for reindexing.';
}
