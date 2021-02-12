<?php

namespace Drupal\damopen_common\Helper;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use RuntimeException;

/**
 * Class FieldManager.
 *
 * @package Drupal\damopen_common\Helper
 */
class FieldManager {

  /**
   * Return the upload location for a file field.
   *
   * Returns e.g "private://my-location/folder".
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $parent
   *   Parent entity.
   * @param string $fieldName
   *   Name of the field.
   *
   * @return string
   *   Upload location for the given file field.
   */
  public static function determineUploadLocation(FieldableEntityInterface $parent, string $fieldName): string {
    if (!$parent->hasField($fieldName)) {
      throw new RuntimeException("The {$fieldName} field was not found on the entity.");
    }

    $field = $parent->get($fieldName);
    /** @var \Drupal\file\Plugin\Field\FieldType\FileItem $item */
    $item = $field->isEmpty() ? new FileItem($field->getItemDefinition()) : $field->first();
    return $item->getUploadLocation([$parent->getEntityTypeId() => $parent]);
  }

}
