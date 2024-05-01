<?php

namespace Drupal\damopen_common\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a confirmation form to confirm deletion of something by id.
 */
class DamoPublishMediaForm extends ConfirmFormBase {

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
    $form = parent::buildForm($form, $form_state);
    $form['description'] = [
      '#markup' => $this->getQuestion()
    ];
    $form['actions']['submit']['#value'] = $this->t('Publish');
    // Return the user to the media page.
    $form['actions']['submit']['#url'] = $this->getCancelUrl();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Publidh the selected media.
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($this->id);
    $media->setPublished();
    $media->save();
    // Set redirect to media page.
    $form_state->setRedirectUrl($this->getCancelUrl());
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "confirm_publish_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // path to current media.
    return new Url('entity.media.canonical', ['media' => $this->id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    // Load media by id.
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($this->id);
    // Get media title.
    $title = $media->label();
    return $this->t('Do you want to publish %title?', ['%title' => $title]);
  }

}
