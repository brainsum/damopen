<?php

namespace Drupal\Tests\damopen_category_upload\Kernel;

use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Exercises category boundaries across validation and direct entity saves.
 */
#[Group('damopen_category_upload')]
#[RunTestsInSeparateProcesses]
final class CategoryUploadTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'file', 'image', 'media',
    'media_test_source', 'taxonomy', 'text', 'damopen_category_upload',
    'term_reference_change', 'term_merge',
  ];
  /**
   * Administrator account.
   */
  private UserInterface $admin;
  /**
   * Assigned uploader account.
   */
  private UserInterface $uploader;
  /**
   * Category fixtures by name.
   */
  private array $terms;
  /**
   * Test media bundle.
   */
  private string $bundle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'file', 'media', 'taxonomy_term'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'user', 'field', 'media']);
    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();
    $this->container->get('module_handler')->loadInclude('damopen_category_upload', 'install');
    damopen_category_upload_install();
    $this->admin = $this->createUser(['administer upload categories', 'bypass category upload restrictions']);
    $this->container->get('current_user')->setAccount($this->admin);
    foreach ([
      'Countries' => NULL,
      'Africa' => 'Countries',
      'Benin' => 'Africa',
      'Village' => 'Benin',
      'Asia' => 'Countries',
    ] as $name => $parent) {
      $this->terms[$name] = Term::create([
        'vid' => 'category',
        'name' => $name,
        'parent' => $parent ? $this->terms[$parent]->id() : 0,
      ]);
      $this->terms[$name]->save();
    }
    $this->uploader = $this->createUser(['administer users'], 'scoped_manager');
    $this->uploader->set('field_upload_category', $this->terms['Benin']->id())->save();
    $this->bundle = $this->createMediaType('test')->id();
    FieldStorageConfig::create([
      'field_name' => 'field_category',
      'entity_type' => 'media',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_category',
      'entity_type' => 'media',
      'bundle' => $this->bundle,
      'settings' => ['handler_settings' => ['target_bundles' => ['category' => 'category']]],
    ])->save();
    $this->container->get('current_user')->setAccount($this->uploader);
  }

  /**
   * Allows descendants, rejects ancestors and siblings, and permits admins.
   */
  public function testScopeAndAdminBypass(): void {
    $policy = $this->container->get('damopen_category_upload.policy');
    foreach (['Benin', 'Village'] as $name) {
      $this->assertTrue($policy->allowed((int) $this->terms[$name]->id()));
      $media = $this->asset([$name]);
      $media->save();
      $this->assertFalse($media->isNew());
    }
    foreach ([[], ['Countries'], ['Africa'], ['Asia'], ['Benin', 'Asia']] as $names) {
      $media = $this->asset($names);
      $this->assertNotNull($policy->violation($media));
      $violations = iterator_to_array($media->validate());
      $this->assertNotEmpty(array_filter($violations, fn($v) => str_contains($v->getMessage(), 'assigned')));
      try {
        $media->save();
        $this->fail('An invalid category must not be saved.');
      }
      catch (EntityStorageException $e) {
        $this->assertStringContainsString('assigned', $e->getMessage());
      }
    }
    $this->container->get('current_user')->setAccount($this->admin);
    $this->assertTrue($policy->allowed((int) $this->terms['Countries']->id()));
    $this->asset(['Asia'])->save();
  }

  /**
   * Prevents category escalation while allowing existing assets to be reviewed.
   */
  public function testReassignmentAndReview(): void {
    $media = $this->asset(['Benin']);
    $media->save();
    $media->set('field_category', [$this->terms['Countries']->id()]);
    $this->assertNotNull($this->container->get('damopen_category_upload.policy')->violation($media));
    $this->container->get('current_user')->setAccount($this->admin);
    $media->save();
    $this->container->get('current_user')->setAccount($this->uploader);
    $media = $this->container->get('entity_type.manager')->getStorage('media')->loadUnchanged($media->id());
    $media->setName('Review metadata only');
    $this->assertNull($this->container->get('damopen_category_upload.policy')->violation($media));
    $media->save();
  }

  /**
   * Enforces link presets and exposes full category paths.
   */
  public function testPresetAndDuplicateNames(): void {
    $policy = $this->container->get('damopen_category_upload.policy');
    $request = Request::create('/upload-image', 'GET', ['category' => $this->terms['Benin']->id()]);
    $request->attributes->set('_route', 'damopen_upload.upload');
    $this->container->get('request_stack')->push($request);
    $settings = $policy->settings();
    $this->assertCount(1, $settings['categories']);
    $this->assertSame('Countries / Africa / Benin', $settings['categories'][0]['name']);
    $this->assertNull($policy->violation($this->asset(['Benin'])));
    $this->assertNotNull($policy->violation($this->asset(['Village'])));
    $request->query->set('category', $this->terms['Asia']->id());
    try {
      $policy->settings();
      $this->fail('A link must not grant access to a different branch.');
    }
    catch (AccessDeniedHttpException $e) {
      $this->assertSame(403, $e->getStatusCode());
    }
    finally {
      $this->container->get('request_stack')->pop();
    }

    // Other pages use ?category= for themselves, such as a Views filter.
    $this->container->get('request_stack')->push(Request::create('/assets', 'GET', ['category' => 'All']));
    $this->assertNull($policy->preset());
    $this->assertNull($policy->violation($this->asset(['Village'])));
    $this->container->get('request_stack')->pop();

    // An out-of-scope API preset is a validation error (JSON:API 422).
    $this->container->get('request_stack')->push(Request::create('/jsonapi/media/test?uploadCategory=' . $this->terms['Asia']->id(), 'POST'));
    $this->assertSame('You cannot upload to this category.', $policy->violation($this->asset(['Benin'])));
    $this->container->get('request_stack')->pop();
  }

  /**
   * Keeps upload assignments admin-only without breaking programmatic saves.
   */
  public function testProfileAssignmentIsAdminOnly(): void {
    $this->assertFalse($this->uploader->get('field_upload_category')->access('edit', $this->uploader));
    $this->assertTrue($this->uploader->get('field_upload_category')->access('edit', $this->admin));
    // Drush, cron and migrations run as anonymous and must still save users.
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $this->uploader->set('field_upload_category', $this->terms['Village']->id())->save();
    $this->assertSame($this->terms['Village']->id(), $this->uploader->get('field_upload_category')->target_id);
  }

  /**
   * Prevents moving other branches into the allowed upload subtree.
   */
  public function testHierarchyIsAdminOnly(): void {
    foreach (['update', 'delete'] as $operation) {
      $this->assertFalse($this->terms['Asia']->access($operation, $this->uploader));
      $this->assertTrue($this->terms['Asia']->access($operation, $this->admin));
    }
    $this->assertFalse($this->container->get('entity_type.manager')->getAccessControlHandler('taxonomy_term')->createAccess('category', $this->uploader));
    // Programmatic saves (drush deploy, migrations, cron) are not blocked.
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $this->terms['Asia']->setName('Asia and Pacific')->save();
    $this->terms['Asia']->delete();
  }

  /**
   * Merging and reordering the Library need the category administration right.
   */
  public function testBulkStructureChangesAreAdminOnly(): void {
    $this->container->get('router.builder')->rebuild();
    // Mirrors the shipped Manager role's taxonomy permissions.
    $manager = $this->createUser([
      'merge taxonomy terms',
      'edit terms in category',
      'delete terms in category',
      'access taxonomy overview',
    ]);
    $access = $this->container->get('access_manager');
    $vocabulary = Vocabulary::load('category');
    foreach (['merge_form', 'merge_target', 'merge_confirm'] as $route) {
      $this->assertFalse($access->checkNamedRoute('entity.taxonomy_vocabulary.' . $route, ['taxonomy_vocabulary' => 'category'], $manager), $route);
      $this->assertTrue($access->checkNamedRoute('entity.taxonomy_vocabulary.' . $route, ['taxonomy_vocabulary' => 'category'], $this->admin), $route);
    }
    // Other vocabularies keep Term Merge's own rules.
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $tagger = $this->createUser(['merge taxonomy terms', 'edit terms in tags']);
    $this->assertTrue($access->checkNamedRoute('entity.taxonomy_vocabulary.merge_form', ['taxonomy_vocabulary' => 'tags'], $tagger));
    // Drag-and-drop reordering can change parents too.
    $this->assertFalse($vocabulary->access('reset all weights', $manager));
    $this->assertTrue($vocabulary->access('reset all weights', $this->admin));
    $this->assertFalse($this->terms['Asia']->access('revert', $manager));
  }

  /**
   * A restricted reviewer editing an asset filed elsewhere keeps categories.
   */
  public function testRestrictedEditKeepsOtherCategories(): void {
    $policy = $this->container->get('damopen_category_upload.policy');
    $storage = $this->container->get('entity_type.manager')->getStorage('media');
    $this->container->get('current_user')->setAccount($this->admin);
    $media = $this->asset(['Asia', 'Benin']);
    $media->save();
    $this->container->get('current_user')->setAccount($this->uploader);

    // Form and JSON:API values differ from storage in type, keys and order.
    $media = $storage->loadUnchanged($media->id());
    $media->set('field_category', [
      ['target_id' => (int) $this->terms['Benin']->id(), '_weight' => 0],
      ['target_id' => (string) $this->terms['Asia']->id(), '_weight' => 1],
    ]);
    $media->setName('Metadata only');
    $this->assertNull($policy->violation($media));
    $media->save();

    // Adding an in-scope category keeps the out-of-scope one.
    $media = $storage->loadUnchanged($media->id());
    $media->set('field_category', $this->ids(['Asia', 'Benin', 'Village']));
    $this->assertNull($policy->violation($media));
    $media->save();

    // Removing or adding another branch's category is rejected.
    $media = $storage->loadUnchanged($media->id());
    $media->set('field_category', [$this->terms['Benin']->id()]);
    $this->assertSame('You cannot remove categories outside your assigned category.', $policy->violation($media));
    $media->set('field_category', $this->ids(['Asia', 'Benin', 'Africa']));
    $this->assertStringContainsString('assigned category and its descendants', $policy->violation($media));
  }

  /**
   * A deleted or unpublished assignment fails closed with an explanation.
   */
  public function testUnavailableAssignment(): void {
    $policy = $this->container->get('damopen_category_upload.policy');
    $this->assertNull($policy->assignmentError());
    $this->container->get('current_user')->setAccount($this->admin);
    $this->terms['Benin']->setUnpublished()->save();
    $this->container->get('current_user')->setAccount($this->uploader);
    $this->assertStringContainsString('no longer available', $policy->assignmentError());
    $this->assertSame([], $policy->options());
    $this->assertStringContainsString('no longer available', $policy->violation($this->asset(['Village'])));
    $this->assertStringContainsString('no longer available', $policy->settings()['assignmentError']);

    // An upload link explains the problem instead of denying access.
    $link = Request::create('/upload-image', 'GET', ['category' => $this->terms['Village']->id()]);
    $link->attributes->set('_route', 'damopen_upload.upload');
    $this->container->get('request_stack')->push($link);
    $settings = $policy->settings();
    $this->container->get('request_stack')->pop();
    $this->assertStringContainsString('no longer available', $settings['assignmentError']);
    $this->assertSame([], $settings['categories']);
    $this->assertNull($settings['presetId']);

    $this->container->get('current_user')->setAccount($this->admin);
    $this->terms['Benin']->delete();
    $this->container->get('current_user')->setAccount($this->uploader);
    $this->assertStringContainsString('no longer available', $policy->assignmentError());
  }

  /**
   * Returns category fixture IDs by name.
   */
  private function ids(array $names): array {
    return array_map(fn($name) => $this->terms[$name]->id(), $names);
  }

  /**
   * Creates an unsaved media entity for the given test categories.
   */
  private function asset(array $names): Media {
    return Media::create([
      'bundle' => $this->bundle,
      'name' => 'Scope test',
      'uid' => $this->uploader->id(),
      'field_category' => $this->ids($names),
    ]);
  }

}
