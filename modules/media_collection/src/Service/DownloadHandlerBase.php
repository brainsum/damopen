<?php

namespace Drupal\media_collection\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\FileInterface;

/**
 * Class DownloadHandlerBase.
 *
 * @package Drupal\media_collection\Service
 */
abstract class DownloadHandlerBase {

  abstract protected function archivePath(ContentEntityInterface $entity);

  abstract protected function generateArchive(ContentEntityInterface $entity): ?string;

  abstract protected function generateArchiveEntity(ContentEntityInterface $entity): ?FileInterface;

}
