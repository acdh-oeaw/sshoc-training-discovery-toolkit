<?php
/**
 * @Author: Klaus Illmayer, klaus.illmayer@oeaw.ac.at
 * 
 * MIT License
 * 
 * Copyright (c) 2019 ACDH-OEAW, Klaus Illmayer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */

/*
 * Installation notice:
 * 
 * - Use the CSV library from here: https://csv.thephpleague.com/
 *   Installation information: https://csv.thephpleague.com/9.0/installation/
 *   It is installed by calling "composer install"
 * - It is expected that sourcedata.csv already exists. This is a specific csv
 *   export from a gSpreadsheet created in SSHOC WP6, that holds a list of source
 *   to look at.
 */
require_once __DIR__.'/vendor/autoload.php';

use League\Csv\Reader;
use League\Csv\Writer;

/**
 * Class to prepare the csv from a GSpreadsheet export for a Drupal import.
 * It first covered only a fresh export, as we do have new sources the next
 * step is to identify only the new ones.
 * 
 * It may be necessary to adapt settings and/or manipulate the csv before running
 * this script (e.g. due to new columns).
 * 
 * In general, there are sources that are identified and disciplines as well as topics
 * of that sources that are extracted. Items are not covered by this script.
 * 
 */
class PrepareCSVForDrupal {

  /**
   * Should if no title is present one be harvested from the website?
   * This is necessary especially for importOne
   */
  private $harvestTitle = FALSE;

  /**
   * Import 1: The very first one for T6.3.
   * Has a separate filename where the source csv is available.
   */
  private $importOne = TRUE;
  private $sourceFileImportOne = 'data/import/sourcedata_t_6_3.csv';
  private $disciplineFileImportOne = 'data/export/disciplines_i1.csv';
  private $topicFileImportOne = 'data/export/topics_i1.csv';
  private $sourcesFileExportOne = 'data/export/sources_i1.csv';

  /**
   * The relevant columns in the source csv.
   * There is no name in this csv.
   */
  private $sourceFileColumnsImportOne = [
    'url' => 0,
    'discipline' => 1,
    'description' => 2,
    // topics is a special case: as it can be a very long list and it is the last column, the value
    // here means: starting with this column every other column is also a topic
    'topics' => 3,
  ];

  /**
   * Import 2: The second one for T6.4: TTT.
   * It is necessary to have the CSV import files to Drupal vom import one available,
   * to identify the ones that are already imported. (they are in the Git repo)
   */
  private $importTwo = TRUE;
  private $sourceFileImportTwo = 'data/import/CollectionMaterials_TTTToolkit.csv';
  private $disciplineFileImportTwo = 'data/export/disciplines_i2.csv';
  private $topicFileImportTwo = 'data/export/topics_i2.csv';
  private $sourcesFileExportTwo = 'data/export/sources_i2.csv';

  /**
   * The relevant columns in the source csv
   */
  private $sourceFileColumnsImportTwo = [
    'name' => 0,
    'url' => 1,
    'discipline' => 2,
    'description' => 3,
    'examples' => 5,
    // topics is a special case: as it can be a very long list and it is the last column, the value
    // here means: starting with this column every other column is also a topic
    'topics' => 6,
    'topics_max' => 8,
  ];

  /**
   * Collects all sources.
   * 
   * @var array
   */
  public $sourcesList = [];

  /**
   * Collects all mentioned disciplines about the sources.
   * 
   * @var array
   */
  public $disciplineList = [];

  /**
   * Collects all mentioned topics about the sources.
   * 
   * @var array
   */
  public $topicList = [];

  /**
   * URLs can be more than one in the spreadsheet
   */
  function extractUrls($urls) {
    // Currently there are only spaces as delimiter.
    $extrUrls = explode(' ', $urls);
    // check if the urls are valid
    $checkedUrls = [];
    foreach($extrUrls as $url) {
      $url = trim($url);
      // could be that there are more than one spaces in between - ignore such cases
      if (!empty($url)) {
        if (filter_var($url, FILTER_VALIDATE_URL === FALSE)) {
          die("ERROR: $url is not a valid URL<br />");
        } else {
          $checkedUrls[] = $url;
        }
      }
    }
    return $checkedUrls;
  }

  /**
   * As not always the name is set but there should be always an URL, extract the URL from the website title.
   */
  function getTitleOfWebsite($url) {
    if ($this->harvestTitle) {
      // c&p + slight adaption from https://stackoverflow.com/a/4349042
      print "url: $url<br />";
      $doc = new DOMDocument();
      @$doc->loadHTMLFile($url);
      $xpath = new DOMXPath($doc);
      $liveTitle = $xpath->query('//title')->item(0)->nodeValue;
      // give away linebreaks + do a trim
      $liveTitle = trim(str_replace("\n", '', str_replace("\r", '', $liveTitle)));
      if (empty($liveTitle)) {
        print "Found no title for URL: $url <br />";
        $liveTitle = 'Found no title on the website';
      }
      return $liveTitle;
    } else {
      print "turned off live creation of titles<br />";
      return "No live creation of titles";
    }
  }
  
