<?php

namespace Drupal\damopen_assets_api\Plugin\views\style;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\damopen_common\Temporary\ImageStyleLoader;
use Drupal\rest\Plugin\views\style\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function reset;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "damopen_assets_serializer",
 *   title = @Translation("Serializer for assets data"),
 *   help = @Translation("Serializes views row data using the Serializer component and adds custom data."), display_types = {"data"}
 * )
 */
class MediaAssetsSerializer extends Serializer {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  private $termStorage;

  /**
   * Array of image styles.
   *
   * @var \Drupal\image\ImageStyleInterface[]
   */
  private $imageStyleList;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer'),
      $container->getParameter('serializer.formats'),
      $container->getParameter('serializer.format_providers'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * SerializerTML constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SerializerInterface $serializer,
    array $serializer_formats,
    array $serializer_format_providers,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer, $serializer_formats, $serializer_format_providers);

    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];

    $current_page = NULL;
    $total_items = 0;
    if (isset($this->view->pager)) {
      $current_page = $this->view->pager->getCurrentPage();
      $total_items = $this->view->pager->getTotalItems();
    }
    $damopen_assets = [];
    foreach ($this->fetchImageStyleList() as $key => $style) {
      $damopen_assets[$key] = $style->label();
    }
    $terms = $this->termStorage->loadTree('category');

    $categories = [];
    foreach ($terms as $term) {
      $categories[$term->tid] = $term->name;
    }

    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }
    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the
    // default.
    if (empty($this->view->live_preview)) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }
    return $this->serializer->serialize(
      [
        'results' => $rows,
        'damopen_assets' => $damopen_assets,
        'categories' => $categories,
        'current_page' => $current_page,
        'total_items' => $total_items,
      ],
      $content_type,
      ['views_style_plugin' => $this]
    );
  }

  /**
   * Array of image styles.
   *
   * @return \Drupal\image\ImageStyleInterface[]
   *   The image style list.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo: Temporary.
   */
  protected function fetchImageStyleList(): array {
    if (empty($this->imageStyleList)) {
      $this->imageStyleList = ImageStyleLoader::loadImageStylesList($this->entityTypeManager);
    }

    return $this->imageStyleList;
  }

}
