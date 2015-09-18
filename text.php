<?php header('Content-Type: text/html; charset="UTF-8"');?>
<html>
  <body onload="form.wpDiff.click()">
    <?php
//      error_reporting(E_ALL^E_NOTICE);
      $account_suffix = '_1'; // Keep this before including expandFns
      global $html_output;
      $html_output = -1;
      // this is absolute because cwd/__DIR__ = /data/projects/citations-dev/ when redirected from the expand-citations gadget
      require_once('/data/project/citations-dev/public_html/expandFns.php');
      $html_output = 0;
      $edit_initiator = '[txt' . revisionID() . ']';
      $postvars = $_POST;
      $page = new Page();
      $page->text = mb_convert_encoding($postvars["wpTextbox1"], "UTF-8");
    ?>
    <h1>Citation bot v. <?=$last_revision_id?> is running...</h1>
    <h3>Wait a moment whilst the bot runs.  You'll be returned to Wikipedia when it's done.</h3>
    <?php
      $page->expand_text();
    ?>
    <form id="form" method="post" action="<?=str_replace("&action=edit", "&action=submit", $_SERVER["HTTP_REFERER"])?>">
      <textarea rows="20" cols="90" name="wpTextbox1"><?php
      echo htmlentities($page->text, ENT_COMPAT | ENT_HTML401, 'UTF-8');
    ?></textarea>
      <?php
unset ($postvars["wpTextbox1"]);
$postvars["wpSummary"] .= stripos($postvars["wpSummary"], "citation bot")
        ? ""
        : " | [[WP:UCB|Assisted by Citation bot r" . revisionID() . ']]';
foreach ($postvars as $key => $value) {
  echo "\n\t<input type=\"hidden\" name=\"$key\" value=\"" . str_replace('"', '&#34;', $value) . "\" />";
}
?>    <br />
      <input id="wpDiff" name="wpDiff" type="submit" value="View changes on edit page" accesskey="v" title="Go back to the edit page, showing which changes the bot made to the text [v]" />
    </form>
  </body>
</html>
