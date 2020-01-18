<?php
ob_implicit_flush();
echo "<!DOCTYPE html><html><body><pre>\n";
if (!file_exists('git_pull.lock')) die('Lock file gone');
// Make the person wait.  I know how I can be.
echo "Wait 6</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) die('Lock file gone');
echo "Wait 5</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) die('Lock file gone');
echo "Wait 4</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) die('Lock file gone');
echo "Wait 3</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) die('Lock file gone');
echo "Wait 2</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) die('Lock file gone');
echo "Wait 1</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) die('Lock file gone');
rmdir('git_pull.lock') ;
echo "Script done</p>\n";
if (file_exists('git_pull.lock')) die('Lock file not gone!');
echo "</pre></body></html>\n"
?>
