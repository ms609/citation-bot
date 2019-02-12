<?php
// Some data we get from outside sources is bad or at least mis-defined
// Use lower case for all of these, and then compare to a lower cased version
const HAS_NO_VOLUME = array("zookeys");  // Some journals have issues only, no volume numbers
const BAD_ACCEPTED_MANUSCRIPT_TITLES = array("oup accepted manuscript", "placeholder for bad pdf file", 
                                             "placeholder", "symbolic placeholder", "[placeholder]", 
                                             "placeholder for arabic language transliteration");
const BAD_AUTHORS = array("unknown", "missing", "- -.", "- -", "no authorship indicated", "no authorship", "no author",
                           "no authors");
const NON_HUMAN_AUTHORS = array('collaborat', 'reporter', 'journalist', 'correspondent', 'anchor');

// Catch 'authors' such as "hearst magazines", "time inc", "nielsen business media, inc"
// Ordered alphabetically.
const PUBLISHER_ENDINGS = ["books", "corporation", 'centre', 'center', 'company', "inc.", "inc", "magazines",
                           'museum', "press", "publishers", "publishing", 'science'];
const BAD_TITLES = array("unknown", "missing", "arxiv e-prints", "arxiv mathematics e-prints", 
                         "ssrn electronic journal", "dissertations available from proquest");
const IN_PRESS_ALIASES = array("in press", "inpress", "pending", "published", 
                               "published online", "no-no", "n/a", "online ahead of print", 
                               "unpublished", "unknown", "tba", "forthcoming", "in the press", 
                               "na", "submitted", "tbd", "missing");
const NON_JOURNAL_BIBCODES = array('arXiv', 'gr.qc', 'hep.ex', 'hep.lat', 'hep.ph', 'hep.th', 'astro.ph',
                                   'math', 'nucl.ex', 'nucl.th', 'physics', 'quant.ph', 'alg.geom',
                                   'cond.mat', 'cs.', 'econ.', 'eess.', 'nlin.');
const NON_PUBLISHERS = ['books.google', 'google books', 'google news', 'google.co', 'amazon.com',
                        'zenodo', 'archive.org']; // Google Inc is a valid publisher, however.
const BAD_ZOTERO_TITLES = ['Browse publications', 'Central Authentication Service', 'http://', 'https://',
                                 'ZbMATH - the first resource for mathematics', 'MR: Matches for:',
                                 ' Log In', 'Log In ', 'Bookmarkable URL intermediate page', 'Shibboleth Authentication Request',
                                 'domain for sale', 'website for sale', 'domain is for sale', 'website is for sale',
                                 'lease this domain', 'domain available', 'metaTags', 'An Error Occurred', 'User Cookie',
                                 'Cookies Disabled', 'page not found', '411 error', 'url not found',
                                 'limit exceeded', 'Error Page', '}}', '{{', 'EU Login', 'bad gateway', 'Captcha',
                                 '.com', '.gov', '.org', 'View PDF', 'Wayback Machine', 'does not exist'];

const CANONICAL_PUBLISHER_URLS = array ('elsevier.com', 'springer.com', 'sciencedirect.com', 'tandfonline.com',
                                'taylorandfrancis.com', 'wiley.com', 'sagepub.com', 'sagepublications.com',
                                'scielo.org', 'scielo.br', 'degruyter.com', 'hindawi.com', 'inderscience.com',
                                'cambridge.org', '.oup.com', 'nature.com', 'macmillan.com', 'ieeexplore.ieee.org',
                                'worldscientific.com', 'iospress.com', 'iospress.nl', 'pnas.org', 'journals.ametsoc.org',
                                'pubs.geoscienceworld.org',
                                //  Below are sites that are simply DOI resolvers, like dx.doi.org
                                'doi.library.ubc.ca');

const WEB_NEWSPAPERS = array('bbc news', 'bbc', 'news.bbc.co.uk', 'bbc sports', 'bbc sport');

const NO_DATE_WEBSITES = array('wikipedia.org', 'web.archive.org', 'perma-archives.org', 'webarchive.proni.gov.uk', 'perma.cc',
                              'wayback', 'web.archive.bibalex.org', 'web.petabox.bibalex.org', 'webharvest.gov', 'archive.wikiwix.com',
                              'archive.is', 'archive-it.org', 'nationalarchives.gov.uk', 'freezepage.com', 'www.webcitation.org',
                              'waybackmachine.org');

const ZOTERO_AVOID_REGEX = array("twitter\.",               // This should be {{cite tweet}}
                                 "youtube\.", "youtu\.be",  // This should be {{cite AV media}}
                                 "books\.google\.",         // We have special google books code
                                 "google\.com/search",      // Google search results
                                 "jstor\.org/stable/",      // We have special jstor code
                                 "ned\.ipac\.caltech\.edu");// Gives no real title
const NON_JOURNAL_WEBSITES = array('cnn.com/', 'foxnews.com/', 'msnbc.com/', 'nbcnews.com/', 'abcnews.com/', 'cbs.com/', 
                                   'cbsnews.com/', 'abc.com/', 'bbc.com/', 'bbc.co.uk/', 'apnews.com/',
                                   '.ap.org/', 'nytimes.com/', 'theguardian.com/', 'washingtonpost.com/',
                                   'newyorker.com/', 'independent.co.uk/', 'cnbc.com/', 'vanityfair.com/',
                                   'theatlantic.com/', '-news.co.uk/', 'news.google.com/', 'jpl.nasa.gov/',
                                   'gsfc.nasa.gov/', 'solarsystem.nasa.gov/'); 
                                   // Just a list of ones that are obvious.  Add ones that time-out as we find them
