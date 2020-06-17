<?php
declare(strict_types=1);

/*
 * Contains constants and text-parsing functions for wikitext comments.
 */

// If you ever change PLACEHOLDER_TEXT, please update expandFns.php::remove_comments

interface WikiStuffInterface
{
  public function parse_text(string $text) : void ;
  public function parsed_text() : string ;
  public static function get_treat_identical_seperately() : bool;
  public static function get_regex() : string;
  public static function get_placeholder() : string;
}


abstract class WikiThings implements WikiStuffInterface {
  protected $rawtext;

  public function parse_text(string $text) : void {
    $this->rawtext = $text;
  }

  public function parsed_text() : string {
    if (!isset($this->rawtext)) die('Attempt to access undefined WikiThings');
    return $this->rawtext;
  }
  
  public static function get_treat_identical_seperately() : bool {
    return FALSE;
  }
}

final class Comment extends WikiThings {
  public static function get_regex() : string {
    return '~<!--.*?-->~us';
  }
  public static function get_placeholder : string (
    return '# # # CITATION_BOT_PLACEHOLDER_COMMENT %s # # #';
  }
}

final class Nowiki extends WikiThings {
  public static function get_regex() : string {
    return '~<nowiki>.*?</nowiki>~us';
  }
  public static function get_placeholder : string (
    return '# # # CITATION_BOT_PLACEHOLDER_NOWIKI %s # # #';
  }
}

final class Chemistry extends WikiThings {
  public static function get_regex() : string {
    return '~<chem>.*?</chem>~us';
  }
  public static function get_placeholder : string (
    return '# # # CITATION_BOT_PLACEHOLDER_CHEMISTRY %s # # #';
  }
}

final class Mathematics extends WikiThings {
  public static function get_regex() : string {
    return '~<math>.*?</math>~us';
  }
  public static function get_placeholder : string (
    return '# # # CITATION_BOT_PLACEHOLDER_MATHEMATICS %s # # #';
  }
}

final class Musicscores extends WikiThings {
  public static function get_regex() : string {
    return '~<score>.*?</score>~us';
  }
  public static function get_placeholder : string (
    return '# # # CITATION_BOT_PLACEHOLDER_MUSIC %s # # #';
  }
}

final class Preformated extends WikiThings {
  public static function get_regex() : string {
    return '~<pre>.*?</pre>~us';
  }
  public static function get_placeholder : string (
    return '# # # CITATION_BOT_PLACEHOLDER_PREFORMAT %s # # #';
  }
}

final class SingleBracket extends WikiThings {
  public static function get_regex() : string {
    return '~(?<!\{)\{[^\{\}]+\}(?!\})~us';
  }
  public static function get_placeholder : string (
    return '# # # CITATION_BOT_PLACEHOLDER_SINGLE_BRACKET %s # # #';
  }
}
