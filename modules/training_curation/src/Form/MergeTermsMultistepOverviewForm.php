<?php

/**
 * @file
 * Contains \Drupal\training_curation\Form\MergeTermsMultistepOverviewForm
 */

namespace Drupal\training_curation\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Multiform approach: Second form is for giving an overview what will happen.
 */
class MergeTermsMultistepOverviewForm extends MergeTermsMultistepFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'training_curation_merge_terms_overview';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the source to merge.

    $form = parent::buildForm($form, $form_state);

    $search_term_id = $this->store->get('merge_term_search');
    $apply_term_id = $this->store->get('merge_term_apply');
    // todo: error, if no term id found

    // Get the overview table with the involved nodes.
    //$this->termManage->testFields($search_term_id, $apply_term_id);
    $overviewTable = $this->termManage->getComparisonTerms(
      $search_term_id, $apply_term_id);

    // Take the overview table and format it in a form.
    $this->termManage->formatOverviewTable($overviewTable, $form);
/*
    $this->showFields();

    $this->showInvolvedTerms($form);

    // Show the fields that are to be merged.
    // Get all the fields that are compared and put them into a tableview.
    // This does also include ways to decide, which data to import.
    $fieldsTable = $this->termManage->getNodesToTerm($this->store->get('merge_term_search'));

    $form['show_fields'] = [
      '#type' => 'table',
      '#theme' => 'table',
      '#caption' => $this->t('Fields to merge'),
      '#header' => $fieldsTable['#header'],
    ];
    foreach($fieldsTable['#rows'] as $index=>$row) {
      $form['show_fields'][$index] = $row;
    }
*/
    $form['actions']['previous'] = array(
      '#type' => 'link',
      '#title' => $this->t('Previous'),
      '#attributes' => array(
        'class' => array('button'),
      ),
      '#weight' => 0,
      '#url' => Url::fromRoute('training_curation.merge_terms'),
    );
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
/*    // Need to filter out all changes and put them to the next form, that
    // will show the results of the merge, before it offers the option to
    // delete the source node.
    
    // Get the changes for the node. Collect them and if there are changes for the
    // destination, apply them to the node.
    $nodeChangedFields = $form_state->getValue('show_fields');
    $nodeChanges = $this->getChangesToNode($nodeChangedFields);
    $this->store->set('merge_node_fields', $nodeChanges);
    
    // Next get the references. They have a variable name, depending on the
    // type of the reference. They look like this:
    // $form_state->getValue('show_reference|paragraph|person_relation|field_person')
    // Therefore get all values from the form_state and look for those, that start
    // with show_reference.
    $findReferences = array_keys($form_state->getValues());
    $referenceChanges = array();
    $titleChanges = array();
    foreach($findReferences as $submitKey) {
      // If the key starts with show_reference, then this tells us that it is
      // a reference form.
      // There is an additional case: If the type of show_reference is change_title
      // then it tells that the title of the referred node should be changed.
      if (strpos($submitKey, 'show_reference') === 0) {
        $refValues = $form_state->getValue($submitKey);

        // Now get the rest of the submitKey to find out, where to write the changes.
        $toChange = explode('|', $submitKey);

        foreach($refValues as $val) {
          if ($val) {
            // Check if it is a change_title case.
            if ($toChange[1] == 'change_title') {
              // Now get the title from the field list (and check if it is the new
              // one of the destination or if it stays the one from the source).
              $changeTitleTo = '';
              foreach($nodeChangedFields as $changedFields) {
                if ($changedFields['name'] == 'title') {
                  // Now have a look at the action to see, if the value from the
                  // destination node is choosen (usually that should be the case).
                  // Action should be 0 in such cases.
                  if (empty($changedFields['action'])) {
                    $changeTitleTo = $changedFields['value1'];
                  }
                  break;
                }
              }
              if (!empty($changeTitleTo)) {
                $titleChanges[] = [
                  'nid' => $val,
                  'title' => $changeTitleTo,
                ];
              }
            } else {
              // Load the reference that should to be changed.
              // We now store the id of the ones, that will change.
              $referenceChanges[] = [
                'type' => $toChange[1],
                'value' => $val,
                'field' => $toChange[3],
              ];
            }
          }
        }
      }
    }
    $this->store->set('merge_node_references', $referenceChanges);
    $this->store->set('merge_node_titles', $titleChanges);
    
    // Redirect to the confirm form.
    // @todo: add some labels so that we now, what is changing (old value for fields / name for paragraphs)
    $form_state->setRedirect('theadok_curation.merge_confirm');
  */  }

}