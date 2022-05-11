<?php
declare(strict_types=1);

require_once __DIR__ . '/../setup.php';

define("BAD_PAGE_API", ""); // Remember that debug_print_backtrace(0, 6) can be helpful

abstract class testBaseClass extends PHPUnit\Framework\TestCase {

  private $testing_skip_bibcode= FALSE;
  private $testing_skip_wiki   = FALSE;
  
  function __construct() {
    parent::__construct();

   // Non-trusted builds
    if (!PHP_ADSABSAPIKEY) $this->testing_skip_bibcode = TRUE;
    if (!getenv('PHP_OAUTH_CONSUMER_TOKEN') || !getenv('PHP_OAUTH_CONSUMER_SECRET') ||
        !getenv('PHP_OAUTH_ACCESS_TOKEN')   || !getenv('PHP_OAUTH_ACCESS_SECRET')) {
       $this->testing_skip_wiki = TRUE;
    }

    AdsAbsControl::small_give_up();
    AdsAbsControl::big_give_up();
    Zotero::block_zotero();
    gc_collect_cycles();
  }

  protected function requires_secrets(callable $function) : void {
    if ($this->testing_skip_wiki) {
      echo 'A'; // For API, since W is taken
      ob_flush();
      $this->assertNull(NULL);
    } else {
      $function();
    }
  }

  // Only routines that absolutely need bibcode access since we are limited 
  protected function requires_bibcode(callable $function) : void {
    if ($this->testing_skip_bibcode) {
      echo 'B';
      ob_flush();
      AdsAbsControl::big_back_on();
      AdsAbsControl::big_give_up();
      AdsAbsControl::small_back_on();
      AdsAbsControl::small_give_up();
      $this->assertNull(NULL);
    } else {
      try {
        AdsAbsControl::big_back_on();
        AdsAbsControl::small_back_on();
        $function();
      } finally {
        AdsAbsControl::big_give_up();
        AdsAbsControl::small_give_up();
      }
    }
  }

  // Speeds up non-zotero tests
  protected function requires_zotero(callable $function) : void {
      try {
        Zotero::unblock_zotero();
        $function();
      } finally {
        Zotero::block_zotero();
      }
  } 
  
  protected function make_citation(string $text) : Template {
    Template::$all_templates = array();
    Template::$date_style = DATES_WHATEVER;
    $this->assertSame('{{', mb_substr($text, 0, 2));
    $this->assertSame('}}', mb_substr($text, -2));
    $template = new Template();
    $template->parse_text($text);
    return $template;
  }
  
  protected function prepare_citation(string $text) : Template {
    $template = $this->make_citation($text);
    $template->prepare();
    return $template;
  }
  
  protected function process_citation(string $text) : Template {
    $page = $this->process_page($text);
    $expanded_text = $page->parsed_text();
    $template = new Template();
    $template->parse_text($expanded_text);
    return $template;
  }
    
  protected function process_page(string $text) : TestPage { // Only used if more than just a citation template
    Template::$all_templates = array();
    Template::$date_style = DATES_WHATEVER;
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
    $expanded = $this->make_citation($text);
    Zotero::expand_by_zotero($expanded);
    $expanded->tidy();
    return $expanded;
  }
 
  protected function reference_to_template(string $text) : Template {
    $matches = ['', '']; // prevent memory leak in some PHP versions
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
