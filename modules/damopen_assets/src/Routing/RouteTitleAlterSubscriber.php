<?php

namespace Drupal\damopen_assets\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alter route titles.
 *
 * @package Drupal\damopen_assets\Routing
 */
class RouteTitleAlterSubscriber extends RouteSubscriberBase {

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.media.add_page')) {
      $route->setDefault('_title', 'Add content');
      $route->setDefault('_title_callback', NULL);
    }
    if ($route = $collection->get('media_upload.bulk_media_upload_list')) {
      $route->setDefault('_title', 'Upload content in bulk');
    }
  }

}
