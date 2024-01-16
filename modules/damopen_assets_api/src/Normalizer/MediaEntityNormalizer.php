<?php

namespace Drupal\damopen_assets_api\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\media\MediaInterface;
use Drupal\damopen_common\Temporary\ImageStyleLoader;
use Drupal\serialization\Normalizer\ContentEntityNormalizer;
use function count;
use function in_array;

/**
 * Converts the Drupal entity object structures to a normalized array.
 */
class MediaEntityNormalizer extends ContentEntityNormalizer {

  /**
   * File storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  private $fileStorage;

  /**
   * Image style storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  private $imageStyleStorage;

  /**
   * Taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  private $termStorage;

  /**
   * Array of image styles.
   *
   * @var \Drupal\image\ImageStyleInterface[]
   */
  private $imageStyleList;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeRepositoryInterface $entityTypeRepository,
    EntityFieldManagerInterface $entityFieldManager
  ) {
    parent::__construct(
      $entityTypeManager,
      $entityTypeRepository,
      $entityFieldManager
    );

    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->imageStyleStorage = $entityTypeManager->getStorage('image_style');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');

    $this->supportedInterfaceOrClass = MediaInterface::class;
  }

  /**
   * The white listed field list.
   *
   * @var array
   */
  private const FIELD_WHITELIST = [
    'mid',
    'name',
    'thumbnail',
    'field_category',
    'field_keywords',
  ];

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    $attributes = [];
    /** @var \Drupal\Core\Field\FieldItemListInterface $field */
    foreach ($object as $name => $field) {
      if (!$field->access('view', $context['account'] ?? NULL)) {
        continue;
      }

      if (!in_array($field->getName(), static::FIELD_WHITELIST, FALSE)) {
        continue;
      }

      if ($name === 'thumbnail') {
        $attributes[$name] = $this->normalizeThumbnailFieldItemList($field);
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->fileStorage->load($field->first()->getEntity()->get('thumbnail')->target_id);

        foreach ($this->fetchImageStyleList() as $key => $style) {
          $attributes['assets'][$key] = $style->buildUrl($file->getFileUri());
        }
      }
      elseif (in_array($name, ['field_category', 'field_keywords'])) {
        $attributes[$name] = $this->normalizeTaxonomyFieldItemList($field);
      }
      else {
        if ($name === 'mid') {
          $name = 'media_id';
        }
        $attributes[$name] = $this->serializer->normalize($field, $format, $context);
      }

      // Override/exclude normalized data.
      if (isset($attributes[$name][0]['value']) && count($attributes[$name][0]) === 1) {
        $attributes[$name] = $attributes[$name][0]['value'];
      }
    }

    return $attributes;
  }

  /**
   * Array of image styles.
   *
   * @return \Drupal\image\ImageStyleInterface[]
   *   The image style list.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo: Temporary.
   */
  protected function fetchImageStyleList(): array {
    if (empty($this->imageStyleList)) {
      $this->imageStyleList = ImageStyleLoader::loadImageStylesList($this->entityTypeManager);
    }

    return $this->imageStyleList;
  }

  /**
   * Custom normalization.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   *
   * @return array
   *   Normalized field.
   */
  private function normalizeThumbnailFieldItemList(FieldItemListInterface $items): array {
    $normalized = [];

    foreach ($items as $item) {
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->fileStorage->load($item->getEntity()->get('thumbnail')->target_id);
      if ($file) {
        /** @var \Drupal\image\ImageStyleInterface $thumbnail */
        $thumbnail = $this->imageStyleStorage->load('thumbnail');
        $normalized['alt'] = $item->alt;
        $normalized['title'] = $item->title;
        $normalized['url'] = $thumbnail->buildUrl($file->getFileUri());
      }
    }

    return $normalized;
  }

  /**
   * Custom normalization.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   *
   * @return array
   *   Normalized field.
   */
  private function normalizeTaxonomyFieldItemList(FieldItemListInterface $items): array {
    $normalized = [];

    foreach ($items as $key => $item) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $this->termStorage->load($item->getValue()['target_id']);
      if ($term) {
        $normalized[$key] = [$term->getName()];
      }
    }

    return $normalized;
  }

}
