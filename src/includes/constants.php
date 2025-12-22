<?php

declare(strict_types=1);

// @codeCoverageIgnoreStart
// all new constant files needed listed here
require_once __DIR__ . '/constants/bad_data.php';
require_once __DIR__ . '/constants/capitalization.php';
require_once __DIR__ . '/constants/math.php';
require_once __DIR__ . '/constants/mistakes.php';
require_once __DIR__ . '/constants/parameters.php';
require_once __DIR__ . '/constants/regular_expressions.php';
require_once __DIR__ . '/constants/italics.php';
require_once __DIR__ . '/constants/isbn.php';
require_once __DIR__ . '/constants/null_good_doi.php';
require_once __DIR__ . '/constants/null_bad_doi.php';
require_once __DIR__ . '/constants/translations.php';
// @codeCoverageIgnoreEnd

const PIPE_PLACEHOLDER = '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #';
const TEMP_PLACEHOLDER = "# # # CITATION_BOT_PLACEHOLDER_TEMPORARY %s # # #";

//Common replacements
const HTML_DECODE = ["[", "]", "<", ">", " "];
const HTML_ENCODE = ["&#x5B;", "&#x5D;", "&#60;", "&#62;", "+"];

const HTML_DECODE_DOI = ["[", "]", "<", ">"];
const HTML_ENCODE_DOI = ["&#x5B;", "&#x5D;", "&#60;", "&#62;"];

const DOT_ENCODE = [".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29"];
const DOT_DECODE = ["/", "[", "{", "}", "]", "<", ">", ";", "(", ")"];

const ENGLISH_WIKI = ['en', 'simple', 'mdwiki'];

enum DateStyle {
    case DATES_WHATEVER;
    case DATES_MDY;
    case DATES_DMY;
    case DATES_ISO;
}

enum VancStyle
{
    case NAME_LIST_STYLE_DEFAULT;
    case NAME_LIST_STYLE_AMP;
    case NAME_LIST_STYLE_VANC;
}

const COMMONUSERNAME   = 'citations@tools.wmflabs.org';
const CROSSREFUSERNAME = 'martins@gmail.com';
const PUBMEDUSERNAME   = 'martins+pubmed@gmail.com';

const BOT_CROSSREF_USER_AGENT = "Mozilla/5.0 (compatible; Citation_bot; mailto:" . CROSSREFUSERNAME . "; +https://citations.toolforge.org/)";
const BOT_USER_AGENT = "Mozilla/5.0 (compatible; Citation_bot; mailto:" . COMMONUSERNAME . "; +https://citations.toolforge.org/)";

const BOT_HTTP_TIMEOUT = 20;
const BOT_CONNECTION_TIMEOUT = 10;

// Template.php constants
const REJECT_NEW = ['null', 'n/a', 'undefined', '0 0', '(:none)', '-'];
const GOOFY_TITLES = ['Archived copy', "{title}", 'ScienceDirect', 'Google Books', 'None', 'usurped title'];
const BAD_NEW_PAGES = ['0', '0-0', '0–0'];
const BAD_ISBN = ['9780918678072', '978-0-918678-07-2', '0918678072', '0-918678-07-2'];
const SHORT_STRING = ['the', 'and', 'a', 'for', 'in', 'on', 's', 're', 't', 'an', 'as', 'at', 'but', 'how', 'why', 'by', 'when', 'with', 'who', 'where', ''];
const RIS_IS_BOOK = ['CHAP', 'BOOK', 'EBOOK', 'ECHAP', 'EDBOOK', 'DICT', 'ENCYC', 'GOVDOC', 'RPRT'];
const RIS_IS_FULL_BOOK = ['BOOK', 'EBOOK', 'EDBOOK'];
const GOOD_FREE = ['publisher', 'projectmuse', 'have free'];
const BAD_OA_URL = ['10.4135/9781529742343', '10.1017/9781108859745'];
const REMOVE_SEMI = ['date', 'year', 'location', 'publisher', 'issue', 'number', 'page', 'pages', 'pp', 'p', 'volume'];
const REMOVE_PERIOD = ['date', 'year', 'issue', 'number', 'page', 'pages', 'pp', 'p', 'volume'];
const LINK_LIST = ['authorlink', 'chapterlink', 'contributorlink', 'editorlink', 'episodelink', 'interviewerlink', 'inventorlink', 'serieslink', 'subjectlink', 'titlelink', 'translatorlink'];
const BAD_AGENT = ['United States Food and Drug Administration', 'Surgeon General of the United States', 'California Department of Public Health'];
const BAD_AGENT_PUBS = ['United States Department of Health and Human Services', 'California Tobacco Control Program', ''];
const NO_LANGS = ['n', 'no', 'live', 'alive', 'কার্যকর', 'hayır', 'não', 'nao', 'false'];
const YES_LANGS = ['y', 'yes', 'dead', 'si', 'sì', 'ja', 'evet', 'ei tööta', 'sim', 'ano', 'true'];
const PDF_LINKS = ['pdf', 'portable document format', '[[portable document format|pdf]]', '[[portable document format]]', '[[pdf]]'];
const DEPARTMENTS = ['local', 'editorial', 'international', 'national', 'communication', 'letter to the editor',
        'review', 'coronavirus', 'race & reckoning', 'politics', 'opinion', 'opinions', 'investigations', 'tech',
        'technology', 'world', 'sports', 'arts & entertainment', 'arts', 'entertainment', 'u.s.', 'n.y.',
        'business', 'science', 'health', 'books', 'style', 'food', 'travel', 'real estate', 'magazine', 'economy',
        'markets', 'life & arts', 'uk news', 'world news', 'health news', 'lifestyle', 'photos', 'education',
        'life', 'puzzles'];
const BAD_VIA = [ '', 'project muse', 'wiley', 'springer', 'questia', 'elsevier', 'wiley online library',
        'wiley interscience', 'interscience', 'sciencedirect', 'science direct', 'ebscohost', 'proquest',
        'google scholar', 'google', 'bing', 'yahoo'];
const VOL_NUM = ['volume', 'issue', 'number'];
