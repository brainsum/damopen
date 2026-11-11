<?php

namespace Drupal\damopen_common\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Keeps content managers in the product workspace, including review forms.
 */
final class WorkspaceThemeNegotiator implements ThemeNegotiatorInterface {

  public function __construct(private AccountInterface $account, private ConfigFactoryInterface $configFactory) {}

  public function applies(RouteMatchInterface $route_match): bool {
    return in_array('manager', $this->account->getRoles(), TRUE)
      && !$this->account->hasPermission('view the administration theme');
  }

  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    return $this->configFactory->get('system.theme')->get('default');
  }

}
