<?php

namespace Drupal\Tests\media_collection\Functional;

use Drupal\Core\Url;
use Drupal\Core\File\FileExists;
use Drupal\media\Entity\Media;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 */
#[Group('media_collection')]
#[RunTestsInSeparateProcesses]
class LoadTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['media_collection'];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'damo_theme';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'damopen';

  /**
   * Existing contributed config (media_upload, Bootstrap) lacks full schema.
   *
   * This test verifies profile installation and page rendering, not schemas.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->user = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests profile rendering and private image downloads as an administrator.
   */
  public function testLoad(): void {
    $this->drupalGet(Url::fromRoute('<front>'));
    $this->assertSession()->statusCodeEquals(200);

    // Exercise the asset lifecycle against a fresh Drupal 11 installation.
    $image = file_get_contents(DRUPAL_ROOT . '/core/tests/fixtures/files/image-1.png');
    $file = $this->container->get('file.repository')->writeData($image, 'private://drupal11-test.png', FileExists::Replace);
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Drupal 11 test image',
      'uid' => $this->user->id(),
      'status' => TRUE,
      'field_image' => ['target_id' => $file->id(), 'alt' => 'Test image'],
    ]);
    $media->save();

    // Uploaded media are rendered as library cards, including icon-only actions.
    $second_media = $media->createDuplicate();
    $second_media->setName('Second Drupal 11 test image');
    $second_media->save();
    $switcher = $this->container->get('account_switcher');
    $switcher->switchTo($this->user);
    try {
      $this->container->get('theme.manager')->setActiveTheme(
        $this->container->get('theme.initialization')->getActiveThemeByName('damo_theme')
      );
      $builder = $this->container->get('entity_type.manager')->getViewBuilder('media');
      $cards = $builder->viewMultiple([$media, $second_media], 'media_library');
      $html = (string) $this->container->get('renderer')->renderInIsolation($cards);
      foreach ([$media, $second_media] as $asset) {
        $this->assertStringContainsString('/media/' . $asset->id() . '/edit', $html);
        $this->assertStringContainsString('/media/' . $asset->id() . '/delete', $html);
      }
    }
    finally {
      $switcher->switchBack();
    }

    $this->drupalGet('media/' . $media->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('asset-download/' . $media->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame($image, $this->getSession()->getPage()->getContent());
  }

}
