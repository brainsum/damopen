<?php

namespace Drupal\media_collection_share\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

class MediaCollectionAccessCheck implements AccessInterface {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function access(AccountInterface $account, Route $route, $media_collection) {
    $collection = $this->entityTypeManager->getStorage('media_collection')->load($media_collection);
    if ($collection && (
      $collection->getOwnerId() === $account->id() ||
        $account->hasPermission('share media collections with users')
      )
    ) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }
}
