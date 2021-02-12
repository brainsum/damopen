<?php

namespace Drupal\media_collection_share\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\media_collection_share\Entity\SharedMediaCollectionInterface;
use Drupal\user\UserInterface;
use Exception;
use function array_key_exists;

/**
 * Class CollectionMailer.
 *
 * @package Drupal\media_collection_share\Service
 */
final class CollectionMailer {

  private const MODULE_NAME = 'media_collection_share';
  public const MAIL_KEY = 'media_collection_share.collection_share';

  /**
   * Mail service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  private $mail;

  /**
   * Custom storage for sent collection emails.
   *
   * @var \Drupal\media_collection_share\Service\CollectionMailStorage
   */
  private $mailStorage;

  /**
   * Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * Name of the site (from config).
   *
   * @var string
   */
  private $siteName;

  /**
   * Logger for the module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * CollectionMailer constructor.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail
   *   Mail manager.
   * @param \Drupal\media_collection_share\Service\CollectionMailStorage $mailStorage
   *   Custom mail storage.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger channel factory.
   */
  public function __construct(
    MailManagerInterface $mail,
    CollectionMailStorage $mailStorage,
    ConfigFactoryInterface $configFactory,
    RendererInterface $renderer,
    LoggerChannelFactoryInterface $logger
  ) {
    $this->mail = $mail;
    $this->mailStorage = $mailStorage;

    $this->siteName = $configFactory->get('system.site')->get('name');
    $this->renderer = $renderer;
    $this->logger = $logger->get('media_collection_share');
  }

  /**
   * Send mails for a collection.
   *
   * @param \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $collection
   *   The shared collection to send.
   */
  public function sendCollection(SharedMediaCollectionInterface $collection): void {
    $sender = $collection->getOwner();
    $shareUrl = $collection->shareAbsoluteUrl();
    $uuid = $collection->uuid();

    foreach ($this->determineRecipients($collection) as $recipient) {
      if ($this->sendEmail($sender, $recipient, $shareUrl)) {
        $this->mailStorage->add($uuid, $recipient);
        $this->logger->info("Shared collection '{$uuid}' was sent to '{$recipient}'.");
      }
      else {
        $this->logger->alert("Shared collection '{$uuid}' failed be sent to '{$recipient}'.");
      }
    }
  }

  /**
   * Returns the user's full name, or falls back to the display name.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return string
   *   The name.
   */
  private function determineUserName(UserInterface $user): string {
    if (
      $user->hasField('field_user_fullname')
      && ($fullNameField = $user->get('field_user_fullname'))
      && !$fullNameField->isEmpty()
    ) {
      return $fullNameField->getString();
    }

    return $user->getDisplayName();
  }

  /**
   * Determines recipients for a collection.
   *
   * @param \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $collection
   *   The collection.
   *
   * @return string[]
   *   Recipient email addresses.
   */
  private function determineRecipients(SharedMediaCollectionInterface $collection): array {
    $storedData = $this->mailStorage->readData($collection->uuid());

    $recipients = [];

    if (
      ($ownerEmail = $collection->getOwner()->getEmail())
      && !array_key_exists($ownerEmail, $storedData)
    ) {
      $recipients[] = $ownerEmail;
    }

    foreach ($collection->emails() as $email) {
      if (array_key_exists($email, $storedData)) {
        continue;
      }

      $recipients[] = $email;
    }

    return $recipients;
  }

  /**
   * Prepare template for the email.
   *
   * @param string $recipientEmail
   *   Recipient email address.
   * @param string $senderName
   *   Sender name.
   * @param string $senderEmail
   *   Sender email address.
   * @param string $sharedUrl
   *   Collection share URL.
   * @param string $subject
   *   Subject of the email.
   * @param string $langCode
   *   Language code.
   *
   * @return array
   *   The render array for the template.
   */
  private function prepareTemplate(
    string $recipientEmail,
    string $senderName,
    string $senderEmail,
    string $sharedUrl,
    string $subject,
    string $langCode
  ): array {
    return [
      '#theme' => 'notification__collection_shared',
      '#data' => [
        'recipient_email' => $recipientEmail,
        'sender_name' => $senderName,
        'sender_email' => $senderEmail,
        'site_name' => $this->siteName,
        'share_url' => $sharedUrl,
        'subject' => $subject,
        'langcode' => $langCode,
      ],
      '#elements' => [
        'recipient_email' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $recipientEmail,
          '#attributes' => [
            'class' => [
              'recipient-email-address',
            ],
          ],
        ],
        'sender_name' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $senderName,
          '#attributes' => [
            'class' => [
              'sender-name',
            ],
          ],
        ],
        'sender_email' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $senderEmail,
          '#attributes' => [
            'class' => [
              'sender-email-address',
            ],
          ],
        ],
        'site_name' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->siteName,
          '#attributes' => [
            'class' => [
              'site-name',
            ],
          ],
        ],
        'share_url' => [
          '#type' => 'link',
          '#title' => $sharedUrl,
          '#url' => Url::fromUri($sharedUrl),
          '#attributes' => [
            'target' => '_blank',
            'rel' => 'noopener noreferrer nofollow',
          ],
        ],
        'subject' => [
          '#markup' => $subject,
        ],
        'langcode' => [
          '#markup' => $langCode,
        ],
      ],
    ];
  }

  /**
   * Send an email.
   *
   * @param \Drupal\user\UserInterface $sender
   *   The sender user.
   * @param string $recipientEmail
   *   Recipient email address.
   * @param string $sharedUrl
   *   Collection share URL.
   *
   * @return bool
   *   TRUE, if the email could be sent, FALSE otherwise.
   */
  private function sendEmail(
    UserInterface $sender,
    string $recipientEmail,
    string $sharedUrl
  ): bool {
    $senderName = $this->determineUserName($sender);
    $senderEmail = $sender->getEmail();
    $langCode = $sender->getPreferredLangcode();
    $mailSubject = "Set of assets shared by {$senderName} - {$this->siteName}";

    $build = $this->prepareTemplate(
      $recipientEmail,
      $senderName,
      $senderEmail,
      $sharedUrl,
      $mailSubject,
      $langCode
    );

    try {
      $rendered = Html::escape($this->renderer->renderRoot($build));
    }
    catch (Exception $exception) {
      $this->logger->error("Sharing a collection failed. {$exception->getMessage()}");
      return FALSE;
    }

    $params = [
      'subject' => $mailSubject,
      'message' => $rendered,
    ];

    $result = $this->mail->mail(
      static::MODULE_NAME,
      static::MAIL_KEY,
      $recipientEmail,
      $langCode,
      $params,
      $senderEmail
    );

    return (bool) $result['result'];
  }

}
