<?php

namespace Drupal\damopen_image_media_styles_preview\Render;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\damopen_common\Temporary\ImageStyleLoader;
use Drupal\damopen_image_media_styles_preview\Form\MediaAssetFilterForm;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function array_shift;
use function array_values;
use function drupal_get_path;
use function explode;
use function file_create_url;
use function file_exists;
use function file_get_contents;
use function getimagesize;
use function is_array;
use function render;
use function str_replace;
use function strpos;
use function strtolower;

/**
 * Class AssetPreviewListMarkup.
 *
 * @package Drupal\damopen_image_media_styles_preview\Render
 *
 * @todo: Refactor into service. Add alters or other ways to change output.
 */
final class AssetPreviewListMarkup {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The current collection, if it exists.
   *
   * @var \Drupal\media_collection\Entity\MediaCollectionInterface|null
   *
   * @todo: Inject via alters or service overrides.
   */
  protected $currentCollection;

  /**
   * The collection handler, if it exists.
   *
   * @var \Drupal\media_collection\Service\CollectionHandler|null
   */
  protected $collectionHandler;

  /**
   * Render array for the "Added to collection" icon.
   *
   * @var array
   */
  protected $itemInCollectionIcon;

  /**
   * Render array for the "Add to collection" icon.
   *
   * @var array
   */
  protected $addToCollectionIcon;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Create a class instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The dependency injection container.
   *
   * @return \Drupal\damopen_image_media_styles_preview\Render\AssetPreviewListMarkup
   *   The class instance.
   */
  public static function create(ContainerInterface $container): AssetPreviewListMarkup {
    $collectionHandler = $container->has('media_collection.collection_handler')
      ? $container->get('media_collection.collection_handler')
      : NULL;

    return new static(
      $container->get('current_user'),
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('image.factory'),
      $collectionHandler
    );
  }

  /**
   * AssetPreviewListMarkup constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Image\ImageFactory $imageFactory
   *   The image factory.
   * @param \Drupal\media_collection\Service\CollectionHandler|null $collectionHandler
   *   Collection handler if it exists.
   */
  public function __construct(
    AccountInterface $currentUser,
    FormBuilderInterface $formBuilder,
    EntityTypeManagerInterface $entityTypeManager,
    ImageFactory $imageFactory,
    $collectionHandler = NULL
  ) {
    $this->currentUser = $currentUser;
    $this->formBuilder = $formBuilder;
    $this->entityTypeManager = $entityTypeManager;
    $this->imageFactory = $imageFactory;
    $this->collectionHandler = $collectionHandler;
  }

