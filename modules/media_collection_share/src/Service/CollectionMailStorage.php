<?php

namespace Drupal\media_collection_share\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use function array_key_exists;

/**
 * Class CollectionMailStorage.
 *
 * @package Drupal\media_collection_share\Service
 */
final class CollectionMailStorage {

  public const STORAGE_KEY = 'media_collection_share.collection_share_mail_storage';

  private $storage;
  private $time;

  /**
   * CollectionMailStorage constructor.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   State storage.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   */
  public function __construct(
    KeyValueFactoryInterface $keyValueFactory,
    TimeInterface $time
  ) {
    $this->storage = $keyValueFactory->get(static::STORAGE_KEY);
    $this->time = $time;
  }

  /**
   * Add an email for the given collection.
   *
   * @param string $uuid
   *   The UUID of the shared collection.
   * @param string $email
   *   The email.
   */
  public function add(string $uuid, string $email): void {
    $this->addMultiple($uuid, [$email]);
  }

  /**
   * Add multiple emails for the given collection.
   *
   * @param string $uuid
   *   The UUID of the shared collection.
   * @param array $emails
   *   An array of emails.
   */
  public function addMultiple(string $uuid, array $emails): void {
    $collectionData = $this->readData($uuid);

    foreach ($emails as $email) {
      if (array_key_exists($email, $collectionData)) {
        continue;
      }

      $collectionData[$email] = [
        'sent' => $this->time->getCurrentTime(),
      ];
    }

    $this->storeData($uuid, $collectionData);
  }

  /**
   * Remove an email for the given collection.
   *
   * @param string $uuid
   *   The UUID of the shared collection.
   * @param string $email
   *   The email.
   */
  public function remove(string $uuid, string $email): void {
    $this->removeMultiple($uuid, [$email]);
  }

  /**
   * Remove multiple emails for the given collection.
   *
   * @param string $uuid
   *   The UUID of the shared collection.
   * @param array $emails
   *   An array of emails.
   */
  public function removeMultiple(string $uuid, array $emails): void {
    $collectionData = $this->readData($uuid);

    foreach ($emails as $email) {
      if (array_key_exists($email, $collectionData)) {
        unset($collectionData[$email]);
      }
    }

    $this->storeData($uuid, $collectionData);
  }

  /**
   * Check if the email exists for the given collection.
   *
   * @param string $uuid
   *   The UUID of the shared collection.
   * @param string $email
   *   The email.
   *
   * @return bool
   *   TRUE, when it exists, FALSE otherwise.
   */
  public function has(string $uuid, string $email): bool {
    return array_key_exists($email, $this->readData($uuid));
  }

  /**
   * Returns data for a shared collection.
   *
   * The array is keyed with email addresses.
   * The individual mails are assoc. arrays with the following structure:
   * [
   *   'sent' => 'timestamp',
   * ]
   * Example:
   * [
   *   'user@example.com' => ['sent' => 1563891390],
   *   ...
   * ]
   *
   * @param string $uuid
   *   The UUID of the shared collection.
   *
   * @return array
   *   The data for the collection.
   */
  public function readData(string $uuid): array {
    return $this->storage->get($uuid, []);
  }

  /**
   * Stores the data for a uuid.
   *
   * @param string $uuid
   *   The UUID of the shared collection.
   * @param array $data
   *   The data to store.
   */
  public function storeData(string $uuid, array $data): void {
    $this->storage->set($uuid, $data);
  }

}