  /**
   * taxonomies have this structure:
   *   name,description,count
   * 
   * @param type $addTerm
   *   adding this term to the taxonomy
   * @param type &$taxonomy
   *   which taxonomy to use
   *   write into it further data
   * @return id
   *   the id of the term used in the taxonomy
   */
  function addTermInTaxonomy($addTerm, &$taxonomy) {
    // check if there is a description (if there is a colon then use the text behind
    // the colon as description (but only the first colon)
    $addTerm = trim($addTerm);
    $description = strstr($addTerm, ':');
    if (!empty($description)) {
      // delete the colon and make a trim
      $description = trim(substr($description, 1));
      // alter the term name
      $addTerm = trim(substr($addTerm, 0, strpos($addTerm, ':')));
    }
    // harmonizing terms
    // terms should be no longer than 255 characters (maximum for name in drupal - todo: but i didn't check it, so possibly it is less, eg. 128)
    // here the limit is 100 characters, otherwise the term name is too long
    // if it is longer than 100 take the first four words and put the rest into the description
    if (strlen($addTerm) > 100) {
      $newTermName = '';
      $newTermDesc = $addTerm;
      $words = 0;
      while($word = strpos($newTermDesc, ' ')) {
        if ($words == 4 || strlen($newTermName) > 100) {
          break;
        }
        $newTermName .= substr($newTermDesc, 0, $word+1);
        $newTermDesc = substr($newTermDesc, $word+1);
        $words++;
      }
      if (!empty($newTermName)) {
        $addTerm = $newTermName;
        if (!empty($description)) {
          // simulate the colon that was there before
          $description = ': '. $description;
        }
        $description = $newTermDesc . $description;
      }
    }
    // if there is a point at the end of a term, kick the point out
    if ($addTerm[strlen($addTerm)-1] == '.') {
      $addTerm = trim(substr($addTerm, 0, strlen($addTerm)-1));
    }
    //if there is a ` at the beginning of a term, delete it (comes form gSpreadsheet)
    if ($addTerm[0] == '`') {
      $addTerm = trim(substr($addTerm, 1));
    }

    foreach($taxonomy as $offset => $termTaxonomy) {
      // todo: some more fancy checks on similarity
      if (strtolower($termTaxonomy['name']) == strtolower($addTerm)) {
        $taxonomy[$offset]['count']++;
        if (!empty($description)) {
          // if a description is already set, add a newline otherwise add the
          // description text
          if (!empty($taxonomy[$offset]['description']) && $taxonomy[$offset]['description'] != $description) {
            $taxonomy[$offset]['description'] .= "\n" . $description;
          } else {
            $taxonomy[$offset]['description'] = $description;
          }
        }
        // in the array we only like to have the referrer
        return $offset+1;
      }
    }

    // if running until here, no discipline was found - add this one
    $newItem = [
      'name' => $addTerm,
      'description' => $description,
      'count' => 1
    ];
    $taxonomy[] = $newItem;
    
    // in the array we only like to have the referrer
    return count($taxonomy);
  }

  function handleArrayCsv($convArray) {
    $conv = '';
    if (!empty($convArray)) {
      if (is_array($convArray)) {
        $conv = implode('|', $convArray); // assuming that | is not used in any of the terms
      }
    }
    return $conv;
  }

  function getTaxonomy($terms, &$taxonomy) {
    // currently semicolon as delimiter
    $extractedTerms = explode(';', $terms);
    // we like to have a list of disciplines to create the taxonomy for it
    // also use this to find duplicates
    $checkedTerms = [];
    foreach($extractedTerms as $term) {
      $term = trim($term);
      if (!empty($term)) {
        $checkedTerms[] = $this->addTermInTaxonomy($term, $taxonomy);
      }
    }
    return $checkedTerms;
  }

  /**
   * Takes a taxonomy array and writes it into csv.
   * The taxonomy is limited to a specific array structure:
   *   [term-id => term-name]
   * 
   * @param array $taxonomy
   * @param string $csvFileName
   *   The name for the csv file to be written in the root directory
   */
  function writeTaxonomy($taxonomy, $csvFileName) {
    if (is_array($taxonomy)) {
      $records = [];
      $records[] = ['Id', 'Name', 'Description']; // title fields
      foreach($taxonomy as $term_id=>$term) {
        $records[] = [
          $term_id,
          $term['name'],
          $term['description'],
        ];
      }
      $writer = Writer::createFromPath($csvFileName, 'w+');
      /*print "<pre>";
      print_r($records);
      print "</pre>";*/
      $writer->insertAll($records);
      return true;
    }
    return false;
  }

