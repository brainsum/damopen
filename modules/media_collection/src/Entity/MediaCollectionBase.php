<?php

namespace Drupal\media_collection\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\user\EntityOwnerTrait;
use function count;

/**
 * Class MediaCollectionBase.
 *
 * @package Drupal\media_collection\Entity
 */
abstract class MediaCollectionBase extends ContentEntityBase implements MediaCollectionInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Returns the "items" field.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemListInterface
   *   The "items" field.
   */
  private function itemsField(): EntityReferenceFieldItemListInterface {
    return $this->get($this->getEntityType()->getKey('items'));
  }

  /**
   * {@inheritdoc}
   */
  public function items(): array {
    $items = [];

    foreach ($this->itemsField() as $field) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $field */
      /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface|null $item */
      if ($item = $field->entity) {
        $items[] = $item;
      }
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function itemCount(): int {
    // @todo: Debug why "$this->itemsField()->count()" doesn't return the actual count.
    return count($this->items());
  }

  /**
   * {@inheritdoc}
   */
  public function hasItem(MediaCollectionItemInterface $item): bool {
    return $this->itemIndex($item) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function itemIndex(MediaCollectionItemInterface $item): ?int {
    // @todo: Merge with CollectionHandler::itemWithGivenEntities().
    $newMedia = $item->media();
    $newStyle = $item->style();

    /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface $item */
    foreach ($this->items() as $index => $existingItem) {
      if ($existingItem->media()->id() !== $newMedia->id()) {
        continue;
      }

      if ($newStyle === NULL) {
        return $index;
      }

      if (
        $existingItem->style() === NULL
        || $existingItem->style()->id() !== $newStyle->id()
      ) {
        continue;
      }

      return $index;
    }

    return NULL;

  }

  /**
   * {@inheritdoc}
   */
  public function setItems(array $items): MediaCollectionInterface {
    $this->itemsField()->setValue($items);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addItem(MediaCollectionItemInterface $item): MediaCollectionInterface {
    $this->itemsField()->appendItem($item);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem(MediaCollectionItemInterface $item): MediaCollectionInterface {
    if ($this->itemCount() <= 0) {
      return $this;
    }

    // Protect against the edge-case where the same item is
    // added multiple times. Remove re-indexes the items array, so we have
    // to get indexes one-by-one.
    $index = $this->itemIndex($item);
    while ($index !== NULL) {
      $this->itemsField()->removeItem($index);
      $index = $this->itemIndex($item);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setArchive(FileInterface $file): MediaCollectionInterface {
    $this->set('assets_archive', $file);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function archiveFile(): FileInterface {
    return $this->get('assets_archive')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    $fields = parent::baseFieldDefinitions($entityType);
    $fields += static::ownerBaseFieldDefinitions($entityType);

    $fields[$entityType->getKey('owner')]
      ->setLabel(new TranslatableMarkup('User'))
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('The time that the entity was created.'))
      ->setRevisionable(TRUE);

    $fields[$entityType->getKey('items')] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Items'))
      ->setDescription(new TranslatableMarkup('Items that belong to this collection.'))
      ->setSetting('target_type', 'media_collection_item')
      ->setSetting('handler', 'default')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setCardinality(128)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['assets_archive'] = BaseFieldDefinition::create('file')
      ->setCardinality(1)
      ->setLabel(new TranslatableMarkup('Archived assets'))
      ->setDescription(new TranslatableMarkup('Field holding the download file for all assets'))
      ->setSetting('file_extensions', 'zip')
      ->setSetting('uri_scheme', 'private')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'file_url_plain',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRevisionable(TRUE);

    return $fields;
  }

}
