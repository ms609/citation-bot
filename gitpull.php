<html>
Pulling <a href="https://github.com/ms609/citation-bot">latest Git repo</a>...
<?php
exec ("git pull", $output, $return_var);
var_dump($return_var);
?>
<pre>
<?php print_r($output); ?>
</pre>
Operation complete.
</html>