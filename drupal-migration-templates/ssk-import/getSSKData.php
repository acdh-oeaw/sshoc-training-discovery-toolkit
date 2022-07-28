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
 *     Installation information: https://csv.thephpleague.com/9.0/installation/
 *     As can be seen in the code, I worked with verson 9.4.1
 *     @todo: use composer for getting the library
 * - It is expected that the scenarios from SSK
 *   (https://github.com/ParthenosWP4/SSK/tree/master/scenarios) are available
 *   The directory where the XML files of the scenarios are harvested are defined
 *   in getSSKData::directory (currently: ssk_scenarios/)
 */
use League\Csv\Writer;

require 'csv-9.4.1/autoload.php';

class getSSKData {
  /* this fields from training materials database are ignored
   * because they are bound to controlled vocabularies, therefore would need
   * reconciliation (something we don't do in the training inventory)
   *  - intended audience
   *  - format
   *  - curated topics
   */

  /**
   * Only process GitHub if necessary (not in test mode), set TRUE to process it.
   * 
   * @var boolean
   */
  private $processGitHub = FALSE; //TRUE;

  /**
   * For test issues process only the test file defined in the $this->fileName
   * 
   * @var boolean
   */
  private $processOnlyTestFile = FALSE; //TRUE;
  
  private $directory = 'ssk_scenarios/';
  
  /**
   * Only relevant if $this->processOnlyTestFile is TRUE
   * @var string
   */
  private $fileName = 'SSK_sc_linguisticAnnotationOfCorpora.xml';
  
  private $uriSSK = 'http://ssk.huma-num.fr/#/scenarios/';
  
  private $gitHubPath = 'https://api.github.com/repos/ParthenosWP4/SSK/commits?path=scenarios%2F';
  
  private $trainingSourceId = 40;
  
  private $outputCSV = 'ssk_data.csv';
  
  private $outputCSVContributors = 'ssk_data_contributors.csv';
  
  /**
   * All data of the TEI files are put into this variable.
   * 
   * @var array
   */
  private $allDataSSK = [];
  
  /**
   * Some of the files are ignored for imprt to the SSK website. As it seems to
   * be hardcoded, there is no way to check it automatically. Therefore also doing
   * it hardcoded here.
   * 
   * @var array
   */
  private $ignoreScenarios = [
    'SSK_sc_3Dclassification.xml',
    'SSK_sc_manualTranscription_unst.xml',
    'SSK_sc_FTIR.xml'
  ];

  /**
   * Loads an TEI file
   * 
   * @param string $fileName
   * @return object:SimpleXMLElement | FALSE
   *   returns FALSE if file was not found/not valid
   */
  private function loadFile($fileName) {
    if (file_exists($this->directory . $fileName)) {
      return simplexml_load_file($this->directory . $fileName);
    }
    return FALSE;
  }
  
  /**
   * Identifies all xml-files in a directory.
   * 
   * @return array
   *   Array with all relevant filenames (including the scenarios to ignore).
   */
  private function getAllXMLFiles() {
    $xmlFiles = [];
    // open directory
    $d = dir($this->directory);
    while(false !== ($entry = $d->read())) {
      // search for xml-files
      if (strpos($entry, '.xml')) {
        $xmlFiles[] = $entry;
      }
    }
    $d->close();

    return $xmlFiles;
  }
  
  private function convertTEIString($teiString) {
    if (!empty($teiString)) {
      // check if it is a literal/string - can't use is_string because we need
      // to deal also with objects that are strings
      if (!is_array($teiString)) {
        // need to do:
        // - trimming
        // - in tei we often break the line after 80 characters. here we like to have
        //   everything in a row, therefore delete linebreaks and delete double
        //   empty chars
        // - evaluate: process further tei/html in description, e.g. <br />?
        $converted =
          str_replace("\n", ' ',
            str_replace("\r", ' ',
              str_replace("\t", ' ',
                html_entity_decode(
                  trim($teiString))))
        );
        while (strpos($converted, '  ') !== FALSE) {
          $converted = str_replace('  ', '', $converted);
        }
        return $converted;
      } else {
        // we only have flat arrays (holding only strings and no further arrays)
        // therefore doing a recursive activity here (this will also deal with
        // arrays in arrays)
        foreach($teiString as $key=>$part) {
          $teiString[$key] = $this->convertTEIString($part);
          // we want to be sure that no pipe is used in the strings of an array
          // because this is the separator for the output
          if (!is_array($teiString[$key])) {
            if (strpos($teiString[$key], '|') !== FALSE) {
              print 'WARNING: There is a pipe in usage: '.$teiString[$key]."<br />";
            }
          }
        }
      }
    }
    return $teiString;
  }
  
  private function pushDataSSK(&$dataSSK, $key, $value) {
    if (!empty($value)) {
      if (!empty($dataSSK[$key])) {
        if (is_array($dataSSK[$key]) && is_array($value)) {
          $dataSSK[$key] = array_merge($dataSSK[$key], $this->convertTEIString($value));
        } elseif (!is_array($dataSSK[$key]) && !is_array($value)) {
          $dataSSK[$key] .= $this->convertTEIString($value);
        } else {
          // there are other combinations possible, but that shouldn't happen
          // therefore give a warning here
          print "WARNING: merge of $key is not possible: ";
          /*print "<pre>";
          print_r($dataSSK[$key]);
          print "</pre>";
          print "<br />" . gettype($dataSSK[$key]) . (!empty($dataSSK[$key])?' not empty '.$dataSSK[$key][0]:' empty')."<br />";
          print_r($value);
          print "<br />" . gettype($value) . "<br />";
          print "<br />";*/
        }
      } else {
        $dataSSK[$key] = $this->convertTEIString($value);
      }
    } else {
      $dataSSK[$key] = ''; //<div style="color: red">MISSING</div>';
    }
  }
  
  function getSSKContributors($authors) {
    $contributors = [];
    if (!empty($authors)) {
      foreach($authors as $author) {
        if (!empty($author->forename) && !empty($author->surname)) {
          $contributors[] = $author->forename . ' ' . $author->surname;
        }
      }
    }
    return $contributors;
  }
  
  function getSSKExtent($researchSteps) {
    $extent = '';
    if (!empty($researchSteps)) {
      // @todo: extent and 'steps' need to be handled in different fields
      $extent = count($researchSteps);
      if ($extent > 1) {
        $extent .= ' steps';
      } elseif ($extent == 1) {
        $extent .= ' step';
      } else {
        $extent = '';
      }
    }
    return $extent;
  }
  
  /**
   * Extracts information from the teiHeader
   * 
   * @param object:SimpleXMLElement $teiHeader
   * @return array
   */
  private function processTEIHeader($teiHeader, &$dataSSK) {
    if (!empty($teiHeader)) {
      // license
      $this->pushDataSSK(
        $dataSSK, 'license',
        $teiHeader->publicationStmt->availability->licence['target']
      );

      // contributors
      $this->pushDataSSK(
        $dataSSK, 'contributors',
        $this->getSSKContributors($teiHeader->titleStmt->author)
      );
    }
  }
  
  private function extractCategories($teiCategories) {
    $categories = [
      'disciplines' => [],
      'tadirahTechnique' => [],
      'tadirahObject' => [],
      'tadirahActivity' => [],
      'nemo' => [],
      'standard' => []
    ];

    if (!empty($teiCategories)) {
      // first get the terms out of the TEI (there are other values possible)
      $terms = [];
      foreach($teiCategories as $descs) {
        if ($descs['type'] == 'terms') {
          $terms = $descs;
          break;
        }
      }
      // if there are terms defined, extract and analyze them (there are different
      // categories possible)
      foreach($terms as $term) {
        // disciplines
        if ($term['type'] == 'discipline') {
          $discipline = '';
          // there are two different ways to define the disciplines
          // one is in the key-attribute of the tag term
          // the other in the value of the tag term
          // both are handled here, the key-attribute is prioritized
          // @todo: in ssk harmonize this regarding the manual
          if (empty((string)$term['key'][0])) {
            $discipline = (string)$term[0][0];
          } else {
            $discipline = (string)$term['key'][0];
          }
          if (!empty($discipline)) {
            $categories['disciplines'][] = $discipline;
          }
        }
        // tadirah terms - there are different spellings of Tadirah, therefore
        // doing a strtoupper
        // @todo: in ssk harmonize the spelling of Tadirah
        if ((strtoupper($term['source']) == strtoupper('Tadirah')) ||
          ($term['source'] == 'http://tadirah.dariah.eu/')) {
          // tadirah technique and tadirah object
          // sometimes they are spelled "objects" and sometimes "object"
          // also "techniques" and "technique"
          // @todo: in ssk harmonize it
          if (($term['type'] == 'technique') || ($term['type'] == 'techniques')) {
            $categories['tadirahTechnique'][] = (string)$term['key'][0];
          } elseif (($term['type'] == 'object') || ($term['type'] == 'objects')) {
            $categories['tadirahObject'][] = (string)$term['key'][0];
          } elseif ($term['type'] == 'activity') {
            $categories['tadirahActivity'][] = (string)$term['key'][0];
          }
        } elseif ((strtoupper($term['source']) == strtoupper('Nemo'))) {
          // nemo terms
          $categories['nemo'][] = (string)$term['key'][0];
        } elseif ((strtoupper($term['source']) == strtoupper('SSK'))) {
          // standards terms
          $categories['standard'][] = (string)$term['key'][0];
        }
      }
    }

    return $categories;
  }
  
  private function processCreationUpdateFromGitHub($fileName) {
    $dates = [
      'created' => '',
      'updated' => '',
    ];

    // get the data with curl
    if ($this->processGitHub) {
      $objCurl = curl_init();
      curl_setopt($objCurl, CURLOPT_URL, $this->gitHubPath . $fileName);
      curl_setopt($objCurl, CURLOPT_USERAGENT, "SSK-training-inventory");
      curl_setopt($objCurl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($objCurl, CURLOPT_RETURNTRANSFER, 1);
      // make an array out of the response
      $response = json_decode(curl_exec($objCurl), TRUE);
      curl_close($objCurl);

      // the first is the update-date
      // the last the create-date
      if (!empty($response)) {
        $dates['updated'] = $response[0]['commit']['author']['date'];
        $dates['created'] = $response[count($response)-1]['commit']['author']['date'];
      }
    }
    
    return $dates;
  }
  
  /**
   * The language is defined as xml:lang in the title and the description of the
   * TEI file. It can happen, that only the title has a translation, but not the
   * description. In this case, take only languages that are defined in title
   * and in description. English is default.
   * 
   * @param string $title
   *   The XML-path to the title
   * @param string $description
   *   The XML-path to the title
   * @return array
   *   Returns the languages that are defined in two characters ISO language
   */
  private function processLanguages($title, $description) {
    $languages = ['English'];
    // to get the xml:lang we need to set the namespace for the attribute
    // we also need to cast it as array and the the @attributes out of it
    // @todo: check if attributes exist
    // @problem: the lang is in the same path but different xml:lang-attributes,
    // therefore i would need to process this before this method
    // @todo: not really necessary, but keep in mind
    /*print "<pre>";
    print_r($title);
    print "</pre>";*/
    $languages_title = ((array)$title->attributes('xml', TRUE))['@attributes'];
    $languages_description = ((array)$description->attributes('xml', TRUE))['@attributes'];
    /*print "<pre>";
    print_r($languages_title);
    print "</pre>";*/
    // @todo: current situation: it is not language aware due to the problem of
    // having attributes not in one path but in different paths
    foreach($languages_title as $lang) {
      if (in_array($lang, $languages_description)) {
        switch ($lang) {
          case 'en':
            // nothing to be done, as it is already in the array;
            break;
          case 'fr':
            $languages[] = 'French';
            break;
          case 'de':
            $languages[] = 'German';
            break;
          default:
            // we don't have it currently, so give a warning
            print "WARNING: Language $lang is not resolving!";
            break;
        }
      }
    }

    return $languages;
  }

  private function processTEIBody($teiBody, &$dataSSK) {
    if (!empty($teiBody)) {
      // title
      $this->pushDataSSK($dataSSK, 'title', $teiBody->head);

      // description
      $this->pushDataSSK($dataSSK, 'description', $teiBody->desc);
      
      // languages
      $languages = $this->processLanguages($teiBody->head, $teiBody->desc);
      $this->pushDataSSK($dataSSK, 'languages', $languages);

      // extent: x steps
      $this->pushDataSSK($dataSSK, 'extent',
        $this->getSSKExtent($teiBody->listEvent->event));
      
      // categories
      $categories = $this->extractCategories($teiBody->desc);
      // disciplines
      $this->pushDataSSK($dataSSK, 'disciplines', $categories['disciplines']);
      // topics
      $this->pushDataSSK($dataSSK, 'topics',
        array_merge(
          $categories['tadirahTechnique'],
          $categories['tadirahObject'],
          $categories['tadirahActivity'],
          $categories['nemo'],
          $categories['standard']
        )
      );
    }
  }

  private function processTEIFile($fileName, &$dataSSK) {
    if (!empty($fileName)) {
      // first get the uriName out of the fileName
      $lastBash = strrpos($fileName, '/');
      $lastFileEnding = strrpos($fileName, '.xml');
      $uriName = substr($fileName,
          ($lastBash===FALSE?0:$lastBash+1),
          $lastFileEnding - $lastBash);

      // id
      $this->pushDataSSK($dataSSK, 'id', $uriName);

      // accesspoint
      $this->pushDataSSK($dataSSK, 'accesspoint', $this->uriSSK . urlencode($uriName));
      
      // created/updated
      $dates = $this->processCreationUpdateFromGitHub($uriName . '.xml');
      // created
      $this->pushDataSSK($dataSSK, 'created', $dates['created']);
      // updated
      $this->pushDataSSK($dataSSK, 'updated', $dates['updated']);
    }
  }
  
  private function processOtherData(&$dataSSK) {
    // source of item
    $this->pushDataSSK($dataSSK, 'source', $this->trainingSourceId);
  }
  
  private function showDataSSK($dataSSK) {
    print (!empty($dataSSK)?'<table>':'No data found');
    foreach($dataSSK as $training=>$ssk) {
      print "<tr><td>$training</td><td>";
      if (is_array($ssk)) {
        print '<ul>';
        foreach($ssk as $val) {
          print "<li>";
          if (!empty($val)) {
            print $val;
          } else {
            print '<div style="color: red">MISSING</div>';
          }
          print "</li>";
        }
        print '</ul>';
      } else {
        if (!empty($ssk)) {
          print $ssk;
        } else {
          print '<div style="color: red">MISSING</div>';
        }
      }
      print "</td></tr>";
    }
    print (!empty($dataSSK)?'</table>':'');
    print '<hr />';
  }
  
  /**
   * Takes the XML data from a TEI file and processes it
   */
  public function getXMLData() {
    if ($this->processOnlyTestFile) {
      $xmlFiles = [$this->fileName];
    } else {
      $xmlFiles = $this->getAllXMLFiles();
    }

    $count = 0;
    foreach($xmlFiles as $fileName) {
      if (in_array($fileName, $this->ignoreScenarios)) {
        // Ignore scenarios.
        continue;
      }
      $xml = $this->loadFile($fileName);
      if ($xml !== FALSE) {
        $dataSSK = [];
      
        $this->processTEIHeader($xml->teiHeader->fileDesc, $dataSSK);
        $this->processTEIBody($xml->text->body->div, $dataSSK);
      
        // we also have data that is coming from the filename itself
        $this->processTEIFile($fileName, $dataSSK);
      
        // and there is static data
        $this->processOtherData($dataSSK);
      
        $this->showDataSSK($dataSSK);
        
        $this->allDataSSK[] = $dataSSK;
      }
      $count++;
    }
    print "Count of scenarios: $count <br />";
  }
  
  private function handleArrayCsv($convArray) {
    $conv = '';
    if (!empty($convArray)) {
      if (is_array($convArray)) {
        // assuming that | is not used in any of the terms
        $conv = implode('|', $convArray);
      } else {
        print "<pre>";
        print_r($convArray);
        print "</pre>";
        print "WARNING: structure for array conv is wrong <br />";
      }
    }
    return $conv;
  }

  public function writeDataIntoCSV() {
    // first line is for the headings
    // todo: use variable variables to have a better handling of this
    $records[] = [
      'id',
      'title',
      'description',
      'accesspoint',
      'license',
      'contributors',
      'languages',
      'extent_count',
      'extent_text',
      'disciplines',
      'topics',
      'created',
      'updated',
      'source'
    ];
    
    // necessary for the dedicated contributor export
    // start with one, otherwise asking for !empty produces a wrong entry
    $contributorId = 1;
    $contributors[] = [
      'contributor_id',
      'full_name'
    ];

    foreach($this->allDataSSK as $item) {
      // map the SSK data
      // it is guaranted, that for every field at least an empty value is set

      // doing some preprocessing
      // for extent split e.g. 6 steps should be 6 and 'steps'
      $extent_count = '';
      $extent_text = '';
      if (!empty($item['extent'])) {
        $extent_split = explode(' ', $item['extent']);
        $extent_count = $extent_split[0];
        $extent_text = (isset($extent_split[1])?$extent_split[1]:'steps');
      }
      
      // for contributors we have a specific handling
      // temporary store them and give them identifier
      // set the identifier in the main record set
      // and export the contributors in a specific file
      // @todo: put this into a method
      $recordContributors = '';
      foreach($item['contributors'] as $contributor) {
        if (!empty($contributor)) {
          // export file
          $contributors[] = [
            $contributorId,
            $contributor
          ];

          // used in record
          if (!empty($recordContributors)) {
            $recordContributors .= '|';
          }
          $recordContributors .= $contributorId;

          $contributorId++;
        }
      }

      // create the main record set
      $records[] = [
        $item['id'],
        $item['title'],
        $item['description'],
        $item['accesspoint'],
        $item['license'],
        // for arrays we need a special treatment - it uses the pipe as separator
        // this is necessary for drupal migration
        // @todo: handle cases where a pipe is used in the text
        // there is an output if a pipe is found, as long as this is not the case,
        // postpone this issue.
        //$this->handleArrayCsv($item['contributors']),
        $recordContributors,
        $this->handleArrayCsv($item['languages']),
        $extent_count,
        $extent_text,
        $this->handleArrayCsv($item['disciplines']),
        $this->handleArrayCsv($item['topics']),
        $item['created'],
        $item['updated'],
        $item['source']
      ];
    }
    // save records to csv
    $writer = Writer::createFromPath($this->outputCSV, 'w+');
    $writer->insertAll($records);

    // save contributors to csv
    $writer_contributors = Writer::createFromPath($this->outputCSVContributors, 'w+');
    $writer_contributors->insertAll($contributors);
  }

}

$sskData = new getSSKData();
$sskData->getXMLData();
$sskData->writeDataIntoCSV();