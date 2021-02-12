<?php

namespace Drupal\damopen_assets_statistics\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MediaFileDownloadStatisticsBlock.
 *
 * Provides a block with statistics informations about media files download.
 *
 * @Block(
 *   id = "media_file_download_statistics_block",
 *   admin_label = @Translation("Media File Download Statistics"),
 * )
 *
 * @package Drupal\damopen_assets_statistics\Plugin\Block
 */
class MediaFileDownloadStatisticsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Connection $database
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->database = $database;
  }

  /**
   * Returns the query string for statistical data.
   *
   * @return string
   *   The query.
   */
  private function statisticsQuery() {
    /* @todo: Generalize, don't have image + video_file here. Probably we should
     * - save the mid and rev_mid of the parent media entity
     * - save the bundle id
     * Then we can just sum the counts grouped by bundle.
     */
    return <<<EOD
    SELECT a.name,
      SUM(CASE WHEN a.bundle = 'image' THEN a.nr ELSE 0 END) AS images,
      SUM(CASE WHEN a.bundle  = 'video_file' THEN a.nr ELSE 0 END) AS video_files
    FROM (
      SELECT u.name,
        CASE
          WHEN i.bundle IS NOT NULL THEN i.bundle
          WHEN vf.bundle IS NOT NULL THEN vf.bundle
          ELSE NULL
        END AS bundle,
        count(*) AS nr
      FROM {damopen_assets_statistics} s
        INNER JOIN {users_field_data} u ON s.uid = u.uid
        LEFT JOIN {media__field_image} i ON s.fid = i.field_image_target_id
        LEFT JOIN {media__field_video_file} vf ON s.fid = vf.field_video_file_target_id
      GROUP BY u.name, bundle
      ) a
    GROUP BY a.name
    ORDER BY a.name
EOD;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $query_result = $this->database->query($this->statisticsQuery());

    $rows = [];
    while ($bundles = $query_result->fetch()) {
      $rows[] = [
        $bundles->name,
        $bundles->images,
        $bundles->video_files,
      ];
    }

    $block['subject'] = [
      '#markup' => $this->t('Number of downloads by type and sites'),
    ];
    if (!empty($rows)) {
      $block['content'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Client site'),
          $this->t('Images'),
          $this->t('Video files'),
        ],
        '#rows' => $rows,
      ];
    }
    else {
      $block['content'] = [
        '#markup' => $this->t('There are no downloaded files.'),
      ];
    }
    // @todo invalidate in damopen_assets_statistics_file_download().
    $block[] = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];
    return $block;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Protect block access with permission check.
    if ($account->hasPermission('access media asset usage statistics')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
