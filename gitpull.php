<?php
echo "<!DOCTYPE html><html><head><title>Update</title></head><body><pre>";
// Local changes seem to accumulate in capitalization.php, preventing a pull
echo shell_exec ("git checkout constants/capitalization.php");
exec ("git pull", $output, $return_var);
foreach($output as $line) echo "$line \n";

if ($return_var) {
  if ($return_var == 1) {
    echo "\n Failure.  Check that there are no uncommitted changes on the server.\n\n";
    unset($output);
    echo shell_exec("git status");
  } else {
    echo "\n Returned error code $return_var \n\n";
  }
} else {
  echo "Successfully updated from Git repository.\n";
}

unset($output);
exec("git show --oneline -s", $output, $return_var);
foreach ($output as $line) echo "$line \n";

echo "</pre></body></html>"
?>
