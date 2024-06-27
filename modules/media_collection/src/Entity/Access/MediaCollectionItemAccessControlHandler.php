<?php

namespace Drupal\media_collection\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_collection\Entity\MediaCollectionInterface;

/**
 * Access controller for the Media collection item entity.
 *
 * @see \Drupal\media_collection\Entity\MediaCollectionItem.
 */
class MediaCollectionItemAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $item, $operation, AccountInterface $account) {
    /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface $item */
    $ownership = $item->getOwnerId() === $account->id() ? 'own' : 'any';

    switch ($operation) {
      case 'view':
        if ($account->hasPermission("view {$ownership} media collection item entities")) {
          return AccessResult::allowed();
        }

        if ($account->hasPermission('view shared media collection item entities')) {
          // Get parent and check the list of shared users.
          $parent = $item->parent();
          if ($parent === NULL) {
            // Maybe it is shared parent.
            $parent = $item->get('shared_parent')->entity;
          }
          if ($parent instanceof MediaCollectionInterface) {
            $shared_with = $parent->get('shared_with')->getValue();
            return AccessResult::allowedIf(in_array(['target_id' => $account->id()], $shared_with));
          }

          // No parent, no sharing.
          return AccessResult::forbidden();
        }

        return AccessResult::neutral();

      case 'update':
        return AccessResult::allowedIfHasPermission($account, "edit {$ownership} media collection item entities");

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, "delete {$ownership} media collection item entities");
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add media collection item entities');
  }

}
