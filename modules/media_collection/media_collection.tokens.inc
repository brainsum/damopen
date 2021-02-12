<?php

/**
 * @file
 * Tokens for media collections.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Implements hook_token_info().
 */
function media_collection_token_info() {
  $type = [
    'name' => new TranslatableMarkup('Media collection'),
    'description' => new TranslatableMarkup('Tokens for media collections'),
    'needs-data' => 'media_collection',
  ];

  $tokens = [];
  $tokens['uuid'] = [
    'name' => new TranslatableMarkup('Collection UUID'),
    'description' => new TranslatableMarkup('The UUID of the given collection'),
    'type' => 'media_collection',
  ];

  return [
    'types' => [
      'media_collection' => $type,
    ],
    'tokens' => [
      'media_collection' => $tokens,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function media_collection_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type === 'media_collection' && !empty($data['media_collection'])) {
    /** @var \Drupal\media_collection\Entity\MediaCollectionInterface $collection */
    $collection = $data['media_collection'];

    foreach ($tokens as $name => $original) {
      if ($name === 'uuid') {
        $replacements[$original] = $collection->uuid();
        continue;
      }
    }
  }

  return $replacements;
}
