<?php 
declare(strict_types=1);
const REGEXP_PLAIN_WIKILINK = '~\[\[([^|\[\]]+?)\]\]~';
const REGEXP_PLAIN_WIKILINK_ONLY = '~^\[\[([^|\[\]]+?)\]\]$~';
// Matches: [1], target; [2], display text
const REGEXP_PIPED_WIKILINK = '~\[\[([^|\[\]]+?)\|([^|\[\]]+?)\]\]~';
const REGEXP_PIPED_WIKILINK_ONLY = '~^\[\[([^|\[\]]+?)\|([^|\[\]]+?)\]\]$~';
const REGEXP_TO_EN_DASH = "--?|\&mdash;|\xe2\x80\x94|\?\?\?"; // regexp for replacing to ndashes using mb_ereg_replace
const REGEXP_EN_DASH = "\xe2\x80\x93"; // regexp for replacing to ndashes using mb_ereg_replace

const REGEXP_BIBCODE = "~^https?://(?:(?:\w+.)?adsabs\.harvard\.edu|ads\.ari\.uni-heidelberg\.de|ads\.inasan\.ru|ads\.mao\.kiev\.ua|ads\.astro\.puc\.cl|ads\.on\.br|ads\.nao\.ac\.jp|ads\.bao\.ac\.cn|ads\.iucaa\.ernet\.in|ads\.lipi\.go\.id|cdsads\.u-strasbg\.fr|esoads\.eso\.org|ukads\.nottingham\.ac\.uk|www\.ads\.lipi\.go\.id)/.*(?:abs/|bibcode=|query\?|full/)([12]\d{3}[\w\d\.&]{15})~";
const REGEXP_DOI = "~10\.\d[\d\.]+\d/\S+~";
const REGEXP_SICI = "~(\d{4}-\d{3}[\dxX])" . // ISSN
                    "\((\d{4})(\d{2})?/?(\d{2})?\)" . // Chronology, YY MM DD
                    "(\d+):?([\+\d]*)" . // Enumeration: Volume / issue
                    "[<\[]" . "(\d+)::?\w+" . "[>\]]" . "2\.0\.CO;2\-?[A-z0-9]?~";
const REGEXP_DOI_ISSN_ONLY = '~^10.[^/]+/\(ISSN\)\d{4}-\d{3}[xX0-9]$~';

// See https://mathiasbynens.be/demo/url-regex/  This regex is more exact than validator.  We only spend time on this after quick and dirty check is passed
const REGEXP_IS_URL = '~^(?:(?:https?|ftp)://)(?:\\S+(?::\\S*)?@)?(?:(?!10(?:\\.\\d{1,3}){3})(?!127(?:\\.\\d{1,3}){3})(?!169\\.254(?:\\.\\d{1,3}){2})(?!192\\.168(?:\\.\\d{1,3}){2})(?!172\\.(?:1[6-9]|2\\d|3[0-1])(?:\\.\\d{1,3}){2})(?:[1-9]\\d?|1\\d\\d|2[01]\\d|22[0-3])(?:\\.(?:1?\\d{1,2}|2[0-4]\\d|25[0-5])){2}(?:\\.(?:[1-9]\\d?|1\\d\\d|2[0-4]\\d|25[0-4]))|(?:(?:[a-z\\x{00a1}-\\x{ffff}0-9]+-?)*[a-z\\x{00a1}-\\x{ffff}0-9]+)(?:\\.(?:[a-z\\x{00a1}-\\x{ffff}0-9]+-?)*[a-z\\x{00a1}-\\x{ffff}0-9]+)*(?:\\.(?:[a-z\\x{00a1}-\\x{ffff}]{2,})))(?::\\d{2,5})?(?:/[^\\s]*)?$~iu';

// This list is going to grow and grow based upon user requests
// ALWAYS verify that they registered the examples you are given
// http://oasis.postech.ac.kr/handle/2014.oak/9965 is not registered for example
// But, the code verifies each and every hdl, before the conversion is done, so even bad hosts should be fine

// The code changes the dots to \. in this list
const HANDLES_HOSTS = array('hdl.handle.net', 'kb.osu.edu', 'repositorio.aemet.es', 'fieldresearch.msf.org',
                            'dspace.lib.cranfield.ac.uk', 'dspace.lboro.ac.uk', 'scholarship.rice.edu',
                            'deepblue.lib.umich.edu', 'arrow.latrobe.edu.au', 'kb.osu.edu', 'scholarworks.csun.edu',
                            'www.era.lib.ed.ac.uk', 'research-repository.st-andrews.ac.uk',
                            'dataverse.scholarsportal.info', 'idus.us.es', 'dspace.library.uu.nl',
                            'dspace.mit.edu', 'digital.csic.es', 'apps.who.int', 'repository.si.edu',
                            'lume.ufrgs.br', 'digitallibrary.amnh.org', 'hdl.cqu.edu.au', 'repository.bilkent.edu.tr',
                            'digital.library.wisc.edu', 'www.territorystories.nt.gov.au', 'repository.up.ac.za',
                            'researchonline.federation.edu.au', 'scholarspace.manoa.hawaii.edu', 'orbi.uliege.be',
                            'repositorio.uasb.edu.ec', 'shodhganga.inflibnet.ac.in', 'www.tara.tcd.ie');
// Order here could matter
// YOU MUST escape the RegEx
const HANDLES_PATHS = array('/dspace/bitstream/handle/', '/msf/handle/', '/vital/access/HandleResolver/',
                            '/handle/', '/dspace-jspui/handle/', '/dataset.xhtml\?persistentId=hdl:',
                            '/dspace/handle/', '/handle2/', '/xmlui/handle/', '/iris/handle/',
                            '/repository/bitstream/handle/', '/repository/handle/', 
                            '/tools/dspace/load/\?file=/repository/bitstream/handle/',
                            '/xmlui/bitstream/handle/', '/jspui/handle/', '/jspui/bitstream/',
                            '/vital/access/HandleResolver/');
