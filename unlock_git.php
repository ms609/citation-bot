<?php
set_time_limit(120);

require_once 'html_headers.php';

ob_implicit_flush();

echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Unlocker</title></head><body><main><pre>';

if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></main></body></html>");
// Make the person wait.  I know how I can be.
echo "Wait 6<br>\n";
sleep(1);
clearstatcache();
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></main></body></html>");
echo "Wait 5<br>\n";
sleep(1);
clearstatcache();
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></main></body></html>");
echo "Wait 4<br>\n";
sleep(1);
clearstatcache();
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></main></body></html>");
echo "Wait 3<br>\n";
sleep(1);
clearstatcache();
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></main></body></html>");
echo "Wait 2<br>\n";
sleep(1);
clearstatcache();
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></main></body></html>");
echo "Wait 1<br>\n";
sleep(1);
clearstatcache();
if (!file_exists('git_pull.lock')) exit("Lock file gone</pre></main></body></html>");
rmdir('git_pull.lock') ;
clearstatcache();
if (file_exists('git_pull.lock'))  exit("Lock file not gone</pre></main></body></html>");
echo "Script done<br></pre></main></body></html>"
?>
