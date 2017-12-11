<?php
header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
header("Content-Type: text/json");

// This is needed because the Gadget API expects only JSON back, therefore ALL output from the citation bot is thrown away
ob_start();
  
//Set up tool requirements
require_once __DIR__ . '/expandFns.php';

$originalText = $_POST['text'];
$editSummary = $_POST['summary'];

//Expand text from postvars
$page = new Page();
$page->parse_text($originalText);
$page->expand_text();

//Modify edit summary to identify bot-assisted edits
if ($editSummary) {
  $editSummary .= " | ";
}
$editSummary .= "[[WP:UCB|Assisted by Citation bot]]";

if (isset($_REQUEST['debug']) && $_REQUEST['debug']==='1') {
  $debug_text = ob_get_contents();
} else {
  $debug_text = '';
}

$result = array(
  'expandedtext' => $page->parsed_text(),
  'editsummary' => $editSummary,
  'debug' => $debug_text;
);

// Throw away all output
ob_end_clean();

echo @json_encode($result);  // On error returns "FALSE", which makes echo print nothing.  Thus we do not have to check for FALSE
