<?php

namespace Drupal\media_collection\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Media collection item entities.
 *
 * @ingroup media_collection
 */
class MediaCollectionItemListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // @todo: Media Collection Item list should show the collection for the item.
    // @todo: Display the media name as the collection item name.
    // @todo: Display the style name.
    $header['id'] = $this->t('Media collection item ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\media_collection\Entity\MediaCollectionItem $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->id(),
      'entity.media_collection_item.edit_form',
      ['media_collection_item' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
