<?php

namespace Drupal\media_collection\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Media collection item entity.
 *
 * @ingroup media_collection
 *
 * @ContentEntityType(
 *   id = "media_collection_item",
 *   label = @Translation("Media collection item"),
 *   handlers = {
 *     "view_builder" = "Drupal\media_collection\Entity\ViewBuilder\MediaCollectionItemViewBuilder",
 *     "list_builder" = "Drupal\media_collection\Entity\ListBuilder\MediaCollectionItemListBuilder",
 *     "views_data" = "Drupal\media_collection\Entity\ViewsData\MediaCollectionItemViewsData",
 *     "translation" = "Drupal\media_collection\Entity\Translation\MediaCollectionItemTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\media_collection\Form\MediaCollectionItemForm",
 *       "add" = "Drupal\media_collection\Form\MediaCollectionItemForm",
 *       "edit" = "Drupal\media_collection\Form\MediaCollectionItemForm",
 *       "delete" = "Drupal\media_collection\Form\MediaCollectionItemDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\media_collection\Entity\Routing\MediaCollectionItemHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\media_collection\Entity\Access\MediaCollectionItemAccessControlHandler",
 *   },
 *   base_table = "media_collection_item",
 *   data_table = "media_collection_item_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer media collection item entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/media_collection_item/{media_collection_item}",
 *     "add-form" = "/admin/structure/media_collection_item/add",
 *     "edit-form" = "/admin/structure/media_collection_item/{media_collection_item}/edit",
 *     "delete-form" = "/admin/structure/media_collection_item/{media_collection_item}/delete",
 *     "collection" = "/admin/structure/media_collection_item",
 *   },
 *   field_ui_base_route = "media_collection_item.settings"
 * )
 *
 * @todo: Implement the EntityCreatedInterface when added to core.
 * @see: https://www.drupal.org/project/drupal/issues/2833378
 */
class MediaCollectionItem extends ContentEntityBase implements MediaCollectionItemInterface {

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
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields[$entity_type->getKey('owner')]
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
      ->setDescription(new TranslatableMarkup('The time that the entity was created.'));

    $fields['media'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Media entity'))
      ->setDescription(new TranslatableMarkup('Referenced media entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setCardinality(1)
      ->setRequired(TRUE)
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

    $fields['style'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Image style'))
      ->setDescription(new TranslatableMarkup('Referenced image style.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'image_style')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setCardinality(1)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 5,
      ])
      ->addConstraint('item_style_constraint', [])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // @todo: Consider making "media_collection" bundleable, so we don't
    // need to add another parent for "shared_media_collection"
    // in the submodule.
    $fields['parent'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Parent'))
      ->setDescription(new TranslatableMarkup('Parent collection of the item.'))
      ->setSetting('target_type', 'media_collection')
      ->setSetting('handler', 'default')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setCardinality(1)
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

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function media(): MediaInterface {
    return $this->get('media')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setMedia(MediaInterface $media): MediaCollectionItemInterface {
    $this->set('media', $media);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function style(): ?ImageStyleInterface {
    return $this->get('style')->entity ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setStyle(ImageStyleInterface $style): MediaCollectionItemInterface {
    $this->set('style', $style);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function parent(): MediaCollectionInterface {
    return $this->get('parent')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setParent(MediaCollectionInterface $parent): MediaCollectionItemInterface {
    $this->set('parent', $parent);
    return $this;
  }

}
