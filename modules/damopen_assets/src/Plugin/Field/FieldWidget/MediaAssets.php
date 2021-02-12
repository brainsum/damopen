<?php

namespace Drupal\damopen_assets\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'damopen_assets' widget.
 *
 * @FieldWidget(
 *   id = "damopen_assets",
 *   label = @Translation("Media assets"),
 *   field_types = {
 *     "damopen_assets"
 *   }
 * )
 */
class MediaAssets extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['value'] = $element;

    return $element;
  }

}
