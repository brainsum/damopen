<?php

namespace Drupal\damopen_assets_api\Authentication\Provider;

use Drupal\basic_auth\Authentication\Provider\BasicAuth;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\UserAuthInterface;
use Symfony\Component\HttpFoundation\Request;
use function reset;

/**
 * Exclude HTTP Basic authentication.
 */
class BasicAuthWithExclude extends BasicAuth {

  /**
   * Route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * User storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    UserAuthInterface $user_auth,
    FloodInterface $flood,
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $routeMatch,
    ModuleHandlerInterface $moduleHandler
  ) {
    parent::__construct($config_factory, $user_auth, $flood, $entity_type_manager);

    $this->routeMatch = $routeMatch;
    $this->moduleHandler = $moduleHandler;
    $this->userStorage = $entity_type_manager->getStorage('user');
  }

  /**
   * Determines if a given route is a JSON:API route or not.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   *
   * @return bool
   *   TRUE, if this is a JSON:API route.
   */
  protected function isJsonApiRoute(RouteMatchInterface $routeMatch): bool {
    $route = $routeMatch->getRouteObject();

    if ($route === NULL) {
      return FALSE;
    }

    return $route->getDefault('_is_jsonapi') ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    if ($this->isJsonApiRoute($this->routeMatch)) {
      return TRUE;
    }

    return parent::applies($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    // Run authenticate only for non LDAP users.
    if (!$this->moduleHandler->moduleExists('ldap_user')) {
      return parent::authenticate($request);
    }

    $username = $request->headers->get('PHP_AUTH_USER');
    /** @var \Drupal\user\UserInterface[] $accounts */
    $accounts = $this->userStorage
      ->loadByProperties(['name' => $username, 'status' => 1]);
    $account = reset($accounts);

    if ($account && $account->hasField('ldap_user_puid') && !$account->get('ldap_user_puid')->value) {
      return parent::authenticate($request);
    }

    return NULL;
  }

}
