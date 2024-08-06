<?php

namespace Drupal\damopen_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a block for DAM roles.
 *
 * @Block(
 *   id = "header_menu_block",
 *   admin_label = @Translation("Admin header menu"),
 * )
 *
 * @package Drupal\damopen_assets_statistics\Plugin\Block
 */
class HeaderMenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get current user roles.
    $current_user = \Drupal::currentUser();
    $roles = $current_user->getRoles();

    $view_own_unpublished = [
      'agency'
    ];
    $menu = [];

    if ($current_user->hasPermission('administer users')) {
      $menu['administer_users'] = [
        'title' => new TranslatableMarkup('Manage users'),
        'url' => Url::fromRoute('entity.user.collection')->toString(),
        'class' => '',
      ];
    }

    if ($current_user->hasPermission('manage uploaded assets')) {
      // Count all unpublished media.
      $query = \Drupal::entityQuery('media')
        ->condition('status', 0)
        ->accessCheck(FALSE);
      $count = $query->count()->execute();
      $menu['manage_assets'] = [
        'title' => new TranslatableMarkup('Assets waiting for approval'),
        'url' => Url::fromRoute('view.unpublished_assets.unpublished_assets')->toString(),
        'class' => '',
        'count' => $count,
      ];
    }

    // Todo: change this to a permission.
    if (array_intersect($roles, $view_own_unpublished)) {
      // Count all unpublished media.
      $query = \Drupal::entityQuery('media')
        ->condition('status', 0)
        ->condition('uid', $current_user->id())
        ->accessCheck(TRUE);
      $count = $query->count()->execute();
      $menu['manage_assets'] = [
        'title' => new TranslatableMarkup('My Assets waiting for approval'),
        'url' => Url::fromRoute('view.unpublished_assets.user_unpublished_assets')->toString(),
        'class' => '',
        'count' => $count,
      ];
    }
    // Add the user menu.
    $menu['user'] = [
      'view' => [
        'title' => new TranslatableMarkup('View profile'),
        'url' => Url::fromRoute('entity.user.canonical', ['user' => $current_user->id()])->toString(),
      ],
      'edit' => [
        'title' => new TranslatableMarkup('Edit profile'),
        'url' => Url::fromRoute('entity.user.edit_form', ['user' => $current_user->id()])->toString(),
      ],
      'logout' => [
        'title' => new TranslatableMarkup('Log out'),
        'url' => Url::fromRoute('user.logout')->toString(),
      ],
    ];
    return [
      '#theme' => 'damopen_common_header_menu',
      '#menu' => $menu,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
