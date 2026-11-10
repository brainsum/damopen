<?php

namespace Drupal\damopen_category_upload\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\damopen_category_upload\Access\LibraryStructureAccess;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the category administration check to the Term Merge routes.
 */
final class LibraryStructureRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection as $route) {
      // Term Merge marks its merge, target and confirm routes with this check.
      if ($route->hasRequirement('_term_merge_access_check')) {
        $route->setRequirement('_custom_access', LibraryStructureAccess::class . '::access');
      }
    }
  }

}
