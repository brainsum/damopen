<?php

namespace Drupal\media_collection\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Media collection entity.
 *
 * @see \Drupal\media_collection\Entity\MediaCollection.
 */
class MediaCollectionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $collection, $operation, AccountInterface $account) {
    /** @var \Drupal\media_collection\Entity\MediaCollectionInterface $collection */

    $ownership = $collection->getOwnerId() === $account->id() ? 'own' : 'any';

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, "view {$ownership} media collection entities");

      case 'update':
        return AccessResult::allowedIfHasPermission($account, "edit {$ownership} media collection entities");

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, "delete {$ownership}  media collection entities");
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add media collection entities');
  }

}
