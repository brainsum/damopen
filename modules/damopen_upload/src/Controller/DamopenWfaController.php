<?php

namespace Drupal\damopen_upload\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for assets waiting for approval.
 */
class DamopenWfaController extends ControllerBase {

  /**
   * Returns the waiting for approval page with React app.
   *
   * @return array
   *   A renderable array.
   */
  public function waitingForApproval() {
    return [
      '#markup' => '<div id="damo-wfa"></div>',
      '#cache' => [
        'max-age' => 0,
      ],
      '#attached' => [
        'library' => [
          'damo_theme/damopen_wfa',
        ],
      ],
    ];
  }

}