  function writeSources($sources, $csvFileName) {
    if (is_array($sources)) {
      $records = [];
      $records[] = [
        'Id',
        'Title',
        'Description',
        'Accesspoints',
        'Disciplines',
        'Topics',
      ]; // title fields
      foreach($sources as $source) {
        // map now the source entry to the record
        $records[] = [
          $source['id'],
          $source['title'],
          $source['description'],
          (!empty($source['accesspoints']) && is_array($source['accesspoints'])
              ? $this->handleArrayCsv($source['accesspoints'])
              : ''),
          (!empty($source['disciplines']) && is_array($source['disciplines'])
              ? $this->handleArrayCsv($source['disciplines'])
              : ''),
          (!empty($source['topics']) && is_array($source['topics'])
              ? $this->handleArrayCsv($source['topics'])
              : ''),
        ];
      }
      $writer = Writer::createFromPath($csvFileName, 'w+');
      $writer->insertAll($records);
      return true;
    }
    return false;
  }

  private function analyzeExportRow($id, $row, $sourceFileColumns) {
    // running id: there is no unique id in the data itself, therefore take the linenumber
    // @todo: be aware that changes in the document may change the id: could be complicated if there are updateds planned
    $convdata = [
      'id' => $id,
    ];

    foreach($row as $column=>$item) {
      switch ($column) {
        case $sourceFileColumns['url']:
          // link to the source (there could be more than one) - we need to harvest the title of it
          $convdata['accesspoints'] = $this->extractUrls($item);
          // we need at least one url - give an error, if this is not true
          if (empty($convdata['accesspoints'])) {
            print("ERROR: row $id does not have an (valid) accesspoint<br />");
            return [];
          }
          // get the title of the first accesspoint and use it
          // update: with the new spreadsheet there is now a name field - if set, then use the name,
          // otherwise try the getTitle algo
          // there is sometimes no name
          if (isset($sourceFileColumns['name']) && !empty($row[$sourceFileColumns['name']])) {
            $convdata['title'] = $row[$sourceFileColumns['name']];
          } else {
            $convdata['title'] = $this->getTitleOfWebsite($convdata['accesspoints'][0]);
          }
          if (empty($convdata['title'])) {
            print "WARNING: website ".$convdata['accesspoints'][0]." has no title<br />";
            $convdata['title'] = 'Found no title';
          }
          break;
        case $sourceFileColumns['discipline']:
          // get the discipline back
          $convdata['disciplines'] = $this->getTaxonomy($item, $this->disciplineList);
          break;
        case $sourceFileColumns['description']:
          // description
          $convdata['description'] = $item;
          // there is also the possibility to have an example for the description (at least for import two)
          if (isset($sourceFileColumns['examples']) && !empty($row[$sourceFileColumns['examples']])) {
            if (!empty($convdata['description'])) {
              $convdata['description'] .= "\n\r";
            }
            $convdata['description'] .= 'Examples: '.$row[$sourceFileColumns['examples']];
          }
          break;
        default:
          // the rest are topics (depends on the settings)
          // if only topics is set, then from there on everything is seen as topic (import 1)
          // if topics_max also exist, then it defines a range (import 2)
          // as there are a lot of them, merge them
          if (($column >= $sourceFileColumns['topics'])
            && (!isset($sourceFileColumns['topics_max']) || $column <= $sourceFileColumns['topics_max'])) {
            if (!empty($item)) {
              if (!isset($convdata['topics'])) {
                $convdata['topics'] = [];
              }
              $convdata['topics'] = array_merge($convdata['topics'], $this->getTaxonomy($item, $this->topicList));
            }
          }
          break;
      }
    }
    return $convdata;
  }

  private function readCSVExport($sourceFile, $sourceFileColumns) {
    $reader = Reader::createFromPath($sourceFile, 'r');

    $records = $reader->getRecords();
    foreach($records as $offset => $record) {
      // ignore first row: it is the header but is not useful here, as it does not have unique column labels.
      if ($offset > 0) {
        $source = $this->analyzeExportRow($offset, $record, $sourceFileColumns);
        if (!empty($source)) {
          $this->sourcesList[] = $source;
        }
      }
    }
  }
  
