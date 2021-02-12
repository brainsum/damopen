<?php

namespace Drupal\media_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\media_collection\Service\CollectionHandler;
use Drupal\user\UserInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class CollectionController.
 *
 * @package Drupal\media_collection\Controller
 */
class CollectionController extends ControllerBase {

  /**
   * The collection handler service.
   *
   * @var \Drupal\media_collection\Service\CollectionHandler
   */
  private $handler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_collection.collection_handler')
    );
  }

  /**
   * CollectionController constructor.
   *
   * @param \Drupal\media_collection\Service\CollectionHandler $handler
   *   The collection handler service.
   */
  public function __construct(
    CollectionHandler $handler
  ) {
    $this->handler = $handler;
  }

  /**
   * Route callback.
   *
   * @return array
   *   The render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function currentUserCollection(): array {
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());

    if ($user === NULL) {
      return [
        '#markup' => $this->t('The current account could not be loaded.'),
      ];
    }

    return $this->handler->renderCollection($user);
  }

  /**
   * Route callback.
   *
   * @param \Drupal\user\UserInterface $user
   *   The given user.
   *
   * @return array
   *   The render array.
   */
  public function givenUserCollection(UserInterface $user): array {
    return $this->handler->renderCollection($user);
  }

  /**
   * Title callback for ::givenUserCollection().
   *
   * @param \Drupal\user\UserInterface $user
   *   The given user.
   *
   * @return string
   *   The title.
   */
  public function givenUserCollectionTitle(UserInterface $user): string {
    return "Collection for {$user->getDisplayName()}";
  }

  /**
   * Callback for clearing the collection of the current user.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the current user's collection page.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function clearCollectionForCurrentUser(): RedirectResponse {
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());

    if ($user === NULL) {
      throw new NotFoundHttpException("The current user (ID: {$this->currentUser()->id()}) could not be loaded.");
    }

    try {
      $this->handler->clearCollectionForUser($user);
    }
    catch (RuntimeException $exception) {
      throw new HttpException(500, $exception->getMessage());
    }

    $collectionUrl = Url::fromRoute(
      'media_collection.collection.current_user',
      [],
      [
        'absolute' => TRUE,
      ]
    )
      ->toString(TRUE)
      ->getGeneratedUrl();

    return new RedirectResponse($collectionUrl);
  }

  /**
   * Callback for clearing the collection of the given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The given user.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the given user's collection page.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function clearCollectionForGivenUser(UserInterface $user): RedirectResponse {
    try {
      $this->handler->clearCollectionForUser($user);
    }
    catch (RuntimeException $exception) {
      throw new HttpException(500, $exception->getMessage());
    }

    $collectionUrl = Url::fromRoute(
      'media_collection.collection.given_user',
      [
        'user' => $user->id(),
      ],
      [
        'absolute' => TRUE,
      ]
    )
      ->toString(TRUE)
      ->getGeneratedUrl();

    return new RedirectResponse($collectionUrl);
  }

}
