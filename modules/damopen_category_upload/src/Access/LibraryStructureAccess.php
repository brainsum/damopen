<?php

namespace Drupal\damopen_category_upload\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Restricts bulk Library structure changes to category administrators.
 *
 * Merging terms rewrites every reference, including upload category
 * assignments, and deletes the merged terms without entity access checks.
 */
final class LibraryStructureAccess {

  /**
   * Checks access to a vocabulary-wide structure route.
   */
  public static function access(VocabularyInterface $taxonomy_vocabulary, AccountInterface $account): AccessResultInterface {
    if ($taxonomy_vocabulary->id() !== 'category') {
      return AccessResult::allowed()->addCacheableDependency($taxonomy_vocabulary);
    }
    return AccessResult::allowedIfHasPermission($account, 'administer upload categories')
      ->addCacheableDependency($taxonomy_vocabulary);
  }

}
