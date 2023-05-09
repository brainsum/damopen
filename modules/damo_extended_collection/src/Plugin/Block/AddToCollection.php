<?php

// Create a drupal 9 block class.
namespace Drupal\damo_extended_collection\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'CreateCollectionBlock' block.
 *
 * @Block(
 *  id = "add_to_collection_block",
 *  admin_label = @Translation("Add to collection block"),
 * )
 */
class AddToCollection extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if ($route_name === "media_upload.bulk_media_upload") {
      return [];
    }

    // Get media collections.
    $query = \Drupal::entityQuery('media_collection')
      ->condition('uid', \Drupal::currentUser()->id());

    $ids = $query->execute();
    $media_collections = \Drupal::entityTypeManager()->getStorage('media_collection')->loadMultiple($ids);
    $param = \Drupal::routeMatch()->getParameters();
    $mid = $param->get('media')->id();

    foreach ($media_collections as $collection) {
      $results[] = [
        'title' => $collection->get('field_title')->value,
        'id' => $collection->id(),
        'mid' => $mid,
      ];
    }
    return [
      '#theme' => 'add_to_collection',
      '#collections' => $results,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
