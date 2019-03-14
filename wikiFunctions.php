<?php
/**
 * Unused
 * @codeCoverageIgnore
 */
function wiki_link($page, $style = "#036;", $target = NULL) {
  if (!$target) $target = $page;
  $css = $style?" style='color:$style !important'":"";
  return "<a href='" . WIKI_ROOT . "?title=" . urlencode($target) . "' title='$page ($target) on Wikipedia'$css>$page</a>";
}


function is_valid_user($user) {
  echo "\n USER IS " . $user . "\n";
  if (!$user) {
          echo "\n" . "BLANK" . "\n";
    return FALSE;
  }
  $headers_test = @get_headers('https://en.wikipedia.org/wiki/User:' . urlencode(str_replace(" ", "_", $user)), 1);
  if ($headers_test === FALSE) {
      echo "\n" . "FALSE" . "\n";
    return FALSE;
  }
  
   echo "\n" . (string) $headers_test . "\n";
  if (strpos((string) $headers_test[0], '404')) return FALSE;  // Even non-existent pages for valid users do exist.  They redirect, but do exist
  return TRUE;
}
