<?php

// Create a drupal 9 block class.
namespace Drupal\damo_extended_collection\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'CreateCollectionBlock' block.
 *
 * @Block(
 *  id = "create_collection_block",
 *  admin_label = @Translation("Create collection block"),
 * )
 */
class CreateCollectionBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get url by route name.
    $url = \Drupal::urlGenerator()->generateFromRoute('damo_extended_collection.add');
    return [
      '#theme' => 'create_collection',
      '#url' => $url,
    ];
  }

}
