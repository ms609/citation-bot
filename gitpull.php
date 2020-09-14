<?php
@header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
?>
<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Git Pull</title></head><body><pre>
<?php
ob_implicit_flush();
if (mkdir('git_pull.lock', 0700)) {
  /** @psalm-suppress ForbiddenCode */
  echo shell_exec ("/usr/bin/git fetch  2>&1"); // This only updates .git, so it is very safe. This is first half of pull
  /** @psalm-suppress ForbiddenCode */
  echo shell_exec ("/usr/bin/git pull  2>&1");
  rmdir('git_pull.lock') ;
} else {
  echo 'lock file exists -- aborting ';
}
?>
</pre></body></html>
