<html>
  <body onload="form.submit()">
    <h1>Citation bot is running...</h1>
    <h3>Wait a moment whilst the bot runs.  You'll be returned to Wikipedia when it's done.</h3>
    <form id="form" method="post" target="<?=$_SERVER["HTTP_REFERER"]?>">
      <textarea rows="50" cols="50" name="wpTextbox1"><?php
      error_reporting(E_ALL^E_NOTICE);
      $accountSuffix = '_1'; // Keep this before including expandFns
      $html_output = -1;
      include("expandFns.php");
      $editInitiator = '[txt' . revisionID() . '&beta;]';

      $postvars = $_POST;
      echo html_entity_encode(expand_text($postvars["wpTextbox1"]));
    ?></textarea>
      <?php
unset ($postvars["wpTextbox1"]);
$postvars["wpSummary"] .= " [[WP:UCB|Assisted by Citation bot]]";
foreach ($postvars as $key => $value) {
  echo "<input type=hidden name=$key value=$value />";
}
?>
      <input id="wpDiff" name="wpDiff" type="submit" value="Show changes" accesskey="v" title="Show which changes the bot made to the text [v]" />
    </form>
  </body>
</html>
