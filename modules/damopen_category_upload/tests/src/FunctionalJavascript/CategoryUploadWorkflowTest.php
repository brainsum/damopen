<?php

namespace Drupal\Tests\damopen_category_upload\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Real browser uploads with locked presets and ordinary restricted accounts.
 */
#[Group('damopen_category_upload')]
#[RunTestsInSeparateProcesses]
final class CategoryUploadWorkflowTest extends WebDriverTestBase {

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
   * {@inheritdoc}
   */
  protected static $modules = ['damopen_category_upload'];

  /**
   * Uploads batches through React and Drupal with a restricted account.
   */
  public function testCategorizedUploads(): void {
    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $switcher = $this->container->get('account_switcher');
    $switcher->switchTo($admin);
    $root = Term::create(['vid' => 'category', 'name' => 'Countries']);
    $root->save();
    $country = Term::create(['vid' => 'category', 'name' => 'Benin', 'parent' => $root->id()]);
    $country->save();
    $sibling = Term::create(['vid' => 'category', 'name' => 'Nepal', 'parent' => $root->id()]);
    $sibling->save();
    $child = Term::create(['vid' => 'category', 'name' => 'Session', 'parent' => $country->id()]);
    $child->save();
    $uploader = $this->drupalCreateUser();
    $uploader->addRole('agency')->set('field_upload_category', $country->id())->save();
    $switcher->switchBack();
    $this->drupalLogin($uploader);

    // Out-of-scope links cannot broaden access, including the old bulk form.
    foreach ([$root, $sibling] as $forbidden) {
      $this->drupalGet('upload-image', ['query' => ['category' => $forbidden->id()]]);
      $this->assertSession()->pageTextContains('You are not authorized to access this page.');
      $this->drupalGet('media/bulk-upload/image', ['query' => ['category' => $forbidden->id()]]);
      $this->assertSession()->pageTextContains('You are not authorized to access this page.');
    }

    // Ordinary upload options contain only this branch, with full paths.
    $this->drupalGet('upload-image');
    $this->assertJsCondition('window.drupalSettings.damopenCategoryUpload.categories.length === 2');
    $this->assertSame(['Countries / Benin', 'Countries / Benin / Session'], $this->getSession()->evaluateScript('window.drupalSettings.damopenCategoryUpload.categories.map(c => c.name)'));

    // The React uploader applies the link's category before any manual editing.
    $this->drupalGet('upload-image', ['query' => ['category' => $child->id()]]);
    $this->assertNotEmpty($this->assertSession()->waitForText('Upload to Countries / Benin / Session'));
    foreach (['image-1.png', 'image-2.jpg'] as $filename) {
      // Simulate a fresh file-picker selection, rather than Selenium appending
      // to the previous selection of this persistent multiple-file input.
      $this->getSession()->executeScript("document.querySelector('#damo-upload input[type=file]').value = ''");
      $input = $this->assertSession()->elementExists('css', '#damo-upload input[type=file]');
      $input->attachFile(DRUPAL_ROOT . '/core/tests/fixtures/files/' . $filename);
      $this->assertNotEmpty($this->assertSession()->waitForText(pathinfo($filename, PATHINFO_FILENAME)), $this->getSession()->getPage()->getText());
    }
    $this->assertSession()->elementsCount('css', '#damo-upload h3', 2);
    $this->getSession()->getPage()->pressButton('Send for approval');
    $success = $this->assertSession()->waitForText('All files have been sent for approval', 30000);
    $this->assertNotEmpty($success, $this->getSession()->getPage()->getText());
    $storage = $this->container->get('entity_type.manager')->getStorage('media');
    $storage->resetCache();
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uid', $uploader->id())->execute();
    $this->assertCount(2, $ids);
    foreach ($storage->loadMultiple($ids) as $media) {
      $this->assertSame($child->id(), $media->get('field_category')->target_id);
      $this->assertFalse($media->isPublished());
      $this->assertTrue($media->get('field_image')->entity->isPermanent());
    }

    $this->drupalGet('library');

    // Bypassing the dropdown and PATCHing a sibling must fail on the server.
    $payload = json_encode([
      'data' => [
        'type' => 'media--image',
        'id' => $media->uuid(),
        'relationships' => [
          'field_category' => [
            'data' => [
        ['type' => 'taxonomy_term--category', 'id' => $sibling->uuid()],
            ],
          ],
        ],
      ],
    ], JSON_THROW_ON_ERROR);
    $url = json_encode('/jsonapi/media/image/' . $media->uuid(), JSON_THROW_ON_ERROR);
    $this->getSession()->executeScript("fetch('/session/token').then(r => r.text()).then(token => fetch($url, {method: 'PATCH', headers: {'Content-Type': 'application/vnd.api+json', 'X-CSRF-Token': token}, body: JSON.stringify($payload)})).then(r => window.categoryPatchStatus = r.status)");
    $this->assertJsCondition('window.categoryPatchStatus === 422');
    $storage->resetCache();
    $this->assertSame($child->id(), $storage->load($media->id())->get('field_category')->target_id);

    // Native bulk form also applies the fixed category to every saved image.
    $this->drupalGet('media/bulk-upload/image', ['query' => ['category' => $country->id()]]);
    $page = $this->getSession()->getPage();
    $this->assertTrue($this->assertSession()->fieldExists('category[]')->hasAttribute('disabled'));
    $page->fillField('asset_name', 'Preset batch');
    $this->assertJsCondition('typeof Dropzone !== "undefined" && document.querySelector(".dropzone").dropzone !== undefined');
    foreach (['image-3.jpg', 'image-test.jpg'] as $filename) {
      $input = $this->assertSession()->elementExists('css', 'input.dz-hidden-input');
      $input->attachFile(DRUPAL_ROOT . '/core/tests/fixtures/files/' . $filename);
    }
    $this->assertJsCondition('document.querySelectorAll(".dz-success").length === 2', 30000);
    $page->pressButton('Submit');
    $this->assertSession()->pageTextContains('2 documents have been uploaded');
    $storage->resetCache();
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('name', 'Preset batch')->execute();
    $this->assertCount(2, $ids);
    foreach ($storage->loadMultiple($ids) as $media) {
      $this->assertSame($country->id(), $media->get('field_category')->target_id);
    }

    // Editing several files without a link keeps their default category when
    // only the title changes.
    $this->drupalGet('upload-image');
    foreach (['image-1.png', 'image-2.jpg'] as $filename) {
      $this->getSession()->executeScript("document.querySelector('#damo-upload input[type=file]').value = ''");
      $this->assertSession()->elementExists('css', '#damo-upload input[type=file]')->attachFile(DRUPAL_ROOT . '/core/tests/fixtures/files/' . $filename);
      $this->assertNotEmpty($this->assertSession()->waitForText(pathinfo($filename, PATHINFO_FILENAME)));
    }
    $page->pressButton('Select all');
    $this->assertNotEmpty($this->assertSession()->waitForText('2 of 2 selected'));
    $page->fillField('title', 'Bulk renamed');
    $modify = $this->assertSession()->waitForButton('Modify 2 selected items');
    $this->assertNotEmpty($modify);
    $modify->press();
    $page->pressButton('Send for approval');
    $this->assertNotEmpty($this->assertSession()->waitForText('All files have been sent for approval', 30000), $page->getText());
    $storage->resetCache();
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('name', 'Bulk renamed')->execute();
    $this->assertCount(2, $ids);
    foreach ($storage->loadMultiple($ids) as $renamed) {
      $this->assertSame($country->id(), $renamed->get('field_category')->target_id);
    }

    // A restricted reviewer editing an asset that is also filed in another
    // branch keeps that category; it is shown locked.
    $switcher->switchTo($admin);
    $media->set('field_category', [$country->id(), $sibling->id()])->save();
    $switcher->switchBack();
    // Managers edit media in damo_theme, as on a real site.
    $reviewer = $this->drupalCreateUser();
    $reviewer->addRole('manager')->set('field_upload_category', $country->id())->save();
    $this->drupalLogin($reviewer);
    $this->drupalGet('user/' . $reviewer->id());
    $this->assertSession()->pageTextContains('Countries / Benin');
    $this->drupalGet('media/' . $media->id() . '/edit');
    $this->assertSession()->elementExists('css', 'body.dam-workspace');
    $locked = $this->assertSession()->fieldExists('field_category[' . $sibling->id() . ']');
    $this->assertTrue($locked->hasAttribute('disabled'));
    $this->assertTrue($locked->isChecked());
    $page->fillField('name[0][value]', 'Reviewed metadata');
    $page->pressButton('Save');
    $this->assertSession()->pageTextNotContains('Choose your assigned upload category');
    $storage->resetCache();
    $reviewed = $storage->load($media->id());
    $this->assertSame('Reviewed metadata', $reviewed->label());
    $this->assertEqualsCanonicalizing([$country->id(), $sibling->id()], array_column($reviewed->get('field_category')->getValue(), 'target_id'));

    // The Manager role may merge terms elsewhere, but merging categories would
    // rewrite this reviewer's assignment to a broader one.
    foreach (['merge', 'merge/target', 'merge/confirm', 'reset'] as $path) {
      $this->drupalGet('admin/structure/taxonomy/manage/category/' . $path);
      $this->assertSession()->pageTextContains('You are not authorized to access this page.');
    }
    $storage->resetCache();
    $this->container->get('entity_type.manager')->getStorage('user')->resetCache();
    $this->assertSame($country->id(), $this->container->get('entity_type.manager')->getStorage('user')->load($reviewer->id())->get('field_upload_category')->target_id);

    // An unavailable assignment explains itself, even on an upload link.
    $switcher->switchTo($admin);
    $reviewer->set('field_upload_category', $child->id())->save();
    $child->setUnpublished()->save();
    $switcher->switchBack();
    $this->drupalGet('user/' . $reviewer->id());
    $this->assertSession()->pageTextContains('Your assigned upload category is no longer available.');
    $this->drupalGet('upload-image', ['query' => ['category' => $child->id()]]);
    $this->assertSession()->pageTextContains('Your assigned upload category is no longer available.');
    $this->drupalGet('media/bulk-upload/image', ['query' => ['category' => $child->id()]]);
    $this->assertSession()->pageTextContains('Your assigned upload category is no longer available.');

    // Parent filters include published descendant assets, but never drafts.
    $switcher->switchTo($admin);
    $reviewed->setPublished()->save();
    $index = \Drupal\search_api\Entity\Index::load('database_media');
    $index->indexItems();
    $switcher->switchBack();

    // Read-only staff may browse empty branches, but may not upload.
    $reader = $this->drupalCreateUser(['view media']);
    $this->drupalLogin($reader);
    $this->drupalGet('library/' . $root->id());
    $this->assertSession()->addressEquals('assets?f%5B0%5D=asset_category%3A' . $root->id());
    $options = $this->getSession()->getPage()->findAll('css', '.dam-category-browser option');
    $labels = implode(' ', array_map(static fn($option) => strip_tags($option->getHtml()), $options));
    $this->assertStringContainsString('Benin', $labels);
    $this->assertStringContainsString('Nepal', $labels);
    $this->assertSession()->linkNotExists('Upload images here');
    $this->assertSession()->pageTextContains('Reviewed metadata');
    $this->assertSession()->pageTextNotContains('Bulk renamed');
    $this->drupalGet('upload-image', ['query' => ['category' => $country->id()]]);
    $this->assertSession()->pageTextContains('You are not authorized to access this page.');
  }

}
