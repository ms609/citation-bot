<?php
// outputs a list of wikipedia pages linked to
// usage: https://tools.wmflabs.org/citations/get_linked_pages.php?<PAGE>

header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
header("Content-Type: text/plain");

$page = str_replace(' ', '_', trim($_GET));
if ($page == '') exit('No parameters passed');
if (strlen($page) > 128) exit('Excessively long page name passed');

$url = 'https://en.wikipedia.org/w/api.php?action=parse&prop=links&page=' . $page . '&format=json';
$json = file_get_contents($url);
$array = json_decode($json, true);
$links = $array['parse']['links'];
foreach($links as $link) {
    if (isset($link['exists']) && ($link['ns'] == 0 || $link['ns'] == 118)) {
        echo str_replace(' ', '_', $link['*']) . "\n";
    }
}
echo  "\n";
echo  "\n";

exit(0);
?>