  private function rearrangeSources() {
    // trying to sort it in a way, so that it will not touch the already imported data
    // the key factor is the id that is dependend on the running number and was created at the first run
    // therefore take the old import file and rearrange the new one, so that the order still fits
    // start with the old one
    $newSources = $this->sourcesList;
    // only used for statistical reasons
    $sameSources = [];
    $notfoundSources = [];
    // take the sources file from export one
    $readerOld = Reader::createFromPath($this->sourcesFileExportOne);
    foreach($readerOld->getRecords() as $offset => $record) {
      // the old structure has this format
      // Id | Title | Description | Accesspoints | Disciplines | Topics
      // ignore the first line
      $foundComparableRecord = FALSE;
      if ($offset > 0) {
        // we only have the url as a way to find out if it is the same
        foreach($this->sourcesList as $sourceId => $source) {
          // need to create the same string to compare it
          $compareUrl = $this->handleArrayCsv($source['accesspoints']);
          if ($compareUrl == $record[3]) {
            $sameSources[$offset] = $source;
            print "Found a direct match for running id $offset<br />";
            $foundComparableRecord = TRUE;
            unset($newSources[$sourceId]);
            break;
          }
        }
        if (!$foundComparableRecord) {
          print "Found not a match for running id $offset<br />";
          print "<pre>";
          print_r($record);
          print "</pre>";
          $notfoundSources[] = $record;
        }
      }
    }
    return $newSources;
  }

  private function writeCSVDisciplines($disciplineList, $disciplineFile) {
    print "===== DISCIPLINE LIST =====<br />";
    $csvDisciplineList = [];
    foreach($disciplineList as $termid=>$discipline) {
      print $discipline['name'] . ' | '
          . $discipline['description'] . ' | '
          . $discipline['count'] . "<br />";
      $csvDisciplineList[$termid+1] = [
        'name' => $discipline['name'],
        'description' => $discipline['description']
      ];
    }
    $this->writeTaxonomy($csvDisciplineList, $disciplineFile);
    //var_dump($disciplineList);
  }
  
  private function writeCSVTopics($topicList, $topicFile) {
    print "======= TOPIC LIST ========<br />";
    $csvTopicList = [];
    foreach($topicList as $termid => $topic) {
      print $topic['name'] . ' | '
          . $topic['description'] . ' | '
          . $topic['count'] . "<br />";
      $csvTopicList[$termid+1] = [
        'name' => $topic['name'],
        'description' => $topic['description']
      ];
    }
    $this->writeTaxonomy($csvTopicList, $topicFile);
    //var_dump($disciplineList);
  }

  private function writeCSVSources($sourcesList, $sourcesFile) {
    print "<br />";
    print "========== SOURCES ========<br />";
    print "<pre>";
    
    $this->writeSources($sourcesList, $sourcesFile);
    
    print_r(json_encode($sourcesList));
    print "</pre>";
    print "<pre>";
    print_r($sourcesList);
    print "</pre>";
  }

  private function getNewSourcesImportTwo() {
    // it is quite simple for import two
    // everything from line 30 on is a new source
    // update: as we kick out the ones that do not have a url, the line 30 is not available anymore
    // but we know, that from the source "DataOne" on, all otheres are new
    // not nice, @todo: find a better solution
    // here we also define that they all are relevant for import two = TTT (but that is indirectly clear, do this in the import file)
    $newSources = [];
    $oldData = TRUE;
    foreach($this->sourcesList as $offset => $items) {
      /*if ($offset < 30) {
        continue;
      }*/
      if ($oldData) {
        if ($items['title'] == 'DataOne') {
          $oldData = FALSE;
        } else {
          continue;
        }
      }
      $newSources[] = $items;
    }
    return $newSources;
  }

  /**
   * Main public function to create the csv export files for Drupal.
   */
  public function createImport() {
    // there are different states of import

    // import 1: is the very first one, that was for T6.3
    // as it is already in the database, this is only for documenting the way how to 
    // create the files of this import (and they are necessary if looking for differences which is done in import 2)
    // we always need the basic import settings for the topics and disciplines list
    $this->readCSVExport($this->sourceFileImportOne, $this->sourceFileColumnsImportOne);
    if ($this->importOne) {
      $this->writeCSVDisciplines($this->disciplineList, $this->disciplineFileImportOne);
      $this->writeCSVTopics($this->topicList, $this->topicFileImportOne);
      $this->writeCSVSources($this->sourcesList, $this->sourcesFileExportOne);
    }

    $this->readCSVExport($this->sourceFileImportTwo, $this->sourceFileColumnsImportTwo);
    if ($this->importTwo) {
      $this->writeCSVDisciplines($this->disciplineList, $this->disciplineFileImportTwo);
      $this->writeCSVTopics($this->topicList, $this->topicFileImportTwo);
      // only the new ones are imported, the old ones are ignored
      //$importTwoSources = $this->rearrangeSources($this->sourcesFileExportTwo);
      $importTwoSources = $this->getNewSourcesImportTwo($this->sourcesFileExportTwo);
      print "<pre>";
      print_r($importTwoSources);
      print "</pre>";
      $this->writeCSVSources($importTwoSources, $this->sourcesFileExportTwo);
    }
  }
}

$createImport = new PrepareCSVForDrupal();
$createImport->createImport();