  /**
   * Initialize the collection handler features.
   *
   * @return bool
   *   TRUE if it is initialized.
   */
  protected function initCollectionHandler(): bool {
    if ($this->collectionHandler === NULL) {
      return FALSE;
    }

    if ($this->itemInCollectionIcon && $this->addToCollectionIcon) {
      return TRUE;
    }

    $this->currentCollection = $this->collectionHandler->loadCollectionForUser($this->currentUser->id());
    $modulePath = drupal_get_path('module', 'media_collection');

    if ($this->itemInCollectionIcon === NULL) {
      $this->itemInCollectionIcon = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => Url::fromUri(file_create_url("{$modulePath}/assets/added-to-collection.png"))
            ->getUri(),
          'class' => [
            'icon--item-in-collection',
          ],
        ],
      ];
    }

    if ($this->addToCollectionIcon === NULL) {
      $this->addToCollectionIcon = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => Url::fromUri(file_create_url("{$modulePath}/assets/plus-icon.svg"))
            ->getUri(),
          'class' => [
            'plus',
          ],
        ],
      ];
    }

    return TRUE;
  }

  /**
   * Return the table render array.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   *
   * @return array
   *   The render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function render(MediaInterface $media): array {
    $fieldName = $this->getFieldName($media->bundle());
    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $image */
    $image = $media->{$fieldName}->first();
    /** @var \Drupal\file\Entity\File|null $file */
    $file = $this->entityTypeManager->getStorage('file')
      ->load($image->target_id);

    if ($file === NULL) {
      $this->messenger()
        ->addMessage("The image '{$image->getName()}' was not found.", 'error');
      return [];
    }

    try {
      $derivativeImages = $this->getTableRows(
        $this->getImageUri($file),
        ImageStyleLoader::loadImageStylesList($this->entityTypeManager),
        $media
      );
    }
    catch (InvalidArgumentException $exception) {
      $this->messenger()->addMessage($exception->getMessage(), 'error');
      return [];
    }

    $form = $this->formBuilder->getForm(MediaAssetFilterForm::class);

    $build = [
      '#prefix' => render($form),
      '#theme' => 'media_display_page',
      '#rows' => $derivativeImages,
      '#title' => $media->getName(),
      '#caption' => $this->t('Social media versions of the selected asset'),
      '#attributes' => ['class' => ['social-media-assets']],
      '#attached' => [
        'library' => [
          'damopen_image_media_styles_preview/lister',
        ],
      ],
      '#metadata' => [],
    ];

    // @todo: Debug why this is not added.
    if ($this->initCollectionHandler()) {
      $build['#metadata']['media_collection']['added_to_collection_icon'] = $this->itemInCollectionIcon;
      $build['#metadata']['media_collection']['remove_from_collection_text'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('(Remove from your collection)'),
        '#attributes' => [
          'class' => 'button--remove-style-from-collection',
        ],
      ];
    }

    return $build;
  }

  /**
   * Returns the name of the field which contains the media file.
   *
   * @param string $type
   *   Entity bundle in which we search for the field.
   *
   * @return string
   *   The field name.
   */
  protected function getFieldName($type): string {
    return 'video' === $type
      ? 'thumbnail'
      : 'field_image';
  }

  /**
   * Generate derivative images and build table rows.
   *
   * @param string $imageUri
   *   The image source url.
   * @param array $styles
   *   Image styles defined in Drupal core.
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return array
   *   Generated images.
   *
   * @throws \UnexpectedValueException
   * @throws \InvalidArgumentException
   */
  protected function getTableRows($imageUri, array $styles, MediaInterface $media): array {
    // Count platform types.
    $platforms = [];
    foreach ($styles as $key => $style) {
      $tmp = explode('_', $key);
      $platform = array_shift($tmp);
      if (isset($platforms[$platform])) {
        $platforms[$platform]++;
      }
      else {
        $platforms[$platform] = 1;
      }
    }

    $modulePath = drupal_get_path('module', 'damopen_image_media_styles_preview');
    $rows = [];
    $rowNumber = 0;
    $controller = [];
    /** @var \Drupal\image\Entity\ImageStyle $style */
    foreach ($styles as $style) {
      $styleLabel = $style->label();

      // @todo: One style = One effect, but what if someone adds another?
      $styleData = [];

      if ($styleConfig = $style->getEffects()->getConfiguration()) {
        $styleData = array_values($styleConfig)[0]['data'];
      }

      $hasBadge = strpos($styleLabel, '(no badge)') ? FALSE : TRUE;

      if (empty($styleData['width']) || empty($styleData['height'])) {
        $imageSize = getimagesize($imageUri);
        $styleData['width'] = $imageSize[0];
        $styleData['height'] = $imageSize[1];
      }

      // Create URL for the thumbnails and buttons.
      $styleUrl = $style->buildUrl($imageUri);

      // Create thumbnail image element.
      $thumbnail = [
        '#theme' => 'image',
        '#uri' => $styleUrl,
        '#height' => 100,
        '#alt' => $this->t('Media asset preview for %label', ['%label' => $styleLabel]),
        '#attributes' =>  [
          'loading' => 'lazy',
        ],
      ];

      // Column 2: Image.
      $rows['images'][$rowNumber]['image'] = [
        'data' => [
          '#theme' => 'media_column_image',
          '#thumbnail' => $thumbnail,
        ],
        'class' => ['media-thumbnail'],
      ];
      $identifier = str_replace(
        ['(', ')', ',', ' '],
        ['', '', '', '-'],
        $styleLabel
      );
      $identifier = strtolower($identifier);

      // Column 3: Metadata.
      $rows['images'][$rowNumber]['metadata'] = [
        'class' => [
          'media-meta',
        ],
        'data' => [
          '#theme' => 'media_column_metadata',
          'badge' => $hasBadge,
          'identifier' => $identifier,
          '#style' => [
            'label' => $styleLabel,
            'width' => $styleData['width'],
            'height' => $styleData['height'],
          ],
        ],
      ];

      $group = explode('-', $identifier)[0];
      // @todo: This does not work (at least with S3 URLs in chrome).
      $controller[$group][$rowNumber] = [
        'label' => $styleLabel,
        'badge' => $hasBadge,
        'identifier' => $identifier,
        'style' => $styleLabel,
        'download_link' => Link::createFromRoute(
          $this->t('Download'),
          'damopen_assets_download.styled_asset_download',
          ['media' => $media->id(), 'style' => $style->id()],
          [  
            'attributes' => [
              'class' => ['button', 'button--green'],
              'target' => '_blank',
              'rel' => 'noopener',
              'download' => '',
            ]
          ]
        ),
      ];

      // This is equivalent to a "media_collection is installed" condition.
      if (
        $this->initCollectionHandler()
        && $this->currentUser->hasPermission('add media collection item entities')
      ) {
        $collectionLink = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'title' => $this->t('Add to collection'),
            'data-media-uuid' => $media->uuid(),
            'data-media-type' => $media->bundle(),
            'data-style-uuid' => $style->uuid(),
            'class' => [
              'button',
              'button--gray',
              'button--add-to-collection',
            ],
          ],
          '0' => $this->addToCollectionIcon,
          '1' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $this->t('Add to collection'),
            '#attributes' => [
              'class' => [
                'add-to-collection-text',
              ],
            ],
          ],
        ];

        /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface $collectionItem */
        if (
          $this->currentCollection !== NULL
          && ($collectionItem = $this->collectionHandler->itemWithGivenEntities($this->currentCollection, $media, $style))
        ) {
          $controller[$group][$rowNumber]['media_collection']['in_collection'] = TRUE;
          $collectionLink['#attributes']['data-collection-item-uuid'] = $collectionItem->uuid();
          $collectionLink['#attributes']['class'][] = 'style-in-collection';
        }

        // @todo: Add "Remove from collection" link?
        $controller[$group][$rowNumber]['media_collection']['add_to_collection_link'] = $collectionLink;
      }

      $svg_path = "{$modulePath}/images/social/social-{$group}.svg";

      if (file_exists($svg_path)) {
        $svg = file_get_contents($svg_path);
        $controller[$group]['icon_path'] = $svg;
      }

      switch ($group) {
        case 'original':
          $class = 'fas fa-file-image';
          break;

        case 'facebook':
          $class = 'fab fa-facebook-f';
          break;

        case 'instagram':
          $class = 'fab fa-instagram';
          break;

        case 'linkedin':
          $class = 'fab fa-linkedin';
          break;

        case 'twitter':
          $class = 'fab fa-twitter-square';
          break;

        case 'powerpoint':
          $class = 'fas fa-file-powerpoint';
          break;

        default:
          $class = 'fas fa-file-image';
      }
      // t('svg', ['svg' => $svg])
      $controller[$group]['icon_class'] = $class;
      // Disable striped class and set row attributes.
      // $rows[$rowNumber]['data-platform'] = $platformName;
      // $rows[$rowNumber]['no_striping'] = TRUE; //Appease the linters.
      $rowNumber++;
    }

    foreach ($controller as $group => $value) {
      $has_badge = 0;
      $no_badge = 0;
      $single = 0;
      $classes = '';

      foreach ($value as $link) {
        if (is_array($link) && isset($link['badge'])) {
          if (!$link['badge']) {
            $has_badge++;
          }
          else {
            $no_badge++;
          }
          $single++;
        }
      }
      if ($no_badge > 0 && $has_badge === 0) {
        $classes .= 'no-badge';
      }
      if ($single <= 2) {
        $classes .= ' single';
      }
      $controller[$group]['classes'] = $classes;
    }
    $rows['controllers'] = $controller;
    return $rows;
  }

  /**
   * Gets the URI of an image file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The image file.
   *
   * @return null|string
   *   The image source.
   *
   * @throws \InvalidArgumentException
   */
  protected function getImageUri(File $file): ?string {
    /** @var \Drupal\Core\Image\Image $imageLoaded */
    $imageLoaded = $this->imageFactory->get($file->getFileUri());

    if (!$imageLoaded->isValid()) {
      throw new InvalidArgumentException("The given file (ID {$file->id()}) is missing, or not a valid image.");
    }

    return $imageLoaded->getSource();
  }

}
