<?php 
const REGEXP_PLAIN_WIKILINK = '~\[\[([^|\[\]]+?)\]\]~';
// Matches: [1], target; [2], display text
const REGEXP_PIPED_WIKILINK = '~\[\[([^|\[\]]+?)\|([^|\[\]]+?)\]\]~';
const REGEXP_TO_EN_DASH = "--?|\&mdash;|\xe2\x80\x94|\?\?\?"; // regexp for replacing to ndashes using mb_ereg_replace
const REGEXP_EN_DASH = "\xe2\x80\x93"; // regexp for replacing to ndashes using mb_ereg_replace

const REGEXP_BIBCODE = "~^(?:https?://(?:\w+.)?adsabs.harvard.edu|https?://ads\.ari\.uni-heidelberg\.de|https?://ads\.inasan\.ru|https?://ads\.mao\.kiev\.ua|https?://ads\.astro\.puc\.cl|https?://ads\.on\.br|https?://ads\.nao\.ac\.jp|https?://ads\.bao\.ac\.cn|https?://ads\.iucaa\.ernet\.in|https?://ads\.lipi\.go\.id|https?://cdsads\.u-strasbg\.fr|https?://esoads\.eso\.org|https?://ukads\.nottingham\.ac\.uk|https?://www\.ads\.lipi\.go\.id)/.*(?:abs/|bibcode=|query\?|full/)([12]\d{3}[\w\d\.&]{15})~";
const REGEXP_DOI = "~10\.\d{4,6}/\S+~";
const REGEXP_SICI = "~(\d{4}-\d{3}[\dxX])" . // ISSN
                    "\((\d{4})(\d{2})?/?(\d{2})?\)" . // Chronology, YY MM DD
                    "(\d+):?([\+\d]*)" . // Enumeration: Volume / issue
                    "[<\[]" . "(\d+)::?\w+" . "[>\]]" . "2\.0\.CO;2\-?[A-z0-9]?~";

// See https://mathiasbynens.be/demo/url-regex/  This regex is more exact than validator.  We only spend time on this after quick and dirty check is passed
const REGEXP_IS_URL = '~^(?:(?:https?|ftp)://)(?:\\S+(?::\\S*)?@)?(?:(?!10(?:\\.\\d{1,3}){3})(?!127(?:\\.\\d{1,3}){3})(?!169\\.254(?:\\.\\d{1,3}){2})(?!192\\.168(?:\\.\\d{1,3}){2})(?!172\\.(?:1[6-9]|2\\d|3[0-1])(?:\\.\\d{1,3}){2})(?:[1-9]\\d?|1\\d\\d|2[01]\\d|22[0-3])(?:\\.(?:1?\\d{1,2}|2[0-4]\\d|25[0-5])){2}(?:\\.(?:[1-9]\\d?|1\\d\\d|2[0-4]\\d|25[0-4]))|(?:(?:[a-z\\x{00a1}-\\x{ffff}0-9]+-?)*[a-z\\x{00a1}-\\x{ffff}0-9]+)(?:\\.(?:[a-z\\x{00a1}-\\x{ffff}0-9]+-?)*[a-z\\x{00a1}-\\x{ffff}0-9]+)*(?:\\.(?:[a-z\\x{00a1}-\\x{ffff}]{2,})))(?::\\d{2,5})?(?:/[^\\s]*)?$~iuS';
// This list is going to grow and grow based upon user requests
// ALWAYS verify that they registered the examples you are given
// http://oasis.postech.ac.kr/handle/2014.oak/9965 is not registered for example
const REGEXP_HANDLES = "~^(?:https?://)(?:hdl\.handle\.net/|kb\.osu\.edu/dspace/handle/|oasis\.postech\.ac\.kr/handle/|idus\.us\.es/xmlui/handle/|dspace\.library\.uu\.nl/handle/)([^\?]*)~i";
