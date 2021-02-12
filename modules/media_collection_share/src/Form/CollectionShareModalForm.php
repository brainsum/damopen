<?php

namespace Drupal\media_collection_share\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_collection_share\Service\CollectionSharer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function array_diff;
use function array_filter;
use function array_intersect;
use function array_unique;
use function count;
use function explode;
use function implode;

/**
 * Class CollectionShareModalForm.
 *
 * @package Drupal\media_collection_share\Form
 */
class CollectionShareModalForm extends FormBase {

  private $sharedCollection;
  private $emailValidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_collection_share.collection_sharer'),
      $container->get('email.validator')
    );
  }

  /**
   * CollectionShareModalForm constructor.
   *
   * @param \Drupal\media_collection_share\Service\CollectionSharer $collectionSharer
   *   Collection sharer service.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $emailValidator
   *   Email validator.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function __construct(
    CollectionSharer $collectionSharer,
    EmailValidatorInterface $emailValidator
  ) {
    $this->sharedCollection = $collectionSharer->createSharedCollectionForUser($this->currentUser()->id());
    $this->emailValidator = $emailValidator;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_collection_share__collection_share_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @todo
     *
     * Display:
     * - Display existing shared mails.
     *
     * Submit:
     * - Validate emails (comma separated list with valid mails).
     */
    $form['#attached']['library'][] = 'core/drupal.ajax';

    $form['status_messages_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'share-modal-form-status-messages-wrapper',
      ],
      'status_messages' => [
        '#type' => 'status_messages',
        '#weight' => -999,
        '#attributes' => [
          'id' => 'share-modal-form-status-messages',
        ],
      ],
    ];

    $form['share_url_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'share-form--share-url-wrapper',
      ],
      'share_url' => [
        '#type' => 'url',
        '#title' => $this->t('Share the current state of this collection by copying this link'),
        '#default_value' => $this->sharedCollection->shareAbsoluteUrl(),
        '#description' => $this->t('Please note, this URL will not reflect the changes you make in your board later on.'),
        '#attributes' => [
          'readonly' => 'readonly',
        ],
      ],
    ];

    $form['email_share_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'share-form--email-share-wrapper',
      ],
      'emails' => [
        '#type' => 'textfield',
        '#title' => $this->t('Share the current state of this collection via email'),
        '#placeholder' => $this->t("Recipient's e-mail address"),
        '#description' => $this->t("Please note, this URL will not reflect the changes you make in your board later on. Please separate the recipients' addresses with commas"),
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Send the e-mail'),
        '#ajax' => [
          'callback' => '::ajaxSubmitForm',
          'event' => 'click',
        ],
        '#attributes' => [
          'class' => [
            'btn-primary',
          ],
        ],
      ],
      '0' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t("Recipients' e-mail address"),
      ],
      'shared_emails_list_wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'shared-emails-list-wrapper',
        ],
        'shared_emails_list' => $this->sharedEmailsElement(),
      ],
    ];

    if (!isset($form['#cache']['tags'])) {
      $form['#cache']['tags'] = [];
    }

    $form['#cache']['tags'] = Cache::mergeTags($form['#cache']['tags'], [
      'media_collection_list',
      'shared_media_collection_list',
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('Submitted.'));
  }

  /**
   * Implements the submit handler for the modal dialog AJAX call.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Array of AJAX commands to execute on submit of the modal form.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    if ($form_state->getErrors()) {
      $form['status_messages_wrapper']['status_messages']['#weight'] = -10;

      return $response;
    }

    // We don't want any messages that were added by submitForm().
    $this->messenger()->deleteAll();

    $validatedEmails = $this->processSubmittedEmails($this->preprocessSubmittedEmails($form_state->getValue('emails', '')));

    if (count($validatedEmails['raw']) > 0) {

      if (count($validatedEmails['valid'])) {
        $this->sharedCollection->addEmails($validatedEmails['valid']);

        try {
          $this->sharedCollection->save();

          $this->validEmailsMessage($validatedEmails['valid']);

          $sharedEmailsElement = $this->sharedEmailsElement();
          $sharedEmailsSelector = "#{$sharedEmailsElement['#attributes']['id']}";
          $response->addCommand(new ReplaceCommand($sharedEmailsSelector, $sharedEmailsElement));
        }
        catch (EntityStorageException $exception) {
          $this->messenger()->addError($exception->getMessage());
        }
      }

      $this->invalidEmailsMessage($validatedEmails['invalid']);
      $this->existingEmailsMessage($validatedEmails['existing']);
    }
    else {
      $this->messenger()->addWarning($this->t('No email address was entered.'));
    }

    if (count($this->messenger()->all()) > 0) {
      $form['status_messages_wrapper']['status_messages']['#weight'] = -10;
      $response->addCommand(new ReplaceCommand('#share-modal-form-status-messages-wrapper', $form['status_messages_wrapper']));
    }

    return $response;
  }

  /**
   * Add status message about successfully added emails.
   *
   * @param array $emails
   *   The emails.
   */
  private function validEmailsMessage(array $emails): void {
    $this->messenger()->addStatus($this->formatPlural(
      count($emails),
      'The collection has been shared with the following recipient: %emails',
      'The collection has been shared with the following recipients: %emails',
      [
        '%emails' => implode(', ', $emails),
      ]
    ));
  }

  /**
   * Add warning message about invalid emails.
   *
   * @param array $emails
   *   The emails.
   */
  private function invalidEmailsMessage(array $emails): void {
    $count = count($emails);

    if ($count <= 0) {
      return;
    }

    $this->messenger()->addWarning($this->formatPlural(
      $count,
      'The following recipient was invalid: %emails',
      'The following recipients were invalid: %emails',
      [
        '%emails' => implode(', ', $emails),
      ]
    ));
  }

  /**
   * Add warning message about already existing emails.
   *
   * @param array $emails
   *   The emails.
   */
  private function existingEmailsMessage(array $emails): void {
    $count = count($emails);

    if ($count <= 0) {
      return;
    }

    $this->messenger()->addWarning($this->formatPlural(
      $count,
      'The collection was already shared with the following recipient: %emails',
      'The collection was already shared with the following recipients: %emails',
      [
        '%emails' => implode(', ', $emails),
      ]
    ));
  }

  /**
   * Preprocess for the email values from the form state.
   *
   * @param string $emails
   *   The raw value that was submitted.
   *
   * @return array
   *   Cleaned up version of the input.
   */
  private function preprocessSubmittedEmails(string $emails): array {
    $emails = trim($emails);

    if (empty($emails)) {
      return [];
    }

    return array_unique(array_map('trim', explode(',', $emails)));
  }

  /**
   * Process emails.
   *
   * @param array $emails
   *   An array of emails submitted via the AJAX button.
   *
   * @return array
   *   Associative array with keys: valid, invalid, existing.
   */
  private function processSubmittedEmails(array $emails): array {
    $validations = [
      'valid' => [],
      'invalid' => [],
      'existing' => [],
      'raw' => $emails,
    ];

    $validMails = array_filter($emails, function ($email) {
      return $this->emailValidator->isValid($email);
    });

    $validations['invalid'] = array_diff($emails, $validMails);
    $validations['existing'] = array_intersect($validMails, $this->sharedCollection->emails());
    $validations['valid'] = array_diff($validMails, $this->sharedCollection->emails());

    return $validations;
  }

  /**
   * Returns render element for "shared emails".
   *
   * @return array
   *   Render element for "shared emails".
   */
  private function sharedEmailsElement(): array {
    if ($this->sharedCollection->emailCount() <= 0) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('The collection has not yet been shared via email.'),
        '#attributes' => [
          'id' => 'shared-emails-list',
        ],
      ];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->formatPlural(
        $this->sharedCollection->emailCount(),
        'The collection has been shared with the following recipient: %email_list',
        'The collection has been shared with the following recipients: %email_list',
        [
          '%email_list' => implode(', ', $this->sharedCollection->emails()),
        ]
      ),
      '#attributes' => [
        'id' => 'shared-emails-list',
      ],
    ];
  }

}
