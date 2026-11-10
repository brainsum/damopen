<?php

namespace Drupal\damopen_category_upload;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Shared server-side rules for forms, JSON:API and direct entity saves.
 */
final class CategoryUploadPolicy {

  /**
   * Routes whose human-facing ?category= parameter is an upload link preset.
   */
  private const UPLOAD_ROUTES = ['damopen_upload.upload', 'media_upload.bulk_media_upload'];

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private AccountInterface $currentUser,
    private RequestStack $requestStack,
  ) {}

  /**
   * Returns the assigned root, or NULL for an unrestricted uploader.
   */
  public function root(?AccountInterface $account = NULL): ?int {
    $account ??= $this->currentUser;
    if ($account->hasPermission('bypass category upload restrictions')) {
      return NULL;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    $id = $user?->hasField('field_upload_category') ? $user->get('field_upload_category')->target_id : NULL;
    return $id ? (int) $id : NULL;
  }

  /**
   * Explains why an assigned upload category cannot be used, if it cannot.
   *
   * A missing or unpublished assignment still restricts the account (failing
   * closed), so the user needs a message rather than an empty category list.
   */
  public function assignmentError(?AccountInterface $account = NULL): ?string {
    $root = $this->root($account);
    if ($root === NULL || isset($this->hierarchy()[0][$root])) {
      return NULL;
    }
    return 'Your assigned upload category is no longer available. Ask an administrator to update the Upload category on your profile.';
  }

  /**
   * Returns the published category IDs the account may upload to.
   *
   * Restricted accounts get their root and its descendants, stopping at
   * unpublished terms. The core term tree is one cached query and the walk
   * keeps a visited set, so malformed cyclic hierarchies terminate.
   *
   * @return array<int, int>
   *   Allowed term IDs, keyed by ID.
   */
  public function allowedIds(?AccountInterface $account = NULL): array {
    [$published, $children] = $this->hierarchy();
    $root = $this->root($account);
    if ($root === NULL) {
      return $published;
    }
    $allowed = [];
    $pending = isset($published[$root]) ? [$root] : [];
    while ($pending) {
      $id = array_pop($pending);
      if (isset($allowed[$id])) {
        continue;
      }
      $allowed[$id] = $id;
      foreach ($children[$id] ?? [] as $child) {
        if (isset($published[$child])) {
          $pending[] = $child;
        }
      }
    }
    return $allowed;
  }

  /**
   * Checks whether the account may upload to a category.
   */
  public function allowed(int $id, ?AccountInterface $account = NULL): bool {
    return isset($this->allowedIds($account)[$id]);
  }

  /**
   * Returns the full category path, including repeated labels.
   */
  public function path(TermInterface $term): string {
    $names = array_map(fn(TermInterface $ancestor) => $ancestor->label(), $this->terms()->loadAllParents($term->id()));
    return implode(' / ', array_reverse($names));
  }

  /**
   * Full paths disambiguate duplicate names; no JSON:API pagination limit.
   */
  public function options(bool $restricted = TRUE): array {
    [$published] = $this->hierarchy();
    // Intersecting with the tree-ordered list keeps Library order.
    $ids = $restricted ? array_intersect_key($published, $this->allowedIds()) : $published;
    $options = [];
    foreach ($this->terms()->loadMultiple($ids) as $id => $term) {
      $options[$id] = $this->path($term);
    }
    return $options;
  }

  /**
   * A preset is classification, never an access token or permission grant.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
   *   On an invalid or out-of-scope preset, so upload pages deny access.
   */
  public function preset(): ?int {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return NULL;
    }
    $query = $request->query;
    // Other pages use ?category= for their own purposes, such as Views
    // filters, so it is a preset only on the upload pages. JSON:API reserves
    // lowercase-only parameter names, so its write requests use uploadCategory.
    $link = $query->has('category') && in_array($request->attributes->get('_route'), self::UPLOAD_ROUTES, TRUE);
    $api = $query->has('uploadCategory');
    if (!$link && !$api) {
      return NULL;
    }
    if ($link && $api) {
      throw new BadRequestHttpException('Specify only one upload category.');
    }
    $raw = $query->all()[$api ? 'uploadCategory' : 'category'];
    if (!is_scalar($raw) || !ctype_digit((string) $raw) || (int) $raw < 1) {
      throw new BadRequestHttpException('Invalid upload category.');
    }
    $id = (int) $raw;
    if (!$this->allowed($id)) {
      throw new AccessDeniedHttpException('You cannot upload to this category.');
    }
    return $id;
  }

  /**
   * Returns allowed category choices and the batch preset for the uploader.
   */
  public function settings(): array {
    // Explain an unavailable assignment instead of denying the upload link.
    if ($error = $this->assignmentError()) {
      return [
        'categories' => [],
        'presetId' => NULL,
        'presetCategory' => NULL,
        'defaultCategory' => NULL,
        'assignmentError' => $error,
      ];
    }
    $preset = $this->preset();
    $categories = [];
    foreach ($this->options() as $id => $path) {
      if ($preset !== NULL && $preset !== (int) $id) {
        continue;
      }
      $categories[] = [
        'id' => $this->terms()->load($id)->uuid(),
        'type' => 'taxonomy_term--category',
        'name' => $path,
        'status' => TRUE,
      ];
    }
    $default = $preset ?? $this->root();
    $defaultUuid = $default && $this->allowed($default) ? $this->terms()->load($default)->uuid() : NULL;
    return [
      'categories' => $categories,
      'presetId' => $preset,
      'presetCategory' => $preset !== NULL ? $defaultUuid : NULL,
      'defaultCategory' => $defaultUuid,
      'assignmentError' => $this->assignmentError(),
    ];
  }

  /**
   * Returns an error for an out-of-scope upload or category reassignment.
   */
  public function violation(MediaInterface $media): ?string {
    if (!$media->hasField('field_category')) {
      return NULL;
    }
    $original = NULL;
    if (!$media->isNew()) {
      $original = $media->getOriginal() ?? $this->entityTypeManager->getStorage('media')->loadUnchanged($media->id());
    }
    $ids = self::targetIds($media->get('field_category'));
    $before = $original ? self::targetIds($original->get('field_category')) : [];
    $source = $media->getSource()->getConfiguration()['source_field'] ?? NULL;
    // A new asset or replaced file is an upload into every category it has.
    $upload = !$original
      || ($source && self::targetIds($original->get($source)) !== self::targetIds($media->get($source)));
    // Reviewers can approve/edit metadata without moving the original asset.
    if (!$upload && $ids === $before) {
      return NULL;
    }
    if ($error = $this->assignmentError()) {
      return $error;
    }
    $restricted = $this->root() !== NULL;
    if ($restricted && !$ids) {
      return 'Choose your assigned upload category or one of its descendants.';
    }
    $allowed = $this->allowedIds();
    // Editing categories only needs the added ones in scope. Categories
    // outside the user's branch stay on the asset; removing them would move
    // another branch's asset.
    foreach ($upload ? $ids : array_diff($ids, $before) as $id) {
      if (!isset($allowed[$id])) {
        return 'You can upload only to your assigned category and its descendants.';
      }
    }
    if ($restricted && !$upload) {
      foreach (array_diff($before, $ids) as $id) {
        if (!isset($allowed[$id])) {
          return 'You cannot remove categories outside your assigned category.';
        }
      }
    }
    if ($media->isNew()) {
      try {
        $preset = $this->preset();
      }
      catch (HttpExceptionInterface $e) {
        // Report as a validation error (JSON:API 422), not an HTTP failure.
        return $e->getMessage();
      }
      if ($preset !== NULL && $ids !== [$preset]) {
        return 'Every image in this upload must use the category specified by the upload link.';
      }
    }
    return NULL;
  }

  /**
   * Returns sorted, unique target IDs, ignoring widget-specific item keys.
   *
   * @return int[]
   *   The referenced IDs.
   */
  private static function targetIds(FieldItemListInterface $items): array {
    $ids = array_map('intval', array_filter(array_column($items->getValue(), 'target_id')));
    $ids = array_values(array_unique($ids));
    sort($ids);
    return $ids;
  }

  /**
   * Returns published category IDs in Library order, and children by parent.
   *
   * @return array{0: array<int, int>, 1: array<int, int[]>}
   *   Published IDs keyed by ID, and child IDs keyed by parent ID.
   */
  private function hierarchy(): array {
    $published = [];
    $children = [];
    foreach ($this->terms()->loadTree('category') as $item) {
      $id = (int) $item->tid;
      if ($item->status) {
        $published[$id] = $id;
      }
      foreach ($item->parents as $parent) {
        $children[(int) $parent][] = $id;
      }
    }
    return [$published, $children];
  }

  /**
   * Returns the taxonomy term storage.
   */
  private function terms(): TermStorageInterface {
    return $this->entityTypeManager->getStorage('taxonomy_term');
  }

}
