<?php

namespace Drupal\damopen_category_upload;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Enhances the existing category facet after its lazy block content is built.
 */
final class CategoryBrowser implements TrustedCallbackInterface {

  public static function trustedCallbacks(): array {
    return ['preRender'];
  }

  public static function preRender(array $build): array {
    $request = \Drupal::request();
    $query = $request->query->all();
    unset($query['page']);
    $active = [];
    $other_filters = [];
    foreach ((array) ($query['f'] ?? []) as $filter) {
      if (is_string($filter) && preg_match('/^asset_category:(\d+)$/', $filter, $match)) {
        $active[] = (int) $match[1];
      }
      elseif (is_string($filter)) {
        $other_filters[] = $filter;
      }
    }
    $policy = \Drupal::service('damopen_category_upload.policy');
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $options = [];
    $selected = [];
    foreach ($storage->loadTree('category', 0, NULL, TRUE) as $term) {
      // Category labels are search metadata, as in the original facet. Viewing
      // assets does not require permission to visit taxonomy entity pages.
      if (!$term->isPublished()) {
        continue;
      }
      $ids = in_array((int) $term->id(), $active, TRUE) ? array_diff($active, [(int) $term->id()]) : array_merge($active, [(int) $term->id()]);
      $item_query = $query;
      $item_query['f'] = array_merge($other_filters, array_map(fn($id) => 'asset_category:' . $id, $ids));
      $url = \Drupal\Core\Url::fromRoute('view.asset_search.asset_search', [], ['query' => $item_query])->toString();
      $options[$url] = $policy->path($term);
      if (in_array((int) $term->id(), $active, TRUE)) {
        $selected[] = $url;
      }
    }
    unset($build['#markup']);
    $build['content'] = [
      '#type' => 'container', '#attributes' => ['class' => ['dam-category-browser']],
      'select' => [
        '#type' => 'select2', '#title' => t('Browse categories'), '#options' => $options,
        '#value' => $selected, '#multiple' => TRUE, '#required' => FALSE,
        '#name' => 'Asset Category', '#autocomplete' => FALSE,
        '#attributes' => ['data-drupal-facet-id' => 'asset_category', 'data-drupal-selector' => 'facet-asset_category', 'class' => ['js-facets-select2', 'js-facets-widget']],
        '#select2' => ['width' => '100%', 'placeholder' => t('All categories — search a country or folder')],
        '#attached' => ['library' => ['select2_facets/drupal.select2_facets.select2-widget', 'damopen_category_upload/category_browser']],
      ],
    ];
    if (count($active) === 1 && ($term = $storage->load(reset($active))) && $term->bundle() === 'category' && $term->isPublished()) {
      $build['content']['path'] = ['#type' => 'container', '#attributes' => ['class' => ['dam-category-context']], 'label' => ['#plain_text' => $policy->path($term)]];
      if (\Drupal::currentUser()->hasPermission('create image media') && \Drupal::currentUser()->hasPermission('upload media') && $policy->allowed((int) $term->id())) {
        $build['content']['path']['upload'] = [
          '#type' => 'link', '#title' => t('Upload images here'),
          '#url' => \Drupal\Core\Url::fromRoute('damopen_upload.upload', [], ['query' => ['category' => $term->id()]]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ];
      }
    }
    $build['#cache']['max-age'] = 0;
    return $build;
  }

}
