<?php

namespace Drupal\Tests\damopen_assets\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers rejecting assets with a reason for the uploader.
 */
#[Group('damopen_assets')]
#[RunTestsInSeparateProcesses]
class AssetRejectionTest extends KernelTestBase {

  use AssertMailTrait;
  use MediaTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'damopen_assets',
    'field',
    'file',
    'image',
    'media',
    'media_test_source',
    'system',
    'user',
  ];

  /**
   * The media type used for the assets.
   */
  protected string $mediaTypeId;

  /**
   * The account that uploaded the assets.
   */
  protected UserInterface $uploader;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['field', 'media', 'system', 'user']);
    $this->config('system.site')->set('name', 'DAMopen test')->save();
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();
    $this->container->get('router.builder')->rebuild();

    $this->mediaTypeId = $this->createMediaType('test')->id();
    // Reserve user 1, whose permissions bypass every check.
    $this->createUser();
    $this->uploader = $this->createUser([], 'uploader');
    $this->setUpCurrentUser([], ['publish any media', 'update any media']);
  }

  /**
   * Rejects a pending asset and checks the stored reason and the email.
   */
  public function testRejectionNotifiesUploader(): void {
    $media = $this->createPendingAsset();
    $media->set('rejected', TRUE);
    $media->set('rejection_reason', 'The logo is cropped.');
    $media->save();

    $media = $this->reload($media);
    $this->assertFalse($media->isPublished());
    $this->assertTrue((bool) $media->get('rejected')->value);
    $this->assertSame('The logo is cropped.', $media->get('rejection_reason')->value);

    $mails = $this->getMails(['id' => 'damopen_assets_asset_rejected']);
    $this->assertCount(1, $mails);
    $this->assertSame($this->uploader->getEmail(), $mails[0]['to']);
    $this->assertSame('Your asset "Pending asset" was not approved', $mails[0]['subject']);
    $this->assertStringContainsString('The logo is cropped.', $mails[0]['body']);
    $this->assertStringContainsString($media->toUrl('edit-form', ['absolute' => TRUE])->toString(), $mails[0]['body']);

    // Saving the already rejected asset does not notify the uploader again.
    $media->setName('Renamed asset')->save();
    $this->assertCount(1, $this->getMails(['id' => 'damopen_assets_asset_rejected']));
  }

  /**
   * Rejects an asset without a reason.
   */
  public function testRejectionWithoutReason(): void {
    $media = $this->createPendingAsset();
    $media->set('rejected', TRUE)->save();

    $this->assertTrue((bool) $this->reload($media)->get('rejected')->value);
    $this->assertMailString('body', 'The reviewer did not give a reason.', 1);
  }

  /**
   * Rejecting a published asset takes it out of the library.
   */
  public function testRejectionUnpublishes(): void {
    $media = $this->createPendingAsset();
    $media->setPublished()->save();
    $this->assertTrue($this->reload($media)->isPublished());

    $media->set('rejected', TRUE)->save();
    $this->assertFalse($this->reload($media)->isPublished());
  }

  /**
   * Approving or resubmitting an asset clears the rejection.
   */
  public function testRejectionIsCleared(): void {
    $media = $this->createPendingAsset();
    $media->set('rejected', TRUE);
    $media->set('rejection_reason', 'Wrong category.');
    $media->save();

    $media->setPublished()->save();
    $media = $this->reload($media);
    $this->assertTrue($media->isPublished());
    $this->assertFalse((bool) $media->get('rejected')->value);
    $this->assertNull($media->get('rejection_reason')->value);

    $media->setUnpublished();
    $media->set('rejected', TRUE);
    $media->set('rejection_reason', 'Still the wrong category.');
    $media->save();
    $this->assertCount(2, $this->getMails(['id' => 'damopen_assets_asset_rejected']));

    $media->set('rejected', FALSE)->save();
    $media = $this->reload($media);
    $this->assertFalse($media->isPublished());
    $this->assertNull($media->get('rejection_reason')->value);
  }

  /**
   * Only reviewers may change the rejection fields.
   */
  public function testFieldAccess(): void {
    $media = $this->createPendingAsset();
    $reviewer = $this->createUser(['publish any media']);

    foreach (['rejected', 'rejection_reason'] as $field) {
      $this->assertFalse($media->get($field)->access('edit', $this->uploader), $field);
      $this->assertTrue($media->get($field)->access('edit', $reviewer), $field);
    }
  }

  /**
   * Creates an unpublished asset owned by the uploader.
   */
  protected function createPendingAsset(): MediaInterface {
    $media = Media::create([
      'bundle' => $this->mediaTypeId,
      'name' => 'Pending asset',
      'uid' => $this->uploader->id(),
      'status' => FALSE,
    ]);
    $media->save();
    return $media;
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
