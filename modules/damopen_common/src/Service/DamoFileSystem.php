<?php

namespace Drupal\damopen_common\Service;

use Drupal\Core\File\FileSystem;
use SplFileInfo;

/**
 * Class DamoFileSystem.
 *
 * @package Drupal\damopen_common\Service
 */
class DamoFileSystem extends FileSystem implements DamoFileSystemInterface {

  /**
   * {@inheritdoc}
   */
  public function safeMkdir(string $uri): bool {
    $uriInfo = new SplFileInfo($uri);
    $path = $uri;

    if ($uriInfo->getExtension()) {
      $path = $uriInfo->getPath();
    }

    return !(!is_dir($path) && !$this->mkdir($path, NULL, TRUE) && !is_dir($path));
  }

}
