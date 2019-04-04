<?php
// Local changes seem to accumulate in capitalization.php, preventing a pull
exec ("git checkout constants/capitalization.php", $output); 
exec ("git pull", $output, $return_var);
#exec ("git fetch --all", $output, $return_var); // Doesn't seem to do much...
?><pre>
<?php foreach($output as $line) echo "$line \n<br />"; ?>
</pre>
<?php if ($return_var) {
  echo "Returned error code $return_var \n<br />";
  if ($return_var == 1) {
    echo "\n<br /> Check that there are no uncommitted changes on the server.\n<br />";
    unset($output);
    exec("git status -uno", $output, $return_var);
    foreach ($output as $line) echo "$line \n<br />";
  }
} else {
  echo "Successfully updated from Git repository.\n<br />";
}
?>
<pre>
<?php
unset($output);
exec("git show --oneline -s", $output, $return_var);
foreach ($output as $line) echo "$line \n<br />";
?>
</pre>
