<?php

namespace Drupal\damopen_assets_library\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\Image;
use function explode;
use function is_file;

/**
 * Provides media type plugin for Document with custom thumbnail.
 *
 * @MediaSource(
 *   id = "documentthumbnail",
 *   label = @Translation("Document thumbnail"),
 *   description = @Translation("Provides business logic and metadata for documents with custom thumbnail."),
 *   allowed_field_types = {"file", "image"},
 *   default_thumbnail_filename = "no-thumbnail.png"
 * )
 *
 * @todo: Test and properly rewrite. Maybe this is not even needed anymore.
 */
class DocumentThumbnail extends Image {

  protected $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    FieldTypePluginManagerInterface $field_type_manager,
    ConfigFactoryInterface $config_factory,
    ImageFactory $image_factory,
    FileSystem $file_system
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory, $image_factory, $file_system);
    $this->config = $this->configFactory->get('media.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'source_field' => '',
      'source_field_thumbnail' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    // Get the file and image data.
    /** @var \Drupal\file\FileInterface $file */
    $file = $media->get($this->configuration['source_field'])->entity;
    // If the source field is not required, it may be empty.
    if (!$file) {
      return parent::getMetadata($media, $name);
    }

    $uri = $file->getFileUri();
    $image = $this->imageFactory->get($uri);
    switch ($name) {
      case static::METADATA_ATTRIBUTE_WIDTH:
        return $image->getWidth() ?: NULL;

      case static::METADATA_ATTRIBUTE_HEIGHT:
        return $image->getHeight() ?: NULL;

      case 'thumbnail_uri':
        return $this->thumbnail($media);
    }

    return parent::getMetadata($media, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function thumbnail(MediaInterface $media) {
    // @todo:
    // modules providing icons should copy the hook_install() and
    // hook_requirements() implementations from the media.install file and
    // change it to copy their own icons to the directory specified by the
    // media.settings:icon_base_uri config setting.
    $sourceField = $this->configuration['source_field'];
    $sourceFieldThumbnail = $this->configuration['source_field_thumbnail'];

    /** @var \Drupal\file\FileInterface $file */
    if ($file = $media->{$sourceFieldThumbnail}->entity) {
      return $file->getFileUri();
    }

    $type = $media->bundle();
    if ($type === 'document') {
      $file = $media->{$sourceField}->entity;

      if ($file) {
        $mimetype = $file->getMimeType();
        $mimetype = explode('/', $mimetype);
        $thumbnail = $this->config->get('icon_base_uri') . "/{$mimetype[0]}-{$mimetype[1]}.png";

        if (!is_file($thumbnail)) {
          $thumbnail = $this->config->get('icon_base_uri') . "/{$mimetype[1]}.png";

          if (!is_file($thumbnail)) {
            $thumbnail = $this->config->get('icon_base_uri') . '/document.png';
          }
        }
      }
      else {
        $thumbnail = $this->config->get('icon_base_uri') . '/document.png';
      }

      return $thumbnail;
    }

    return $this->config->get('icon_base_uri') . '/image.png';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['source_field_thumbnail'] = [
      '#type' => 'select',
      '#title' => $this->t('Thumbnail image source'),
      '#description' => $this->t('Field on media entity that stores thumbnail file. You can create a type without selecting a value for this dropdown initially. This dropdown can be populated after adding fields to the type. If no thumbnail field is set, a mime-type icon will be selected based on the source field file.'),
      '#empty_option' => $this->t('- Select -'),
      '#options' => $this->getSourceFieldOptions(),
      '#default_value' => empty($this->configuration['source_field_thumbnail']) ? NULL : $this->configuration['source_field_thumbnail'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldUpdateThumbnail(MediaInterface $media, MediaInterface $originalMedia = NULL) {
    // If there is no original media entity, then entity is new and thumbnail
    // should be created.
    if ($originalMedia === NULL) {
      return TRUE;
    }

    foreach (['source_field', 'source_field_thumbnail'] as $fieldName) {
      // If source field is changed, then also thumbnail should be changed.
      $sourceField = $this->configuration[$fieldName];

      /** @var \Drupal\file\FileInterface $file */
      $file = $media->{$sourceField}->entity;

      /** @var \Drupal\file\FileInterface $previousFile */
      $previousFile = $originalMedia->{$sourceField}->entity;

      // Xor covers case when source field is deleted (emptied) and when it's
      // filled from previously empty field. That means switching between
      // default icon and thumbnail created from image file.
      if (($file xor $previousFile) || ($file && $previousFile && $file->getFileUri() !== $previousFile->getFileUri())) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
