<?php
header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
header("Content-Type: text/json");

//Configure setting to suppress buffered output
define("HTML_OUTPUT", -1);

//Set up tool requirements
require_once __DIR__ . '/expandFns.php';

$originalText = $_POST['text'];
$editSummary = $_POST['summary'];

//Expand text from postvars
$page = new Page();
$page->text = $originalText;
$page->expand_text( FALSE );

//Modify edit summary to identify bot-assisted edits
if ($editSummary) {
  $editSummary .= " | ";
}
$editSummary .= "[[WP:UCB|Assisted by Citation bot]]";

$result = array(
  'expandedtext' => $page->text,
  'editsummary' => $editSummary,
);

echo json_encode($result);
