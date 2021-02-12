<?php

namespace Drupal\media_collection\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Media collection entities.
 *
 * @ingroup media_collection
 */
class MediaCollectionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Media collection ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\media_collection\Entity\MediaCollection $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->id(),
      'entity.media_collection.edit_form',
      ['media_collection' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
