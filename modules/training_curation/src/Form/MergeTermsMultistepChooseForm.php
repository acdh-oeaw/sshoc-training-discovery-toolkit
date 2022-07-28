<?php

/**
 * @file
 * Contains \Drupal\training_curation\Form\MergeTermsMultistepChooseForm
 */

namespace Drupal\training_curation\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Multiform approach: First form is for choosing the involved term ids.
 */
class MergeTermsMultistepChooseForm extends MergeTermsMultistepFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'training_curation_merge_terms_choose';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // The current structure needs to have the buildForm of the parent in MerteTermsMultistepFormBase
    // to be called at first (it initializes the form).
    $form = parent::buildForm($form, $form_state);

    $form['merge_term_search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search term ID'),
      '#description' => $this->t('ID of term that is to be searched for.'),
      '#default_value' => $this->store->get('merge_term_search') ? $this->store->get('merge_term_search') : '',
      '#required' => TRUE,
    ];
    
    $form['merge_term_apply'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apply term ID'),
      '#description' => $this->t('ID of term that is applied for all search results.'),
      '#default_value' => $this->store->get('merge_term_apply') ? $this->store->get('merge_term_apply') : '',
      '#required' => TRUE,
    ];
    
    $form['merge_term_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete search term?'),
      '#description' => $this->t('Should after appliance of the new term the one that is searched for '.
          'deleted?'),
      '#default_value' => FALSE,
    ];
    
    $form['actions']['submit']['#value'] = $this->t('Next');

    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // first check if the search term id is correct
    $merge_term_search_id = $form_state->getValue('merge_term_search');
    if (empty($merge_term_search_id)) {
      $form_state->setErrorByName('merge_term_search',
        $this->t('There must be an entity id set for Search term ID'));
    }
    elseif (!is_numeric($merge_term_search_id)) {
      $form_state->setErrorByName('merge_term_search',
        $this->t('Search term ID must be an integer value: ' . $merge_term_search_id));
    }
    elseif (!$this->termManage->isTermAvailable($merge_term_search_id)) {
      $form_state->setErrorByName('merge_term_search',
        $this->t('Could not find Search term ID ' . $merge_term_search_id));
    }

    // check also if there is the correct apply term id
    $merge_term_apply_id = $form_state->getValue('merge_term_apply');
    if (empty($merge_term_apply_id)) {
      $form_state->setErrorByName('merge_term_apply',
        $this->t('There must be an entity id set for Apply term ID'));
    }
    elseif (!is_numeric($merge_term_apply_id)) {
      $form_state->setErrorByName('merge_term_apply',
        $this->t('Apply term ID must be an integer value: ' . $merge_term_apply_id));
    }
    elseif (!$this->termManage->isTermAvailable($merge_term_apply_id)) {
      $form_state->setErrorByName('merge_term_apply',
        $this->t('Could not find Apply term ID ' . $merge_term_apply_id));
    }

  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('merge_term_search', $form_state->getValue('merge_term_search'));
    $this->store->set('merge_term_apply', $form_state->getValue('merge_term_apply'));
    $this->store->set('merge_term_delete', $form_state->getValue('merge_term_delete'));
    // The next form gives an overview on all found nodes to the search term.
    $form_state->setRedirect('training_curation.merge_terms_overview');
  }

}