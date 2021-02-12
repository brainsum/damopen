<?php

namespace Drupal\media_collection_share\Entity\Access;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Media collection (shared) entity.
 *
 * @see \Drupal\media_collection_share\Entity\SharedMediaCollectionEntity.
 */
class SharedMediaCollectionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $entity */
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view shared media collection entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit media collection (shared) entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete media collection (shared) entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add media collection (shared) entities');
  }

}
