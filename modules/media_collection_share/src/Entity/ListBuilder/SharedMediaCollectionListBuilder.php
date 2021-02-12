<?php

namespace Drupal\media_collection_share\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Media collection (shared) entities.
 *
 * @ingroup media_collection_share
 */
class SharedMediaCollectionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Media collection (shared) ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.shared_media_collection.edit_form',
      ['shared_media_collection' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
