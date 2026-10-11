<?php

namespace Drupal\Tests\damopen_assets\FunctionalJavascript;

use Drupal\Core\File\FileExists;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\search_api\Entity\Index;
use Drupal\taxonomy\Entity\Term;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Declines an asset with a reason and resubmits it as the uploader.
 */
#[Group('damopen_assets')]
#[RunTestsInSeparateProcesses]
class AssetRejectionWorkflowTest extends WebDriverTestBase {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'damopen';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'damo_theme';

  /**
   * Existing Bootstrap/media_upload configuration has incomplete schemas.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The theme's Bootstrap 4 alpha tooltips throw under jQuery 4 on edit forms.
   *
   * @var bool
   */
  protected $failOnJavascriptConsoleErrors = FALSE;

  /**
   * Covers the reviewer's decline dialog and the uploader's view of it.
   */
  public function testDeclineWithReason(): void {
    // Saving an image media validates its file through the image toolkit.
    $this->config('system.image')->set('toolkit', 'gd')->save();
    $uploader = $this->drupalCreateUser();
    $uploader->addRole('agency')->save();
    $reviewer = $this->drupalCreateUser();
    $reviewer->addRole('manager')->save();

    $category = Term::create(['vid' => 'category', 'name' => 'Rejection test category']);
    $category->save();
    $image = file_get_contents(DRUPAL_ROOT . '/core/tests/fixtures/files/image-1.png');
    $file = $this->container->get('file.repository')->writeData($image, 'private://rejection-test.png', FileExists::Replace);
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Cropped logo',
      'uid' => $uploader->id(),
      'status' => FALSE,
      'field_image' => ['target_id' => $file->id(), 'alt' => 'Cropped logo'],
      'field_category' => [$category->id()],
    ]);
    $media->save();
    $this->indexItems();

    // The reviewer declines the asset with a reason.
    $this->drupalLogin($reviewer);
    $this->drupalGet('admin/assets/all-unpublished');
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();
    $assert->waitForText('Waiting for approval (1)');
    // Select the card; its overlay covers the image.
    $assert->waitForElementVisible('css', '#damo-wfa img[alt="Cropped logo"]')->getParent()->click();
    $page->pressButton('Decline');
    $assert->waitForElementVisible('css', '.chakra-modal__content textarea')
      ->setValue('The logo is cropped.');
    $assert->pageTextContains('Decline 1 item');
    $assert->elementExists('css', '.chakra-modal__footer button:last-child')->click();
    $this->assertTrue($assert->waitForText('Rejected (1)'));
    $assert->pageTextContains('Waiting for approval (0)');

    $media = $this->reload($media);
    $this->assertFalse($media->isPublished());
    $this->assertTrue((bool) $media->get('rejected')->value);
    $this->assertSame('The logo is cropped.', $media->get('rejection_reason')->value);
    $mails = $this->getMails(['id' => 'damopen_assets_asset_rejected']);
    $this->assertCount(1, $mails);
    $this->assertSame($uploader->getEmail(), $mails[0]['to']);
    $this->assertStringContainsString('The logo is cropped.', $mails[0]['body']);

    // The uploader sees the reason but cannot review.
    $this->drupalLogin($uploader);
    $this->drupalGet('admin/assets/my-unpublished');
    $assert->waitForText('My assets waiting for approval');
    $assert->buttonNotExists('Approve selected items');
    $page->pressButton('Rejected (1)');
    $card = $assert->waitForElementVisible('css', '#damo-wfa img[alt="Cropped logo"]');
    $assert->pageTextContains('Rejected');
    $card->mouseOver();
    $page->pressButton('Details');
    $assert->waitForText('Rejection reason:');
    $assert->pageTextContains('The logo is cropped.');

    // Saving the edit form sends the asset for approval again.
    $this->drupalGet($media->toUrl('edit-form'));
    $assert->pageTextContains('A reviewer rejected this asset: The logo is cropped.');
    $page->pressButton('Save and resubmit');
    $media = $this->reload($media);
    $this->assertFalse($media->isPublished());
    $this->assertFalse((bool) $media->get('rejected')->value);
    $this->assertNull($media->get('rejection_reason')->value);
  }

  /**
   * Indexes the search items that back the approval views.
   */
  protected function indexItems(): void {
    foreach (Index::loadMultiple() as $index) {
      if ($index->status()) {
        $index->indexItems();
      }
    }
  }

  /**
   * Loads the stored version of an asset.
   */
  protected function reload(MediaInterface $media): MediaInterface {
    return $this->container->get('entity_type.manager')
      ->getStorage('media')
      ->loadUnchanged($media->id());
  }

}
