<?php

/**
 * @file
 * Service for handling the management of the taxonomy/terms
 */

namespace Drupal\training_curation;

use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

/**
 * Collecting methods to manipulate or query taxonmoy terms
 */
class TermManage {

  /**
   * Check if a term id is availalbe
   */
  public function isTermAvailable($termId) {
    $term = Term::load($termId);
    if ($term != NULL) {
      return TRUE;
    }
    return FALSE;
  }

  public function getBundlesToSearchFor($entityType) {
    // todo: look in all bundles not just node
    return array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo($entityType));
  }

  public function hasFieldReferenceToBundle($fieldConfig, $termTaxonomy) {
    // the field must be set and it needs to have a FieldConfig (then it is field, that can be re-used and not a field created by the system)
    if (isset($fieldConfig)) {
      if (get_class($fieldConfig) == 'Drupal\field\Entity\FieldConfig') {
      //if (isset($val) && ($val instanceof Drupal\field\Entity\FieldConfig)) { // does not work this way
        // only interested in fields that are references
        if ($fieldConfig->getType() == 'entity_reference') {
          // now get the settings and check, if it refers to a vocabulary
          $settings = $fieldConfig->getSettings();
          if (isset($settings['target_type']) && ($settings['target_type']=='taxonomy_term')) {
            // now check, if it can be a reference to the taxonomy we are searching for
            if (in_array($termTaxonomy, $settings['handler_settings']['target_bundles'])) {
              //print "name of field: " . $fieldConfig->getName() . "<br />";
              return true;
            }
          }
        }
      }
    }
    return false;
  }

  public function getFieldsOfTerm($termId) {
    $fieldAndBundles = [];

    $term = Term::load($termId);
    if ($term != NULL) {
      // that is the taxonomy a field needs to point to
      $termTaxonomy = $term->bundle();

      // currently we only search for nodes
      $entityTypeToSearch = 'node';
      // now get all bundles with fields and go through them
      foreach($this->getBundlesToSearchFor($entityTypeToSearch) as $bundleToSearch) {
        // get the field defnitions
        // todo: don't use entityManger, use an injection
        $bundle_fields = \Drupal::entityManager()->getFieldDefinitions($entityTypeToSearch, $bundleToSearch);
        foreach($bundle_fields as $fieldName => $fieldConfig) {
          if ($this->hasFieldReferenceToBundle($fieldConfig, $termTaxonomy)) {
            //$fieldAndBundles[$bundleToSearch][] = $termTaxonomy;
            /*$fieldAndBundles[] = [
              'fieldName' => $fieldConfig->getName(),
              'bundle' => $bundleToSearch,
              'taxonomy' => $termTaxonomy,
            ];*/
            $fieldAndBundles[$bundleToSearch][$fieldConfig->getName()][] = $termTaxonomy;
          }
        }
      }
    }

    return $fieldAndBundles;
  }

  private function calculateSameBundles($searchFields, $applyFields) {
    $sameBundles = [];
    foreach($searchFields as $sKey => $sVal) {
      if ($applyFields[$sKey]) {
        // both bundles exist, therefore they can be used
        $sameBundles['search'][$sKey] = $sVal;
        $sameBundles['apply'][$sKey] = $applyFields[$sKey];
      }
    }
    return $sameBundles;
  }

  private function getTermInformation($termId) {
    print "termId: $termId <br />";
    $term = Term::load($termId);
    if ($term != NULL) {
      print "termName: " . $term->getName() . "<br />";
      print "termBundle: " . $term->bundle() . "<br />";
    }
    else {
      print "Term $termId is not available - load is NULL<br />";
    }
  }

  private function getNodeInformation($nodeId) {
    $nodeInfo = [];
    $nodeInfo['nodeId'] = $nodeId;
    //print "nodeId: $nodeId <br />";
    $node = Node::Load($nodeId);
    if ($node != NULL) {
      $nodeInfo['nodeTitle'] = $node->title->value;
      //print "nodeTitle: " . $nodeInfo->title->value . "<br />";
      $nodeInfo['nodeBundle'] = $node->bundle();
      //print "nodeBundle: " . $nodeInfo->bundle() . "<br />";
    }
    else {
      print "Node $nodeId is not available - load is NULL<br />";
    }
    return $nodeInfo;
  }

  public function getComparisonTerms($termSearchId, $termApplyId) {
    // Get all fields that are involved by the terms.
    $searchFields = $this->getfieldsOfTerm($termSearchId);
    $applyFields = $this->getfieldsOfTerm($termApplyId);

    // Only use bundles that are in search and in apply.
    $nodesSearch = [];
    $nodesApply = [];
    foreach($searchFields as $searchBundle=>$searchField) {
      // Ignore bundles, that are not in apply.
      if (!empty($applyFields[$searchBundle])) {
        // Get for every bundle and field the node ids that do have the term.
        // Both for the search term ids ...
        foreach($searchField as $field=>$taxonomy) {
          $nodesSearch = array_merge($nodesSearch,
            \Drupal::entityQuery('node')
              ->condition($field, $termSearchId)
              ->condition('type', $searchBundle)
              ->execute());
        }
        // ...  as well as the apply term ids.
        foreach($applyFields[$searchBundle] as $fieldApply=>$taxonomyApply) {
          $nodesApply = array_merge($nodesApply,
            \Drupal::entityQuery('node')
              ->condition($fieldApply, $termApplyId)
              ->condition('type', $searchBundle)
              ->execute());
        }
      }
    }

    // Create the overview table. This is divided by terms that are in search
    // and apply.
    // If it is in both then this means that no action is needed, as the apply
    // term is already in place.
    // Is it only in search but not in apply, then the apply term id should be
    // applied to the ones found in search.
    $overviewTable = [];

    // First get the ones that are in both arrays,
    // "both"  means "available in both nodes".
    $nodesIntersect = array_intersect($nodesSearch, $nodesApply);
    foreach($nodesIntersect as $nodeId) {
      $overviewTable['both'][] = $this->getNodeInformation($nodeId);
    }

    // Now get the ones, where there is no applyId;
    // "apply" means "missing apply id".
    $nodesDiff = array_diff($nodesSearch, $nodesApply);
    foreach($nodesDiff as $nodeId) {
      $overviewTable['apply'][] = $this->getNodeInformation($nodeId);
    }

    return $overviewTable;
  }

  public function formatOverviewTable($overviewTable, &$form) {
    $header = [
      'action' => 'Action',
      'nodeId' => 'Node Id',
      'nodeBundle' => 'Node Bundle',
      'nodeTitle' => 'Node Title',
    ];

    $form['show_fields'] = [
      '#type' => 'table',
      '#theme' => 'table',
      //'#caption' => $this->t('Fields overview'),
      '#caption' => 'Fields overview',
      '#header' => $header,
      '#empty' => 'Nothing found',
    ];
    $index = 1;
    foreach($overviewTable as $action=>$elements) {
      print "test1";
      foreach($elements as $element) {
        print "test2 $index <br />";
        $form['show_fields']['#options'][$index] = [
          '#type' => 'textfield',
          'action' => $action,
          'nodeId' => $element['nodeId'],
          'nodeBundle' => $element['nodeBundle'],
          'nodeTitle' => $element['nodeTitle'],
        ];
        /*$form['show_fields'][$index][$index]['action'] = $action;
        $form['show_fields'][$index][$index]['nodeId'] = $element['nodeId'];
        $form['show_fields'][$index][$index]['nodeBundle'] = $element['nodeBundle'];
        $form['show_fields'][$index][$index]['nodeTitle'] = $element['nodeTitle'];*/
        $index++;
      }
    }
    print_r($form['show_fields']);
  }

  /**
   * Test function
   */
  public function testFields($termSearchId, $termApplyId) {
    print "term Search<br />";
    $this->getTermInformation($termSearchId);
    print "term Apply<br />";
    $this->getTermInformation($termApplyId);

    $searchFields = $this->getfieldsOfTerm($termSearchId);
    $applyFields = $this->getfieldsOfTerm($termApplyId);

    // zuerst überprüfen, ob es zwischen search und apply gemeinsame bundles gibt
    // nur die gemeinsamen bundles verwenden
    $nodesSearch = [];
    $nodesApply = [];
    foreach($searchFields as $searchBundle=>$searchField) {
      // ignore bundles, that are not in the apply
      if (!empty($applyFields[$searchBundle])) {
        // now get for every bundle and field the nodes to the term
        foreach($searchField as $field=>$taxonomy) {
          $nodesSearch = array_merge($nodesSearch,
            \Drupal::entityQuery('node')
              ->condition($field, $termSearchId)
              ->condition('type', $searchBundle)
              ->execute());
        }

        foreach($applyFields[$searchBundle] as $fieldApply=>$taxonomyApply) {
          $nodesApply = array_merge($nodesApply,
            \Drupal::entityQuery('node')
              ->condition($fieldApply, $termApplyId)
              ->condition('type', $searchBundle)
              ->execute());
        }
      }
    }
    // jetzt alle searches durchgehen: zusammenfassen von fieldNames: warnung, wenn etwas nicht passt
    // dann vergleichen mit apply: gibt es das jeweilige bundle?
    // ich möchte ein array zurückgeben, dass dann in einer tabelle ausgegeben wird, mit allen werten

    //print "Search nodes:<pre>";print_r($nodesSearch);print "</pre>";
    //print "Apply nodes:<pre>";print_r($nodesApply);print "</pre>";

    $overviewTable = [];

    // first get the ones that are in both arrays
    $nodesIntersect = array_intersect($nodesSearch, $nodesApply);
    foreach($nodesIntersect as $nodeId) {
      // 'status' => 'available in both nodes',
      $overviewTable['both'][] = $this->getNodeInformation($nodeId);
    }

    // not the ones, where there is no applyId
    $nodesDiff = array_diff($nodesSearch, $nodesApply);
    foreach($nodesDiff as $nodeId) {
      // 'status' => 'missing apply id',
      $overviewTable['apply'][] = $this->getNodeInformation($nodeId);
    }

    print "Overview nodes:<pre>";print_r($overviewTable);print "</pre>";
    exit;

    //$sameBundles = $this->calculateSameBundles($searchFields, $applyFields);

    print "Search fields:<pre>";print_r($searchFields);print "</pre>";
    print "Apply fields:<pre>";print_r($applyFields);print "</pre>";
    //print "Same bundles:<pre>";print_r($sameBundles);print "</pre>";

    // works: next search for all nodes with the term used in the bundle
    // and look if the apply is already in use > give statistics
    // this does not work! $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['collections' => 481]);

    // todo: i need the field-names to search for! and then go through them, create the intersect or the other way round: the differences > so that we konw where to apply
    //$nodes_search = \Drupal::entityQuery('node')->condition('field_intended_audience','422')->execute(); //trainer
    $nodes_search = \Drupal::entityQuery('node')->condition('field_intended_audience','10')->execute(); // student
    $nodes_apply = \Drupal::entityQuery('node')->condition('field_collections','481')->execute();
    //$nodes_apply = \Drupal::entityQuery('node')->condition('field_intended_audience','10')->execute();
    $nodes_intersect = array_intersect($nodes_search, $nodes_apply);
    $nodes_diff = array_diff($nodes_search, $nodes_apply);
    // return nodes without checking the bundles (therefore not working if e.g. one taxonomy is only existing in one bundle, as it is for collections: because it will show also items)
    // e.g. in the example with search audicen 10 = student and collection = ttt, it will give back http://localhost/acdh/training-inventory/web/node/170 but that is an item not having colleciton (at least on localhost), therefore not possible to apply collection
    // i need the bundles and need to ignore all that are not fitting > add an condition
    print "Search nodes:<pre>";print_r($nodes_search);print "</pre>";
    print "Apply nodes:<pre>";print_r($nodes_apply);print "</pre>";
    print "Nodes intersect:<pre>";print_r($nodes_intersect);print "</pre>";
    print "Nodes diff:<pre>";print_r($nodes_diff);print "</pre>";
    print_r();

    exit;

    $term = Term::load($termId);
    if ($term != NULL) {
      // get the taxonomy = bundle of the term
      $termBundle = $term->bundle();
      print "Bundle of term to search is: ".$termBundle."<br />";
      // todo: first get all bundles to the node-entity
      // use service injection
      $bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('node'));
      foreach($bundles as $bundle) {
        //print "Bundle: $bundle <br />";
        // go through all the field definitions
        $bundle_fields = \Drupal::entityManager()->getFieldDefinitions('node', $bundle);
        //$bundle_fields = \Drupal::entityTypeManager()->getFieldDefinitions('node', 'article');
        foreach($bundle_fields as $key=>$val) {
          //print "settings: ";
          //print "Type: ".$val->type . "<br />";
          if (isset($val) && (get_class($val) == 'Drupal\field\Entity\FieldConfig')) {
          //if (isset($val) && ($val instanceof Drupal\field\Entity\FieldConfig)) { // does not work this way
            if ($val->getType() == 'entity_reference') {
              $settings = $val->getSettings();
              if (in_array($termBundle, $settings['handler_settings']['target_bundles'])) {
                print "FOUND: it is there: $bundle | $key <br />";
                //print "the value is of type FieldConfig<br />";
                //print "entitytype: ".$val->getEntityType()->getClass()."<br />";
                //print "fieldtype: ".$val->getType()."<br />";
                print "settings: <pre>"; print_r($settings); print "</pre></br>";
                //print_r($val);print "<br />";
                //foreach($val as $k=>$v) {
                //  print "- $k<br />";
                //}
                //print "<br />";
              }
            }
          }
          //break;
        }
      }
    }

    //print_r($bundle_fields);
    exit;
    return true;
    //dpm($fieldMap);
    //dpm($srcNode->field_places->getValue());
    //kint($srcNode->field_places);
    //print_r($srcNode->getFields());
    //$src_ref = $srcNode->referencedEntities();
    //kint($src_ref);

    //kint($srcNode);
    //kint($fieldMap);
    //$node_field_map = $fieldMap['node'];
    /*$valuelist = '';
    foreach($srcNode->values as $fieldname=>$value) {
      $valuelist .= "$field: $value <br />";
    }
    dpm($valuelist);*/
    //dpm($srcNode);
    // problem - need to get the paragraphs where a nid is the source

    // todo: gehe durch alle paragraphs (+ nodes) > schaue, ob da etwas ist, was auf bundle verlinkt
    // wenn, dann überprüfe, ob einträge gibt > wenn es gibt, dann ändere auf das neue (selbst wenn es doppelt ist dann > sollte wohl aber nicht sein)

    /*    $list_ref = $this->findListOfReferences($srcNode->bundle());
    //dpm($list_ref);
    foreach($list_ref as $ref) {
      $tmp2 = \Drupal::entityQuery($ref['entity_type'])
          ->condition('type', $ref['bundle_of_entity_type'])
          // target_id probably only for paragraphs
          ->condition($ref['field_name'].'.target_id', $content_merge_source_id)
          ->execute();
      //kint($tmp2);
      $this->getReferencesToEntityId($ref['entity_type'], $ref['bundle_of_entity_type'], $ref['field_name'], $content_merge_source_id);
    }
*/    
    //this works > now i have a list of all fields, but i need to go through them and look, if one of them is a reference field that has a reference to the choosen bundle (= person)
    //        for this i need to go through the fields of paragraphs and of nodes, get the field names, get then the config and then check if reference + bundle

    /*$fieldnames = '';
    foreach($fields as $fieldName=>$field) {
      //print "$fieldName <br />";
      $fieldnames .= "$fieldName <br />";
    }
    dpm($fieldnames);*/
    // to get list of fields, see: https://drupal.stackexchange.com/questions/211057/how-to-get-a-list-of-fields-that-are-used-in-entities
    //$fieldMap = \Drupal::entityManager()->getFieldMap();
    // is deprecated!! https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityManager.php/function/EntityManager%3A%3AgetFieldDefinitions/8.7.x
    //does not work: $fm = EntityFieldManagerInterface::getFieldDefintions('node', 'person');
    //$fieldMap = \Drupal::entityManager()->getFieldDefinitions('node', 'person');
    //$fm = \Drupal::entityTypeManager()->getFieldDefinitions('node', 'person');
    //$fm = \Drupal::entityTypeManager()->getDefinitions();
    //$fmt = new EntityFieldManagerInterface();
    //$fm = $fmt->getFieldDefinitions('node', 'person');
    //$fm = \Drupal::entityTypeManager()->getDefinition('node')->;
    //kint($fm);
  }

  /**
   * Load all node entities to a term id
   */
  public function loadEntitiesToTermId($searchTermId) {
    $term = Term::load($searchTermId);
    
  }
}
