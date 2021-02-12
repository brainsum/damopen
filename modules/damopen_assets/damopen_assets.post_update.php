<?php

/**
 * @file
 * Post update functions for the Media Library module.
 */

/**
 * Add content for the "Focus area" vocab.
 */
function damopen_assets_post_update_8501() {
  $focusAreas = [
    'Abstract',
    'All perspectives',
    'Application services',
    'Automotive',
    'Bank',
    'Business renewal',
    'Careers',
    'Cloud & capacity',
    'Common images for block',
    'Construction',
    'Consulting',
    'Customer cases',
    'Customer experience management',
    'Data & AI',
    'Design',
    'Education',
    'End-user services',
    'Energy',
    'Enterprise solutions',
    'Family care',
    'Financial services',
    'Forest',
    'Healthcare and welfare',
    'Industry solutions',
    'IT outsourcing',
    'Logistics',
    'Machinery and equipment',
    'Manufacturing industry',
    'Marketing technology',
    'Media',
    'Metal and mining',
    'Oil and gas',
    'Production excellence',
    'Public',
    'Retail',
    'Security',
    'Smarter society',
    'Software R&D',
    'Telecom',
  ];

  /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
  $termStorage = Drupal::entityTypeManager()->getStorage('taxonomy_term');

  foreach ($focusAreas as $focusArea) {
    $term = $termStorage->create([
      'vid' => 'focus_area',
      'name' => $focusArea,
    ]);

    $term->save();
  }

}

/**
 * Add taxonomy terms for the "Industry" vocab.
 */
function damopen_assets_post_update_add_industry_terms() {
  $industries = [
    'Financial services',
    'Healthcare and welfare',
    'Public sector',
    'Telecom and media',
    'Energy, oil and gas',
    'Forest, pulp and paper',
    'Industrial services and automotive',
    'Retail and consumer services',
  ];

  /** @var \Drupal\taxonomy\TermStorageInterface $termStorage */
  $termStorage = Drupal::entityTypeManager()->getStorage('taxonomy_term');

  $weight = 0;
  foreach ($industries as $industry) {
    $term = $termStorage->create([
      'vid' => 'industry',
      'name' => $industry,
      'weight' => $weight++,
    ]);

    $term->save();
  }

}
