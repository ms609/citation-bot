<?php
declare(strict_types=1);

require_once __DIR__ . '/../setup.php';

define("BAD_PAGE_API", ""); // Remember that debug_print_backtrace(0, 6) can be helpful

final class TestPage extends Page {
    public function __construct() {
        $bad_functions = ['__construct', 'process_page', 'process_citation', 'runTest', 'runBare',
                          'run', 'requires_secrets', 'requires_bibcode', 'requires_zotero', '{closure}',
                          'make_citation', 'prepare_citation', 'parameter_parse_text_helper',
                          'expand_via_zotero', 'reference_to_template', 'fill_cache', '']; // Some of these should never occur
        $trace = debug_backtrace();
        $i = 0;
        while (in_array($trace[$i]['function'], $bad_functions, true)) {
             $i++; // Climb stack to find useful name
        }
        $this->title = $trace[$i]['function'];
        self::$last_title = $this->title;
        parent::__construct();
    }

    public function overwrite_text(string $text): void {
        $this->text = $text;
    }

    public function parse_text(string $text): void { // Save title from test name
        $save_title = $this->title;
        parent::parse_text($text);
        $this->title = $save_title;
        self::$last_title = $save_title;
    }
}

abstract class testBaseClass extends PHPUnit\Framework\TestCase {

    private bool $testing_skip_bibcode;
    private bool $testing_skip_wiki;

    function __construct() {
        parent::__construct();

        if (!PHP_ADSABSAPIKEY) {
             $this->testing_skip_bibcode = true;
        } else {
             $this->testing_skip_bibcode = false;
        }
        if (!getenv('PHP_OAUTH_CONSUMER_TOKEN') || !getenv('PHP_OAUTH_CONSUMER_SECRET') ||
                !getenv('PHP_OAUTH_ACCESS_TOKEN') || !getenv('PHP_OAUTH_ACCESS_SECRET')) {
             $this->testing_skip_wiki = true;
        } else {
             $this->testing_skip_wiki = false;
        }

        AdsAbsControl::small_give_up();
        AdsAbsControl::big_give_up();
        Zotero::block_zotero();
        gc_collect_cycles();
        $this->flush();
    }

    protected function requires_secrets(callable $function): void {
        if ($this->testing_skip_wiki) {
            $this->flush();
            echo 'A'; // For API, since W is taken
            $this->flush();
            $this->assertNull(null);
        } else {
            $function();
        }
    }

    // Only routines that absolutely need bibcode access since we are limited
    protected function requires_bibcode(callable $function): void {
        if ($this->testing_skip_bibcode) {
            $this->flush();
            echo 'B';
            $this->flush();
            AdsAbsControl::big_back_on();
            AdsAbsControl::big_give_up();
            AdsAbsControl::small_back_on();
            AdsAbsControl::small_give_up();
            $this->assertNull(null);
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
    protected function requires_zotero(callable $function): void {
        try {
            usleep(300000); // Reduce failures
            Zotero::unblock_zotero();
            $function();
        } finally {
            Zotero::block_zotero();
        }
    }

    protected function make_citation(string $text): Template {
        $tp = new TestPage(); unset($tp); // Fill page name with test name for debugging
        $this->flush();
        Template::$all_templates = [];
        Template::$date_style = DateStyle::DATES_WHATEVER;
        $this->assertSame('{{', mb_substr($text, 0, 2));
        $this->assertSame('}}', mb_substr($text, -2));
        $template = new Template();
        $template->parse_text($text);
        return $template;
    }

    protected function prepare_citation(string $text): Template {
        $template = $this->make_citation($text);
        $template->prepare();
        return $template;
    }

    protected function process_citation(string $text): Template {
        $page = $this->process_page($text);
        $expanded_text = $page->parsed_text();
        $template = new Template();
        $template->parse_text($expanded_text);
        return $template;
    }

    protected function process_page(string $text): TestPage { // Only used if more than just a citation template
        $this->flush();
        Template::$all_templates = [];
        Template::$date_style = DateStyle::DATES_WHATEVER;
        $page = new TestPage();
        $page->parse_text($text);
        $page->expand_text();
        return $page;
    }

    protected function parameter_parse_text_helper(string $text): Parameter {
        $this->flush();
        $parameter = new Parameter();
        $parameter->parse_text($text);
        return $parameter;
    }

    protected function getDateAndYear(Template $input): ?string {
        // Generates string that makes debugging easy and will throw error
        if (is_null($input->get2('year'))) { 
            return $input->get2('date'); // Might be null too
        }
        if (is_null($input->get2('date'))) {
            return $input->get2('year');
        }
        return 'Date is ' . $input->get2('date') . ' and year is ' . $input->get2('year');
    }

    protected function expand_via_zotero(string $text): Template {
        $expanded = $this->make_citation($text);
        Zotero::expand_by_zotero($expanded);
        $expanded->tidy();
        return $expanded;
    }

    protected function reference_to_template(string $text): Template {
        $this->flush();
        $text=trim($text);
        if (preg_match("~^(?:<(?:\s*)ref[^>]*?>)(.*)(?:<\s*?\/\s*?ref(?:\s*)>)$~i", $text, $matches)) {
            $template = new Template();
            $template->parse_text($matches[1]);
            return $template;
        } else {
            report_error('Non-reference passsed to reference_to_template: ' . echoable($text));
        }
    }

    protected function flush(): void {
        $level = ob_get_level();
        for ($count = 0; $count < $level; $count++) {
            ob_end_flush();
        }
        flush();
        for ($count = 0; $count < $level; $count++) {
            ob_start();
        }
    }

    protected function fill_cache(): void { // Name is outdated
        Zotero::create_ch_zotero();
        $wb = new WikipediaBot();
        unset($wb);
        WikipediaBot::make_ch();
    }
}
