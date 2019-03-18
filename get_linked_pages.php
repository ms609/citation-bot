<?php
// outputs a list of wikipedia pages linked to
// usage: https://tools.wmflabs.org/citations/get_linked_pages.php?page=<PAGE>
// <PAGE> will need to be URL encoded

header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
header("Content-Type: text/plain");

require_once('constants/bad_data.php');
$page = str_replace(' ', '_', trim($_REQUEST['page']));
if ($page == '') exit('Nothing requested');
if (strlen($page) > 128) exit('Excessively long page name passed');

$url = 'https://en.wikipedia.org/w/api.php?action=parse&prop=links&format=json&page=' . $page;
$json = file_get_contents($url);
$array = json_decode($json, true);
$links = $array['parse']['links'];

echo "\n";
foreach($links as $link) {
    if (isset($link['exists']) && ($link['ns'] == 0 || $link['ns'] == 118)) {  // normal and draft articles only
        $linked_page = str_replace(' ', '_', $link['*']);
        if(!in_array($linked_page, AVOIDED_LINKS)) {
            echo $linked_page . "|";
        }
    }
}
echo  "\n";

exit(0);
?>
