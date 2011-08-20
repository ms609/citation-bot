<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
  </head>
  <body onload="form.wpDiff.click()">
    <h1>Citation bot is running...</h1>
    <h3>Wait a moment whilst the bot runs.  You'll be returned to Wikipedia when it's done.</h3>
    <form id="form" method="post" accept-charset="UTF-8"
          action="<?=str_replace("&action=edit", "&action=submit", $_SERVER["HTTP_REFERER"])?>">
      <textarea rows="20" cols="90" name="wpTextbox1"><?php
      error_reporting(E_ALL^E_NOTICE);
      $accountSuffix = '_1'; // Keep this before including expandFns
      $html_output = -1;
      include("expandFns.php");
      $editInitiator = '[txt' . revisionID() . ']';

      $postvars = $_POST;
      echo htmlentities(expand_text(
              mb_convert_encoding($postvars["wpTextbox1"], "UTF-8")), ENT_QUOTES, 'UTF-8');
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
      <input id="wpDiff" name="wpDiff" type="submit" value="Show changes" accesskey="v" title="Show which changes the bot made to the text [v]" />
    </form>
  </body>
</html>
