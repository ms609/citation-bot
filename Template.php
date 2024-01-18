<?php
declare(strict_types=1);
/*
 * Handle most aspects of citation templates
 * add_if_new() is generally called to add or sometimes overwrite parameters.
 */

// @codeCoverageIgnoreStart
require_once 'Parameter.php';
require_once 'expandFns.php';
require_once 'user_messages.php';
require_once 'constants.php';
require_once 'NameTools.php';
// @codeCoverageIgnoreEnd

final class Template {
  public const PLACEHOLDER_TEXT = '# # # CITATION_BOT_PLACEHOLDER_TEMPLATE %s # # #';
  public const REGEXP = ['~(?<!\{)\{\{\}\}(?!\})~su', '~\{\{[^\{\}\|]+\}\}~su', '~\{\{[^\{\}]+\}\}~su', '~\{\{(?>[^\{]|\{[^\{])+?\}\}~su'];  // Please see https://stackoverflow.com/questions/1722453/need-to-prevent-php-regex-segfault for discussion of atomic regex
  public const TREAT_IDENTICAL_SEPARATELY = FALSE; // This is safe because templates are the last thing we do AND we do not directly edit $all_templates that are sub-templates - we might remove them, but do not change their content directly
  /** @var array<Template> $all_templates */
  public static array $all_templates = array();  // List of all the Template() on the Page() including this one.  Can only be set by the page class after all templates are made
  public static int $date_style = DATES_WHATEVER;
  public static int $name_list_style = NAME_LIST_STYLE_DEFAULT;
  /** @psalm-suppress PropertyNotSetInConstructor */
  protected string $rawtext;  // Must start out as unset
  public string $last_searched_doi = '';
  protected string $example_param = '';
  protected string $name = '';
  /** @var array<Parameter> $param */
  protected array $param = array();
  /** @var array<string> $initial_param */
  protected array $initial_param = array();
  /** @var array<string> $initial_author_params */
  protected array $initial_author_params = array();
  protected string $initial_name = '';
  protected bool $doi_valid = FALSE;
  protected bool $had_initial_editor = FALSE;
  protected bool $had_initial_publisher = FALSE;
  protected bool $mod_dashes = FALSE;
  protected bool $mod_names = FALSE;
  protected bool $no_initial_doi = FALSE;
  /** @var array<array<string>> $used_by_api **/
  protected array $used_by_api = array(
			   'adsabs'   => array(),
			   'arxiv'    => array(),
			   'crossref' => array(),
			   'dx'       => array(),
			   'entrez'   => array(),
			   'jstor'    => array(),
			   'zotero'   => array(),
			);
  /** @var array<Template> $this_array */
  private array $this_array = array(); // Unset after using to avoid pointer loop that makes garbage collection harder

  function __construct() {
	 ; // Construction is in parse_text() and above in variable initialization
  }

  public function parse_text(string $text) : void {
	set_time_limit(120);
	/** @psalm-suppress RedundantPropertyInitializationCheck */
	if (isset($this->rawtext)) {
		report_error("Template already initialized"); // @codeCoverageIgnore
	}
	$this->rawtext = $text;
	$pipe_pos = strpos($text, '|');
	if ($pipe_pos) {
	  $this->name = substr($text, 2, $pipe_pos - 2); # Remove {{ and }}
	  $this->split_params(substr($text, $pipe_pos + 1, -2));
	} else {
	  $this->name = substr($text, 2, -2);
	}
	$this->initial_name = $this->name;
	// Clean up outdated redirects
	if (preg_match("~^(\s*)[\s\S]*?(\s*)$~", $this->name, $spacing)) { // Use non-greedy RegEx
	   $trim_name = trim($this->name);
	} else {
           bot_debug_log("RegEx failure in Template name: " . $this->name);
           $trim_name = $this->name;
           $spacing = array();
           $spacing[1] = '';
           $spacing[2] = '';
	}
	if (strpos($trim_name, "_") !== FALSE) {
	  $tmp_name = str_replace("_", " ", $trim_name);
	  if (in_array(strtolower($tmp_name), array_merge(TEMPLATES_WE_PROCESS, TEMPLATES_WE_SLIGHTLY_PROCESS, TEMPLATES_WE_BARELY_PROCESS, TEMPLATES_WE_RENAME))) {
		 $this->name = $spacing[1] . str_replace("_", " ", $trim_name) . $spacing[2];
		 $trim_name = str_replace("_", " ", $trim_name);
	  }
	}

	foreach (TEMPLATE_CONVERSIONS as $trial) {
	  if ($trim_name === $trial[0]) {
		$this->name = $spacing[1] . $trial[1] . $spacing[2];
		break;
	  }
	}
	while (strpos($this->name, 'Cite  ') === 0 || strpos($this->name, 'cite  ') === 0) {
	  $this->name = substr_replace($this->name, 'ite ', 1, 5);
	}
	$trim_name = trim($this->name); // Update if changed above
	// Cite article is actually cite news, but often used for journal by mistake - fix
	if (strtolower($trim_name) === 'cite article') {
	  if ($trim_name === 'Cite article') {
		$cite_caps = $spacing[1] . "Cite ";
	  } else {
		$cite_caps = $spacing[1] . "cite ";
	  }
	  if ($this->blank(['journal', 'pmid', 'pmc', 'doi', 's2cid', 'citeseerx'])) {
		$this->name = $cite_caps . 'news' . $spacing[2];
	  } else {
		$this->name = $cite_caps . 'journal' . $spacing[2];
	  }
	  // Cite paper is really cite journal
	} elseif (strtolower($trim_name) === 'cite paper' || strtolower($trim_name) === 'cite document') {
	  if ($trim_name === 'Cite paper' || $trim_name === 'Cite document') {
		$cite_caps = $spacing[1] . "Cite ";
	  } else {
		$cite_caps = $spacing[1] . "cite ";
	  }
	  if (!$this->blank_other_than_comments('journal')) {
		$this->name = $cite_caps . 'journal' . $spacing[2];
	  } elseif (!$this->blank_other_than_comments('newspaper')) {
		$this->name = $cite_caps . 'news' . $spacing[2];
	  } elseif (!$this->blank_other_than_comments('website') && $this->has('url')) {
		$this->name = $cite_caps . 'web' . $spacing[2];
	  } elseif (!$this->blank_other_than_comments('magazine')) {
		$this->name = $cite_caps . 'magazine' . $spacing[2];
	  } elseif (!$this->blank_other_than_comments(['encyclopedia', 'encyclopaedia'])) {
		$this->name = $cite_caps . 'encyclopedia' . $spacing[2];
	  } elseif (strpos($this->get('doi'), '/978-') !== FALSE || strpos($this->get('doi'), '/978019') !== FALSE || strpos($this->get('isbn'), '978-0-19') === 0 || strpos($this->get('isbn'), '978019') === 0) {
		$this->name = $cite_caps . 'book' . $spacing[2];
	  } elseif (!$this->blank_other_than_comments('chapter') || !$this->blank_other_than_comments('isbn')) {
		$this->name = $cite_caps . 'book' . $spacing[2];
	  } elseif (!$this->blank_other_than_comments(['journal', 'pmid', 'pmc'])) {
		$this->name = $cite_caps . 'journal' . $spacing[2];
	  } elseif (!$this->blank_other_than_comments('publisher') && $this->blank(['url', 'citeseerx', 's2cid'])) {
		$this->name = $cite_caps . 'document' . $spacing[2];
	  }
	}

	if (substr($this->wikiname(),0,5) === 'cite ' || $this->wikiname() === 'citation') {
	  if (preg_match('~< */? *ref *>~i', $this->rawtext) && (strpos($this->wikiname(), 'cite check') !== 0)) {
		 report_warning('reference within citation template: most likely unclosed template. ' . "\n" . echoable($this->rawtext) . "\n");
		 throw new Exception('page_error');
	  }
	}

	// extract initial parameters/values from Parameters in $this->param
	foreach ($this->param as $p) {
	  $this->initial_param[$p->param] = $p->val;

	  // Save author params for special handling
	  if (in_array($p->param, FLATTENED_AUTHOR_PARAMETERS) && $p->val) {
		$this->initial_author_params[$p->param] = $p->val;
	  }

	  // Save editor information for special handling
	  if (in_array($p->param, FIRST_EDITOR_ALIASES) && $p->val) {
		$this->had_initial_editor = TRUE;
	  }
	  if ($p->param === 'veditors' && $p->val) $this->had_initial_editor = TRUE;
	  if ($p->param === 'others' && $p->val) $this->had_initial_editor = TRUE; // Often tossed in there
	}
	$this->no_initial_doi = $this->blank('doi');

	if (!$this->blank(['publisher', 'location', 'publication-place', 'place'])) $this->had_initial_publisher = TRUE;

	if (isset($this->param[0])) {
		// Use second param as a template if present, in case first pair
		// is last1 = Smith | first1 = J.\n
		if (isset($this->param[1])) {
		   $example = $this->param[1]->parsed_text();
		} else {
		   $example = $this->param[0]->parsed_text();
		}
		$example = preg_replace('~[^\s=][^=]*[^\s=]~u', 'X', $example); // Collapse strings
		$example = preg_replace('~ +~u', ' ', $example); // Collapse spaces
		// Check if messed up, and do not use bad styles
		if ((substr_count($example, '=') !== 1) ||
			(substr_count($example, "\n") > 1) ||
			($example === 'X = X')) {
		   $example = ' X=X ';
		} elseif ($example === 'X=X') {
		   $example = 'X=X ';
		}
	} else {
		$example = ' X=X ';
	}
	$this->example_param = $example;

	if (in_array($this->wikiname(), TEMPLATES_WE_HARV)) {
	  $this->tidy_parameter('ref'); // Remove ref=harv or empty ref=
	}
	if (in_array($this->wikiname(), TEMPLATES_VCITE)) {
	  if ($this->has('doi')) {
		if ($this->verify_doi()) $this->forget('doi-broken-date');
	  }
	}
  }

  // Re-assemble parsed template into string
  public function parsed_text() : string {
	if ($this->has(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
	  if ($this->has('title') || $this->has('chapter') ||
		  ($this->has('journal') &&
		   ($this->get('volume') . $this->get('issue') !== '') &&
		   ($this->page() !== '') &&
		   ($this->year() !== ''))) {
		report_action("Converted Bare reference to template: " . echoable(trim(base64_decode($this->get(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))))));
		$this->quietly_forget(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
	  } else {
		return base64_decode($this->get(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL')));
	  }
	}
	if (stripos(trim($this->name), '#invoke:') === 0) {
		$add_pipe = FALSE;
		$wikiname = $this->wikiname();
		if (in_array($wikiname, TEMPLATES_WE_PROCESS) ||
			in_array($wikiname, TEMPLATES_WE_SLIGHTLY_PROCESS) ||
			in_array($wikiname, TEMPLATES_WE_BARELY_PROCESS) ||
			in_array($wikiname, TEMPLATES_WE_RENAME) ||
			strpos($wikiname, 'cite ') === 0) {
			$add_pipe = TRUE;
		}
		$joined = str_replace(array("\t", "\n", "\r", " "), '', $this->join_params());
		if (strpos($joined, "||") === 0) {
			$add_pipe = FALSE;
		}
		if ($add_pipe) {
			return '{{' . $this->name . '|' .  $this->join_params() . '}}';
		}
	}
	return '{{' . $this->name . $this->join_params() . '}}';
  }

  // Parts of each param: | [pre] [param] [eq] [value] [post]
  protected function split_params(string $text) : void {
	// Replace | characters that are inside template parameter/value pairs
	$PIPE_REGEX = "~(\[\[[^\[\]]*)(?:\|)([^\[\]]*\]\])~u";
	while (preg_match($PIPE_REGEX, $text)) {
	  $text = preg_replace_callback($PIPE_REGEX,
		  function(array $matches) : string {
			 return($matches[1] . PIPE_PLACEHOLDER . $matches[2]);
		  },
		  $text);
	}
	$params = explode('|', $text);
	foreach ($params as $i => $text_found) {
	  $this->param[$i] = new Parameter();
	  $this->param[$i]->parse_text($text_found);
	}
  }

  public function prepare() : void {
	set_time_limit(120);
	if (in_array($this->wikiname(), TEMPLATES_WE_PROCESS) || in_array($this->wikiname(), TEMPLATES_WE_SLIGHTLY_PROCESS)) {
	  // Clean up bad data
	  if (in_array($this->get('title'), ALWAYS_BAD_TITLES)) {
		  $this->set('title', '');
	  }
	  if (($this->get('title') === "Wayback Machine" || $this->get('title') === "Internet Archive Wayback Machine") && !$this->blank(['archive-url', 'archiveurl'])) {
		  $this->set('title', '');
	  }
	  if ($this->get('last1') === 'Published' || $this->get('last1') === 'published') {
		$this->forget('last1');
		if ($this->has('first1')) {
		  $this->rename('first1', 'author1');
		} elseif ($this->has('first')) {
		  $this->rename('first', 'author1');
		}
	  } elseif ($this->get('last') === 'Published' || $this->get('last') === 'published') {
		$this->forget('last');
		if ($this->has('first1')) {
		  $this->rename('first1', 'author1');
		} elseif ($this->has('first')) {
		  $this->rename('first', 'author1');
		}
	  }
	}
	if ($this->should_be_processed()) {
	  // Remove empty duplicates
	  if (!empty($this->param)) {
		$drop_me_maybe = array();
		foreach (ALL_ALIASES as $alias_list) {
		  if (!$this->blank($alias_list)) { // At least one is set
			$drop_me_maybe = array_merge($drop_me_maybe, $alias_list);
		  }
		}
		// Do it this way to avoid massive N*M work load (N=size of $param and M=size of $drop_me_maybe) which happens when checking if each one is blank
		foreach ($this->param as $key => $p) {
		  if (@$p->val === '' && in_array(@$p->param, $drop_me_maybe)) {
			 unset($this->param[$key]);
		  }
		}
	  }
	  foreach (DATES_TO_CLEAN as $date) {
		if ($this->has($date)) {
		  $input = $this->get($date);
		  if (stripos($input, 'citation') === FALSE) {
			$output = clean_dates($input);
			if ($input !== $output) {
			  $this->set($date, $output);
			}
		  }
		}
	  }
	  if ($this->get('last') === 'Archive' || $this->get('last1') === 'Archive') {
		if ($this->get('first2') === 'Get author RSS' ||
			$this->get('first3') === 'Get author RSS' ||
			$this->get('first4') === 'Get author RSS' ||
			($this->get('first2') === 'Email the' && $this->get('last2') === 'Author')) {
		  foreach (FLATTENED_AUTHOR_PARAMETERS as $author) {
			$this->forget($author);
		  }
		}
	  }
	  if (doi_works($this->get('doi')) === NULL) { // this can be slow. Prime cache for better slow step determination
		$this->tidy_parameter('doi'); // Clean it up now
	  }
	  $this->get_inline_doi_from_title();
	  $this->parameter_names_to_lowercase();
	  $this->use_unnamed_params();
	  $this->get_identifiers_from_url();
	  $this->id_to_param();
	  $this->correct_param_spelling();
	  $this->get_doi_from_text();
	  $this->fix_rogue_etal();

	  switch ($this->wikiname()) {
		case "cite arxiv":
		   // Forget dates so that DOI can update with publication date, not ARXIV date
		  $this->rename('date', 'CITATION_BOT_PLACEHOLDER_date');
		  $this->rename('year', 'CITATION_BOT_PLACEHOLDER_year');
		  expand_by_doi($this);
		  if ($this->blank('year') && $this->blank('date')) {
			  $this->rename('CITATION_BOT_PLACEHOLDER_date', 'date');
			  $this->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
		  } else {
			  $this->quietly_forget('CITATION_BOT_PLACEHOLDER_year'); // @codeCoverageIgnore
			  $this->quietly_forget('CITATION_BOT_PLACEHOLDER_date'); // @codeCoverageIgnore
		  }
		  break;
		case "cite journal":
		  if ($this->use_sici()) {
			report_action("Found and used SICI");
		  }
	  }
	  if ((stripos($this->rawtext, 'citation_bot_placeholder_comment') === FALSE) &&
		 (stripos($this->rawtext, 'graph drawing') === FALSE) &&
		 (stripos($this->rawtext, 'Lecture Notes in Computer Science') === FALSE) &&
		 (stripos($this->rawtext, 'LNCS ') === FALSE) &&
		 (stripos($this->rawtext, ' LNCS') === FALSE) &&
		 (!$this->blank(['pmc', 'pmid', 'doi', 'jstor']) ||
		 (stripos($this->get('journal') . $this->get('title'), 'arxiv') !== FALSE && !$this->blank(ARXIV_ALIASES)))) { // Have some good data
		  $the_title   = $this->get('title');
		  $the_journal = str_replace(['[', ']'], '', $this->get('journal'));
		  $the_chapter = $this->get('chapter');
		  $the_volume  = $this->get('volume');
		  $the_issue   = $this->get('issue');
		  $the_page    = $this->get('page');
		  $the_pages   = $this->get('pages');
		  if ($this->get2('chapter') === NULL) {
			 $no_start_chapter = TRUE;
		  } else {
			 $no_start_chapter = FALSE;
		  }
		  if ($this->get2('journal') === NULL) {
			 $no_start_journal = TRUE;
		  } else {
			 $no_start_journal = FALSE;
		  }
		  $initial_author_params_save = $this->initial_author_params;
		  $bad_data = FALSE;
		  if (stripos($the_journal, 'Advances in Cryptology') === 0 && stripos($the_title, 'Advances in Cryptology') === 0) {
			  $the_journal = '';
			  $this->forget('journal');
			  $bad_data = TRUE;
		  }
		  $ieee_insanity = FALSE;
		  if (conference_doi($this->get('doi')) && in_array($this->wikiname(), ['cite journal', 'cite web']) &&
			  ($this->has('isbn') ||
			  (stripos($the_title, 'proceedings') !== FALSE && stripos($the_journal, 'proceedings') !== FALSE) ||
			  (stripos($the_title, 'proc. ') !== FALSE && stripos($the_journal, 'proc. ') !== FALSE) ||
			  (stripos($the_title, 'Conference') !== FALSE && stripos($the_journal, 'Conference') !== FALSE) ||
			  (stripos($the_title, 'Colloquium') !== FALSE && stripos($the_journal, 'Colloquium') !== FALSE) ||
			  (stripos($the_title, 'Symposium') !== FALSE && stripos($the_journal, 'Symposium') !== FALSE) ||
			  (stripos($the_title, 'Extended Abstracts') !== FALSE && stripos($the_journal, 'Extended Abstracts') !== FALSE) ||
			  (stripos($the_title, 'Meeting on ') !== FALSE && stripos($the_journal, 'Meeting on ') !== FALSE))) { // IEEE/ACM/etc "book"
			  $data_to_check = $the_title . $the_journal . $the_chapter . $this->get('series');
			  if (stripos($data_to_check, 'IEEE Standard for') !== FALSE && $this->blank('journal')) {
				; // Do nothing
			  } elseif (stripos($data_to_check, 'SIGCOMM Computer Communication Review') !== FALSE) { // Actual journal with ISBN
				; // Do nothing
			  } elseif (stripos($data_to_check, 'Symposium') === FALSE &&
						stripos($data_to_check, 'Conference') === FALSE &&
						stripos($data_to_check, 'Proceedings') === FALSE &&
						stripos($data_to_check, 'Proc. ') === FALSE &&
						stripos($data_to_check, 'Workshop') === FALSE &&
						stripos($data_to_check, 'Symp. On ') === FALSE &&
						stripos($data_to_check, 'Meeting on ') === FALSE &&
						stripos($data_to_check, 'Colloquium') === FALSE &&
						stripos($data_to_check, 'Extended Abstracts') === FALSE &&
						stripos($the_journal, 'Visual Languages and Human-Centric Computing') === FALSE &&
						stripos($the_journal, 'Active and Passive Microwave Remote Sensing for') === FALSE
					   ) { // Looks like conference done, but does not claim so
				if ($the_journal !== '') {
				  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
				  $the_journal = '';
				}
				if ($the_title !== '') {
				  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
				  $the_title = '';
				}
				if ($the_chapter !== '') {
				  $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
				  $the_chapter = '';
				}
				$bad_data = TRUE;
			  } elseif (stripos($the_journal, 'Symposium') !== FALSE ||
						stripos($the_journal, 'Conference') !== FALSE ||
						stripos($the_journal, 'Proceedings') !== FALSE ||
						stripos($the_journal, 'Proc. ') !== FALSE ||
						stripos($the_journal, 'Workshop') !== FALSE ||
						stripos($the_journal, 'Symp. On ') !== FALSE ||
						stripos($the_journal, 'Meeting on ') !== FALSE ||
						stripos($the_journal, 'Colloquium') !== FALSE ||
						stripos($the_journal, 'Extended Abstracts') !== FALSE ||
						stripos($the_journal, 'Active and Passive Microwave Remote Sensing for') !== FALSE ||
						stripos($the_journal, 'Visual Languages and Human-Centric Computing') !== FALSE
					   ) {
				 $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
				 $ieee_insanity = TRUE;
				 $the_journal = '';
				 $bad_data = TRUE;
				 if ($the_title !== '') {
				   $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
				   $the_title = '';
				 }
				 if ($the_chapter !== '') {
				   $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
				   $the_chapter = '';
				 }
			  }
		  }
		  if (stripos($the_journal, 'Advances in Cryptology') === 0 ||
			  stripos($the_journal, 'IEEE Symposium') !== FALSE ||
			  stripos($the_journal, 'IEEE Conference') !== FALSE ||
			  stripos($the_journal, 'IEEE International Conference') !== FALSE ||
			  stripos($the_journal, 'ACM International Symposium') !== FALSE ||
			  stripos($the_journal, 'ACM Symposium') !== FALSE ||
			  stripos($the_journal, 'Extended Abstracts') !== FALSE ||
			  stripos($the_journal, 'IEEE International Symposium') !== FALSE ||
			  stripos($the_journal, 'Symposium on Theoretical Aspects') !== FALSE ||
			  stripos($the_journal, 'Lecture Notes in Computer Science') !== FALSE ||
			  stripos($the_journal, 'International Conference on ') !== FALSE ||
			  stripos($the_journal, 'ACM International Conference') !== FALSE ||
			  (stripos($the_journal, 'Proceedings of ') !== FALSE && stripos($the_journal, 'Conference') !== FALSE) ||
			  (stripos($the_journal, 'Proc. ') !== FALSE && stripos($the_journal, 'Conference') !== FALSE) ||
			  (stripos($the_journal, 'International') !== FALSE && stripos($the_journal, 'Conference') !== FALSE) ||
			  (stripos($the_journal, 'International') !== FALSE && stripos($the_journal, 'Meeting') !== FALSE) ||
			  (stripos($the_journal, 'International') !== FALSE && stripos($the_journal, 'Colloquium') !== FALSE) ||
			  stripos($the_journal, 'SIGGRAPH') !== FALSE ||
			  stripos($the_journal, 'Design Automation Conference') !== FALSE
			 ) {
			  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
			  $the_journal = '';
			  $bad_data = TRUE;
			  if ($the_title !== '') {
				  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
				  $the_title = '';
			  }
			  if ($the_chapter !== '') {
				  $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
				  $the_chapter = '';
			  }
		  }
		  if ($this->is_book_series('series') && $the_journal !== "") {
			  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
			  $the_journal = '';
			  $bad_data = TRUE;
			  if ($the_title !== '') {
				  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
				  $the_title = '';
			  }
			  if ($the_chapter !== '') {
				  $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
				  $the_chapter = '';
			  }
		  } elseif ($this->is_book_series('series') && $the_chapter === '' && $the_title !== '' && $this->has('doi')) {
			  $bad_data = TRUE;
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
		  }

		  if ($the_pages === '_' || $the_pages === '0' || $the_pages === 'null' || $the_pages === 'n/a' || $the_pages === 'online' || $the_pages === 'Online' || $the_pages === 'Forthcoming' || $the_pages === 'forthcoming') {
			  $this->rename('pages', 'CITATION_BOT_PLACEHOLDER_pages');
			  $the_pages = '';
			  $bad_data = TRUE;
		  }
		  if ($the_page === '_' || $the_page === '0' || $the_page === 'null' || $the_page === 'n/a' || $the_page === 'online' || $the_page === 'Online' || $the_page === 'Forthcoming' || $the_page === 'forthcoming') {
			  $this->rename('page', 'CITATION_BOT_PLACEHOLDER_page');
			  $the_page = '';
			  $bad_data = TRUE;
		  }
		  if ($the_volume === '_' || $the_volume === '0' || $the_volume === 'null' || $the_volume === 'n/a' || $the_volume === 'Online edition' || $the_volume === 'online' || $the_volume === 'Online' || $the_volume === 'in press'  || $the_volume === 'In press' || $the_volume === 'ahead-of-print' || $the_volume === 'Forthcoming' || $the_volume === 'forthcoming') {
			  $this->rename('volume', 'CITATION_BOT_PLACEHOLDER_volume');
			  $the_volume = '';
			  $bad_data = TRUE;
		  }
		  if ($the_issue === '_' || $the_issue === '0' || $the_issue === 'null' || $the_issue === 'ja' || $the_issue === 'n/a' || $the_issue === 'Online edition' || $the_issue === 'online' || $the_issue === 'Online' || $the_issue === 'in press' || $the_issue === 'In press' || $the_issue === 'ahead-of-print' || $the_issue === 'Forthcoming' || $the_issue === 'forthcoming') {
			  $this->rename('issue', 'CITATION_BOT_PLACEHOLDER_issue');
			  $the_issue = '';
			  $bad_data = TRUE;
		  }
		  if (strlen($the_title) > 15 && strpos($the_title, ' ') !== FALSE &&
			  mb_strtoupper($the_title) === $the_title && strpos($the_title, 'CITATION') === FALSE &&
			  mb_check_encoding($the_title, 'ASCII')) {
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
			  $bad_data = TRUE;
		  }
		  if (stripos($the_title, 'SpringerLink') !== FALSE) {
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
			  $bad_data = TRUE;
		  }
		  if ($the_title === '_' || $the_title === 'null' || $the_title === '[No title found]' || $the_title === 'Archived copy' || $the_title === 'JSTOR' ||
			  $the_title === 'ShieldSquare Captcha' || $the_title === 'Shibboleth Authentication Request' || $the_title === 'Pubmed' || $the_title === 'usurped title' ||
			  $the_title === 'Pubmed Central' || $the_title === 'Optica Publishing Group' || $the_title === 'BioOne' || $the_title === 'IEEE Xplore' ||
			  $the_title === 'ScienceDirect' || $the_title === 'Science Direct' || $the_title === 'Validate User') { // title=none is often because title is "reviewed work....
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
			  $bad_data = TRUE;
		  }
		  if (strlen($the_journal) > 15 && strpos($the_journal, ' ') !== FALSE &&
			  mb_strtoupper($the_journal) === $the_journal && strpos($the_journal, 'CITATION') === FALSE &&
			  mb_check_encoding($the_journal, 'ASCII')) {
			  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
			  $the_journal = '';
			  $bad_data = TRUE;
		  }
		  if (strlen($the_chapter) > 15 && strpos($the_chapter, ' ') !== FALSE &&
			  mb_strtoupper($the_chapter) === $the_chapter && strpos($the_chapter, 'CITATION') === FALSE &&
			  mb_check_encoding($the_chapter, 'ASCII')) {
			  $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
			  $the_chapter = '';
			  $bad_data = TRUE;
		  }
		  if (str_i_same($the_journal, 'Biochimica et Biophysica Acta') || str_i_same($the_journal, '[[Biochimica et Biophysica Acta]]')) { // Only part of the journal name
			  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
			  $the_journal = '';
			  $bad_data = TRUE;
		  }
		  if (str_i_same($the_journal, 'JSTOR') || $the_journal === '_' || str_i_same($the_journal, 'BioOne') || str_i_same($the_journal, 'IEEE Xplore') || str_i_same($the_journal, 'PubMed') || str_i_same($the_journal, 'PubMed Central') || str_i_same($the_journal, 'ScienceDirect') || str_i_same($the_journal, 'Science Direct')) {
			  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
			  $the_journal = '';
			  $bad_data = TRUE;
		  }
		  if ((stripos($the_journal, 'arXiv:') === 0 || $the_journal === 'arXiv') && !$this->blank(ARXIV_ALIASES)) {
			  $this->forget('journal');
			  $the_journal = '';
			  $bad_data = TRUE;
			  if ($this->wikiname() === 'cite journal') $this->change_name_to('cite arxiv');
		  }
		  if (stripos($the_journal, 'arXiv') !== FALSE) {
			  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
			  $the_journal = '';
			  $bad_data = TRUE;
		  }
		  if (stripos($the_journal, 'ScienceDirect') !== FALSE) {
			  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
			  $the_journal = '';
			  $bad_data = TRUE;
		  }
		  if ($the_chapter === '_') {
			  $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
			  $the_chapter = '';
			  $bad_data = TRUE;
		  }
		  if ($the_title !== '' && stripos(str_replace('CITATION_BOT_PLACEHOLDER_TEMPLATE', '', $the_title), 'CITATION') === FALSE) { // Templates are generally {{!}} and such
			if (str_i_same($the_title, $the_journal) &&
				str_i_same($the_title, $the_chapter)) { // Journal === Title === Chapter INSANE!  Never actually seen
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
			  $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
			  $the_title = '';
			  $the_journal = '';
			  $the_chapter = '';
			  $bad_data = TRUE;
			} elseif (str_i_same($the_title, $the_journal)) { // Journal === Title
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $this->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
			  $the_title = '';
			  $the_journal = '';
			  $bad_data = TRUE;
			} elseif (str_i_same($the_title, $the_chapter)) { // Chapter === Title
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $this->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
			  $the_title = '';
			  $the_chapter = '';
			  $bad_data = TRUE;
			} elseif (substr($the_title, -9, 9) === ' on JSTOR') {
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title'); // Ends in 'on jstor'
			  $the_title = '';
			  $bad_data = TRUE;
			} elseif (substr($the_title, -20, 20) === 'IEEE Xplore Document') {
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
			  $bad_data = TRUE;
			} elseif (substr($the_title, 0, 12) === 'IEEE Xplore ') {
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
			  $bad_data = TRUE;
			} elseif (substr($the_title, -12) === ' IEEE Xplore') {
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
			  $bad_data = TRUE;
			} elseif (preg_match('~.+(?: Volume| Vol\.| V. | Number| No\.| Num\.| Issue ).*\d+.*page.*\d+~i', $the_title)) {
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
			  $bad_data = TRUE;
			} elseif (preg_match('~^\[No title found\]$~i', $the_title)) {
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
			  $bad_data = TRUE;
			} elseif (stripos($the_title, 'arXiv') !== FALSE) {
			  $this->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
			  $the_title = '';
			  $bad_data = TRUE;
			}
		  }
		  if ($this->has('coauthors')) {
			  if ($this->has('first'))  $this->rename('first',  'CITATION_BOT_PLACEHOLDER_first');
			  if ($this->has('last'))   $this->rename('last',   'CITATION_BOT_PLACEHOLDER_last');
			  if ($this->has('first1')) $this->rename('first1', 'CITATION_BOT_PLACEHOLDER_first1');
			  if ($this->has('last1'))  $this->rename('last1',  'CITATION_BOT_PLACEHOLDER_last1');
			  if ($this->has('author1'))$this->rename('author1','CITATION_BOT_PLACEHOLDER_author1');
			  if ($this->has('author')) $this->rename('author', 'CITATION_BOT_PLACEHOLDER_author');
			  $this->rename('coauthors', 'CITATION_BOT_PLACEHOLDER_coauthors');
			  if ($this->blank(FLATTENED_AUTHOR_PARAMETERS)) {
				$this->initial_author_params = array();
				$bad_data = TRUE;
			  } else {
				if ($this->has('CITATION_BOT_PLACEHOLDER_first'))  $this->rename('CITATION_BOT_PLACEHOLDER_first',  'first');
				if ($this->has('CITATION_BOT_PLACEHOLDER_last'))   $this->rename('CITATION_BOT_PLACEHOLDER_last',   'last');
				if ($this->has('CITATION_BOT_PLACEHOLDER_first1')) $this->rename('CITATION_BOT_PLACEHOLDER_first1', 'first1');
				if ($this->has('CITATION_BOT_PLACEHOLDER_last1'))  $this->rename('CITATION_BOT_PLACEHOLDER_last1',  'last1');
				if ($this->has('CITATION_BOT_PLACEHOLDER_author1'))$this->rename('CITATION_BOT_PLACEHOLDER_author1','author1');
				if ($this->has('CITATION_BOT_PLACEHOLDER_author')) $this->rename('CITATION_BOT_PLACEHOLDER_author', 'author');
				$this->rename('CITATION_BOT_PLACEHOLDER_coauthors', 'coauthors');
			  }
		  }
		  if ($bad_data) {
			if ($this->has('year') && $this->blank(['isbn', 'lccn', 'oclc'])) { // Often the pre-print year
			  $this->rename('year', 'CITATION_BOT_PLACEHOLDER_year');
			}
			if ($this->has('doi') && doi_active($this->get('doi'))) {
			  expand_by_doi($this);
			}
			$this->this_array = array($this);
			if ($this->has('pmid')) {
			  query_pmid_api(array($this->get('pmid')), $this->this_array);
			}
			if ($this->has('pmc')) {
			  query_pmc_api(array($this->get('pmc')), $this->this_array);
			}
			if ($this->has('jstor')) {
			  expand_by_jstor($this);
			}
			if ($this->blank(['pmid', 'pmc', 'jstor']) && ($this->has('eprint') || $this->has('arxiv'))) {
			  expand_arxiv_templates($this->this_array);
			}
			$this->this_array = array();
			if ($ieee_insanity && $this->has('chapter') && $this->has('title')) {
			  $this->forget('CITATION_BOT_PLACEHOLDER_journal');
			}
			if ($this->has('CITATION_BOT_PLACEHOLDER_journal')) {
			  if ($this->has('journal') && $this->get('journal') !== $this->get('CITATION_BOT_PLACEHOLDER_journal') &&
				  '[[' . $this->get('journal') . ']]' !== $this->get('CITATION_BOT_PLACEHOLDER_journal')) {
				$this->move_and_forget('CITATION_BOT_PLACEHOLDER_journal');
			  } else {
				$this->rename('CITATION_BOT_PLACEHOLDER_journal', 'journal');
			  }
			}
			if ($this->has('CITATION_BOT_PLACEHOLDER_title')) {
			  if ($this->has('title')) {
				$newer = str_replace(array(".", ",", ":", ";", "?", "!", " ", "-", "'", '"'), '', mb_strtolower($this->get('title')));
				$older = str_replace(array(".", ",", ":", ";", "?", "!", " ", "-", "'", '"'), '', mb_strtolower($this->get('CITATION_BOT_PLACEHOLDER_title')));
				if (($newer !== $older) && (strpos($older, $newer) === 0)) {
				  $this->rename('CITATION_BOT_PLACEHOLDER_title', 'title'); // New title lost sub-title
				} elseif (str_replace(" ", '', $this->get('title')) === str_replace([" ", "'"], '', $this->get('CITATION_BOT_PLACEHOLDER_title'))) {
				  $this->rename('CITATION_BOT_PLACEHOLDER_title', 'title'); // New title lost italics
				} elseif ($this->get('title') === $this->get('CITATION_BOT_PLACEHOLDER_title')) {
				  $this->rename('CITATION_BOT_PLACEHOLDER_title', 'title');
				} else {
				  $this->move_and_forget('CITATION_BOT_PLACEHOLDER_title');
				}
			  } else {
				$this->rename('CITATION_BOT_PLACEHOLDER_title', 'title');
			  }
			}
			if ($this->has('CITATION_BOT_PLACEHOLDER_chapter')) {
			  if ($this->has('chapter')) {
				$newer = str_replace(array(".", ",", ":", ";", "?", "!", " ", "-", "'", '"'), '', mb_strtolower($this->get('chapter')));
				$older = str_replace(array(".", ",", ":", ";", "?", "!", " ", "-", "'", '"'), '', mb_strtolower($this->get('CITATION_BOT_PLACEHOLDER_chapter')));
				if (($newer !== $older) && (strpos($older, $newer) === 0)) {
				  $this->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter'); // New chapter lost sub-chapter
				} elseif (str_replace(" ", '', $this->get('chapter')) === str_replace([" ", "'"], '', $this->get('CITATION_BOT_PLACEHOLDER_chapter'))) {
				  $this->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter'); // New chapter lost italics
				} elseif ($this->get('chapter') === $this->get('CITATION_BOT_PLACEHOLDER_chapter')) {
				  $this->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter');
				} else {
				  $this->move_and_forget('CITATION_BOT_PLACEHOLDER_chapter');
				}
			  } else {
				$this->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter');
			  }
			}
			if ($this->has('CITATION_BOT_PLACEHOLDER_issue')) {
			  if ($this->has('issue') && $this->get('issue') !== $this->get('CITATION_BOT_PLACEHOLDER_issue')) {
				$this->move_and_forget('CITATION_BOT_PLACEHOLDER_issue');
			  } else {
				$this->rename('CITATION_BOT_PLACEHOLDER_issue', 'issue');
			  }
			}
			if ($this->has('CITATION_BOT_PLACEHOLDER_volume')) {
			  if ($this->has('volume') && $this->get('volume') !== $this->get('CITATION_BOT_PLACEHOLDER_volume')) {
				$this->move_and_forget('CITATION_BOT_PLACEHOLDER_volume');
			  } else {
				$this->rename('CITATION_BOT_PLACEHOLDER_volume', 'volume');
			  }
			}
			if ($this->has('CITATION_BOT_PLACEHOLDER_page')) {
			  if (($this->has('page') || $this->has('pages')) && ($this->get('page') . $this->get('pages') !== $this->get('CITATION_BOT_PLACEHOLDER_page'))) {
				$this->move_and_forget('CITATION_BOT_PLACEHOLDER_page');
			  } else {
				$this->rename('CITATION_BOT_PLACEHOLDER_page', 'page');
			  }
			}
			if ($this->has('CITATION_BOT_PLACEHOLDER_pages')) {
			  if (($this->has('page') || $this->has('pages')) && ($this->get('page') . $this->get('pages') !== $this->get('CITATION_BOT_PLACEHOLDER_pages'))) {
				$this->move_and_forget('CITATION_BOT_PLACEHOLDER_pages');
			  } else {
				$this->rename('CITATION_BOT_PLACEHOLDER_pages', 'pages');
			  }
			}
			if ($this->has('CITATION_BOT_PLACEHOLDER_year')) {
			  if ($this->has('year') && ($this->get('year') !== $this->get('CITATION_BOT_PLACEHOLDER_year'))) {
				$this->move_and_forget('CITATION_BOT_PLACEHOLDER_year');
			  } elseif ($this->has('date') && ($this->get('date') !== $this->get('CITATION_BOT_PLACEHOLDER_year'))) {
				$this->move_and_forget('CITATION_BOT_PLACEHOLDER_year');
			  } elseif ($this->has('date') && ($this->get('date') === $this->get('CITATION_BOT_PLACEHOLDER_year'))) {
				$this->forget('date');
				$this->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
			  } else {
				$this->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
			  }
			}
			if ($this->has('CITATION_BOT_PLACEHOLDER_coauthors')) {
			  if ($this->has('last1') || $this->has('author1')) {
				$this->forget('CITATION_BOT_PLACEHOLDER_first');
				$this->forget('CITATION_BOT_PLACEHOLDER_last');
				$this->forget('CITATION_BOT_PLACEHOLDER_first1');
				$this->forget('CITATION_BOT_PLACEHOLDER_last1');
				$this->forget('CITATION_BOT_PLACEHOLDER_author1');
				$this->forget('CITATION_BOT_PLACEHOLDER_author');
				$this->forget('CITATION_BOT_PLACEHOLDER_coauthors');
			  } else {
				$this->initial_author_params = $initial_author_params_save;
				if ($this->has('CITATION_BOT_PLACEHOLDER_first'))   $this->rename('CITATION_BOT_PLACEHOLDER_first',  'first');
				if ($this->has('CITATION_BOT_PLACEHOLDER_last'))    $this->rename('CITATION_BOT_PLACEHOLDER_last',   'last');
				if ($this->has('CITATION_BOT_PLACEHOLDER_first1'))  $this->rename('CITATION_BOT_PLACEHOLDER_first1', 'first1');
				if ($this->has('CITATION_BOT_PLACEHOLDER_last1'))   $this->rename('CITATION_BOT_PLACEHOLDER_last1',  'last1');
				if ($this->has('CITATION_BOT_PLACEHOLDER_author1')) $this->rename('CITATION_BOT_PLACEHOLDER_author1','author1');
				if ($this->has('CITATION_BOT_PLACEHOLDER_author'))  $this->rename('CITATION_BOT_PLACEHOLDER_author','author');
				$this->rename('CITATION_BOT_PLACEHOLDER_coauthors', 'coauthors');
			  }
			}
		  }
		  if ($no_start_chapter && $this->blank('chapter')) $this->forget('chapter');
		  if ($no_start_journal && $this->blank('journal')) $this->forget('journal');
		  unset($initial_author_params_save, $the_title, $the_journal, $the_chapter, $the_volume, $the_issue, $the_page, $the_pages, $bad_data);
		  unset($no_start_chapter, $no_start_journal);
		}
		$this->tidy();
		// Fix up URLs hiding in identifiers
		foreach (['issn', 'oclc', 'pmc', 'doi', 'pmid', 'jstor', 'arxiv', 'zbl', 'mr',
				  'lccn', 'hdl', 'ssrn', 'ol', 'jfm', 'osti', 'biorxiv', 'citeseerx', 'hdl'] as $possible) {
		  if ($this->has($possible)) {
			 $url = $this->get($possible);
			 if (stripos($url, 'CITATION_BOT') === FALSE &&
				 !preg_match('~^https?://[^/]+/?$~', $url) && // Ignore just a hostname
			   preg_match (REGEXP_IS_URL, $url) === 1) {
			   $this->rename($possible, 'CITATION_BOT_PLACEHOLDER_possible');
			   $this->get_identifiers_from_url($url);
			   if ($this->has($possible)) {
				 $this->forget('CITATION_BOT_PLACEHOLDER_possible');
			   } else {
				 $this->rename('CITATION_BOT_PLACEHOLDER_possible', $possible);
			   }
			 }
		  }
		}
		if ($this->wikiname() === 'cite document') {
		  foreach (ALL_URL_TYPES as $thing) {
			if ($this->blank($thing)) $this->forget($thing);
			if ($this->blank($thing . '-access')) $this->forget($thing . '-access');
		  }
		  foreach (WORK_ALIASES as $thing) {
			if ($this->blank($thing)) $this->forget($thing);
		  }
		  foreach (['archive-url', 'archiveurl', 'archivedate', 'archive-date', 'url-status'] as $thing) {
			if ($this->blank($thing)) $this->forget($thing);
		  }
		}
	} elseif ($this->wikiname() === 'cite magazine' && $this->blank('magazine') && $this->has_but_maybe_blank('work')) {
	  // This is all we do with cite magazine
	  $this->rename('work', 'magazine');
	}
  }

  public function fix_rogue_etal() : void {
	if ($this->blank(DISPLAY_AUTHORS)) {
	  $i = 2;
	  while (!$this->blank(['author' . (string) $i, 'last' . (string) $i])) {
		$i = $i + 1;
	  }
	  $i = $i - 1;
	  if (preg_match('~^et\.? ?al\.?$~i', $this->get('author' . (string) $i))) $this->rename('author' . (string) $i, 'display-authors', 'etal');
	  if (preg_match('~^et\.? ?al\.?$~i', $this->get('last'   . (string) $i))) $this->rename('last'   . (string) $i, 'display-authors', 'etal');
	}
  }

  public function record_api_usage(string $api, string $param) : void {
	$param = array($param);
	foreach ($param as $p) {
	  if (!in_array($p, $this->used_by_api[$api])) $this->used_by_api[$api][] = $p;
	}
  }

  /** @param array<string> $param **/
  public function api_has_used(string $api, array $param) : bool {
	if (!isset($this->used_by_api[$api])) report_error("Invalid API: $api");
	/** @psalm-suppress all */
	return (bool) count(array_intersect($param, $this->used_by_api[$api]));
  }

  public function incomplete() : bool {   // FYI: some references will never be considered complete
	$possible_extra_authors = $this->get('author') . $this->get('authors') . $this->get('vauthors');
	if (strpos($possible_extra_authors, ' and ') !== FALSE ||
		strpos($possible_extra_authors, '; ')    !== FALSE ||
		strpos($possible_extra_authors, 'et al ')!== FALSE ||
		strpos($possible_extra_authors, 'et al.')!== FALSE ||
		strpos($possible_extra_authors, ' et al')!== FALSE ||
		substr_count($possible_extra_authors, ',') > 3     ||
		$this->has('author2') || $this->has('last2') || $this->has('surname2')
	) {
		$two_authors = TRUE;
	} else {
		$two_authors = FALSE;
	}
	if ($this->wikiname() === 'cite book' || ($this->wikiname() === 'citation' && $this->has('isbn'))) { // Assume book
	  if ($this->display_authors() >= $this->number_of_authors()) return TRUE;
	  return (!(
			  $this->has('isbn')
		  &&  $this->has('title')
		  && ($this->has('date') || $this->has('year'))
		  && $two_authors
	  ));
	}
	if ($this->wikiname() === 'cite conference') { // cite conference uses very different parameters
	  if ($this->has('title') && ($this->has('conference') || $this->has('book-title') || $this->has('chapter'))) {
		return FALSE;
	  }
	}
	// And now everything else
	if ($this->blank(['pages', 'page', 'at']) ||
		preg_match('~no.+no|n/a|in press|none~', $this->get('pages') . $this->get('page') . $this->get('at')) ||
		(preg_match('~^1[^0-9]~', $this->get('pages') . $this->get('page') . '-') && ($this->blank('year') || 2 > ((int)date("Y") - (int)$this->get('year')))) // It claims to be on page one
	   ) {
	  return TRUE;
	}
	if ($this->display_authors() >= $this->number_of_authors()) return TRUE;

	if ($this->wikiname() === 'citation' && $this->has('work')) {
	  return TRUE; // Should consider changing the work parameter, since {{citation}} uses the work parameter type to determine format :-(
	}

	return (!(
			 ($this->has('journal') || $this->has('periodical') || $this->has('work') || $this->has('newspaper') || $this->has('magazine') || $this->has('trans-work') || $this->has('script-work'))
		  &&  $this->has('volume')
		  && ($this->has('issue') || $this->has('number'))
		  &&  $this->has('title')
		  && ($this->has('date') || $this->has('year'))
		  && $two_authors
		  && $this->get('journal') !== 'none'
		  && $this->get('title') !== 'none'
	));
  }

  public function profoundly_incomplete(string $url = '') : bool {
	// Zotero translation server often returns bad data, which is worth having if we have no data,
	// but we don't want to fill a single missing field with garbage if a reference is otherwise well formed.
	$has_date = $this->has('date') || $this->has('year') ;
	foreach (NO_DATE_WEBSITES as $bad_website) {
	  if (stripos($url, $bad_website) !== FALSE) {
		$has_date = TRUE;
		break;
	  }
	}

	if ($this->wikiname() =='cite book' || ($this->wikiname() =='citation' && $this->has('isbn'))) { // Assume book
	  if ($this->display_authors() >= $this->number_of_authors()) return TRUE;
	  return (!(
		$this->has('isbn')
		&&  $this->has('title')
		&&  $has_date
	  ));
	}

	if (str_ireplace(NON_JOURNAL_WEBSITES, '', $url) !== $url) { // A website that will never give a volume
		  return (!(
			 ($this->has('journal') || $this->has('periodical') || $this->has('work') || $this->has('trans-work') || $this->has('script-work') ||
			  $this->has('website') || $this->has('publisher') || $this->has('newspaper') ||
			  $this->has('magazine')|| $this->has('encyclopedia') || $this->has('encyclopaedia') ||
			  $this->has('contribution'))
		  &&  $this->has('title')
		  &&  $has_date
	));
	}
	return (!(
			 ($this->has('journal') || $this->has('periodical') || $this->has('trans-work') || $this->has('script-work'))
		  &&  $this->has('volume')
		  &&  $this->has('title')
		  &&  $has_date
	));
  }

  /**
  * @param string[]|string $param
  */
  public function blank($param) : bool { // Accepts arrays of strings and string
	if (!$param) report_error('NULL passed to blank()');
	if (empty($this->param)) return TRUE;
	if (!is_array($param)) $param = array($param);
	foreach ($this->param as $p) {
	  if (in_array($p->param, $param) && trim($p->val) !== '' && !str_i_same('Epub ahead of print', $p->val)) return FALSE;
	}
	return TRUE;
  }
  /**
  * @param string[]|string $param
  */
  public function blank_other_than_comments($param) : bool { // Accepts arrays of strings and string
	if (!$param) report_error('NULL passed to blank_other_than_comments()');
	if (empty($this->param)) return TRUE;
	if (!is_array($param)) $param = array($param);
	foreach ($this->param as $p) {
	  if (in_array($p->param, $param)) {
		$value = $p->val;
		$value = trim($value);
		if (stripos($value, '# # # CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) { // Regex failure paranoia
		  $value = trim(preg_replace('~^# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #~i', '', $value));
		  $value = trim(preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #$~i', '', $value));
		  $value = trim(preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #~i', '', $value));
		}
		$value = trim($value);
		if ($value !== '') return FALSE;
	  }
	}
	return TRUE;
  }

  /*
   * Adds a parameter to a template if the parameter and its equivalents are blank
   * $api (string) specifies the API route by which a parameter was found; this will log the
   * parameter so it is not used to trigger a new search via the same API.
   *
   */
  public function add_if_new(string $param_name, string $value, string $api = '') : bool {
	// Clean up weird stuff from CrossRef etc.
	$value = safe_preg_replace('~[\x{2000}-\x{200B}\x{00A0}\x{202F}\x{205F}\x{3000}]~u', ' ', $value); // Non-standard spaces
	$value = safe_preg_replace("~^\xE2\x80\x8B~", " ", $value); // Zero-width at start
	$value = safe_preg_replace("~\xE2\x80\x8B$~", " ", $value); // Zero-width at end
	$value = safe_preg_replace('~[\t\n\r\0\x0B]~u', ' ', $value); // tabs, linefeeds, null bytes
	$value = safe_preg_replace('~  +~u', ' ', $value); // multiple spaces
	$value = trim($value);
	$param_name = trim($param_name); // Pure paranoia
	if ($value === '') {
	  return FALSE;
	}
	if ($param_name === '') {
	  report_error('invalid param_name passed to add_if_new()'); // @codeCoverageIgnore
	}

	$low_value = strtolower($value);
	if (in_array($low_value, array('null', 'n/a', 'undefined', '0 0', '(:none)'))) { // Hopeully name is not actually null
		return FALSE;
	}

	if (mb_stripos($this->get($param_name), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
	  return FALSE;  // We let comments block the bot
	}

	if (array_key_exists($param_name, COMMON_MISTAKES)) { // This is not an error, since sometimes the floating text code finds odd stuff
	  report_minor_error("Attempted to add invalid parameter: " . echoable($param_name)); // @codeCoverageIgnore
	}

	// We have to map these, since sometimes we get floating accessdate and such
	if (array_key_exists($param_name, COMMON_MISTAKES_TOOL)) {
		$param_name = COMMON_MISTAKES_TOOL[$param_name];
	}
	/** @psalm-assert string $param_name */

	if ($api) $this->record_api_usage($api, $param_name);

	// If we already have name parameters for author, don't add more
	if ($this->initial_author_params && in_array($param_name, FLATTENED_AUTHOR_PARAMETERS)) {
	  return FALSE;
	}

	if ($param_name !== 's2cid') {
	 if (strpos($param_name, 'last') === 0 || strpos($param_name, 'first') === 0 || strpos($param_name, 'author') === 0) {
	  if ((int) substr($param_name, -4) > 0 || (int) substr($param_name, -3) > 0 || (int) substr($param_name, -2) > 30) {
	   // Stop at 30 authors - or page codes will become cluttered!
	   if ((bool) $this->get('last29') || (bool) $this->get('author29') || (bool) $this->get('surname29')) $this->add_if_new('display-authors', '1');
	   return FALSE;
	  }
	 }
	 if (strpos($param_name, 'editor') === 0) {
	  if ((int) substr($param_name, -4) > 0 || (int) substr($param_name, -3) > 0 || (int) substr($param_name, -2) > 30) {
	   // Stop at 30 editors - or page codes will become cluttered!
	   if ((bool) $this->get('editor29') || (bool) $this->get('editor-last29') || (bool) $this->get('editor29-last')) $this->add_if_new('display-editors', '1');
	   return FALSE;
	  }
	 }
	}

	$auNo = preg_match('~\d+$~', $param_name, $auNo) ? $auNo[0] : '';

	switch ($param_name) {
	  ### EDITORS
	  case (bool) preg_match('~^editor(\d{1,})$~', $param_name, $match) :
		if ($this->had_initial_editor) return FALSE;
		if (stripos($this->get('doi'), '10.1093/gmo') !== FALSE) return FALSE;
		if (!$this->blank(['editors', 'editor', 'editor-last', 'editor-first'])) return FALSE; // Existing incompatible data
		if ($this->blank(['editor' . $match[1], 'editor' . $match[1] . '-last', 'editor' . $match[1] . '-first',
						  'editor-last' . $match[1], 'editor-first' . $match[1]])) {
		  return $this->add($param_name, clean_up_full_names($value));
		}
		return FALSE;

	  case (bool) preg_match('~^editor-first(\d{1,})$~', $param_name, $match) :
		if ($this->had_initial_editor) return FALSE;
		if (stripos($this->get('doi'), '10.1093/gmo') !== FALSE) return FALSE;
		if (!$this->blank(['editors', 'editor', 'editor-last', 'editor-first'])) return FALSE; // Existing incompatible data
		if ($this->blank(['editor' . $match[1], 'editor' . $match[1] . '-first', 'editor-first' . $match[1]])) {
		  return $this->add($param_name, clean_up_first_names($value));
		}
		return FALSE;

	  case (bool) preg_match('~^editor-last(\d{1,})$~', $param_name, $match) :
		if ($this->had_initial_editor) return FALSE;
		if (stripos($this->get('doi'), '10.1093/gmo') !== FALSE) return FALSE;
		if (!$this->blank(['editors', 'editor', 'editor-last', 'editor-first'])) return FALSE; // Existing incompatible data
		if ($this->blank(['editor' . $match[1], 'editor' . $match[1] . '-last', 'editor-last' . $match[1]])) {
		  return $this->add($param_name, clean_up_last_names($value));
		}
		return FALSE;

	  #TRANSLATOR
	  case (bool) preg_match('~^translator(\d{1,})$~', $param_name, $match) :
		if (!$this->blank(['translators', 'translator', 'translator-last', 'translator-first'])) return FALSE; // Existing incompatible data
		if ($this->blank(['translator' . $match[1], 'translator' . $match[1] . '-last', 'translator' . $match[1] . '-first'])) {
		  return $this->add($param_name, clean_up_full_names($value));
		}
		return FALSE;

	  ### AUTHORS
	  case "author": case "author1": case "last1": case "last": case "authors":
		if ($this->blank(FIRST_AUTHOR_ALIASES)) {
		  $value = clean_up_full_names($value);
		  $au = split_author($value);
		  if (!empty($au) && (substr($param_name, 0, 3) === 'aut')) {
			$this->add('last' . (substr($param_name, -1) === '1' ? '1' : ''), clean_up_last_names(format_surname($au[0])));
			return $this->add_if_new('first' . (substr($param_name, -1) === '1' ? '1' : ''), clean_up_first_names(format_forename(trim($au[1]))));
		  } elseif (strpos($param_name, 'last') === FALSE) {
			return $this->add($param_name, $value);
		  } else {
			return $this->add($param_name, clean_up_last_names($value));
		  }
		}
		return FALSE;

	  case "first": case "first1":
	   if ($this->blank(FIRST_FORENAME_ALIASES)) {
		  return $this->add($param_name, clean_up_first_names($value));
	  }
	  return FALSE;
	  case "last2": case "last3": case "last4": case "last5": case "last6": case "last7": case "last8": case "last9":
	  case "last10": case "last20": case "last30": case "last40": case "last50": case "last60": case "last70": case "last80": case "last90":
	  case "last11": case "last21": case "last31": case "last41": case "last51": case "last61": case "last71": case "last81": case "last91":
	  case "last12": case "last22": case "last32": case "last42": case "last52": case "last62": case "last72": case "last82": case "last92":
	  case "last13": case "last23": case "last33": case "last43": case "last53": case "last63": case "last73": case "last83": case "last93":
	  case "last14": case "last24": case "last34": case "last44": case "last54": case "last64": case "last74": case "last84": case "last94":
	  case "last15": case "last25": case "last35": case "last45": case "last55": case "last65": case "last75": case "last85": case "last95":
	  case "last16": case "last26": case "last36": case "last46": case "last56": case "last66": case "last76": case "last86": case "last96":
	  case "last17": case "last27": case "last37": case "last47": case "last57": case "last67": case "last77": case "last87": case "last97":
	  case "last18": case "last28": case "last38": case "last48": case "last58": case "last68": case "last78": case "last88": case "last98":
	  case "last19": case "last29": case "last39": case "last49": case "last59": case "last69": case "last79": case "last89": case "last99":
	  case "author2": case "author3": case "author4": case "author5": case "author6": case "author7": case "author8": case "author9":
	  case "author10": case "author20": case "author30": case "author40": case "author50": case "author60": case "author70": case "author80": case "author90":
	  case "author11": case "author21": case "author31": case "author41": case "author51": case "author61": case "author71": case "author81": case "author91":
	  case "author12": case "author22": case "author32": case "author42": case "author52": case "author62": case "author72": case "author82": case "author92":
	  case "author13": case "author23": case "author33": case "author43": case "author53": case "author63": case "author73": case "author83": case "author93":
	  case "author14": case "author24": case "author34": case "author44": case "author54": case "author64": case "author74": case "author84": case "author94":
	  case "author15": case "author25": case "author35": case "author45": case "author55": case "author65": case "author75": case "author85": case "author95":
	  case "author16": case "author26": case "author36": case "author46": case "author56": case "author66": case "author76": case "author86": case "author96":
	  case "author17": case "author27": case "author37": case "author47": case "author57": case "author67": case "author77": case "author87": case "author97":
	  case "author18": case "author28": case "author38": case "author48": case "author58": case "author68": case "author78": case "author88": case "author98":
	  case "author19": case "author29": case "author39": case "author49": case "author59": case "author69": case "author79": case "author89": case "author99":

		if ($this->blank(array_merge(COAUTHOR_ALIASES, ["last$auNo", "author$auNo"]))
		  && strpos($this->get('author') . $this->get('authors'), ' and ') === FALSE
		  && strpos($this->get('author') . $this->get('authors'), '; ') === FALSE
		  && strpos($this->get('author') . $this->get('authors'), ' et al') === FALSE
		) {
		  $value = clean_up_full_names($value);
		  $au = split_author($value);
		  if (!empty($au) && (substr($param_name, 0, 3) === 'aut')) {
			$this->add('last' . $auNo, clean_up_last_names(format_surname($au[0])));
			return $this->add_if_new('first' . $auNo, clean_up_first_names(format_forename(trim($au[1]))));
		  } else {
			return $this->add($param_name, $value);
		  }
		}
		return FALSE;
	  case "first2": case "first3": case "first4": case "first5": case "first6": case "first7": case "first8": case "first9":
	  case "first10": case "first11": case "first12": case "first13": case "first14": case "first15": case "first16": case "first17": case "first18": case "first19":
	  case "first20": case "first21": case "first22": case "first23": case "first24": case "first25": case "first26": case "first27": case "first28": case "first29":
	  case "first30": case "first31": case "first32": case "first33": case "first34": case "first35": case "first36": case "first37": case "first38": case "first39":
	  case "first40": case "first41": case "first42": case "first43": case "first44": case "first45": case "first46": case "first47": case "first48": case "first49":
	  case "first50": case "first51": case "first52": case "first53": case "first54": case "first55": case "first56": case "first57": case "first58": case "first59":
	  case "first60": case "first61": case "first62": case "first63": case "first64": case "first65": case "first66": case "first67": case "first68": case "first69":
	  case "first70": case "first71": case "first72": case "first73": case "first74": case "first75": case "first76": case "first77": case "first78": case "first79":
	  case "first80": case "first81": case "first82": case "first83": case "first84": case "first85": case "first86": case "first87": case "first88": case "first89":
	  case "first90": case "first91": case "first92": case "first93": case "first94": case "first95": case "first96": case "first97": case "first98": case "first99":

		if ($this->blank(array_merge(COAUTHOR_ALIASES, [$param_name, "author" . $auNo]))
				&& under_two_authors($this->get('author'))) {
		  return $this->add($param_name, clean_up_first_names($value));
		}
		return FALSE;

	  case 'displayauthors':
	  case 'display-authors':
		if ($this->blank(DISPLAY_AUTHORS)) {
		  return $this->add('display-authors', $value);
		}
		return FALSE;

	  case 'displayeditors':
	  case 'display-editors':
		if ($this->blank(DISPLAY_EDITORS)) {
		  return $this->add('display-editors', $value);
		}
		return FALSE;

	  case 'accessdate':
	  case 'access-date':
		if (!$this->blank(['access-date', 'accessdate'])) return FALSE;
		$time = strtotime($value);
		if ($time) { // should come in cleaned up
			if (self::$date_style === DATES_MDY) {
			   $value = date('F j, Y', $time);
			} elseif (self::$date_style === DATES_DMY) {
			   $value = date('j F Y', $time);
			}
			return $this->add('access-date', $value);
		}
		return FALSE;

	  case 'archivedate':
	  case 'archive-date':
		if (!$this->blank(['archive-date', 'archivedate'])) return FALSE;
		$time = strtotime($value);
		if ($time) { // should come in cleaned up
			if (self::$date_style === DATES_MDY) {
			   $value = date('F j, Y', $time);
			} elseif (self::$date_style === DATES_DMY) {
			   $value = date('j F Y', $time);
			}
			return $this->add('archive-date', $value);
		}
		return FALSE;

	  case 'pmc-embargo-date': // Must come in formatted right!
		if (!$this->blank('pmc-embargo-date')) return FALSE;
		if (!preg_match('~2\d\d\d~', $value)) return FALSE; // 2000 or later
		$new_date=strtotime($value);
		$now_date=strtotime('now');
		if ($now_date > $new_date) return FALSE;
		return $this->add('pmc-embargo-date', $value);

	  ### DATE AND YEAR ###

	  case "date":
		if (self::$date_style !== DATES_WHATEVER || preg_match('~^\d{4}\-\d{2}\-\d{2}$~', $value)) {
		  $time = strtotime($value);
		  if ($time) {
			if ($time === strtotime(date("Y-m-d"))) return FALSE; // Reject bad data
			$day = date('d', $time);
			if ($day !== '01') { // Probably just got month and year if day=1
			  if (self::$date_style === DATES_MDY) {
				 $value = date('F j, Y', $time);
			  } else { // DATES_DMY and make DATES_WHATEVER pretty
				 $value = date('j F Y', $time);
			  }
			}
		  }
		}
	  // Don't break here; we want to go straight in to year;
	  case "year":
		if ($this->has('publication-date')) return FALSE; // No idea what to do with this
		if ($value === $this->year()) return FALSE;
		if (   ($this->blank('date')
			   || in_array(trim(strtolower($this->get_without_comments_and_placeholders('date'))), IN_PRESS_ALIASES))
			&& ($this->blank('year')
			   || in_array(trim(strtolower($this->get_without_comments_and_placeholders('year'))), IN_PRESS_ALIASES))
		  ) {  // Delete any "in press" dates.
		  $this->forget('year'); // "year" is discouraged
		  if ($this->add('date', $value)) {
			$this->tidy_parameter('isbn'); // We just added a date, we now know if 2007 or later
			return TRUE;
		  }
		}
		// Update Year with CrossRef data in a few limited cases
		if ($param_name === 'year' && $api === 'crossref' && $this->no_initial_doi && ((int) $this->year() < $value) && ((int)date('Y') - 3 < $value)) {
		  if ($this->blank('year')) {
			 $this->forget('year');
			 $this->set('date', $value);
		  } else {
			 $this->forget('date');
			 $this->set('year', $value);
		  }
		  $this->tidy_parameter('isbn');
		  return TRUE;
		}
		return FALSE;

	  ### JOURNAL IDENTIFIERS ###

	  case 'issn':
		if ($this->blank(["journal", "periodical", "work", $param_name]) &&
			preg_match('~^\d{4}-\d{3}[\dxX]$~', $value)) {
		  // Only add ISSN if journal is unspecified
		  return $this->add($param_name, $value);
		}
		return FALSE;

	  case 'issn_force': // When dropping URL, force adding it
		if ($this->blank('issn') && preg_match('~^\d{4}-\d{3}[\dxX]$~', $value)) {
		  return $this->add('issn', $value);
		}
		return FALSE;

	  case 'ismn':
		$value = str_ireplace('m', '9790', $value); // update them
		if ($this->blank('ismn')) {
		  return $this->add('ismn', $value);
		}
		return FALSE;

	  case 'periodical': case 'journal': case 'newspaper': case 'magazine':
		if ($value === 'HEP Lib.Web') $value = 'High Energy Physics Libraries Webzine'; // These should be array
		if ($value === 'Peoplemag') $value = 'People';
		if (preg_match('~Conference Proceedings.*IEEE.*IEEE~', $value)) return FALSE;
		if ($value === 'Wiley Online Library') return FALSE;
		if (stripos($value, 'Capstone Projects') !== FALSE) return FALSE;
		if (stripos($value, 'Dissertations') !== FALSE) return FALSE;
		if (stripos($value, 'Theses and Projects') !== FALSE) return FALSE;
		if (stripos($value, 'Electronic Thesis') !== FALSE) return FALSE;
		if (stripos($value, ' and Capstones') !== FALSE) return FALSE;
		if (stripos($value, ' and Problem Reports') !== FALSE) return FALSE;
		if (stripos($value, 'Doctoral ') !== FALSE) return FALSE;
		if (stripos($value, 'IETF Datatracker') !== FALSE) return FALSE;
		if (stripos($value, 'Springerlink') !== FALSE) return FALSE;
		if (stripos($value, 'Report No. ') !== FALSE) return FALSE;
		if (stripos($value, 'Report Number ') !== FALSE) return FALSE;
		if (!$this->blank(['booktitle', 'book-title'])) return FALSE;
		if (in_array(strtolower(sanitize_string($value)), BAD_TITLES )) return FALSE;
		if (in_array(strtolower($value), ARE_MANY_THINGS)) {
		  if ($this->wikiname() === 'cite news' && $param_name === 'newspaper') {
			; // Only time we trust zotero on these (people already said news)
		  } else {
			$param_name = 'website';
		  }
		}
		if (!$this->blank(['trans-work','script-work'])) return FALSE;
		if (in_array(strtolower(sanitize_string($this->get('journal'))), BAD_TITLES)) $this->forget('journal'); // Update to real data
		if (preg_match('~^(?:www\.|)rte.ie$~i', $value)) $value = 'RTÉ News'; // Russian special case code
		if ($this->wikiname() === 'cite book' && $this->has('chapter') && $this->has('title') && $this->has('series')) return FALSE;
		if ($this->has('title') && str_equivalent($this->get('title'), $value)) return FALSE; // Messed up already or in database
		if (!$this->blank(array_merge(['agency','publisher'],WORK_ALIASES)) && in_array(strtolower($value), DUBIOUS_JOURNALS)) return FALSE; // non-journals that are probably same as agency or publisher that come from zotero
		if ($this->get($param_name) === 'none' || $this->blank(["journal", "periodical", "encyclopedia", "encyclopaedia", "newspaper", "magazine", "contribution"])) {
		  if (in_array(strtolower(sanitize_string($value)), HAS_NO_VOLUME)) $this->forget('volume') ; // No volumes, just issues.
		  if (in_array(strtolower(sanitize_string($value)), HAS_NO_ISSUE))  { $this->forget('issue'); $this->forget('number'); } ; // No issues, just volumes
		  $value = wikify_external_text(title_case($value));
		  if ($this->has('series') && str_equivalent($this->get('series'), $value)) return FALSE ;
		  if ($this->has('work')) {
			if (str_equivalent($this->get('work'), $value) && !in_array(strtolower($value), ARE_MANY_THINGS)) {
			  if ($param_name === 'journal') $this->rename('work', $param_name); // Distinction between newspaper and magazine and websites are not clear to zotero
			  if (!$this->blank(['pmc', 'doi', 'pmid'])) $this->forget('issn');
			  return TRUE;
			} else {
			  return FALSE;  // Cannot have both work and journal
			}
		  }
		  if ($this->has('via')) {
			if (str_equivalent($this->get('via'), $value)) {
			  $this->rename('via', $param_name);
			  if (!$this->blank(['pmc', 'doi', 'pmid'])) $this->forget('issn');
			  return TRUE;
			}
		  }
		  $this->forget('class');
		  if ($this->wikiname() === 'cite arxiv') $this->change_name_to('cite journal');
		  if ($param_name === 'newspaper' && in_array(strtolower($value), WEB_NEWSPAPERS)) {
			 if ($this->has('publisher') && str_equivalent($this->get('publisher'), $value)) return FALSE;
			 if($this->blank('work')) {
			   $this->set('work', $value);
			   $this->quietly_forget('website');
			   if (stripos($this->get('publisher'), 'bbc') !== FALSE && stripos($value, 'bbc') !== FALSE) {
				  $this->quietly_forget('publisher');
			   }
			   return TRUE;
			 }
			report_error('Unreachable code reached in newspaper add'); // @codeCoverageIgnore
		  }
		  if ($param_name === 'newspaper' && $this->has('via')) {
			 if (stripos($value, 'times') !== FALSE && stripos($this->get('via'), 'times') !== FALSE) {
			   $this->forget('via'); // eliminate via= that matches newspaper mostly
			 }
			 if (stripos($value, ' post') !== FALSE && stripos($this->get('via'), 'post') !== FALSE) {
			   $this->forget('via'); // eliminate via= that matches newspaper mostly
			 }
			 if (stripos($value, ' bloomberg') !== FALSE && stripos($this->get('via'), 'bloomberg') !== FALSE) {
			   $this->forget('via'); // eliminate via= that matches newspaper mostly
			 }
		  }
		  if (($param_name === 'newspaper' || $param_name === 'journal') && $this->has('publisher') && str_equivalent($this->get('publisher'), $value)
				  && $this->blank('website')) { // Website is an alias for newspaper/work/journal, and did not check above
			 $this->rename('publisher', $param_name);
			 return TRUE;
		  }
		  if ($this->has('website')) {
			 if (str_equivalent($this->get('website'), $value)) {
			   if ($param_name === 'journal') $this->rename('website', $param_name);  // alias for journal. Distinction between newspaper and magazine and websites are not clear to zotero
			 } elseif (preg_match('~^\[.+\]$~', $this->get('website'))) {
			   if ($param_name === 'journal') $this->rename('website', $param_name); // existing data is linked
			 } elseif (!in_array(strtolower($value), ARE_MANY_THINGS)) {
			   $this->rename('website', $param_name, $value);
			 }
			 return TRUE;
		  } else {
			 $my_return = $this->add($param_name, $value);
			 // Avoid running twice
			 $this->tidy_parameter('publisher');
			 return $my_return;
		  }
		}
		return FALSE;

	  case 'series':
		if ($this->blank($param_name)) {
		  $value = wikify_external_text($value);
		  if ($this->has('journal') && str_equivalent($this->get('journal'), $value)) return FALSE;
		  if ($this->has('title') && str_equivalent($this->get('title'), $value)) return FALSE;
		  if ($value === 'A Penguin book') return FALSE;
		  if ($value === 'Also known as:Official records of the Union and Confederate armies') return FALSE;
		  return $this->add($param_name, $value);
		}
		return FALSE;

	  case 'chapter': case 'contribution': case 'article': case 'section': //  We do not add article/section, but sometimes found floating in a template
		if (!$this->blank(['booktitle', 'book-title']) && $this->has('title')) return FALSE;
		if (!$this->blank(WORK_ALIASES) && $this->wikiname() === 'citation') return FALSE; // TODO - check for things that should be swapped etc.
		$value = preg_replace('~^\[\d+\]\s*~', '', $value);  // Remove chapter numbers
		if ($this->blank(CHAPTER_ALIASES)) {
		  return $this->add($param_name, wikify_external_text($value));
		}
		return FALSE;

	  ###  ARTICLE LOCATORS ###
	  ### (page, volume etc) ###

	  case 'title':
		if (in_array(strtolower(sanitize_string($value)), BAD_TITLES )) return FALSE;
		if ($this->blank($param_name) || in_array($this->get($param_name), ['Archived copy', "{title}", 'ScienceDirect', 'Google Books', 'None', 'usurped title'])
									  || (stripos($this->get($param_name), 'EZProxy') !== FALSE && stripos($value, 'EZProxy') === FALSE)) {
		  foreach (['encyclopedia', 'encyclopaedia', 'work', 'dictionary', 'journal'] as $worky) {
			if (str_equivalent($this->get($worky), sanitize_string($value))) {
			  return FALSE;
			}
		  }
		  if ($this->has('article') &&
				 ($this->wikiname() === 'cite encyclopedia' || $this->wikiname() === 'cite dictionary' || $this->wikiname() === 'cite encyclopaedia')) return FALSE; // Probably the same thing
		  if (!$this->blank(['booktitle', 'book-title'])) return FALSE; // Cite conference uses this
		  if ($this->blank('script-title')) {
			return $this->add($param_name, wikify_external_text($value));
		  } else {
			$value = trim($value);
			$script_value = $this->get('script-title');
			if (preg_match('~^[a-zA-Z0-9\.\,\-\; ]+$~u', $value) &&
				  mb_stripos($script_value, $value) === FALSE &&
				  mb_stripos($value, $script_value) === FALSE &&
				  !preg_match('~^[a-zA-Z0-9\.\,\-\; ]+$~u', $script_value)) {
			  { // Neither one is part of the other and script is not all ascii and new title is all ascii
				 return $this->add($param_name, wikify_external_text($value));
			  }
			}
		  }
		}
		return FALSE;

	  case 'volume':
		if ($this->blank($param_name) || str_i_same('in press', $this->get($param_name))) {
		  if ($value === '0') return FALSE;
		  if ($value === 'Online First') return FALSE;
		  if ($value === '1') { // dubious
			if (bad_10_1093_doi($this->get('doi'))) return FALSE;
			if (stripos($this->rawtext, 'oxforddnb') !== FALSE) return FALSE;
			if (stripos($this->rawtext, 'escholarship.org') !== FALSE) return FALSE;
		  }
		  if (preg_match('~^volume[:\s]+0*(.*)~i', $value, $matches)) {
			$value = $matches[1];
		  }
		  if ((intval($value) > 1820) && (stripos($this->get('doi'), '10.1515/crll') === 0)) return FALSE;
		  $temp_string = strtolower($this->get('journal')) ;
		  if(substr($temp_string, 0, 2) === "[[" && substr($temp_string, -2) === "]]") {  // Wikilinked journal title
			   $temp_string = substr(substr($temp_string, 2), 0, -2); // Remove [[ and ]]
		  }
		  if (in_array($temp_string, HAS_NO_VOLUME)) {
			// This journal has no volume. This is really the issue number
			return $this->add_if_new('issue', $value);
		  } else {
			return $this->add($param_name, $value);
		  }
		}
		return FALSE;

	  case 'issue':
	  case 'number':
		if ($value === '0') return FALSE;
		if ($value === 'Online First') return FALSE;
		$temp_string = strtolower($this->get('journal')) ;
		if(substr($temp_string, 0, 2) === "[[" && substr($temp_string, -2) === "]]") { // Wikilinked journal title
		   $temp_string = substr(substr($temp_string, 2), 0, -2); // Remove [[ and ]]
		}
		if (in_array($temp_string, HAS_NO_ISSUE)) {
		  return $this->add_if_new('volume', $value);
		}
		if ($this->blank(ISSUE_ALIASES) || str_i_same('in press', $this->get($param_name))) {
		  if ($value === '1') { // dubious
			if (bad_10_1093_doi($this->get('doi'))) return FALSE;
			if (stripos($this->rawtext, 'oxforddnb') !== FALSE) return FALSE;
			if (stripos($this->rawtext, 'escholarship.org') !== FALSE) return FALSE;
		  }
		  return $this->add($param_name, $value);
		} elseif ($this->get('issue') . $this->get('number') === '1' && $value !== '1' && $this->blank('volume')) {
		  if ($param_name === 'issue' && $this->has('number')) $this->rename('number', 'issue');
		  if ($param_name === 'number' && $this->has('issue')) $this->rename('issue', 'number');
		  $this->set($param_name, $value); // Updating bad data
		  return TRUE;
		}
		return FALSE;

	  case "page": case "pages":
		if (in_array($value, ['0', '0-0', '0–0'], TRUE)) return FALSE; // Reject bogus zero page number
		if ($this->has('at')) return FALSE;  // Leave at= alone.  People often use that for at=See figure 17 on page......
		if (preg_match('~^\d+$~', $value) && intval($value) > 50000) return FALSE; // Sometimes get HUGE values
		$pages_value = $this->get('pages');
		$all_page_values = $pages_value . $this->get('page') . $this->get('pp') . $this->get('p') . $this->get('at');
		$en_dash = [chr(2013), chr(150), chr(226), '-', '&ndash;'];
		$en_dash_X = ['X', 'X', 'X', 'X', 'X'];
		if (  mb_stripos($all_page_values, 'see ') !== FALSE  // Someone is pointing to a specific part
		   || mb_stripos($all_page_values, 'table') !== FALSE
		   || mb_stripos($all_page_values, 'footnote') !== FALSE
		   || mb_stripos($all_page_values, 'endnote') !== FALSE
		   || mb_stripos($all_page_values, 'article') !== FALSE
		   || mb_stripos($all_page_values, '[') !== FALSE
		   || mb_stripos($all_page_values, ',') !== FALSE
		   || mb_stripos($all_page_values, '(') !== FALSE
		   || mb_stripos($all_page_values, 'CITATION_BOT_PLACEHOLDER') !== FALSE) { // A comment or template will block the bot
		   return FALSE;
		}
		if ($this->blank(PAGE_ALIASES) // no page yet set
		   || $all_page_values === ""
		   || (str_i_same($all_page_values,'no') || str_i_same($all_page_values,'none')) // Is exactly "no" or "none"
		   || (strpos(strtolower($all_page_values), 'no') !== FALSE && $this->blank('at')) // "None" or "no" contained within something other than "at"
		   || (
				(  str_replace($en_dash, $en_dash_X, $value) !== $value) // dash in new `pages`
				&& str_replace($en_dash, $en_dash_X, $pages_value) === $pages_value // No dash already
			  )
		   || ( // Document with bogus pre-print page ranges
				   ($value !== '1' && substr(str_replace($en_dash, $en_dash_X, $value), 0, 2) !== '1X') // New is not 1-
				&& ($all_page_values === '1' || substr(str_replace($en_dash, $en_dash_X, $all_page_values), 0, 2) === '1X') // Old is 1-
				&& ($this->blank('year') || 2 > ((int) date("Y") - (int) $this->get('year'))) // Less than two years old
			  )
		) {
			if ($param_name === "pages" && preg_match('~^\d{1,}$~', $value)) $param_name = 'page'; // No dashes, just numbers
			// One last check to see if old template had a specific page listed
			if ($all_page_values !== '' &&
				preg_match("~^[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?[-–—‒]+[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?$~u", $value, $newpagenos) && // Adding a range
				preg_match("~^[a-zA-Z]?[a-zA-Z]?(\d+)[a-zA-Z]?[a-zA-Z]?~u", $all_page_values, $oldpagenos)) { // Just had a single number before
				$first_page = (int) $newpagenos[1];
				$last_page  = (int) $newpagenos[2];
				$old_page   = (int) $oldpagenos[1];
				if ($last_page < $first_page) { // 2342-5 istead of 2342-2345
				   if ($last_page < 10) {
					 $last_page = $last_page + (10 * (int)($first_page/10));
				   } else {
					 $last_page = $last_page + (100 * (int)($first_page/100));
				   }
				}
				if ($old_page > $first_page && $old_page <= $last_page) {
				  foreach (['pages', 'page', 'pp', 'p'] as $forget_blank) {
					if ($this->blank($forget_blank)) {
					  $this->forget($forget_blank);
					}
				  }
				  return FALSE;
				}
			}
			// 1-34 vs article 431234 -- Some give range and article ID as page numbers depending upon database - at least 4 characters though. Prefer article number
			if (preg_match('~^1[-–]\d+$~u', $value) && preg_match('~^[a-zA-Z1-9]\d{3,}$~', $all_page_values)) {
			  return FALSE;
			}
			if ($param_name !== "pages") $this->forget('pages'); // Forget others -- sometimes we upgrade page=123 to pages=123-456
			if ($param_name !== "page")  $this->forget('page');
			// Forget ones we never even add
			$this->forget('pp');
			$this->forget('p');
			$this->forget('at');

			if (preg_match("~^(\d+)[-–—‒]+(\d+)$~u", $value, $newpagenos)) {
				$first_page = (int) $newpagenos[1];
				$last_page  = (int) $newpagenos[2];
				if ($last_page < $first_page) { // 2342-5 istead of 2342-2345
				   if ($last_page < 10) {
					 $last_page = $last_page + (10 * (int)($first_page/10));
				   } else {
					 $last_page = $last_page + (100 * (int)($first_page/100));
				   }
				   if ($last_page > $first_page) { // Paranoid
					 $value = (string) $first_page . "–" . (string) $last_page;
				   }
				}
			}

			$param_key = $this->get_param_key($param_name);
			if (!is_null($param_key)) {
			  $this->param[$param_key]->val = sanitize_string($value); // Minimize template changes (i.e. location) when upgrading from page=123 to pages=123-456
			} else {
			  $this->add($param_name, sanitize_string($value));
			}
			$this->tidy_parameter($param_name); // Clean up dashes etc
			return TRUE;
		}
		return FALSE;

	  ###  ARTICLE IDENTIFIERS

	  case 'url':
		// look for identifiers in URL - might be better to add a PMC parameter, say
		if ($this->get_identifiers_from_url($value)) return FALSE;
		if (!$this->blank(array_merge([$param_name], TITLE_LINK_ALIASES))) return FALSE;
		if ($this->get('title') === 'none') return FALSE;
		if (strpos($this->get('title'), '[') !== FALSE) return FALSE;  // TITLE_LINK_ALIASES within the title
		$value = sanitize_string($value);
		foreach (ALL_URL_TYPES as $existing)  {
		  if (str_i_same($value, $this->get($existing))) {
			return FALSE;
		  }
		}
		return $this->add($param_name, $value);

	  case 'chapter-url':
		$value = sanitize_string($value);
		foreach (ALL_URL_TYPES as $existing)  {
		  if (str_i_same($value, $this->get($existing))) {
			return FALSE;
		  }
		}
		$chap = '';
		foreach (CHAPTER_ALIASES as $alias) {
		  $chap = $chap . $this->get($alias);
		}
		if (preg_match('~\[\[.+\]\]~', $chap)) return FALSE; // Chapter is already wikilinked
		return $this->add($param_name, $value);

	  case 'archive-url':
		if ($this->blank(['archive-url', 'archiveurl'])) {
		   $this->add($param_name, $value);
		   $this->tidy_parameter($param_name);
		   return TRUE;
		}
		return FALSE;

	  case 'title-link':
		if ($this->blank(array_merge(TITLE_LINK_ALIASES, ['url']))) {
		  return $this->add($param_name, $value); // We do not sanitize this, since it is not new data
		}
		return FALSE;

	  case 'class':
		if ($this->blank($param_name) && strpos($this->get('eprint') . $this->get('arxiv'), '/') === FALSE ) { // Old eprints include class in the ID
		  if ($this->wikiname() === 'cite arxiv') {  // Only relevant for cite arxiv
			return $this->add($param_name, sanitize_string($value));
		  }
		}
		return FALSE;

	  case 'doi':
		if (doi_is_bad($value)) return FALSE;
		if (preg_match(REGEXP_DOI, $value, $match)) {
		  if ($this->blank($param_name)) {
			if ($this->wikiname() === 'cite arxiv') $this->change_name_to('cite journal');
			$this->add('doi', $match[0]);
			return TRUE;
		  } elseif (!str_i_same($this->get('doi'), $match[0]) && !$this->blank(DOI_BROKEN_ALIASES) && doi_active($match[0])) {
			report_action("Replacing non-working DOI with a working one");
			$this->set('doi', $match[0]);
			$this->tidy_parameter('doi');
			return TRUE;
		  } elseif (!str_i_same($this->get('doi'), $match[0])
					&& strpos($this->get('doi'), '10.13140/') === 0
					&& doi_active($match[0])) {
			report_action("Replacing ResearchGate DOI with publisher's");
			$this->set('doi', $match[0]);
			$this->tidy_parameter('doi');
			return TRUE;
		  }
		}
		return FALSE;

	  case 'doi-access':
		if ($this->blank('doi') || $this->has($param_name)) return FALSE;
		$this->add($param_name, $value);
		if ($value === 'free' && doi_works($this->get('doi'))) {
			if (preg_match('~^https?://(?:dx\.|)doi\.org~', $this->get('url'))) $this->forget('url');
			$this->this_array = array($this);
			Zotero::drop_urls_that_match_dois($this->this_array);
			$this->this_array = array();
		}
		return TRUE;

	  case 's2cid':
		if (in_array($value, ['11008564'])) return FALSE; // known bad values
		if ($this->blank(['s2cid', 'S2CID'])) {
		  $this->add($param_name, $value);
		  $this->get_doi_from_semanticscholar();
		  return TRUE;
		}
		return FALSE;

	  case 'eprint':
	  case 'arxiv':
		if ($this->blank(ARXIV_ALIASES)) {
		  $this->add($param_name, $value);
		  return TRUE;
		}
		return FALSE;

	  case 'doi-broken-date':
		if ($this->blank('jstor')) {
		  check_doi_for_jstor($this->get('doi'), $this);
		  if ($this->has('jstor')) {
			$this->quietly_forget('doi');
			return TRUE;
		  }
		}
		// Forget any others that are blank
		foreach (array_diff(DOI_BROKEN_ALIASES, ['doi-broken-date']) as $alias) {
		  if ($this->blank($alias)) {
			 $this->forget($alias);
		  }
		}
		// Switch any that are set to doi-broken-date
		if ($this->blank('doi-broken-date')) {
		  foreach (array_diff(DOI_BROKEN_ALIASES, ['doi-broken-date']) as $alias) {
			$this->rename($alias, 'doi-broken-date');
		  }
		} else {
		  foreach (array_diff(DOI_BROKEN_ALIASES, ['doi-broken-date']) as $alias) {
			$this->forget($alias);
		  }
		}
		$time = strtotime($value);
		if ($time) { // paranoid
			if (self::$date_style === DATES_MDY) {
			   $value = date('F j, Y', $time);
			} elseif (self::$date_style === DATES_DMY) {
			   $value = date('j F Y', $time);
			}
		}
		if ($this->blank(DOI_BROKEN_ALIASES)) {
		  return $this->add($param_name, $value);
		}
		$existing = strtotime($this->get('doi-broken-date'));
		$the_new  = strtotime($value);
		if (($existing === FALSE) || ($existing + (7*2592000) < $the_new) || ((2592000*7) + $the_new < $existing)) { // Seven months of difference
		   return $this->add($param_name, $value);
		}
		// TODO : re-checked & change this back to 6 months ago everyone in a while to compact all DOIs
		$last_day = strtotime("23:59:59 31 July 2023");
		$check_date = $last_day - 126000;
		// @codeCoverageIgnoreStart
		if (($the_new > $last_day) && ($existing < $check_date)) {
			if (self::$date_style === DATES_MDY) {
			   return $this->add($param_name, date('F j, Y', $last_day));
			} else {
			   return $this->add($param_name, date('j F Y', $last_day));
			}
		}
		// @codeCoverageIgnoreEnd
		return FALSE;

	  case 'pmid':
		if ($value === "0" ) return FALSE; // Got PMID of zero once from pubmed
		if ($this->blank($param_name)) {
		  if ($this->wikiname() === 'cite web') $this->change_name_to('cite journal');
		  $this->add($param_name, sanitize_string($value));
		  $this->expand_by_pubmed($this->blank('pmc') || $this->blank('doi')); //Force = TRUE if missing DOI or PMC
		  $this->get_doi_from_crossref();
		  return TRUE;
		}
		return FALSE;

	  case 'pmc':
		if ($value === "PMC0" || $value === "0" ) return FALSE;  // Got PMID of zero once from pubmed
		if ($this->blank($param_name)) {
		  $this->add($param_name, sanitize_string($value));
		  if ($this->blank('pmid')) {
			$this->expand_by_pubmed(TRUE); // Almost always can get a PMID (it is rare not too)
		  }
		  return TRUE;
		}
		return FALSE;

	  case 'bibcode_nosearch': // Avoid recursive loop
	  case 'bibcode':
		if (stripos($value, 'arxiv') === FALSE && stripos($value, 'tmp') === FALSE &&
			(stripos($this->get('bibcode'), 'arxiv') !== FALSE || stripos($this->get('bibcode'), 'tmp') !== FALSE) &&
			strlen(trim($value)) > 16
			) {
		  $this->quietly_forget('bibcode');  // Upgrade bad bibcode
		}
		if ($this->blank('bibcode')) {
		  $bibcode_pad = 19 - strlen($value);
		  if ($bibcode_pad > 0) {  // Paranoid, don't want a negative value, if bibcodes get longer
			$value = $value . str_repeat( ".", $bibcode_pad);  // Add back on trailing periods
		  }
		  if (stripos($value, 'arxiv') !== FALSE) {
			if ($this->has('arxiv') || $this->has('eprint'))return FALSE;
			$low_quality = TRUE;
		  } else {
			$low_quality = FALSE;
		  }
		  $this->add('bibcode', $value);
		  if ($param_name === 'bibcode') {
			$bib_array = array($value);
			$this->this_array = array($this);
			query_bibcode_api($bib_array, $this->this_array);
			$this->this_array = array();
		  }
		  if ($low_quality) {
			$this->quietly_forget('bibcode');
		  }
		  return TRUE;
		}
		return FALSE;

	  case 'isbn';
		if (in_array($value, ['9780918678072', '978-0-918678-07-2', '0918678072', '0-918678-07-2'])) return FALSE; // Not a good one
		if ($this->blank($param_name)) {
		  $value = $this->isbn10Toisbn13($value);
		  if (strlen($value) === 13 && substr($value, 0, 6) === '978019') { // Oxford
			 $value = '978-0-19-' . substr($value, 6, 6) . '-' . substr($value, 12, 1);
		  }
		  if (strlen($value) > 19) return FALSE; // Two ISBNs
		  $value = self::addISBNdashes($value);
		  return $this->add($param_name, $value);
		}
		return FALSE;

	  case 'asin':
		if ($this->blank($param_name)) {
		  if($this->has('isbn')) { // Already have ISBN
			quietly('report_inaction', "Not adding ASIN: redundant to existing ISBN.");
			return FALSE;
		  } elseif (preg_match("~^\d~", $value) && substr($value, 0, 2) !== '63') { // 630 and 631 ones are not ISBNs, so block all of 63*
			$possible_isbn = sanitize_string($value);
			$possible_isbn13 = $this->isbn10Toisbn13($possible_isbn, TRUE);
			if ($possible_isbn === $possible_isbn13) {
			  return $this->add('asin', $possible_isbn); // Something went wrong, add as ASIN
			} else {
			  return $this->add('isbn', $this->isbn10Toisbn13($possible_isbn));
			}
		  } else { // NOT ISBN
			return $this->add($param_name, sanitize_string($value));
		  }
		}
		return FALSE;

	  case 'publisher':
		if ($this->had_initial_publisher) return FALSE;
		if (strlen(preg_replace('~[\.\s\d\,]~', '', $value)) < 5) return FALSE; // too few characters
		if (stripos($value, 'Springer') === 0) $value = 'Springer'; // they add locations often
		if (stripos($value, '[s.n.]') !== FALSE) return FALSE;
		if (preg_match('~^\[([^\|\[\]]*)\]$~', $value, $match)) $value = $match[1]; // usually zotero problem of [data]
		if (preg_match('~^(.+), \d{4}$~', $value, $match)) $value = $match[1]; // remove years from zotero
		if (strpos(strtolower($value), 'london') !== FALSE) return FALSE; // Common from archive.org
		if (strpos(strtolower($value), 'edinburg') !== FALSE) return FALSE; // Common from archive.org
		if (strpos(strtolower($value), 'privately printed') !== FALSE) return FALSE; // Common from archive.org
		if (str_equivalent($this->get('location'), $value)) return FALSE; // Catch some bad archive.org data
		if (strpos(strtolower($value), 'impressum') !== FALSE) return FALSE; // Common from archive.org
		if (strpos(strtolower($value), ':') !== FALSE) return FALSE; // Common from archive.org when location is included
		if (strpos(strtolower($value), '[etc.]') !== FALSE) return FALSE; // common from biodiversitylibrary.org - what does the etc. mean?
		if (($this->wikiname() !== 'cite book') && !$this->blank(WORK_ALIASES)) return FALSE;  // Do not add if work is set, unless explicitly a book

		$value = truncate_publisher($value);
		if (in_array(trim(strtolower($value), " \.\,\[\]\:\;\t\n\r\0\x0B" ), BAD_PUBLISHERS)) return FALSE;
		if ($this->has('via') && str_equivalent($this->get('via'), $value)) $this->rename('via', $param_name);
		if (mb_strtoupper($value) === $value || mb_strtolower($value) === $value) {
		   $value = title_capitalization($value, TRUE);
		}
		if ($this->blank($param_name)) {
		  return $this->add($param_name, $value);
		}
		return FALSE;

	  case 'type':
		if ($this->blank($param_name) &&
			!in_array(strtolower($value), ['text', 'data set']) &&
			strlen($value) === mb_strlen($value) &&
			strpos($value, 'purl.org') === FALSE &&
			strpos($value, 'dcmitype') === FALSE &&
			strpos($value, 'http') === FALSE
		   ) {
		  return $this->add($param_name, sanitize_string($value));
		}
		return FALSE;

	  case 'location':
		if ($this->had_initial_publisher) return FALSE;
		if ($this->blank($param_name)) {
		  return $this->add($param_name, sanitize_string($value));
		}
		return FALSE;

	  case 'jstor':
		if ($value === '3511692') return FALSE; // common review
		if ($this->blank($param_name)) {
		  return $this->add($param_name, sanitize_string($value));
		}
		return FALSE;

	  case 'zbl': case 'oclc': case 'mr': case 'lccn': case 'hdl':
	  case 'ssrn': case 'ol': case 'jfm': case 'osti': case 'biorxiv': case 'citeseerx': case 'via':
		if ($this->blank($param_name)) {
		  return $this->add($param_name, sanitize_string($value));
		}
		return FALSE;

	  case (bool) preg_match('~author(?:\d{1,}|)-link~', $param_name):
		if ($this->blank($param_name)) {
		  return $this->add($param_name, sanitize_string($value));
		}
		return FALSE;

	  case 'id':
		if ($this->blank($param_name)) {
		  return $this->add($param_name, $value); // Do NOT Sanitize.  It includes templates often
		}
		return FALSE;

	  case 'edition':
		if ($this->blank($param_name)) {
		  $this->add($param_name, $value);
		  return TRUE;
		}
		return FALSE;

	  case 'work':
	  case 'website':
		if ($this->blank(WORK_ALIASES)) {
		  return $this->add($param_name, $value); // Do NOT Sanitize
		}
		return FALSE;

	  default: // We want to make sure we understand what we are adding - sometimes we find odd floating parameters
		// @codeCoverageIgnoreStart
		report_minor_error('Unexpected parameter: ' . echoable($param_name) . ' trying to be set to ' . echoable($value));
		// if ($this->blank($param_name)) {
		//  return $this->add($param_name, sanitize_string($value));
		// }
		return FALSE;
		// @codeCoverageIgnoreEnd
	}
  }

  public function validate_and_add(string $author_param, string $author, string $forename, string $check_against, bool $add_even_if_existing) : void {
	if (!$add_even_if_existing && ($this->initial_author_params || $this->had_initial_editor)) return; // Zotero does not know difference between editors and authors often
	if (in_array(mb_strtolower($author), BAD_AUTHORS) === FALSE &&
		in_array(mb_strtolower($forename), BAD_AUTHORS) === FALSE &&
		in_array(mb_strtolower($forename . ' ' . $author), BAD_AUTHORS) === FALSE &&
		author_is_human($author) &&
		author_is_human($forename)) {
	  while(preg_match('~^(.*)\s[\S]+@~', ' ' . $author, $match) || // Remove emails
			preg_match('~^(.*)\s+@~', ' ' . $author, $match)) { // Remove twitter handles
		 $author = trim($match[1]);
	  }
	  while(preg_match('~^(.*)\s[\S]+@~', ' ' . $forename, $match) || // Remove emails
			preg_match('~^(.*)\s+@~', ' ' . $forename, $match)) { // Remove twitter handles
		 $forename = trim($match[1]);
	  }
	  while(preg_match('~^(?:rabbi|prof\.|doctor|professor|dr\.)\s([\s\S]+)$~i', $forename, $match)) { // Remove titles
		 $forename = trim($match[1]);
	  }
	  while(preg_match('~^(?:rabbi|prof\.|doctor|professor|dr\.)\s([\s\S]+)$~i', $author, $match)) { // Remove titles
		 $author = trim($match[1]);
	  }
	  if (trim($author) === '') {
		 $author = trim($forename);
		 $forename = '';
	  }
	  $author_parts  = explode(" ", $author);
	  $author_ending = end($author_parts);
	  $name_as_publisher = trim($forename . ' ' . $author);
	  if (in_array(strtolower($author_ending), PUBLISHER_ENDINGS)
		  || stripos($check_against, $name_as_publisher) !== FALSE) {
		$this->add_if_new('publisher' , $name_as_publisher);
	  } else {
		$this->add_if_new($author_param, format_author($author . ($forename ? ", $forename" : '')));
	  }
	}
  }

  public function mark_inactive_doi() : void {
	$doi = $this->get_without_comments_and_placeholders('doi');
	if (doi_works($doi) === FALSE) { // NULL which would cast to FALSE means we don't know, so use ===
	  $this->add_if_new('doi-broken-date', date('Y-m-d'));
	}
  }

  // This is also called when adding a URL with add_if_new, in which case
  // it looks for a parameter before adding the url.
  public function get_identifiers_from_url(?string $url_sent = NULL) : bool {
	return Zotero::find_indentifiers_in_urls($this, $url_sent);
  }

  protected function get_doi_from_text() : void {
	set_time_limit(120);
	if ($this->blank('doi') && preg_match('~10\.\d{4}/[^&\s\|\}\{]*~', urldecode($this->parsed_text()), $match)) {
	  if (stripos($this->rawtext, 'oxforddnb.com') !== FALSE) return; // generally bad, and not helpful
	  if (strpos($this->rawtext, '10.1093') !== FALSE) return; // generally bad, and not helpful
	  // Search the entire citation text for anything in a DOI format.
	  // This is quite a broad match, so we need to ensure that no baggage has been tagged on to the end of the URL.
	  $doi = preg_replace("~(\.x)/(?:\w+)~", "$1", $match[0]);
	  $doi = extract_doi($doi)[1];
	  if ($doi === '') return; // Found nothing
	  if ($this->has('quote') && strpos($this->get('quote'), $doi) !== FALSE) return;
	  if (doi_active($doi)) $this->add_if_new('doi', $doi);
	}
  }

  public function get_doi_from_crossref() : void {
	set_time_limit(120);
	if ($this->has('doi')) return;
	report_action("Checking CrossRef database for doi. ");
	$page_range = $this->page_range();
	$data = [
	  'title'      => de_wikify($this->get('title')),
	  'journal'    => de_wikify($this->get('journal')),
	  'author'     => $this->first_surname(),
	  'year'       => (int) preg_replace("~([12]\d{3}).*~", "$1", $this->year()),
	  'volume'     => $this->get('volume'),
	  'start_page' => isset($page_range[1]) ? $page_range[1] : NULL,
	  'end_page'   => isset($page_range[2]) ? $page_range[2] : NULL,
	  'issn'       => $this->get('issn')
	];

	if ($data['year'] < 1900 || $data['year'] > ((int) date("Y") + 3)) {
	  $data['year'] = NULL;
	} else {
	  $data['year'] = (string) $data['year'];
	}
	if ((int) $data['end_page'] < (int) $data['start_page']) {
	  $data['end_page'] = NULL;
	}

	$novel_data = FALSE;
	foreach ($data as $key => $value) if ($value) {
	  if (!$this->api_has_used('crossref', equivalent_parameters($key))) $novel_data = TRUE;
	  $this->record_api_usage('crossref', $key);
	}

	if (!$novel_data) {
	  report_info("No new data since last CrossRef search.");
	  return;
	}
	// They already allow some fuzziness in matches
	if (($data['journal'] || $data['issn']) && ($data['start_page'] || $data['author'])) {
	  $url = "https://www.crossref.org/openurl/?noredirect=TRUE&pid=" . CROSSREFUSERNAME
		   . ($data['title']      ? "&atitle=" . urlencode($data['title'])      : '')
		   . ($data['author']     ? "&aulast=" . urlencode($data['author'])     : '')
		   . ($data['start_page'] ? "&spage="  . urlencode($data['start_page']) : '')
		   . ($data['end_page']   ? "&epage="  . urlencode($data['end_page'])   : '')
		   . ($data['year']       ? "&date="   . urlencode($data['year'])       : '')
		   . ($data['volume']     ? "&volume=" . urlencode($data['volume'])     : '')
		   . ($data['issn']       ? "&issn="   . urlencode($data['issn'])       : "&title=" . urlencode($data['journal']))
		   . "&mailto=".CROSSREFUSERNAME; // do not encode crossref email
	  $ch = curl_init_array(1.0,
	    [CURLOPT_URL => $url,
	     CURLOPT_USERAGENT => BOT_CROSSREF_USER_AGENT]);
	  $xml = curl_exec($ch);
	  unset($ch);
	  if (is_string($xml) && (strlen($xml) > 0)) {
		$result = @simplexml_load_string($xml);
	  } else {
		$result = FALSE;
	  }
	  if ($result === FALSE) {
		report_warning("Error loading simpleXML file from CrossRef."); // @codeCoverageIgnore
		return;   // @codeCoverageIgnore
	  }
	  if (!isset($result->query_result->body->query)) {
		report_warning("Unexpected simpleXML file from CrossRef.");  // @codeCoverageIgnore
		return;      // @codeCoverageIgnore
	  }
	  $result = $result->query_result->body->query;
	  if ((string) $result->attributes()->status === 'malformed') {
		report_minor_error("Cannot search CrossRef: " . echoable((string) $result->msg));  // @codeCoverageIgnore
	  } elseif ((string) $result->attributes()->status === "resolved") {
		if (!isset($result->doi)) return;
		report_info(" Successful!");
		$this->add_if_new('doi', (string) $result->doi);
		return;
	  }
	}
	return;
  }

  public function get_doi_from_semanticscholar() : void {
	set_time_limit(120);
	if ($this->has('doi')) return;
	if ($this->blank(['s2cid', 'S2CID'])) return;
	if ($this->has('s2cid') && $this->has('S2CID')) return;
	report_action("Checking semanticscholar database for doi. ");
	$doi = ConvertS2CID_DOI($this->get('s2cid') . $this->get('S2CID'));
	if ($doi) {
	  report_info(" Successful!");
	  $this->add_if_new('doi', $doi);
	}
	return;
  }

  public function find_pmid() : void {
	set_time_limit(120);
	if (!$this->blank('pmid')) return;
	report_action("Searching PubMed... ");
	$results = $this->query_pubmed();
	if ($results[1] === 1) {
	  // Double check title if we did not use DOI
	  if ($this->has('title') && !in_array('doi', $results[2])) {
		usleep(100000); // Wait 1/10 of a second since we just tried
		$xml = get_entrez_xml('pubmed', $results[0]);
		if ($xml === NULL || !is_object($xml->DocSum->Item)) {
		  report_inline("Unable to query pubmed."); // @codeCoverageIgnore
		  return;  // @codeCoverageIgnore
		}
		$Items = $xml->DocSum->Item;
		foreach ($Items as $item) {
		   if ((string) $item->attributes()->Name === 'Title') {
			   $new_title = str_replace(array("[", "]"), "", (string) $item);
			   foreach (['chapter', 'title', 'series', 'trans-title', 'book-title'] as $possible) {
				 if ($this->has($possible) && titles_are_similar($this->get($possible), $new_title)) {
				   $this->add_if_new('pmid', $results[0]);
				   return;
				 }
			   }
			   // @codeCoverageIgnoreStart
			   report_inline("Similar matching pubmed title not similar enough. Rejected: " . pubmed_link('pmid', $results[0]));
			   return;
			   // @codeCoverageIgnoreEnd
		   }
		}
	  }
	  $this->add_if_new('pmid', $results[0]);
	} else {
	  report_inline("nothing found.");
	}
  }

  /** @return array{0: string, 1: int, 2: array<string>} */
  protected function query_pubmed() : array {
/*
 * Performs a search based on article data, using the DOI preferentially, and failing that, the rest of the article details.
 * Returns an array:
 * [0] => PMID of first matching result
 * [1] => total number of results
 */
	if ($doi = $this->get_without_comments_and_placeholders('doi')) {
	  if (doi_works($doi)) {
		$results = $this->do_pumbed_query(array("doi"));
		if ($results[1] !== 0) return $results; // If more than one, we are doomed
	  }
	}
	// If we've got this far, the DOI was unproductive or there was no DOI.

	if ($this->has('journal') && $this->has('volume') && $this->page_range()) {
	  $results = $this->do_pumbed_query(array("journal", "volume", "issue", "page"));
	  if ($results[1] === 1) return $results;
	}
	$is_book = $this->looksLikeBookReview((object) array());
	if ($this->has('title') && $this->first_surname() && !$is_book) {
		$results = $this->do_pumbed_query(array("title", "surname", "year", "volume"));
		if ($results[1] === 1) return $results;
		if ($results[1] > 1) {
		  $results = $this->do_pumbed_query(array("title", "surname", "year", "volume", "issue"));
		  if ($results[1] === 1) return $results;
		}
	}
	return array('', 0, array());
  }

  /** @param array<string> $terms
	  @return array{0: string, 1: int, 2: array<string>} */
  protected function do_pumbed_query(array $terms) : array {
	set_time_limit(120);
  /* do_query
   *
   * Searches pubmed based on terms provided in an array.
   * Provide an array of wikipedia parameters which exist in $p, and this will construct a Pubmed search query and
   * return the results as array (first result, # of results)
   */
	$key_index = array(
		'issue' => 'Issue',
		'journal' => 'Journal',
		'pmid' => 'PMID',
		'volume' => 'Volume'
	);
	$query = '';
	foreach ($terms as $term) {
	  $term = mb_strtolower($term);
	  if ($term === "title") {
	   if ($data = $this->get_without_comments_and_placeholders('title')) {
		$key = 'Title';
		$data = straighten_quotes($data, TRUE);
		$data = str_replace([';', ',', ':', '.', '?', '!', '&', '/', '(', ')', '[', ']', '{', '}', '"', "'", '|', '\\'],
							[' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '], $data);
		$data = strip_diacritics($data);
		$data_array = explode(" ", $data);
		foreach ($data_array as $val) {
		  if (!in_array(strtolower($val), array('the', 'and', 'a', 'for', 'in', 'on', 's', 're', 't',
												'an', 'as', 'at', 'and', 'but', 'how',
												'why', 'by', 'when', 'with', 'who', 'where', '')) &&
			 (mb_strlen($val) > 3)) {  // Small words are NOT indexed
			$query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
		  }
		}
	   }
	  } elseif ($term === "page") {
		if ($pages = $this->page_range()) {
		  $val = $pages[1];
		  $key = 'Pagination';
		  $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
		}
	  } elseif ($term === "surname") {
		if ($val = $this->first_surname()) {
		  $key = 'Author';
		  $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
		}
	  } elseif ($term === "year") {
		$key = 'Publication Date';
		if ($val = $this->year()) {
		  $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
		}
	  } elseif ($term === "doi") {
		$key = 'AID';
		if ($val = $this->get_without_comments_and_placeholders($term)) {
		   $query .= " AND (" . "\"" . str_replace(array("%E2%80%93", ';'), array("-", '%3B'), $val) . "\"" . "[$key])"; // PubMed does not like escaped /s in DOIs, but other characters seem problematic.
		}
	  } else {
		$key = $key_index[$term]; // Will crash if bad data is passed
		if ($val = $this->get_without_comments_and_placeholders($term)) {
		  if (preg_match(REGEXP_PLAIN_WIKILINK, $val, $matches)) {
			  $val = $matches[1];  // @codeCoverageIgnore
		  } elseif (preg_match(REGEXP_PIPED_WIKILINK, $val, $matches)) {
			  $val = $matches[2];  // @codeCoverageIgnore
		  }
		  $val = strip_diacritics($val);
		  $val = straighten_quotes($val, TRUE);
		  $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[$key])";
		}
	  }
	}
	$query = substr($query, 5); // Chop off initial " AND "
	usleep(20000); // Wait 1/50 of a second since we probably just tried
	$xml = get_entrez_xml('esearch_pubmed', $query);
	// @codeCoverageIgnoreStart
	if ($xml === NULL) {
	   sleep(1);
	   report_inline("no results.");
	   return array('', 0, array());
	}
	if (isset($xml->ErrorList)) { // Could look at $xml->ErrorList->PhraseNotFound for list of what was not found
	  report_inline('no results.');
	  return array('', 0, array());
	}
	// @codeCoverageIgnoreEnd

	if (isset($xml->IdList->Id[0]) && isset($xml->Count)) {
	  return array((string)$xml->IdList->Id[0], (int)(string)$xml->Count, $terms);// first results; number of results
	} else {
	  return array('', 0, array());
	}
  }

  public function expand_by_adsabs() : void {
	static $needs_told = TRUE;
	set_time_limit(120);
	if ($this->has('bibcode') && $this->blank('doi')) {
	  $doi = AdsAbsControl::get_bib2doi($this->get('bibcode'));
	  if (doi_works($doi)) {
		$this->add_if_new('doi', $doi);
	  }
	}
	if ($this->has('doi') && ($this->blank('bibcode') || stripos($this->get('bibcode'), 'tmp') !== FALSE || stripos($this->get('bibcode'), 'arxiv') !== FALSE)) {
	  $doi = $this->get('doi');
	  if (doi_works($doi)) {
		$bib = AdsAbsControl::get_doi2bib($doi);
		if (strlen($bib) > 12) $this->add_if_new('bibcode_nosearch', $bib);
	  }
	}

	// API docs at https://github.com/adsabs/adsabs-dev-api
	if ($this->has('bibcode') && !$this->incomplete() && stripos($this->get('bibcode'), 'tmp') === FALSE && stripos($this->get('bibcode'), 'arxiv') === FALSE  &&
		($this->has('doi') || AdsAbsControl::get_bib2doi($this->get('bibcode')) === 'X')) {  // Don't waste a query, if it has a doi or will not find a doi
	  return;  // @codeCoverageIgnore
	}

	if (!SLOW_MODE && $this->blank('bibcode')) return; // Only look for new bibcodes in slow mode
	if (stripos($this->get('bibcode'), 'CITATION') !== FALSE) return;
	// Do not search if it is a book - might find book review
	if (stripos($this->get('jstor'), 'document') !== FALSE) return;
	if (stripos($this->get('jstor'), '.ch.') !== FALSE) return;

	// Now use big query API for existing bibcode - code below still assumes that we might use a bibcode
	if (!$this->blank_other_than_comments('bibcode') && stripos($this->get('bibcode'), 'tmp') === FALSE && stripos($this->get('bibcode'), 'arxiv') === FALSE ) return;

	if ($this->api_has_used('adsabs', equivalent_parameters('bibcode'))) return;

	if ($this->has('bibcode')) $this->record_api_usage('adsabs', 'bibcode');
	if (strpos($this->get('doi'), '10.1093/') === 0) return;
	report_action("Checking AdsAbs database");
	// No longer use this code for exanding existing bibcodes
	// if ($this->has('bibcode')) {
	// $result = query_adsabs("identifier:" . urlencode('"' . $this->get('bibcode') . '"'));
	// } else
	if ($this->has('doi') && preg_match(REGEXP_DOI, $this->get_without_comments_and_placeholders('doi'), $doi)) {
	  $result = query_adsabs("identifier:" . urlencode('"' .  $doi[0] . '"')); // In DOI we trust
	} elseif ($this->has('eprint')) {
	  $result = query_adsabs("identifier:" . urlencode('"' . $this->get('eprint') . '"'));
	} elseif ($this->has('arxiv')) {
	  $result = query_adsabs("identifier:" . urlencode('"' . $this->get('arxiv')  . '"')); // @codeCoverageIgnore
	} else {
	  $result = (object) array("numFound" => 0);
	}

	if ($result->numFound > 1) {
	  report_warning("Multiple articles match identifiers "); // @codeCoverageIgnore
	  return;   // @codeCoverageIgnore
	}

	if ($result->numFound === 0) {
	  // Avoid blowing through our quota
	  if ((!in_array($this->wikiname(), ['cite journal', 'citation', 'cite conference', 'cite book', 'cite arxiv', 'cite article'])) || // Unlikely to find anything
		  ($this->wikiname() === 'cite book' && $this->has('isbn')) || // "complete" enough for a book
		  ($this->wikiname() === 'citation' && $this->has('isbn') && $this->has('chapter')) ||// "complete" enough for a book
		  ($this->has_good_free_copy()) || // Alreadly links out to something free
		  ($this->has('s2cid')) || // good enough, usually includes abstract and link to copy
		  ($this->has('doi') && doi_works($this->get('doi'))) || // good enough, usually includes abstract
		  ($this->has('bibcode'))) // Must be GIGO
		  {
			report_inline('no record retrieved.'); // @codeCoverageIgnore
			return;   // @codeCoverageIgnore
		  }
	}

	if (($result->numFound !== 1) && $this->has('title')) { // Do assume failure to find arXiv means that it is not there
	  $result = query_adsabs("title:" . urlencode('"' . trim(remove_brackets(str_replace('"', ' ', $this->get_without_comments_and_placeholders("title")))) . '"'));
	  if ($result->numFound === 0) return;
	  $record = $result->docs[0];
	  if (titles_are_dissimilar($this->get_without_comments_and_placeholders("title"), $record->title[0])) {  // Considering we searched for title, this is very paranoid
		report_info("Similar title not found in database."); // @codeCoverageIgnore
		return;   // @codeCoverageIgnore
	  }
	  // If we have a match, but other links exists, and we have nothing journal like, then require exact title match
	  if (!$this->blank(array_merge(['doi','pmc','pmid','eprint','arxiv'], ALL_URL_TYPES)) &&
		  $this->blank(['issn', 'journal', 'volume', 'issue', 'number']) &&
		  mb_strtolower($record->title[0]) !== mb_strtolower($this->get_without_comments_and_placeholders('title'))) {  // Probably not a journal, trust zotero more
		  report_info("Exact title match not found in database."); // @codeCoverageIgnore
		  return;   // @codeCoverageIgnore
	  }
	}

	if ($result->numFound !== 1 && ($this->has('journal') || $this->has('issn'))) {
	  $journal = $this->get('journal');
	  // try partial search using bibcode components:
	  $pages = $this->page_range();
	  if (!$pages) return;
	  if ($this->blank('volume') && !$this->year()) return;
	  $result = query_adsabs(
		  ($this->has('journal') ? "pub:" . urlencode('"' . remove_brackets($journal) . '"') : "&fq=issn:" . urlencode($this->get('issn')))
		. ($this->year() ? ("&fq=year:" . urlencode($this->year())) : '')
		. ($this->has('volume') ? ("&fq=volume:" . urlencode('"' . $this->get('volume') . '"')) : '')
		. ("&fq=page:" . urlencode('"' . $pages[1] . '"'))
	  );
	  if ($result->numFound === 0 || !isset($result->docs[0]->pub)) {
		report_inline('no record retrieved.'); // @codeCoverageIgnore
		return;   // @codeCoverageIgnore
	  }
	  $journal_string = explode(",", (string) $result->docs[0]->pub);
	  $journal_fuzzyer = "~\([iI]ncorporating.+|\bof\b|\bthe\b|\ba|eedings\b|\W~";
	  if (strlen($journal_string[0])
	  &&  strpos(mb_strtolower(safe_preg_replace($journal_fuzzyer, "", $journal)),
				 mb_strtolower(safe_preg_replace($journal_fuzzyer, "", $journal_string[0]))
				 ) === FALSE
	  ) {
		report_info("Partial match but database journal \"" . // @codeCoverageIgnoreStart
		  echoable($journal_string[0]) . "\" didn't match \"" .
		  echoable($journal) . "\".");
		return;   // @codeCoverageIgnoreEnd
	  }
	}
	if ($result->numFound === 1) {
	  $record = $result->docs[0];
	  if (isset($record->year) && $this->year()) {
		$diff = abs((int)$record->year - (int)$this->year()); // Check for book reviews (fuzzy >2 for arxiv data)
		$today = (int) date("Y");
		if ($diff > 2) return;
		if (($record->year < $today - 5)  && $diff > 1) return;
		if (($record->year < $today - 10) && $diff !== 0) return;
		if ($this->has('doi') && $diff !== 0) return;
	  }

	  if (!isset($record->title[0]) || !isset($record->bibcode)) {
		report_info("Database entry not complete");  // @codeCoverageIgnore
		return;  // @codeCoverageIgnore
	  }
	  if ($this->has('title') && titles_are_dissimilar($this->get('title'), $record->title[0])
		 && !in_array($this->get('title'), ['Archived copy', "{title}", 'ScienceDirect', "Google Books", "None", 'usurped title'])) { // Verify the title matches. We get some strange mis-matches {
		report_info("Similar title not found in database");  // @codeCoverageIgnore
		return;  // @codeCoverageIgnore
	  }

	  if (isset($record->doi) && $this->get_without_comments_and_placeholders('doi')) {
		if (!str_i_same((string) $record->doi[0], $this->get_without_comments_and_placeholders('doi'))) return; // New DOI does not match
	  }

	  if (strpos((string) $record->bibcode, 'book') !== FALSE) {  // Found a book. Need special code
		 $this->add_if_new('bibcode_nosearch', (string) $record->bibcode);
		 expand_book_adsabs($this, $record);
		 return;
	  }

	  if ($this->looksLikeBookReview($record)) { // Possible book and we found book review in journal
		  report_info("Suspect that BibCode " . bibcode_link((string) $record->bibcode) . " is book review. Rejecting.");
		  return;
	  }

	  if ($this->blank('bibcode')) {
		$this->add_if_new('bibcode_nosearch', (string) $record->bibcode);
		// The code below is not used anymore, since bot always uses interface in APIfunctions for existing bibcodes
		// @codeCoverageIgnoreStart
	  } elseif ($this->get('bibcode') !== (string) $record->bibcode && stripos($this->get('bibcode'), 'CITATION_BOT_PLACEHOLDER') === FALSE) {
		report_info("Updating " . bibcode_link($this->get('bibcode')) . " to " .  bibcode_link((string) $record->bibcode));
		$this->set('bibcode', (string) $record->bibcode);
	  }
	  // @codeCoverageIgnoreEnd
	  process_bibcode_data($this, $record);
	  return;
	} elseif ($result->numFound === 0) {  // @codeCoverageIgnoreStart
	  report_inline('no record retrieved.');
	  return;
	} else {
	  report_inline('multiple records retrieved.  Ignoring.');
	  return;   // @codeCoverageIgnoreEnd
	}
  }

  public function looksLikeBookReview(object $record) : bool {
	  if ($this->wikiname() === 'cite book' || $this->wikiname() === 'citation') {
		$book_count = 0;
		if($this->has('publisher')) $book_count += 1;
		if($this->has('isbn')) $book_count += 2;
		if($this->has('location')) $book_count += 1;
		if($this->has('chapter')) $book_count += 2;
		if($this->has('oclc')) $book_count += 1;
		if($this->has('lccn')) $book_count += 2;
		if($this->has('journal')) $book_count -= 2;
		if($this->has('series')) $book_count += 1;
		if($this->has('edition')) $book_count += 2;
		if($this->has('asin')) $book_count += 2;
		if(stripos($this->get('url'), 'google') !== FALSE && stripos($this->get('url'), 'book') !== FALSE) $book_count += 2;
		if(isset($record->year) && $this->year() && ((int)$record->year !== (int)$this->year())) $book_count += 1;
		if($this->wikiname() === 'cite book') $book_count += 3;
		if($book_count > 3) return TRUE;
	  }
	  return FALSE;
  }

  public function expand_by_RIS(string &$dat, bool $add_url) : void { // Pass by pointer to wipe this data when called from use_unnamed_params()
	$ris_review = FALSE;
	$ris_issn = FALSE;
	$ris_publisher = FALSE;
	$ris_book = FALSE;
	$ris_fullbook = FALSE;
	$has_T2 = FALSE;
	$bad_EP = FALSE;
	$bad_SP = FALSE;
	// Convert &#x__; to characters
	$ris = explode("\n", html_entity_decode($dat, ENT_COMPAT | ENT_HTML401, 'UTF-8'));
	$ris_authors = 0;

	if(preg_match('~(?:T[I1]).*-(.*)$~m', $dat, $match)) {
		if(in_array(strtolower(trim($match[1])), BAD_ACCEPTED_MANUSCRIPT_TITLES)) return ;
	}

	foreach ($ris as $ris_line) {
	  $ris_part = explode(" - ", $ris_line . " ", 2);
	  if (!isset($ris_part[1])) $ris_part[0] = ""; // Ignore
	  if (trim($ris_part[0]) === "TY") {
		if (in_array(trim($ris_part[1]), ['CHAP', 'BOOK', 'EBOOK', 'ECHAP', 'EDBOOK', 'DICT', 'ENCYC', 'GOVDOC'])) {
		  $ris_book = TRUE; // See https://en.wikipedia.org/wiki/RIS_(file_format)#Type_of_reference
		}
		if (in_array(trim($ris_part[1]), ['BOOK', 'EBOOK', 'EDBOOK'])) {
		  $ris_fullbook = TRUE;
		}
	  } elseif (trim($ris_part[0]) === "T2") {
		$has_T2 = TRUE;
	  } elseif (trim($ris_part[0]) === "SP" && (trim($ris_part[1]) === 'i' || trim($ris_part[1]) === '1')) {
		$bad_SP = TRUE;
	  } elseif (trim($ris_part[0]) === "EP" && preg_match('~^\d{3,}$~', trim($ris_part[1]))) {
		$bad_EP = TRUE;
	  }
	}

	foreach ($ris as $ris_line) {
	  $ris_part = explode(" - ", $ris_line . " ", 2);
	  $ris_parameter = FALSE;
	  if (!isset($ris_part[1])) $ris_part[0] = ""; // Ignore
	  switch (trim($ris_part[0])) {
		case "T1":
		  if ($ris_fullbook) {
			 ; // Sub-title of main title most likely
		  } elseif ($ris_book) {
			 $ris_parameter = "chapter";
		  } else {
			 $ris_parameter = "title";
		  }
		  break;
		case "TI":
		  $ris_parameter = "title";
		  if ($ris_book && $has_T2) $ris_parameter = "chapter";
		  break;
		case "AU":
		  $ris_authors++;
		  $ris_parameter = "author$ris_authors";
		  $ris_part[1] = format_author($ris_part[1]);
		  break;
		case "Y1":
		  $ris_parameter = "date";
		  break;
		case "PY":
		  $ris_parameter = "date";
		  $ris_part[1] = (preg_replace("~([\-\s]+)$~", '', str_replace('/', '-', $ris_part[1])));
		  break;
		case "SP": // Deal with start pages later
		  $start_page = trim($ris_part[1]);
		  $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
		  break;
		case "EP": // Deal with end pages later
		  $end_page = trim($ris_part[1]);
		  $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
		  break;
		case "DO":
		  $ris_parameter = doi_active($ris_part[1]) ? "doi" : FALSE;
		  break;
		case "JO":
		case "JF":
		  $ris_parameter = "journal";
		  break;
		case "T2":
		case "BT":
		  if ($ris_book) {
			 $ris_parameter = "title";
		  } else {
			 $ris_parameter = "journal";
		  }
		  break;
		case "VL":
		  $ris_parameter = "volume";
		  break;
		case "IS":
		  $ris_parameter = "issue";
		  break;
		case "RI": // Deal with review titles later
		  $ris_review = "Reviewed work: " . trim($ris_part[1]);  // Get these from JSTOR
		  $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
		  break;
		case "SN": // Deal with ISSN later
		  $ris_issn = trim($ris_part[1]);
		  $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
		  break;
		case "UR":
		  $ris_parameter = "url";
		  break;
		case "PB": // Deal with publisher later
		  $ris_publisher = trim($ris_part[1]); // Get these from JSTOR
		  $dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
		  break;
		case "M3": case "N1": case "N2": case "ER": case "TY": case "KW": case "T3":  // T3 is often the sub-title of a book
		case "A2": // This can be of the book that is reviewed
		case "A3": // Only seen this once and it duplicated AU
		case "ET": // Might be edition of book as an integer
		case "LA": // Language
		case "DA": // Date this is based upon, not written or published
		case "CY": // Location
		case "TT": // Translated title - very rare and often poor
		case "C1": case "DB": case "AB": case "Y2": // The following line is from JSTOR RIS (basically the header and blank lines)
		case "": case "Provider: JSTOR http://www.jstor.org": case "Database: JSTOR": case "Content: text/plain; charset=\"UTF-8\"";
		  $dat = trim(str_replace("\n$ris_line", "", "\n$dat")); // Ignore these completely
		  break;
		default:
		  if (isset($ris_part[1])) {
			 report_minor_error("Unexpected RIS data type ignored: " . echoable(trim($ris_part[0])) . " set to " . echoable(trim($ris_part[1]))); // @codeCoverageIgnore
		  };
	  }
	  unset($ris_part[0]);
	  if ($ris_parameter
			  && (($ris_parameter=='url' && !$add_url) || $this->add_if_new($ris_parameter, trim(implode($ris_part))))
		  ) {
		$dat = trim(str_replace("\n$ris_line", "", "\n$dat"));
	  }
	}
	if ($ris_review) $this->add_if_new('title', trim($ris_review));  // Do at end in case we have real title
	if (isset($start_page) && (!$bad_EP || !$bad_SP)) { // Have to do at end since might get end pages before start pages
	  if (isset($end_page) && ($start_page !== $end_page)) {
		 $this->add_if_new('pages', $start_page . '–' . $end_page);
	  } else {
		 $this->add_if_new('pages', $start_page);
	  }
	}
	if ($ris_issn) {
	   if (preg_match("~[\d\-]{9,}[\dXx]~", $ris_issn)) {
		  $this->add_if_new('isbn', $ris_issn);
	   } elseif (preg_match("~\d{4}\-?\d{3}[\dXx]~", $ris_issn)) {
		  if ($this->blank('journal')) $this->add_if_new('issn', $ris_issn);
	   }
	}
	if ($ris_publisher) {
	  if ($ris_book || $this->blank(['journal', 'magazine'])) {
		$this->add_if_new('publisher', $ris_publisher);
	  }
	}
  }

  public function expand_by_pubmed(bool $force = FALSE) : void {
	if (!$force && !$this->incomplete()) return;
	$this->this_array = array($this);
	if ($pm = $this->get('pmid')) {
	  report_action('Checking ' . pubmed_link('pmid', $pm) . ' for more details');
	  query_pmid_api(array($pm), $this->this_array);
	} elseif ($pm = $this->get('pmc')) {
	  report_action('Checking ' . pubmed_link('pmc', $pm) . ' for more details');
	  query_pmc_api(array($pm), $this->this_array);
	}
	$this->this_array = array();
  }

  public function use_sici() : bool {
	if (preg_match(REGEXP_SICI, urldecode($this->parsed_text()), $sici)) {
	  quietly('report_action', "Extracting information from SICI");
	  $this->add_if_new('issn', $sici[1]); // Check whether journal is set in add_if_new
	  $this->add_if_new('year', (string) (int) $sici[2]);
	  $this->add_if_new('volume', (string) (int) $sici[5]);
	  if ($sici[6]) $this->add_if_new('issue', (string) (int) $sici[6]);
	  $this->add_if_new('pages', (string) (int) $sici[7]);
	  return TRUE;
	} else return FALSE;
  }

  public function get_open_access_url() : void {
	if (!$this->blank(DOI_BROKEN_ALIASES)) return;
	$doi = $this->get_without_comments_and_placeholders('doi');
	if (!$doi) return;
	if (strpos($doi, '10.1093/') === 0) return;
	$return = $this->get_unpaywall_url($doi);
	if (in_array($return, array('publisher', 'projectmuse', 'have free'))) return; // Do continue on
	$this->get_semanticscholar_url($doi);
  }

  protected function get_semanticscholar_url(string $doi) : void {
   set_time_limit(120);
   if(      $this->has('pmc') ||
			($this->has('doi') && $this->get('doi-access') === 'free') ||
			($this->has('jstor') && $this->get('jstor-access') === 'free')
		   ) return; // do not add url if have OA already. Do indlude preprints in list
	if ($this->has('s2cid') || $this->has('S2CID')) return;
	$url = 'https://api.semanticscholar.org/v1/paper/' . doi_encode(urldecode($doi));
	$ch = curl_init_array(0.5, [CURLOPT_HTTPHEADER => HEADER_S2, CURLOPT_URL => $url]);
	$response = (string) curl_exec($ch);
	if ($response) {
	  $oa = @json_decode($response);
	  if ($oa !== FALSE && isset($oa->url) && isset($oa->is_publisher_licensed) && $oa->is_publisher_licensed) {
		$this->get_identifiers_from_url($oa->url);
	  }
	}
  }

  public function get_unpaywall_url(string $doi) : string {
	set_time_limit(120);
	$url = "https://api.unpaywall.org/v2/$doi?email=" . CROSSREFUSERNAME;
	$ch = curl_init_array(1.0,
			[CURLOPT_URL => $url,
			 CURLOPT_USERAGENT => BOT_CROSSREF_USER_AGENT]);
	$json = (string) @curl_exec($ch);
	unset($ch);
	if ($json) {
	  $oa = @json_decode($json);
	  if ($oa !== FALSE && isset($oa->best_oa_location)) {
		$best_location = $oa->best_oa_location;
		if ($best_location->host_type === 'publisher') {
		  // The best location is already linked to by the doi link
		  return 'publisher';
		}
		if (!isset($best_location->evidence)) return 'nothing';
		if (isset($oa->journal_name) && $oa->journal_name === "Cochrane Database of Systematic Reviews" ) {
		  report_warning("Ignored a OA from Cochrane Database of Systematic Reviews for DOI: " . echoable($doi)); // @codeCoverageIgnore
		  return 'unreliable';   // @codeCoverageIgnore
		}
		if (isset($best_location->url_for_landing_page)) {
		  $oa_url = (string) $best_location->url_for_landing_page; // Prefer to PDF
		} elseif (isset($best_location->url)) {   // @codeCoverageIgnoreStart
		  $oa_url = (string) $best_location->url;
		} else {
		  return 'nothing';   // @codeCoverageIgnoreEnd
		}
		if (!$oa_url) return 'nothing';

		if (stripos($oa_url, 'semanticscholar.org') !== FALSE) return 'semanticscholar';  // use API call instead (avoid blacklisting)
		if (stripos($oa_url, 'timetravel.mementoweb.org') !== FALSE) return 'mementoweb'; // Not good ones
		if (stripos($oa_url, 'citeseerx') !== FALSE) return 'citeseerx'; // blacklisted due to copyright concerns
		if (stripos($oa_url, 'palgraveconnect') !== FALSE) return 'palgraveconnect';
		if (stripos($oa_url, 'muse.jhu.edu') !== FALSE) return 'projectmuse'; // Same as DOI 99% of the time
		if (stripos($oa_url, 'lib.myilibrary.com') !== FALSE) return 'proquest'; // Rubbish
		if (stripos($oa_url, 'repository.upenn.edu') !== FALSE) return 'epository.upenn.edu'; // All links broken right now
		if ($this->get('url')) {
			if ($this->get('url') !== $oa_url) $this->get_identifiers_from_url($oa_url);  // Maybe we can get a new link type
			return 'have url';
		}
		preg_match("~^https?://([^\/]+)/~", $oa_url, $match);
		$host_name = (string) @$match[1]; // On very rare occasions we get a non-valid url, such as http://lib.myilibrary.com?id=281759
		if ($host_name == '') return 'no_slash';
		if (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $host_name) !== $host_name) return 'publisher';
		if (stripos($oa_url, 'bioone.org/doi') !== FALSE) return 'publisher';
		if (stripos($oa_url, 'gateway.isiknowledge.com') !== FALSE) return 'nothing';
		if (stripos($oa_url, 'orbit.dtu.dk/en/publications') !== FALSE) return 'nothing'; // Abstract only
		// Check if free location is already linked
		if(($this->has('pmc') &&
			 preg_match("~^https?://europepmc\.org/articles/pmc\d"
					  . "|^https?://www\.pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=\d"
					  . "|^https?://www\.ncbi\.nlm\.nih\.gov/(?:m/)?pmc/articles/PMC\d~", $oa_url))
		 ||($this->has('arxiv') && preg_match("~arxiv\.org/~", $oa_url))
		 ||($this->has('eprint') && preg_match("~arxiv\.org/~", $oa_url))
		 ||($this->has('citeseerx') && preg_match("~citeseerx\.ist\.psu\.edu~", $oa_url))) {
		   return 'have free';
		}
		// @codeCoverageIgnoreStart
		// These are not generally full-text.  Will probably never see
		if(($this->has('bibcode') &&
			preg_match(REGEXP_BIBCODE, urldecode($oa_url)))
		 ||($this->has('pmid') && preg_match("~^https?://www.ncbi.nlm.nih.gov/.*pubmed/~", $oa_url))) {
		   return 'probably not free';
		}
		// This should be found above when listed as location=publisher
		if($this->has('doi') &&
			preg_match("~^https?://doi\.library\.ubc\.ca/|^https?://(?:dx\.|)doi\.org/~", $oa_url)) {
			return 'publisher';
		}
		// @codeCoverageIgnoreEnd
		if (preg_match('~^https?://hdl\.handle\.net/(\d{2,}.*/.+)$~', $oa_url, $matches)) {  // Normalize Handle URLs
			$oa_url = 'https://hdl.handle.net/handle/' . $matches[1];
		}
		if ($this->has('hdl') ) {
		  if (stripos($oa_url, $this->get('hdl')) !== FALSE) return 'have free';
		  foreach (HANDLES_HOSTS as $hosts) {
			if (preg_match('~^https?://' . str_replace('.', '\.', $hosts) . '(/.+)$~', $oa_url, $matches)) {
			  $handle1 = $matches[1];
			  foreach (HANDLES_PATHS as $handle_path) {
				if (preg_match('~^' . $handle_path . '(.+)$~', $handle1)) return 'have free';
			  }
			}
		  }
		}

		if ($this->has('arxiv') ||
			$this->has('eprint') ||
			$this->has('biorxiv') ||
			$this->has('citeseerx') ||
			$this->has('pmc') ||
			$this->has('rfc') ||
			($this->has('doi') && $this->get('doi-access') === 'free') ||
			($this->has('jstor') && $this->get('jstor-access') === 'free') ||
			($this->has('osti') && $this->get('osti-access') === 'free') ||
			($this->has('ol') && $this->get('ol-access') === 'free')
		   ) {
		  return 'have free'; // do not add url if have OA already
		}
		// Double check URL against existing data
		if (!preg_match('~^(?:https?|ftp):\/\/\/?([^\/\.]+\.[^\/]+)\/~i', $oa_url, $matches)) {
		   report_minor_error(' OA database gave invalid URL: ' . echoable($oa_url)); // @codeCoverageIgnore
		   return 'nothing';   // @codeCoverageIgnore
		}
		$oa_hostname = $matches[1];
		if (($this->has('osti') && stripos($oa_hostname, 'osti.gov') !== FALSE) ||
			($this->has('ssrn') && stripos($oa_hostname, 'ssrn.com') !== FALSE) ||
			($this->has('jstor') && stripos($oa_hostname, 'jstor.org') !== FALSE) ||
			($this->has('pmid') && stripos($oa_hostname, 'nlm.nih.gov') !== FALSE) ||
			($this->has('jstor') && stripos($oa_hostname, 'jstor') !== FALSE) ||
			(stripos($oa_hostname, 'doi.org') !== FALSE)) {
		  return 'have free';
	   }
	   if (preg_match("~^https?://([^\/]+)/~", $oa_url . '/', $match)) {
		 $new_host_name = str_replace('www.', '', strtolower($match[1]));
		 foreach (ALL_URL_TYPES as $old_url) {
			if (preg_match("~^https?://([^\/]+)/~", $this->get($old_url), $match)) {
				$old_host_name = str_replace('www.', '', strtolower($match[1]));
				if ($old_host_name === $new_host_name) return 'have free';
			}
		 }
	   }
		$url_type = 'url';
		if ($this->has('chapter')) {
		  if ( preg_match('~^10\.\d+/9[\-\d]+_+\d+~', $doi) ||
			  (strpos($oa_url, 'eprints') !== FALSE) ||
			  (strpos($oa_url, 'chapter') !== FALSE)) {
			$url_type = 'chapter-url';
		  }
		}
		$has_url_already = $this->has($url_type);
		$this->add_if_new($url_type, $oa_url); // Will check for PMCs etc hidden in URL
		if ($this->has($url_type) && !$has_url_already) {  // The above line might have eaten the URL and upgraded it
		  $the_url = $this->get($url_type);
		  $ch = curl_init_array(1.5,[
					CURLOPT_HEADER => TRUE,
					CURLOPT_NOBODY => TRUE,
					CURLOPT_SSL_VERIFYHOST => 0,
					CURLOPT_SSL_VERIFYPEER => FALSE,
					CURLOPT_SSL_VERIFYSTATUS => FALSE,
					CURLOPT_URL => $the_url
					]);				 
		  $headers_test = @curl_exec($ch);
		  // @codeCoverageIgnoreStart
		  if($headers_test === FALSE) {
			$this->forget($url_type);
			report_warning("Open access URL was unreachable from Unpaywall API for doi: " . echoable($doi));
			return 'nothing';
		  }
		  // @codeCoverageIgnoreEnd
		  $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		  // @codeCoverageIgnoreStart
		  if($response_code > 400) {  // Generally 400 and below are okay, includes redirects too though
			$this->forget($url_type);
			report_warning("Open access URL gave response code " . (string) $response_code . " from oiDOI API for doi: " . echoable($doi));
			return 'nothing';
		  }
		  // @codeCoverageIgnoreEnd
		}
		return 'got one';
	  }
	}
	return 'nothing';
  }

  public function clean_google_books() : void {
	foreach (ALL_URL_TYPES as $url_type) {
	  if ($this->has($url_type)) {
	   $url = $this->get($url_type);
	   if (preg_match('~^(https?://(?:books|www)\.google\.[^/]+/books.+)\?$~',$url, $matches)) {
		 $url = $matches[1]; // trailing ?
	   }
	   if (preg_match('~^https?://books\.google\.[^/]+/booksid=(.+)$~', $url, $matches)) {
		 $url = 'https://books.google.com/books?id=' . $matches[1];
	   }
	   if (preg_match('~^https?://www\.google\.[^/]+/books\?(.+)$~', $url, $matches)) {
		 $url = 'https://books.google.com/books?' . $matches[1];
	   }
	   if (preg_match('~^https?://books\.google\.[^/\?]+\?id=(.+)$~', $url, $matches)) {
		 $url = 'https://books.google.com/books?id=' . $matches[1];
	   }
	   if (preg_match('~^https?://books\.google\.[^/]+\/books\/about\/[^/]+\.html$~', $url, $matches)) {
		 $url = '';
	   }
	   if (preg_match('~^https?://(?:books|www)\.google\.[^/]+\/books\/edition\/[a-zA-Z0-9\_]+\/?$~', $url, $matches)) {
		$url = '';
	   }
	   if (preg_match('~^https?://(?:books|www)\.google\.[^/]+\/books\?pg=P\S\S\S\S*$~', $url, $matches)) {
		 $url = '';
	   }
	   if (preg_match('~^https?://(?:books|www)\.google\.[^/]+\/books\/edition\/[a-zA-Z0-9\_]+\/([a-zA-Z0-9\-]+)\?(.+)$~', $url, $matches)) {
		 $url = 'https://books.google.com/books?id=' . $matches[1] . '&' . $matches[2];
	   }
	   if (preg_match('~^https?://books\.google\..*id\&\#61\;.*$~', $url, $matches)) {
		 $url = str_replace('&#61;', '=', $url);
	   }
	   if (preg_match('~^https?://books\.google\.[^/]+/(?:books|)\?[qv]id=(.+)$~', $url, $matches)) {
		 $url = 'https://books.google.com/books?id=' . $matches[1];
	   }
	   if (preg_match('~^https?://(?:|www\.)books\.google\.com/\?id=(.+)$~', $url, $matches)) {
		 $url = 'https://books.google.com/books?id=' . $matches[1];
	   }
	   if (preg_match('~^https?://www\.google\.[a-z\.]+/books\?id=(.+)$~', $url, $matches)) {
		 $url = 'https://books.google.com/books?id=' . $matches[1];
	   }
	   $this->set($url_type, $url);
	   if ($url === '') $this->forget($url_type);
	   $this->expand_by_google_books_inner($url_type, FALSE);
	   if (preg_match('~^https?://books\.google\.([^/]+)/books\?((?:isbn|vid)=.+)$~', $this->get($url_type), $matches)) {
		 if ($matches[1] !== 'com') {
		   $this->set($url_type, 'https://books.google.com/books?' . $matches[2]);
		 }
	   }
	  }
	}
  }

  public function expand_by_google_books() : void {
	$this->clean_google_books();
	if ($this->has('doi') && doi_active($this->get('doi'))) return;
	foreach (['url', 'chapterurl', 'chapter-url'] as $url_type) {
	   if ($this->expand_by_google_books_inner($url_type, TRUE)) return;
	}
	$this->expand_by_google_books_inner('', TRUE);
	return;
  }

  protected function expand_by_google_books_inner(string $url_type, bool $use_it) : bool {
	set_time_limit(120);
	if ($url_type) {
	  $url = $this->get($url_type);
	  if (!$url) return FALSE;
	  if (preg_match('~^https?:\/\/(?:www|books)\.google\.[a-zA-Z\.][a-zA-Z\.][a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?\/books\/(?:editions?|about)\/[^\/\/\s\<\|\{\}\>\]]+\/([^\? \]\[]+)\?([^\s\<\|\{\}\>\]]+)$~i', $url, $matches)) {
			$url = 'https://books.google.com/books?id='. $matches[1] . '&' . $matches[2];
			$this->set($url_type, $url);
	  } elseif (preg_match('~^https?:\/\/(?:www|books)\.google\.[a-zA-Z\.][a-zA-Z\.][a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?\/books\/(?:editions?|about)\/_\/([^\s\<\|\{\}\>\]\&\?\%]+)$~i', $url, $matches)) {
			$url = 'https://books.google.com/books?id='. $matches[1];
			$this->set($url_type, $url);
	  } elseif (preg_match('~^https?:\/\/(?:www|books)\.google\.[a-zA-Z\.][a-zA-Z\.][a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?\/books\/(?:editions?|about)\/_\/([^\s\<\|\{\}\>\]\&\?\%]+)?([^\s\<\|\{\}\>\]\?\%]+)$~i', $url, $matches)) {
			$url = 'https://books.google.com/books?id='. $matches[1] . '&' . $matches[2];
			$this->set($url_type, $url);
	  } elseif (preg_match('~^https?:\/\/(?:www|books)\.google\.[a-zA-Z\.][a-zA-Z\.][a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?[a-zA-Z\.]?\/books\/(?:editions?|about)\/[^\/\/\s\<\|\{\}\>\]]+\/([^\? \]\[\&\%]+)$~i', $url, $matches)) {
			$url = 'https://books.google.com/books?id='. $matches[1];
			$this->set($url_type, $url);
	  }
	  if (preg_match("~^https?://www\.google\.(?:[^\./]+)/books/(?:editions?|about)/_/(.+)$~", $url, $matches)) {
		$url = 'https://www.google.com/books/edition/_/'. $matches[1];
		$this->set($url_type, $url);
	  }
	  if (!preg_match("~(?:[Bb]ooks|[Ee]ncrypted)\.[Gg]oogle\.[\w\.]+/.*\bid=([\w\d\-]+)~", $url, $gid) &&
		  !preg_match("~\.[Gg]oogle\.com/books/edition/_/([a-zA-Z0-9]+)(?:\?.+|)$~", $url, $gid)) {
		 return FALSE; // Got nothing usable
	  }
	} else {
	  $url = '';
	  $isbn = $this->get('isbn');
	  if ($isbn) {
		$isbn = str_replace(array(" ", "-"), "", $isbn);
		if (preg_match("~[^0-9Xx]~", $isbn) === 1) $isbn='' ;
		if (strlen($isbn) !== 13 && strlen($isbn) !== 10) $isbn='' ;
	  }
	  if ($isbn) { // Try Books.Google.Com
		$google_book_url = 'https://www.google.com/search?tbo=p&tbm=bks&q=isbn:' . $isbn;
		$ch = curl_init_array(1.0,
				      [CURLOPT_URL => $google_book_url]);
		$google_content = (string) @curl_exec($ch);
		unset($ch);
		if ($google_content && preg_match_all('~[Bb]ooks\.[Gg]oogle\.com/books\?id=(............)&amp~', $google_content, $google_results)) {
		  $google_results = $google_results[1];
		  $google_results = array_unique($google_results);
		  if (count($google_results) === 1) {
			$gid = $google_results[0];
			$url = 'https://books.google.com/books?id=' . $gid;
		  }
		}
	  }
	}
	// Now we parse a Google Books URL
	if ($url && (preg_match("~[Bb]ooks\.[Gg]oogle\.[\w\.]+/.*\bid=([\w\d\-]+)~", $url, $gid) || preg_match("~[Ee]ncrypted\.[Gg]oogle\..+book.*\bid=([\w\d\-]+)~", $url, $gid))) {
	  $orig_book_url = $url;
	  $removed_redundant = 0;
	  $removed_parts = '';
	  normalize_google_books($url, $removed_redundant, $removed_parts, $gid);
	  if ($url !== $orig_book_url && $url_type && (strpos($url_type, 'url') !== FALSE)) {
		if ($removed_redundant > 1) { // http:// is counted as 1 parameter
		  report_forget(echoable($removed_parts));
		} else {
		  report_forget('Standardized Google Books URL');
		}
		$this->set($url_type, $url);
	  }
	  if ($use_it) $this->google_book_details($gid[1]);
	  return TRUE;
	}
	if (preg_match("~^(.+\.google\.com/books/edition/[^\/]+/)([a-zA-Z0-9]+)(\?.+|)$~", $url, $gid)) {
	  if ($url_type && $gid[3] === '?hl=en') {
		report_forget('Anonymized/Standardized/Denationalized Google Books URL');
		$this->set($url_type, $gid[1] . $gid[2]);
	  }
	  if ($use_it) $this->google_book_details($gid[2]);
	  return TRUE;
	}
	return FALSE;
  }

  protected function google_book_details(string $gid) : void {
	set_time_limit(120);
	$google_book_url = "https://books.google.com/books/feeds/volumes/" . $gid;
	$ch = curl_init_array(1.0,
			[CURLOPT_URL => $google_book_url]);
	$data = (string) @curl_exec($ch);
	unset($ch);
	if ($data === '') return;
	$simplified_xml = str_replace('http___//www.w3.org/2005/Atom', 'http://www.w3.org/2005/Atom',
	  str_replace(":", "___", $data));
	$xml = @simplexml_load_string($simplified_xml);
	if ($xml === FALSE) return;
	if ($xml->dc___title[1]) {
	  $this->add_if_new('title',
			   wikify_external_text(str_replace("___", ":", $xml->dc___title[0] . ": " . $xml->dc___title[1])));
	} else {
	  $this->add_if_new('title',  wikify_external_text(str_replace("___", ":", (string) $xml->title)));
	}
	$isbn = '';
	foreach ($xml->dc___identifier as $ident) {
	  if (preg_match("~isbn.*?([\d\-]{9}[\d\-]+)~i", (string) $ident, $match)) {
		$isbn = $match[1];
	  }
	}
	$this->add_if_new('isbn', $isbn);

	$i = 0;
	if ($this->blank(array_merge(FIRST_EDITOR_ALIASES, FIRST_AUTHOR_ALIASES, ['publisher', 'journal', 'magazine', 'periodical']))) { // Too many errors in gBook database to add to existing data. Only add if blank.
	  foreach ($xml->dc___creator as $author) {
		if (strtolower(str_replace("___", ":", (string) $author)) === "gale group") break;
		if (preg_match('~\d{4}~', (string) $author)) break; // Has a date in it
		$this->validate_and_add('author' . (string) ++$i, str_replace("___", ":", (string) $author), '', '', TRUE);
		if ($this->blank(['author' . (string) $i, 'first' . (string) $i, 'last' . (string) $i])) $i--; // It did not get added
	  }
	}

	// Possibly contains dud information on occasion - only add if data is good enough to have ISBN, and is probably a stand-alone book
	if (isset($xml->dc___publisher) && $isbn !== '' && $this->blank(['doi', 'pmid', 'pmc', 's2cid', 'arxiv', 'eprint', 'journal', 'magazine', 'newspaper', 'series'])) {
		$this->add_if_new('publisher', str_replace("___", ":", (string) $xml->dc___publisher));
	}

	$google_date = sanitize_string(trim( (string) $xml->dc___date )); // Google often sends us YYYY-MM
	if (substr_count($google_date, "-") === 1) {
		$date = @date_create($google_date);
		if ($date !== FALSE) {
		  $date = @date_format($date, "F Y");
		  if ($date !== FALSE) {
			$google_date = $date; // only now change data
		  }
		}
	}
	$google_date = tidy_date($google_date);
	$now = (integer) date("Y");
	// Some publishers give next year always for OLD stuff
	for ($i = 1; $i <= 30; $i++) {
		$next_year = (string) ($now + $i);
		if (strpos($google_date, $next_year) !== FALSE) return;
	}
	if ($this->has('isbn')) { // Assume this is recent, and any old date is bogus
	  if (preg_match('~1[0-8]\d\d~', $google_date)) return;
	  if (!preg_match('~[12]\d\d\d~', $google_date)) return;
	}
	$this->add_if_new('date', $google_date);
	// Don't add page count
	return;
  }

  ### parameter processing
  protected function parameter_names_to_lowercase() : void {
	if (empty($this->param)) return;
	$keys = array_keys($this->param);
	foreach ($keys as $the_key) {
	  $this->param[$the_key]->param = str_replace('duplicate_', 'DUPLICATE_', strtolower($this->param[$the_key]->param));
	}
  }

  protected function use_unnamed_params() : void {
	if (empty($this->param)) return;

	$param_occurrences = array();
	$duplicated_parameters = array();
	$duplicate_identical = array();

	foreach ($this->param as $pointer => $par) {
	  if ($par->param && isset($param_occurrences[$par->param])) {
		$duplicate_pos = $param_occurrences[$par->param];
		if ($par->val === '') {
		  $par->val = $this->param[$duplicate_pos]->val;
		} elseif ($this->param[$duplicate_pos]->val === '') {
		  $this->param[$duplicate_pos]->val = $par->val;
		}
		array_unshift($duplicated_parameters, $duplicate_pos);
		array_unshift($duplicate_identical, (mb_strtolower(trim($par->val)) === mb_strtolower(trim($this->param[$duplicate_pos]->val)))); // Drop duplicates that differ only by case
	  }
	  $param_occurrences[$par->param] = $pointer;
	}

	$n_dup_params = count($duplicated_parameters);

	for ($i = 0; $i < $n_dup_params; $i++) {
	  $the_dup = $duplicated_parameters[$i];
	  /** @psalm-suppress InvalidArrayOffset */
	  $is_same = $duplicate_identical[$i];
	  if ($is_same) {
		report_forget("Deleting identical duplicate of parameter: " .
		  echoable($this->param[$the_dup]->param));
		unset($this->param[$the_dup]);
	  } else {
		$this->param[$the_dup]->param = str_ireplace('DUPLICATE_DUPLICATE_', 'DUPLICATE_', 'DUPLICATE_' . $this->param[$the_dup]->param);
		report_modification("Marking duplicate parameter: " .
		  echoable($this->param[$the_dup]->param));
	  }
	}

	if ($this->blank('url')) {
	  $need_one = TRUE;
	  foreach ($this->param as $param_key => $p) {
		if ($need_one && !empty($p->param)) {
		  if (preg_match('~^\s*(https?://|www\.)\S+~', $p->param)) { # URL ending ~ xxx.com/?para=val
			$val = $p->val;
			$param = $p->param;
			$this->param[$param_key]->val = $param . '=' . $val;
			$this->param[$param_key]->param = 'url';
			$this->param[$param_key]->eq = ' = '; // Upgrade it to nicely spread out
			$need_one = FALSE;
			if (stripos($param . $val, 'books.google.') !== FALSE) {
			  $this->change_name_to('cite book');
			}
		  }
		}
	  }
	}
	$blank_count = 0;
	foreach ($this->param as &$p) { // Protect them from being overwritten
	  if (empty($p->param)) {
		$p->param = 'CITATION_BOT_PLACEHOLDER_EMPTY_' . (string) $blank_count++;
		$p->eq = ' = ';
	  }
	}
	unset ($p); // Destroy pointer to be safe
	foreach ($this->param as &$p) {
	  if (stripos($p->param, 'CITATION_BOT_PLACEHOLDER_EMPTY') === FALSE) continue;
	  $dat = $p->val;
	  $endnote_test = explode("\n%", "\n" . $dat);
	  if (isset($endnote_test[1])) {
		$endnote_authors = 0;
		foreach ($endnote_test as $endnote_line) {
		  $endnote_linetype = substr($endnote_line, 0, 1);
		  $endnote_datum = trim((string) substr($endnote_line, 2)); // Cast to string in case of FALSE
		  switch ($endnote_linetype) {
			case "A":
			  $this->add_if_new('author' . (string) ++$endnote_authors, format_author($endnote_datum));
			  $dat = trim(str_replace("\n%$endnote_line", "", "\n" . $dat));
			  $endnote_parameter = FALSE;
			  break;
			case "D": $endnote_parameter = "date";  break;
			case "I": $endnote_parameter = "publisher";  break;
			case "C": $endnote_parameter = "location";  break;
			case "J": $endnote_parameter = "journal";  break;
			case "N": $endnote_parameter = "issue";  break;
			case "P": $endnote_parameter = "pages";  break;
			case "T": $endnote_parameter = "title";  break;
			case "U": $endnote_parameter = "url";  break;
			case "V": $endnote_parameter = "volume";  break;
			case "@": // ISSN / ISBN
			  if (preg_match("~@\s*([\d\-]{9,}[\dxX])~", $endnote_line, $matches)) {
				$endnote_datum = $matches[1];
				$endnote_parameter = "isbn";
			  } elseif (preg_match("~@\s*(\d{4}\-?\d{3}[\dxX])~", $endnote_line, $matches)) {
				$endnote_datum = $matches[1];
				$endnote_parameter = "issn";
			  } else {
				$endnote_parameter = FALSE;
			  }
			  break;
			case "R": // Resource identifier... *may* be DOI but probably isn't always.
			  $matches = extract_doi($endnote_datum)[1];
			  if ($matches !== '') {
				$endnote_datum = $matches;
				$endnote_parameter = 'doi';
			  } else {
				$endnote_parameter = FALSE;
			  }
			  break;
			case "8": // Date
			case "0": // Citation type
			case "X": // Abstract
			case "M": // Object identifier
			  $dat = trim(str_replace("\n%$endnote_line", "", "\n" . $dat));
			  $endnote_parameter = FALSE;
			  break;
			default:
			  $endnote_parameter = FALSE;
		  }
		  if ($endnote_parameter) {
			$this->add_if_new($endnote_parameter, $endnote_datum);
			$dat = trim(str_replace("\n%$endnote_line", "", "\n$dat"));
		  }
		}
	  }

	  if (preg_match("~^TY\s+-\s+[A-Z]+~", $dat)) { // RIS formatted data:
		$this->expand_by_RIS($dat, TRUE);
	  }

	  $doi = extract_doi($dat);
	  if ($doi[1] !== '') {
		$this->add_if_new('doi', $doi[1]);
		$this->change_name_to('cite journal');
		$dat = str_replace($doi[0], '', $dat);
	  }

	  if (preg_match('~^(https?://|www\.)\S+~', $dat, $match)) { # Takes priority over more tentative matches
		report_add("Found URL floating in template; setting url");
		$url = $match[0];
		if ($this->blank('url')) {
		   $this->add_if_new('url', $url);
		} elseif ($this->blank(['archive-url','archiveurl']) &&
			   stripos($url, 'archive') !== FALSE) {
		   $this->add_if_new('archive-url', $url);
		}
		$dat = str_replace($url, '', $dat);
	  }

	  if (preg_match_all("~(\w+)\.?[:\-\s]*([^\s;:,.]+)[;.,]*~", $dat, $match)) { #vol/page abbrev.
		foreach ($match[0] as $i => $oMatch) {
		  switch (strtolower($match[1][$i])) {
			case "vol": case "v": case 'volume':
			  $matched_parameter = "volume";
			  break;
			case "no": case "number": case 'issue': case 'n': case 'issues':
			  $matched_parameter = "issue";
			  break;
			case 'pages': case 'pp': case 'pg': case 'pgs': case 'pag':
			  $matched_parameter = "pages";
			  break;
			case 'p':
			  $matched_parameter = "page";
			  break;
			default:
			  $matched_parameter = FALSE;
		  }
		  if ($matched_parameter) {
			$dat = trim(str_replace($oMatch, "", $dat));
			$this->add_if_new($matched_parameter, $match[2][$i]);
		  }
		}
	  }
	  // Match vol(iss):pp
	  if (preg_match("~(\d+)\s*(?:\((\d+)\))?\s*:\s*(\d+(?:\d\s*-\s*\d+))~", $dat, $match)) {
		$this->add_if_new('volume', $match[1]);
		if($match[2] > 2100 || $match[2] < 1500) { // if between 1500 and 2100, might be year or issue
			 $this->add_if_new('issue' , $match[2]);
		}
		$this->add_if_new('pages' , $match[3]);
		$dat = trim(str_replace($match[0], '', $dat));
	  }

	  $shortest = -1;
	  $test_dat = '';
	  $shortish = -1;
	  $comp = '';
	  $closest = '';
	  $parameter_list = array();
	  foreach (PARAMETER_LIST as $parameter) {
		if (strpos($parameter, '#') === FALSE) {
		  $parameter_list[] = $parameter;
		} else {
		  for ($i = 1; $i < 99; $i++) {
			$parameter_list[] = str_replace('#', (string) $i, $parameter);
		  }
		}
	  }
	  $parameter_list = array_reverse($parameter_list); // Longer things first

	  foreach ($parameter_list as $parameter) {
		if ((strpos($parameter, '#') === FALSE) && ($parameter === strtolower($parameter)) && preg_match('~^(' . preg_quote($parameter) . '(?: -|:| )\s*)~iu', $dat, $match)) { // Avoid adding "URL" instead of "url"
		  $parameter_value = trim(mb_substr($dat, mb_strlen($match[1])));
		  report_add("Found " . echoable($parameter) . " floating around in template; converted to parameter");
		  $this->add_if_new($parameter, $parameter_value);
		  $numSpaces = preg_match_all('~[\s]+~', $parameter_value);
		  if ($numSpaces < 4) {
			$dat = '';
			$p->val = '';
			break;
		  }
		}
		$para_len = strlen($parameter);
		if ($para_len < 3) continue; // minimum length to avoid FALSE positives
		$test_dat = preg_replace("~\d~", "_$0",
					preg_replace("~[ -+].*$~", "", substr(mb_strtolower($dat), 0, $para_len)));
		if (preg_match("~\d~", $parameter)) {
		  $lev = (float) levenshtein($test_dat, preg_replace("~\d~", "_$0", $parameter));
		} else {
		  $lev = (float) levenshtein($test_dat, $parameter);
		}
		if ($lev === 0.0) {
		  $closest = $parameter;
		  $shortest = 0.0;
		  break;
		} else {
		  $closest = '';
		}
		// Strict inequality as we want to favor the longest match possible
		if ($lev < $shortest || $shortest < 0) {
		  $comp = $closest;
		  $closest = $parameter;
		  $shortish = $shortest;
		  $shortest = $lev;
		} elseif ($lev < $shortish) {
		  // Keep track of the second shortest result, to ensure that our chosen parameter is an out and out winner
		  $shortish = $lev;
		  $comp = $parameter;
		}
	  }
	  // Deal with # values
	  if(preg_match('~\d+~', $dat, $match)) {
		$closest = str_replace('#', $match[0], $closest);
		$comp    = str_replace('#', $match[0], $comp);
	  } else {
		$closest = str_replace('#', "", $closest);
		$comp    = str_replace('#', "", $comp);
	  }
	  if ($shortest < 3
		 && strlen($test_dat) > 0
		 && ((float) similar_text($closest, $test_dat) / (float) strlen($test_dat)) > 0.4
		 && ((float) $shortest + 1.0 < $shortish  // No close competitor
			 || strlen($closest) > strlen($comp)
			)
	  ) {
		// remove leading spaces or hyphens (which may have been typoed for an equals)
		if (preg_match("~^[ -+]*(.+)~", (string) substr($dat, strlen($closest)), $match)) { // Cast FALSE to string
		  $this->add_if_new($closest, $match[1]/* . " [$shortest / $comp = $shortish]"*/);
		  $replace_pos = strrpos($dat, $match[1]) + strlen($match[1]);
		  $dat = trim(substr($dat, $replace_pos));
		}
	  } elseif (preg_match("~(?<!\d)(\d{10})(?!\d)~", str_replace(Array(" ", "-"), "", $dat), $match)) {
		$the_isbn = str_split($match[1]);
		preg_match('~' . $the_isbn[0] . '[ -]?' . $the_isbn[1] . '[ -]?'
					   . $the_isbn[2] . '[ -]?' . $the_isbn[3] . '[ -]?'
					   . $the_isbn[4] . '[ -]?' . $the_isbn[5] . '[ -]?'
					   . $the_isbn[6] . '[ -]?' . $the_isbn[7] . '[ -]?'
					   . $the_isbn[8] . '[ -]?' . $the_isbn[9] .
				   '~', $dat, $match); // Crazy to deal with dashes and spaces
		$this->add_if_new('isbn', $match[0]);
		$dat = trim(str_replace($match[0], '', $dat));
	  } elseif (preg_match("~(?<!\d)(\d{13})(?!\d)~", str_replace(Array(" ", "-"), "", $dat), $match)) {
		$the_isbn = str_split($match[1]);
		preg_match('~' . $the_isbn[0] . '[ -]?' . $the_isbn[1] . '[ -]?'
					   . $the_isbn[2] . '[ -]?' . $the_isbn[3] . '[ -]?'
					   . $the_isbn[4] . '[ -]?' . $the_isbn[5] . '[ -]?'
					   . $the_isbn[6] . '[ -]?' . $the_isbn[7] . '[ -]?'
					   . $the_isbn[8] . '[ -]?' . $the_isbn[9] . '[ -]?'
					   . $the_isbn[10]. '[ -]?' . $the_isbn[11]. '[ -]?'
					   . $the_isbn[12].
				   '~', $dat, $match); // Crazy to deal with dashes and spaces
		$this->add_if_new('isbn', $match[0]);
		$dat = trim(str_replace($match[0], '', $dat));
	  }
	  if (preg_match("~^access date[ :]+(.+)$~i", $dat, $match)) {
		if ($this->add_if_new('access-date', $match[1])) {
		  $dat = trim(str_replace($match[0], '', $dat));
		}
	  }
	  if (preg_match("~\(?(1[89]\d\d|20\d\d)[.,;\)]*~", $dat, $match)) { #YYYY
		if ($this->blank(['year', 'date'])) {
		  $this->add_if_new('year', $match[1]);
		  $dat = trim(str_replace($match[0], '', $dat));
		}
	  }
	  $p->val = trim($dat, " \t\0\x0B");
	}
	unset ($p); // Destroy pointer to be safe
	foreach ($this->param as $param_key => &$p) {
	  if (stripos($p->param, 'CITATION_BOT_PLACEHOLDER_EMPTY') === FALSE) continue;
	  $p->param = '';
	  $p->eq = '';
	  if($p->val === '') unset($this->param[$param_key]);
	}
	unset ($p); // Destroy pointer to be safe
  }

  protected function id_to_param(): void {
	set_time_limit(120);
	$id = $this->get('id');
	if (trim($id)) {
	  report_action("Trying to convert ID parameter to parameterized identifiers.");
	} else {
	  return;
	}
	if ($id === "<small></small>" || $id === "<small> </small>" || $id === ".") {
	  $this->forget('id');
	  return;
	}
	while (preg_match("~\b(PMID|DOI|ISBN|ISSN|ARXIV|LCCN|CiteSeerX|s2cid|PMC)[\s:]*(\d[\d\s\-][^\s\}\{\|,;]*)(?:[,;] )?~iu", $id, $match)) {
	  $the_type = mb_strtolower($match[1]);
	  $the_data = $match[2];
	  $the_all  = $match[0];
	  if ($the_type !== 'doi' && preg_match("~^([^\]\}\{\s\,\;\:\|\<\>]+)$~", $the_data, $matches)) {
		$the_data = $matches[1];
	  }
	  $this->add_if_new($the_type, $the_data);
	  $id = str_replace($the_all, '', $id);
	}
	if (preg_match_all('~' . sprintf(Self::PLACEHOLDER_TEXT, '(\d+)') . '~', $id, $matches)) {
	  for ($i = 0; $i < count($matches[1]); $i++) {
		$subtemplate = self::$all_templates[$matches[1][$i]];
		$subtemplate_name = $subtemplate->wikiname();
		switch($subtemplate_name) {
		  case "arxiv":
			if ($subtemplate->get('id')) {
			  $archive_parameter = trim($subtemplate->get('archive') ? $subtemplate->get('archive') . '/' : '');
			  $this->add_if_new('arxiv', $archive_parameter . $subtemplate->get('id'));
			} elseif (!is_null($subtemplate->param_with_index(1))) {
			  $this->add_if_new('arxiv', trim($subtemplate->param_value(0)) .
								"/" . trim($subtemplate->param_value(1)));
			} else {
			  $this->add_if_new('arxiv', $subtemplate->param_value(0));
			}
			$id = str_replace($matches[0][$i], '', $id);
			break;
		  case "asin":
		  case "oclc":
		  case "bibcode":
		  case "doi":
		  case "isbn":
		  case "issn":
		  case "jfm":
		  case "jstor":
		  case "lccn":
		  case "mr":
		  case "osti":
		  case "pmid":
		  case "pmc":
		  case "ssrn":
		  case "citeseerx":
		  case "s2cid":
		  case "hdl":
		  case "zbl":
		  case "ol":
		  case "lcc":
		  case "ismn":
		  case "biorxiv":

			// Specific checks for particular templates:
			if ($subtemplate_name === 'asin' && $subtemplate->has('country')) {
			  report_info("{{ASIN}} country parameter not supported: cannot convert.");
			  break;
			}
			if ($subtemplate_name === 'ol' && $subtemplate->has('author')) {
			  report_info("{{OL}} author parameter not supported: cannot convert.");
			  break;
			}
			if ($subtemplate_name === 'ol' && (stripos($subtemplate->parsed_text(), "ia:") !== FALSE)) {
			  report_info("{{OL}} ia: parameter not supported: cannot convert.");
			  break;
			}
			if ($subtemplate_name === 'jstor' && $subtemplate->has('sici') || $subtemplate->has('issn')) {
			  report_info("{{JSTOR}} named parameters are not supported: cannot convert.");
			  break;
			}
			if ($subtemplate_name === 'oclc' && !is_null($subtemplate->param_with_index(1))) {
			  report_info("{{OCLC}} has multiple parameters: cannot convert.");
			  report_info(echoable($subtemplate->parsed_text()));
			  break;
			}
			if ($subtemplate_name === 'issn' && !is_null($subtemplate->param_with_index(1))) {
			  report_info("{{ISSN}} has multiple parameters: cannot convert.");
			  report_info(echoable($subtemplate->parsed_text()));
			  break;
			}
			if ($subtemplate_name === 'ismn' && !is_null($subtemplate->param_with_index(1))) {
			  report_info("{{ISMN}} has multiple parameters: cannot convert.");
			  report_info(echoable($subtemplate->parsed_text()));
			  break;
			}
			if ($subtemplate_name === 'biorxiv' && !is_null($subtemplate->param_with_index(1))) {
			  report_info("{{biorxiv}} has multiple parameters: cannot convert.");
			  report_info(echoable($subtemplate->parsed_text()));
			  break;
			}
			if ($subtemplate_name === 'lcc') {
			  if (preg_match('~^[\d\-]+$~', $subtemplate->param_value(0))) {
				report_minor_error("Possible bad LCC template (did they mean LCCN) : " . echoable($subtemplate->param_value(0)));  // @codeCoverageIgnore
			  }
			  break;
			}

			// All tests okay; move identifier to suitable parameter
			$subtemplate_identifier = $subtemplate->has('id') ?
									  $subtemplate->get('id') :
									  $subtemplate->param_value(0);

			$did_it = $this->add_if_new($subtemplate_name, $subtemplate_identifier);
			if ($did_it) $id = str_replace($matches[0][$i], '', $id);
			break;

		  // TODO: Check if these have been added https://en.wikipedia.org/wiki/Template:Cite_journal
		  case "proquest": case "inist": case "gale": case "eric": case "naid": case "dtic":  case "project muse":
		  case "pii": case "ebscohost": case "libris": case "selibr":
		  case "cobiss": case "crosbi": case "euclid": case "federal register": case "jpno":
		  case "lancaster university library": case "listed invalid isbn": case "ncbibook2":
		  case "ncj": case "researchgatepub": case "university of south wales pure":
		  case "usgs index id": case "us patent": case "us trademark": case "zdb":
		  case "subscription required": case "ncid": case "wikileaks cable": case "idp":
		  case "bhl page": case "internet archive": case "youtube": case "nypl":
		  case "bnf": case "dnb-idn": case "nara catalog record": case "urn": case "viaf":
		  case "so-vid": case "philpapers": case "iccu": case "hathitrust": case "allmusic":
		  case "hal": case "icd11": case "coden": case "blcat": case "cobiss.bih":
		  case "cobiss.rs": case "cobiss.sr": case "harvtxt": case "mathnet": case "eissn":
		  case "ndljp": case "orcid": case "pq": case "sudoc": case "upc": case "ceeol":
		  case "nps history library": case "smaller": case "zenodo": case "!": case "hathitrust catalog":
		  case "eccc": case "ean": case "ethos": case "chmid": case "factiva": case "mesh":
		  case "dggs citation id": case "harvp": case "nla": case "catkey": case "hyphen":
		  case "mit libraries": case "epa national catalog": case "unt key": case "eram":
		  case "regreq": case "nobr": case "subscription": case "uspl": case "small":
		  case "rism": case "jan": case "nbsp": case "abbr": case "closed access": case "interp":
		  case "genbank": case "better source needed": case "free access": case "required subscription":
		  case "fahrplan-ch": case "incomplete short citation": case "music": case "bar-ads":
		  case "subscription or libraries": case "gallica": case "gnd": case "ncbibook":
		  case "spaces": case "ndash": case "dggs": case "self-published source": case "nobreak":
		  case "university of twente pure": case "mathscinet": case "discogs master": case "harv":
		  case "gbooks": case "gburl": // TODO - should use
		  case "isbnt": case "issn link": case "lccn8": // Assume not normal template for a reason
		  case "google books": // Usually done for fancy formatting and because already has title-link/url
		  case "url": // Untrustable: used by bozos
			break;
		  default:
			report_minor_error("No match found for subtemplate type: " . echoable($subtemplate_name));
		}
	  }
	}
	if (trim($id)) {
	  $this->set('id', $id);
	} else {
	  $this->forget('id');
	}
	if ($id === "<small></small>" || $id === "<small> </small>") {
	  $this->forget('id');
	  return;
	}
  }

  public function correct_param_mistakes() : void {
  // It will correct any that appear to be mistyped in minor templates
  if (empty($this->param)) return ;
  $mistake_corrections = array_values(COMMON_MISTAKES);
  $mistake_keys = array_keys(COMMON_MISTAKES);

  foreach ($this->param as $p) {
	if (strlen($p->param) > 0) {
	  $mistake_id = array_search($p->param, $mistake_keys);
	  if ($mistake_id) {
		$new = $mistake_corrections[$mistake_id];
		if ($this->blank($new)) {
		   $p->param = $new;
		   report_modification('replaced with ' . echoable($new) . ' (common mistakes list)');
		}
		continue;
	  }
	}
  }
	// Catch archive=url=http......
  foreach ($this->param as $p) {
	if (substr_count($p->val, "=") === 1 && !in_array($p->param, PARAMETER_LIST)) {
	  $param = $p->param;
	  $value = $p->val;
	  $equals = (int) strpos($value, '=');
	  $before = trim(substr($value, 0, $equals));
	  $after  = trim(substr($value, $equals+1));
	  $possible = $param . '-' . $before;
	  if (in_array($possible, PARAMETER_LIST)) {
		$p->param = $possible;
		$p->val   = $after;
	  }
	}
   }
}

  protected function correct_param_spelling() : void {
  // check each parameter name against the list of accepted names (loaded in expand.php).
  // It will correct any that appear to be mistyped.
  set_time_limit(120);
  if (empty($this->param)) return ;
  $parameter_list = PARAMETER_LIST;
  $parameter_dead = DEAD_PARAMETERS;
  $parameters_used=array();
  $mistake_corrections = array_values(COMMON_MISTAKES);
  $mistake_keys = array_keys(COMMON_MISTAKES);
  foreach ($this->param as $p) {
	$parameters_used[] = $p->param;
  }

  $parameter_list = array_diff($parameter_list, $mistake_keys); // This way it does not contain "URL", but only "url"
  $unused_parameters = array_diff($parameter_list, $parameters_used);

  foreach ($this->param as $p) {

	if ((strlen($p->param) > 0) &&
		!(in_array(preg_replace('~\d+~', '#', $p->param), $parameter_list) || in_array($p->param, $parameter_list)) && // Some parameters have actual numbers in them
		stripos($p->param, 'CITATION_BOT')===FALSE) {
	  if (trim($p->val) === '') {
		if (stripos($p->param, 'DUPLICATE_') === 0) {
		  report_forget("Dropping empty left-over duplicate parameter " . echoable($p->param) . " ");
		} else {
		  report_forget("Dropping empty unrecognized parameter " . echoable($p->param) . " ");
		}
		$this->quietly_forget($p->param);
		continue;
	  }

	  if (stripos($p->param, 'DUPLICATE_') === 0) {
		report_modification("Left-over duplicate parameter " . echoable($p->param) . " ");
	  } else {
		report_modification("Unrecognized parameter " . echoable($p->param) . " ");
	  }
	  $mistake_id = array_search($p->param, $mistake_keys);
	  if ($mistake_id) {
		// Check for common mistakes.  This will over-ride anything found by levenshtein: important for "editor1link" !-> "editor-link" (though this example is no longer relevant as of 2017)
		$p->param = $mistake_corrections[$mistake_id];
		report_modification('replaced with ' . echoable($mistake_corrections[$mistake_id]) . ' (common mistakes list)');
		continue;
	  }

	  $p->param = preg_replace('~author(\d+)-(la|fir)st~', "$2st$1", $p->param);
	  $p->param = preg_replace('~surname\-?_?(\d+)~', "last$1", $p->param);
	  $p->param = preg_replace('~(?:forename|initials?)\-?_?(\d+)~', "first$1", $p->param);

	  // Check the parameter list to find a likely replacement
	  $shortest = -1.0;
	  $closest = '';
	  $comp = '';
	  $shortish = -1.0;

	  if (preg_match('~\d+~', $p->param, $match)) { // Deal with # values
		 $param_number = $match[0];
	  } else {
		 $param_number = '#';
	  }
	  foreach ($unused_parameters as $parameter) {
		$parameter = str_replace('#', $param_number, $parameter);
		if (strpos($parameter, '#') !== FALSE) continue; // Do no use # items unless we have a number
		$lev = (float) levenshtein($p->param, $parameter, 5, 4, 6);
		// Strict inequality as we want to favor the longest match possible
		if ($lev < $shortest || $shortest < 0) {
		  $comp = $closest;
		  $closest = $parameter;
		  $shortish = $shortest;
		  $shortest = $lev;
		}
		// Keep track of the second-shortest result, to ensure that our chosen parameter is an out and out winner
		elseif ($lev < $shortish) {
		  $shortish = $lev;
		  $comp = $parameter;
		}
	  }
	  $str_len = strlen($p->param);

	  // Account for short words...
	  if ($str_len < 4) {
		$shortest *= ((float) $str_len / (float) (similar_text($p->param, $closest) ? similar_text($p->param, $closest) : 0.001));
		$shortish *= ((float) $str_len / (float) (similar_text($p->param, $comp) ? similar_text($p->param, $comp) : 0.001));
	  }

	  if (in_array($p->param, $parameter_dead)) {
		report_inline("Could not fix outdated " . echoable($p->param));
	  } elseif ($shortest < 12 && $shortest < $shortish) {
		$p->param = $closest;
		report_inline("replaced with $closest (likelihood " . (string)round(24.0 - $shortest,1) . "/24)"); // Scale arbitrarily re-based by adding 12 so users are more impressed by size of similarity
	  } else {
		$similarity = (float) similar_text($p->param, $closest) / (float) strlen($p->param);
		if ($similarity > 0.6) {
		  $p->param = $closest;
		  report_inline("replaced with $closest (similarity " . (string)(round(24.0 * $similarity, 1)) . "/24)"); // Scale arbitrarily re-based by multiplying by 2 so users are more impressed by size of similarity
		} else {
		  report_inline("could not be replaced with confidence. Please check the citation yourself.");
		}
	  }
	}
  }
}

  /** @return array<string> */
  public function initial_author_params() : array { return $this->initial_author_params; }
  public function had_initial_author() : bool {return (is_array($this->initial_author_params) && (sizeof($this->initial_author_params)>0));}

  protected function join_params() : string {
	if ((self::$name_list_style === NAME_LIST_STYLE_VANC) && !$this->had_initial_author() && !$this->had_initial_editor)
	{
	  $vanc_attribs = array('vauthors', 'veditors');
	  $vanc_fa = array('first', 'editor-first');
	  $vanc_la = array('last', 'editor-last');
	  foreach($vanc_attribs as $vanc_idx => $vanc_attrib) {
		$vanc_f = $vanc_fa[$vanc_idx];
		$vanc_l = $vanc_la[$vanc_idx];
		$v = '';
		if (!array_key_exists($vanc_attrib, $this->param) || !isset($this->param[$vanc_attrib]))
		{
		  $arr = array();
		  foreach($this->param as $k => $p) {
			if (str_starts_with($p->param, $vanc_f) || str_starts_with($p->param, $vanc_l)) {
			  $arr[$p->param] = $p->val;
			  unset($this->param[$k]);
			}
		  }

		  // Convert firstN/lastN to vauthors
		  $i = 1;
		  while (TRUE){
			$fv = '';
			$lv = '';
			$fk = $vanc_f.strval($i);
			$lk = $vanc_l.strval($i);
			if (array_key_exists($fk, $arr)) {
			  $tfk = $arr[$fk];
			  unset($arr[$fk]);
			  if (is_string($tfk) && (strlen($tfk) > 0)) {
				$fv = $tfk;
			  }
			  unset($tfk);
			}
			if (array_key_exists($lk, $arr)) {
			  $tlk = $arr[$lk];
			  unset($arr[$lk]);
			  if (is_string($tlk) && (strlen($tlk)>0)) {
				$lv = $tlk;
			  }
			  unset($tlk);
			}
			if (($fv === '') && ($lv === '')) {
			  break;
			}
			if ($v !== '') $v .= ', ';
			$v .= trim($lv . ' ' . substr($fv, 0, 1));
			$lve = explode(' ', $fv);
			if (array_key_exists(1, $lve)) $v .= substr($lve[1], 0, 1);
			$i++;
		  }
		}
		if ($v !== '')
		{
		  $p = new Parameter();
		  $p->parse_text(' '.$vanc_attrib.' = '.$v.' ');
		  $p->param = $vanc_attrib;
		  $p->val = $v;
		  array_push($this->param, $p);
		}
	  }
	}
	$ret = '';
	foreach($this->param as $p) {
	  $ret .= '|' . $p->parsed_text();
	}
	return $ret;
  }

  public function change_name_to(string $new_name, bool $rename_cite_book = TRUE, bool $rename_anything = FALSE) : void {
	if (strpos($this->get('doi'), '10.1093') !== FALSE && $this->wikiname() !== 'cite web') return;
	if (($new_name === 'cite document') && $this->blank('publisher')) return;
	if (bad_10_1093_doi($this->get('doi'))) return;
	foreach (WORK_ALIASES as $work) {
	  $worky = mb_strtolower($this->get($work));
	  if (preg_match(REGEXP_PLAIN_WIKILINK, $worky, $matches) || preg_match(REGEXP_PIPED_WIKILINK, $worky, $matches)) {
		$worky = $matches[1]; // Always the wikilink for easier standardization
	  }
	  if (in_array($worky, ARE_MANY_THINGS)) return;
	}
	if ($this->wikiname() === 'cite book' && !$this->blank_other_than_comments(CHAPTER_ALIASES_AND_SCRIPT)) {
	  return; // Changing away leads to error
	}
	if ($this->wikiname() === 'cite document' && in_array(strtolower($this->get('work')), ARE_WORKS)) {
	  return; // Things with DOIs that are works
	}
	$new_name = strtolower(trim($new_name)); // Match wikiname() output and cite book below
	if ($new_name === $this->wikiname()) return;
	if ($this->has('conference') && 'cite conference' === $this->wikiname()) return; // Need to lose conference first
	if ((in_array($this->wikiname(), TEMPLATES_WE_RENAME) && ($rename_cite_book || $this->wikiname() !== 'cite book')) ||
		($this->wikiname() === 'cite news' && $new_name === 'cite magazine') ||
		($rename_anything && in_array($new_name, TEMPLATES_WE_RENAME)) // In rare cases when we are positive that cite news is really cite journal
	) {
	  if ($new_name === 'cite arxiv') {
		if (!$this->blank(array_merge(['website','displayauthors','display-authors','access-date','accessdate',
						   'translator', 'translator1','translator1-first', 'translator1-given',
						   'translator1-last','translator1-surname', 'translator-first',
						   'translator-first1', 'translator-given', 'translator-given1', 'translator-last',
						   'translator-last1','translator-surname', 'translator-surname1',
						   'display-editors','displayeditors','url'], FIRST_EDITOR_ALIASES))) return; // Unsupported parameters
		$new_name = 'cite arXiv';  // Without the capital X is the alias
	  }
	  if (stripos($this->name, '#invoke:') !== FALSE) {
		  $this->name = str_ireplace('#invoke:', '', $this->name);
		  $invoke = '#invoke:';
	  } else {
		  $invoke = '';
	  }
	  preg_match("~^(\s*).*\b(\s*)$~", $this->name, $spacing);
	  if (substr($this->name,0,1) === 'c') {
		$this->name = $spacing[1] . $invoke . $new_name . $spacing[2];
	  } else {
		$this->name = $spacing[1] . $invoke . mb_ucfirst($new_name) . $spacing[2];
	  }
	  switch (strtolower($new_name)) {
		case 'cite journal':
		  $this->rename('eprint', 'arxiv');
		  $this->forget('class');
		  break;
	  }
	}
	if ($new_name === 'cite book' && $this->wikiname() === 'cite book') {
	  // all open-access versions of conference papers point to the paper itself
	  // not to the whole proceedings
	  // so we use chapter-url so that the template is well rendered afterwards
	  if ($this->should_url2chapter(TRUE)) {
		$this->rename('url', 'chapter-url');
	  } elseif (!$this->blank(['chapter-url','chapterurl']) && (str_i_same($this->get('chapter-url'), $this->get('url')))) {
		$this->forget('url');
	  } // otherwise they are different urls
	}
  }

  public function wikiname() : string {
	$name = trim(mb_strtolower(str_replace('_', ' ', $this->name)));
	$name = trim(mb_strtolower(str_replace('#invoke:', '', $name)));
	 // Treat the same since alias
	if ($name === 'cite work') $name = 'cite book';
	if ($name === 'cite chapter') $name = 'cite book';
	if ($name === 'cite newspaper') $name = 'cite news';
	if ($name === 'cite website') $name = 'cite web';
	if ($name === 'cite manual') $name = 'cite book';
	if ($name === 'cite paper') $name = 'cite journal';
	if ($name === 'cite contribution') $name = 'cite encyclopedia';
	if ($name === 'cite periodical') $name = 'cite magazine';
	if ($name === 'cite') $name = 'citation';
	return $name ;
  }

  public function should_be_processed() : bool {
	return in_array($this->wikiname(), TEMPLATES_WE_PROCESS);
  }

  public function tidy_parameter(string $param) : void {
	set_time_limit(120);
	// Note: Parameters are treated in alphabetical order, except where one
	// case necessarily continues from the previous (without a return).

	if (!$param) return;

	if ($param === 'postscript' && $this->wikiname() !== 'citation' &&
		preg_match('~^(?:# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #)\s*(?:# # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # #|)$~i', $this->get('postscript'))) {
	   // Remove misleading stuff -- comments of "NONE" etc mean nothing!
	   // Cannot call forget, since it will not remove items with comments in it
	   $key = $this->get_param_key('postscript');
	   /** @psalm-suppress PossiblyNullArrayOffset */
	   unset($this->param[$key]); // Key cannot be NULL because of get() call above
	   report_forget('Dropping postscript that is only a comment');
	   return;
	}

	if (mb_stripos($this->get($param), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE && $param !== 'ref') {
	  return;  // We let comments block the bot
	}
	if ($this->get($param) !== $this->get3($param)) return;
	if($this->has($param)) {
	  if (stripos($param, 'separator') === FALSE &&  // lone punctuation valid
		  stripos($param, 'postscript') === FALSE &&  // periods valid
		  stripos($param, 'url') === FALSE &&  // all characters are valid
		  stripos($param, 'quot') === FALSE &&  // someone might have formatted the quote
		  stripos($param, 'link') === FALSE &&  // inter-wiki links
		  $param !== 'trans-title' &&   // these can be very weird
		  (($param !== 'chapter' && $param !== 'title') || strlen($this->get($param)) > 4) // Avoid tiny titles that might be a smiley face
		 ) {
		$this->set($param, safe_preg_replace('~[\x{2000}-\x{200A}\x{00A0}\x{202F}\x{205F}\x{3000}]~u', ' ', $this->get($param))); // Non-standard spaces
		$this->set($param, safe_preg_replace('~[\t\n\r\0\x0B]~u', ' ', $this->get($param))); // tabs, linefeeds, null bytes
		$this->set($param, safe_preg_replace('~﻿~u', ' ', $this->get($param)));
		$this->set($param, safe_preg_replace('~  +~u', ' ', $this->get($param))); // multiple spaces
		$this->set($param, safe_preg_replace('~(?<!:)[:,]$~u', '', $this->get($param)));   // Remove trailing commas, colons, but not semi-colons--They are HTML encoding stuff
		$this->set($param, safe_preg_replace('~^[:,;](?!:)~u', '', $this->get($param)));  // Remove leading commas, colons, and semi-colons
		$this->set($param, safe_preg_replace('~^\=+\s*(?![^a-zA-Z0-9\[\'\"])~u', '', $this->get($param))); // Remove leading ='s sign if in front of letter or number
		$this->set($param, safe_preg_replace('~&#x2010;~u', '-', $this->get($param)));
		$this->set($param, safe_preg_replace('~\x{2010}~u', '-', $this->get($param)));
		$this->set($param, safe_preg_replace('~&#x2013;~u', '&ndash;', $this->get($param)));
		$this->set($param, safe_preg_replace('~&#x2014;~u', '&mdash;', $this->get($param)));
		$this->set($param, safe_preg_replace('~&#x00026;~u', '&', $this->get($param)));
		$this->set($param, safe_preg_replace('~&#8203;~u', ' ', $this->get($param)));
		$this->set($param, safe_preg_replace('~  +~u', ' ', $this->get($param))); // multiple spaces
		$this->set($param, safe_preg_replace('~(?<!\&)&[Aa]mp;(?!&)~u', '&', $this->get($param))); // &Amp; => & but not if next character is & or previous character is ;

		// Remove final semi-colon from a few items
		if ((in_array($param, ['date', 'year', 'location', 'publisher', 'issue', 'number', 'page', 'pages', 'pp', 'p', 'volume']) ||
		   in_array($param, FLATTENED_AUTHOR_PARAMETERS))
		  && strpos($this->get($param), '&') === FALSE) {
		 $this->set($param, preg_replace('~;$~u', '', $this->get($param)));
		}

		// Remove quotes, if only at start and end -- In the case of title, leave them unless they are messed up
		// Do not do titles of non-books, since they sometimes have quotes in the actual one
		if (($param !== 'title' || $this->has('chapter')) && preg_match("~^([\'\"]+)([^\'\"]+)([\'\"]+)$~u", $this->get($param), $matches)) {
		  if (($matches[1] !== $matches[3]) || ($param !== 'title' && $param !== 'chapter' && $param !== 'publisher')) {
			$this->set($param, $matches[2]);
		 }
		}
		if (preg_match("~^\'\'\'([^\']+)\'\'\'$~u", $this->get($param), $matches)) {
		   $this->set($param, $matches[1]); // Remove bold
		}
		$this->set($param, safe_preg_replace('~\x{00AD}~u', '', $this->get($param))); // Remove soft hyphen
	  }
	  if (stripos($param, 'separator') === FALSE &&  // punctuation valid
		  stripos($param, 'url') === FALSE &&  // everything is valid
		  stripos($param, 'link') === FALSE &&  // inter-wiki links
		  $param !== 'trans-title'  // these can be very weird
		 ) {
		// Non-breaking spaces at ends
		$this->set($param, trim($this->get($param), " \t\n\r\0\x0B"));
		$this->set($param, safe_preg_replace("~^\xE2\x80\x8B~", " ", $this->get($param))); // Zero-width at start
		$this->set($param, safe_preg_replace("~\xE2\x80\x8B$~", " ", $this->get($param))); // Zero-width at end
		$this->set($param, safe_preg_replace("~\x{200B}~u", " ", $this->get($param))); //Zero-width anywhere
		while (preg_match("~^&nbsp;(.+)$~u", $this->get($param), $matches)) {
		  $this->set($param, trim($matches[1], " \t\n\r\0\x0B"));
		}
		while (preg_match("~^(.+)&nbsp;$~u", $this->get($param), $matches)) {
		  $this->set($param, trim($matches[1], " \t\n\r\0\x0B"));
		}
	  }
	}
	if (in_array(strtolower($param), ['series', 'journal', 'newspaper']) && $this->has($param)) {
	  $this->set($param, safe_preg_replace('~[™|®]$~u', '', $this->get($param))); // remove trailing TM/(R)
	}
	if (in_array(str_replace(array('-','0','1','2','3','4','5','6','7','8','9'), '', strtolower($param)), ['authorlink', 'chapterlink', 'contributorlink',
				 'editorlink', 'episodelink', 'interviewerlink', 'inventorlink', 'serieslink',
				 'subjectlink', 'titlelink', 'translatorlink']) &&
		$this->has($param) && (stripos($this->get($param), 'http') === FALSE) && (stripos($this->get($param), 'PLACEHOLDER') === FALSE)) {
	  $this->set($param, safe_preg_replace('~_~u', ' ', $this->get($param)));
	}

	if (!preg_match('~^(\D+)(\d*)(\D*)$~', $param, $pmatch)) {
	  report_minor_error("Unrecognized parameter name format in " . echoable($param)); // @codeCoverageIgnore
	  return;   // @codeCoverageIgnore
	} else {
	  // Put "odd ones" in "normalized" order - be careful down below about $param vs $pmatch values
	  if (in_array(strtolower($param), ['s2cid','s2cid-access'])) {
		$pmatch = [$param, $param, '', ''];
	  }
	  if (in_array(strtolower($pmatch[3]), ['-first', '-last', '-surname', '-given', 'given', '-link', 'link', '-mask', 'mask'])) {
		$pmatch = [$param, $pmatch[1] . $pmatch[3], $pmatch[2], ''];
	  }
	  if ($pmatch[3] !== '') {
		report_minor_error("Unrecognized parameter name format in " . echoable($param));  // @codeCoverageIgnore
		return;   // @codeCoverageIgnore
	  }
	  switch ($pmatch[1]) {
		// Parameters are listed mostly alphabetically, though those with numerical content are grouped under "year"

		case 'accessdate':
		case 'access-date':
		  if ($this->has($param) && $this->blank(ALL_URL_TYPES))
		  {
			$this->forget($param);
		  }
		  return;

		case 'agency':
		  if (in_array($this->get('agency'), ['United States Food and Drug Administration',
											  'Surgeon General of the United States',
											  'California Department of Public Health'])
			   &&
			  in_array($this->get('publisher'),
				['United States Department of Health and Human Services', 'California Tobacco Control Program', ''])) {
			$this->forget('publisher');
			$this->rename('agency', 'publisher'); // A single user messed this up on a lot of pages with "agency"
			return;
		  }
		  // Undo some bad bot/human edits
		  if ($this->blank(WORK_ALIASES) &&
			  in_array(strtolower(str_replace(array('[', ']', '.'), '', $this->get($param))),
					   ['reuters', 'associated press', 'united press international', 'yonhap news agency', 'official charts company',
						'philippine news agency', 'philippine information agency', 'ap', 'ap news', 'associated press news'])) {
			$the_url = '';
			foreach (ALL_URL_TYPES as $thingy) {
			  $the_url .= $this->get($thingy);
			}
			if (stripos($the_url, 'reuters.com') !== FALSE || stripos($the_url, 'apnews.com') !== FALSE ||
				stripos($the_url, 'yna.co.kr') !== FALSE || stripos($the_url, 'upi.com') !== FALSE ||
				stripos($the_url, 'officialcharts.com') !== FALSE || stripos($the_url, 'pia.gov.ph') !== FALSE ||
				stripos($the_url, 'pna.gov.ph') !== FALSE) {
			   $this->rename($param, 'work');
			   if (stripos($the_url, 'apnews.com') !== FALSE) {
				 if ($this->get('work') === 'AP'){
				   $this->set('work', 'AP News');
				 } elseif ($this->get('work') === 'Associated Press'){
				   $this->set('work', 'Associated Press News');
				 } elseif ($this->get('work') === '[[Associated Press]]'){
				   $this->set('work', '[[Associated Press News]]');
				 } elseif ($this->get('work') === '[[AP]]'){
				   $this->set('work', '[[AP News]]');
				 }
			   }
			}
		  }

		  return;

		case 'arxiv':
		  if ($this->has($param) && $this->wikiname() === 'cite web') {
			$this->change_name_to('cite arxiv');
		  }
		  return;

		case 'author':
		  $the_author = $this->get($param);
		  if ($this->blank('agency') && in_array(strtolower($the_author), ['associated press', 'reuters'])) {
			$this->rename('author' . $pmatch[2], 'agency');
			if ($pmatch[2] === '1' || $pmatch[2] === '') {
			  $this->forget('author-link');
			  $this->forget('authorlink');
			  $this->forget('author-link1');
			  $this->forget('authorlink1');
			  $this->forget('author1-link');
			} else {
			  $this->forget('author-link' . $pmatch[2]);
			  $this->forget('authorlink' . $pmatch[2]);
			  $this->forget('author' . $pmatch[2] . '-link');
			}
			for ($looper = (int) $pmatch[2] + 1; $looper <= 100 ; $looper++) {
			  $old = (string) $looper;
			  $new = (string) ($looper-1);
			  $this->rename('author'. $old, 'author' . $new);
			  $this->rename('last'  . $old, 'last'   . $new);
			  $this->rename('first' . $old, 'first'  . $new);
			  $this->rename('author-link' . $old, 'author-link' . $new);
			  $this->rename('authorlink' . $old, 'authorlink' . $new);
			  $this->rename('author' . $old . '-link', 'author' . $new . '-link');
			}
			return;
		  }
		  // Convert authorX to lastX, if firstX is set
		  if ($pmatch[2] && $this->has('first' . $pmatch[2]) && $this->blank('last' . $pmatch[2])) {
			$this->rename('author' . $pmatch[2], 'last' . $pmatch[2]);
			$pmatch[1] = 'last';
			// Comment out since "never used"  $param = 'last' . $pmatch[2];
			return;
		  }
		  // No return here
		case 'authors':
		  if ($this->has('author') && $this->has('authors')) $this->rename('author', 'DUPLICATE_authors');
		  if (!$this->initial_author_params) $this->handle_et_al();
		  // Continue from authors without break
		case 'last': case 'surname':
			if (!$this->initial_author_params) {
			  if ($pmatch[2]) {
				$translator_regexp = "~\b([Tt]r(ans(lat...?(by)?)?)?\.?)\s([\w\p{L}\p{M}\s]+)$~u";
				if (preg_match($translator_regexp, trim($this->get($param)), $match)) {
				  $others = trim("$match[1] $match[5]");
				  if ($this->has('others')) {
					$this->append_to('others', '; ' . $others);
				  } else {
					$this->set('others', $others);
				  }
				  $this->set($param, trim(preg_replace($translator_regexp, "", $this->get($param))));
				}
			  }
			}
			if ($pmatch[2] && $pmatch[1] === 'last') {
			  $the_author = $this->get($param);
			  if (substr($the_author, 0, 2) === '[[' &&
				 substr($the_author,   -2) === ']]' &&
				 mb_substr_count($the_author, '[[') === 1 &&
				 mb_substr_count($the_author, ']]') === 1 &&
				 strpos($the_author, 'CITATION_BOT') === FALSE &&
				 strpos($the_author, '{{!}}') === FALSE) { // Has a normal wikilink
				   $did_something = FALSE;
				   if (preg_match(REGEXP_PLAIN_WIKILINK_ONLY, $the_author, $matches)) {
					$this->set($param, $matches[1]);
					$this->add_if_new('author' . $pmatch[2] . '-link', $matches[1]);
					$did_something = TRUE;
				   } elseif (preg_match(REGEXP_PIPED_WIKILINK_ONLY, $the_author, $matches)) {
					$this->set($param, $matches[2]);
					$this->add_if_new('author' . $pmatch[2] . '-link', $matches[1]);
					$did_something = TRUE;
				  }
				  if ($pmatch[2] === '1' && $this->has('first')) {
					$this->rename('first', 'first1');
				  }
				  if ($did_something && strpos($this->get('first' . $pmatch[2]), '[') !==FALSE) { // Clean up links in first names
					$the_author = $this->get('first' . $pmatch[2]);
					if (preg_match(REGEXP_PLAIN_WIKILINK_ONLY, $the_author, $matches)) {
					  $this->set('first' . $pmatch[2], $matches[1]);
					} elseif (preg_match(REGEXP_PIPED_WIKILINK_ONLY, $the_author, $matches)) {
					  $this->set('first' . $pmatch[2], $matches[2]);
					}
				  }
			  }
			}
			if (!$pmatch[2] && $pmatch[1] === 'last' && !$this->blank(['first1', 'first2', 'last2'])) {
			  if ($this->blank('last1'))  $this->rename('last', 'last1');
			  if ($this->blank('first1')) $this->rename('first', 'first1');
			}
			return;

		case 'first':
			if (!$pmatch[2] && $pmatch[1] === 'first' && !$this->blank(['last1', 'first2', 'last2'])) {
			  if ($this->blank('last1'))  $this->rename('last', 'last1');
			  if ($this->blank('first1')) $this->rename('first', 'first1');
			}
			return;

		case 'bibcode':
		  if ($this->blank($param)) return;
		  $bibcode_journal = (string) substr($this->get($param), 4);
		  if ($bibcode_journal === '') return; // bad bibcodes would not have four characters, use ==, since it might be "" or FALSE depending upon error/PHP version
		  foreach (NON_JOURNAL_BIBCODES as $exception) {
			if (substr($bibcode_journal, 0, strlen($exception)) === $exception) return;
		  }
		  if (strpos($this->get($param), 'book') !== FALSE) {
			$this->change_name_to('cite book', FALSE);
		  } else {
			$this->change_name_to('cite journal', FALSE);
		  }
		  return;

		case 'chapter':
		  if ($this->has('chapter')) {
			if (str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');
			if (str_equivalent($this->get('chapter'), $this->get('title'))) {
			  $this->forget('chapter');
			  return; // Nonsense to have both.
			}
			if ('Cultural Advice' === $this->get('chapter') &&
				strpos($this->get('url') . $this->get('chapter-url'), 'anu.edu.au') !== FALSE) {
			  $this->forget('chapter');
			  return;
			}
		  }
		  if ($this->has('chapter') && $this->blank(['journal', 'bibcode', 'jstor', 'pmid'])) {
			$this->change_name_to('cite book');
		  }
		  return;

		case 'class':
		   if ($this->blank('class')) {
			  if ($this->wikiname() !== 'cite arxiv' && !$this->blank(array('doi', 'pmid', 'pmc', 'journal', 'series', 'isbn'))) {
				 $this->forget('class');
			  }
		   }
		   return;

		case 'date':
		  if ($this->blank('date') && $this->has('year')) $this->forget('date');
		  return;

		case 'month':
		  if ($this->blank($param)) {
			$this->forget($param);
			return;
		  }
		  if ($this->has('date')) {
			 if (stripos($this->get('date'), $this->get($param)) !== FALSE) {  // Date has month already
				$this->forget('month');
				$this->forget('day');
				return;
			 }
		  }
		  if ($this->has('date') || $this->blank('year')) return;
		  $day = $this->get('day');
		  $month = $this->get('month');
		  $year = $this->get('year');
		  if ($day === '' && preg_match('~^([a-zA-Z]+) 0*(\d+)$~', $month, $matches)) {
			 $day = $matches[2];
			 $month = $matches[1];
		  }
		  if ($day === '' && preg_match('~^0*(\d+) ([a-zA-Z]+)$~', $month, $matches)) {
			 $day = $matches[1];
			 $month = $matches[2];
		  }
		  if (!preg_match('~^\d*$~', $day)) return;
		  if (!preg_match('~^[a-zA-Z\–\-]+$~u', $month)) return;
		  if (!preg_match('~^\d{4}$~', $year)) return;
		  $new_date =  trim($day . ' ' . $month . ' ' . $year);
		  $this->forget('day');
		  $this->rename($param, 'date', $new_date);
		  return;

		case 'dead-url': case 'deadurl':
		  $the_data = mb_strtolower($this->get($param));
		  if (in_array($the_data, ['y', 'yes', 'dead', 'si', 'sì', 'ja'])) {
			$this->rename($param, 'url-status', 'dead');
			$this->forget($param);
		  } elseif (in_array($the_data, ['n', 'no', 'live', 'alive'])) {
			$this->rename($param, 'url-status', 'live');
			$this->forget($param);
		  } elseif (in_array($the_data, ['', 'bot: unknown'])) {
			$this->forget($param);
		  } elseif (in_array($the_data, ['unfit'])) {
			$this->rename($param, 'url-status');
			$this->forget($param);
		  }
		  return;

		case 'url-status':
		  $the_data = mb_strtolower($this->get($param));
		  if (in_array($the_data, ['y', 'yes', 'si', 'sì'])) {
			$this->set($param, 'dead');
		  } elseif (in_array($the_data, ['n', 'no', 'alive'])) {
			$this->set($param, 'live');
		  }
		  return;

		case 'df':
		  if ($this->blank('df')) $this->forget('df');
		  return;

		case 'last-author-amp': case 'lastauthoramp':
		  $the_data = mb_strtolower($this->get($param));
		  if (in_array($the_data, ['n', 'no', 'false'])) {
			$this->forget($param);
			return;
		  }
		  if (in_array($the_data, ['y', 'yes', 'true'])) {
			$this->rename($param, 'name-list-style', 'amp');
			$this->forget($param);
		  }
		  return;

		case 'doi-access':
		if ($this->blank('doi') && $this->has('doi-access')) {
		   $this->forget('doi-access');
		}
		return;

		case 'doi':
		  $doi = $this->get($param);
		  if (!$doi) return;
		  if (preg_match('~^(10\.[^\/]+\/)\/(.+)$~', $doi, $matches)) {
			$try = $matches[1] . $matches[2];
			if (doi_works($try)) {
			   $doi = $try;
			   $this->set('doi', $try);
			}
		  }
		  if ($doi === '10.1267/science.040579197') {
			// This is a bogus DOI from the PMID example file
			$this->forget('doi');
			return;
		  }
		  if ($doi === '10.5284/1000184') {
			// This is a DOI for an entire database, not anything within it
			$this->forget('doi');
			return;
		  }
		  if (stripos($doi, '10.48550/arXiv.') === 0) {
			 $pos0 = strtolower(substr($doi, 15));
			 $pos1 = strtolower($this->get('eprint'));
			 $pos2 = strtolower($this->get('arxiv'));
			 if ($pos0 === $pos1 || $pos0 === $pos2) {
			   $this->forget('doi');
			   return;
			 }
		  }
		  if (substr($doi, 0, 8) === '10.5555/') { // Test DOI prefix. NEVER will work
			$this->forget('doi');
			if ($this->blank('url')) {
			  /** @psalm-taint-escape ssrf */
			  $test_url = 'https://plants.jstor.org/stable/' . $doi;
			  $ch = curl_init_array(1.5,
						[CURLOPT_URL => $test_url]);
			  @curl_exec($ch);
			  $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
			  unset($ch);
			  if ($httpCode === 200) $this->add_if_new('url', $test_url);
			}
			return;
		  }
		  if (!doi_works($doi) && (stripos($doi, '10.3316/') === 0)) {
			if ($this->has('url') || $this->has('pmid') || $this->has('jstor') || $this->has('pmc')) {
			  $this->forget('doi');
			  return;
			}
		  }
		  if (!doi_works($doi) && (stripos($doi, '10.1043/0003-3219(') === 0)) {
			$this->forget('doi'); // Per-email. The Angle Orthodontist will NEVER do these, since they have <> and [] in them
			return;
		  }
		  if (doi_works($doi) === NULL) { // This is super slow and rare
		   // @codeCoverageIgnoreStart
		   if ($this->has('pmc') || $this->has('pmid')) {
			if (stripos($doi, '10.1210/me.') === 0 || stripos($doi, '10.1210/jc.') === 0 ||
				stripos($doi, '10.1210/er.') === 0 || stripos($doi, '10.1210/en.') === 0 ||
				stripos($doi, '10.1128/.61') === 0 || stripos($doi, '10.1379/1466-1268') === 0) {
			  $this->forget('doi'); // Need updated and replaced
			  return;
			}
		   }
		   if (stripos($doi, '10.1258/jrsm.') === 0 || stripos($doi, '10.1525/as.') === 0 ||
			   stripos($doi, '10.1525/sp.') === 0 || stripos($doi, '10.1067/mva.') === 0) {
			  $doi = $this->get('doi');
			  $this->set('doi', ''); // Need updated and replaced
			  $this->get_doi_from_crossref();
			  if (doi_works($this->get('doi')) !== TRUE) {
				$this->set('doi', $doi);
			  }
			  return;
		   }
		   if (stripos($doi, '10.2979/new.') === 0 || stripos($doi, '10.2979/FSR.') === 0 ||
			   stripos($doi, '10.2979/NWS') === 0  || stripos($doi, '10.1353/nwsa.') === 0 ) {
			 if ($this->has('url') ||$this->has('jstor')) {
			  $this->forget('doi');// Dead/Jstor/Muse
			  return;
			 }
		   }
		   if (stripos($doi, '10.1093/em/') === 0) {
			 if (preg_match('~^10\.1093/em/(\d+)(\.\d+\.\d+)$~', $doi, $matches)) {
			   $romed = numberToRomanRepresentation((int) $matches[1]) . $matches[2];
			   $try_doi = '10.1093/earlyj/' . $romed;
			   if (doi_works($try_doi) === TRUE) {
				 $this->set('doi', $try_doi);
				 return;
			   }
			   $try_doi = '10.1093/em/' . $romed;
			   if (doi_works($try_doi) === TRUE) {
				 $this->set('doi', $try_doi);
				 return;
			   }
			 }
			 return;
		   }
		   if (strpos($doi, '10.2310/') === 0) {
			 $this->set('doi', '');
			 $this->get_doi_from_crossref();
			 if ($this->blank('doi')) {
				$this->set('doi', $doi);
			 }
			 return;
		   }
		   if (stripos($doi, '10.1093/ml/') === 0) {
			 if (preg_match('~^10\.1093/ml/(\d+)(\.\d+\.\d+)$~', $doi, $matches)) {
			   $romed = numberToRomanRepresentation((int) $matches[1]) . $matches[2];
			   $try_doi = '10.1093/ml/' . $romed;
			   if (doi_works($try_doi) === TRUE) {
				 $this->set('doi', $try_doi);
			   }
			 }
			 return;
		   }
		   // @codeCoverageIgnoreEnd
		  }
		  if (!doi_works($doi)) {
			$this->verify_doi();
			$doi = $this->get($param);
			if ($doi === '') return;
		  }
		  if (!doi_works($doi) && strpos($doi, '10.1111/j.1475-4983.' . $this->year()) === 0) {
			$this->forget('doi');  // Special Papers in Palaeontology - they do not work
			return;
		  }
		  if (doi_works($doi) !== TRUE && strpos($doi, '10.2277/') === 0) {
			$this->forget('doi');  // contentdirections.com DOI provider is gone
			return;
		  }
		  if (doi_works($doi) !== TRUE && strpos($doi, '10.1336/') === 0 && $this->has('isbn')) {
			$this->forget('doi');  // contentdirections.com DOI provider is gone
			return;
		  }
		  if (doi_works($doi) !== TRUE && strpos($doi, '10.1036/') === 0 && $this->has('isbn')) {
			$this->forget('doi');  // contentdirections.com DOI provider is gone
			return;
		  }
		  if (!doi_works($doi)) {
			$doi = sanitize_doi($doi);
			$this->set($param, $doi);
		  }
		  if (!doi_works($doi)) {
			if (preg_match('~^10.1093\/oi\/authority\.\d{10,}$~', $doi) &&
			  preg_match('~(?:oxfordreference\.com|oxfordindex\.oup\.com)\/[^\/]+\/10.1093\/oi\/authority\.\d{10,}~', $this->get('url'))) {
			 $this->forget('doi');
			 return;
			} elseif (preg_match('~^10\.1093\/law\:epil\/9780199231690\/law\-9780199231690~', $doi) &&
			  preg_match('~ouplaw.com\/view\/10\.1093/law\:epil\/9780199231690\/law\-9780199231690~', $this->get('url'))) {
			$this->forget('doi');
			return;
			}
		  }
		  if (stripos($doi, '10.1093/law:epil') === 0 || stripos($doi, '10.1093/oi/authority') === 0) {
			return;
		  }
		  if (!preg_match(REGEXP_DOI_ISSN_ONLY, $doi) && doi_works($doi)) {
		   if(!in_array(strtolower($doi), NON_JOURNAL_DOIS) &&
			  (strpos($doi, '10.14344/') === FALSE) &&
			  (stripos($doi, '10.7289/V') === FALSE) &&
			  (stripos($doi, '10.7282/') === FALSE) &&
			  (stripos($doi, '10.5962/bhl.title.') === FALSE)) {
			$the_journal = $this->get('journal') . $this->get('work') . $this->get('periodical');
			if (str_replace(NON_JOURNALS, '', $the_journal) === $the_journal && !$this->blank(WORK_ALIASES)) {
			  $this->change_name_to('cite journal', FALSE);
			}
		   }
		  }
		  if ($this->blank('jstor') && preg_match('~^10\.2307/(\d+)$~', $this->get_without_comments_and_placeholders('doi'))) {
			$this->add_if_new('jstor', substr($this->get_without_comments_and_placeholders('doi'), 8));
		  }
		  if ($this->wikiname() === 'cite arxiv') $this->change_name_to('cite journal');
		  if (preg_match('~^10\.3897/zookeys\.(\d+)\.\d+$~', $doi, $matches)) {
			if ($this->blank(ISSUE_ALIASES)) {
			  $this->add_if_new('issue', $matches[1]);
			} elseif ($this->has('number')) {
			  $this->rename('number', 'issue', $matches[1]);
			} else {
			  $this->set('issue', $matches[1]);
			}
		  }
		  if (doi_works($doi)) {
			foreach (DOI_FREE_PREFIX as $prefix) {
			  if (strpos($doi, $prefix) === 0) {
				 $this->add_if_new('doi-access', 'free');
			  }
			}
			// Weird ones that are time dependent
			$year = intval($this->year()); // Will be zero if not set
			if (strpos($doi, '10.1155/') === 0 && $year > 2006) {
				$this->add_if_new('doi-access', 'free');
			}
			unset($year);
		  }
		  if (doi_works($doi) && (strpos($doi, '10.1073/pnas') === 0)) {
			$template_year = $this->year();
			if ($template_year !== '') {
			  $template_year = (int) $template_year;
			  $year = (int) date("Y");
			  if (($template_year + 1) < $year){ // At least one year old, up to three
				$this->add_if_new('doi-access', 'free');
			  }
			}
		  }
		  if (preg_match('~^10\.48550/arXiv\.(\S{4}\.\S{5})$~i', $doi, $matches)) {
			if ($this->blank(ARXIV_ALIASES)) {
			  $this->rename('doi', 'eprint', $matches[1]);
			} elseif ($this->has('eprint')) {
			  $eprint = $this->get('eprint');
			  if ($matches[1] === $eprint) {
				$this->forget('doi');
			  }
			} elseif ($this->has('arxiv')) {
			  $eprint = $this->get('arxiv');
			  if ($matches[1] === $eprint) {
				$this->forget('doi');
			  }
			}
		  }
		  return;

		case 'hdl':
		  $handle = $this->get($param);
		  if (!$handle) return;
		  $handle = hdl_decode($handle);
		  if (preg_match('~^(.+)(%3Bownerid=.*)$~', $handle, $matches)) {  // should we shorten it?
			if (hdl_works($handle) === FALSE) {
			   $handle = $matches[1];
			}
		  }
		  if (preg_match('~^(.+)\?urlappend=~', $handle, $matches)) {  // should we shorten it?
			if (hdl_works($handle) === FALSE) {
			   $handle = $matches[1]; // @codeCoverageIgnore
			} elseif (hdl_works($handle) === NULL) {
			   ; // Do nothing
			} else {
			   $long  = hdl_works($handle);
			   $short = hdl_works($matches[1]);
			   if ($long === $short) { // urlappend does nothing
				 $handle = $matches[1];
			   }
			}
		  }
		  $this->set('hdl', $handle);
		  return;

		case 'doi-broken': case 'doi_brokendate': case 'doi-broken-date': case 'doi_inactivedate': case 'doi-inactive-date':
		  if ($this->blank('doi')) $this->forget($param);
		  return;

		case 'edition':
		  if ($this->blank($param)) return;
		  $this->set($param, preg_replace("~\s+ed(ition)?\.?\s*$~i", "", $this->get($param)));
		  return; // Don't want 'Edition ed.'

		case 'eprint':
		  if ($this->blank($param)) return;
		  if ($this->wikiname() === 'cite web') $this->change_name_to('cite arxiv');
		  return;

		case 'encyclopedia': case 'encyclopeadia':
		  if ($this->blank($param)) return;
		  if ($this->wikiname() === 'cite web') $this->change_name_to('cite encyclopedia');
		  return;

		case 'format': // clean up bot's old (pre-2018-09-18) edits
		  if ($this->get($param) === 'Accepted manuscript' ||
			  $this->get($param) === 'Submitted manuscript' ||
			  $this->get($param) === 'Full text') {
			$this->forget($param);
		  }
		  // Citation templates do this automatically -- also remove if there is no url
		  if (in_array(strtolower($this->get($param)), ['pdf', 'portable document format', '[[portable document format|pdf]]', '[[portable document format]]', '[[pdf]]'])) {
			if ($this->blank('url') || strtolower(substr($this->get('url'), -4)) === '.pdf') {
			   $this->forget($param);
			}
		  }
		  return;

		case 'chapter-format':
		// clean up bot's old (pre-2018-09-18) edits
		  if ($this->get($param) === 'Accepted manuscript' ||
			  $this->get($param) === 'Submitted manuscript' ||
			  $this->get($param) === 'Full text') {
			$this->forget($param);
		  }
		  // Citation templates do this automatically -- also remove if there is no url, which is template error
		  if (in_array(strtolower($this->get($param)), ['pdf', 'portable document format', '[[portable document format|pdf]]', '[[portable document format]]'])) {
			 if ($this->has('chapter-url')) {
			   if (substr($this->get('chapter-url'), -4) === '.pdf' || substr($this->get('chapter-url'), -4) === '.PDF') {
				 $this->forget($param);
			   }
			 } elseif ($this->has('chapterurl')) {
			   if (substr($this->get('chapterurl'), -4) === '.pdf' || substr($this->get('chapterurl'), -4) === '.PDF') {
				 $this->forget($param);
			   }
			 } else {
			   $this->forget($param); // Has no chapter URL at all
			 }
		  }
		  return;

		case 'isbn':
		  if ($this->blank('isbn')) return;
		  $this->set('isbn', safe_preg_replace('~\s?-\s?~', '-', $this->get('isbn'))); // a White space next to a dash
		  $this->set('isbn', $this->isbn10Toisbn13($this->get('isbn')));
		  if ($this->blank('journal') || $this->has('chapter') || $this->wikiname() === 'cite web') {
			$this->change_name_to('cite book');
		  }
		  $this->forget('asin');
		  return;

		case 'issn':
		case 'eissn':
		  if ($this->blank($param)) return;
		  $orig = $this->get($param);
		  $new = safe_preg_replace('~\s?[\-\–]+\s?~', '-', $orig); // a White space next to a dash or bad dash
		  $new = str_replace('x', 'X', $new);
		  if (preg_match('~^(\d{4})\s?(\d{3}[\dX])$~', $new, $matches)) {
			$new = $matches[1] . '-' . strtoupper($matches[2]); // Add dash
		  }
		  if ($orig !== $new) $this->set($param, $new);
		  return;

		case 'asin':
		  if ($this->blank($param)) return;
		  if ($this->has('isbn')) return;
		  $value = $this->get($param);
		  if (preg_match("~^\d~", $value) && substr($value, 0, 2) !== '63') { // 630 and 631 ones are not ISBNs, so block all of 63*
			$possible_isbn = sanitize_string($value);
			$possible_isbn13 = $this->isbn10Toisbn13($possible_isbn, TRUE);
			if ($possible_isbn !== $possible_isbn13) { // It is an ISBN
			  $this->rename('asin', 'isbn', $this->isbn10Toisbn13($possible_isbn));
			}
		  }
		  return;

		case 'journal':
		case 'periodical':
		  if ($this->blank($param)) return;
		  if ($this->get($param) === 'Undefined' || $this->get($param) === 'Semantic Scholar' || $this->get($param) === '[[Semantic Scholar]]') {
			 $this->forget($param);
			return;
		  }
		  if (preg_match('~^(|[a-zA-Z0-9][a-zA-Z0-9]+\.)([a-zA-Z0-9][a-zA-Z0-9][a-zA-Z0-9]+)\.(org|net|com)$~', $this->get($param))) {
			$this->rename($param, 'website');
			return;
		  }
		  if (str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');

		  $periodical = trim($this->get($param));
		  if (stripos($periodical, 'arxiv') !== FALSE) return;
		  // Special odd cases go here
		  if ($periodical === 'TAXON') { // All caps that should not be
			 $this->set($param, 'Taxon');
			 return;
		  }
		  // End special odd cases
		  if (substr(strtolower($periodical), 0, 7) === 'http://' || substr(strtolower($periodical), 0, 8) === 'https://') {
			 if ($this->blank('url')) $this->rename($param, 'url');
			 return;
		  } elseif (substr(strtolower($periodical), 0, 4) === 'www.') {
			 if ($this->blank('website')) $this->rename($param, 'website');
			 return;
		  }
		  if ($this->blank(['chapter', 'isbn']) && $param === 'journal' && stripos($this->get($param), 'arxiv') === FALSE) {
			// Avoid renaming between cite journal and cite book
			$this->change_name_to('cite journal');
		  }

		  if (( mb_substr($periodical, 0, 2) !== "[[" // Only remove partial wikilinks
					|| mb_substr($periodical, -2) !== "]]"
					|| mb_substr_count($periodical, '[[') !== 1
					|| mb_substr_count($periodical, ']]') !== 1)
					&& !preg_match('~^(?:the |)(?:publications|publication|journal|transactions|letters|annals|bulletin|reports|history) of the ~i', $periodical)
					&& !preg_match('~(magazin für |magazin fur |magazine for |section )~i', $periodical)
					)
		  {
			  $this->set($param, preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $periodical));
			  $this->set($param, preg_replace(REGEXP_PIPED_WIKILINK, "$2", $this->get($param)));
		  }
		  $periodical = trim($this->get($param));
		  if (substr($periodical, 0, 1) !== "[" && substr($periodical, -1) !== "]") {
			if ((strlen($periodical) - mb_strlen($periodical)) < 9 ) { // eight or fewer UTF-8 stuff
			   if (str_ireplace(OBVIOUS_FOREIGN_WORDS, '', ' ' . $periodical . ' ') === ' ' . $periodical . ' ' &&
				   strip_diacritics($periodical) === $periodical) {
					  $periodical = mb_ucwords($periodical); // Found NO foreign words/phrase
			   }
			   $this->set($param, title_capitalization($periodical, TRUE));
			}
		  } elseif (strpos($periodical, ":") !== 2) { // Avoid inter-wiki links
			if (preg_match(REGEXP_PLAIN_WIKILINK_ONLY, $periodical, $matches)) {
			  $periodical = $matches[1];
			  $periodical = str_replace("’", "'", $periodical); // Fix quotes for links
			  $this->set($param, '[[' . $periodical . ']]');
			  $new_periodical = title_capitalization(mb_ucwords($periodical), TRUE);
			  if (str_ireplace(OBVIOUS_FOREIGN_WORDS, '', ' ' . $periodical . ' ') === ' ' . $periodical . ' ' &&
				  str_replace(['(', ')'], '', $periodical) == $periodical &&
				  $new_periodical != $periodical) {
				 $now = WikipediaBot::is_redirect($periodical);
				 if ($now === -1) { // Dead link
				   $this->set($param, '[[' . $new_periodical . ']]');
				 } elseif ($now === 1) { // Redirect
				   if (WikipediaBot::is_redirect($new_periodical) === 0) {
					 $this->set($param, '[[' . $new_periodical . ']]');
				   }
				 }
			  }
			} elseif (preg_match(REGEXP_PIPED_WIKILINK_ONLY, $periodical, $matches)) {
			  $linked_text = str_replace("’", "'", $matches[1]); // Fix quotes for links
			  $human_text  = $matches[2];
			  if (preg_match("~^[\'\"]+([^\'\"]+)[\'\"]+$~", $human_text, $matches)) { // Remove quotes
				$human_text = $matches[1];
			  }
			  $new_linked_text = title_capitalization(mb_ucwords($linked_text), TRUE);
			  if (str_ireplace(OBVIOUS_FOREIGN_WORDS, '', ' ' . $linked_text . ' ') === ' ' . $linked_text . ' ' &&
				str_replace(['(', ')'], '', $linked_text ) == $linked_text &&
				$new_linked_text != $linked_text) {
				  $now = WikipediaBot::is_redirect($linked_text);
				  if ($now === -1) {
					$linked_text = $new_linked_text; // Dead to something
				  } elseif ($now === 1) {
					if (WikipediaBot::is_redirect($new_linked_text) === 0) {
					  $linked_text = $new_linked_text; // Redirect to actual page
					}
				  }
			  }
			  // We assume that human text is some kind of abbreviations that we really do not want to mess with
			  $periodical  = '[[' . $linked_text . '|' . $human_text . ']]';
			  $this->set($param, $periodical);
			}
		  }
		  if ($this->wikiname() === 'cite arxiv') $this->change_name_to('cite journal');
		  if ($this->is_book_series($param)) {
			$this->change_name_to('cite book');
			if ($this->blank('series')) {
			  $this->rename($param, 'series');
			} elseif ($this->is_book_series('series') ||
					 str_equivalent($this->get($param), $this->get('series'))) {
			  $this->forget($param);
			}
		  }
		  $the_param = $this->get($param);
		  if (preg_match(REGEXP_PLAIN_WIKILINK, $the_param, $matches) || preg_match(REGEXP_PIPED_WIKILINK, $the_param, $matches)) {
			  $the_param = $matches[1]; // Always the wikilink for easier standardization
		  }
		  if (in_array(strtolower($the_param), ARE_MAGAZINES) && $this->blank(['pmc','doi','pmid'])) {
			$this->change_name_to('cite magazine');
			$this->rename($param, 'magazine');
			return;
		  } elseif (in_array(strtolower($the_param), ARE_NEWSPAPERS)) {
			$this->change_name_to('cite news');
			if ($param !== 'work') $this->rename($param, 'newspaper'); // Grumpy people
			return;
		  } elseif (in_array(strtolower($the_param), ARE_WORKS)) {
			$this->rename($param, 'CITATION_BOT_HOLDS_WORK');
			$this->change_name_to('cite document');
			$this->rename('CITATION_BOT_HOLDS_WORK', 'work');
			return;
		  }
		  $the_title = $this->get('title');
		  foreach (CONFERENCE_LIST as $conf) {
			if ((stripos($the_title, $conf) !== FALSE) && (stripos($the_title, $the_param) !== FALSE)) {
			   $this->forget($param);
			}
		  }
		  return;

		case 'jstor':
		  if ($this->blank($param)) return;
		  if (substr($this->get($param), 0, 8) === '10.2307/') {
			$this->set($param, substr($this->get($param), 8));
		  } elseif (preg_match('~^https?://www\.jstor\.org/stable/(.*)$~', $this->get($param), $matches)) {
			$this->set($param, $matches[1]);
		  }
		  $this->change_name_to('cite journal', FALSE);
		  return;

		case 'magazine':
		  if ($this->blank($param)) return;
		  // Remember, we don't process cite magazine.
		  if ($this->wikiname() === 'cite journal' && !$this->has('journal')) {
			$this->rename('magazine', 'journal');
		  }
		  return;

		case 'orig-year': case 'origyear':
		  if ($this->blank($param)) return;
		  if ($this->blank(['year', 'date'])) { // Will not show unless one of these is set, so convert
			if (preg_match('~^\d\d\d\d$~', $this->get($param))) { // Only if a year, might contain text like "originally was...."
			  $this->rename($param, 'year');
			}
		  }
		  return;

		case 'mr':
		  if (preg_match("~mr(\d+)$~i", $this->get($param), $matches)) {
			 $this->set($param, $matches[1]);
		  }
		  return;

		case 'day':
		  if ($this->blank($param)) $this->forget($param);
		  return;

		case 'others':
		  if ($this->blank($param)) $this->forget($param);
		  return;

		case 'pmc-embargo-date':
		  if ($this->blank($param)) {
			return;
		  }
		  $value = $this->get($param);
		  if (!preg_match('~2\d\d\d~', $value)) {
			$this->forget($param);
			return;
		  }
		  $pmc_date=strtotime($value);
		  $now_date=strtotime('now') - 86400; // Pad one day for paranoia
		  if ($now_date > $pmc_date) {
			$this->forget($param);
		  }
		  return;

		case 'pmc':
		  if (preg_match("~pmc(\d+)$~i", $this->get($param), $matches)) {
			 $this->set($param, $matches[1]);
		  }
		  // No break; continue from pmc to pmid:
		case 'pmid':
		  if ($this->blank($param)) return;
		  $this->change_name_to('cite journal', FALSE);
		  return;

		case 'publisher':
		  if ($this->wikiname() === 'cite journal' && $this->has('journal') && $this->has('title') && $this->blank($param)) {
			$this->forget($param); // Not good to encourage adding this
			return;
		  }
		  if ($this->wikiname() === 'cite journal' && $this->has('journal') && $this->has('title') && $this->has('doi')) {
			 $test_me = str_replace(array(']', '['), '', strtolower($this->get($param)));
			 if (in_array($test_me, array('sciencedirect', 'science direct'))) {  // TODO add more
				$this->forget($param);
			 }
			return;
		  }
		  if (stripos($this->get($param), 'proquest') !== FALSE && stripos($this->get($param), 'llc') === FALSE && stripos($this->get('title'), 'Magazines for Libraries') === FALSE) {
			$this->forget($param);
			if ($this->blank('via')) {
			  $this_big_url = $this->get('url') . $this->get('thesis-url') . $this->get('thesisurl') . $this->get('chapter-url') . $this->get('chapterurl');
			  if (stripos($this_big_url, 'proquest') !== FALSE) $this->add_if_new('via', 'ProQuest');
			}
			return;
		  }
		  if ($this->blank($param)) return;
		  $publisher = mb_strtolower($this->get($param));
		  if ($this->wikiname() === 'cite journal' && $this->has('journal') && $this->has('title')
			  && !$this->blank(['pmc', 'pmid'])
			  && (strpos($publisher, 'national center for biotechnology information') !== FALSE ||
				  strpos($publisher, 'u.s. national library of medicine') !== FALSE)) {
			  $this->forget($param);
			  return;
		  }
		  if (substr($publisher, 0, 2) === '[[' &&
			  substr($publisher,   -2) === ']]' &&
			  mb_substr_count($publisher, '[[') === 1 &&
			  mb_substr_count($publisher, ']]') === 1) {
			if (preg_match(REGEXP_PLAIN_WIKILINK, $publisher, $matches)) {
			  $publisher = $matches[1];
			} elseif (preg_match(REGEXP_PIPED_WIKILINK, $publisher, $matches)) {
			  $publisher = $matches[2];
			}
			foreach (['journal', 'newspaper'] as $the_same) { // Prefer wiki-linked
			  if (mb_strtolower($this->get($the_same)) === $publisher) {
				$this->forget($the_same);
				$this->rename($param, $the_same);
				return;
			  }
			}
		  }
		  if (stripos($this->get('url'), 'maps.google') !== FALSE && stripos($publisher, 'google') !== FALSE) {
			$this->set($param, 'Google Maps'); // Case when Google actually IS a publisher
			return;
		  }
		  if (stripos($this->get('url'), 'developers.google.com') !== FALSE && stripos($publisher, 'google') !== FALSE) {
			$this->set($param, 'Google Inc.'); // Case when Google actually IS a publisher
			return;
		  }
		  if (stripos($this->get('url'), 'support.google.com') !== FALSE && stripos($publisher, 'google') !== FALSE) {
			$this->set($param, 'Google Inc.'); // Case when Google actually IS a publisher
			return;
		  }
		  if (stripos($publisher, 'google') !== FALSE) {
			$this_host = (string) parse_url($this->get('url'), PHP_URL_HOST);
			if (stripos($this_host, 'google') === FALSE ||
				stripos($this_host, 'blog')   !== FALSE ||
				stripos($this_host, 'github') !== FALSE){
			  return; // Case when Google actually IS a publisher
			}
		  }
		  if (preg_match('~\S+\s*\/\s*(?:|\[\[)Google News Archive~i', $publisher)) {
			 return;  // this is Newspaper / Google News Archive
		  }
		  if ($publisher === 'pubmed' || $publisher === 'pubmed central') {
			 if ($this->has('doi') || $this->has('pmid')) {
				$this->forget($param);
				return;
			 }
		  }

		  foreach (NON_PUBLISHERS as $not_publisher) {
			if (stripos($publisher, $not_publisher) !== FALSE) {
			  $this->forget($param);
			  return;
			}
		  }
		  // It might not be a product/book, but a "top 100" list
		  if (mb_strtolower(str_replace(array('[', ' ', ']'), '', $publisher)) === 'amazon.com') {
			$all_urls = '';
			foreach (ALL_URL_TYPES as $a_url_type) {
			  $all_urls .= $this->get($a_url_type);
			}
			$all_urls = strtolower($all_urls);
			if (strpos($all_urls, '/dp/') !== FALSE && strpos($all_urls, '/feature/') === FALSE && strpos($all_urls, '/exec/obidos/') === FALSE) {
			  $this->forget($param);
			  return;
			}
		  }
		  if (str_replace(array('[', ' ', ']'), '', $publisher) === 'google') {
			$this->forget($param);
			return;
		  }
		  if (mb_strtolower($this->get('journal')) === $publisher) {
			$this->forget($param);
			return;
		  }
		  if (mb_strtolower($this->get('newspaper')) === $publisher) {
			$this->forget($param);
			return;
		  }
		  if ($this->blank(WORK_ALIASES)) {
			if (in_array(str_replace(array('[', ']', '"', "'", 'www.'), '', $publisher), PUBLISHERS_ARE_WORKS)) {
			   $this->rename($param, 'work'); // Don't think about which work it is
			   return;
			}
		  } elseif ($this->has('website')) {
			if (in_array(str_replace(array('[', ']', '"', "'", 'www.'), '', $publisher), PUBLISHERS_ARE_WORKS)) {
			   $webby = str_replace(array('[', ']', '"', "'", 'www.', 'the ', '.com', ' '), '', mb_strtolower($this->get('website')));
			   $pubby = str_replace(array('[', ']', '"', "'", 'www.', 'the ', '.com', ' '), '', $publisher);
			   if ($webby === $pubby) {
				 if (stripos($this->get('website'), 'www') === 0 ||
					 strpos($publisher, '[') !== FALSE ||
					 strpos($this->get('website'), '[') === FALSE) {
					$this->forget('website');
					$this->rename($param, 'work');
					return;
				 }
			   }
			}
		  }
		  if ($publisher === 'nytc') {
			$publisher = 'new york times company';
		  }
		  if ($publisher === 'nyt') {
			$publisher = 'new york times';
		  }
		  if ($publisher === 'wpc') {
			$publisher = 'washington post company';
		  }
		  if (in_array(str_replace(array('[', ']', '"', "'", 'www.', ' company', ' digital archive', ' communications llc'), '', $publisher), PUBLISHERS_ARE_WORKS)) {
			$pubby = str_replace(array('the ', ' company', ' digital archive', ' communications llc'), '', $publisher);
			foreach (WORK_ALIASES as $work) {
			  $worky = str_replace(array('the ', ' company', ' digital archive', ' communications llc'), '', mb_strtolower($this->get($work)));
			  if ($worky === $pubby) {
				 $this->forget($param);
				 return;
			  }
			}
		  }

		  if (!$this->blank(['eprint', 'arxiv']) &&
			  strtolower($publisher) === 'arxiv') {
			  $this->forget($param);
			  return;
		  }

		  if ($publisher === 'the times digital archive.') {
			$this->set($param, 'The Times Digital Archive');
			$publisher = 'the times digital archive';
		  }
		  if ($publisher === 'the times digital archive') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'the times') !== FALSE ||
				  stripos($this->get($work), 'times (london') !== FALSE ||
				  stripos($this->get($work), 'times [london') !== FALSE) {
				 $this->forget($param);
				 return;
			  }
			}
		  }
		  if ($this->blank('via') && $publisher === 'the washington post – via legacy.com') {
			$publisher = 'the washington post';
			$this->set($param, '[[The Washington Post]]');
			$this->set('via', 'Legacy.com');
		  }
		  if ($publisher === 'the washington post' ||
			  $publisher === 'washington post' ||
			  $publisher === 'the washington post company' ||
			  $publisher === 'the washington post websites' ||
			  $publisher === 'washington post websites' ||
			  $publisher === 'the washington post (subscription required)') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'washington post') !== FALSE ||
				  stripos($this->get($work), 'washingtonpost.com') !== FALSE) {
				 $this->forget($param);
				 if (stripos($this->get($work), 'washingtonpost.com') !== FALSE) {
				   $this->set($work, '[[The Washington Post]]');
				 }
				 return;
			  }
			}
			if (in_array(strtolower($this->get('work')), array('local', 'editorial', 'international', 'national',
				'communication', 'letter to the editor', 'review', 'coronavirus', 'race & reckoning',
				'politics', 'opinion', 'opinions', 'investigations', 'tech', 'technology', 'world',
				'sports', 'world', 'arts & entertainment', 'arts', 'entertainment', 'u.s.', 'n.y.',
				'business', 'science', 'health', 'books', 'style', 'food', 'travel', 'real estate',
				'magazine', 'economy', 'markets', 'life & arts', 'uk news', 'world news', 'health news',
				'lifestyle', 'photos', 'education', 'arts', 'life', 'puzzles')) &&
				$this->blank('department')) {
				$this->rename('work', 'department');
				$this->rename($param, 'work');
			  return;
			}
		  }

		  if ($publisher === 'the new york times' ||
			  $publisher === 'new york times' ||
			  $publisher === 'the new york times (subscription required)') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'new york times') !== FALSE ||
				  stripos($this->get($work), 'nytimes.com') !== FALSE) {
				 $this->forget($param);
				 if (stripos($this->get($work), 'nytimes.com') !== FALSE) {
				   $this->set($work, '[[The New York Times]]');
				 }
				 return;
			  }
			}
		  }

		  if ($publisher === 'the guardian' ||
			  $publisher === 'the guardian media' ||
			  $publisher === 'the guardian media group' ||
			  $publisher === 'guardian media' ||
			  $publisher === 'guardian media group') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'The Guardian') !== FALSE ||
				  stripos($this->get($work), 'theguardian.com') !== FALSE) {
				 $this->forget($param);
				 if (stripos($this->get($work), 'theguardian.com') !== FALSE) {
				   $this->set($work, '[[The Guardian]]');
				 }
				 return;
			  }
			}
		  }

		  if ($publisher === 'the economist' ||
			  $publisher === 'the economist group') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'the economist') !== FALSE ||
				  stripos($this->get($work), 'economist.com') !== FALSE) {
				 $this->forget($param);
				 if (stripos($this->get($work), 'economist.com') !== FALSE) {
				   $this->set($work, '[[The Economist]]');
				 }
				 return;
			  }
			}
		  }

		  if ($publisher === 'news uk') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'the times') !== FALSE ||
				  stripos($this->get($work), 'thetimes.co.uk') !== FALSE) {
				 $this->forget($param);
				 if (stripos($this->get($work), 'thetimes.co.uk') !== FALSE) {
				   $this->set($work, '[[The Times]]');
				 }
				 return;
			  }
			}
		  }

		  if ($publisher === 'san jose mercury news' ||
			  $publisher === 'san jose mercury-news') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'mercurynews.com') !== FALSE ||
				  stripos($this->get($work), 'mercury news') !== FALSE) {
				 $this->forget($param);
				 if (stripos($this->get($work), 'mercurynews.com') !== FALSE) {
				   $this->set($work, '[[San Jose Mercury News]]');
				 }
				 return;
			  }
			}
		  }
		  if ($publisher === 'the san diego union-tribune, llc' ||
			  $publisher === 'the san diego union tribune, llc') {
			$publisher = 'the san diego union-tribune';
			$this->set($param, 'The San Diego Union-Tribune');
		  }
		  if ($publisher === 'the san diego union-tribune' ||
			  $publisher === 'the san diego union tribune' ||
			  $publisher === 'san diego union-tribune' ||
			  $publisher === 'san diego union tribune') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'sandiegouniontribune.com') !== FALSE ||
				  stripos($this->get($work), 'SignOnSanDiego.com') !== FALSE ||
				  stripos($this->get($work), 'san diego union') !== FALSE) {
				 $this->forget($param);
				 if (stripos($this->get($work), 'sandiegouniontribune.com') !== FALSE ||
					 stripos($this->get($work), 'SignOnSanDiego.com') !== FALSE) {
				   $this->set($work, '[[The San Diego Union-Tribune]]');
				 }
				 return;
			  }
			}
			if (in_array(strtolower($this->get('work')), array('local', 'editorial', 'international', 'national',
				'communication', 'letter to the editor', 'review', 'coronavirus', 'race & reckoning',
				'politics', 'opinion', 'opinions', 'investigations', 'tech', 'technology', 'world',
				'sports', 'world', 'arts & entertainment', 'arts', 'entertainment', 'u.s.', 'n.y.',
				'business', 'science', 'health', 'books', 'style', 'food', 'travel', 'real estate',
				'magazine', 'economy', 'markets', 'life & arts', 'uk news', 'world news', 'health news',
				'lifestyle', 'photos', 'education', 'arts', 'life', 'puzzles')) &&
				$this->blank('department')) {
				$this->rename('work', 'department');
				$this->rename($param, 'work');
			  return;
			}
		  }

		  if ($publisher === 'forbes media llc' ||
			  $publisher === 'forbes media, llc' ||
			  $publisher === 'forbes media, llc.' ||
			  $publisher === 'forbes (forbes media)' ||
			  $publisher === 'forbes media llc.') {
			$publisher = 'forbes media';
			$this->set($param, 'Forbes Media');
		  }
		  if ($publisher === 'forbes inc' ||
			  $publisher === 'forbes inc.' ||
			  $publisher === 'forbes, inc' ||
			  $publisher === 'forbes, inc.' ||
			  $publisher === 'forbes.' ||
			  $publisher === 'forbes ny'
			 ) {
			$publisher = 'forbes';
			$this->set($param, 'Forbes');
		  }
		  if ($publisher === 'forbes.com llc' ||
			  $publisher === 'forbes.com' ||
			  $publisher === 'forbes.com llc™' ||
			  $publisher === 'forbes.com llc.') {
			$publisher = 'forbes';
			$this->set($param, 'Forbes');
		  }
		  if ($publisher === 'forbes publishing' ||
			  $publisher === 'forbes publishing company' ||
			  $publisher === 'forbes publishing co' ||
			  $publisher === 'forbes publishing co.'
			 ) {
			$publisher = 'forbes publishing';
			$this->set($param, 'Forbes Publishing');
		  }
		  if ($publisher === 'forbes publishing' ||
			  $publisher === 'forbes' ||
			  $publisher === 'forbes magazine' ||
			  $publisher === 'forbes media') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'forbes') !== FALSE) {
				 $this->forget($param);
				 if (stripos($this->get($work), 'forbes.com') !== FALSE) {
				   $this->set($work, '[[Forbes]]');
				 }
				 return;
			  }
			  if ($this->blank('agency')) {
				if (stripos($this->get($work), 'AFX News') !== FALSE ||
					stripos($this->get($work), 'Thomson Financial News') !== FALSE) {
				  $this->rename($work, 'agency');
				}
			  }
			}
		  }

		  if ($publisher === 'la times' ||
			  $publisher === 'latimes' ||
			  $publisher === 'latimes.com' ||
			  $publisher === 'the la times' ||
			  $publisher === 'the los angeles times' ||
			  $publisher === '[[los angeles times]] (latimes.com)'
			 ) {
			$publisher = 'los angeles times';
			if (strpos($this->get($param), '[') !== FALSE) {
			  $this->set($param, '[[Los Angeles Times]]');
			} else {
			  $this->set($param, 'Los Angeles Times');
			}
		  }
		  if ($publisher === 'los angeles times' ||
			  $publisher === 'los angeles times media group') {
			foreach (WORK_ALIASES as $work) {
			  if (stripos($this->get($work), 'latimes') !== FALSE ||
				  stripos($this->get($work), 'los angeles times') !== FALSE) {
				 $this->forget($param);
				 if (stripos($this->get($work), 'latimes') !== FALSE) {
				   $this->set($work, '[[Los Angeles Times]]');
				 }
				 return;
			  }
			  if ($this->blank('via')) {
				if (stripos($this->get($work), 'laweekly') !== FALSE) {
				  $this->rename($work, 'via');
				}
			  }
			}
		  }

		  foreach (WORK_ALIASES as $work) {
			  $worky = strtolower($this->get($work));
			  $worky = str_replace(array("[[" , "]]"), "", $worky);
			  if (in_array($worky, NO_PUBLISHER_NEEDED)) {
				 $this->forget($param);
				 return;
			  }
		  }

		  if ( $publisher === 'www.pressreader.com' ||
			   $publisher === 'pressreader.com' ||
			   $publisher === 'pressreader.com (archived)' ||
			   $publisher === 'pressreader' ||
			   $publisher === 'www.pressreader.com/'
			  ) {
			  if ($this->blank('via')) {
				 $this->set($param, '[[PressReader]]');
				 $this->rename($param, 'via');
			  } elseif (stripos($this->get('via'), 'pressreader') !== FALSE) {
				 $this->forget($param);
			  }
			  return;
		  }

		  if ( $publisher === 'www.sify.com' ||
			   $publisher === 'sify.com' ||
			   $publisher === 'sify'
			  ) {
				 $this->set($param, '[[Sify]]');
				 $publisher = 'sify';
		  }
		  if ((stripos($publisher, 'sify.com') !== FALSE) ||(stripos($publisher, 'sify ') !== FALSE) || ($publisher === 'sify')) {
			if ($this->blank(WORK_ALIASES)) {
			  $this->rename($param, 'website');
			} else {
			  $lower = "";
			  foreach (WORK_ALIASES as $worky) {
				$lower = $lower . strtolower($this->get($worky));
			  }
			  if (strpos($lower, 'sify') !== FALSE) {
				$this->forget($param);
			  }
			}
			return;
		  }

		  if ( $publisher === 'www.bollywoodhungama.com' ||
			   $publisher === 'bollywoodhungama.com' ||
			   $publisher === 'bollywoodhungama' ||
			   $publisher === 'bollywood hungama'
			  ) {
				 $this->set($param, '[[Bollywood Hungama]]');
				 $publisher = 'bollywood hungama';
		  }
		  if ((stripos($publisher, 'bollywoodhungama.com') !== FALSE) || (stripos($publisher, 'bollywood hungama') !== FALSE) || (stripos($publisher, 'BH News Network') !== FALSE)) {
			if ($this->blank(WORK_ALIASES)) {
			  $this->rename($param, 'website');
			} else {
			  $lower = "";
			  foreach (WORK_ALIASES as $worky) {
				$lower = $lower . strtolower($this->get($worky));
			  }
			  if (strpos($lower, 'bollywoodhungama') !== FALSE || strpos($lower, 'bollywood hungama') !== FALSE) {
				$this->forget($param);
			  } elseif ($lower === 'bh news network') {
				foreach (WORK_ALIASES as $worky) {
				  $this->forget($worky);
				}
				$this->rename($param, 'website');
			  }
			}
			$authtmp = $this->get('author');
			if ($authtmp === 'Bollywood Hungama News Network' || $authtmp === 'Bollywood Hungama') {
			  $this->forget('author');
			}
			return;
		  }

		  return;

		case 'quotes':
		  switch(strtolower(trim($this->get($param)))) {
			case 'yes': case 'y': case 'true': case 'no': case 'n': case 'false': $this->forget($param);
		  }
		  return;

		case 'ref':
		  $content = mb_strtolower($this->get($param));
		  if ($content === '' || $content === 'harv') {
			$this->forget($param);
		  } elseif (preg_match('~^harv( *# # # CITATION_BOT_PLACEHOLDER_COMMENT.*?# # #)$~sui', $content, $matches)) {
			$this->set($param, $matches[1]); // Sometimes it is ref=harv <!-- {{harvid|....}} -->
		  }
		  return;

		case 'series':
		  if (str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');
		  if ($this->is_book_series('series')) {
			$this->change_name_to('cite book');
			if ($this->has('journal')) {
			  if ($this->is_book_series('journal') ||
					 str_equivalent($this->get('series'), $this->get('journal'))) {
				$this->forget('journal');
			  }
			}
		  }
		  return;

		case 'title':
		  if ($this->blank($param)) return;
		  $title = $this->get($param);
		  if ($title === 'Validate User' || $title === 'Join Ancestry' || $title === 'Join Ancestry.com' || $title === 'Ancestry - Sign Up') {
			 $this->set('title', '');
			 return;
		  }
		  $title = straighten_quotes($title, FALSE);
		  if ((   mb_substr($title, 0, 1) === '"'
			   && mb_substr($title, -1)   === '"'
			   && mb_substr_count($title, '"') === 2)
			   ||
			   (   mb_substr($title, 0, 1) === "'"
				&& mb_substr($title, -1)   === "'"
				&& mb_substr_count($title, "'") === 2)
		  ) {
			report_warning("The quotes around the title are most likely an editor's error: " . echoable(mb_substr($title, 1, -1)));
		  }
		  // Messed up cases: [[sdfsad] or [dsfasdf]]
		  if (preg_match('~^\[\[([^\]\[\|]+)\]$~', $title, $matches) ||
			  preg_match('~^\[([^\]\[\|]+)\]\]$~', $title, $matches)) {
			 $title = $matches[1];
		  }
		  // Only do for cite book, since might be title="A review of the book Bob (Robert Edition)"
		  if ($this->wikiname() === 'cite book' && $this->blank('edition') && preg_match('~^(.+)\(([^\(\)]+) edition\)$~i', $title, $matches)) {
			 $title = trim($matches[1]);
			 $this->add_if_new('edition', trim($matches[2]));
		  }
		  if (mb_substr_count($title, '[[') !== 1 ||  // Completely remove multiple wikilinks
			  mb_substr_count($title, ']]') !== 1) {
			if (stripos($title, 'reviewed work') === FALSE) {
			 $title = preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $title);   // Convert [[X]] wikilinks into X
			 $title = preg_replace(REGEXP_PIPED_WIKILINK, "$2", $title);   // Convert [[Y|X]] wikilinks into X
			 $title = preg_replace("~\[\[~", "", $title); // Remove any extra [[ or ]] that should not be there
			 $title = preg_replace("~\]\]~", "", $title);
			}
		  } elseif (strpos($title, '{{!}}') === FALSE) { // Convert a single link to a title-link
			 if (preg_match(REGEXP_PLAIN_WIKILINK, $title, $matches)) {
			   if (strlen($matches[1]) > (0.7 * (float) strlen($title)) && ($title !== '[[' . $matches[1] . ']]')) {  // Only add as title-link if a large part of title text
				 $title = '[[' . $matches[1] . "|" . str_replace(array("[[", "]]"), "", $title) . ']]';
			   }
			 } elseif (preg_match(REGEXP_PIPED_WIKILINK_ONLY, $title, $matches) &&
					   strpos($title, ':') === FALSE) { // Avoid touching inter-wiki links
			   if (($matches[1] === $matches[2]) && ($title === $matches[0])) {
				   $title = '[[' . $matches[1] . ']]'; // Clean up double links
			   }
			 }
		  }
		  $this->set($param, $title);
		  if ($title && str_equivalent($this->get($param), $this->get('work'))) $this->forget('work');
		  if ($title && str_equivalent($this->get($param), $this->get('encyclopedia'))) $this->forget($param);
		  if ($title && str_equivalent($this->get($param), $this->get('encyclopaedia'))) $this->forget($param);
		  if (preg_match('~^(.+)\{\{!\}\} Request PDF$~i', trim($this->get($param)), $match)) {
				 $this->set($param, trim($match[1]));
		  } elseif (!$this->blank(['isbn', 'doi', 'pmc', 'pmid']) && preg_match('~^(.+) \(PDF\)$~i', trim($this->get($param)), $match)) {
				 $this->set($param, trim($match[1])); // Books/journals probably don't end in (PDF)
		  }

		  if (preg_match("~^(.+national conference) on \-$~i", $this->get($param), $matches)) {
			  $this->set($param, trim($matches[1])); // ACM conference titles
		  }
		  return;

		case 'archivedate':
		  if ($this->has('archivedate') && $this->get('archive-date') === $this->get('archivedate')) {
			$this->forget('archivedate');
		  }
		  return;

		case 'archive-url':
		case 'archiveurl':
		  if ($this->blank(['archive-date', 'archivedate'])) {
			if (preg_match('~^https?://(?:web\.archive\.org/web/|archive\.today/|archive\.\S\S/|webarchive\.loc\.gov/all/|www\.webarchive\.org\.uk/wayback/archive/)(\d{4})(\d{2})(\d{2})\d{6}~', $this->get($param), $matches)) {
			  $this->add_if_new('archive-date', $matches[1] . '-' . $matches[2] . '-' . $matches[3]);
			}
			if (preg_match('~^https?://wayback\.archive\-it\.org/\d{4}/(\d{4})(\d{2})(\d{2})\d{6}~', $this->get($param), $matches)) {
			  $this->add_if_new('archive-date', $matches[1] . '-' . $matches[2] . '-' . $matches[3]);
			}
		  }
		  if (preg_match('~^(?:web\.|www\.).+$~', $this->get($param), $matches) && stripos($this->get($param), 'citation') === FALSE) {
			  $this->set($param, 'http://' . $matches[0]);
		  }
		  if (preg_match('~^https?://(?:web\.archive\.org/web|archive\.today|archive\.\S\S|webarchive\.loc\.gov/all|www\.webarchive\.org\.uk/wayback/archive)/(?:save|\*)/~', $this->get($param)) ||
			  preg_match('~https://www\.bloomberg\.com/tosv2\.html~', $this->get($param)) ||
			  preg_match('~googleads\.g\.doubleclick\.net~', $this->get($param)) ||
			  preg_match('~https://apis\.google\.com/js/plusone\.js$~', $this->get($param)) ||
			  preg_match('~https?://www\.britishnewspaperarchive\.co\.uk/account/register~', $this->get($param)) ||
			  preg_match('~https://www\.google\-analytics\.com/ga\.js$~', $this->get($param)) ||
			  preg_match('~academic\.oup\.com/crawlprevention~', $this->get($param)) ||
			  preg_match('~ancestryinstitution~', $this->get($param)) ||
			  preg_match('~ancestry\.com/cs/offers~', $this->get($param)) ||
			  preg_match('~myprivacy\.dpgmedia\.nl~', $this->get($param)) ||
			  preg_match('~https://meta\.wikimedia\.org/w/index\.php\?title\=Special\:UserLogin~', $this->get($param))) {
				$this->forget($param);
				if ($this->get('title') === 'Validate User') $this->set('title', '');
				return;
		  }
		  if (preg_match('~^(https?://(?:www\.|)webcitation\.org/)([0-9a-zA-Z]{9})(?:|\?url=.*)$~', $this->get($param), $matches)) {
			  // $this->set($param, $matches[1] . $matches[2]); // The url part is actually NOT binding, but other wikipedia bots check it
			  if ($this->blank(['archive-date', 'archivedate'])) {
				 $base62='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
				 $num62 = str_split($matches[2]);
				 $time = 0;
				 for($i=0;$i<9;$i++) {
					$time = (62 * $time) + (int) strpos($base62, $num62[$i]);
				 }
				 $this->add_if_new('archive-date', date("Y-m-d", (int) ($time/1000000)));
			  }
			  return;
		  }
		  if (stripos($this->get($param), 'archive') === FALSE) {
			if ($this->get($param) === $this->get('url')) {
			  $this->forget($param);  // The archive url is the real one
			  return;
			}
		  }
		  // Clean up a bunch on non-archive URLs
		  if (stripos($this->get($param), 'archive') === FALSE &&
			  stripos($this->get($param), 'webcitation') === FALSE &&
			  stripos($this->get($param), 'perma.') === FALSE &&
			  stripos($this->get($param), 'wayback') === FALSE &&
			  stripos($this->get($param), 'webharvest') === FALSE &&
			  stripos($this->get($param), 'freezepage') === FALSE &&
			  stripos($this->get($param), 'petabox.bibalex.org') === FALSE) {
			 if (preg_match("~^https?://(?:www\.|)researchgate\.net/[^\s]*publication/([0-9]+)_*~i", $this->get($param), $matches)) {
				 $this->set($param, 'https://www.researchgate.net/publication/' . $matches[1]);
				 if (preg_match('~^\(PDF\)(.+)$~i', trim($this->get('title')), $match)) {
				   $this->set('title', trim($match[1]));
				 }
			 } elseif (preg_match("~^https?://(?:www\.|)academia\.edu/(?:documents/|)([0-9]+)/*~i", $this->get($param), $matches)) {
				 $this->set($param, 'https://www.academia.edu/' . $matches[1]);
			 } elseif (preg_match("~^https?://(?:www\.|)zenodo\.org/record/([0-9]+)(?:#|/files/)~i", $this->get($param), $matches)) {
				 $this->set($param, 'https://zenodo.org/record/' . $matches[1]);
			 } elseif (preg_match("~^https?://(?:www\.|)google\.com/search~i", $this->get($param))) {
				 $this->set($param, $this->simplify_google_search($this->get($param)));
			 } elseif (preg_match("~^(https?://(?:www\.|)sciencedirect\.com/\S+)\?via(?:%3d|=)\S*$~i", $this->get($param), $matches)) {
				 $this->set($param, $matches[1]);
			 } elseif (preg_match("~^(https?://(?:www\.|)bloomberg\.com/\S+)\?(?:utm_|cmpId=)\S*$~i", $this->get($param), $matches)) {
				 $this->set($param, $matches[1]);
			 } elseif (preg_match("~^https?://watermark\.silverchair\.com/~", $this->get($param))
				 || preg_match("~^https?://s3\.amazonaws\.com/academia\.edu~", $this->get($param))
				 || preg_match("~^https?://onlinelibrarystatic\.wiley\.com/store/~", $this->get($param))) {
				 $this->forget($param);
				 return;
			 }
			 if ($this->get_identifiers_from_url($this->get($param))) {
			   if (extract_doi($this->get($param))[1] === '') { // If it gives a doi, then might want to keep it anyway since many archives have doi in the url string
				 $this->forget($param);
				 return;
			   }
			 }
		  }
		  if ($this->blank(ALL_URL_TYPES)) {
			 if (preg_match("~^https?://web\.archive\.org/web/\d{14}/(https?://.*)$~", $this->get($param), $match)) {
				quietly('report_modification', 'Extracting URL from archive');
				$this->add_if_new('url', $match[1]);
			 }
		  }
		  // Remove trailing #
		  if (preg_match("~^(\S+)#$~u", $this->get($param), $matches)) {
			$this->set($param, $matches[1]);
			foreach (ALL_URL_TYPES as $url_types) {
			  if (preg_match("~^(\S+)#$~u", $this->get($url_types), $matches)) {
				$this->set($url_types, $matches[1]);
			  }
			}
		  }
		  return;

		case 'chapter-url':
		case 'chapterurl':
		  if ($this->blank($param)) return;
		  if ($this->blank('url') && $this->blank(CHAPTER_ALIASES_AND_SCRIPT)) {
			$this->rename($param, 'url');
			$param = 'url'; // passes down to next area
		  }
		case 'url':
		  if ($this->blank($param)) return;
		  if (preg_match('~^(?:web\.|www\.).+$~', $this->get($param), $matches) && stripos($this->get($param), 'citation') === FALSE) {
			  $this->set($param, 'http://' . $matches[0]);
		  }
		  $the_original_url = $this->get($param);
		  if (preg_match("~^https?://(?:www\.|)researchgate\.net/[^\s]*publication/([0-9]+)_*~i", $this->get($param), $matches)) {
			  $this->set($param, 'https://www.researchgate.net/publication/' . $matches[1]);
			  if (preg_match('~^\(PDF\)(.+)$~i', trim($this->get('title')), $match)) {
				 $this->set('title', trim($match[1]));
			  }
		  } elseif (preg_match("~^https?://(?:www\.|)academia\.edu/(?:documents/|)([0-9]+)/*~i", $this->get($param), $matches)) {
			  $this->set($param, 'https://www.academia.edu/' . $matches[1]);
		  } elseif (preg_match("~^https?://(?:www\.|)zenodo\.org/record/([0-9]+)(?:#|/files/)~i", $this->get($param), $matches)) {
			  $this->set($param, 'https://zenodo.org/record/' . $matches[1]);
		  } elseif (preg_match("~^https?://(?:www\.|)google\.com/search~i", $this->get($param))) {
			  $this->set($param, $this->simplify_google_search($this->get($param)));
		  } elseif (preg_match("~^(https?://(?:www\.|)sciencedirect\.com/\S+)\?via(?:%3d|=)\S*$~i", $this->get($param), $matches)) {
			  $this->set($param, $matches[1]);
		  } elseif (preg_match("~^(https?://(?:www\.|)bloomberg\.com/\S+)\?(?:utm_|cmpId=)\S*$~i", $this->get($param), $matches)) {
			  $this->set($param, $matches[1]);
		  } elseif (preg_match("~^https?://watermark\.silverchair\.com/~", $this->get($param))
				 || preg_match("~^https?://s3\.amazonaws\.com/academia\.edu~", $this->get($param))
				 || preg_match("~^https?://onlinelibrarystatic\.wiley\.com/store/~", $this->get($param))) {
				 if ($this->blank(['archive-url', 'archiveurl'])) { // Sometimes people grabbed a snap of it
					$this->forget($param);
				 }
			  return;
		  } elseif (preg_match("~^https?://(?:www\.|)bloomberg\.com/tosv2\.html\?vid=&uuid=(?:.+)&url=([a-zA-Z0-9/\+]+=*)$~", $this->get($param), $matches)) {
			 if (base64_decode($matches[1])) {
			   quietly('report_modification', "Decoding Bloomberg URL.");
			   $this->set($param, 'https://www.bloomberg.com' . base64_decode($matches[1]));
			 }
		  } elseif (preg_match("~^https:?//myprivacy\.dpgmedia\.nl/.+callbackUrl=(.+)$~", $this->get($param), $matches)) {
			  $the_match = $matches[1];
			  $the_match = urldecode(urldecode($the_match));
			  if (preg_match("~^(https.+)/privacy-gate/accept-tcf2\?redirectUri=(/.+)$~", $the_match, $matches)) {
				$this->set($param, $matches[1] . $matches[2]);
			  } 
		  } elseif (preg_match("~^https?://academic\.oup\.com/crawlprevention/governor\?content=([^\s]+)$~", $this->get($param), $matches)) {
			 quietly('report_modification', "Decoding OUP URL.");
			 $this->set($param, 'https://academic.oup.com' . preg_replace('~(?:\?login=false|\?redirectedFrom=fulltext|\?login=true)$~i', '', urldecode($matches[1])));
			 if ($this->get('title') === 'Validate User') $this->set('title', '');
			 if ($this->get('website') === 'academic.oup.com') $this->forget('website');
		  } elseif (preg_match("~^https?://.*ebookcentral.proquest.+/lib/.+docID(?:%3D|=)(\d+)(|#.*|&.*)(?:|\.)$~i", $this->get($param), $matches)) {
			  if ($matches[2] === '#' || $matches[2] === '#goto_toc' || $matches[2] === '&' ||
				  $matches[2] === '&query=' || $matches[2] === '&query=#' || preg_match('~^&tm=\d*$~', $matches[2])) {
				$matches[2] = '';
			  }
			  if (substr($matches[2], -1) === '#' || substr($matches[2], -1) === '.') $matches[2] = substr($matches[2], 0, -1); // Sometime just a trailing # after & part
			  quietly('report_modification', "Unmasking Proquest eBook URL.");
			  $this->set($param, 'https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=' . $matches[1] . $matches[2]);
		  } elseif (preg_match("~^https?://(?:www\.|)figshare\.com/articles/journal_contribution/[^/]+/([0-9]+)$~i", $this->get($param), $matches)) {
			  $this->set($param, 'https://figshare.com/articles/journal_contribution/' . $matches[1]);
		  }

		  if (preg_match("~ebscohost.com.*AN=(\d+)$~", $this->get($param), $matches)) {
			 $this->set($param, 'http://connection.ebscohost.com/c/articles/' . $matches[1]);
		  }
		  if (preg_match("~https?://www\.britishnewspaperarchive\.co\.uk/account/register.+viewer\%252fbl\%252f(\d+)\%252f(\d+)\%252f(\d+)\%252f(\d+)(?:\&|\%253f)~", $this->get($param), $matches)) {
			 $this->set($param, 'https://www.britishnewspaperarchive.co.uk/viewer/bl/' . $matches[1] . '/' . $matches[2] . '/' . $matches[3] . '/' .$matches[4]);
		  }
		  if (preg_match("~^https?(://pubs\.rsc\.org.+)#!divAbstract$~", $this->get($param), $matches)) {
			 $this->set($param, 'https' . $matches[1]);
		  }
		  if (preg_match("~^https?(://pubs\.rsc\.org.+)\/unauth$~", $this->get($param), $matches)) {
			 $this->set($param, 'https' . $matches[1]);
		  }
		  if (preg_match("~^https?://www.healthaffairs.org/do/10.1377/hblog(\d+\.\d+)/full/$~", $this->get($param), $matches)) {
			  $this->set($param, 'https://www.healthaffairs.org/do/10.1377/forefront.' . $matches[1] . '/full/');
			  $this->forget('access-date');
			  $this->forget('accessdate');
			  $this->add_if_new('doi', '10.1377/forefront.' . $matches[1]);
			  if (strpos($this->get('doi'), 'forefront') !== FALSE) {
				if (strpos($this->get('archiveurl') . $this->get('archive-url'), 'healthaffairs') !== FALSE) {
				  $this->forget('archiveurl');
				  $this->forget('archive-url');
				}
			  }
		  }

		  if (stripos($this->get($param), 'youtube') !== FALSE) {
			if (preg_match("~^(https?://(?:|www\.|m\.)youtube\.com/watch)(%3F.+)$~", $this->get($param), $matches)) {
				 report_info("Decoded YouTube URL");
				 $this->set($param, $matches[1] . urldecode($matches[2]));
			}
		  }

		  if (preg_match("~^https?://(.+\.springer\.com/.+)#citeas$~", $this->get($param), $matches)) {
			   $this->set($param, 'https://' . $matches[1]);
		  }

		  // Proxy stuff
		  if (stripos($this->get($param), 'proxy') !== FALSE) { // Look for proxy first for speed, this list will grow and grow
			  // Use dots, not \. since it might match dot or dash
			  if (preg_match("~^https?://ieeexplore.ieee.org.+proxy.*/document/(.+)$~", $this->get($param), $matches)) {
				 report_info("Remove proxy from IEEE URL");
				 $this->set($param, 'https://ieeexplore.ieee.org/document/' . $matches[1]);
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
			  } elseif (preg_match("~^https?://(?:www.|)oxfordhandbooks.com.+proxy.*/view/(.+)$~", $this->get($param), $matches)) {
				 $this->set($param, 'https://www.oxfordhandbooks.com/view/' . $matches[1]);
				 report_info("Remove proxy from Oxford Handbooks URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
			  } elseif (preg_match("~^https?://(?:www.|)oxfordartonline.com.+proxy.*/view/(.+)$~", $this->get($param), $matches)) {
				 $this->set($param, 'https://www.oxfordartonline.com/view/' . $matches[1]);
				 report_info("Remove proxy from Oxford Art URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
			  } elseif (preg_match("~^https?://(?:www.|)sciencedirect.com[^/]+/(\S+)$~i", $this->get($param), $matches)) {
				 report_info("Remove proxy from ScienceDirect URL");
				 $this->set($param, 'https://www.sciencedirect.com/' . $matches[1]);
				 if ($this->has('via')) {
				   if (stripos($this->get('via'), 'library') !== FALSE ||
					   stripos($this->get('via'), 'direct') === FALSE) {
					 $this->forget('via');
				   }
				 }
			  } elseif (preg_match("~^https?://(?:login\.|)(?:lib|)proxy\.[^\?\/]+\/login\?q?url=(https?://)(.+)$~", $this->get($param), $matches)) {
				 if (strpos($matches[2], '/') === FALSE) {
					$this->set($param, $matches[1] . urldecode($matches[2]));
				 } else {
					$this->set($param, $matches[1] . $matches[2]);
				 }
				 report_info("Remove proxy from URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
			  } elseif (preg_match("~^https?://(?:login\.|)(?:lib|)proxy\.[^\?\/]+\/login\?q?url=(https?%3A%2F%2F.+)$~i", $this->get($param), $matches)) {
				 $this->set($param, urldecode($matches[1]));
				 report_info("Remove proxy from URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
			  }
		  }
		  if (preg_match("~^https://wikipedialibrary\.idm\.oclc\.org/login\?auth=production&url=(https?://.+)$~i", $this->get($param), $matches)) {
			$this->set($param, $matches[1]);
		  }
		  if (preg_match("~^(https://www\.ancestry(?:institution|).com/discoveryui-content/view/\d+:\d+)\?.+$~i", $this->get($param), $matches)) {
			$this->set($param, $matches[1]);
		  }
		  if (preg_match("~ancestry\.com/cs/offers/join.*url=(http.*)$~i", $this->get($param), $matches)) {
			$this->set($param, str_replace(' ', '+', urldecode($matches[1])));
		  }
		  if (preg_match("~ancestry\.com/account/create.*returnurl=(http.*)$~i", $this->get($param), $matches)) {
			$this->set($param, str_replace(' ', '+', urldecode($matches[1])));
		  }
		  if (preg_match("~^https://search\.ancestry(?:|institution)\.com.*cgi-bin/sse.dll.*_phcmd.*(http.+)\'\,\'successSource\'\)$~i", $this->get($param), $matches)) {
			$this->set($param, str_replace(' ', '+', urldecode($matches[1])));
		  }
		  if (preg_match("~^https://search\.ancestry(?:|institution)\.com.*cgi-bin/sse.dll.*_phcmd.*(http.+)%27\,%27successSource%27\)$~i", $this->get($param), $matches)) {
			$this->set($param, str_replace(' ', '+', urldecode($matches[1])));
		  }
		  if (preg_match("~^https://www\.ancestry(?:|institution)\.com/facts.*_phcmd.*(http.+)\'\,\'successSource\'\)$~i", $this->get($param), $matches)) {
			$this->set($param, str_replace(' ', '+', urldecode($matches[1])));
		  }
		  if (preg_match("~^https://www\.ancestry(?:|institution)\.com/facts.*_phcmd.*(http.+)%27\,%27successSource%27\)$~i", $this->get($param), $matches)) {
			$this->set($param, str_replace(' ', '+', urldecode($matches[1])));
		  }
		  // idm.oclc.org Proxy
		  if (stripos($this->get($param), 'idm.oclc.org') !== FALSE && stripos($this->get($param), 'ancestryinstitution') === FALSE) {
			  $oclc_found = FALSE;
			  if (preg_match("~^https://([^\.\-\/]+)-([^\.\-\/]+)-([^\.\-\/]+)\.[^\.\-\/]+\.idm\.oclc\.org/(.+)$~i", $this->get($param), $matches)) {
				 $this->set($param, 'https://' . $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '/' . $matches[4]);
				 $oclc_found = TRUE;
			  } elseif (preg_match("~^https://([^\.\-\/]+)\.([^\.\-\/]+)\.com.[^\.\-\/]+\.idm\.oclc\.org/(.+)$~i", $this->get($param), $matches)) {
				 $this->set($param, 'https://' . $matches[1] . '.' . $matches[2] . '.com/' . $matches[3]);
				 $oclc_found = TRUE;
			  } elseif (preg_match("~^https://([^\.\-\/]+)-([^\.\-\/]+)\.[^\.\-\/]+\.idm\.oclc\.org/(.+)$~i", $this->get($param), $matches)) {
				 $this->set($param, 'https://' . $matches[1] . '.' . $matches[2] . '/' . $matches[3]);
				 $oclc_found = TRUE;
			  } elseif (preg_match("~^https://(?:login.?|)[^\.\-\/]+\.idm\.oclc\.org/login\?q?url=(https?://[^\.\-\/]+\.[^\.\-\/]+\.[^\.\-\/]+/.*)$~i", $this->get($param), $matches)) {
				 $this->set($param, $matches[1]);
				 $oclc_found = TRUE;
			  } elseif (preg_match("~^https://(?:login.?|)[^\.\-\/]+\.idm\.oclc\.org/login\?q?url=(https?://[^\.\-\/\%]+\.[^\.\-\/\%]+\.[^\.\-\/\%]+)(\%2f.*)$~i", $this->get($param), $matches)) {
				 $this->set($param, $matches[1] . urldecode($matches[2]));
				 $oclc_found = TRUE;
			  }
			  if ($oclc_found) {
				report_info("Remove OCLC proxy from URL");
				if (stripos($this->get('via'), 'wiki') !== FALSE ||
					stripos($this->get('via'), 'oclc') !== FALSE) {
				  $this->forget('via');
				}
			  }
		  }
		  if (stripos($this->get($param), 'https://access.newspaperarchive.com/') === 0) {
			$this->set($param, str_ireplace('https://access.newspaperarchive.com/', 'https://www.newspaperarchive.com/', $this->get($param)));
		  }
		  if (stripos($this->get($param), 'http://access.newspaperarchive.com/') === 0) {
			$this->set($param, str_ireplace('http://access.newspaperarchive.com/', 'https://www.newspaperarchive.com/', $this->get($param)));
		  }
		  clean_up_oxford_stuff($this, $param);

		  if (preg_match('~^https?://([^/]+)/~', $this->get($param), $matches)) {
			 $the_host = $matches[1];
		  } else {
			 $the_host = '';
		  }
		  if (stripos($the_host, 'proxy') !== FALSE ||
			  stripos($the_host, 'lib') !== FALSE ||
			  stripos($the_host, 'mutex') !== FALSE) {
				// Generic proxy code www.host.com.proxy-stuff/dsfasfdsfasdfds
			  if (preg_match("~^https?://(www\.[^\./\-]+\.com)\.[^/]*(?:proxy|library|\.lib\.|mutex\.gmu)[^/]*/(\S+)$~i", $this->get($param), $matches)) {
				 report_info("Remove proxy from " . echoable($matches[1]) . " URL");
				 $this->set($param, 'https://' . $matches[1] . '/' . $matches[2]);
				 if ($this->has('via')) {
					 $this->forget('via');
				 }
			  // Generic proxy code www-host-com.proxy-stuff/dsfasfdsfasdfds
			  } elseif (preg_match("~^https?://www\-([^\./\-]+)\-com[\.\-][^/]*(?:proxy|library|\.lib\.|mutex\.gmu)[^/]*/(\S+)$~i", $this->get($param), $matches)) {
				 $matches[1] = 'www.' . $matches[1] . '.com';
				 report_info("Remove proxy from " . echoable($matches[1]) . " URL");
				 $this->set($param, 'https://' . $matches[1] . '/' . $matches[2]);
				 if ($this->has('via')) {
					 $this->forget('via');
				 }
			  }
		  }
		  if (stripos($this->get($param), 'galegroup') !== FALSE) {
			if (preg_match("~^(?:http.+url=|)https?://go.galegroup.com(%2fps.+)$~i", $this->get($param), $matches)) {
				 $this->set($param, 'https://go.galegroup.com' . urldecode($matches[1]));
				 report_info("Remove proxy from Gale URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
				 if ($this->has('via') && stripos($this->get('via'), 'gale') === FALSE) $this->forget('via');
			} elseif (preg_match("~^http.+url=https?://go\.galegroup\.com/(.+)$~i", $this->get($param), $matches)) {
				 $this->set($param, 'https://go.galegroup.com/' . $matches[1]);
				 report_info("Remove proxy from Gale URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
				 if ($this->has('via') && stripos($this->get('via'), 'gale') === FALSE) $this->forget('via');
			} elseif (preg_match("~^(?:http.+url=|)https?://link.galegroup.com(%2fps.+)$~i", $this->get($param), $matches)) {
				 $this->set($param, 'https://link.galegroup.com' . urldecode($matches[1]));
				 report_info("Remove proxy from Gale URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
				 if ($this->has('via') && stripos($this->get('via'), 'gale') === FALSE) $this->forget('via');
			} elseif (preg_match("~^http.+url=https?://link\.galegroup\.com/(.+)$~", $this->get($param), $matches)) {
				 $this->set($param, 'https://link.galegroup.com/' . $matches[1]);
				 report_info("Remove proxy from Gale URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
				 if ($this->has('via') && stripos($this->get('via'), 'gale') === FALSE) $this->forget('via');
			}
		  }
		  if (stripos($this->get($param), 'proquest') !== FALSE) {
			if (preg_match("~^(?:http.+/login/?\?url=|)https?://(?:0\-|)(?:search|www).proquest.com[^/]+(|/[^/]+)+/docview/(.+)$~", $this->get($param), $matches)) {
				 $this->set($param, 'https://www.proquest.com' . $matches[1] . '/docview/' . $matches[2]);
				 report_info("Remove proxy from ProQuest URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
				 if ($this->has('via') && stripos($this->get('via'), 'proquest') === FALSE) $this->forget('via');
			} elseif (preg_match('~^https?://(.*)proquest.umi.com(.*)/(pqd.+)$~', $this->get($param), $matches)) {
			   if ($matches[1] || $matches[2]) {
				 $this->set($param, 'http://proquest.umi.com/' . $matches[3]);
				 report_info("Remove proxy from ProQuest URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
				 if ($this->has('via') && stripos($this->get('via'), 'proquest') === FALSE) $this->forget('via');
			   }
			} elseif (preg_match("~^(?:http.+/login/?\?url=|)https?://(?:0\-|)(?:www|search).proquest.+scoolaid\.net(|/[^/]+)+/docview/(.+)$~", $this->get($param), $matches)) {
				 $this->set($param, 'https://www.proquest.com' . $matches[1] . '/docview/' . $matches[2]);
				 report_info("Remove proxy from ProQuest URL");
				 if ($this->has('via') && stripos($this->get('via'), 'library') !== FALSE) $this->forget('via');
				 if ($this->has('via') && stripos($this->get('via'), 'proquest') === FALSE) $this->forget('via');
			}
			$changed = FALSE;
			if (preg_match("~^https?://(?:|search|www).proquest.com/(.+)/docview/(.+)$~", $this->get($param), $matches)) {
			  if ($matches[1] !== 'dissertations') {
				 $changed = TRUE;
				 $this->set($param, 'https://www.proquest.com/docview/' . $matches[2]); // Remove specific search engine
			  }
			}
			if (preg_match("~^https?://(?:search|www)\.proquest\.com/docview/(.+)/(?:abstract|fulltext|preview|page).*$~i", $this->get($param), $matches)) {
				 $changed = TRUE;
				 $this->set($param, 'https://www.proquest.com/docview/' . $matches[1]); // You have to login to get that
			}
			if (preg_match("~^https?://(?:search|www)\.proquest\.com/docview/(.+)\?.+$~", $this->get($param), $matches)) {
				 $changed = TRUE;
				 $this->set($param, 'https://www.proquest.com/docview/' . $matches[1]); // User specific information
			}
			if (preg_match("~^https?://(?:www|search)\.proquest\.com/docview/([0-9]+)/[0-9A-Z]+/[0-9]+\??$~", $this->get($param), $matches)) {
				 $changed = TRUE;
				 $this->set($param, 'https://www.proquest.com/docview/' . $matches[1]); // User specific information
			}
			if (preg_match("~^https?://search\.proquest\.com/docview/(.+)$~", $this->get($param), $matches)) {
				 $changed = TRUE;
				 $this->set($param, 'https://www.proquest.com/docview/' . $matches[1]);
			}
			if (preg_match("~^https?://search\.proquest\.com/dissertations/docview/(.+)$~", $this->get($param), $matches)) {
				 $changed = TRUE;
				 $this->set($param, 'https://www.proquest.com/dissertations/docview/' . $matches[1]);
			}
			if (preg_match("~^https?://search\.proquest\.com/openview/(.+)$~", $this->get($param), $matches)) {
				 $changed = TRUE;
				 $this->set($param, 'https://www.proquest.com/openview/' . $matches[1]);
			}
			if (preg_match("~^(https://www\.proquest\.com/docview/.+)\?$~", $this->get($param), $matches)) {
				 $changed = TRUE;
				 $this->set($param, $matches[1]);
			}
			if (preg_match("~^https?://proquest\.umi\.com/.*$~", $this->get($param), $matches)) {
				 $ch = curl_init_array(1.5,
						 [CURLOPT_COOKIEFILE => 'cookie.txt', // Needed for proquest
						  CURLOPT_URL => $matches[0]]);
				 if (@curl_exec($ch)) {
					$redirectedUrl = (string) @curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);  // Final URL
					if (preg_match("~^https?://.+(\.proquest\.com/docview/\d{4,})(?:|/abstract.*|/fulltext.*|/preview.*)$~", $redirectedUrl, $matches) ||
						preg_match("~^https?://.+(\.proquest\.com/openurl/handler/.+)$~", $redirectedUrl, $matches)) {
					   $changed = TRUE;
					   $this->set($param, 'https://search' . $matches[1]);
					   if (stripos($this->get('id'), 'Proquest Document ID') !== FALSE) $this->forget('id');
					} elseif (preg_match("~^https?://.+\.proquest\.com(?:|/)$~", $redirectedUrl)) {
					   $changed = TRUE;
					   report_forget('Proquest.umi.com URL does not work.  Forgetting');
					   $this->forget($param);
					}
				 }
				 unset($ch);
			}
			if (preg_match("~^(.+)/se-[^\/]+/?$~", $this->get($param), $matches)) {
			  $this->set($param, $matches[1]);
			  $changed = TRUE;
			}
			if ($changed) report_info("Normalized ProQuest URL");
		  }
		  if ($param === 'url' && $this->wikiname() === 'cite book' && $this->should_url2chapter(FALSE)) {
			$this->rename('url', 'chapter-url');
			// Comment out because "never used" $param = 'chapter-url';
			return;
		  }
		  $the_new_url = $this->get('url');
		  if ($the_original_url !== $the_new_url) {
			$this->get_identifiers_from_url();
		  }
		  if (stripos($this->get('url'), 'cinemaexpress.com') !== FALSE) {
		   foreach (WORK_ALIASES as $worky) {
			$lower = strtolower($this->get($worky));
			if ($lower === 'the new indian express' ||
				$lower === '[[the new indian express]]' ||
				$lower === 'm.cinemaexpress.com' ||
				$lower === 'cinemaexpress.com' ||
				$lower === 'www.cinemaexpress.com') {
			  $this->set($worky, '[[Cinema Express]]');
			}
		   }
		  }
		  return;

		case 'work':
		  if ($this->has('work')
		  && (  str_equivalent($this->get('work'), $this->get('series'))
			 || str_equivalent($this->get('work'), $this->get('title'))
			 || str_equivalent($this->get('work'), $this->get('journal'))
			 || str_equivalent($this->get('work'), $this->get('website'))
			 )
		  ) {
			$this->forget('work');
			return;
		  }
		  if ($this->get('work') === 'The Times Digital Archive') {
			$this->set('work', '[[The Times]]');
		  }
		  if (strtolower($this->get('work')) === 'latimes' ||
			  strtolower($this->get('work')) === 'latimes.com') {
			$this->set('work', '[[Los Angeles Times]]');
		  }
		  if (strtolower($this->get('work')) === 'nytimes' ||
			  strtolower($this->get('work')) === 'nytimes.com') {
			$this->set('work', '[[The New York Times]]');
		  }

		  switch ($this->wikiname()) {
			case 'cite book': $work_becomes = 'title'; break;
			case 'cite journal': $work_becomes = 'journal'; break;
			// case 'cite web': $work_becomes = 'website'; break;  this change should correct, but way too much crap gets put in work that does not belong there.  Secondly this make no change to the what the user sees
			default: $work_becomes = 'work';
		  }
		  if ($this->has('url') && str_replace(ENCYCLOPEDIA_WEB, '', $this->get('url')) !== $this->get('url')) {
			$work_becomes = 'encyclopedia';
		  }
		  if ($this->has('work') && str_ireplace(['encyclopedia', 'encyclopædia', 'encyclopaedia'], '', $this->get('work')) !== $this->get('work')) {
			$work_becomes = 'encyclopedia';
		  }

		  if ($this->has_but_maybe_blank($param) && $this->blank($work_becomes)) {
			if ($work_becomes === 'encyclopedia' && $this->wikiname() === 'cite web') {
			  $this->change_name_to('cite encyclopedia');
			}
			if ($work_becomes !== 'encyclopedia' || in_array($this->wikiname(), ['cite dictionary', 'cite encyclopedia', 'citation'])) {
			  $this->rename('work', $work_becomes); // encyclopedia=XYZ only valid in some citation types
			}
		  }
		  if ($this->wikiname() === 'cite book') {
			$publisher = strtolower($this->get($param));
			foreach (NON_PUBLISHERS as $not_publisher) {
			  if (stripos($publisher, $not_publisher) !== FALSE) {
				$this->forget($param);
				return;
			  }
			}
			if (stripos($publisher, 'amazon') !== FALSE) {
			  $this->forget($param);
			  return;
			}
		  }
		  if ($this->blank('agency') && in_array(strtolower(str_replace(array('[', ']', '.'), '', $this->get($param))), ['reuters', 'associated press'])) {
			$the_url = '';
			foreach (ALL_URL_TYPES as $thingy) {
			  $the_url .= $this->get($thingy);
			}
			if (stripos($the_url, 'reuters.com') === FALSE && stripos($the_url, 'apnews.com') === FALSE) {
			   $this->rename($param, 'agency');
			}
		  }
		  $the_param = $this->get($param);
		  if (preg_match(REGEXP_PLAIN_WIKILINK, $the_param, $matches) || preg_match(REGEXP_PIPED_WIKILINK, $the_param, $matches)) {
			  $the_param = $matches[1]; // Always the wikilink for easier standardization
		  }
		  if (in_array(strtolower($the_param), ARE_MAGAZINES) && $this->blank(['pmc','doi','pmid'])) {
			$this->change_name_to('cite magazine');
			$this->rename($param, 'magazine');
			return;
		  } elseif (in_array(strtolower($the_param), ARE_NEWSPAPERS)) {
			$this->change_name_to('cite news');
			$this->rename($param, 'newspaper');
			return;
		  }

		  if (strtolower($the_param) === 'www.pressreader.com' ||
			   strtolower($the_param) === 'pressreader.com' ||
			   strtolower($the_param) === 'pressreader.com (archived)' ||
			   strtolower($the_param) === 'www.pressreader.com/'
			  ) {
			  if ($this->blank('via')) {
				 $this->set($param, 'PressReader');
				 $this->rename($param, 'via');
			  } elseif (stripos($this->get('via'), 'pressreader') !== FALSE) {
				 $this->forget($param);
			  }
		  }

		  return;

		case 'via':  // Should just remove all 'via' with no url, but do not want to make people angry
		  if ($this->blank(ALL_URL_TYPES)) { // Include blank via
			if (stripos($this->get('via'), 'PubMed') !== FALSE && ($this->has('pmc') || $this->has('pmid'))) {
			  $this->forget('via');
			} elseif (stripos($this->get('via'), 'JSTOR') !== FALSE && $this->has('jstor')) {
			  $this->forget('via');
			} elseif (stripos($this->get('via'), 'google book') !== FALSE && $this->has('isbn')) {
			  $this->forget('via');
			} elseif (stripos($this->get('via'), 'questia') !== FALSE && $this->has('isbn')) {
			  $this->forget('via');
			} elseif (stripos($this->get('via'), 'library') !== FALSE) {
			  $this->forget('via');
			} elseif (in_array($this->wikiname(), ['cite arxiv', 'cite biorxiv', 'cite citeseerx', 'cite ssrn'])) {
			  $this->forget('via');
			} elseif ($this->has('pmc') || $this->has('pmid') || ($this->has('doi') && $this->blank(DOI_BROKEN_ALIASES)) ||
					  $this->has('jstor') || $this->has('arxiv') || $this->has('isbn') || ($this->has('issn') && $this->has('title')) ||
					  $this->has('oclc') || $this->has('lccn') || $this->has('bibcode')) {
			  $via = trim(str_replace(array('[',']'),'', strtolower($this->get('via'))));
			  if (in_array($via, ['', 'project muse', 'wiley', 'springer', 'questia', 'elsevier', 'wiley online library',
								  'wiley interscience', 'interscience', 'sciencedirect', 'science direct', 'ebscohost',
								  'proquest', 'google scholar', 'google', 'bing', 'yahoo']))
			  {
				$this->forget('via');
				return;
			  }
			}
		  }
		  if ($this->blank('via')) return;
		  foreach (array_merge( array('publisher'), WORK_ALIASES) as $others) {
			if ($this->has($others)) {
			  if (str_equivalent($this->get($others), $this->get('via')) ||
				  (stripos($this->get($others), 'bbc') !== FALSE && stripos($this->get('via'), 'bbc')) !== FALSE) {
				$this->forget('via');
				return;
			  }
			}
		  }
		  if (str_i_same('DOI.org (Crossref)', $this->get('via'))) $this->forget('via');
		  return;
		case 'volume':
		  if ($this->blank($param)) return;
		  if ($this->get($param) === 'Online First') {
			$this->forget($param);
			return;
		  }
		  $temp_string = strtolower($this->get('journal')) ;
		  if(substr($temp_string, 0, 2) === "[[" && substr($temp_string, -2) === "]]") {  // Wikilinked journal title
			   $temp_string = substr(substr($temp_string, 2), 0, -2); // Remove [[ and ]]
		  }
		  if (in_array($temp_string, HAS_NO_VOLUME)) {
			if ($this->blank(ISSUE_ALIASES)) {
			  $this->rename('volume', 'issue');
			} else {
			  $this->forget('volume');
			  return;
			}
		  }
		  if (in_array($temp_string, PREFER_VOLUMES) && $this->has('volume')) {
			if ($this->get('volume') === $this->get('issue')) {
			  $this->forget('issue');
			} elseif ($this->get('volume') === $this->get('number')) {
			  $this->forget('number');
			}
		  }
		  if (in_array($temp_string, PREFER_ISSUES) && $this->has('volume')) {
			if ($this->get('volume') === $this->get('issue')) {
			  $this->forget('volume');
			} elseif ($this->get('volume') === $this->get('number')) {
			  $this->forget('volume');
			}
		  }
		  // Remove leading zeroes
		  $value = $this->get($param);
		  if ($value !== '') {
			$value = safe_preg_replace('~^0+~', '', $value);
			if ($value === '') {
			  $this->forget($param); // Was all zeros
			}
		  }
		  $this->volume_issue_demix($this->get($param), $param);
		  return;

		case 'year':
		  if ($this->blank($param)) {
			if ($this->has('date')) $this->forget('year');
			return;
		  }
		  if (preg_match("~\d\d*\-\d\d*\-\d\d*~", $this->get('year'))) { // We have more than one dash, must not be range of years.
			 if ($this->blank('date')) $this->rename('year', 'date');
			 $this->forget('year');
			 return;
		  }
		  if (preg_match("~[A-Za-z][A-Za-z][A-Za-z]~", $this->get('year'))) { // At least three letters
			 if ($this->blank('date')) $this->rename('year', 'date');
			 $this->forget('year');
			 return;
		  }
		  if (preg_match("~^(\d{4})\.$~", $this->get($param), $matches)) {
			 $this->set($param, $matches[1]); // trailing period
			 return;
		  }
		  if ($this->get($param) === 'n.d.') return; // Special no-date code that citation template recognize.
		  // Issue should follow year with no break.  [A bit of redundant execution but simpler.]
		case 'issue':
		case 'number':
		  if ($this->blank($param)) return;
		  $value = $this->get($param);
		  if ($value === 'Online First') {
			$this->forget($param);
			return;
		  }
		  if ($param === 'issue' || $param === 'number') {
			if (preg_match('~^(?:iss\.|iss|issue|number|num|num\.|no|no:|no\.|№|№\.)\s*(\d+)$~iu', $value, $matches)) {
			  $value = $matches[1];
			}
		  }
		  // Remove leading zeroes
		  if ($value && $this->get('journal') !== 'Insecta Mundi') {
			$value = safe_preg_replace('~^0+~', '', $value);
			if ($value === '') {
			  $this->forget($param); // Was all zeros
			}
		  }
		  if ($value) {
			$this->set($param, $value);
		  } else {
			if (!$this->blank($param)) $this->forget($param);
			return;
		  }
		  if ($param === 'issue' || $param === 'number') {
			$this->volume_issue_demix($this->get($param), $param);
			if ($this->blank($param)) {
			  $this->forget($param);
			  return;
			}
			$temp_string = mb_strtolower($this->get('journal')) ;
			if (substr($temp_string, 0, 2) === "[[" && substr($temp_string, -2) === "]]") {  // Wikilinked journal title
			   $temp_string = substr(substr($temp_string, 2), 0, -2); // Remove [[ and ]]
			}
			if (in_array($temp_string, HAS_NO_ISSUE)) {
			  if ($this->blank('volume')) {
				$this->rename($param, 'volume');
			  } else {
				$this->forget($param);
			  }
			  return;
			}
			if (in_array($temp_string, PREFER_VOLUMES) && $this->has('volume')) {
			  if ($this->get('volume') === $this->get($param)) {
				$this->forget($param);
				return;
			  }
			}
			if (in_array($temp_string, PREFER_ISSUES) && $this->has('volume')) {
			  if ($this->get('volume') === $this->get($param)) {
				$this->forget('volume');
				return;
			  }
			}
		  }
		  // No break here: pages, issue and year (the previous case) should be treated in this fashion.
		case 'pages': case 'page': case 'pp': # And case 'year': case 'issue':, following from previous
		  $value = $this->get($param);
		  $value = str_replace('--', '-', $value);
		  if (str_i_same('null', $value)) {
			$this->forget($param);
			return;
		  }
		  if (strpos($value, "[//") === 0) { // We can fix them, if they are the very first item
			$value = "[https://" . substr($value, 3);
			$this->set($param, $value);
		  }
		  if (preg_match('~^p\.?p\.?[(?:&nbsp;)\s]*(\d+[–-]?\d+)$~u' , $value, $matches)) {
			$value = $matches[1];
			$this->set($param, $value);
		  }
		  if (preg_match('~^[Pp]ages?[\.\:]? *(\d+[–-]?\d+)$~u' , $value, $matches)) {
			$value = $matches[1];
			$this->set($param, $value);
		  }
		  if (preg_match('~^p\.? *(\d+[–-]?\d+)$~u' , $value, $matches)) {
			$value = $matches[1];
			$this->set($param, $value);
		  }
		  if (!preg_match("~^[A-Za-z ]+\-~", $value) && mb_ereg(REGEXP_TO_EN_DASH, $value)
			  && can_safely_modify_dashes($value) && ($pmatch[1] !== 'page')) {
			$this->mod_dashes = TRUE;
			report_modification("Upgrading to en-dash in " . echoable($param) . " parameter");
			$value =  mb_ereg_replace(REGEXP_TO_EN_DASH, REGEXP_EN_DASH, $value);
			$this->set($param, $value);
		  }
		  if ((mb_substr_count($value, "–") === 1) // Exactly one EN_DASH.
			  && can_safely_modify_dashes($value)) {
			if ($pmatch[1] === 'page') {
			  $bad = TRUE;
			  if (preg_match('~^(\d+)\–(\d+)$~', $value, $matches_dash)) {
				$part1 = (integer) $matches_dash[1];
				$part2 = (integer) $matches_dash[2];
				if (($matches_dash[1][0] !== '0') && ($matches_dash[2][0] !== '0') &&
				  ($part1 < $part2) &&
				  ($part1 > 9)) { // Probably not a section
				  $this->rename($param, 'pages');
				  $bad = FALSE;
				}
			  }
			  if ($bad) report_warning('Perhaps page= of ' . echoable($value) . ' is actually a page range. If so, change to pages=, otherwise change minus sign to {{hyphen}}');
			} else {
			  $the_dash = (int) mb_strpos($value, "–"); // ALL must be mb_ functions because of long dash
			  $part1 = trim(mb_substr($value, 0, $the_dash));
			  $part2 = trim(mb_substr($value, $the_dash + 1));
			  if ($part1 === $part2) {
				$this->set($param, $part1);
			  } elseif (is_numeric($part1) && is_numeric($part2)) {
				$this->set($param, $part1 . "–" . $part2); // Remove any extra spaces
			  }
			}
		  }
		  if (strpos($this->get($param), '&') === FALSE) {
			$this->set($param, safe_preg_replace("~^[.,;]*\s*(.*?)\s*[,.;]*$~", "$1", $this->get($param)));
		  } else {
			$this->set($param, safe_preg_replace("~^[.,;]*\s*(.*?)\s*[,.]*$~", "$1", $this->get($param))); // Not trailing ;
		  }
		  if (mb_substr($this->get($param), -4) === ' etc') {
			$this->set($param, $this->get($param) . '.');
		  }

		  if ($param === 'page' || $param === 'pages') {
			if (preg_match("~^pg\.? +(\d+)$~i", $this->get($param), $matches) || preg_match("~^pg\.? +(\d+–\d+)$~iu", $this->get($param), $matches)) {
			  $this->set($param, $matches[1]);
			}
		  }
		  return;

		case 'pages totales':
		  if ($this->blank($param) || !$this->blank(PAGE_ALIASES)) {
			$this->forget($param);
		  }
		  return;

		case 'postscript': // postscript=. is the default in CS1 templates.  It literally does nothing.
		  if ($this->wikiname() !== 'citation') {
			if ($this->get($param) === '.') $this->forget($param); // Default action does not need specified
			if ($this->blank($param)) $this->forget($param);  // Misleading -- blank means period!!!!
		  }
		  return;

		case 'website':
		  if ($this->get($param) === 'Undefined' || $this->get($param) === 'undefined' || $this->get($param) === 'myprivacy.dpgmedia.nl') {
			$this->forget($param);
			return;
		  }
		  if (($this->wikiname() === 'cite book') && (str_i_same($this->get($param), 'google.com') ||
				str_i_same($this->get($param), 'Google Books') ||
				str_i_same($this->get($param), 'Google Book') ||
				stripos($this->get($param), 'Books.google.') === 0)) {
			$this->forget($param);
			return;
		  }
		  if (stripos($this->get($param), 'archive.org') !== FALSE &&
			  stripos($this->get('url') . $this->get('chapter-url') . $this->get('chapterurl'), 'archive.org') === FALSE) {
			$this->forget($param);
			return;
		  }
		  if (($this->wikiname() === 'cite arxiv') || $this->has('eprint') || $this->has('arxiv')) {
			if (str_i_same($this->get($param), 'arxiv')) {
			  $this->forget($param);
			  return;
			}
		  }
		  if (strtolower($this->get($param)) === 'latimes' ||
			  strtolower($this->get($param)) === 'latimes.com') {
			$this->set($param, '[[Los Angeles Times]]');
			return;
		  }
		  if (strtolower($this->get($param)) === 'nytimes' ||
			  strtolower($this->get($param)) === 'nytimes.com') {
			$this->set($param, '[[The New York Times]]');
			return;
		  }

		  if ($this->get($param) === 'The Times Digital Archive') {
			$this->set($param, '[[The Times]]');
			return;
		  }
		  $the_param = $this->get($param);
		  if (preg_match(REGEXP_PLAIN_WIKILINK, $the_param, $matches) || preg_match(REGEXP_PIPED_WIKILINK, $the_param, $matches)) {
			  $the_param = $matches[1]; // Always the wikilink for easier standardization
		  }
		  if (in_array(strtolower($the_param), ARE_MAGAZINES)) {
			$this->change_name_to('cite magazine');
			$this->rename($param, 'magazine');
			return;
		  } elseif (in_array(strtolower($the_param), ARE_NEWSPAPERS)) {
			$this->change_name_to('cite news');
			$this->rename($param, 'newspaper');
			return;
		  }
		  if ((strtolower($the_param) === 'www.britishnewspaperarchive.co.uk' || strtolower($the_param) === 'britishnewspaperarchive.co.uk') && $this->blank('via')) {
			$this->set($param, '[[British Newspaper Archive]]');
			$this->rename($param, 'via');
			return;
		  }
		  if (strtolower($the_param) === 'www.pressreader.com' ||
			   strtolower($the_param) === 'pressreader.com' ||
			   strtolower($the_param) === 'pressreader.com (archived)' ||
			   strtolower($the_param) === 'www.pressreader.com/'
			  ) {
			  if ($this->blank('via')) {
				 $this->set($param, 'PressReader');
				 $this->rename($param, 'via');
			  } elseif (stripos($this->get('via'), 'pressreader') !== FALSE) {
				 $this->forget($param);
			  }
			return;
		  }

		  if ( strtolower($the_param) === 'www.sify.com' ||
			   strtolower($the_param) === 'sify.com' ||
			   strtolower($the_param) === 'sify'
			  ) {
			  $this->set($param, '[[Sify]]');
			  return;
		  }
		  if ( strtolower($the_param) === 'www.bollywoodhungama.com' ||
			   strtolower($the_param) === 'bollywoodhungama.com' ||
			   strtolower($the_param) === 'bollywoodhungama'
			  ) {
			  $this->set($param, '[[Bollywood Hungama]]');
			  $authtmp = $this->get('author');
			  if ($authtmp === 'Bollywood Hungama News Network' || $authtmp === 'Bollywood Hungama') {
				$this->forget('author');
			  }
			  return;
		  } elseif (stripos($the_param, 'bollywoodhungama') !== FALSE || stripos($the_param, 'bollywood hungama') !== FALSE) {
			  $authtmp = $this->get('author');
			  if ($authtmp === 'Bollywood Hungama News Network' || $authtmp === 'Bollywood Hungama') {
				$this->forget('author');
			  }
		  }
		  if ( strtolower($the_param) === 'www.sciencedirect.com' ||
			   strtolower($the_param) === 'sciencedirect.com' ||
			   strtolower($the_param) === 'sciencedirect'
			  ) {
			if ($this->has('isbn')) {
			   $this->forget($param);
			}
		  }

		  if ( strtolower($the_param) === 'ieeexplore.ieee.org') {
			if ($this->has('isbn') || $this->has('doi') || $this->has('s2cid')) {
			   $this->forget($param);
			}
		  }

		  return;

		case 'location':
		  // Check if it is a URL
		  $the_param = $this->get($param);
		  if (preg_match(REGEXP_IS_URL, $the_param) !== 1) return; // complete
		  if ($this->has('url')) {
			 $url = $this->get('url');
			 if (strpos($url, $the_param) === 0) {
			   $this->forget($param);
			 } elseif (strpos($the_param, $url) === 0) {
			   $this->rename($param, 'url');
			 }
		  } else {
			 $this->rename($param, 'url');
		  }
		  return;

		case 'article-url':
		case 'conference-url':
		case 'conferenceurl':
		case 'contribution-url':
		case 'contributionurl':
		case 'entry-url':
		case 'event-url':
		case 'eventurl':
		case 'lay-url':
		case 'layurl':
		case 'map-url':
		case 'mapurl':
		case 'section-url':
		case 'sectionurl':
		case 'transcript-url':
		case 'transcripturl':
		case 'URL':
		  if (preg_match('~^(?:web\.|www\.).+$~', $this->get($param), $matches) && stripos($this->get($param), 'citation') === FALSE) {
			  $this->set($param, 'http://' . $matches[0]);
		  }
		  return;

	  }
	}
  }

  public function tidy() : void {
	// Should only be run once (perhaps when template is first loaded)
	// Future tidying should occur when parameters are added using tidy_parameter.
	// Called in final_tidy when the template type is changed
	// We do this again when anything changes - up to three times
	$orig = $this->parsed_text();
	foreach ($this->param as $param) {
	  $this->tidy_parameter($param->param);
	}
	$new = $this->parsed_text();
	if ($orig !== $new) {
	  $orig = $new;
	  foreach ($this->param as $param) {
		$this->tidy_parameter($param->param);
	  }
	}
	$new = $this->parsed_text();
	if ($orig !== $new) {
	  foreach ($this->param as $param) {
		$this->tidy_parameter($param->param);
	  }
	} // Give up tidy after third time. Something is goofy.
  }

  public function final_tidy() : void {
	set_time_limit(120);
	if ($this->should_be_processed()) {
	  if ($this->initial_name !== $this->name) {
		 $this->tidy();
	  }
	  // Sometimes title and chapter come from different databases
	  if ($this->has('chapter') && ($this->get('chapter') === $this->get('title'))) {  // Leave only one
		if ($this->wikiname() === 'cite book' || $this->has('isbn')) {
			$this->forget('title');
		} elseif ($this->wikiname() === 'cite journal' || $this->wikiname() === 'citation') {
		  $this->forget('chapter');
		}
	  }
	  // Sometimes series and journal come from different databases
	  if ($this->has('series') && $this->has('journal') &&
		  (str_equivalent($this->get('series'), $this->get('journal')))) { // Leave only one
		if ($this->wikiname() === 'cite book' || $this->has('isbn')) {
			$this->forget('journal');
		} elseif ($this->wikiname() === 'cite journal'|| $this->wikiname() === 'citation') {
		  $this->forget('series');
		}
	  }
	  if ($this->has('journal') && str_equivalent($this->get('title'), $this->get('journal'))) {
		if ($this->wikiname() === 'cite book' || $this->has('isbn')) {
			$this->forget('journal');
		}
	  }
	  // Double check these troublesome "journals"
	  if (($this->is_book_series('journal') || $this->is_book_series('series') ||
		   $this->is_book_series('chapter') || $this->is_book_series('title')) ||
		  ($this->wikiname() !== 'cite book' && $this->wikiname() !== 'citation' && $this->has('chapter'))) {
		// Do it twice - since things change
		$this->tidy_parameter('series');
		$this->tidy_parameter('journal');
		$this->tidy_parameter('title');
		$this->tidy_parameter('chapter');
		$this->tidy_parameter('series');
		$this->tidy_parameter('journal');
		$this->tidy_parameter('title');
		$this->tidy_parameter('chapter');
	  }
	  // "Work is a troublesome parameter
	  if ($this->has_but_maybe_blank('work') && $this->blank('work')) { // Have work=, but it is blank
		 if ($this->has('journal') ||
			 $this->has('newspaper') ||
			 $this->has('magazine') ||
			 $this->has('periodical') ||
			 $this->has('website')) {
			  $this->forget('work'); // Delete if we have alias
		 } elseif ($this->wikiname() === 'cite web') {
			$this->forget('work'); // The likelihood of this being a good thing to add is very low
		 } elseif ($this->wikiname() === 'cite journal') {
			$this->rename('work', 'journal');
		 }
	  }
	  if ($this->has(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
		if ($this->has('title') || $this->has('chapter')) {
		  $this->quietly_forget(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
		}
	  }
	  if ($this->get('issue') === 'n/a' && preg_match('~^\d+$~', $this->get('volume'))) {
		$this->forget('issue');
	  }
	  if ($this->get('volume') === 'n/a' && preg_match('~^\d+$~', $this->get('issue'))) {
		$this->forget('volume');
	  }
	  if ($this->has('doi') && $this->has('issue') && ($this->get('issue') === $this->get('volume')) && // Issue = Volume and not NULL
		($this->get('issue') === $this->get_without_comments_and_placeholders('issue')) &&
		($this->get('volume') === $this->get_without_comments_and_placeholders('volume'))) { // No comments to flag problems
		$crossRef = query_crossref($this->get_without_comments_and_placeholders('doi'));
		if ($crossRef) {
		  $orig_data = trim($this->get('volume'));
		   $possible_issue = trim((string) @$crossRef->issue);
		   $possible_volume = trim((string) @$crossRef->volume);
		   if ($possible_issue !== $possible_volume) { // They don't match
			 if ((strpos($possible_issue, '-') > 0 || (int) $possible_issue > 1) && (int) $possible_volume > 0) { // Legit data
			   if ($possible_issue === $orig_data) {
				 $this->set('volume', $possible_volume);
				 report_action('Citation had volume and issue the same. Changing volume.');
			   } elseif ($possible_volume === $orig_data) {
				 $this->set('issue', $possible_issue);
				 report_action('Citation had volume and issue the same. Changing issue.');
			   } else {
				 report_inaction('Citation has volume and issue set to ' . echoable($orig_data) . ' which disagrees with CrossRef');  // @codeCoverageIgnore
			   }
			 }
		   }
		}
	  }
	  $this->tidy_parameter('url'); // depending upon end state, convert to chapter-url
	  if ($this->has_good_free_copy()) { // One last try to drop URLs
		$url = $this->get('url');
		if ($url !== str_ireplace(['nih.gov', 'pubmed', 'pmc', 'doi'], '', $url)) {
		  $this->get_identifiers_from_url();
		}
	  }
	  $this->tidy_parameter('via');
	  $this->tidy_parameter('publisher');
	  if ($this->has('publisher') && preg_match("~^([\'\"]+)([^\'\"]+)([\'\"]+)$~u", $this->get('publisher'), $matches)) {
		if ($this->blank(WORK_ALIASES)) {
		  $this->rename('publisher', 'work', $matches[2]);
		  $this->tidy_parameter('work');
		} else {
		  $this->set('publisher', $matches[2]);
		  $this->tidy_parameter('publisher');
		}
	  }
	  if ($this->wikiname() === 'cite journal' && $this->blank(WORK_ALIASES) &&
		  stripos($this->initial_name, 'journal') === FALSE) {
		 if ($this->has('arxiv') || $this->has('eprint')) {
			$this->change_name_to('cite arxiv');
		 } else {
			$this->change_name_to($this->initial_name);
		 }
	  }
	  if (($this->wikiname() === 'cite document' || $this->wikiname() === 'cite journal' || $this->wikiname() === 'cite web') &&
		  (strpos($this->get('isbn'), '978-0-19') === 0 || strpos($this->get('isbn'), '978019') === 0 || strpos($this->get('isbn'), '978-019') === 0)) {
		 $this->change_name_to('cite book', TRUE, TRUE);
	  }
	  if ($this->blank('pmc-embargo-date')) $this->forget('pmc-embargo-date'); // Do at the very end, so we do not delete it, then add it later in a different position
	  if ($this->wikiname() === 'cite arxiv' && $this->get_without_comments_and_placeholders('doi') && stripos($this->get_without_comments_and_placeholders('doi'), 'arxiv') === FALSE) {
		$this->change_name_to('cite journal');
	  }
	  if ($this->wikiname() === 'cite arxiv' && $this->has('bibcode')) {
		$this->forget('bibcode'); // Not supported and 99% of the time just a arxiv bibcode anyway
	  }
	  if ($this->wikiname() === 'cite web') {
	   if (!$this->blank_other_than_comments('title') && !$this->blank_other_than_comments('chapter')) {
		  if ($this->name === 'cite web') { // Need special code to keep caps the same
			$this->name = 'cite book';
		  } else {
			$this->name = 'Cite book';
		  }
	   }
	   if (($this->has('arxiv') || $this->has('eprint')) && (stripos($this->get('url'), 'arxiv') !== FALSE)) {
		  if ($this->name === 'cite web') {
			$this->name = 'cite arXiv';
		  } else {
			$this->name = 'Cite arXiv';
		  }
		  $this->quietly_forget('url');
	   }
	  }
	  if (!$this->blank(DOI_BROKEN_ALIASES) && $this->has('jstor') &&
		(strpos($this->get('doi'), '10.2307') === 0 || $this->get('doi') === $this->get('jstor') ||
		 substr($this->get('doi'), 0, -2) === $this->get('jstor') || substr($this->get('doi'), 0, -3) === $this->get('jstor'))) {
	   $this->forget('doi'); // Forget DOI that is really jstor, if it is broken
	   foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
	  }
	  if ($this->has('journal') && stripos($this->get('journal'), 'arxiv') === FALSE) { // Do this at the very end of work in case we change type/etc during expansion
		  if ($this->blank(['chapter', 'isbn'])) {
			// Avoid renaming between cite journal and cite book
			$this->change_name_to('cite journal');
			// Remove blank stuff that will most likely never get filled in
			$this->forget('isbn');
			$this->forget('chapter');
			foreach (['location', 'place', 'publisher', 'publication-place', 'publicationplace'] as $to_drop) {
			  if ($this->blank($to_drop)) $this->forget($to_drop);
			}
		  } elseif (in_array(strtolower($this->get('journal')), array_merge(NON_PUBLISHERS, BAD_TITLES, DUBIOUS_JOURNALS, ['amazon.com']))) {
			report_forget('Citation has chapter/ISBN already, dropping dubious Journal title: ' . echoable($this->get('journal')));
			$this->forget('journal');
		  } else {
			report_warning(echoable('Citation should probably not have journal = ' . $this->get('journal')
			. ' as well as chapter / ISBN ' . $this->get('chapter') . ' ' .  $this->get('isbn')));
		  }
	  }
	  if ($this->wikiname() === 'cite book' && $this->blank(['issue', 'journal'])) {
	   // Remove blank stuff that will most likely never get filled in
	   $this->forget('issue');
	   $this->forget('journal');
	  }
	  if (preg_match('~^10\.1093/ref\:odnb/\d+$~', $this->get('doi')) &&
		$this->has('title') &&
		$this->wikiname() !== 'cite encyclopedia' &&
		$this->wikiname() !== 'cite encyclopaedia') {
	   preg_match("~^(\s*).*\b(\s*)$~", $this->name, $spacing);
	   if (substr($this->name,0,1) === 'c') {
		$this->name = $spacing[1] . 'cite ODNB' . $spacing[2];
	   } else {
		$this->name = $spacing[1] . 'Cite ODNB' . $spacing[2];
	   }
	   foreach (array_diff(WORK_ALIASES, array('encyclopedia','encyclopaedia')) as $worker) {
		$this->forget($worker);
	   }
	   if (stripos($this->get('publisher'), 'oxford') !== FALSE) $this->forget('publisher');
	   $this->forget('dictionary');
	  }
	  if (preg_match('~^10\.1093/~', $this->get('doi')) &&
		$this->has('title') &&
		($this->wikiname() === 'cite web' || $this->wikiname() === 'cite journal') &&
		$this->blank(WORK_ALIASES) && $this->blank('url')) {
		preg_match("~^(\s*).*\b(\s*)$~", $this->name, $spacing);
		if ($this->has('chapter')) {
		  if (substr($this->name,0,1) === 'c') {
			$this->name = $spacing[1] . 'cite book' . $spacing[2];
		  } else {
			$this->name = $spacing[1] . 'Cite book' . $spacing[2];
		  }
		} else {
		  if (substr($this->name,0,1) === 'c') {
			$this->name = $spacing[1] . 'cite document' . $spacing[2];
		  } else {
			$this->name = $spacing[1] . 'Cite document' . $spacing[2];
		  }
		}
	  }
	  if (conference_doi($this->get('doi')) &&
		$this->has('title') && $this->has('chapter') && $this->has('isbn') &&
		$this->wikiname() === 'cite book' && doi_works($this->get('doi'))) {
		  foreach (WORK_ALIASES as $worky) {
			foreach (['Conference', 'Symposium', 'SIGGRAPH', 'workshop'] as $thingy) {
			  if (stripos($this->get('title'), $thingy) !== FALSE && stripos($this->get($worky), $thingy) !== FALSE) {
				$this->forget($worky);
			  }
			}
		  }
	  }
	  if (stripos($this->get('journal'), 'SIGGRAPH') !== FALSE && stripos($this->get('title'), 'SIGGRAPH') !== FALSE) {
		  $this->forget('journal');
	  }
	  if (stripos($this->get('journal'), 'SIGGRAPH') !== FALSE && $this->blank('title')) {
		  $this->rename('journal', 'title');
	  }
	  if ($this->has('series') && stripos($this->get('title'), $this->get('series')) !== FALSE && 'Surtees Society' !== $this->get('series')) {
		  $this->forget('series');
	  }

	  $this->tidy_parameter('doi'); // might be free, and freedom is date dependent for some journals
	  if ($this->blank(PAGE_ALIASES) && preg_match('~^10\.1103\/[a-zA-Z]+\.(\d+)\.(\d+)$~', $this->get('doi'), $matches)) {
		if ($matches[1] === $this->get('volume')) {
		   $this->set('page', $matches[2]); // Often not in CrossRef
		}
	  }
	  if ($this->wikiname() === 'cite book') {
		foreach (WORK_ALIASES as $worky) {
		  if ($this->blank($worky)) $this->forget($worky); // Discourage filling these in
		  if (strtolower($this->get('publisher')) === strtolower($this->get($worky))) $this->forget($worky);
		}
		// If one and only one work alias is set, the move it to publisher
		/**
		if ($this->blank('publisher')) {
		  $counting = 0;
		  foreach (WORK_ALIASES as $worky) {
			if ($this->has($worky)) $counting = $counting + 1;
		  }
		  if ($counting === 1) {
			foreach (WORK_ALIASES as $worky) {
			  //TODO: convert to via/publisher/delete/log depending upon specificsif ($this->has($worky)) bot_debug_log('WORKY ' . $this->get($worky));
			}
		  }
		}
		**/
	  } elseif ($this->has('publisher')) {
		foreach (WORK_ALIASES as $worky) {
		  if (strtolower($this->get('publisher')) === strtolower($this->get($worky))) $this->forget('publisher');
		}
	  }
	  if (!empty($this->param)) {
		$drop_me_maybe = array();
		foreach (ALL_ALIASES as $alias_list) {
		  if (!$this->blank($alias_list)) { // At least one is set
			$drop_me_maybe = array_merge($drop_me_maybe, $alias_list);
		  }
		}
		if (!$this->incomplete()) {
		  $drop_me_maybe = array_merge($drop_me_maybe, LOTS_OF_EDITORS);  // Always drop empty editors at end, if "complete"
		}
		// Do it this way to avoid massive N*M work load (N=size of $param and M=size of $drop_me_maybe) which happens when checking if each one is blank
		foreach ($this->param as $key => $p) {
		 if (@$p->val === '' && in_array(@$p->param, $drop_me_maybe)) {
		   unset($this->param[$key]);
		 }
		}
	  }
	  if (!empty($this->param)) { // Forget author-link and such that have no such author
	   foreach ($this->param as $p) {
		$alias = $p->param;
		if ($alias !== '' && $this->blank($alias)) {
		  if (preg_match('~^author(\d+)\-?link$~', $alias, $matches) || preg_match('~^author\-?link(\d+)$~', $alias, $matches)) {
			if ($this->blank(AUTHOR_PARAMETERS[(int) $matches[1]])) {
			  $this->forget($alias);
			}
		  }
		}
	   }
	  }
	  if ($this->get('newspaper') === 'Reuters') {
		$this->rename('newspaper', 'work');
	  }
	  if (($this->wikiname() === 'cite journal' || $this->wikiname() === 'cite document' || $this->wikiname() === 'cite web') && $this->has('chapter') && $this->blank('title')) {
		$this->rename('chapter', 'title');
	  }
	  if (($this->wikiname() === 'cite journal' || $this->wikiname() === 'cite document' || $this->wikiname() === 'cite web') && $this->has('chapter')) { // At least avoid a template error
		$this->change_name_to('cite book', TRUE, TRUE);
	  }
	  if (($this->wikiname() === 'cite web' || $this->wikiname() === 'cite news') &&
		  $this->blank(WORK_ALIASES) &&
		  $this->blank(['publisher', 'via', 'pmc', 'pmid', 'doi', 'mr', 'asin', 'issn', 'eissn', 'hdl', 'id', 'isbn', 'jfm', 'jstor', 'oclc', 'ol', 'osti', 's2cid', 'ssrn', 'zbl', 'citeseerx', 'arxiv', 'eprint', 'biorxiv']) &&
		  $this->blank(array_diff_key(ALL_URL_TYPES, [0 => 'url'])) &&
		  $this->has('url')
		  ) {
		$url = $this->get('url');
		if (stripos($url, 'CITATION_BOT') === FALSE &&
			!preg_match('~^https?://[^/]+/*?$~', $url) &&  // Ignore just a hostname
			preg_match (REGEXP_IS_URL, $url) === 1 &&
		   preg_match('~^https?://([^/]+)/~', $url, $matches)) {
		   $hostname = mb_strtolower($matches[1]);
		   $hostname = (string) preg_replace('~^(m\.|www\.)~', '', $hostname);
		   if (preg_match('~^https?://([^/]+/+[^/]+)~', $url, $matches)) {
			 $hostname_plus = mb_strtolower($matches[1]);
		   } else {
			 bot_debug_log($url . " generated matches nothing event"); // @codeCoverageIgnore
			 $hostname_plus = 'matches nothing';   // @codeCoverageIgnore
		   }
		   $hostname_plus = (string) preg_replace('~^(m\.|www\.)~', '', $hostname_plus);
		   $hostname_plus = (string) preg_replace('~//+~', '/', $hostname_plus);
		   if (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $hostname) === $hostname &&
			   str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $hostname) === $hostname &&
			   str_ireplace(PROXY_HOSTS_TO_DROP, '', $hostname) === $hostname &&
			   str_ireplace(HOSTS_TO_NOT_ADD, '', $hostname) === $hostname
			 ) {
			 foreach (HOSTNAME_MAP as $i_key => $i_value) { // Scan longer url first
			   if ($hostname_plus === $i_key) {
				 $this->add_if_new('website', $i_value);
			   }
			 }
			 foreach (HOSTNAME_MAP as $i_key => $i_value) { // Scan longer url first
			   if ($hostname === $i_key) {
				 $this->add_if_new('website', $i_value);
			   }
			 }
			 // Special Cases
			 if ($hostname === 'theweek.in') {
				 foreach (WORK_ALIASES as $works) {
					 if (strpos($this->get($works), '[[The Week]]') !== FALSE) {
						 $this->set($works, '[[The Week (Indian magazine)|The Week]]');
					 }
				 }
			 }
		   }
		}
	  }
	  if (($this->get('url-status') === 'live') && $this->blank(['archive-url', 'archivedate', 'archiveurl', 'archived-date'])) {
		 $this->forget('url-status');
	  }
	} elseif (in_array($this->wikiname(), TEMPLATES_WE_SLIGHTLY_PROCESS)) {
	  $this->tidy_parameter('publisher');
	  $this->tidy_parameter('via');
	  if (($this->get('url-status') === 'live') && $this->blank(['archive-url', 'archivedate', 'archiveurl', 'archived-date'])) {
		 $this->forget('url-status');
	  }
	}
  }

  public function verify_doi() : bool {
	set_time_limit(120);
	static $last_doi = '';
	$doi = $this->get_without_comments_and_placeholders('doi');
	if (!$doi) return FALSE;
	if ($this->doi_valid) return TRUE;
	if ($last_doi === $doi) {
	  report_info("Rechecking if DOI " . echoable($doi) . " is operational...");  // Sometimes we get NULL, so check again for FALSE/TRUE
	} else {
	  $last_doi = $doi;
	  report_info("Checking that DOI " . echoable($doi) . " is operational...");
	}
	$trial = get_possible_dois($doi);
	foreach ($trial as $try) {
	  // Check that it begins with 10.
	  if (preg_match("~[^/]*(\d{4}/.+)$~", $try, $match)) $try = "10." . $match[1];
	  if (doi_active($try)) {
		$this->set('doi', $try);
		$this->doi_valid = TRUE;
		foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
		if ($doi === $try) {
		   report_inline('DOI ok.');
		} else {
		   report_info("Modified DOI:  " . echoable($try) . " is operational...");
		}
		return TRUE;
	  }
	}
	foreach ($trial as $try) {
	  // Check that it begins with 10.
	  if (preg_match("~[^/]*(\d{4}/.+)$~", $try, $match)) $try = "10." . $match[1];
	  if (doi_works($try)) {
		$this->set('doi', $try);
		$this->doi_valid = TRUE;
		foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
		if ($doi === $try) {
		   report_inline('DOI ok.');
		} else {
		   report_info("Modified DOI:  " . echoable($try) . " is operational...");
		}
		return TRUE;
	  }
	}
	$doi_status = doi_works($doi);
	if ($doi_status === NULL) {
	  report_warning("DOI status unknown.  doi.org failed to respond to: " . doi_link($doi)); // @codeCoverageIgnore
	  return FALSE;    // @codeCoverageIgnore
	} elseif ($doi_status === FALSE) {
	  report_inline("It's not...");
	  $this->add_if_new('doi-broken-date', date("Y-m-d"));
	  return FALSE;
	} else {
	  // Only get to this code if we got NULL earlier and now suddenly get OK
	  // @codeCoverageIgnoreStart
	  foreach (DOI_BROKEN_ALIASES as $alias) $this->forget($alias);
	  $this->doi_valid = TRUE;
	  report_inline('DOI ok.');
	  return TRUE;
	  // @codeCoverageIgnoreEnd
	}
  }

  /* function handle_et_al
   * To preserve user-input data, this function will only be called
   * if no author parameters were specified at the start of the
   * expansion process.
  */
  public function handle_et_al() : void {
	foreach (AUTHOR_PARAMETERS as $author_cardinality => $group) {
	  foreach ($group as $param) {
		if (strpos($this->get($param), 'et al') !== FALSE) { // Have to deal with 0 !== FALSE
		  // remove 'et al' from the parameter value if present
		  $val_base = preg_replace("~,?\s*'*et al['.]*~", '', $this->get($param));
		  if ($author_cardinality === 1) {
			// then we (probably) have a list of authors joined by commas in our first parameter
			if (under_two_authors($val_base)) {
			  $this->set($param, $val_base);
			  if ($param === 'authors' && $this->blank('author')) {
				$this->rename('authors', 'author');
				$param = 'author';
			  }
			} else {
			  $this->forget($param);
			  $authors = split_authors($val_base);
			  foreach ($authors as $i => $author_name) {
				$this->add_if_new('author' . (string)((int) $i + 1), format_author($author_name));
			  }
			}
		  }
		  if (trim($val_base) === "") {
			$this->forget($param);
		  }
		  $this->add_if_new('display-authors', 'etal');
		}
	  }
	}
  }

/***
 * Functions to retrieve values that may be specified in various ways
 ***/
  protected function display_authors() : int {
	if (($da = $this->get('display-authors')) === '') {
	  $da = $this->get('displayauthors');
	}
	return ctype_digit($da) ? (int) $da : 0;
  }

  protected function number_of_authors() : int {
	$max = 0;
	foreach ($this->param as $p) {
	  if (preg_match('~(?:author|last|first|forename|initials|surname|given)(\d+)~', $p->param, $matches)) {
		if (stripos($p->param, 'editor') === FALSE) $max = max((int) $matches[1], $max);
	  }
	}
	if ($max === 0) {
	  foreach ($this->param as $p) {
		if (preg_match('~(?:author|last|first|forename|initials|surname|given)$~', $p->param)) {
		  if (stripos($p->param, 'editor') === FALSE) return 1;
		}
	  }
	}
	return $max;
  }

  // Retrieve properties of template
  public function first_author() : string {
	foreach (array('author', 'author1', 'authors', 'vauthors') as $auth_param) {
	  $author = $this->get($auth_param);
	  if ($author) return $author;
	}
	$forenames = $this->get('given') . $this->get('first') . $this->get('forename') . $this->get('initials') .
	  $this->get('given1') . $this->get('first1') . $this->get('forename1') . $this->get('initials1');
	foreach (array('last', 'surname', 'last1', 'surname1') as $surname_param) {
	  $surname = $this->get($surname_param);
	  if ($surname) {
		return ($surname . ', ' . $forenames);
	  }
	}
	return '';
  }

  protected function first_surname() : string {
	// Fetch the surname of the first author only
	if (preg_match("~[^.,;\s]{2,}~u", $this->first_author(), $first_author)) {
	  return $first_author[0];
	} else {
	  return '';
	}
  }

  public function page() : string {
	if ($this->has('pages')) {
	  $page = $this->get('pages');
	} else {
	  $page = $this->get('page');
	}
	$page = str_replace(['&mdash;', '--', '&ndash;', '—', '–'], ['-','-','-','-','-'], $page);
	return $page;
  }

  public function year() : string {
	if ($this->has('year')) {
	  return $this->get('year');
	}
	if ($this->has('date')) {
	   $date = $this->get('date');
	   if (preg_match("~^\d{4}$~", $date)) {
		 return $date; // Just a year
	   } elseif (preg_match("~^(\d{4})[^0-9]~", $date, $matches)) {
		 return $matches[1]; // Start with year
	   } elseif (preg_match("~[^0-9](\d{4})$~", $date, $matches)) {
		 return $matches[1]; // Ends with year
	   }
	}
	return '';
  }

  /** @return ?array<string> **/
  protected function page_range() : ?array {
	preg_match("~(\w?\w?\d+\w?\w?)(?:\D+(\w?\w?\d+\w?\w?))?~", $this->page(), $pagenos);
	return $pagenos;
  }

  // Amend parameters
  public function rename(string $old_param, string $new_param, ?string $new_value = NULL) : void {
	if (empty($this->param)) return;
	if ($old_param === $new_param) {
	   if ($new_value !== NULL) {
		   $this->set($new_param, $new_value);
		   return;
		}
		return;
	}
	$have_nothing = TRUE;
	foreach ($this->param as $p) {
	  if ($p->param === $old_param) {
		$have_nothing = FALSE;
		break;
	  }
	}
	if ($have_nothing) {
	   if ($new_value !== NULL) {
		  $this->add_if_new($new_param, $new_value);
		  return;
	   }
	   return;
	}
	// Forget old copies
	$pos = $this->get_param_key($new_param);
	while ($pos !== NULL) {
	  unset($this->param[$pos]);
	  $pos = $this->get_param_key($new_param);
	}
	foreach ($this->param as $p) {
	  if ($p->param === $old_param) {
		$p->param = $new_param;
		if ($new_value !== NULL) {
		  $p->val = $new_value;
		}
		if (strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_year') === FALSE &&
			strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_date') === FALSE &&
			strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_title') === FALSE &&
			strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_volume') === FALSE &&
			strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_issue') === FALSE &&
			strpos($old_param . $new_param, 'CITATION_BOT_PLACEHOLDER_journal') === FALSE) {
		  report_modification("Renamed \"" . echoable($old_param) . "\" -> \"" . echoable($new_param) . "\"");
		  $this->mod_names = TRUE;
		}
		$this->tidy_parameter($new_param);
	  }
	}
	if ($old_param === 'url' && $new_param === 'chapter-url') {
	  $this->rename('urlaccess', 'chapter-url-access');
	  $this->rename('url-access', 'chapter-url-access');
	  $this->rename('format', 'chapter-format');
	} elseif (($old_param === 'chapter-url' || $old_param === 'chapterurl') && $new_param === 'url') {
		$this->rename('chapter-url-access', 'url-access');
		$this->rename('chapter-format', 'format');
	} elseif ($old_param === 'title' && $new_param === 'chapter') {
	  $this->rename('url', 'chapter-url');
	} elseif ($old_param === 'chapter' && $new_param === 'title') {
	  $this->rename('chapter-url', 'url');
	  $this->rename('chapterurl', 'url');
	}
  }

  public function get(string $name) : string {
	// NOTE $this->param and $p->param are different and refer to different types!
	// $this->param is an array of Parameter objects
	// $parameter_i->param is the parameter name within the Parameter object
	foreach ($this->param as $parameter_i) {
	  if ($parameter_i->param === $name) {
		$the_val = $parameter_i->val;
		if (preg_match("~^\(\((.*)\)\)$~", $the_val, $matches)) {
		  $the_val = trim($matches[1]);
		}
		return $the_val;
	  }
	}
	return '';
  }
  // This one is used in the test suite to distinguish there-but-blank vs not-there-at-all
  public function get2(string $name) : ?string {
	foreach ($this->param as $parameter_i) {
	  if ($parameter_i->param === $name) {
		$the_val = $parameter_i->val;
		if (preg_match("~^\(\((.*)\)\)$~", $the_val, $matches)) {
		  $the_val = trim($matches[1]);
		}
		return $the_val;
	  }
	}
	return NULL;
  }

  public function get3(string $name) : string {  // like get() only includes (( ))
	foreach ($this->param as $parameter_i) {
	  if ($parameter_i->param === $name) {
		$the_val = $parameter_i->val;
		return $the_val;
	  }
	}
	return '';
  }

  public function has_but_maybe_blank(string $name) : bool {
	foreach ($this->param as $parameter_i) {
	  if ($parameter_i->param === $name) {
		 return TRUE;
	  }
	}
	return FALSE;
  }

  protected function param_with_index(int $i) : ?Parameter {
	return (isset($this->param[$i])) ? $this->param[$i] : NULL;
  }

  protected function param_value(int $i) : string {
	$item = $this->param_with_index($i);
	if (is_null($item)) return ''; // id={{arxiv}} and other junk
	return $item->val;
  }

  public function get_without_comments_and_placeholders(string $name) : string {
	$ret = $this->get($name);
	$ret = safe_preg_replace('~<!--.*?-->~su', '', $ret); // Comments
	$ret = safe_preg_replace('~# # # CITATION_BOT_PLACEHOLDER.*?# # #~sui', '', $ret); // Other place holders already escaped. Case insensitive
	$ret = str_replace("\xc2\xa0", ' ', $ret); // Replace non-breaking with breaking spaces, which are trimmable
	$ret = trim($ret);
	return $ret;
  }

  protected function get_param_key (string $needle) : ?int {
	if (empty($this->param)) return NULL;
	foreach ($this->param as $i => $p) {
	  if ($p->param === $needle) return $i;
	}
	return NULL;
  }

  public function has(string $par) : bool {
	return (bool) strlen($this->get($par));
  }

  public function add(string $par, string $val) : bool {
	report_add(echoable("Adding $par: $val"));
	$could_set = $this->set($par, $val);
	$this->tidy_parameter($par);
	return $could_set;
  }

  public function set(string $par, string $val) : bool {
	if ($par === '') report_error('NULL parameter passed to set with value of ' . echoable($val));
	if (mb_stripos($this->get($par), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE && $par !== 'ref') {
	  return FALSE;
	}
	if ($this->get($par) !== $this->get3($par)) {
	  return FALSE;
	}
	if (($pos = $this->get_param_key($par)) !== NULL) {
	  $this->param[$pos]->val = $val;
	  return TRUE;
	}
	$p = new Parameter();
	$p->parse_text($this->example_param);
	$p->param = $par;
	$p->val = $val;

	$insert_after = prior_parameters($par);
	$prior_pos_best = -1;
	foreach (array_reverse($insert_after) as $after) {
	  if (($after_key = $this->get_param_key($after)) !== NULL) {
		$keys = array_keys($this->param);
		for ($prior_pos = 0; $prior_pos < count($keys); $prior_pos++) {
		  if ($keys[$prior_pos] === $after_key) {
			if($prior_pos > $prior_pos_best) $prior_pos_best = $prior_pos;
			break;
		  }
		}
	  }
	}
	$prior_pos = $prior_pos_best;
	if ($prior_pos > -1) {
		$this->param = array_merge(
		  array_slice($this->param, 0, $prior_pos + 1),
		  array($p),
		  array_slice($this->param, $prior_pos + 1));
		return TRUE;
	}

	if ($p->post !== '') { // Often templates are {{cite this|x=y |a=b |l=m}}  with last space missing
	   $last = array_key_last($this->param);
	   if (($last !== NULL) && $this->param[$last]->post === '') {
		   $this->param[$last]->post = $p->post;
	   }
	}
	$this->param[] = $p;
	return TRUE;
  }

  public function append_to(string $par, string $val) : void {
	if (mb_stripos($this->get($par), 'CITATION_BOT_PLACEHOLDER_COMMENT') !== FALSE) {
	  return;
	}
	$pos = $this->get_param_key($par);
	if ($pos !== NULL) { // Could be zero which is "FALSE"
	  $this->param[$pos]->val = $this->param[$pos]->val . $val;
	} else {
	  $this->set($par, $val);
	}
	return;
  }

  public function quietly_forget(string $par) : void {
	$this->forgetter($par, FALSE);
  }
  public function forget(string $par) : void {
	$this->forgetter($par, TRUE);
  }
  private function forgetter(string $par, bool $echo_forgetting) : void { // Do not call this function directly
   if (!$this->blank($par)) { // do not remove all this other stuff if blank
	if ($par === 'url') {
	  if ($this->blank(array_diff(ALL_URL_TYPES, array($par)))) {
		$this->forgetter('archive-url', $echo_forgetting);
		$this->forgetter('archiveurl', $echo_forgetting);
		$this->forgetter('accessdate', $echo_forgetting);
		$this->forgetter('access-date', $echo_forgetting);
	  }
	  $this->forgetter('format', $echo_forgetting);
	  $this->forgetter('registration', $echo_forgetting);
	  $this->forgetter('subscription', $echo_forgetting);
	  $this->forgetter('url-access', $echo_forgetting);
	  $this->forgetter('deadurl', $echo_forgetting);
	  $this->forgetter('url-status', $echo_forgetting);
	  if ($this->has('work') && stripos($this->get('work'), 'www.') === 0) {
		 $this->forgetter('work', $echo_forgetting);
	  }
	  if ($this->blank(array_diff(WORK_ALIASES, ['website'])) && bad_10_1093_doi($this->get('doi'))) {
		if ($this->has('via') && $this->blank('website')) {
		  $this->rename('via', 'work');
		} elseif ($this->has('website') && $this->blank('via')) {
		  $this->rename('website', 'work');
		} elseif ($this->has('website') && $this->has('via')) {
		  if (titles_are_similar($this->get('website'), $this->get('via'))) {
			$this->forgetter('via', $echo_forgetting);
			$this->rename('website', 'work');
		  } else {
			$tmp = $this->get('website') . ' via ' . $this->get('via');
			$this->forgetter('via', $echo_forgetting);
			$this->rename('website', 'work', $tmp);
		  }
		}
		preg_match("~^(\s*).*\b(\s*)$~", $this->name, $spacing);
		if (substr($this->name,0,1) === 'c') {
		  $this->name = $spacing[1] . 'cite document' . $spacing[2];
		} else {
		  $this->name = $spacing[1] . 'Cite document' . $spacing[2];
		}
	  }
	  $this->forgetter('via', $echo_forgetting);
	  $this->forgetter('website', $echo_forgetting);
	}
	if ($par === 'chapter' && $this->blank('url')) {
	  if($this->has('chapter-url')) {
		$this->rename('chapter-url', 'url');
	  } elseif ($this->has('chapterurl')) {
		$this->rename('chapterurl', 'url');
	  }
	}
	if ($par === 'chapter-url' || $par === 'chapterurl') {
	   $this->forgetter('chapter-format', $echo_forgetting);
	   $this->forgetter('chapter-url-access', $echo_forgetting);
	   if ($this->blank(array_diff(ALL_URL_TYPES, array($par)))) {
		$this->forgetter('accessdate', $echo_forgetting);
		$this->forgetter('access-date', $echo_forgetting);
		$this->forgetter('archive-url', $echo_forgetting);
		$this->forgetter('archiveurl', $echo_forgetting);
	   }
	}
   }  // even if blank try to remove
	if ($par === 'doi') {
	  foreach (DOI_BROKEN_ALIASES as $broke) {
		$this->forgetter($broke, FALSE);
	  }
	}
	if ($par === 'archive-url' && $this->blank('archiveurl')) {
		$this->forgetter('archive-date', FALSE);
		$this->forgetter('archivedate', FALSE);
		$this->forgetter('dead-url', FALSE);
		$this->forgetter('url-status', FALSE);
	}
	if ($par === 'archiveurl' && $this->blank('archive-url')) {
		$this->forgetter('archive-date', FALSE);
		$this->forgetter('archivedate', FALSE);
		$this->forgetter('dead-url', FALSE);
		$this->forgetter('url-status', FALSE);
	}
	$pos = $this->get_param_key($par);
	if ($pos !== NULL) {
	  if ($echo_forgetting && $this->has($par) && stripos($par, 'CITATION_BOT_PLACEHOLDER') === FALSE) {
		// Do not mention forgetting empty parameters or internal temporary parameters
		report_forget("Dropping parameter \"" . echoable($par) . '"');
	  }
	  while ($pos !== NULL) { // paranoid
		unset($this->param[$pos]);
		$pos = $this->get_param_key($par);
	  }
	}
	if (strpos($par, 'url') !== FALSE && $this->wikiname() === 'cite web' &&
		$this->blank(array_diff(ALL_URL_TYPES, array($par)))) {
	   if ($this->has('journal')) {
		 $this->change_name_to('cite journal');
	   } elseif ($this->has('newspaper')) {
		 $this->change_name_to('cite news');
	   } elseif (!$this->blank(['isbn', 'lccn', 'oclc', 'ol', 'chapter'])) {
		 $this->change_name_to('cite book');
	   } elseif ($this->has('arxiv') || $this->has('eprint')) {
		 $this->change_name_to('cite arxiv');
	   } else {
		 $this->change_name_to('cite document');
	   }
	}
  }

  /** @return array<mixed> **/
  public function modifications() : array {
	if ($this->has(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) {
	  if ($this->has('title') || $this->has('chapter')) {
		$this->forget(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'));
	  }
	}
	if ($this->has(strtolower('CITATION_BOT_PLACEHOLDER_BARE_URL'))) return array();
	$new = array();
	$ret = array();
	foreach ($this->param as $p) {
	  $new[$p->param] = $p->val;
	}

	$old = ($this->initial_param) ? $this->initial_param : array();

	$old['template type'] = trim($this->initial_name);
	$new['template type'] = trim($this->name);

	// Do not call ISSN to issn "Added issn, deleted ISSN"
	$old = array_change_key_case($old, CASE_LOWER);
	$new = array_change_key_case($new, CASE_LOWER);

	$mistake_corrections = array_values(COMMON_MISTAKES);
	$mistake_keys = array_keys(COMMON_MISTAKES);
	foreach ($old as $old_name => $old_data) {
	  $mistake_id = array_search($old_name, $mistake_keys);
	  if ($mistake_id !== FALSE) {
		if ($this->should_be_processed()) $this->mod_names = TRUE; // 99.99% of the time this is true
		$old[$mistake_corrections[$mistake_id]] = $old_data;
		unset($old[$old_name]);
	  }
	}
	// 99.99% of the time does nothing, since they should already be switched. This will be needed for templates that we do very little too, such as TEMPLATES_WE_CHAPTER_URL
	foreach ($new as $old_name => $old_data) {
	  $mistake_id = array_search($old_name, $mistake_keys);
	  if ($mistake_id !== FALSE) {
		$new[$mistake_corrections[$mistake_id]] = $old_data;
		unset($new[$old_name]);
	  }
	}

	$ret['modifications'] = array_keys(array_diff_assoc($new, $old));
	$ret['additions'] = array_diff(array_keys($new), array_keys($old));
	$ret['deletions'] = array_diff(array_keys($old), array_keys($new));
	$ret['changeonly'] = array_diff($ret['modifications'], $ret['additions']);
	foreach ($ret['deletions'] as $inds=>$vals) {
	   if ($vals === '') unset($ret['deletions'][$inds]); // If we get rid of double pipe that appears as a deletion, not misc.
	}

	$no_dash_to_start = TRUE;
	foreach ($old as $old_name => $old_data) {
		if (in_array($old_name, PAGE_ALIASES)) {
		   if (strpos($old_data, '-') !== FALSE) $no_dash_to_start = FALSE;
		}
		if (in_array($old_name, ['volume', 'issue', 'number'])) {
		   if (strpos($old_data, '-') !== FALSE) $no_dash_to_start = FALSE;
		}
	}
	if ($no_dash_to_start) $this->mod_dashes = FALSE;

	$ret['dashes'] = $this->mod_dashes;
	$ret['names'] = $this->mod_names;
	return $ret;
  }

  protected function isbn10Toisbn13(string $isbn10, bool $ignore_year = FALSE) : string {
	$isbn10 = trim($isbn10);  // Remove leading and trailing spaces
	$test = str_replace(array('—', '?', '–', '-', '?', ' '), '', $isbn10);
	if (strlen($test) < 10 || strlen ($test) > 13) return $isbn10;
	 $isbn10 = str_replace('x', 'X', $isbn10);
	if (preg_match("~^[0-9Xx ]+$~", $isbn10) === 1) { // Uses spaces
	  $isbn10 = str_replace(' ', '-', $isbn10);
	}
	$isbn10 = str_replace(array('—', '?', '–', '-', '?'), '-', $isbn10); // Standardize dahses : en dash, horizontal bar, em dash, minus sign, figure dash, to hyphen.
	if (preg_match("~[^0-9Xx\-]~", $isbn10) === 1) return $isbn10;  // Contains invalid characters
	if (substr($isbn10, -1) === "-" || substr($isbn10, 0, 1) === "-") return $isbn10;  // Ends or starts with a dash
	if ((intval($this->year()) < 2007) && !$ignore_year) return $isbn10; // Older books does not have ISBN-13, see [[WP:ISBN]]
	$isbn13 = str_replace('-', '', $isbn10);  // Remove dashes to do math
	if (strlen($isbn13) !== 10) return $isbn10;  // Might be an ISBN 13 already, or rubbish
	$isbn13 = '978' . substr($isbn13, 0, -1);  // Convert without check digit - do not need and might be X
	if (preg_match("~[^0123456789]~", $isbn13) === 1)  return $isbn10; // Not just numbers
	$sum = 0;
	for ($count=0; $count<12; $count++ ) {
	  $sum = $sum + intval($isbn13[$count])*($count%2?3:1);  // Depending upon even or odd, we multiply by 3 or 1 (strange but true)
	}
	$sum = ((10-$sum%10)%10) ;
	$isbn13 = '978' . '-' . substr($isbn10, 0, -1) . (string) $sum; // Assume existing dashes (if any) are right
	quietly('report_modification', "Converted ISBN10 to ISBN13");
	return $isbn13;
  }

  /** @return ?array<string> **/
  protected function inline_doi_information() : ?array {
	if ($this->name !== "doi-inline") return NULL;
	if (count($this->param) !==2) return NULL;
	$vals = array();
	$vals[0] = $this->param[0]->parsed_text();
	$vals[1] = $this->param[1]->parsed_text();
	return $vals;
  }

  protected function get_inline_doi_from_title() : void {
	 if (preg_match("~(?:\s)*(?:# # # CITATION_BOT_PLACEHOLDER_TEMPLATE )(\d+)(?: # # #)(?:\s)*~", $this->get('title'), $match)) {
	   if ($inline_doi = self::$all_templates[$match[1]]->inline_doi_information()) {
		 if ($this->add_if_new('doi', trim($inline_doi[0]))) { // Add doi
		   $this->set('title', trim($inline_doi[1]));
		   quietly('report_modification', "Converting inline DOI to DOI parameter");
		 } elseif ($this->get('doi') === trim($inline_doi[0])) { // Already added by someone else
		   $this->set('title', trim($inline_doi[1]));
		   quietly('report_modification', "Remove duplicate inline DOI ");
		 }
	   }
	 }
  }

  protected function volume_issue_demix(string $data, string $param) : void {
	 if ($param === 'year') return;
	 if (!in_array($param, ['volume','issue','number'])) {
	   report_error('volume_issue_demix ' . echoable($param)); // @codeCoverageIgnore
	 }
	 if (in_array($this->wikiname(), ['cite encyclopaedia', 'cite encyclopedia'])) return;
	 if ($param === 'issue') {
		 $the_issue = 'issue';
	 } elseif ($param === 'number') {
		 $the_issue = 'number';
	 } elseif ($param === 'volume' && $this->has('number')) {
		 $the_issue = 'number';
	 } else {
		 $the_issue = 'issue';
	 }
	 $data = trim($data);
	 $data = str_replace('--', '-', $data);
	 if (preg_match("~^(\d+)\s*\((\d+(-|–|\–|\{\{ndash\}\})?\d*)\)$~", $data, $matches) ||
			  preg_match("~^(?:vol\. |Volume |vol |vol\.|)(\d+)[,\s]\s*(?:no\.|number|issue|Iss.|no )\s*(\d+(-|–|\–|\{\{ndash\}\})?\d*)$~i", $data, $matches) ||
			  preg_match("~^(\d+)\.(\d+)$~i", $data, $matches) ||
			  preg_match("~^(\d+)\((\d+\/\d+)\)$~i", $data, $matches) ||
			  preg_match("~^(\d+) \((\d+ Suppl\.* \d+)\)$~i", $data, $matches) ||
			  preg_match("~^(\d+) (Suppl\.* \d+)$~i", $data, $matches) ||
			  preg_match("~^(\d+) (S\d+)$~i", $data, $matches) ||
			  preg_match("~^Vol\.?(\d+)\((\d+)\)$~", $data, $matches) ||
			  preg_match("~^(\d+) +\(No(?:\.|\. | )(\d+)\)$~i", $data, $matches) ||
			  preg_match("~^(\d+):(\d+)$~", $data, $matches) ||
			  preg_match("~^(\d+) +\(Iss(?:\.|\. | )(\d+)\)$~i", $data, $matches)
		 ) {
		 $possible_volume=$matches[1];
		 $possible_issue=$matches[2];
		 if (preg_match("~^\d{4}.\d{4}$~", $possible_issue)) return; // Range of years
		 if ($possible_issue === $this->get('year')) return;
		 if ($possible_issue === $this->get('date')) return;
		 if ($this->year() !== '' && strpos($possible_issue, $this->year()) !== FALSE) return;
		 if (preg_match('~\d{4}~', $possible_issue)) return; // probably a year or range of years
		 if ($param === 'volume') {
			if ($this->blank(ISSUE_ALIASES)) {
			  $this->add_if_new($the_issue, $possible_issue);
			  $this->set('volume', $possible_volume);
			} elseif (str_replace(".", "", $this->get('issue'))  === str_replace(".", "", $possible_issue) ||
					  str_replace(".", "", $this->get('number')) === str_replace(".", "", $possible_issue)) {
			  $this->set('volume', $possible_volume);
			}
		 } else {
			if ($this->blank('volume')) {
			  $this->set($the_issue, $possible_issue);
			  $this->add_if_new('volume', $possible_volume);
			} elseif ($this->get('volume') === $possible_volume) {
			  $this->set($the_issue, $possible_issue);
			}
		 }
	 } elseif (preg_match('~^\((\d+)\)\.?$~', $data, $matches)) {
	   $this->set($param, $matches[1]);
	   return;
	 } elseif (preg_match('~^(\d+)\.$~', $data, $matches)) {
	   $this->set($param, $matches[1]); // remove period
	   return;
	 }
// volume misuse seems to be popular in cite book, and we would need to move volume to title
	 // Obvious books
	 if ($this->wikiname() === 'cite book') return;
	 if ($this->wikiname() === 'citation' && ($this->has('chapter') || $this->has('isbn') || strpos($this->rawtext, 'archive.org') !== FALSE)) return;
	 // Might not be a journal
	 if (!in_array($this->wikiname(), ['citation', 'cite journal', 'cite web', 'cite magazine']) &&
		 $this->get_without_comments_and_placeholders('issue') === '' &&
		 $this->get_without_comments_and_placeholders('number') === '' &&
		 $this->get_without_comments_and_placeholders('journal') === '' &&
		 $this->get_without_comments_and_placeholders('magazine') === '') return;
	 if ($param === 'volume') {
	   if (preg_match("~^(?:vol\.|volume\s+|vol\s+|vol:)\s*([\dLXVI]+)$~i", $data, $matches)) {
		 $data = $matches[1];
		 $this->set('volume', $data);
	   } elseif (preg_match("~^v\.\s+(\d+)$~i", $data, $matches)) {
		 $data = $matches[1];
		 $this->set('volume', $data);
	   }
	 }
	 if ($param === 'issue' || $param === 'number') {
	   if (preg_match("~^(?:num\.|number\s+|num\s+|num:|number:|iss\.|issue\s+|iss\s+|iss:|issue:)\s*([\dLXVI]+)$~i", $data, $matches)) {
		 $data = $matches[1];
		 $this->set($param, $data);
	   }
	 }
	 if (!$this->blank(['doi', 'jstor', 'pmid', 'pmc'])) { // Have some data to fix it up with
	   if ($param === 'issue' || $param === 'number') {
		 if (preg_match("~^(?:vol\.|volume\s+|vol\s+|vol:)\s*([\dLXVI]+)$~i", $data, $matches)) {
		   $data = $matches[1];
		   if ($this->blank('volume')) {
			 $this->rename($param, 'volume', $data);
		   } elseif (stripos($this->get('volume'), $data) !== FALSE) {
			 $this->forget($param); // Duplicate data
		   }
		 }
	   }
	   if ($param === 'volume') {
		 if (preg_match("~^(?:num\.|number\s+|num\s+|num:|number:|iss\.|issue\s+|iss\s+|iss:|issue:)\s*([\dLXVI]+)$~i", $data, $matches)) {
		   $data = $matches[1];
		   if ($this->blank(['issue', 'number'])) {
			 $this->rename($param, 'issue', $data);
		   } elseif (stripos($this->get('issue') . $this->get('number'), $data) !== FALSE) {
			 $this->forget($param); // Duplicate data
		   }
		 }
	   }
	 }
  }

  protected function simplify_google_search(string $url) : string {
	  if (stripos($url, 'q=') === FALSE) return $url;  // Not a search
	  if (preg_match('~^https?://.*google.com/search/~', $url)) return $url; // Not a search if the slash is there
	  $orig_url = $url;
	  $hash = '';
	  if (strpos($url, "#")) {
		$url_parts = explode("#", $url, 2);
		$url = $url_parts[0];
		$hash = "#" . $url_parts[1];
	  }

	  $url_parts = explode("&", str_replace("&&", "&", str_replace("?", "&", $url)));
	  array_shift($url_parts);
	  $url = "https://www.google.com/search?";

	  foreach ($url_parts as $part) {
		$part_start = explode("=", $part, 2);
		$part_start0 = $part_start[0];
		if (isset($part_start[1]) && $part_start[1] === '') {
		  $part_start0 = "donotaddmeback"; // Do not add blank ones
		  $part_start1 = '';
		  $it_is_blank = TRUE;
		} elseif (empty($part_start[1])) {
		  $part_start1 = '';
		  $it_is_blank = TRUE;
		} else {
		  $part_start1 = $part_start[1];
		  $it_is_blank = FALSE;
		}
		switch ($part_start0) {
		  case "aq": case "aqi": case "bih": case "biw": case "client":
		  case "as": case "useragent": case "as_brr":
		  case "ei": case "ots": case "sig": case "source": case "lr":
		  case "sa": case "oi": case "ct": case "id":  case "cd":
		  case "oq": case "rls": case "sourceid": case "ved":
		  case "aqs": case "gs_l": case "uact": case "tbo": case "tbs":
		  case "num": case "redir_esc": case "gs_lcp": case "sxsrf":
		  case "gfe_rd": case "gws_rd": case "rlz": case "sclient":
		  case "prmd": case "dpr": case "newwindow": case "gs_ssp":
		  case "spell": case "shndl": case "sugexp": case "donotaddmeback":
		  case "usg": case "fir": case "entrypoint": case "as_qdr":
		  case "as_drrb": case "as_minm":  case "as_mind": case "as_maxm":
		  case "as_maxd": case "kgs": case "ictx": case "shem": case "vet":
		  case "iflsig": case "tab": case "sqi": case "noj":
		  case "hs": case "es_sm": case "site": case "btnmeta_news_search":
		  case "channel": case "espv": case "cad": case "gs_sm":
		  case "imgil": case "ins": case "npsic=": case "rflfq": case "lei":
		  case "rlha": case "rldoc": case "rldimm": case "npsic": case "phdesc":
		  case "prmdo": case "ssui": case "lqi": case "rlst": case "pf":
		  case "authuser": case "gsas": case "ned": case "pz": case "e": case "surl":
		  case "aql":
			 break;
		  case "as_occt":
			 if ($it_is_blank || str_i_same($part_start1, 'any')) break;
			 $url .=  $part . "&" ;
			 break;
		  case "cf":
			 if ($it_is_blank || str_i_same($part_start1, 'all')) break;
			 $url .=  $part . "&" ;
			 break;
		  case "cs":
			 if ($it_is_blank || str_i_same($part_start1, '0')) break;
			 $url .=  $part . "&" ;
			 break;
		  case "btnK":
			 if ($it_is_blank || str_i_same($part_start1, 'Google+Search')) break;
			 $url .=  $part . "&" ;
			 break;
		  case "as_epq":
			 if ($it_is_blank) break;
			 $url .=  $part . "&" ;
			 break;
		  case "btnG":
			 if ($it_is_blank || str_i_same($part_start1, 'Search')) break;
			 $url .=  $part . "&" ;
			 break;
		  case "rct":
			 if ($it_is_blank || str_i_same($part_start1, 'j')) break; // default
			 $url .=  $part . "&" ;
			 break;
		  case "resnum":
			 if ($it_is_blank || str_i_same($part_start1, '11')) break; // default
			 $url .=  $part . "&" ;
			 break;
		  case "ie": case "oe":
			 if ($it_is_blank || str_i_same($part_start1, 'utf-8')) break; // UTF-8 is the default
			 $url .=  $part . "&" ;
			 break;
		  case "hl": case "safe": case "q": case "tbm": case "start": case "ludocid":
		  case "cshid": case "stick": case "as_eq": case "kgmid": case "as_drrb": case "gbv":
		  case "as_scoring": case "gl": case "rllag": case "lsig": case "lpsid": case "as_q": case "kponly":
			 $url .=  $part . "&" ;
			 break;
		  default:
			 // @codeCoverageIgnoreStart
			 report_minor_error("Unexpected Google URL component:  " . echoable($part) . " in " . echoable($orig_url));
			 $url .=  $part . "&" ;
			 break;
			 // @codeCoverageIgnoreEnd
		}
	  }

	  if (substr($url, -1) === "&") $url = substr($url, 0, -1);  //remove trailing &
	  $url= $url . $hash;
	  return $url;
  }

  public function use_issn() : void { // Only add if helpful and not a series of books
	if ($this->blank('issn')) return;
	if (!$this->blank(WORK_ALIASES)) return;
	if ($this->has('series')) return;
	if ($this->wikiname() === 'cite book' && $this->has('isbn')) return;
	$issn = $this->get('issn');
	if ($issn === '9999-9999') return;
	if (!preg_match('~^\d{4}.?\d{3}[0-9xX]$~u', $issn)) return;
	if ($issn === '0140-0460') { // Use set to avoid escaping [[ and ]]
	  $this->set('newspaper', '[[The Times]]');
	} elseif ($issn === '0190-8286') {
	  $this->set('newspaper', '[[The Washington Post]]');
	} elseif ($issn === '0362-4331') {
	  $this->set('newspaper', '[[The New York Times]]');
	} elseif ($issn === '0163-089X' || $issn === '1092-0935') {
	  $this->set('newspaper', '[[The Wall Street Journal]]');
	}
	return; // TODO - the API is gone
  }

  private function is_book_series(string $param) : bool {
	$simple = trim(str_replace(['-', '.',  '   ', '  ', '[[', ']]'], [' ', ' ', ' ', ' ', ' ', ' '], strtolower($this->get($param))));
	$simple = trim(str_replace(['    ', '   ', '  '], [' ', ' ', ' '], $simple));
	return in_array($simple, JOURNAL_IS_BOOK_SERIES);
  }

  private function should_url2chapter(bool $force) : bool {
	if ($this->has('chapterurl')) return FALSE;
	if ($this->has('chapter-url')) return FALSE;
	if ($this->has('trans-chapter')) return FALSE;
	if ($this->blank('chapter')) return FALSE;
	if (strpos($this->get('chapter'), '[') !== FALSE) return FALSE;
	$url = $this->get('url');
	if (stripos($url, 'google') && !strpos($this->get('url'), 'pg=')) return FALSE; // Do not move books without page numbers
	if (stripos($url, 'archive.org/details/isbn')) return FALSE;
	if (stripos($url, 'page_id=0')) return FALSE;
	if (stripos($url, 'page=0')) return FALSE;
	if (substr($url, -2) === '_0') return FALSE;
	if (preg_match('~archive\.org/details/[^/]+$~', $url)) return FALSE;
	if (preg_match('~archive\.org/details/.+/page/n(\d+)~', $url, $matches)) {
	  if ((int) $matches[1] < 16) return FALSE; // Assume early in the book - title page, etc
	}
	if (stripos($url, 'PA1') && !preg_match('~PA1[0-9]~i', $url)) return FALSE;
	if (stripos($url, 'PA0')) return FALSE;
	if (stripos($url, 'PP1') && !preg_match('~PP1[0-9]~i', $url)) return FALSE;
	if (stripos($url, 'PP0')) return FALSE;
	if ($this->get_without_comments_and_placeholders('chapter') === '') return FALSE;
	if (stripos($url, 'archive.org')) {
	  if (strpos($url, 'chapter')) return TRUE;
	  if (strpos($url, 'page')) {
		if (preg_match('~page/?[01]?$~i', $url)) return FALSE;
		return TRUE;
	  }
	  return FALSE;
	}
	if (stripos($url, 'wp-content')) { // Private websites are hard to judge
	  if (stripos($url, 'chapter') || stripos($url, 'section')) return TRUE;
	  if (stripos($url, 'pages') && !preg_match('~[^\d]1[-–]~u', $url)) return TRUE;
	  return FALSE;
	}
	if (strpos($url, 'link.springer.com/chapter/10.')) return TRUE;
	if (preg_match('~10\.\1007\/97[89]-?[0-9]{1,5}\-?[0-9]+\-?[0-9]+\-?[0-9]\_\d{1,3}~', $url)) return TRUE;
	if ($force) return TRUE;
	// Only do a few select website unless we just converted to cite book from cite journal
	if (strpos($url, 'archive.org')) return TRUE;
	if (strpos($url, 'google.com')) return TRUE;
	if (strpos($url, 'www.sciencedirect.com/science/article')) return TRUE;
	return FALSE;
  }

  public function clean_cite_odnb() : void {
	if ($this->has('url')) {
	  while (preg_match('~^(https?://www\.oxforddnb\.com/.+)(?:\;jsession|\?rskey|\#)~', $this->get('url'), $matches)) {
		 $this->set('url', $matches[1]);
	  }
	}
	if ($this->has('doi')) {
	  $doi = $this->get('doi');
	  if (doi_works($doi) === FALSE) {
		if (preg_match("~^10\.1093/(?:\S+odnb-9780198614128-e-|ref:odnb|odnb/9780198614128\.013\.|odnb/)(\d+)$~", $doi, $matches)) {
		  $try1 = '10.1093/ref:odnb/' . $matches[1];
		  $try3 = '10.1093/odnb/9780198614128.013.' . $matches[1];
		  if (doi_works($try1)) {
			$this->set('doi', $try1);
		  } elseif (doi_works($try3)) {
			$this->set('doi', $try3);
		  }
		}
	  }
	}
	if ($this->has('id')) {
		  $doi = $this->get('doi');
		  $try1 = '10.1093/ref:odnb/' . $this->get('id');
		  $try3 = '10.1093/odnb/9780198614128.013.' . $this->get('id');
		  if (doi_works($try1) !== FALSE) {
			; // Template does this
		  } elseif (doi_works($try3)) {
			if ($doi === '') {
			  $this->rename('id', 'doi', $try3);
			} elseif ($doi === $try3) {
			  $this->forget('id');
			} elseif (doi_works($doi)) {
			  $this->forget('id');
			} else {
			  $this->forget('doi');
			  $this->rename('id', 'doi', $try3);
			}
		  }
	  }
	  if ($this->has('doi')) {
		  $works = doi_works($this->get('doi'));
		  if ($works === FALSE) {
			 $this->add_if_new('doi-broken-date', date('Y-m-d'));
		  } elseif ($works === TRUE) {
			 $this->forget('doi-broken-date');
		  }
	  }
  }

  public function has_good_free_copy() : bool { // Must link title - TODO add more if jstor-access or hdl-access link
	$this->tidy_parameter('pmc');
	$this->tidy_parameter('pmc-embargo-date');
	if (($this->has('pmc') && $this->blank('pmc-embargo-date') && preg_match('~^\d+$~', $this->get('pmc'))) ||
		($this->has('doi') && $this->get('doi-access') === 'free' && $this->blank(DOI_BROKEN_ALIASES) && doi_works($this->get('doi')))) {
	   return TRUE;
	}
	return FALSE;
  }

  public function block_modifications() : void { // {{void}} should be just like a comment, BUT this code will not stop the normalization of the hidden template which has already been done
	 $tmp = $this->parsed_text();
	 while (preg_match_all('~' . sprintf(Self::PLACEHOLDER_TEXT, '(\d+)') . '~', $tmp, $matches)) {
	   for ($i = 0; $i < count($matches[1]); $i++) {
		 $subtemplate = self::$all_templates[$matches[1][$i]];
		 $tmp = str_replace($matches[0][$i], $subtemplate->parsed_text(), $tmp);
	   }
	 }
	 // So we do not get an error when we parse a second time
	 unset($this->rawtext);  // @phan-suppress-current-line PhanTypeObjectUnsetDeclaredProperty
	 $this->parse_text($tmp);
  }

  private function move_and_forget(string $para) : void {  // Try to keep parameters in the same order
	 $para2 = str_replace('CITATION_BOT_PLACEHOLDER_', '', $para);
	 if ($this->has($para2)) {
	   $this->set($para, $this->get($para2));
	   $this->rename($para, $para2);
	 } else {
	   $this->forget($para); // This can happen when there is less than ideal data, such as {{cite journal|jstor=3073767|pages=null|page=null|volume=n/a|issue=0|title=[No title found]|coauthors=Duh|last1=Duh|first1=Dum|first=Hello|last=By|author=Yup|author1=Nope|year=2002
	 }
  }

  public static function addISBNdashes(string $isbn) : string {
	if (substr_count($isbn, '-') > 1) return $isbn;
	$new = str_replace('-', '', $isbn);
	if (strlen($new) === 10) {
		$num = 9780000000000 + (int) str_ireplace('x','9', $new);
		foreach (ISBN_HYPHEN_POS as $k => $v) {
			if ($num <= (int) $k) {
				$split = $v;
				break;
			}
		}
		if (!isset($split)) return $isbn; // Paranoid
		$v = $split;
		return substr($new, 0, $v[0]) . '-' . substr($new, $v[0], $v[1]) . '-' . substr($new, $v[0]+$v[1], $v[2]) . '-' . substr($new, $v[0]+$v[1]+$v[2], 1) ;
		// split = SKIP3, $v[0], $v[1], $v[2], 1
	} elseif (strlen($new) === 13) {
		$num = (int) $new;
		foreach (ISBN_HYPHEN_POS as $k => $v) {
			if ($num <= (int) $k) {
				$split = $v;
				break;
			}
		}
		if (!isset($split)) return $isbn; // Paranoid
		$v = $split;
		return substr($new, 0, 3) . '-' . substr($new, 3, $v[0]) . '-' . substr($new, 3+$v[0], $v[1]) . '-' . substr($new, 3+$v[0]+$v[1], $v[2]) . '-' . substr($new, 3+$v[0]+$v[1]+$v[2], 1) ;
		// split = 3, $v[0], $v[1], $v[2], 1
	} else {
		return $isbn;
	}
  }
}
