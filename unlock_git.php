<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Unlocker</title></head><body><pre>
<?php
ob_implicit_flush();
echo phpversion() . "\n";
if (!file_exists('git_pull.lock')) exit("Lock file gone");
// Make the person wait.  I know how I can be.
echo "Wait 6</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone");
echo "Wait 5</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone");
echo "Wait 4</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone");
echo "Wait 3</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone");
echo "Wait 2</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone");
echo "Wait 1</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone");
rmdir('git_pull.lock') ;
if (file_exists('git_pull.lock')) exit("Lock file not gone");
echo "Script done</p>"
?>
</pre></body></html>
