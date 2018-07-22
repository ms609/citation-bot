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
  if (!$user) return FALSE;
  $headers_test = @get_headers('https://en.wikipedia.org/wiki/User:' . urlencode($user), 1);
  if ($headers_test === FALSE) return FALSE;
  if (strpos((string) $headers_test[0], '404')) return FALSE;  // Even non-existent pages for valid users do exist.  They redirect, but do exist
  return TRUE;
}
