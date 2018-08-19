<?php

/*
 * Tests for Parameter.php.
 */
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

final class ParameterTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
    if (!defined("PIPE_PLACEHOLDER")) {
// this is usually done elsewhere in the code
      define("PIPE_PLACEHOLDER", '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #');
    }
  }

  protected function tearDown() {
  }

  protected function parameter_parse_text_helper($text) {
    $parameter = new Parameter();
    $parameter->parse_text($text);
    return $parameter;
  }

/*
 * FIXME: these tests have too many assertions. Probably will require some refactoring of Parameter::parse_text().
 */
  public function testValueWithPipeAndTrailingNewline() {
    $text = "last1 = [[:en:Bigwig# # # CITATION_BOT_PLACEHOLDER_PIPE # # #SomeoneFamous]]\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'last1');
    $this->assertEquals($parameter->eq, ' = ');
    $this->assertEquals($parameter->val, '[[:en:Bigwig|SomeoneFamous]]');
    $this->assertEquals($parameter->post, "\n");
  }
  public function testParameterWithNoParamName() {
    $text = " = no param name";
    $parameter = $this->parameter_parse_text_helper($text);
    $this->assertEquals(' = ', $parameter->eq);
  }

  public function testBlankValueWithSpacesLeadingSpaceTrailingNewline() {
    $text = " first1 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, ' ');
    $this->assertEquals($parameter->param, 'first1');
    $this->assertEquals($parameter->eq, ' = ');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueWithSpacesAndTrailingNewline() {
    $text = "first2 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'first2');
    $this->assertEquals($parameter->eq, ' = ');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueWithPreEqSpaceAndTrailingNewline() {
    $text = "first3 =\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'first3');
    $this->assertEquals($parameter->eq, ' =');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueWithPostEqSpaceAndTrailingNewline() {
    $text = "first4= \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'first4');
    $this->assertEquals($parameter->eq, '= ');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueNoSpacesTrailingNewline() {
    $text = "first5=\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'first5');
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testBlankValueNoEquals() {
    $text = "first6 \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, '');
    $this->assertEquals($parameter->eq, '');
    $this->assertEquals($parameter->val, 'first6');
    $this->assertEquals($parameter->post, " \n");
  }

  // This test may not work, depending on your test environment.
  // Works on Tool Labs with PHP 5.5.9-1ubuntu4.13 (cli), PHPUnit 3.7.28
  public function testBlankValueNonBreakingSpaces() {
    $text = " first7 = \n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, ' first7 ');  //These are non-breaking spaces
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, ' ');  //This is a non-breaking space
    $this->assertEquals($parameter->post, "\n");
  }

  public function testMultilinevalueTrailingNewline() {
    $text = "param=multiline\nvalue\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, "param");
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, "multiline\nvalue");
    $this->assertEquals($parameter->post, "\n");
  }

  public function testMultilineParamTrailingNewline() {
    $text = "multiline\nparam=\n";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, "multiline\nparam");
    $this->assertEquals($parameter->eq, '=');
    $this->assertEquals($parameter->val, '');
    $this->assertEquals($parameter->post, "\n");
  }

  public function testHasProtectedCommentInValue() {
    $text = "archivedate= 24 April 2008 # # # Citation bot : comment placeholder 0 # # #";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'archivedate');
    $this->assertEquals($parameter->eq, '= ');
    $this->assertEquals($parameter->val, "24 April 2008 # # # Citation bot : comment placeholder 0 # # #");
    $this->assertEquals($parameter->post, "");
  }
  
  public function testHasUnreplacedCommentInValue() {
    $text = "archivedate= 9 August 2006 <!--DASHBot-->";
    $parameter = $this->parameter_parse_text_helper($text);

    $this->assertEquals($parameter->pre, '');
    $this->assertEquals($parameter->param, 'archivedate');
    $this->assertEquals($parameter->eq, '= ');
    $this->assertEquals($parameter->val, "9 August 2006 <!--DASHBot-->");
    $this->assertEquals($parameter->post, "");
  }
    
  public function testWhiteList() {
      $we_failed = FALSE;
      
      $our_original_whitelist = PARAMETER_LIST;
      $our_whitelist = array_unique($our_original_whitelist);
      $our_whitelist_sorted = sort($our_whitelist);
      $context = stream_context_create(array(
        'http' => array('ignore_errors' => true),
      ));
      $wikipedia_response = @file_get_contents('https://en.wikipedia.org/w/index.php?title=Module:Citation/CS1/Whitelist&action=raw', FALSE, $context);
      preg_match_all("~\s\[\'([a-zA-Z0-9\#\-\_ ]+?)\'\] = ~" , $wikipedia_response, $matches);
      $their_whitelist = $matches[1];
      $patent_whitelist = array('inventor', 'inventor#', 'inventor-surname', 'inventor#-surname', 'inventor-last',
                                'inventor#-last', 'inventor-given', 'inventor#-given', 'inventor-first', 'inventor#-first',
                                'inventor-first#', 'inventor-link', 'inventor#-link', 'inventor-link#', 'inventor#link',
                                'country-code', 'publication-number', 'patent-number', 'country', 'number', 'description',
                                'status', 'invent#', 'gdate', 'pubdate', 'publication-number', 'pridate', 'assign#',
                                'assignee', 'assign', 'inventor-surname#', 'inventor-last#', 'inventor-given#',
                                'inventorlink', 'inventorlink#', 'issue-date', 'fdate'); // Some are not valid, but people use them anyway
      $their_whitelist = array_merge($patent_whitelist,$their_whitelist);
      $their_whitelist = array_unique($their_whitelist); // They might list the same thing twice
      $their_whitelist = array_diff($their_whitelist, ["template doc demo"]);

      $our_extra = array_diff($our_whitelist, $their_whitelist);
      $our_missing = array_diff($their_whitelist, $our_whitelist);
      $our_internal_extra = array_diff($our_original_whitelist, $our_whitelist);
 
      if (count($our_internal_extra) !== 0) {
         echo "\n \n testWhiteList:  What the Citation Bot has more than one copy of\n";
         print_r($our_internal_extra);
         $we_failed = TRUE;
      }
      if (count($our_extra) !== 0) {
         echo "\n \n testWhiteList:  What the Citation Bot has that Wikipedia does not\n";
         print_r($our_extra);
         $we_failed = TRUE;
      }
      if (count($our_missing) !== 0) {
         echo "\n \n testWhiteList:  What Wikipedia has that the Citation Bot does not\n";
         print_r($our_missing);
         $we_failed = TRUE;
      }
      if ($our_whitelist !== $our_whitelist_sorted) {
         echo "\n \n testWhiteList:  Citation Bot has vales out of order.  Expected order:\n";
         print_r($our_whitelist_sorted);
         $we_failed = TRUE;
      }
      
      $this->assertEquals(FALSE,$we_failed);
  }
}



