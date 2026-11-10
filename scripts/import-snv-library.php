<?php

/**
 * @file
 * Run with drush php:script after enabling damopen_category_upload.
 */

use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

if (!\Drupal::moduleHandler()->moduleExists('damopen_category_upload')) {
  throw new RuntimeException('Enable damopen_category_upload first.');
}
$manifest = json_decode(file_get_contents(__DIR__ . '/snv-library.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$nodes = $manifest['nodes'];
$source_ids = array_column($nodes, 'id');
if (count($source_ids) !== count(array_unique($source_ids))) {
  throw new RuntimeException('Duplicate source IDs in manifest.');
}
foreach ($nodes as $node) {
  if ($node['parent'] && !in_array($node['parent'], $source_ids, TRUE)) {
    throw new RuntimeException('Missing parent for ' . $node['id']);
  }
}
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
$transaction = \Drupal::database()->startTransaction();
try {
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $map = [];
  $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
  foreach ($nodes as $node) {
    if ($node['parent'] && !isset($map[$node['parent']])) {
      throw new RuntimeException('Manifest must list parents first: ' . $node['id']);
    }
    $matches = $storage->loadByProperties(['vid' => 'category', 'field_canto_id' => $node['id']]);
    if (count($matches) > 1) {
      throw new RuntimeException('Ambiguous existing source ID: ' . $node['id']);
    }
    $term = $matches ? reset($matches) : Term::create(['vid' => 'category']);
    $values = [
      'name' => $node['name'],
      'parent' => $node['parent'] ? $map[$node['parent']] : 0,
      'weight' => $node['weight'],
      'field_canto_id' => $node['id'],
      'field_canto_type' => $node['type'],
    ];
    $changed = $term->isNew();
    foreach ($values as $field => $value) {
      $old = $field === 'parent' ? $term->get($field)->target_id : $term->get($field)->value;
      $changed = $changed || (string) $old !== (string) $value;
      $term->set($field, $value);
    }
    $state = $term->isNew() ? 'created' : ($changed ? 'updated' : 'unchanged');
    if ($changed) {
      $term->save();
    }
    $counts[$state]++;
    $map[$node['id']] = $term->id();
  }
  echo json_encode(['terms' => count($map)] + $counts, JSON_PRETTY_PRINT) . PHP_EOL;
}
catch (Throwable $e) {
  $transaction->rollBack();
  throw $e;
}
finally {
  $switcher->switchBack();
}
