<?php

namespace Drupal\damo_extended_collection\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines a confirmation form to confirm deletion of something by id.
 */
class EditCollectionForm extends FormBase {

  /**
   * ID of the item to delete.
   *
   * @var int
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $id = NULL) {
    $this->id = $id;
    $collection = \Drupal::entityTypeManager()->getStorage('media_collection')->load($id);
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#size' => 60,
      '#maxlength' => 128,
      '#default_value' => $collection->get('field_title')->value,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $collection = \Drupal::entityTypeManager()->getStorage('media_collection')->load($this->id);

    $collection->set('field_title', $form_state->getValue('title'));
    $collection->save();

    $form_state->setRedirect('view.collection_view_page.collection_view', ['arg_0' => $this->id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "collection_edit_form";
  }

}
