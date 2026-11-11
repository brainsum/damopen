<?php

namespace Drupal\damopen_category_upload\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Keeps legacy Library links pointed at the existing asset browser.
 */
final class LibraryController extends ControllerBase {

  /**
   * Redirects legacy category links to the equivalent search filter.
   */
  public function browse(?TermInterface $taxonomy_term = NULL): \Symfony\Component\HttpFoundation\RedirectResponse {
    if ($taxonomy_term && ($taxonomy_term->bundle() !== 'category' || !$taxonomy_term->isPublished())) {
      throw new NotFoundHttpException();
    }
    $options = $taxonomy_term ? ['query' => ['f' => ['asset_category:' . $taxonomy_term->id()]]] : [];
    return $this->redirect('view.asset_search.asset_search', [], $options);
  }

  /**
   * Lists shareable category URLs without granting upload permissions.
   */
  public function uploadLinks(): array {
    $rows = [];
    foreach (\Drupal::service('damopen_category_upload.policy')->options() as $id => $path) {
      $url = Url::fromRoute('damopen_upload.upload', [], ['query' => ['category' => $id], 'absolute' => TRUE]);
      $rows[] = [$path, Link::fromTextAndUrl($this->t('Open upload'), $url), $url->toString()];
    }
    return [
      '#cache' => ['max-age' => 0],
      'intro' => ['#markup' => '<p>' . $this->t('Share these links with signed-in uploaders. Each link applies its category to every image. Users must still have permission to upload into that branch.') . '</p>'],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Library path'), $this->t('Upload'), $this->t('Shareable URL')],
        '#rows' => $rows,
        '#empty' => $this->t('No upload categories available.'),
      ],
    ];
  }

}
