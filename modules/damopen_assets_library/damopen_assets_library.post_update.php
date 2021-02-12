<?php

/**
 * @file
 * Post update functions for the Media Library module.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;

/**
 * Change the schema from public to private in the database.
 *
 * Move the files to the private folder.
 */
function damopen_assets_library_post_update_change_public_files_to_private() {
  $connection = Database::getConnection();
  // Select the uri from the database.
  $query = $connection
    ->select('file_managed', 'fm')
    ->fields('fm', ['fid', 'uri']);
  $result = $query->execute();

  $fileStorage = Drupal::entityTypeManager()->getStorage('file');
  $fileSystem = Drupal::service('file_system');
  $priv_path = $fileSystem->realpath('private://');

  foreach ($result as $row) {
    $uri = str_replace('public://', '', $row->uri);
    $file_name = substr(strrchr($uri, '/'), 1);
    $folder = str_replace($file_name, '', $uri);
    // Check if the directory already exists.
    // Directory does not exist, so lets create it.
    if (
      !is_dir($priv_path . '/' . $folder)
      && !mkdir($concurrentDirectory = $priv_path . '/' . $folder, 0775, TRUE)
      && !is_dir($concurrentDirectory)
    ) {
      throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
    }

    // Move the file to the private folder.
    $new_uri = $fileSystem->move('public://' . $uri, 'private://' . $uri, FileSystemInterface::EXISTS_REPLACE);

    if ($new_uri !== NULL) {
      // Replace the uri with the new private schema's uri.
      /** @var \Drupal\file\FileInterface $file */
      $file = $fileStorage->load($row->fid);
      $file->setFileUri($new_uri);
      $file->save();
    }
  }
}
