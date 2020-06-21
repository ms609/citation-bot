<?php
declare(strict_types=1);

require_once(__DIR__ . '/../setup.php');

error_reporting(E_ALL); // All tests run this way
if (!defined('VERBOSE')) define('VERBOSE', TRUE);

// Change these to temporarily disable sets of tests======================
$testing_skip_zotero = FALSE;  // failing but still good idea                                         //
$testing_skip_bibcode= FALSE;                                           //
$testing_skip_google = FALSE;                                           //
$testing_skip_wiki   = TRUE;                                           //
$testing_skip_dx     = FALSE;                                           //
$testing_skip_arxiv  = FALSE;                                           //
// =======================================================================

// Non-trusted builds
if (!getenv('PHP_ADSABSAPIKEY')) $testing_skip_bibcode = TRUE;
if (!getenv('PHP_GOOGLEKEY')) $testing_skip_google = TRUE;
if (!getenv('PHP_OAUTH_CONSUMER_TOKEN') || !getenv('PHP_OAUTH_CONSUMER_SECRET') ||
    !getenv('PHP_OAUTH_ACCESS_TOKEN')   || !getenv('PHP_OAUTH_ACCESS_SECRET')) {
   $testing_skip_wiki = TRUE;
}

// Main build skips nothing
if (getenv('TRAVIS_PULL_REQUEST') === 'false') {
   $testing_skip_zotero = FALSE;
   $testing_skip_bibcode= FALSE;
   $testing_skip_google = FALSE;
   $testing_skip_wiki   = FALSE;
   $testing_skip_dx     = FALSE;
   $testing_skip_arxiv  = FALSE;
}

$BLOCK_BIBCODE_SEARCH = TRUE;
$BLOCK_ZOTERO_SEARCH = TRUE;
$SLOW_MODE = TRUE;

abstract class testBaseClass extends PHPUnit\Framework\TestCase {

  protected function requires_secrets(callable $function) : void {
    global $testing_skip_wiki;
    if ($testing_skip_wiki) {
      echo 'S';
      ob_flush();
      $this->assertNull(NULL);
    } else {
      $function();
    }
  }
  
  protected function requires_google(callable $function) : void {
    global $testing_skip_google;
    if ($testing_skip_google) {
      echo 'G';
      ob_flush();
      $this->assertNull(NULL);
    } else {
      $function();
    }
  }
    
    
    
  protected function requires_dx(callable $function) : void {
    global $testing_skip_dx;
    if ($testing_skip_dx) {
      echo 'X';
      ob_flush();
      $this->assertNull(NULL);
    } else {
      $function();
    }
  }
    
  protected function requires_arxiv(callable $function) : void {
    global $testing_skip_arxiv;
    if ($testing_skip_arxiv) {
      echo 'V';
      ob_flush();
      $this->assertNull(NULL);
    } else {
      $function();
    }
  }

  // Only routines that absolutely need bibcode access since we are limited 
  protected function requires_bibcode(callable $function) : void {
    global $BLOCK_BIBCODE_SEARCH;
    global $testing_skip_bibcode;
    if ($testing_skip_bibcode) {
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
  protected function requires_zotero(callable $function) : void {
    global $BLOCK_ZOTERO_SEARCH;
    global $testing_skip_zotero;
    if ($testing_skip_zotero) {
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
  
  protected function make_citation(string $text) : Template {
    $this->assertSame('{{', mb_substr($text, 0, 2));
    $this->assertSame('}}', mb_substr($text, -2));
    $template = new Template();
    $template->parse_text($text);
    return $template;
  }
  
  protected function prepare_citation(string $text) : Template {
    $this->assertSame('{{', mb_substr($text, 0, 2));
    $this->assertSame('}}', mb_substr($text, -2));
    $template = new Template();
    $template->parse_text($text);
    $template->prepare();
    return $template;
  }
  
  protected function process_citation(string $text) : Template {
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
    
  protected function process_page(string $text) : TestPage { // Only used if more than just a citation template
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }

  protected function parameter_parse_text_helper(string $text) : Parameter {
    $parameter = new Parameter();
    $parameter->parse_text($text);
    return $parameter;
  }

  protected function getDateAndYear(Template $input) : ?string {
    // Generates string that makes debugging easy and will throw error
    if (is_null($input->get2('year'))) return $input->get2('date') ; // Might be null too
    if (is_null($input->get2('date'))) return $input->get2('year') ;
    return 'Date is ' . $input->get2('date') . ' and year is ' . $input->get2('year');
  }

  protected function expand_via_zotero(string $text) :  Template {
    global $ch_zotero;
    $expanded = $this->make_citation($text);
    
    $ch_zotero = curl_init(ZOTERO_ROOT);
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
 
  protected function reference_to_template(string $text) : Template {
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
