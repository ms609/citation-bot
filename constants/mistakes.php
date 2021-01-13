<?php
declare(strict_types=1);

const COMMON_MISTAKES = array ( // Common mistakes that aren't picked up by the levenshtein approach
  "ASIN"            =>  "asin",
  "EISSN"           =>  "eissn",
  "HDL"             =>  "hdl",
  "ISBN13"          =>  "isbn",
  "isbn13"          =>  "isbn",
  "ISMN"            =>  "ismn",
  "ISSN"            =>  "issn",
  "JFM"             =>  "jfm",
  "JSTOR"           =>  "jstor",
  "LCCN"            =>  "lccn",
  "MR"              =>  "mr",
  "OCLC"            =>  "oclc",
  "OL"              =>  "ol",
  "OSTI"            =>  "osti",
  "PMC"             =>  "pmc",
  "PMID"            =>  "pmid",
  "RFC"             =>  "rfc",
  "S2CID"           =>  "s2cid",
  "SBN"             =>  "sbn",
  "SSRN"            =>  "ssrn",
  "ZBL"             =>  "zbl",
  "albumlink"       =>  "titlelink",
  "artist"          =>  "others",
  "authorurl"       =>  "authorlink",
  "bioRxiv"         =>  "biorxiv",
  "co-author"       =>  "coauthor",
  "co-authors"      =>  "coauthors",
  "dio"             =>  "doi",
  "director"        =>  "others",
  "display_authors" =>  "display-authors",
  "displayeditors"  =>  "display-editors",
  "doi_brokendate"  =>  "doi-broken-date",
  "doi_inactivedate"=>  "doi-broken-date",
  "doi-inactive-date" =>  "doi-broken-date",
  "doi-broken"      =>  "doi-broken-date",
  "ed"              =>  "editor",
  "ed2"             =>  "editor2",
  "ed3"             =>  "editor3",
  "ed4"             =>  "editor4",
  "ed5"             =>  "editor5",
  "ed6"             =>  "editor6",
  "editorlink1"     =>  "editor1-link",
  "editorlink2"     =>  "editor2-link",
  "editorlink3"     =>  "editor3-link",
  "editorlink4"     =>  "editor4-link",
  "editorlink5"     =>  "editor5-link",
  "editorlink6"     =>  "editor6-link",
  "editorlink7"     =>  "editor7-link",
  "editorlink8"     =>  "editor8-link",
  "editor1link"     =>  "editor1-link",
  "editor2link"     =>  "editor2-link",
  "editor3link"     =>  "editor3-link",
  "editor4link"     =>  "editor4-link",
  "editor5link"     =>  "editor5-link",
  "editor6link"     =>  "editor6-link",
  "editor7link"     =>  "editor7-link",
  "editor8link"     =>  "editor8-link",
  "editor-first1"   =>  "editor1-first",
  "editor-first2"   =>  "editor2-first",
  "editor-first3"   =>  "editor3-first",
  "editor-first4"   =>  "editor4-first",
  "editor-first5"   =>  "editor5-first",
  "editor-first6"   =>  "editor6-first",
  "editor-first7"   =>  "editor7-first",
  "editor-first8"   =>  "editor8-first",
  "editor-last1"    =>  "editor1-last",
  "editor-last2"    =>  "editor2-last",
  "editor-last3"    =>  "editor3-last",
  "editor-last4"    =>  "editor4-last",
  "editor-last5"    =>  "editor5-last",
  "editor-last6"    =>  "editor6-last",
  "editor-last7"    =>  "editor7-last",
  "editor-last8"    =>  "editor8-last",
  "editorn"         =>  "editor2",
  "editorn-link"    =>  "editor2-link",
  "editorn-last"    =>  "editor2-last",
  "editorn-first"   =>  "editor2-first",
  "interviewerlink" =>  "interviewer-link",
  "interviewermask" =>  "interviewer-mask",
  "firstn"          =>  "first2",
  "no-cat"          =>  "nocat",
  "notracking"      =>  "no-tracking",
  "ISBN"            =>  "isbn",
  "ibsn"            =>  "isbn",
  "ibsn2"           =>  "isbn",
  "lastn"           =>  "last2",
  "local"           =>  "location",
  "part"            =>  "issue",
  "no"              =>  "issue",
  "No"              =>  "issue",
  "No."             =>  "issue",
  "notestitle"      =>  "chapter",
  "nurl"            =>  "url",
  "origmonth"       =>  "month",
  "p"               =>  "page",
  "p."              =>  "page",
  "pmpmid"          =>  "pmid",
  "pp"              =>  "pages",
  "pp."             =>  "pages",
  "publisherid"     =>  "id",
  "titleyear"       =>  "origyear",
  "translators"     =>  "translator",
  "URL"             =>  "url",
  "vol"             =>  "volume",
  "Vol"             =>  "volume",
  "Vol."            =>  "volume",
  "ARXIV"           =>  "arxiv",
  "cointerviewers"  =>  "others",
  "trans_chapter"   =>  "trans-chapter",
  "trans_title"     =>  "trans-title",
  "DOI"             =>  "doi",
  "publication"     =>  "work", 
  "publicationplace"     =>  "publication-place", 
  "publicationdate"      =>  "publication-date", 
  "chapterurl"      =>  "chapter-url",
);
