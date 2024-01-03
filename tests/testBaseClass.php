<?php
declare(strict_types=1);

require_once __DIR__ . '/../setup.php';

define("BAD_PAGE_API", ""); // Remember that debug_print_backtrace(0, 6) can be helpful


final class TestPage extends Page {
  function __construct() {
    $bad_functions = array('__construct', 'process_page', 'process_citation', 'runTest', 'runBare',
			   'run', 'requires_secrets', 'requires_bibcode', 'requires_zotero', '{closure}',
			   'make_citation', 'prepare_citation', 'parameter_parse_text_helper',
			   'expand_via_zotero', 'reference_to_template', 'fill_cache', ''); // Some of these should never occur
    $trace = debug_backtrace();
    $name = $trace[0]['function']; // Should be __construct
    if (in_array($name, $bad_functions)) $name = $trace[1]['function'];
    if (in_array($name, $bad_functions)) $name = $trace[2]['function'];
    if (in_array($name, $bad_functions)) $name = $trace[3]['function'];
    if (in_array($name, $bad_functions)) $name = $trace[4]['function'];
    if (in_array($name, $bad_functions)) $name = $trace[5]['function'];
    if (in_array($name, $bad_functions)) $name = $trace[6]['function'];
    if (in_array($name, $bad_functions)) $name = $trace[7]['function'];
    if (in_array($name, $bad_functions)) $name = $trace[8]['function'];
    if (in_array($name, $bad_functions)) $name = $trace[9]['function'];
    if (in_array($name, $bad_functions)) {
      print_r($trace);
      trigger_error("Bad function name in TestPage");
    }
    $this->title = $name;
    self::$last_title = $this->title;
    parent::__construct();
  }

  public function overwrite_text(string $text) : void {
    $this->text = $text;
  }

  public function parse_text(string $text) : void { // Save title from test name
    $save_title = $this->title;
    parent::parse_text($text);
    $this->title = $save_title;
    self::$last_title = $save_title;
  }
}

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
      $this->flush();
      echo 'A'; // For API, since W is taken
      $this->flush();
      $this->assertNull(NULL);
    } else {
      $function();
    }
  }

  // Only routines that absolutely need bibcode access since we are limited
  protected function requires_bibcode(callable $function) : void {
    if ($this->testing_skip_bibcode) {
      $this->flush();
      echo 'B';
      $this->flush();
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
	usleep(300000); // Reduce failures
	Zotero::unblock_zotero();
	$function();
      } finally {
	Zotero::block_zotero();
      }
  }

  protected function make_citation(string $text) : Template {
    $tp = new TestPage(); unset($tp); // Fill page name with test name for debugging
    $this->flush();
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
    $this->flush();
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
    $text=trim($text);
    if (preg_match("~^(?:<(?:\s*)ref[^>]*?>)(.*)(?:<\s*?\/\s*?ref(?:\s*)>)$~i", $text, $matches)) {
      $template = new Template();
      $template->parse_text($matches[1]);
      return $template;
    } else {
      report_error('Non-reference passsed to reference_to_template: ' . echoable($text));
    }
  }

  protected function flush() : void {
     ob_end_flush();
     flush();
     ob_start();
  }

  protected function fill_cache() : void { // complete list of DOIs and HDLs that TRUE/FALSE in test suite as of 18 MAY 2022
    Zotero::create_ch_zotero();
    $wb = new WikipediaBot();
    unset($wb);
    WikipediaBot::make_ch();
  }
}
