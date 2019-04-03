<?php
header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
header("Content-Type: text/json");

// This is needed because the Gadget API expects only JSON back, therefore ALL output from the citation bot is thrown away
define("FLUSHING_OKAY", FALSE);

$SLOW_MODE = FALSE;
if (isset($_REQUEST["slow"])) $SLOW_MODE = TRUE;

//Set up tool requirements
require_once('setup.php');

$originalText = $_POST['text'];
$editSummary = $_POST['summary'];

//Expand text from postvars
$page = new Page();
$page->parse_text($originalText);
$page->expand_text();
$newText = $page->parsed_text();
if ($newText == "") $newText = $originalText; // Something went very wrong

//Modify edit summary to identify bot-assisted edits
if ($newText !== $originalText) {
  if ($editSummary) $editSummary .= ' | '; // Add pipe if already something there.
  $editSummary .=  str_replace('use this bot', 'use this tool', $page->edit_summary()) . ' ';
} elseif (!$editSummary) {
  $editSummary = "";
}

if (isset($_REQUEST['debug']) && $_REQUEST['debug']==='1') {
  $debug_text = ob_get_flush();
} else {
  $debug_text = '';
  ob_end_clean();
}

$result = array(
  'expandedtext' => $newText,
  'editsummary' => $editSummary,
  'debug' => $debug_text,
);

echo @json_encode($result);  // On error returns "FALSE", which makes echo print nothing.  Thus we do not have to check for FALSE
