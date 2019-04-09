<?php
echo "<!DOCTYPE html><html><body><pre>";
echo shell_exec ("/usr/bin/git checkout constants/capitalization.php 2>&1");
echo shell_exec ("/usr/bin/git pull  2>&1");
echo "</pre></body></html>"
?>
