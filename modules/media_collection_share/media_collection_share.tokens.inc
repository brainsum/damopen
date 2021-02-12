<?php

/**
 * @file
 * Tokens for shared media collections.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Implements hook_token_info().
 */
function media_collection_share_token_info() {
  $type = [
    'name' => new TranslatableMarkup('Shared media collection'),
    'description' => new TranslatableMarkup('Tokens for shared media collections'),
    'needs-data' => 'shared_media_collection',
  ];

  $tokens = [];
  $tokens['uuid'] = [
    'name' => new TranslatableMarkup('Shared collection UUID'),
    'description' => new TranslatableMarkup('The UUID of the given shared collection'),
    'type' => 'shared_media_collection',
  ];

  return [
    'types' => [
      'shared_media_collection' => $type,
    ],
    'tokens' => [
      'shared_media_collection' => $tokens,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function media_collection_share_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type === 'shared_media_collection' && !empty($data['shared_media_collection'])) {
    /** @var \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $sharedCollection */
    $sharedCollection = $data['shared_media_collection'];

    foreach ($tokens as $name => $original) {
      if ($name === 'uuid') {
        $replacements[$original] = $sharedCollection->uuid();
        continue;
      }
    }
  }

  return $replacements;
}
