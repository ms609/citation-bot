<html>
Pulling <a href="https://github.com/ms609/citation-bot">latest Git repo</a>:
<?php
exec ("git pull", $output, $return_var);
var_dump($return_var);
?>
<pre>
<?php for ($line in $output) print "$line \n"; ?>
</pre>
Operation complete.
</html>