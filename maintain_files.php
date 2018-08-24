<?php
$start_alpha = '/* The following will be automatically updated to alphabetical order */';
$end_alpha = '/* The above will be automatically updated to alphabetical order */';
$filename = 'constants/capitalization.php';
$contents = file_get_contents($filename);
$sections = explode($start_alpha, $contents);
foreach ($sections as &$section) {
  $alpha_end = stripos($section, $end_alpha);
  $alpha_bit = substr($section, 0, $alpha_end);
  $alpha_bits = explode(',', $alpha_bit);
  $alpha_bits = array_map('trim', $alpha_bits);
  sort($alpha_bits, SORT_NATURAL | SORT_FLAG_CASE);
  $bits_length = array_map('strlen', $alpha_bits);
  $bit_length = current($bits_length);
  $chunk_length = 0;
  $new_line = "\n          ";
  $alphaed = $new_line;
  $line_length = 10;
  foreach ($bits_length as $bit_length) {
    $bit = next($alpha_bits);
    $alphaed .= $bit ? ($bit . ', ') : '';
    $line_length += $bit_length + 2;
    if ($line_length > 86) {
      $alphaed .= $new_line;
      $line_length = 10;
    }
  }
  $section = $alphaed . substr($section, $alpha_end);
}
file_put_contents($filename, implode($start_alpha, $sections));
?>