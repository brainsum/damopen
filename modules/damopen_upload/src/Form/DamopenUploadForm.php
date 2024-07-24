<?php

namespace Drupal\damopen_upload\Form;

/**
 * @file
 * Contains \Drupal\damopen_upload\Form\DamopenUploadForm.
 */

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Class DamopenUploadForm Provides a form for uploading files.
 *
 * @package Drupal\damopen_upload\Form
 */
class DamopenUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'student_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['file'] = [
      '#type' => 'dropzonejs',
      '#title' => $this->t('File'),
      '#description' => $this->t('Choose a file to upload.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the uploaded file.
    $files = $form_state->getValue('file');
    // Generate unique id.
    $upload_id = \Drupal::service('uuid')->generate();
    foreach ($files['uploaded_files'] as $file) {
      $file = File::create([
        'uri' => $file['path'],
        'status' => 0,
      ]);
      $file->set('field_upload_id', $upload_id);
      dpm($upload_id);
      $file->save();
    }
    // Redirect to the file listing page.
    $form_state->setRedirect('damopen_upload.upload', ['upload_id' => $upload_id]);
  }

}
