<?php

namespace Drupal\media_collection\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\media_collection\Service\CollectionHandler;

/**
 * Class CollectionItemFormatter.
 *
 * @FieldFormatter(
 *   id = "media_collection_items",
 *   label = @Translation("Collection items"),
 *   description = @Translation("Display the referenced items."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * ) */
class CollectionItemsFormatter extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $entities = parent::getEntitiesToView($items, $langcode);
    usort($entities, [CollectionHandler::class, 'compareItemsByValue']);
    return $entities;
  }

}
