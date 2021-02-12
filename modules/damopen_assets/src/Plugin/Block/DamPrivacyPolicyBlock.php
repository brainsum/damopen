<?php

namespace Drupal\damopen_assets\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Class DamPrivacyPolicyBlock.
 *
 * Provides a block with the Privacy Policy link.
 *
 * @Block(
 *   id="dam_privacy_policy_block",
 *   admin_label=@Translation("DAM Privacy Policy"),
 *   category=@Translation("DAM")
 * )
 *
 * @package Drupal\damopen_assets\Plugin\Block
 */
class DamPrivacyPolicyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // @todo: Configurable link.
    $build = [
      'privacy_policy' => [
        '#type' => 'link',
        '#url' => Url::fromUri('/privacy-policy'),
        '#title' => t('Privacy Policy'),
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // @todo: drupal/userprotect adds this permission.
    // Protect block access with permission check.
    if ($account->hasPermission('userprotect.account.edit')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
