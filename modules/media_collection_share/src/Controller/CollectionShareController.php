<?php

namespace Drupal\media_collection_share\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\media_collection_share\Service\CollectionSharer;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use function reset;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class CollectionShareController.
 *
 * @package Drupal\media_collection_share\Controller
 */
class CollectionShareController extends ControllerBase {

  private $sharedStorage;
  private $sharedViewBuilder;
  private $sharer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('shared_media_collection'),
      $container->get('entity_type.manager')->getViewBuilder('shared_media_collection'),
      $container->get('media_collection_share.collection_sharer')
    );
  }

  /**
   * CollectionShare constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $sharedStorage
   *   Storage for Shared collections.
   * @param \Drupal\Core\Entity\EntityViewBuilderInterface $sharedViewBuilder
   *   View builder for Shared collections.
   * @param \Drupal\media_collection_share\Service\CollectionSharer $sharer
   *   Sharer service for collections.
   */
  public function __construct(
    EntityStorageInterface $sharedStorage,
    EntityViewBuilderInterface $sharedViewBuilder,
    CollectionSharer $sharer
  ) {
    $this->sharedStorage = $sharedStorage;
    $this->sharedViewBuilder = $sharedViewBuilder;
    $this->sharer = $sharer;
  }

  /**
   * Share callback.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function share(): JsonResponse {
    try {
      $sharedCollection = $this->sharer->createSharedCollectionForUser((int) $this->currentUser()->id());
      $data = [
        'message' => 'OK',
        'code' => Response::HTTP_OK,
        'is_new' => $sharedCollection->isNew(),
        'share_url' => [
          'relative' => $sharedCollection->shareUrl(),
          'absolute' => $sharedCollection->shareAbsoluteUrl(),
        ],
      ];

      return new JsonResponse($data);
    }
    catch (RuntimeException $exception) {
      return new JsonResponse([
        'message' => 'Sharing the collection failed.',
        'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
        'reason' => $exception->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    catch (EntityStorageException $exception) {
      return new JsonResponse([
        'message' => 'Sharing the collection failed.',
        'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
        'reason' => $exception->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Shared collection callback.
   *
   * @param string $date
   *   The date.
   * @param string $uuid
   *   The uuid.
   *
   * @return array
   *   Render array.
   *
   * @throws \Exception
   */
  public function sharedCollection(string $date, string $uuid): array {
    $collections = $this->sharedStorage->loadByProperties([
      'uuid' => $uuid,
    ]);

    if (empty($collections)) {
      throw new NotFoundHttpException("No shared collection was found for {$date}.");
    }

    /** @var \Drupal\media_collection_share\Entity\SharedMediaCollectionInterface $collection */
    $collection = reset($collections);
    return $this->sharedViewBuilder->view($collection);
  }

  /**
   * Email callback.
   *
   * @return array
   *   Render array.
   */
  public function email(): array {
    return [];
  }

}
