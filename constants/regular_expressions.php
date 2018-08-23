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


