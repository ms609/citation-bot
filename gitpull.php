<?php
echo "<!DOCTYPE html><html><body><pre>";
if (mkdir('git_pull.lock', 0700)) {
  echo shell_exec ("/usr/bin/git fetch  2>&1"); // This only updates .git, so it is very safe. This is first half of pull
  echo shell_exec ("/usr/bin/git checkout constants/capitalization.php 2>&1");
  echo shell_exec ("/usr/bin/git pull  2>&1");
  rmdir('git_pull.lock') ;
} else {
  echo 'lock file exists -- aborting ';
}
echo "</pre></body></html>"
?>
