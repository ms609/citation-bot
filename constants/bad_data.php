<?php
// Some data we get from outside sources is bad or at least mis-defined
// Use lower case for all of these, and then compare to a lower cased version
const HAS_NO_VOLUME = array("zookeys");  // Some journals have issues only, no volume numbers
const BAD_ACCEPTED_MANUSCRIPT_TITLES = array("oup accepted manuscript", "placeholder for bad pdf file", 
                                             "placeholder", "symbolic placeholder", "[placeholder]", 
                                             "placeholder for arabic language transliteration");
const BAD_AUTHORS = array("unknown", "missing");

// Catch 'authors' such as "hearst magazines", "time inc", "nielsen business media, inc"
const PUBLISHER_ENDINGS = array("inc.", "inc", "magazines", "press", "publishing", "publishers", 
                                "books", "corporation");
const BAD_TITLES = array("unknown", "missing", "arxiv e-prints");
const IN_PRESS_ALIASES = array("in press", "inpress", "pending", "published", 
                               "published online", "no-no", "n/a", "online ahead of print", 
                               "unpublished", "unknown", "tba", "forthcoming", "in the press", 
                               "na", "submitted", "tbd", "missing");
const NON_JOURNAL_BIBCODES = array('arXiv', 'gr.qc', 'hep.ex', 'hep.lat', 'hep.ph', 'hep.th', 
                                   'math.ph', 'math', 'nucl.ex', 'nucl.th', 'physics');
const NON_PUBLISHERS = array('books.google', 'google books', 'google news', 'google.co', 'amazon.com', 'archive.org'); // Google Inc is a valid publisher, however.
const BAD_ZOTERO_TITLES = array ('on jstor', 'Browse publications', 'Central Authentication Service ',
                                 ' Log In', 'Log In ', 'Bookmarkable URL intermediate page', 'Shibboleth Authentication Request');
