<?php

namespace Drupal\damopen_assets_api\Field;

use Drupal;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use RuntimeException;
use function file_url_transform_relative;
use function strpos;

/**
 * Field definition to provide relative image style url for file entities.
 *
 * For 'image/*' filemime return relative image style url of the uri.
 */
class ImageStyleDownloadUrl extends FieldItemList {

  /**
   * Image style name.
   *
   * @todo: Configurable.
   */
  public const IMAGE_STYLE = 'medium';

  /**
   * Image style storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * File URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function createInstance($definition, $name = NULL, TraversableTypedDataInterface $parent = NULL) {
    $instance = parent::createInstance($definition, $name, $parent);
    $instance->imageStyleStorage = Drupal::entityTypeManager()
      ->getStorage('image_style');
    $instance->fileUrlGenerator = Drupal::service('file_url_generator');

    return $instance;
  }

  /**
   * Creates a relative thumbnail image style URL from file's URI.
   *
   * @param string $uri
   *   The URI to transform.
   *
   * @return string
   *   The transformed relative URL.
   */
  protected function fileCreateThumbnailUrl($uri): string {
    $style = $this->imageStyleStorage->load(self::IMAGE_STYLE);

    if ($style === NULL) {
      throw new RuntimeException('The "' . self::IMAGE_STYLE . '" image style cannot be loaded.');
    }

    $url = $style->buildUrl($uri);
    return $this->fileUrlGenerator->generateString($url);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->initList();
    return parent::getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $this->getEntity()
      ->get('uri')
      ->access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return $this->getEntity()->get('uri')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    $this->initList();

    return parent::getIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    $this->initList();

    return parent::get($index);
  }

  /**
   * Initialize the internal field list with the modified items.
   */
  protected function initList() {
    if ($this->list) {
      return;
    }
    $url_list = [];
    foreach ($this->getEntity()->get('uri') as $delta => $uri_item) {
      if (FALSE !== strpos($this->getEntity()->get('filemime')[$delta]->value, 'image')) {
        $path = $this->fileCreateThumbnailUrl($uri_item->value);
        $url_list[$delta] = $this->createItem($delta, $path);
      }
    }
    $this->list = $url_list;
  }

}
