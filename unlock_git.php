<?php
ob_implicit_flush();
echo "<!DOCTYPE html><html><head><title>Unlocker</title></head><body><pre>" . phpversion(). "\n";
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></body></html>\n");
// Make the person wait.  I know how I can be.
echo "Wait 6</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></body></html>\n");
echo "Wait 5</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></body></html>\n");
echo "Wait 4</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></body></html>\n");
echo "Wait 3</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></body></html>\n");
echo "Wait 2</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></body></html>\n");
echo "Wait 1</p>\n";
sleep(1);
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></body></html>\n");
rmdir('git_pull.lock') ;
if (file_exists('git_pull.lock')) exit("Lock file not gone</pre></body></html>\n");
echo "Script done</p>\n</pre></body></html>\n"
?>
