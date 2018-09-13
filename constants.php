<?php 
$constant_files = glob('constants/*.php');
foreach ($constant_files as $file) {
    require($file);   
}

define('HOME', dirname(__FILE__) . '/');

const PIPE_PLACEHOLDER = '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #';
const TEMP_PLACEHOLDER = "# # # CITATION_BOT_PLACEHOLDER_TEMPORARY %s # # #";
const WIKI_ROOT = "https://en.wikipedia.org/w/index.php";
const API_ROOT = "https://en.wikipedia.org/w/api.php"; // wiki's API endpoint
const BRACESPACE = "!BOTCODE-spaceBeforeTheBrace";

//Common replacements
const HTML_DECODE = array("[", "]", "<", ">", " ");
const HTML_ENCODE = array("&#x5B;", "&#x5D;", "&#60;", "&#62;", "+");

const DOT_ENCODE = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
const DOT_DECODE = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");
