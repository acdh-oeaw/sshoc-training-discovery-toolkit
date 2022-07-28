<?php

/**
 * @file
 * Contains \Drupal\training_curation\Form\MergeTermsMultistepFormBase
 * 
 * Credit for multiform approach: https://www.sitepoint.com/how-to-build-multi-step-forms-in-drupal-8/
 */

namespace Drupal\training_curation\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\training_curation\TermManage;

abstract class MergeTermsMultistepFormBase extends FormBase {
  
  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;
  
 /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  private $sessionManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface;
   */
  protected $messenger;

  /**
   * @var \Drupal\training_curation\TermManage
   */
  protected $termManage;

  /**
   * Constructs a \Drupal\training_curation\Form\MergeTermsMultistepFormBase.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Drupal\training_curation\TermManage $termManage
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager,
      AccountInterface $current_user, MessengerInterface $messenger, TermManage $termManage) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->termManage = $termManage;

    $this->store = $this->tempStoreFactory->get('mergeterms_data');
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('session_manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('training_curation.term_manage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Start a manual session for anonymous users.
    if ($this->currentUser->isAnonymous() && !isset($_SESSION['multistep_form_holds_session'])) {
      $_SESSION['multistep_form_holds_session'] = true;
      $this->sessionManager->start();
    }

    $form = array();
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#weight' => 10,
    );

    return $form;
  }
  
  protected function showInvolvedTerms(array &$form) {
    // Give the URLs of the nodes to be merged in a table.
    $form['merge_terms'] = [
      '#type' => 'table',
      '#caption' => $this->t('Information on merge terms'),
      '#header' => [
        'merge_search' => $this->t('Term to search for'),
        'merge_apply' => $this->t('Term that is applied'),
      ],
    ];
    $form['merge_terms'][1]['merge_search'] = [
      '#type' => 'link',
      '#title' => 'Taxonomy term: ' . $this->store->get('merge_term_search'),
      '#url' => Url::fromRoute('entity.taxonomy_term.canonical',
              ['taxonomy_term' => $this->store->get('merge_term_search')]),
    ];
    $form['merge_terms'][1]['merge_apply'] = [
      '#type' => 'link',
      '#title' => 'Taxonomy term: ' . $this->store->get('merge_term_apply'),
      '#url' => Url::fromRoute('entity.taxonomy_term.canonical',
              ['taxonomy_term' => $this->store->get('merge_term_apply')]),
    ];
  }

  /**
   * Saves the data from the multistep form.
   */
  protected function saveData() {
    // @todo: Logic for saving data goes here...
    $this->deleteStore();
    drupal_set_message($this->t('The form has been saved.'));
  }

  /**
   * Helper method that removes all the keys from the store collection used for
   * the multistep form.
   */
  protected function deleteStore() {
    $keys = [
      'merge_term_search',
      'merge_term_apply',
      'merge_term_delete',
    ];
    foreach ($keys as $key) {
      $this->store->delete($key);
    }
  }

}
