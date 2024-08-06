<?php
namespace Drupal\damopen_upload\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for the Example module.
 */
class DamopenUploadController extends ControllerBase {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function upload() {
    return [
      '#theme' => 'damopen_upload_form',
      '#cache' => [
        'max-age' => 0,
      ],
      '#attached' => [
        'drupalSettings' => [
          'user' => [
            'uploadNeedsApproval' => !$this->currentUser()->hasPermission('manage uploaded assets'),
          ],
        ],
      ]
    ];
  }

}
