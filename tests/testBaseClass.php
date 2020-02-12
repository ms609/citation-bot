<?php

require_once __DIR__ . '/../setup.php';

error_reporting(E_ALL); // All tests run this way
if (!defined('VERBOSE')) define('VERBOSE', TRUE);

$BLOCK_BIBCODE_SEARCH = TRUE;
$BLOCK_ZOTERO_SEARCH = TRUE;
$SLOW_MODE = TRUE;

abstract class testBaseClass extends PHPUnit\Framework\TestCase {
  // Set to TRUE to commit skipping to GIT.  FALSE to not skip.  Something else to skip tests while debugging
  private $skip_zotero = TRUE; // TODO
  private $skip_bibcode= FALSE;

  protected function process_page($text) { // Only used if more than just a citation template
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }

  protected function parameter_parse_text_helper($text) {
    $parameter = new Parameter();
    $parameter->parse_text($text);
    return $parameter;
  }

  protected function requires_secrets($function) {
    if (!getenv('PHP_OAUTH_CONSUMER_TOKEN') || !getenv('PHP_OAUTH_CONSUMER_SECRET') ||
        !getenv('PHP_OAUTH_ACCESS_TOKEN')   || !getenv('PHP_OAUTH_ACCESS_SECRET')
       ) {
      echo 'S';
      ob_flush();
      $this->assertNull(NULL);
    } else {
      $function();
    }
  }
  
  protected function requires_google($function) {
    if (!getenv('PHP_GOOGLEKEY')) {
      echo 'G';
      ob_flush();
      $this->assertNull(NULL);
    } else {
      $function();
    }
  } 

  // Only routines that absolutely need bibcode access since we are limited 
  protected function requires_bibcode($function) {
    global $BLOCK_BIBCODE_SEARCH;
    if ($this->skip_bibcode !== FALSE && $this->skip_bibcode !== TRUE) {
      $this->assertNull('skip_bibcode bocks commit');
    }
    if ($this->skip_bibcode || !getenv('PHP_ADSABSAPIKEY')) {
      echo 'B';
      ob_flush();
      $this->assertNull(NULL);
    } else {
      try {
        $BLOCK_BIBCODE_SEARCH = FALSE;
        $function();
      } finally {
        $BLOCK_BIBCODE_SEARCH = TRUE;
      }
    }
  }

  // allows us to turn off zoreto tests
  protected function requires_zotero($function) {
    global $BLOCK_ZOTERO_SEARCH;
    if ($this->skip_zotero !== FALSE && $this->skip_zotero !== TRUE) {
      $this->assertNull('skip_zotero bocks commit');
    }
    if ($this->skip_zotero && getenv('TRAVIS_PULL_REQUEST') && (getenv('TRAVIS_PULL_REQUEST') !== 'false' )) { // Main build NEVER skips anything
      echo 'Z';
      ob_flush();
      $this->assertNull(NULL);
    } else {
      try {
        $BLOCK_ZOTERO_SEARCH = FALSE;
        $function();
      } finally {
        $BLOCK_ZOTERO_SEARCH = TRUE;
      }
    }
  } 
  
  protected function make_citation($text) {
    $this->assertSame('{{', mb_substr($text, 0, 2));
    $this->assertSame('}}', mb_substr($text, -2));
    $template = new Template();
    $template->parse_text($text);
    return $template;
  }
  
  protected function prepare_citation($text) {
    $this->assertSame('{{', mb_substr($text, 0, 2));
    $this->assertSame('}}', mb_substr($text, -2));
    $template = new Template();
    $template->parse_text($text);
    $template->prepare();
    return $template;
  }
  
  protected function process_citation($text) {
    $this->assertEquals('{{', mb_substr($text, 0, 2));
    $this->assertEquals('}}', mb_substr($text, -2));
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    $expanded_text = $page->parsed_text();
    $template = new Template();
    $template->parse_text($expanded_text);
    return $template;
  }

  protected function getDateAndYear($input){
    // Generates string that makes debugging easy and will throw error
    if (is_null($input->get('year'))) return $input->get('date') ; // Might be null too
    if (is_null($input->get('date'))) return $input->get('year') ;
    return 'Date is ' . $input->get('date') . ' and year is ' . $input->get('year');
  }

  protected function expand_via_zotero($text) {
    global $ch_zotero;
    $expanded = $this->make_citation($text);
    
    $ch_zotero = curl_init('https://tools.wmflabs.org/translation-server/web');
    curl_setopt($ch_zotero, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch_zotero, CURLOPT_USERAGENT, "Citation_bot");  
    curl_setopt($ch_zotero, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
    curl_setopt($ch_zotero, CURLOPT_RETURNTRANSFER, TRUE);   
    curl_setopt($ch_zotero, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch_zotero, CURLOPT_TIMEOUT, 45);

    expand_by_zotero($expanded);
    
    curl_close($ch_zotero);
    
    $expanded->tidy();
    return $expanded;
  }
 
  protected function reference_to_template($text) {
    $text=trim($text);
    if (preg_match("~^(?:<(?:\s*)ref[^>]*?>)(.*)(?:<\s*?\/\s*?ref(?:\s*)>)$~i", $text, $matches)) {
      $template = new Template();
      $template->parse_text($matches[1]);
      return $template;
    } else {
      trigger_error('Non-reference passsed to reference_to_template: ' . $text);
    }
  }
 
}
