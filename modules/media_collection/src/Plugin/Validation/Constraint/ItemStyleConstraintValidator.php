<?php

namespace Drupal\media_collection\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\damopen_common\Temporary\ImageStyleLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use function array_keys;
use function in_array;

/**
 * Validates the ItemStyleConstraint constraint.
 */
final class ItemStyleConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * ItemStyleConstraintValidator constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Return the allowed style IDs.
   *
   * @return array
   *   List of allowed style IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function allowedStyles(): array {
    return array_keys(ImageStyleLoader::loadImageStylesList($this->entityTypeManager));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    /** @var \Drupal\media_collection\Entity\MediaCollectionInterface $parent */
    /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface $collectionItem */
    $collectionItem = $items->getEntity();
    $media = $collectionItem->media();

    if ($media->bundle() !== 'image' && !$items->isEmpty()) {
      $this->context->addViolation($constraint->isNotAnImage, ['%type' => $media->bundle()]);
      return;
    }

    foreach ($items as $item) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item */
      if (!$item->isEmpty() && !in_array($item->target_id, $this->allowedStyles(), FALSE)) {
        $this->context->addViolation($constraint->isInvalid, ['%value' => $item->target_id]);
      }
    }
  }

}
