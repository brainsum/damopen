<?php

namespace Drupal\damopen_assets\Form;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_upload\Form\BulkMediaUploadForm as ContribForm;
use Drupal\taxonomy\TermInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function array_map;
use function explode;
use function file_get_contents;
use function file_save_data;
use function in_array;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Customized version of the form.
 *
 * @see \Drupal\media_upload\Form\BulkMediaUploadForm
 * @package Drupal\damopen_assets\Form
 *
 */
class BulkMediaUploadForm extends ContribForm {

  /**
   * Term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->termStorage = $container->get('entity_type.manager')
      ->getStorage('taxonomy_term');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    MediaTypeInterface $type = NULL
  ) {
    $form = parent::buildForm($form, $form_state, $type);

    // @todo: Load additional fields from type configs, don't hardcode.
    // @todo: Use same widget as on the type form.
    $form['keywords'] = [
      '#type' => 'select2',
      '#title' => $this->t('Keywords'),
      '#description' => $this->t('Drag to re-order keywords.'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['keyword'],
        'auto_create' => TRUE,
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
      ],
      '#autocomplete' => TRUE,
      '#multiple' => TRUE,
      '#autocreate' => [
        'bundle' => 'keyword',
        'uid' => $this->currentUser()->id(),
      ],
    ];

    if ($type !== NULL && $type->id() === 'image') {
      $form['category'] = [
        '#type' => 'select',
        '#title' => $this->t('Category'),
        '#options' => array_map(static function (TermInterface $term) {
          return $term->label();
        }, $this->termStorage->loadByProperties(['vid' => 'category'])),
        '#multiple' => TRUE,
        '#required' => TRUE,
      ];

      $form['asset_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#size' => 60,
        '#maxlength' => 128,
        '#required' => TRUE,
      ];
    }

    // @todo: Add proper api to the media_upload module.
    // Push submit button to the end of the form.
    $submit = $form['submit'];
    unset($form['submit']);
    $form['submit'] = $submit;

    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $errorFlag = FALSE;
      $fileCount = 0;
      $createdMedia = [];
      $values = $form_state->getValues();

      if (empty($values['dropzonejs']) || empty($values['dropzonejs']['uploaded_files'])) {
        $this->logger->warning('No documents were uploaded');
        $this->messenger()
          ->addMessage($this->t('No documents were uploaded'), 'warning');
        return;
      }

      /** @var array $files */
      $files = $values['dropzonejs']['uploaded_files'];

      $typeId = $values['media_type'];
      $targetFieldSettings = $this->getTargetFieldSettings($typeId);
      // Prepare destination. Patterned on protected method
      // FileItem::doGetUploadLocation and public method
      // FileItem::generateSampleValue.
      $fileDirectory = trim($targetFieldSettings['file_directory'], '/');
      // Replace tokens. As the tokens might contain HTML we convert
      // it to plain text.
      $fileDirectory = PlainTextOutput::renderFromHtml(
        $this->token->replace($fileDirectory)
      );
      $targetDirectory = $targetFieldSettings['uri_scheme'] . '://' . $fileDirectory;
      $this->fileSystem->prepareDirectory($targetDirectory, FileSystemInterface::CREATE_DIRECTORY);

      /** @var array $file */
      foreach ($files as $file) {
        $fileInfo = [];
        if (preg_match(static::FILENAME_REGEX, $file['filename'], $fileInfo) !== 1) {
          $errorFlag = TRUE;
          $this->logger->warning('@filename - Incorrect file name', ['@filename' => $file['filename']]);
          $this->messenger()
            ->addMessage($this->t('@filename - Incorrect file name', ['@filename' => $file['filename']]), 'warning');
          continue;
        }

        // @todo: Not sure if strtolower() is the best approach.
        if (!in_array(
          strtolower($fileInfo[static::EXT_NAME]),
          explode(' ', strtolower($targetFieldSettings['file_extensions'])),
          FALSE
        )) {
          $errorFlag = TRUE;
          $this->logger->error('@filename - File extension is not allowed', ['@filename' => $file['filename']]);
          $this->messenger()
            ->addMessage($this->t('@filename - File extension is not allowed', ['@filename' => $file['filename']]), 'error');
          continue;
        }

        $destination = $targetDirectory . '/' . $file['filename'];
        $data = file_get_contents($file['path']);
        $fileEntity = $this->fileRepository->writeData($data, $destination);

        if (FALSE === $fileEntity) {
          $errorFlag = TRUE;
          $this->logger->warning('@filename - File could not be saved.', [
            '@filename' => $file['filename'],
          ]);
          $this->messenger()
            ->addMessage('@filename - File could not be saved.', [
              '@filename' => $file['filename'],
            ], 'warning');
          continue;
        }

        $mediaValues = $this->getNewMediaValues($typeId, $fileInfo, $fileEntity);
        $mediaValues['field_category'] = $values['category'];
        $mediaValues['field_keywords'] = $values['keywords'];
        if ($typeId === 'image') {
          $mediaValues['name'] = $values['asset_name'];
        }

        $media = $this->mediaStorage->create($mediaValues);
        $media->save();
        $createdMedia[] = $media;
        $fileCount++;
      }

      $form_state->set('created_media', $createdMedia);
      if ($errorFlag && !$fileCount) {
        $this->logger->warning('No documents were uploaded');
        $this->messenger()
          ->addMessage($this->t('No documents were uploaded'), 'warning');
        return;
      }

      if ($errorFlag) {
        $this->logger->info('Some documents have not been uploaded');
        $this->messenger()
          ->addMessage($this->t('Some documents have not been uploaded'), 'warning');
        $this->logger->info('@fileCount documents have been uploaded', ['@fileCount' => $fileCount]);
        $this->messenger()
          ->addMessage($this->t('@fileCount documents have been uploaded', ['@fileCount' => $fileCount]));
        return;
      }

      $this->logger->info('@fileCount documents have been uploaded', ['@fileCount' => $fileCount]);
      $this->messenger()
        ->addMessage($this->t('@fileCount documents have been uploaded', ['@fileCount' => $fileCount]));
      return;
    }
    catch (Exception $e) {
      $this->logger->critical($e->getMessage());
      $this->messenger()->addMessage($e->getMessage(), 'error');

      return;
    }
  }

}
