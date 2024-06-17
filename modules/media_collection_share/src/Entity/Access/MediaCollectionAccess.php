<?php

namespace Drupal\media_collection_share\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for media collections.
 */
class MediaCollectionAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
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
