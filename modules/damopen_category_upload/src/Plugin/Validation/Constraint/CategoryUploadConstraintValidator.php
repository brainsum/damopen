<?php

namespace Drupal\damopen_category_upload\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Makes invalid API writes return validation errors before saving any media.
 */
final class CategoryUploadConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if ($error = \Drupal::service('damopen_category_upload.policy')->violation($value)) {
      $this->context->buildViolation($error)->atPath('field_category')->addViolation();
    }
  }

}
