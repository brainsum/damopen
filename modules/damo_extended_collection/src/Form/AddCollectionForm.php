<?php

namespace Drupal\damo_extended_collection\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements a codimth Simple Form API.
 */
class AddCollectionForm extends FormBase {

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Textfield.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#size' => 60,
      '#maxlength' => 128,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * @return string
   */
  public function getFormId() {
    return 'extended_collection_add';
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  // public function validateForm(array &$form, FormStateInterface $form_state) {
  //   $title = $form_state->getValue('title');
  // }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //Create media collection.
    $media_collection = \Drupal::entityTypeManager()->getStorage('media_collection')->create([
      'field_title' => $form_state->getValue('title'),
      'uid' => \Drupal::currentUser()->id(),
    ]);
    $media_collection->save();
    $form_state->setRedirect('view.collections.collections_page');
    // Set success message drupal8.
    \Drupal::messenger()->addStatus($this->t('Collection: <b>@title</b> has been created.', ['@title' => $form_state->getValue('title')]));
  }

}
