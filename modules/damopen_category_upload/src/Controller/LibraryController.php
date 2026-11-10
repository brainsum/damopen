<?php

namespace Drupal\damopen_category_upload\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exposes the complete category tree, including empty folders and albums.
 */
final class LibraryController extends ControllerBase {

  /**
   * Lists child categories and approved assets in the selected branch.
   */
  public function browse(?TermInterface $taxonomy_term = NULL): array {
    if ($taxonomy_term && ($taxonomy_term->bundle() !== 'category' || !$taxonomy_term->isPublished())) {
      throw new NotFoundHttpException();
    }
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $policy = \Drupal::service('damopen_category_upload.policy');
    $build = ['#cache' => ['max-age' => 0]];
    $build['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      'text' => ['#plain_text' => $taxonomy_term ? $policy->path($taxonomy_term) : $this->t('Browse the Library')],
    ];
    if ($taxonomy_term) {
      $parent = $taxonomy_term->get('parent')->target_id;
      $build['back'] = Link::createFromRoute($this->t('Up one level'), $parent ? 'damopen_category_upload.branch' : 'damopen_category_upload.library', $parent ? ['taxonomy_term' => $parent] : [])->toRenderable();
      if ($this->currentUser()->hasPermission('create image media') && $this->currentUser()->hasPermission('upload media') && $policy->allowed((int) $taxonomy_term->id())) {
        $build['upload'] = [
          '#type' => 'container',
          'link' => $this->uploadLink($taxonomy_term->id(), $this->t('Upload images here'))->toRenderable(),
        ];
      }
    }
    $items = [];
    foreach ($storage->loadTree('category', $taxonomy_term?->id() ?? 0, 1, TRUE) as $term) {
      if ($term->isPublished()) {
        $items[] = Link::createFromRoute($term->label(), 'damopen_category_upload.branch', ['taxonomy_term' => $term->id()]);
      }
    }
    $build['children'] = ['#theme' => 'item_list', '#items' => $items, '#empty' => $this->t('No subcategories.')];
    if ($taxonomy_term) {
      $ids = [$taxonomy_term->id()];
      foreach ($storage->loadTree('category', $taxonomy_term->id()) as $term) {
        $ids[] = $term->tid;
      }
      $media_storage = $this->entityTypeManager()->getStorage('media');
      $media_ids = $media_storage->getQuery()->accessCheck(TRUE)->condition('status', 1)
        ->condition('field_category.target_id', array_unique($ids), 'IN')->sort('created', 'DESC')->pager(24)->execute();
      $assets = array_filter($media_storage->loadMultiple($media_ids), fn($media) => $media->access('view'));
      $build['assets_heading'] = ['#markup' => '<h3>' . $this->t('Approved assets in this branch') . '</h3>'];
      $build['assets'] = $this->entityTypeManager()->getViewBuilder('media')->viewMultiple($assets, 'media_library');
      if (!$assets) {
        $build['empty'] = ['#markup' => '<p>' . $this->t('No approved assets in this branch yet.') . '</p>'];
      }
      $build['pager'] = ['#type' => 'pager'];
    }
    return $build;
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

  /**
   * Builds a link to a category-specific batch.
   */
  private function uploadLink($id, $label): Link {
    return Link::createFromRoute($label, 'damopen_upload.upload', [], ['query' => ['category' => $id]]);
  }

}
