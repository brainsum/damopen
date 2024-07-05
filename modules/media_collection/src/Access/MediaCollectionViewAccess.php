<?php

namespace Drupal\media_collection\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for media collections.
 */
class MediaCollectionViewAccess implements AccessInterface {

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
  public function access(AccountInterface $account, Route $route) {
    $parts = explode('/', \Drupal::request()->getRequestUri());
    $media_collection = $parts[2];
    $collection = $this->entityTypeManager->getStorage('media_collection')->load($media_collection);

    if ($collection) {
      if ($collection->getOwnerId() === $account->id()) {
        return AccessResult::allowed();
      }
      $shared_with = $collection->get('shared_with')->getValue();
      return AccessResult::allowedIf(in_array(['target_id' => $account->id()], $shared_with));
    }
    return AccessResult::forbidden();
  }
}
