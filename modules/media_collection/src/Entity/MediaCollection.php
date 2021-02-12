<?php

namespace Drupal\media_collection\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Media collection entity.
 *
 * @ingroup media_collection
 *
 * @ContentEntityType(
 *   id = "media_collection",
 *   label = @Translation("Media collection"),
 *   handlers = {
 *     "view_builder" = "Drupal\media_collection\Entity\ViewBuilder\MediaCollectionViewBuilder",
 *     "list_builder" = "Drupal\media_collection\Entity\ListBuilder\MediaCollectionListBuilder",
 *     "views_data" = "Drupal\media_collection\Entity\ViewsData\MediaCollectionViewsData",
 *     "translation" = "Drupal\media_collection\Entity\Translation\MediaCollectionTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\media_collection\Form\MediaCollectionForm",
 *       "add" = "Drupal\media_collection\Form\MediaCollectionForm",
 *       "edit" = "Drupal\media_collection\Form\MediaCollectionForm",
 *       "delete" = "Drupal\media_collection\Form\MediaCollectionDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\media_collection\Entity\Routing\MediaCollectionHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\media_collection\Entity\Access\MediaCollectionAccessControlHandler",
 *   },
 *   base_table = "media_collection",
 *   data_table = "media_collection_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer media collection entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *     "owner" = "uid",
 *     "items" = "items",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/media_collection/{media_collection}",
 *     "add-form" = "/admin/structure/media_collection/add",
 *     "edit-form" = "/admin/structure/media_collection/{media_collection}/edit",
 *     "delete-form" = "/admin/structure/media_collection/{media_collection}/delete",
 *     "collection" = "/admin/structure/media_collection",
 *   },
 *   field_ui_base_route = "media_collection.settings"
 * )
 */
class MediaCollection extends MediaCollectionBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    $fields = parent::baseFieldDefinitions($entityType);

    $fields['assets_archive']->setSetting(
      'file_directory',
      'collection/standard/[date:custom:Y]-[date:custom:m]-[date:custom:d]/[media_collection:uuid]'
    );

    return $fields;
  }

}
