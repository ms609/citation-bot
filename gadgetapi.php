<?php
header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
header("Content-Type: text/json");

// This is needed because the Gadget API expects only JSON back, and nothing else.
// This buffer is later closed with ob_end_clean() which deletes the buffer without printing it
// This needs to be the absolute first thing done (Other than the header lines that must be the zeroth thing done)
ob_start();

try {
  //Set up tool requirements
  require_once __DIR__ . '/expandFns.php';

  $originalText = $_POST['text'];
  $editSummary = $_POST['summary'];

  //Expand text from postvars
  $page = new Page();
  $page->text = $originalText;

  $page->expand_text();

  //Modify edit summary to identify bot-assisted edits
  if ($editSummary) {
    $editSummary .= " | ";
  }
  $editSummary .= "[[WP:UCB|Assisted by Citation bot]]";

  $result = array(
    'expandedtext' => $page->text,
    'editsummary' => $editSummary,
  );
} catch (Exception $e) {
   exit(0);  // This just exists to block any output to STDERR about uncaught exceptions being passed back to Wikipedia
}

// Throw away all output
ob_end_clean();

echo json_encode($result);  // On error returns "FALSE", which makes echo print nothing.  Thus we do not have to check for FALSE
