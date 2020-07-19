<?php
declare(strict_types=1);
try {
 @header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
 @header("Content-Type: text/json");

 // This is needed because the Gadget API expects only JSON back, therefore ALL output from the citation bot is thrown away
 $FLUSHING_OKAY = FALSE;

 //Set up tool requirements
 require_once('setup.php');

 $originalText = (string) $_POST['text'];
 $editSummary = (string) $_POST['summary'];

 //Expand text from postvars
 $page = new Page();
 $page->parse_text($originalText);
 $page->expand_text();
 $newText = $page->parsed_text();
 if ($newText == "") $newText = $originalText; // Something went very wrong

 //Modify edit summary to identify bot-assisted edits
 if ($newText !== $originalText) {
   if ($editSummary) $editSummary .= ' | '; // Add pipe if already something there.
   $editSummary .=  str_replace('use this bot', 'use this tool', $page->edit_summary()) . '| via #UCB_Gadget ';
 }

 ob_end_clean();

 $result = array(
   'expandedtext' => $newText,
   'editsummary' => $editSummary
 );

 echo (string) @json_encode($result);
} catch (Throwable $e) {                          // @codeCoverageIgnore
 @ob_end_clean();@ob_end_clean();@ob_end_clean(); // @codeCoverageIgnore
 // Above is paranoid panic code.  So paranoid that we even flush buffers two extra times
} finally {
  $FLUSHING_OKAY = TRUE; // Reset for Travis
}
