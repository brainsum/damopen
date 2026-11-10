<?php

namespace Drupal\damopen_category_upload\Plugin\Validation\Constraint;

use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates upload categories, including JSON:API writes.
 */
#[Constraint(id: 'CategoryUpload', label: new TranslatableMarkup('Category upload scope'), type: 'entity:media')]
final class CategoryUploadConstraint extends SymfonyConstraint {}
