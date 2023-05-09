<?php

namespace Drupal\damo_extended_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media_collection\Controller;
use Drupal\Core\Ajax\AjaxResponse;

/**
 * Provides route responses for the Example module.
 */
class CollectionController extends ControllerBase {

  public function addMediaToCollection($collection, $media, $style) {
    $response = new AjaxResponse();
    // devel_dump($collection);
    $collection = \Drupal::entityTypeManager()->getStorage('media_collection')->load($collection);
    $items = $collection->get('items')->getValue();
    // Create media_collection_item entity.
    $media_collection_item = \Drupal::entityTypeManager()->getStorage('media_collection_item')->create([
      'media' => $media,
      'collection' => $collection->id(),
      'style' => 'other_hi_res_no_badge',
    ]);
    $media_collection_item->save();
    $items[] = ['target_id' => $media_collection_item->id()];
    // devel_dump($items);
    $collection->set('items', $items);
    // devel_dump($items);
    // exit;
    $collection->save();
    $response->addCommand(new \Drupal\Core\Ajax\AlertCommand('Media added to collection'));
    return $response;
  }

  public function removeMediaFromCollection($collection, $media) {
    $response = new AjaxResponse();
    devel_dump($collection);
    $collection = \Drupal::entityTypeManager()->getStorage('media_collection')->loadByProperties(['uuid' => $collection]);
    $mediaEntity = \Drupal::entityTypeManager()->getStorage('media_collection_item')->loadByProperties(['uuid' => $media]);
    $mediaEntity = reset($mediaEntity);
    $collection = reset($collection);
    devel_dump($mediaEntity);
    devel_dump($collection);
    $media = $mediaEntity->id();
    $items = $collection->get('items')->getValue();
    foreach ($items as $key => $item) {
      if ($item['target_id'] == $media) {
        unset($items[$key]);
      }
    }
    $collection->set('items', $items);
    $collection->save();
    return $response;
  }

}
