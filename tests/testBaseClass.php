<?php

require_once __DIR__ . '/../setup.php';

error_reporting(E_ALL); // All tests run this way
if (!defined('VERBOSE')) define('VERBOSE', TRUE);

$SLOW_MODE = TRUE;

abstract class testBaseClass extends PHPUnit\Framework\TestCase {

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
    if (!getenv('PHP_OAUTH_CONSUMER_TOKEN')) {
      echo 'S'; // Skipping test: Risks exposing secret keys
      $this->assertNull(NULL); // Make Travis think we tested something
    } else {
      $function();
    }
  }

  protected function wastes_secrets($function) {
    if (getenv('TRAVIS_PULL_REQUEST') && (getenv('TRAVIS_PULL_REQUEST') !== 'false' )) {
      echo 'W'; // Skipping test: uses up a security key up
      $this->assertNull(NULL); // Make Travis think we tested something
    } else {
      $function();
    }
  }

  protected function requires_zotero($function) {
    $skip_zotero = TRUE; // TODO turn back on
    if ($skip_zotero && getenv('TRAVIS_PULL_REQUEST') && (getenv('TRAVIS_PULL_REQUEST') !== 'false' )) {
      echo 'Z'; // Skipping test: Zoteros is rubbish right now
      $this->assertNull(NULL); // Make Travis think we tested something
    } else {
      $function();
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
    $expanded = $this->prepare_citation($text);
    
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
