<?php
$output = [];
exec ("git fetch --all", $output);
exec ("git reset --hard", $output);
exec ("git pull", $output, $return_var);
?><pre>
<?php foreach($output as $line) print "$line \n"; ?>
</pre>
<?php if ($return_var) {
  echo "Returned error code $return_var";
} else {
  echo "Operation successful.";
}
?>
