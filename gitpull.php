<html>
<head><title>Update Citation Bot</title>
<body>
Pulling <a href="https://github.com/ms609/citation-bot">latest Git repo</a>:
<?php
exec ("git pull", $output, $return_var);
?>
<pre>
<?php foreach($output as $line) print "$line \n"; ?>
</pre>
<?php if ($return_var) {
  echo "Returned error code $return_var";
} else {
  echo "Operation successful.";
}
?>
</body>
</html>