<?php

namespace Drupal\media_collection_share\Entity;

use Drupal;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\media_collection\Entity\MediaCollectionBase;
use function trim;

/**
 * Defines the Media collection (shared) entity.
 *
 * @ingroup media_collection_share
 *
 * @ContentEntityType(
 *   id = "shared_media_collection",
 *   label = @Translation("Media collection (shared)"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\media_collection_share\Entity\ListBuilder\SharedMediaCollectionListBuilder",
 *     "views_data" = "Drupal\media_collection_share\Entity\ViewsData\SharedMediaCollectionViewsData",
 *     "translation" = "Drupal\media_collection_share\Entity\Translation\SharedMediaCollectionTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\media_collection_share\Form\SharedMediaCollectionForm",
 *       "add" = "Drupal\media_collection_share\Form\SharedMediaCollectionForm",
 *       "edit" = "Drupal\media_collection_share\Form\SharedMediaCollectionForm",
 *       "delete" = "Drupal\media_collection_share\Form\SharedMediaCollectionDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\media_collection_share\Entity\Routing\SharedMediaCollectionHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\media_collection_share\Entity\Access\SharedMediaCollectionAccessControlHandler",
 *   },
 *   base_table = "shared_media_collection",
 *   data_table = "shared_media_collection_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer media collection (shared) entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "owner" = "uid",
 *     "items" = "items",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/shared_media_collection/{shared_media_collection}",
 *     "add-form" = "/admin/structure/shared_media_collection/add",
 *     "edit-form" = "/admin/structure/shared_media_collection/{shared_media_collection}/edit",
 *     "delete-form" = "/admin/structure/shared_media_collection/{shared_media_collection}/delete",
 *     "collection" = "/admin/structure/shared_media_collection",
 *   },
 *   field_ui_base_route = "shared_media_collection.settings"
 * )
 */
class SharedMediaCollection extends MediaCollectionBase implements SharedMediaCollectionInterface {

  /**
   * The email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * Returns the email validator.
   *
   * @return \Drupal\Component\Utility\EmailValidatorInterface
   *   The email validator.
   */
  protected function emailValidator(): EmailValidatorInterface {
    if ($this->emailValidator === NULL) {
      $this->emailValidator = Drupal::service('email.validator');
    }

    return $this->emailValidator;
  }

  /**
   * {@inheritdoc}
   */
  public function setShareUrl(string $url): SharedMediaCollectionInterface {
    $this->set('url', $url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function shareUrl(): string {
    return (string) $this->get('url')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function shareAbsoluteUrl(): string {
    return Url::fromUserInput(
      $this->shareUrl(),
      [
        'absolute' => TRUE,
      ]
    )->toString(TRUE)->getGeneratedUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function emailCount(): int {
    return $this->get('emails')->count();
  }

  /**
   * {@inheritdoc}
   */
  public function emails(): array {
    $emails = [];

    foreach ($this->get('emails') as $field) {
      $emails[] = $field->value;
    }

    return $emails;
  }

  /**
   * {@inheritdoc}
   */
  public function addEmail(string $email): SharedMediaCollectionInterface {
    $email = trim($email);

    if (
      $this->emailValidator()->isValid($email)
      && !in_array($email, $this->emails(), TRUE)
    ) {
      $this->get('emails')->appendItem($email);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addEmails(array $emails): SharedMediaCollectionInterface {
    foreach ($emails as $email) {
      $this->addEmail($email);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmails(array $emails): SharedMediaCollectionInterface {
    $this->set('emails', []);
    $this->addEmails($emails);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Share URL'))
      ->setDescription(new TranslatableMarkup('The URL that can be shared with others'))
      ->setSetting('max_length', '2048')
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['emails'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Shared with'))
      ->setDescription(new TranslatableMarkup('Emails with which the collection has been shared directly.'))
      ->setCardinality(50)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRevisionable(TRUE);

    $fields['assets_archive']->setSetting(
      'file_directory',
      'collection/shared/[date:custom:Y]-[date:custom:m]-[date:custom:d]/[shared_media_collection:uuid]'
    );

    return $fields;
  }

}
