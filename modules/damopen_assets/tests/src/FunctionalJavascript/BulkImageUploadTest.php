<?php

namespace Drupal\Tests\damopen_assets\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\Media;
use Drupal\search_api\Entity\Index;
use Drupal\taxonomy\Entity\Term;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises real Dropzone uploads, form submission and populated library cards.
 */
#[Group('damopen_upload')]
#[RunTestsInSeparateProcesses]
class BulkImageUploadTest extends WebDriverTestBase {

  protected $profile = 'damopen';

  protected $defaultTheme = 'damo_theme';

  // Existing Bootstrap/media_upload configuration has incomplete schemas.
  protected $strictConfigSchema = FALSE;

  /**
   * Uploads two distinct images in one form submission.
   */
  public function testMultipleImageUpload(): void {
    $account = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($account);
    $category = Term::create(['vid' => 'category', 'name' => 'Upload test category']);
    $category->save();

    $this->drupalGet('media/bulk-upload/image');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldExists('asset_name');
    $page->fillField('asset_name', 'Browser upload regression');
    $page->selectFieldOption('category[]', $category->id());
    $this->assertJsCondition('typeof Dropzone !== "undefined" && document.querySelector(".dropzone").dropzone !== undefined');

    $fixtures = ['image-1.png', 'image-2.jpg'];
    foreach ($fixtures as $filename) {
      // Use Dropzone's real file input, which is replaced after each selection.
      $input = $this->assertSession()->elementExists('css', 'input.dz-hidden-input');
      $input->attachFile(DRUPAL_ROOT . '/core/tests/fixtures/files/' . $filename);
    }
    $this->assertJsCondition('document.querySelectorAll(".dz-success").length === 2', 30000);
    $this->assertSession()->elementsCount('css', '.dz-error', 0);
    $page->pressButton('Submit');
    $this->assertSession()->pageTextContains('2 documents have been uploaded');
    $this->assertSession()->elementNotExists('css', '.messages--error');

    $storage = $this->container->get('entity_type.manager')->getStorage('media');
    $storage->resetCache();
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('bundle', 'image')
      ->condition('name', 'Browser upload regression')->execute();
    $this->assertCount(2, $ids, 'One media entity was created for each uploaded image.');
    $assets = Media::loadMultiple($ids);
    $filenames = [];
    foreach ($assets as $asset) {
      $this->assertSame($account->id(), $asset->getOwnerId());
      $this->assertSame($category->id(), $asset->get('field_category')->target_id);
      $file = $asset->get('field_image')->entity;
      $this->assertNotNull($file);
      $this->assertTrue($file->isPermanent());
      $this->assertStringStartsWith('private://', $file->getFileUri());
      $filenames[] = $file->getFilename();
      $this->assertSame(
        hash_file('sha256', DRUPAL_ROOT . '/core/tests/fixtures/files/' . $file->getFilename()),
        hash_file('sha256', $file->getFileUri()),
        'The saved file matches the selected image.'
      );
    }
    $this->assertEqualsCanonicalizing($fixtures, $filenames);

    // Search indexing is normally done by cron; make it deterministic here.
    foreach (Index::loadMultiple() as $index) {
      if ($index->status()) {
        $index->indexItems();
      }
    }
    $this->drupalGet('assets');
    // Card titles and controls are hidden by CSS until hover.
    $this->assertSession()->responseContains('Browser upload regression');
    foreach ($assets as $asset) {
      $this->assertSession()->elementExists('css', 'a[href*="/media/' . $asset->id() . '/edit"][aria-label="Edit"]');
      $this->assertSession()->elementExists('css', 'a[href*="/media/' . $asset->id() . '/delete"][aria-label="Delete"]');
    }
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
  }

}
