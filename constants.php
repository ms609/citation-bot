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
const TOOLFORGE_IP = '208.80.155.131';
const BRACESPACE = "!BOTCODE-spaceBeforeTheBrace";

//Common replacements
const HTML_DECODE = array("[", "]", "<", ">", " ");
const HTML_ENCODE = array("&#x5B;", "&#x5D;", "&#60;", "&#62;", "+");

const HTML_DECODE_DOI = array("[", "]", "<", ">");
const HTML_ENCODE_DOI = array("&#x5B;", "&#x5D;", "&#60;", "&#62;");

const DOT_ENCODE = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
const DOT_DECODE = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

// Some data we get from outside sources is bad or at least mis-defined
// Use lower case for all of these, and then compare to a lower cased version
const BAD_AUTHORS = array("unknown", "missing");
const AUTHORS_ARE_PUBLISHERS = array(); // Things from google like "hearst magazines", "time inc", 
                                        // "nielsen business media, inc" that the catch alls do not detect
const AUTHORS_ARE_PUBLISHERS_ENDINGS = array("inc.", "inc", "magazines", "press", "publishing",
                                             "publishers", "books", "corporation");
const HAS_NO_VOLUME = array("zookeys");  // Some journals have issues only, no volume numbers
const BAD_TITLES = array("unknown", "missing", "arxiv e-prints");

// dontCap is am array of strings that should not be capitalized in their titlecase format; 
// unCapped is their correct capitalization. Remember to enclose any word in spaces.

const LC_SMALL_WORDS = array(' and then ', ' of ',' the ',' and ',' an ',' or ',' nor ',' but ',' is ',' if ',' then ',' else ',' when', 'at ',' from ',' by ',' on ',' off ',' for ',' in ',' over ',' to ',' into ',' with ',' U S A ',' USA ',' y ', ' el ', ' für ', ' de ', ' zur ', ' der ', ' und ', ' du ', ' et ', ' la ', ' le ', ' DNA ', ' UK ', ' FASEB ', ' van ', ' von ', ' AJHG ', ' BBA ', ' BMC ', ' BMJ ', ' EMBO ', ' FEBS ', ' FEMS ', ' JAMA ', ' MNRAS ', ' NEJM ', ' NYT ', ' PCR ', ' PNAS ', ' RNA ');
const UC_SMALL_WORDS = array(' and Then ', ' Of ',' The ',' And ',' An ',' Or ',' Nor ',' But ',' Is ',' If ',' Then ',' Else ',' When', 'At ',' From ',' By ',' On ',' Off ',' For ',' In ',' Over ',' To ',' Into ',' With ',' U S A ',' Usa ',' Y ', ' El ', ' Für ', ' De ', ' Zur ', ' Der ', ' Und ', ' Du ', ' Et ', ' La ', ' Le ', ' Dna ', ' Uk ', ' Faseb ', ' Van ', ' Von ', ' Ajhg ', ' Bba ', ' Bmc ', ' Bmj ', ' Embo ', ' Febs ', ' Fems ', ' Jama ', ' Mnras ', ' Nejm ', ' Nyt ', ' Pcr ', ' Pnas ', ' Rna ');

const DOI_URL_ENCODE = array("%23", "%3C", "%3E");
const DOI_URL_DECODE = array("#", "<", ">");

const DATES_WHATEVER = FALSE; // PHP has no native enum
const DATES_MDY      = 1;
const DATES_DMY      = 2;
