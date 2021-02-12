<?php

namespace Drupal\media_collection_share\Entity;

use Drupal\media_collection\Entity\MediaCollectionInterface;

/**
 * Provides an interface for defining Media collection (shared) entities.
 *
 * @ingroup media_collection_share
 */
interface SharedMediaCollectionInterface extends MediaCollectionInterface {

  /**
   * Sets the URL field.
   *
   * @param string $url
   *   The url.
   *
   * @return \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface
   *   The entity.
   */
  public function setShareUrl(string $url): SharedMediaCollectionInterface;

  /**
   * Returns the share url.
   *
   * @return string
   *   The share URL.
   */
  public function shareUrl(): string;

  /**
   * Returns the share url as an absolute one.
   *
   * @return string
   *   The share URL.
   */
  public function shareAbsoluteUrl(): string;

  /**
   * Returns the count of stored emails.
   *
   * @return int
   *   The count of stored emails.
   */
  public function emailCount(): int;

  /**
   * Returns the stored emails.
   *
   * @return string[]
   *   The emails.
   */
  public function emails(): array;

  /**
   * Add an email.
   *
   * @param string $email
   *   The email to add.
   *
   * @return \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface
   *   The class instance.
   */
  public function addEmail(string $email): SharedMediaCollectionInterface;

  /**
   * Add multiple emails.
   *
   * @param array $emails
   *   The emails to add.
   *
   * @return \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface
   *   The class instance.
   */
  public function addEmails(array $emails): SharedMediaCollectionInterface;

  /**
   * Set the emails.
   *
   * @param array $emails
   *   The emails to set.
   *
   * @return \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface
   *   The class instance.
   */
  public function setEmails(array $emails): SharedMediaCollectionInterface;

}
