<?php

namespace Drupal\damopen_common\Temporary;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use InvalidArgumentException;

/**
 * Class ImageStyleLoader.
 *
 * @package Drupal\media_collection\Temporary
 *
 * @todo: Remove when this loader is implemented properly, without static IDs.
 */
class ImageStyleLoader {

  /**
   * Build a list of image styles that are needed for the crop list.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @return \Drupal\image\ImageStyleInterface[]
   *   An array of image styles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo: Refactor. Original was \Drupal\damopen_assets\Render\AssetPreviewListMarkup;
   */
  public static function loadImageStylesList(EntityTypeManagerInterface $entityTypeManager): array {
    // @todo: Generalize; add a checkbox to the image styles instead of this static list.
    $styleList = [
      'other_hi_res',
      'other_hi_res_no_badge',
      'facebook_organic',
      'facebook_organic_no_badge',
      'facebook_paid_campaign',
      'facebook_paid_campaign_no_badge',
      'instagram_photo_size',
      'instagram_photo_size_no_badge',
      'instagram_paid_campaign',
      'instagram_paid_campaign_no_badge',
      'linkedin_organic_or_paid_image',
      'linkedin_organic_or_paid_image_no_badge',
      'linkedin_personal_account_newsfeed_update_organic',
      'linkedin_personal_account_newsfeed_update_organic_no_badge',
      'twitter_website_card_paid_campaign',
      'twitter_website_card_paid_campaign_no_badge',
      'twitter_in_stream_photo',
      'twitter_in_stream_photo_no_badge',
      'twitter_organic_tweet',
      'twitter_organic_tweet_no_badge',
      'ms_powerpoint',
      'ms_powerpoint_no_badge',
    ];

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorage $styleStorage */
    $styleStorage = $entityTypeManager->getStorage('image_style');
    /** @var \Drupal\image\ImageStyleInterface[] $styles */
    $styles = $styleStorage->loadMultiple($styleList);

    if (empty($styles)) {
      throw new InvalidArgumentException('No applicable image styles found.');
    }

    return $styles;
  }

}
