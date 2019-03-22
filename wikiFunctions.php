<?php

function is_valid_user($user) {
  if (!$user) return FALSE;
  $headers_test = @get_headers('https://en.wikipedia.org/wiki/User:' . urlencode(str_replace(" ", "_", $user)), 1);
  if ($headers_test === FALSE) return FALSE;
  if (strpos((string) $headers_test[0], '404')) return FALSE;  // Even non-existent pages for valid users do exist.  They redirect, but do exist
  if (strpos((string) $headers_test[0], '301')) return FALSE;  // This user used to exist, but changed names
  return TRUE;
}

function wikify_external_text($title) {
  $replacement = [];
  if (preg_match_all("~<(?:mml:)?math[^>]*>(.*?)</(?:mml:)?math>~", $title, $matches)) {
    $placeholder = [];
    for ($i = 0; $i < count($matches[0]); $i++) {
      $replacement[$i] = '<math>' . 
        str_replace(array_keys(MML_TAGS), array_values(MML_TAGS), 
          str_replace(['<mml:', '</mml:'], ['<', '</'], $matches[1][$i]))
        . '</math>';
      $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i); 
      // Need to use a placeholder to protect contents from URL-safening
      $title = str_replace($matches[0][$i], $placeholder[$i], $title);
    }
  }
  $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
  $title = preg_replace("~\s+~"," ", $title);  // Remove all white spaces before
  if (mb_substr($title, -6) == "&nbsp;") $title = mb_substr($title, 0, -6);
  if (mb_substr($title, -1) == ".") {
    $last_word = mb_substr($title, mb_strpos($title, ' ') + 1);
    if (mb_substr_count($last_word, '.') === 1) $last_word = mb_substr($title, 0, -1); // Do not remove if something like D.C.  (will not catch D. C. though)
  }
  $title = preg_replace('~[\*]$~', '', $title);
  $title = title_capitalization($title, TRUE);
  
  // The following two do not allow < within the inner match since the end tag is the same :-( and they might nest or who knows what
  $title = preg_replace_callback('~(?:<Emphasis Type="Italic">)([^<]+)(?:</Emphasis>)~iu',
      function ($matches) {return ("<i>" . $matches[1]. "</i>");},
      $title);
  $title = preg_replace_callback('~(?:<Emphasis Type="Bold">)([^<]+)(?:</Emphasis>)~iu',
      function ($matches) {return ("<b>" . $matches[1]. "</b>");},
      $title);
  
  $originalTags = array("<i>","</i>", '<title>', '</title>',"From the Cover: ");
  $wikiTags = array("''","''",'','',"");
  $htmlBraces  = array("&lt;", "&gt;");
  $angleBraces = array("<", ">");
  $title = sanitize_string(// order of functions here IS important!
             str_ireplace($originalTags, $wikiTags, 
               str_ireplace($htmlBraces, $angleBraces, $title)
             )
           );
  
  for ($i = 0; $i < count($replacement); $i++) {
    $title = str_replace($placeholder[$i], $replacement[$i], $title);
  }
  return($title); 
}

/**
 * Cannot really test in TRAVIS
 * @codeCoverageIgnore
 */
function throttle ($min_interval) {
  static $last_write_time = 0;
  $time_since_last_write = time() - $last_write_time;
  if ($time_since_last_write < $min_interval) {
    $time_to_pause = floor($min_interval - $time_since_last_write);
    report_warning("Throttling: waiting $time_to_pause seconds...");
    for ($i = 0; $i < $time_to_pause; $i++) {
      sleep(1); 
      report_inline(' .');
    }
  }
  $last_write_time = time();
}

function sanitize_string($str) {
  // ought only be applied to newly-found data.
  if (trim($str) == 'Science (New York, N.Y.)') return 'Science';
  $math_templates_present = preg_match_all("~<\s*math\s*>.*<\s*/\s*math\s*>~", $str, $math_hits);
  if ($math_templates_present) {
    $replacement = [];
    $placeholder = [];
    for ($i = 0; $i < count($math_hits[0]); $i++) {
      $replacement[$i] = $math_hits[0][$i];
      $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i);
    }
    $str = str_replace($replacement, $placeholder, $str);
  }
  $dirty = array ('[', ']', '|', '{', '}');
  $clean = array ('&#91;', '&#93;', '&#124;', '&#123;', '&#125;');
  $str = trim(str_replace($dirty, $clean, preg_replace('~[;.,]+$~', '', $str)));
  if ($math_templates_present) {
    $str = str_replace($placeholder, $replacement, $str);
  }
  return $str;
}

