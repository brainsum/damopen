<?php

namespace Drupal\media_collection_share\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CollectionShareModalForm.
 */
class MediaCollectionShare extends ContentEntityForm {

  /**
   * The shared collection storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $userStorage;

  /**
   * Constructs a new MediaCollectionForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_collection_share__collection_share_with_users_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['status_messages_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'share-modal-form-status-messages-wrapper',
      ],
      '#weight' => -999,
      'status_messages' => [
        '#type' => 'status_messages',
        '#attributes' => [
          'id' => 'share-modal-form-status-messages',
        ],
      ],
    ];

    unset($form['actions']);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::save'],
      '#ajax' => [
        'callback' => '::ajaxUpdate',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $users = $form_state->getValue('shared_with');
    $this->entity->set('shared_with', $users['target_id'] ?? $users);
    $this->entity->save();
    $this->messenger()->addStatus($this->t('List of users updated.'));
  }

  /**
   * Close the modal dialog.
   */
  public function ajaxUpdate(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#share-modal-form-status-messages-wrapper', $form['status_messages_wrapper']));
    return $response;
  }
}
