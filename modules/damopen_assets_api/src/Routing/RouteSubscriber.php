<?php

namespace Drupal\damopen_assets_api\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // @todo: Add 'access tml jsonapi resources' permission.
    // Add custom permission to json api routes.
    foreach ($collection->all() as $name => $route) {
      if ($route->getOption('_is_jsonapi')) {
        $route->setRequirement('_permission', 'access tml jsonapi resources');
      }
      if (in_array($name, ['system.private_file_download', 'system.files'])) {
        $route->setOption('_auth', ['basic_auth', 'cookie']);
      }
    }
  }

}
