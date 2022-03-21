<?php
declare(strict_types=1);
set_time_limit(120);
try {
 @header('Access-Control-Allow-Origin: *'); //This is ok because the API is not authenticated
 @header('Content-Type: text/json');
 @header('Cache-Control: no-cache, no-store, must-revalidate');
 @header('Pragma: no-cache');
 @header('Expires: 0');

 //Set up tool requirements
 require_once 'setup.php';

 $originalText = (string) $_POST['text'];
 $editSummary = (string) $_POST['summary'];

 if (strlen(trim($originalText)) < 4) {
   throw new Exception('tiny');  // @codeCoverageIgnore
 }

 //Expand text from postvars
 $page = new Page();
 gc_collect_cycles();
 $page->parse_text($originalText);
 $page->expand_text();
 $newText = $page->parsed_text();
 if ($newText == "") $newText = $originalText; // Something went very wrong

 //Modify edit summary to identify bot-assisted edits
 if ($newText !== $originalText) {
   if ($editSummary) $editSummary .= ' | '; // Add pipe if already something there.
   $editSummary .=  str_replace('Use this bot', 'Use this tool', $page->edit_summary()) . '| #UCB_Gadget ';
 }

 ob_end_clean();

 /**
   * @psalm-taint-escape html
   * @psalm-taint-escape has_quotes
   */
 $result = array(
   'expandedtext' => $newText,
   'editsummary' => $editSummary
 );

 echo (string) @json_encode($result);
} catch (Throwable $e) {                          // @codeCoverageIgnore
 @ob_end_clean();@ob_end_clean();@ob_end_clean(); // @codeCoverageIgnore
 // Above is paranoid panic code.  So paranoid that we even flush buffers two extra times
}
