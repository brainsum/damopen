<?php declare(strict_types = 1);

namespace Drupal\damopen_assets_library;

use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaInterface;

/**
 * Damopen Assets Library Helper service interface.
 */
interface HelperInterface {

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * @param $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param $form_id
   *   The form id.
   */
  public function formMediaUploadBulkUploadFormAlter(&$form, FormStateInterface $form_state, $form_id);

  /**
   * CALLBACK for formMediaUploadBulkUploadFormAlter().
   *
   * @param $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function mediaUploadBulkUploadValidate($form, FormStateInterface $form_state);

  /**
   * Implements hook_entity_ENTITY_TYPE_delete().
   *
   * @param \Drupal\media\MediaInterface $entity
   *   The media entity to delete.
   */
  public function mediaDelete(MediaInterface $entity);

}
