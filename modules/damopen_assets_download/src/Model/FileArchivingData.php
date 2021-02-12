<?php

namespace Drupal\damopen_assets_download\Model;

/**
 * Class FileArchivingData.
 *
 * @package Drupal\damopen_assets_download\Model
 */
class FileArchivingData {

  /**
   * File entity.
   *
   * @var \Drupal\file\FileInterface
   */
  public $file;

  /**
   * Absolute path of the file on the system.
   *
   * @var string
   */
  public $systemPath;

  /**
   * Desired path of the file in an archive.
   *
   * @var string
   */
  public $archiveTargetPath;

  /**
   * FileArchivingData constructor.
   *
   * @param array $values
   *   Values.
   */
  public function __construct(array $values) {
    foreach ($values as $key => $value) {
      $this->{$key} = $value;
    }
  }

}
