<?php
exec ("git fetch --all", $output, $return_var);
?><pre>
<?php foreach($output as $line) print "$line \n"; ?>
</pre>
<?php if ($return_var) {
  echo "Returned error code $return_var";
  if ($return_var == 1) {
    echo "\n<br /> Check that there are no uncommitted changes on the server.";
  }
} else {
  echo "Git Fetch operation successful.";
}
?>
<pre>
<?php
exec ("git show --oneline -s", $output, $return_var);
foreach ($output as $line) print "$line \n";
?>
</pre>
