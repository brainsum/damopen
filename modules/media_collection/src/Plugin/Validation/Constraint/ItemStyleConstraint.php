<?php

namespace Drupal\media_collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Plugin implementation of the 'item_style_constraint'.
 *
 * @Constraint(
 *   id = "item_style_constraint",
 *   label = @Translation("Item style constraint", context = "Validation"),
 * )
 */
class ItemStyleConstraint extends Constraint {

  public $isInvalid = 'The %value style cannot be referenced.';

  public $isNotAnImage = 'Styles cannot be used with %type media entities.';

}
