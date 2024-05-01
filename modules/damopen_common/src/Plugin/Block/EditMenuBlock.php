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
 *   id = "damo_edit_menu_block",
 *   admin_label = @Translation("Admin header menu"),
 * )
 *
 * @package Drupal\damopen_assets_statistics\Plugin\Block
 */
class EditMenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get current user roles.
    $current_user = \Drupal::currentUser();
    $roles = $current_user->getRoles();

    $edit_any_roles = [
      'manager',
      'administrator',
    ];
    // Get Current media id.
    $media_id = \Drupal::routeMatch()->getParameter('media');

    $media_owner = $media_id->getOwner();
    if (array_intersect($roles, $edit_any_roles) ||
        $current_user->id() == 1 ||
        $media_owner->id() == $media_owner) {

      $content['mid'] = $media_id->id();
    }
    $content['status'] = $media_id->status->value;
    return [
      '#theme' => 'damopen_common_edit_menu',
      '#content' => $content,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
