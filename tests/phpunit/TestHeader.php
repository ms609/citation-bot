  <?php
  protected function setUp() {
  }
  protected function tearDown() {
  }
  
  protected function prepare_citation($text) {
    $this->assertEquals('{{', mb_substr($text, 0, 2));
    $this->assertEquals('}}', mb_substr($text, -2));
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
  
  protected function process_page($text) {  // Only used if more than just a citation template
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }
  
  protected function expand_via_zotero($text) {
    $expanded = $this->prepare_citation($text);
    expand_by_zotero($expanded);
    $expanded->tidy();
    return $expanded;
  }

  protected function requires_secrets($function) {
    if (getenv('TRAVIS_PULL_REQUEST')) {
      echo 'S'; // Skipping test: Risks exposing secret keys
      $this->assertNull(NULL); // Make Travis think we tested something
    } else {
      $function();
    }
  }

  protected function getDateAndYear($input){
    // Generates string that makes debugging easy and will throw error
    if (is_null($input->get('year'))) return $input->get('date') ; // Might be null too
    if (is_null($input->get('date'))) return $input->get('year') ;
    return 'Date is ' . $input->get('date') . ' and year is ' . $input->get('year');
  }
