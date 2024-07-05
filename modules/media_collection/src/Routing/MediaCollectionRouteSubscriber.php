<?php

namespace Drupal\media_collection\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribe to media bulk upload paths.
 *
 * @package Drupal\damopen_assets\Routing
 */
class MediaCollectionRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('view.collection_view_page.collection_view')) {
      $route->setRequirement('_custom_access', 'media_collection.view_access_check::access');
    }
  }

}
