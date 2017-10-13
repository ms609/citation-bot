<?php 
$constant_files = glob('constants/*.php');
foreach ($constant_files as $file) {
    require($file);   
}

define('HOME', dirname(__FILE__) . '/');

const PIPE_PLACEHOLDER = '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #';
const TEMP_PLACEHOLDER = "# # # CITATION_BOT_PLACEHOLDER_TEMPORARY %s # # #";
const TO_EN_DASH = "--?|\&mdash;|\xe2\x80\x94|\?\?\?"; // regexp for replacing to ndashes using mb_ereg_replace
const EN_DASH = "\xe2\x80\x93"; // regexp for replacing to ndashes using mb_ereg_replace
const WIKI_ROOT = "https://en.wikipedia.org/w/index.php?";
const ISBN_KEY = "268OHQMW";
const API_ROOT = "https://en.wikipedia.org/w/api.php"; // wiki's API endpoint
const TEMPLATE_REGEXP = "~\{\{\s*([^\|\}]+)([^\{]|\{[^\{])*?\}\}~";
const BRACESPACE = "!BOTCODE-spaceBeforeTheBrace";
const BIBCODE_REGEXP = "~^(?:http://(?:\w+.)?adsabs.harvard.edu|http://ads\.ari\.uni-heidelberg\.de|http://ads\.inasan\.ru|http://ads\.mao\.kiev\.ua|http://ads\.astro\.puc\.cl|http://ads\.on\.br|http://ads\.nao\.ac\.jp|http://ads\.bao\.ac\.cn|http://ads\.iucaa\.ernet\.in|http://ads\.lipi\.go\.id|http://cdsads\.u-strasbg\.fr|http://esoads\.eso\.org|http://ukads\.nottingham\.ac\.uk|http://www\.ads\.lipi\.go\.id)/.*(?:abs/|bibcode=|query\?|full/)([12]\d{3}[\w\d\.&]{15})~";
const IN_PRESS_ALIASES = array("in press","inpress", "pending","published","published online", "no-no", "n/a", "online ahead of print", "unpublished","unknown","tba","forthcoming","in the press","na","submitted","tbd","missing");
const SICI_REGEXP = "~(\d{4}-\d{4})\((\d{4})(\d\d)?(\d\d)?\)(\d+):?([+\d]*)[<\[](\d+)::?\w+[>\]]2\.0\.CO;2~";

//Common replacements
const HTML_DECODE = array("[", "]", "<", ">", " ");
const HTML_ENCODE = array("&#x5B;", "&#x5D;", "&#60;", "&#62;", "+");

const DOT_ENCODE = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
const DOT_DECODE = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

// Some data we get from outside sources is bad or at least mis-defined
// Use lower case for all of these, and then compare to a lower cased version
const BAD_AUTHORS = array("unknown","missing");
const AUTHORS_ARE_PUBLISHERS = array("hearst magazines", "time inc");
const HAS_NO_VOLUME = array("zookeys");  // Some journals have issues only, no volume numbers
const BAD_TITLES = array("unknown","missing");

// dontCap is am array of strings that should not be capitalized in their titlecase format; 
// unCapped is their correct capitalization. Remember to enclose any word in spaces.

const LC_SMALL_WORDS = array(' and then ', ' of ',' the ',' and ',' an ',' or ',' nor ',' but ',' is ',' if ',' then ',' else ',' when', 'at ',' from ',' by ',' on ',' off ',' for ',' in ',' over ',' to ',' into ',' with ',' U S A ',' USA ',' et ', ' für ');
const UC_SMALL_WORDS = array(' and Then ', ' Of ',' The ',' And ',' An ',' Or ',' Nor ',' But ',' Is ',' If ',' Then ',' Else ',' When', 'At ',' From ',' By ',' On ',' Off ',' For ',' In ',' Over ',' To ',' Into ',' With ',' U S A ',' Usa ',' Et ', ' Für ');

const JOURNAL_ACRONYMS = array(
' ACM SIGPLAN Notices ', ' ASME AES ', ' ASME MTD ', ' BMJ ', ' CBD Ubiquitin ', ' CFSK-DT ', ' e-Neuroforum ', 
' Early Modern Japan: an Interdisciplinary Journal ', ' eLife ', ' EMBO J ', ' EMBO J. ', ' EMBO Journal ',
' EMBO Rep ', ' EMBO Rep. ', ' EMBO Reports ', ' FASEB J ', ' FASEB J. ', ' FEBS J ', ' FEBS J. ', ' FEBS Journal ',
' HOAJ biology ', ' ISRN Genetics ', ' iConference ', ' JABS : Journal of Applied Biological Sciences ',
' Molecular and Cellular Biology ', ' Ocean Science Journal : OSJ ', ' PALAIOS ', ' PLOS ONE ', ' PNAS ',
' S.A.P.I.EN.S ', ' Star Trek: The Official Monthly Magazine ', ' The EMBO Journal ', ' Time Out London ',
' z/Journal ', ' Zeitschrift für Physik A Hadrons and Nuclei ', ' Zeitschrift für Physik A: Hadrons and Nuclei ',
' ZooKeys ', ' Journal of the IEST ', ' Tellus A ', ' Zeitschrift für Geologische Wissenschaften ', ' Journal of Materials Chemistry A ',
' BioEssays ', ' PLOS Biology ', ' RNA ',
' NASA Tech Briefs ', ' PLOS Medicine ', ' PLOS Neglected Tropical Diseases ', ' JAMA Psychiatry ');
const UCFIRST_JOURNAL_ACRONYMS = array(
' Acm Sigplan Notices ', ' Asme Aes ', ' Asme Mtd ', ' Bmj ', ' Cbd Ubiquitin ', ' Cfsk-Dt ', ' E-Neuroforum ', 
' Early Modern Japan: An Interdisciplinary Journal ', ' Elife ', ' Embo J ', ' Embo J. ', ' Embo Journal ', 
' Embo Rep ', ' Embo Rep. ', ' Embo Reports ', ' Faseb J ', ' Faseb J. ', ' Febs J ', ' Febs J. ', ' Febs Journal ',
' Hoaj Biology ', ' Isrn Genetics ', ' Iconference ', ' Jabs : Journal Of Applied Biological Sciences ',
' Molecular And Cellular Biology ', ' Ocean Science Journal : Osj ', ' Palaios ', ' Plos One ', ' Pnas ',
' S.a.p.i.en.s ', ' Star Trek: The Official Monthly Magazine ', ' The Embo Journal ', ' Time Out London ',
' Z/journal ', ' Zeitschrift Für Physik A Hadrons And Nuclei ', ' Zeitschrift Für Physik A: Hadrons And Nuclei ',
' Zookeys ', ' Journal Of The Iest ', ' Tellus A ', ' Zeitschrift Für Geologische Wissenschaften ', ' Journal Of Materials Chemistry A ',
' Bioessays ', ' Plos Biology ', ' Rna ',
' Nasa Tech Briefs ', ' Plos Medicine ', ' Plos Neglected Tropical Diseases ', ' Jama Psychiatry ');
 
 ?>
