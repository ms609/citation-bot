<?php
// Some data we get from outside sources is bad or at least mis-defined
// Use lower case for all of these, and then compare to a lower cased version
const HAS_NO_VOLUME = array("zookeys");  // Some journals have issues only, no volume numbers
const BAD_ACCEPTED_MANUSCRIPT_TITLES = array("oup accepted manuscript", "placeholder for bad pdf file", 
                                             "placeholder", "symbolic placeholder", "[placeholder]", 
                                             "placeholder for arabic language transliteration");
const BAD_AUTHORS = array("unknown", "missing");

// Catch 'authors' such as "hearst magazines", "time inc", "nielsen business media, inc"
// Ordered alphabetically.
const PUBLISHER_ENDINGS = ["books", "corporation", 'centre', 'center', 'company', "inc.", "inc", "magazines",
                           'museum', "press", "publishers", "publishing", 'science'];
const BAD_TITLES = array("unknown", "missing", "arxiv e-prints", "Arxiv Mathematics E-Prints");
const IN_PRESS_ALIASES = array("in press", "inpress", "pending", "published", 
                               "published online", "no-no", "n/a", "online ahead of print", 
                               "unpublished", "unknown", "tba", "forthcoming", "in the press", 
                               "na", "submitted", "tbd", "missing");
const NON_JOURNAL_BIBCODES = array('arXiv', 'gr.qc', 'hep.ex', 'hep.lat', 'hep.ph', 'hep.th', 
                                   'math.ph', 'math', 'nucl.ex', 'nucl.th', 'physics');
const NON_PUBLISHERS = ['books.google', 'google books', 'google news', 'google.co', 'amazon.com', 'archive.org']; // Google Inc is a valid publisher, however.
const BAD_ZOTERO_TITLES = ['Browse publications', 'Central Authentication Service', 'http://', 'https://',
                                 'ZbMATH - the first resource for mathematics', 'MR: Matches for:',
                                 ' Log In', 'Log In ', 'Bookmarkable URL intermediate page', 'Shibboleth Authentication Request',
                                 'domain for sale', 'website for sale', 'domain is for sale', 'website is for sale',
                                 'lease this domain', 'domain available', 'metaTags', 'An Error Occurred', 'User Cookie',
                                 'Cookies Disabled', 'page not found', '411 error', 'url not found',
                                 'limit exceeded', 'Error Page', '}}', '{{', 'EU Login', 'bad gateway', '.com', '.gov', '.org'];

const CANONICAL_PUBLISHER_URLS = array ('elsevier.com', 'springer.com', 'sciencedirect.com', 'tandfonline.com',
                                'taylorandfrancis.com', 'wiley.com', 'sagepub.com', 'sagepublications.com',
                                'scielo.org', 'scielo.br', 'degruyter.com', 'hindawi.com', 'inderscience.com',
                                'cambridge.org', '.oup.com', 'nature.com', 'macmillan.com', 'ieeexplore.ieee.org',
                                'worldscientific.com', 'iospress.com', 'iospress.nl', 'pnas.org');

