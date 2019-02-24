<?php
// outputs a list of wikipedia pages linked to
// usage: https://tools.wmflabs.org/citations/get_linked_pages.php?page=<PAGE>
// PAGE in some cases will need to be URL encoded

header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
header("Content-Type: text/plain");

$page = str_replace(' ', '_', trim($_REQUEST['page']));
if ($page == '') exit('Nothing requested');
if (strlen($page) > 128) exit('Excessively long page name passed');

$url = 'https://en.wikipedia.org/w/api.php?action=parse&prop=links&format=json&page=' . $page;
$json = file_get_contents($url);
$array = json_decode($json, true);
$links = $array['parse']['links'];

// List of things to not print links to, since they occur all the time
const AVOIDED_LINKS = array('', 'Digital_object_identifier', 'JSTOR', 'Website', 'International_Standard_Book_Number',
                            'Library_of_Congress_Control_Number', 'Handle_System', 'PubMed_Central', 'PubMed',
                            'PubMed_Identifier', 'Bibcode', 'International_Standard_Serial_Number', 'bioRxiv',
                            'CiteSeerX', 'Zentralblatt_MATH', 'Jahrbuch_über_die_Fortschritte_der_Mathematik',
                            'Mathematical_Reviews', 'Office_of_Scientific_and_Technical_Information',
                            'Request_for_Comments', 'Social_Science_Research_Network', 'Zentralblatt_MATH',
                            'Open_Library', 'ArXiv', 'OCLC', 'Cf.');

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
