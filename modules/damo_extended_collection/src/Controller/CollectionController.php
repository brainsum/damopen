<?php

namespace Drupal\damo_extended_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media_collection\Controller;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Cache\Cache;

/**
 * Provides route responses for the Example module.
 */
class CollectionController extends ControllerBase {

  public function addMediaToCollection($collection, $media, $style) {
    $response = new AjaxResponse();
    $collection = \Drupal::entityTypeManager()->getStorage('media_collection')->load($collection);
    $items = $collection->get('items')->getValue();
    // Create media_collection_item entity.
    $media_collection_item = \Drupal::entityTypeManager()->getStorage('media_collection_item')->create([
      'media' => $media,
      'style' => 'other_hi_res_no_badge',
    ]);
    $media_collection_item->save();
    $items[] = ['target_id' => $media_collection_item->id()];
    $collection->set('items', $items);
    // Get current timestamp.
    $collection->set('field_updated', time());
    $collection->save();
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($media);
      // Invalidate media cache tags.
    $cache_tags = $media->getCacheTags();
    Cache::invalidateTags($cache_tags);
    return $response;
  }

  public function removeMediaFromCollection($collection, $media) {
    $response = new AjaxResponse();
    $collection = \Drupal::entityTypeManager()->getStorage('media_collection')->loadByProperties(['uuid' => $collection]);
    $mediaEntity = \Drupal::entityTypeManager()->getStorage('media_collection_item')->loadByProperties(['uuid' => $media]);
    $mediaEntity = reset($mediaEntity);
    $collection = reset($collection);
    $media = $mediaEntity->id();
    $items = $collection->get('items')->getValue();
    foreach ($items as $key => $item) {
      if ($item['target_id'] == $media) {
        unset($items[$key]);
      }
    }
    $collection->set('items', $items);
    $collection->set('field_updated', time());
    $collection->save();
    $mediaId = $mediaEntity->media->target_id;
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($mediaId);
      // Invalidate media cache tags.
    $cache_tags = $media->getCacheTags();
    Cache::invalidateTags($cache_tags);
    return $response;
  }

}
