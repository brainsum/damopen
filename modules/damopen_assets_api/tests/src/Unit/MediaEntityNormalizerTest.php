<?php

namespace Drupal\Tests\damopen_assets_api\Unit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\damopen_assets_api\Normalizer\MediaEntityNormalizer;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies media-only normalization under Drupal 11's serializer contracts.
 */
#[Group('damopen_assets_api')]
class MediaEntityNormalizerTest extends UnitTestCase {

  /**
   * The normalizer must not intercept nodes or other content entities.
   */
  public function testSupportedTypes(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturn($this->createMock(EntityStorageInterface::class));
    $normalizer = new MediaEntityNormalizer(
      $manager,
      $this->createMock(EntityTypeRepositoryInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
    );

    $this->assertTrue($normalizer->supportsNormalization($this->createMock(MediaInterface::class), 'json'));
    $this->assertFalse($normalizer->supportsNormalization($this->createMock(NodeInterface::class), 'json'));
    $this->assertFalse($normalizer->supportsNormalization(new \stdClass(), 'json'));
    $this->assertSame([], $normalizer->normalize(new \ArrayObject(), 'json'));
  }

}
