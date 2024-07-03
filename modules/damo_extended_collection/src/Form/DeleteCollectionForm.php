<?php

namespace Drupal\damo_extended_collection\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines a confirmation form to confirm deletion of something by id.
 */
class DeleteCollectionForm extends ConfirmFormBase {

  /**
   * ID of the item to delete.
   *
   * @var int
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $id = NULL) {
    $this->id = $id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $collection = \Drupal::entityTypeManager()->getStorage('media_collection')->load($this->id);
    $collectionUser = $collection->getOwner();
    if ($collectionUser->id() !== \Drupal::currentUser()->id()) {
      throw new AccessDeniedHttpException();
    }
    $items = $collection->get('items')->getValue();

    foreach ($items as $item) {
      $media = \Drupal::entityTypeManager()->getStorage('media_collection_item')->load($item['target_id']);
      // Invalidate media cache tags.
      $cache_tags = $media->getCacheTags();
      Cache::invalidateTags($cache_tags);
      $media->delete();
    }
    $collection->delete();
    // // Redirect to collections page.
    $form_state->setRedirect('view.collections.collections_page');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "confirm_delete_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('view.collections.collections_page');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to delete %id?', ['%id' => $this->id]);
  }

}
