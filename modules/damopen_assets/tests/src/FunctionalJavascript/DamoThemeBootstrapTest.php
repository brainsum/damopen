<?php

namespace Drupal\Tests\damopen_assets\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Damo theme's Bootstrap plugins run with Drupal 11's jQuery 4.
 */
#[Group('damopen')]
#[RunTestsInSeparateProcesses]
final class DamoThemeBootstrapTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'damopen';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'damo_theme';

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Opens the mobile menu, a dropdown and a tooltip without console errors.
   */
  public function testBootstrapPlugins(): void {
    $this->drupalLogin($this->drupalCreateUser(['view media']));
    $this->getSession()->resizeWindow(480, 900);
    $this->drupalGet('<front>');
    $session = $this->getSession();
    $this->assertSame(4, (int) $session->evaluateScript('jQuery.fn.jquery.split(".")[0]'));
    foreach (['collapse', 'dropdown', 'tooltip', 'popover'] as $plugin) {
      $this->assertTrue($session->evaluateScript("typeof jQuery.fn.$plugin === 'function'"), $plugin);
    }

    // The theme's mobile navigation toggle.
    $toggle = $this->assertSession()->elementExists('css', '.navbar-toggler[data-toggle="collapse"]');
    $this->assertTrue($toggle->isVisible());
    $toggle->click();
    $this->assertJsCondition('document.querySelector(".navbar-toggler").getAttribute("aria-expanded") === "true"');

    // Menu dropdown and tooltip markup as the theme's templates render it.
    $session->executeScript(<<<'JS'
      const markup = document.createElement('div');
      markup.id = 'bootstrap-probe';
      markup.innerHTML = '<div class="dropdown"><a href="#" data-target="#" data-toggle="dropdown" aria-expanded="false" role="button">Menu</a><ul class="dropdown-menu"><li>Item</li></ul></div>'
        + '<span data-toggle="tooltip" title="Probe tooltip">Hint</span>';
      document.body.prepend(markup);
      Drupal.attachBehaviors(markup, drupalSettings);
    JS);
    $this->assertSession()->elementExists('css', '#bootstrap-probe [data-toggle="dropdown"]')->click();
    $this->assertJsCondition('document.querySelector("#bootstrap-probe .dropdown").classList.contains("open") || document.querySelector("#bootstrap-probe .dropdown").classList.contains("show")');
    $session->executeScript('jQuery("#bootstrap-probe [data-toggle=tooltip]").tooltip("show")');
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.tooltip'));
    $this->assertStringContainsString('Probe tooltip', $this->assertSession()->elementExists('css', '.tooltip')->getText());
  }

  /**
   * Opens and auto-closes a popover with the theme's popover setting enabled.
   */
  public function testPopoverIntegration(): void {
    // The base theme's defaults include a Bootstrap 3 "auto" placement.
    $this->config('damo_theme.settings')
      ->set('popover_enabled', 1)
      ->set('popover_auto_close', 1)
      ->set('popover_placement', 'auto right')
      ->set('popover_trigger', 'click')
      ->save();
    $this->drupalLogin($this->drupalCreateUser(['view media']));
    $this->drupalGet('<front>');
    $session = $this->getSession();
    $this->assertTrue($session->evaluateScript('Drupal.behaviors.bootstrapPopovers !== undefined'));
    $session->executeScript(<<<'JS'
      const markup = document.createElement('div');
      markup.id = 'popover-probe';
      markup.style.cssText = 'position: relative; z-index: 1000; margin: 150px 20px;';
      markup.innerHTML = '<button type="button" data-toggle="popover" data-content="Probe content" data-html="true" data-template="&lt;div class=&quot;popover injected&quot;&gt;&lt;/div&gt;">Help</button>'
        + '<p style="margin-top: 300px"><button type="button" id="popover-outside">Outside</button></p>';
      document.body.prepend(markup);
      Drupal.attachBehaviors(markup, drupalSettings);
    JS);
    $this->assertSession()->elementExists('css', '#popover-probe [data-toggle="popover"]')->click();
    $popover = $this->assertSession()->waitForElementVisible('css', '.popover');
    $this->assertNotEmpty($popover);
    $this->assertStringContainsString('Probe content', $popover->getText());
    // Markup options come from theme settings, not from the element's data.
    $this->assertSession()->elementNotExists('css', '.popover.injected');
    // Auto-close hides it when clicking elsewhere.
    $this->assertSession()->elementExists('css', '#popover-outside')->click();
    $this->assertJsCondition('document.querySelector(".popover") === null || getComputedStyle(document.querySelector(".popover")).opacity === "0"');
  }

}
