<?php declare(strict_types = 1);

namespace Drupal\damopen_assets_library;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Validation\ConstraintManager;
use Drupal\media\MediaInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Damopen Assets Library Helper service.
 */
final class Helper implements HelperInterface {

  /**
   * Constructs a Helper object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ValidatorInterface $fileRecursiveValidator,
    private readonly ConstraintManager $validationConstraint,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MessengerInterface $messenger
  ) {}

  /**
   * @inheritDoc
   */
  public function formMediaUploadBulkUploadFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    if (
      $this->moduleHandler->moduleExists('filehash')
      && $this->configFactory->get('filehash.settings')->get('dedupe')
    ) {
      $form['#validate'][] = [$this, 'mediaUploadBulkUploadValidate'];
    }
  }

  /**
   * @inheritDoc
   */
  public function mediaUploadBulkUploadValidate($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (empty($values['dropzonejs']) || empty($values['dropzonejs']['uploaded_files'])) {
      return;
    }

    $files = $values['dropzonejs']['uploaded_files'];
    $tmpFiles = [];

    /** @var \Drupal\file\FileStorageInterface $fileStorage */
    $fileStorage = \Drupal::entityTypeManager()->getStorage('file');

    foreach ($files as $key => $file) {
      // Set uri for filehash.
      $file['uri'] = $file['path'];
      $tmpFile = $fileStorage->create($file);
      $tmpFiles[$key] = $tmpFile;
    }

    $config = $this->configFactory->get('filehash.settings');
    $options['strict'] = TRUE;
    $options['original'] = $config->get('dedupe_original') ?? FALSE;
    $constraints[] = $this->validationConstraint
      ->create('FileHashDedupe', $options);
    foreach ($tmpFiles as $key => $tmpFile) {
      $fileTypedData = $tmpFile->getTypedData();
      $violations = $this->fileRecursiveValidator
        ->validate($fileTypedData, $constraints);
      foreach ($violations as $violation) {
        unset($files[$key]);
        $this->messenger->addMessage($violation->getMessage(), 'warning');
      }
    }

    $values['dropzonejs']['uploaded_files'] = $files;
    $form_state->setValue('dropzonejs', $values['dropzonejs']);
  }

  /**
   * @inheritDoc
   */
  public function mediaDelete(MediaInterface $entity) {
    $definitions = $entity->getFieldDefinitions();
    foreach ($definitions as $definition) {
      if (in_array($definition->getType(), ['file', 'image'])) {
        $field = $definition->getName();
        $files = $entity->get($field)->referencedEntities();
        foreach ($files as $file) {
          $file->delete();
        }
      }
    }
  }

}
