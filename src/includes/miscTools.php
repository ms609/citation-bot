<?php

declare(strict_types=1);

const GROUP_F1  = ['first', 'initials', 'forename', 'contributor-first', 'contributor-given'];
const GROUP_L1  = ['last', 'surname', 'author', 'contributor-last', 'contributor-surname', 'contributor'];
const GROUP1  = ['author', 'authors', 'last', 'first', 'vauthors', 'surname'];
const GROUP2  = ['others', 'display-editors', 'displayeditors', 'display-authors', 'displayauthors', 'author-link', 'veditors'];
const GROUP3  = ['title', 'title-link', 'titlelink'];
const GROUP4  = ['chapter'];
const GROUP5  = ['journal', 'work', 'newspaper', 'website', 'magazine', 'periodical', 'encyclopedia', 'encyclopaedia', 'book-title'];
const GROUP6  = ['series'];
const GROUP7  = ['year', 'date'];
const GROUP8  = ['volume'];
const GROUP9  = ['issue', 'number'];
const GROUP10 = ['page', 'pages', 'at'];
const GROUP11 = ['article-number'];
const GROUP12 = ['location', 'publisher', 'edition', 'agency'];
const GROUP13 = ['doi'];
const GROUP14 = ['doi-broken-date'];
const GROUP15 = ['doi-access'];
const GROUP16 = ['doi-broken-date'];
const GROUP17 = ['jstor'];
const GROUP18 = ['pmid'];
const GROUP19 = ['pmc'];
const GROUP20 = ['pmc-embargo-date'];
const GROUP21 = ['arxiv', 'eprint', 'class'];
const GROUP22 = ['bibcode'];
const GROUP23 = ['hdl'];
const GROUP24 = ['isbn', 'biorxiv', 'citeseerx', 'jfm', 'zbl', 'mr', 'osti', 'ssrn', 'rfc'];
const GROUP25 = ['lccn', 'issn', 'ol', 'oclc', 'asin', 's2cid'];
const GROUP26 = ['url'];
const GROUP27 = ['chapter-url', 'article-url', 'chapterurl', 'conference-url', 'conferenceurl', 'contribution-url', 'contributionurl', 'entry-url', 'event-url', 'eventurl', 'lay-url', 'layurl', 'map-url', 'mapurl', 'section-url', 'sectionurl', 'transcript-url', 'transcripturl', 'URL'];
const GROUP28 = ['archive-url', 'archiveurl', 'accessdate', 'access-date'];
const GROUP29 = ['archive-date', 'archivedate'];
const GROUP30 = ['id', 'type', 'via'];

function clean_up_oxford_stuff(Template $template, string $param): void {
    if (preg_match('~^https?://(latinamericanhistory|classics|psychology|americanhistory|africanhistory|internationalstudies|climatescience|religion|environmentalscience|politics)\.oxfordre\.com(/.+)$~', $template->get($param), $matches)) {
        $template->set($param, 'https://oxfordre.com/' . $matches[1] . $matches[2]);
    }

    if (preg_match('~^(https?://(?:[\.+]|)oxfordre\.com)/([^/]+)/([^/]+)/([^/]+)/(.+)$~', $template->get($param), $matches)) {
        if ($matches[2] === $matches[3] && $matches[2] === $matches[4]) {
            $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[5]);
        } elseif ($matches[2] === $matches[3]) {
            $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[4] . '/' . $matches[5]);
        }
    }
    if (preg_match('~^(https?://(?:[\.+]|)oxfordmusiconline\.com)/([^/]+)/([^/]+)/([^/]+)/(.+)$~', $template->get($param), $matches)) {
        if ($matches[2] === $matches[3] && $matches[2] === $matches[4]) {
            $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[5]);
        } elseif ($matches[2] === $matches[3]) {
            $template->set($param, $matches[1] . '/' . $matches[2] . '/' . $matches[4] . '/' . $matches[5]);
        }
    }

    while (preg_match('~^(https?://www\.oxforddnb\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://www\.anb\.org/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://www\.oxfordartonline\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://www\.ukwhoswho\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://www\.oxfordmusiconline\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://oxfordre\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://oxfordaasc\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://oxford\.universitypressscholarship\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    while (preg_match('~^(https?://oxfordreference\.com/.+)(?:\?print|\?p=email|\;jsession|\?result=|\?rskey|\#|/version/\d+|\?backToResults)~', $template->get($param), $matches)) {
        $template->set($param, $matches[1]);
    }
    if (preg_match('~^https?://www\.oxforddnb\.com/view/10\.1093/(?:ref:|)odnb/9780198614128\.001\.0001/odnb\-9780198614128\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/ref:odnb/' . $matches[1];
        if (!doi_works($new_doi)) {
            $new_doi = '10.1093/odnb/9780198614128.013.' . $matches[1];
        }
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-861412-8');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
        $the_title = $template->get('title');
        if (preg_match('~^(.+) \- Oxford Dictionary of National Biography$~', $the_title, $matches) ||
                preg_match('~^(.+) # # # (?:CITATION_BOT_PLACEHOLDER_TEMPLATE|citation_bot_placeholder_template) \d+ # # # Oxford Dictionary of National Biography$~i', $the_title, $matches) ||
                preg_match('~^(.+)  Oxford Dictionary of National Biography$~', $the_title, $matches) ||
                preg_match('~^(.+) &#\d+; Oxford Dictionary of National Biography$~', $the_title, $matches)) {
            $template->set('title', mb_trim($matches[1]));
        }
    }

    if (preg_match('~^https?://www\.anb\.org/(?:view|abstract)/10\.1093/anb/9780198606697\.001\.0001/anb\-9780198606697\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/anb/9780198606697.article.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-860669-7');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordartonline\.com/(?:benezit/|)(?:view|abstract)/10\.1093/benz/9780199773787\.001\.0001/acref-9780199773787\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/benz/9780199773787.article.B' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-977378-7');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }
    if (preg_match('~^https?://www\.oxfordartonline\.com/(?:groveart/|)(?:view|abstract)/10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-7000(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gao/9781884446054.article.T' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-884446-05-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }
    if (preg_match('~^https?://www\.oxfordartonline\.com/(?:groveart/|)(?:view|abstract)/10\.1093/gao/9781884446054\.001\.0001/oao\-9781884446054\-e\-700(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gao/9781884446054.article.T' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-884446-05-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordaasc\.com/view/10\.1093/acref/9780195301731\.001\.0001/acref\-9780195301731\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acref/9780195301731.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-530173-1');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.ukwhoswho\.com/(?:view|abstract)/10\.1093/ww/(9780199540891|9780199540884)\.001\.0001/ww\-9780199540884\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/ww/9780199540884.013.U' . $matches[2];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', $matches[1]);
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-00000(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-100(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.A' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-5000(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.O' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-400(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.L' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://www\.oxfordmusiconline\.com/(?:grovemusic/|)(?:view|abstract)/10\.1093/gmo/9781561592630\.001\.0001/omo-9781561592630-e-2000(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/gmo/9781561592630.article.J' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-1-56159-263-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|latinamericanhistory/)(?:view|abstract)/10\.1093/acrefore/9780199366439\.001\.0001/acrefore\-9780199366439\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199366439.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-936643-9');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|communication/)(?:view|abstract)/10\.1093/acrefore/9780190228613\.001\.0001/acrefore\-9780190228613\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190228613.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-022861-3');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|environmentalscience/)(?:view|abstract)/10\.1093/acrefore/9780199389414\.001\.0001/acrefore\-9780199389414\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199389414.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-938941-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|americanhistory/)(?:view|abstract)/10\.1093/acrefore/9780199329175\.001\.0001/acrefore\-9780199329175\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199329175.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-932917-5');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|africanhistory/)(?:view|abstract)/10\.1093/acrefore/9780190277734\.001\.0001/acrefore\-9780190277734\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190277734.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-027773-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|internationalstudies/)(?:view|abstract)/10\.1093/acrefore/9780190846626\.001\.0001/acrefore\-9780190846626\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190846626.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-084662-6');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|climatescience/)(?:view|abstract)/10\.1093/acrefore/9780190228620\.001\.0001/acrefore\-9780190228620\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190228620.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-022862-0');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|religion/)(?:view|abstract)/10\.1093/acrefore/9780199340378\.001\.0001/acrefore\-9780199340378\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199340378.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-934037-8');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|anthropology/)(?:view|abstract)/10\.1093/acrefore/9780190854584\.001\.0001/acrefore\-9780190854584\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190854584.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-085458-4');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|classics/)(?:view|abstract)/10\.1093/acrefore/9780199381135\.001\.0001/acrefore\-9780199381135\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780199381135.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-938113-5');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|psychology/)(?:view|abstract)/10\.1093/acrefore/9780190236557\.001\.0001/acrefore\-9780190236557\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190236557.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-023655-7');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|politics/)(?:view|abstract)/10\.1093/acrefore/9780190228637\.001\.0001/acrefore\-9780190228637\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190228637.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-022863-7');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxfordre\.com/(?:|literature/)(?:view|abstract)/10\.1093/acrefore/9780190201098\.001\.0001/acrefore\-9780190201098\-e\-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/acrefore/9780190201098.013.' . $matches[1];
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-020109-8');
            if ($template->has('doi') && $template->has('doi-broken-date')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxford\.universitypressscholarship\.com/(?:view|abstract)/10\.1093/(oso|acprof:oso)/(\d{13})\.001\.0001/oso\-(\d{13})\-chapter\-(\d+)$~', $template->get($param), $matches)) {
        if ($matches[2] === $matches[3]) {
            $template->add_if_new('isbn', $matches[2]);
            $new_doi = '10.1093/' . $matches[1] . '/' . $matches[2] . '.003.' . mb_str_pad($matches[4], 4, "0", STR_PAD_LEFT);
            if (doi_works($new_doi)) {
                if ($template->has('doi') && $template->has('doi-broken-date')) {
                    $template->set('doi', '');
                    $template->forget('doi-broken-date');
                    $template->add_if_new('doi', $new_doi);
                } elseif ($template->blank('doi')) {
                    $template->add_if_new('doi', $new_doi);
                }
            }
        }
    }

    if (preg_match('~^https?://(?:www\.|)oxfordmedicine\.com/(?:view|abstract)/10\.1093/med/9780199592548\.001\.0001/med\-9780199592548-chapter-(\d+)$~', $template->get($param), $matches)) {
        $new_doi = '10.1093/med/9780199592548.003.' . mb_str_pad($matches[1], 4, "0", STR_PAD_LEFT);
        if (doi_works($new_doi)) {
            $template->add_if_new('isbn', '978-0-19-959254-8');
            if ($template->has('doi') && ($template->has('doi-broken-date') || $template->get('doi') === '10.1093/med/9780199592548.001.0001')) {
                $template->set('doi', '');
                $template->forget('doi-broken-date');
                $template->add_if_new('doi', $new_doi);
            } elseif ($template->blank('doi')) {
                $template->add_if_new('doi', $new_doi);
            }
        }
    }

    if (preg_match('~^https?://oxford\.universitypressscholarship\.com/(?:view|abstract)/10\.1093/oso/(\d{13})\.001\.0001/oso\-(\d{13})$~', $template->get($param), $matches)) {
        if ($matches[1] === $matches[2]) {
            $template->add_if_new('isbn', $matches[1]);
            $new_doi = '10.1093/oso/' . $matches[1] . '.001.0001';
            if (doi_works($new_doi)) {
                if ($template->has('doi') && $template->has('doi-broken-date')) {
                    $template->set('doi', '');
                    $template->forget('doi-broken-date');
                    $template->add_if_new('doi', $new_doi);
                } elseif ($template->blank('doi')) {
                    $template->add_if_new('doi', $new_doi);
                }
            }
        }
    }

    if (preg_match('~^https?://(?:www\.|)oxfordhandbooks\.com/(?:view|abstract)/10\.1093/oxfordhb/(\d{13})\.001\.0001/oxfordhb\-(\d{13})-e-(\d+)$~', $template->get($param), $matches)) {
        if ($matches[1] === $matches[2]) {
            $new_doi = '10.1093/oxfordhb/' . $matches[1] . '.013.' . $matches[3];
            if (doi_works($new_doi)) {
                $template->add_if_new('isbn', $matches[1]);
                if (($template->has('doi') && $template->has('doi-broken-date')) || ($template->get('doi') === '10.1093/oxfordhb/9780199552238.001.0001')) {
                    $template->set('doi', '');
                    $template->forget('doi-broken-date');
                    $template->add_if_new('doi', $new_doi);
                } elseif ($template->blank('doi')) {
                    $template->add_if_new('doi', $new_doi);
                }
            }
        }
    }
}

function check_memory_usage(string $where): void {
    $mem_used = (int) (memory_get_usage() / 1048576);
    if ($mem_used > 24) {
        bot_debug_log("Memory Usage is up to " . (string) $mem_used . "MB in " . $where);
    }
    $mem_used = (int) (memory_get_peak_usage() / 1048576);
    if ($mem_used > 128) {
        bot_debug_log("Peak memory Usage is up to " . (string) $mem_used . "MB in " . $where); // @codeCoverageIgnore
    }
}



function string_is_book_series(string $str): bool {
    $simple = mb_trim(str_replace(['-', '.', '   ', '  ', '[[', ']]'], [' ', ' ', ' ', ' ', ' ', ' '], mb_strtolower($str)));
    $simple = mb_trim(str_replace(['    ', '   ', '  '], [' ', ' ', ' '], $simple));
    return in_array($simple, JOURNAL_IS_BOOK_SERIES, true);
}

/*
 * This code is recursive as is goes through a long list of parameters to find its place in the list.
 * TODO: think about better ways to do this.
 */

/**
 * @param string $par
 * @param array<string> $list
 * @return array<string>
 */
function prior_parameters(string $par, array $list = []): array {
    if ($par === '') {
        $par = $list['0'];
    }
    array_unshift($list, $par);
    if (preg_match('~(\D+)(\d+)~', $par, $match) && mb_stripos($par, 's2cid') === false) {
        $before = (string) ((int) $match[2] - 1);
        switch ($match[1]) {
            case in_array($match[1], GROUP_F1, true):
                return ['last' . $match[2], 'surname' . $match[2], 'author' . $before, 'contributor-last' . $before, 'contributor-surname' . $before, 'contributor' . $before, 'contributor' . $before . '-surname', 'contributor' . $before . '-last'];
            case in_array($match[1], GROUP_L1, true):
                return ['first' . $before, 'forename' . $before, 'initials' . $before, 'author' . $before, 'contributor-given' . $before, 'contributor-first' . $before, 'contributor' . $before. '-given', 'contributor' . $before. '-first'];
            default:
                $base = $match[1];
                return array_merge(FLATTENED_AUTHOR_PARAMETERS, [
                                   $base . $before,
                                   $base . $before . '-last', $base . $before . '-first',
                                   $base . '-last' . $before, $base . '-first' . $before,
                                   $base . $before . '-surname', $base . $before . '-given',
                                   $base . '-surname' . $before, $base . '-given' . $before,
                                   ]);
        }
    }
    switch ($par) {
        case in_array($par, GROUP1, true):
            return $list;
        case in_array($par, GROUP2, true):
            return prior_parameters('', array_merge(FLATTENED_AUTHOR_PARAMETERS, $list));
        case in_array($par, GROUP3, true):
            return prior_parameters('', array_merge(GROUP2, $list));
        case in_array($par, GROUP4, true):
            return prior_parameters('', array_merge(GROUP3, $list));
        case in_array($par, GROUP5):
            return prior_parameters('', array_merge(GROUP4, $list));
        case in_array($par, GROUP6):
            return prior_parameters('', array_merge(GROUP5, $list));
        case in_array($par, GROUP7):
            return prior_parameters('', array_merge(GROUP6, $list));
        case in_array($par, GROUP8):
            return prior_parameters('', array_merge(GROUP7, $list));
        case in_array($par, GROUP9):
            return prior_parameters('', array_merge(GROUP8, $list));
        case in_array($par, GROUP10):
            return prior_parameters('', array_merge(GROUP9, $list));
        case in_array($par, GROUP11);
            return prior_parameters('', array_merge(GROUP10, $list));
        case in_array($par, GROUP12):
            return prior_parameters('', array_merge(GROUP11, $list));
        case in_array($par, GROUP13):
            return prior_parameters('', array_merge(GROUP12, $list));
        case in_array($par, GROUP14):
            return prior_parameters('', array_merge(GROUP13, $list));
        case in_array($par, GROUP15):
            return prior_parameters('', array_merge(GROUP14, $list));
        case in_array($par, GROUP16):
            return prior_parameters('', array_merge(GROUP15, $list));
        case in_array($par, GROUP17):
            return prior_parameters('', array_merge(GROUP16, $list));
        case in_array($par, GROUP18):
            return prior_parameters('', array_merge(GROUP17, $list));
        case in_array($par, GROUP19):
            return prior_parameters('', array_merge(GROUP18, $list));
        case in_array($par, GROUP20):
            return prior_parameters('', array_merge(GROUP19, $list));
        case in_array($par, GROUP21):
            return prior_parameters('', array_merge(GROUP20, $list));
        case in_array($par, GROUP22):
            return prior_parameters('', array_merge(GROUP21, $list));
        case in_array($par, GROUP23):
            return prior_parameters('', array_merge(GROUP22, $list));
        case in_array($par, GROUP24):
            return prior_parameters('', array_merge(GROUP23, $list));
        case in_array($par, GROUP25):
            return prior_parameters('', array_merge(GROUP24, $list));
        case in_array($par, GROUP26):
            return prior_parameters('', array_merge(GROUP25, $list));
        case in_array($par, GROUP27):
            return prior_parameters('', array_merge(GROUP26, $list));
        case in_array($par, GROUP28):
            return prior_parameters('', array_merge(GROUP27, $list));
        case in_array($par, GROUP29):
            return prior_parameters('', array_merge(GROUP28, $list));
        case in_array($par, GROUP30):
            return prior_parameters('', array_merge(GROUP29, $list));
        default:
            bot_debug_log("prior_parameters missed: " . $par);
            return $list;
    }
}


/** @return array<string> */
function equivalent_parameters(string $par): array {
    switch ($par) {
        case 'author':
        case 'authors':
        case 'author1':
        case 'last1':
            return FLATTENED_AUTHOR_PARAMETERS;
        case 'pmid':
        case 'pmc':
            return ['pmc', 'pmid'];
        case 'page_range':
        case 'start_page':
        case 'end_page': // From doi_crossref
        case 'pages':
        case 'page':
            return ['page_range', 'pages', 'page', 'end_page', 'start_page'];
        default:
            return [$par];
    }
}


function throttle(): void {
    static $last_write_time = 0;
    static $phase = 0;
    $cycles = 20;    // Check every this many writes
    $min_interval = 2 * $cycles;    // How many seconds we want per-write on average
    if ($last_write_time === 0) {
        $last_write_time = time();
    }

    $mem_max = (string) @ini_get('memory_limit');
    if (preg_match('~^(\d+)M$~', $mem_max, $matches)) {
        $mem_max = (int) (0.3 * @intval($matches[1])); // Memory limit is set super high just to avoid crash
        unset($matches);
        $mem_used = (int) (memory_get_usage() / 1048576);
        if (($mem_max !== 0) && ($mem_used > $mem_max)) {    // Clear every buffer we have
                HandleCache::free_memory();                                                 // @codeCoverageIgnoreStart
                $mem_used1 = (string) (int) (memory_get_usage() / 1048576);
                AdsAbsControl::free_memory();
                $mem_used2 = (string) (int) (memory_get_usage() / 1048576);
                $mem_used0 = (string) $mem_used;
            bot_debug_log("Cleared memory: " . $mem_used2 . ' : '   . $mem_used1 . ' : ' . $mem_used0);
        }                                                                                                                // @codeCoverageIgnoreEnd
    } else {
        bot_debug_log("Memory Limit should end in M, but got: " . echoable($mem_max));  // @codeCoverageIgnore
    }
    $phase += 1;
    if ($phase < $cycles) {
        return;
    } else {
        $phase = 0;
    }

    $time_since_last_write = time() - $last_write_time;
    if ($time_since_last_write < 0) {
        $time_since_last_write = 0; // Super paranoid, this would be a freeze point
    }
    if ($time_since_last_write < $min_interval) {
        $time_to_pause = (int) floor($min_interval - $time_since_last_write); // @codeCoverageIgnore
        report_info("Throttling: waiting " . $time_to_pause . " seconds..."); // @codeCoverageIgnore
        sleep($time_to_pause);                                                // @codeCoverageIgnore
    }
    $last_write_time = time();
}

function simplify_google_search(string $url): string {
    if (mb_stripos($url, 'q=') === false) {
        return $url;     // Not a search
    }
    if (preg_match('~^https?://.*google.com/search/~', $url)) {
        return $url; // Not a search if the slash is there
    }
    $hash = '';
    if (mb_strpos($url, "#")) {
        $url_parts = explode("#", $url, 2);
        $url = $url_parts[0];
        $hash = "#" . $url_parts[1];
    }

    $url_parts = explode("&", str_replace("&&", "&", str_replace("?", "&", $url)));
    array_shift($url_parts);
    $url = "https://www.google.com/search?";

    foreach ($url_parts as $part) {
        $part_start = explode("=", $part, 2);
        $part_start0 = $part_start[0];
        if (isset($part_start[1]) && $part_start[1] === '') {
            $part_start0 = "donotaddmeback"; // Do not add blank ones
            $part_start1 = '';
            $it_is_blank = true;
        } elseif (empty($part_start[1])) {
            $part_start1 = '';
            $it_is_blank = true;
        } else {
            $part_start1 = $part_start[1];
            $it_is_blank = false;
        }
        switch ($part_start0) {
            // Stuff that gets dropped
            case "aq":
            case "aqi":
            case "bih":
            case "biw":
            case "client":
            case "as":
            case "useragent":
            case "as_brr":
            case "ei":
            case "ots":
            case "sig":
            case "source":
            case "lr":
            case "sa":
            case "oi":
            case "ct":
            case "id":
            case "cd":
            case "oq":
            case "rls":
            case "sourceid":
            case "ved":
            case "aqs":
            case "gs_l":
            case "uact":
            case "tbo":
            case "tbs":
            case "num":
            case "redir_esc":
            case "gs_lcp":
            case "sxsrf":
            case "gfe_rd":
            case "gws_rd":
            case "rlz":
            case "sclient":
            case "prmd":
            case "dpr":
            case "newwindow":
            case "gs_ssp":
            case "spell":
            case "shndl":
            case "sugexp":
            case "donotaddmeback":
            case "usg":
            case "fir":
            case "entrypoint":
            case "as_qdr":
            case "as_drrb":
            case "as_minm":
            case "as_mind":
            case "as_maxm":
            case "as_maxd":
            case "kgs":
            case "ictx":
            case "shem":
            case "vet":
            case "iflsig":
            case "tab":
            case "sqi":
            case "noj":
            case "hs":
            case "es_sm":
            case "site":
            case "btnmeta_news_search":
            case "channel":
            case "espv":
            case "cad":
            case "gs_sm":
            case "imgil":
            case "ins":
            case "npsic=":
            case "rflfq":
            case "lei":
            case "rlha":
            case "rldoc":
            case "rldimm":
            case "npsic":
            case "phdesc":
            case "prmdo":
            case "ssui":
            case "lqi":
            case "rlst":
            case "pf":
            case "authuser":
            case "gsas":
            case "ned":
            case "pz":
            case "e":
            case "surl":
            case "aql":
            case "gs_lcrp":
            case "sca_esv":
                break;
            case "as_occt":
                if ($it_is_blank || str_i_same($part_start1, 'any')) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "cf":
                if ($it_is_blank || str_i_same($part_start1, 'all')) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "cs":
                if ($it_is_blank || str_i_same($part_start1, '0')) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "btnK":
                if ($it_is_blank || str_i_same($part_start1, 'Google+Search')) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "as_epq":
                if ($it_is_blank) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "btnG":
                if ($it_is_blank || str_i_same($part_start1, 'Search')) {
                    break;
                }
                $url .=  $part . "&";
                break;
            case "rct":
                if ($it_is_blank || str_i_same($part_start1, 'j')) {
                    break; // default
                }
                $url .=  $part . "&";
                break;
            case "resnum":
                if ($it_is_blank || str_i_same($part_start1, '11')) {
                    break; // default
                }
                $url .=  $part . "&";
                break;
            case "ie":
            case "oe":
                if ($it_is_blank || str_i_same($part_start1, 'utf-8')) {
                    break; // UTF-8 is the default
                }
                $url .=  $part . "&";
                break;
            case "hl":
            case "safe":
            case "q":
            case "tbm":
            case "start":
            case "ludocid":
            case "cshid":
            case "stick":
            case "as_eq":
            case "kgmid":
            case "as_drrb":
            case "gbv":
            case "as_scoring":
            case "gl":
            case "rllag":
            case "lsig":
            case "lpsid":
            case "as_q":
            case "kponly":
                $url .=  $part . "&";
                break;
            // @codeCoverageIgnoreStart
            default:
                report_minor_error("Unexpected Google URL component:    " . echoable($part));
                $url .=  $part . "&";
                break;
            // @codeCoverageIgnoreEnd
        }
    }

    if (mb_substr($url, -1) === "&") {
        $url = mb_substr($url, 0, -1); //remove trailing &
    }
    $url .= $hash;
    return $url;
}

function should_url2chapter(Template $template, bool $force): bool
{
    if ($template->has('chapterurl')) {
        return false;
    }
    if ($template->has('chapter-url')) {
        return false;
    }
    if ($template->has('trans-chapter')) {
        return false;
    }
    if ($template->blank('chapter')) {
        return false;
    }
    if (mb_strpos($template->get('chapter'), '[') !== false) {
        return false;
    }
    $url = $template->get('url');
    $url = str_ireplace('%2F', '/', $url);
    if (mb_stripos($url, 'google') && !mb_strpos($template->get('url'), 'pg=')) {
        return false;
    } // Do not move books without page numbers
    if (mb_stripos($url, 'archive.org/details/isbn')) {
        return false;
    }
    if (mb_stripos($url, 'page_id=0')) {
        return false;
    }
    if (mb_stripos($url, 'page=0')) {
        return false;
    }
    if (mb_substr($url, -2) === '_0') {
        return false;
    }
    if (preg_match('~archive\.org/details/[^/]+$~', $url)) {
        return false;
    }
    if (preg_match('~archive\.org/details/.+/page/n(\d+)~', $url, $matches)) {
        if ((int) $matches[1] < 16) {
            return false;
        } // Assume early in the book - title page, etc
    }
    if (mb_stripos($url, 'PA1') && !preg_match('~PA1[0-9]~i', $url)) {
        return false;
    }
    if (mb_stripos($url, 'PA0')) {
        return false;
    }
    if (mb_stripos($url, 'PP1') && !preg_match('~PP1[0-9]~i', $url)) {
        return false;
    }
    if (mb_stripos($url, 'PP0')) {
        return false;
    }
    if ($template->get_without_comments_and_placeholders('chapter') === '') {
        return false;
    }
    if (mb_stripos($url, 'archive.org')) {
        if (mb_strpos($url, 'chapter')) {
            return true;
        }
        if (mb_strpos($url, 'page')) {
            if (preg_match('~page/?[01]?$~i', $url)) {
                return false;
            }
            return true;
        }
        return false;
    }
    if (mb_stripos($url, 'wp-content')) {
        // Private websites are hard to judge
        if (mb_stripos($url, 'chapter') || mb_stripos($url, 'section')) {
            return true;
        }
        if (mb_stripos($url, 'pages') && !preg_match('~[^\d]1[-–]~u', $url)) {
            return true;
        }
        return false;
    }
    if (mb_strpos($url, 'link.springer.com/chapter/10.')) {
        return true;
    }
    if (preg_match('~10\.1007\/97[89]-?[0-9]{1,5}\-?[0-9]+\-?[0-9]+\-?[0-9]\_\d{1,3}~', $url)) {
        return true;
    }
    if (preg_match('~10\.1057\/97[89]-?[0-9]{1,5}\-?[0-9]+\-?[0-9]+\-?[0-9]\_\d{1,3}~', $url)) {
        return true;
    }
    if ($force) {
        return true;
    }
    // Only do a few select website unless we just converted to cite book from cite journal
    if (mb_strpos($url, 'archive.org')) {
        return true;
    }
    if (mb_strpos($url, 'google.com')) {
        return true;
    }
    if (mb_strpos($url, 'www.sciencedirect.com/science/article')) {
        return true;
    }
    return false;
}

function find_indentifiers_in_urls(Template $template, ?string $url_sent = null): bool {
    static $ch_jstor;
    static $ch_pmc;
    if ($ch_jstor === null) {
        if (TRAVIS) {
            $time = 3.0;
        } else {
            $time = 1.0; // @codeCoverageIgnore
        }
        $ch_jstor = bot_curl_init($time, []);
        $ch_pmc = bot_curl_init($time, []);        
    }
    set_time_limit(120);
    if (is_null($url_sent)) {
        // Chapter URLs are generally better than URLs for the whole book.
        if ($template->has('url') && $template->has('chapterurl')) {
            return (bool) ((int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'chapterurl ') +
                                            (int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'url '));
        } elseif ($template->has('url') && $template->has('chapter-url')) {
            return (bool) ((int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'chapter-url ') +
                                            (int) $template->get_identifiers_from_url(MAGIC_STRING_URLS . 'url '));
        } elseif ($template->has('url')) {
            $url = $template->get('url');
            $url_type = 'url';
        } elseif ($template->has('chapter-url')) {
            $url = $template->get('chapter-url');
            $url_type = 'chapter-url';
        } elseif ($template->has('chapterurl')) {
            $url = $template->get('chapterurl');
            $url_type = 'chapterurl';
        } elseif ($template->has('conference-url')) {
            $url = $template->get('conference-url');
            $url_type = 'conference-url';
        } elseif ($template->has('conferenceurl')) {
            $url = $template->get('conferenceurl');
            $url_type = 'conferenceurl';
        } elseif ($template->has('contribution-url')) {
            $url = $template->get('contribution-url');
            $url_type = 'contribution-url';
        } elseif ($template->has('contributionurl')) {
            $url = $template->get('contributionurl');
            $url_type = 'contributionurl';
        } elseif ($template->has('article-url')) {
            $url = $template->get('article-url');
            $url_type = 'article-url';
        } elseif ($template->has('website')) { // No URL, but a website
            $url = mb_trim($template->get('website'));
            if (mb_strtolower(mb_substr( $url, 0, 6 )) === "ttp://" || mb_strtolower(mb_substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
                $url = "h" . $url;
            }
            if (mb_strtolower(mb_substr( $url, 0, 4 )) !== "http" ) {
                $url = "http://" . $url; // Try it with http
            }
            if (preg_match(REGEXP_IS_URL, $url) !== 1) {
                return false;  // See https://mathiasbynens.be/demo/url-regex/ This regex is more exact than validator. We only spend time on this after quick and dirty check is passed
            }
            if (preg_match('~^https?://[^/]+/?$~', $url) === 1) {
                return false; // Just a host name
            }
            $template->rename('website', 'url'); // Change name it first, so that parameters stay in same order
            $template->set('url', $url);
            $url_type = 'url';
            quietly('report_modification', "website is actually HTTP URL; converting to use url parameter.");
        } else {
            // If no URL or website, nothing to worth with.
            return false;
        }
    } elseif (preg_match('~^' . MAGIC_STRING_URLS . '(\S+) $~', $url_sent, $matches)) {
        $url_sent = null;
        $url_type = $matches[1];
        $url   = $template->get($matches[1]);
    } else {
        $url = $url_sent;
        $url_type = 'An invalid value';
    }

    if (mb_strtolower(mb_substr( $url, 0, 6 )) === "ttp://" || mb_strtolower(mb_substr( $url, 0, 7 )) === "ttps://") { // Not unusual to lose first character in copy and paste
        $url = "h" . $url;
        if (is_null($url_sent)) {
            $template->set($url_type, $url); // Save it
        }
    }
    // Common ones that do not help
    if (mb_strpos($url, 'books.google') !== false ||
            mb_strpos($url, 'researchgate.net') !== false ||
            mb_strpos($url, 'academia.edu') !== false) {
        return false;
    }

    // Abstract only websites
    if (mb_strpos($url, 'orbit.dtu.dk/en/publications') !== false) { // This file path only
        if (is_null($url_sent)) {
            if ($template->has('pmc')) {
                $template->forget($url_type); // Remove it to make room for free-link
            } elseif ($template->has('doi') && $template->get('doi-access') === 'free') {
                $template->forget($url_type); // Remove it to make room for free-link
            }
        }
            return false;
    }
    // IEEE
    if (mb_strpos($url, 'ieeexplore') !== false) {
        if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
            $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }
        if (preg_match('~^https?://ieeexplore\.ieee\.org(?:|\:80)/(?:|abstract/)document/(\d+)/?(?:|\?reload=true)$~', $url, $matches)) {
            $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Normalize to HTTPS and remove abstract and remove trailing slash etc
            }
        }
        if (preg_match('~^https?://ieeexplore\.ieee\.org.*/iel5/\d+/\d+/(\d+).pdf(?:|\?.*)$~', $url, $matches)) {
            $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Normalize
            }
        }
        if (preg_match('~^https://ieeexplore\.ieee\.org/document/0+(\d+)$~', $url, $matches)) {
            $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // remove leading zeroes
            }
        }
    }

    // semanticscholar
    if (mb_stripos($url, 'semanticscholar.org') !== false) {
        $s2cid = getS2CID($url);
        if ($s2cid === '') {
            return false;
        }
        if ($template->has('s2cid') && $s2cid !== $template->get('s2cid')) {
            report_warning('Existing URL does not match existing S2CID: ' .  echoable($template->get('s2cid')));
            return false;
        }
        if ($template->has('S2CID') && $s2cid !== $template->get('S2CID')) {
            report_warning('Existing URL does not match existing S2CID: ' .  echoable($template->get('S2CID')));
            return false;
        }
        $template->add_if_new('s2cid', $s2cid);
        if ($template->wikiname() !== 'cite web' || !$template->blank(['doi', 'pmc', 'pmid', 'journal'])) { // Avoid template errors
            if ($template->has('s2cid') && is_null($url_sent) && $template->blank(['archiveurl', 'archive-url'])) {
                $template->forget($url_type);
                return true;  // Time to clean up
            }
            if (is_null($url_sent) && mb_stripos($url, 'pdf') === false) {
                $template->forget($url_type);
                return true;
            }
            if (is_null($url_sent) && $template->has_good_free_copy() && get_semanticscholar_license($s2cid) === false) {
                report_warning('Removing un-licensed Semantic Scholar URL that was converted to S2CID parameter');
                $template->forget($url_type);
                return true;
            }
        }
        return true;
    }

    if (preg_match("~^(https?://.+\/.+)\?casa_token=.+$~", $url, $matches)) {
        $url = $matches[1];
        if (is_null($url_sent)) {
            $template->set($url_type, $url); // Update URL with cleaner one
        }
    }

    if (mb_stripos($url, 'jstor') !== false) {
        // remove ?seq=1#page_scan_tab_contents off of jstor urls
        // We do this since not all jstor urls are recognized below
        if (preg_match("~^(https?://\S*jstor.org\S*)\?seq=1#[a-zA-Z_]+$~", $url, $matches)) {
            $url = $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }
        if (preg_match("~^(https?://\S*jstor.org\S*)\?refreqid=~", $url, $matches)) {
            $url = $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }
        if (preg_match("~^(https?://\S*jstor.org\S*)\?origin=~", $url, $matches)) {
            if (mb_stripos($url, "accept") !== false) {
                bot_debug_log("Accept Terms and Conditions JSTOR found : " . $url); // @codeCoverageIgnore
            } else {
                $url = $matches[1];
                if (is_null($url_sent)) {
                    $template->set($url_type, $url); // Update URL with cleaner one
                }
            }
        }
        if (mb_stripos($url, 'plants.jstor.org') !== false) {
            return false; # Plants database, not journal
        }
        // https://www.jstor.org.stuff/proxy/stuff/stable/10.2307/3347357 and such
        // Optional 0- at front.
        // DO NOT change www.jstor.org to www\.jstor\.org -- Many proxies use www-jstor-org
        if (preg_match('~^(https?://(?:0-www.|www.|)jstor.org)(?:\S*proxy\S*/|/)(?:stable|discover)/10.2307/(.+)$~i', $url, $matches)) {
            $url = $matches[1] . '/stable/' . $matches[2]; // that is default. This also means we get jstor not doi
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one.  Will probably call forget on it below
            }
        }
        // https://www.jstor.org.libweb.lib.utsa.edu/stable/3347357 and such
        // Optional 0- at front.
        // DO NOT change www.jstor.org to www\.jstor\.org -- Many proxies use www-jstor-org
        // https://www-jstor-org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10 and such
        if (preg_match('~^https?://(?:0-www.|www.|)jstor.org\.[^/]+/(?:stable|discover)/(.+)$~i', $url, $matches)) {
            $url = 'https://www.jstor.org/stable/' . $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }
        // Remove junk from URLs
        while (preg_match('~^https?://www\.jstor\.org/stable/(.+)(?:&ved=|&usg=|%3Fseq%3D1#|\?seq=1#|#metadata_info_tab_contents|;uid=|\?uid=|;sid=|\?sid=)~i', $url, $matches)) {
            $url = 'https://www.jstor.org/stable/' . $matches[1];
            if (is_null($url_sent)) {
                $template->set($url_type, $url); // Update URL with cleaner one
            }
        }

        if (preg_match('~^https?://(?:www\.|)jstor\.org/stable/(?:pdf|pdfplus)/(.+)\.pdf$~i', $url, $matches) ||
            preg_match('~^https?://(?:www\.|)jstor\.org/tc/accept\?origin=(?:\%2F|/)stable(?:\%2F|/)pdf(?:\%2F|/)(\d{3,})\.pdf$~i', $url, $matches)) {
            if ($matches[1] === $template->get('jstor')) {
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return false;
            } elseif ($template->blank('jstor')) {
                curl_setopt($ch_jstor, CURLOPT_URL, 'https://www.jstor.org/citation/ris/' . $matches[1]);
                $dat = bot_curl_exec($ch_jstor);
                if ($dat &&
                        mb_stripos($dat, 'No RIS data found for') === false &&
                        mb_stripos($dat, 'Block Reference') === false &&
                        mb_stripos($dat, 'A problem occurred trying to deliver RIS data') === false &&
                        mb_substr_count($dat, '-') > 3) { // It is actually a working JSTOR.  Not sure if all PDF links are done right
                    if (is_null($url_sent) && $template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                    return $template->add_if_new('jstor', $matches[1]);
                }
                unset($dat);
            }
        }
        if ($template->has('jstor') && preg_match('~^https?://(?:www\.|)jstor\.org/(?:stable|discover)/(?:|pdf/)' . $template->get('jstor') . '(?:|\.pdf)$~i', $url)) {
            if (is_null($url_sent)) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                }
            }
            return false;
        }
    } // JSTOR
    if (preg_match('~^https?://(?:www\.|)archive\.org/detail/jstor\-(\d{5,})$~i', $url, $matches)) {
        $template->add_if_new('jstor', $matches[1]);
        if (is_null($url_sent)) {
            if ($template->has_good_free_copy()) {
                $template->forget($url_type);
            }
        }
        return false;
    }

    if (preg_match('~^https?(://(?:0-www\.|www\.|ucsb\.|)worldcat(?:libraries|)\.org.+)(?:\&referer=brief_results|\?referer=di&ht=edition|\?referer=brief_results|%26referer%3Dbrief_results|\?ht=edition&referer=di|\?referer=br&ht=edition|\/viewport)$~i', $url, $matches)) {
        $url = 'https' . $matches[1];
        if (is_null($url_sent)) {
            $template->set($url_type, $url); // Update URL with cleaner one
        }
    }
    if (preg_match('~^https?(://(?:0-www\.|www\.|ucsb\.)worldcat(?:libraries|)\.org.+)/oclc/(\d+)$~i', $url, $matches)) {
        $url = 'https://www.worldcat.org/oclc/' . $matches[2];
        if (is_null($url_sent)) {
            $template->set($url_type, $url); // Update URL with cleaner one
        }
    }

    if (preg_match('~^https?://onlinelibrary\.wiley\.com/doi/(.+)/abstract\?(?:deniedAccessCustomise|userIsAuthenticated)~i', $url, $matches)) {
        $url = 'https://onlinelibrary.wiley.com/doi/' . $matches[1] . '/abstract';
        if (is_null($url_sent)) {
            $template->set($url_type, $url); // Update URL with cleaner one
        }
    }

    if (preg_match('~^https?://(?:dx\.|)doi\.org/10\.1007/springerreference_(\d+)$~i', $url, $matches)) {
        $url = 'http://www.springerreference.com/index/doi/10.1007/springerreference_' . $matches[1];
        if (is_null($url_sent)) {
            $template->set($url_type, $url); // Update URL with cleaner one
        }
    }

    if (preg_match("~^https?://(?:(?:dx\.|www\.|)doi\.org|doi\.library\.ubc\.ca)/([^\?]*)~i", $url, $match)) {
        if ($template->has('doi')) {
            $doi = $template->get('doi');
            if (str_i_same($doi, $match[1]) || str_i_same($doi, urldecode($match[1]))) {
                if (is_null($url_sent) && $template->get('doi-access') === 'free') {
                    quietly('report_modification', "URL is hard-coded DOI; removing since we already have free DOI parameter");
                    $template->forget($url_type);
                }
                return false;
            }
            // The DOIs do not match
            if (is_null($url_sent)) {
                report_warning('doi.org URL does not match existing DOI parameter, investigating...');
            }
            if ($doi !== $template->get3('doi')) {
                return false;
            }
            if (doi_works($match[1]) && !doi_works($doi)) {
                $template->set('doi', $match[1]);
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return true;
            }
            if (!doi_works($match[1]) && doi_works($doi)) {
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return false;
            }
            return false; // Both valid or both invalid (could be legit if chapter and book are different DOIs
        }
        if ($template->add_if_new('doi', urldecode($match[1]))) { // Will expand from DOI when added
            if (is_null($url_sent) && $template->has_good_free_copy()) {
                quietly('report_modification', "URL is hard-coded DOI; converting to use DOI parameter.");
                $template->forget($url_type);
            }
            return true;
        } else {
            return false; // "bad" doi?
        }
    }
    if (mb_stripos($url, 'oxforddnb.com') !== false) {
        return false; // generally bad
    }
    $doi = extract_doi($url)[1];
    if ($doi) {
        if (bad_10_1093_doi($doi)) {
            return false;
        }
        $old_jstor = $template->get('jstor');
        if (mb_stripos($url, 'jstor')) {
            check_doi_for_jstor($doi, $template);
        }
        if (is_null($url_sent) && $old_jstor !== $template->get('jstor') && mb_stripos($url, 'pdf') === false) {
            if ($template->has_good_free_copy()) {
                $template->forget($url_type);
            }
        }
        $template->tidy_parameter('doi'); // Sanitize DOI before comparing
        if ($template->has('doi') && mb_stripos($doi, $template->get('doi')) === 0) { // DOIs are case-insensitive
            if (doi_works($doi) && is_null($url_sent) && mb_strpos(mb_strtolower($url), ".pdf") === false && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) && mb_strpos(mb_strtolower($url), "supplemental") === false && mb_strpos(mb_strtolower($url), "figure") === false) {
                if ($template->has_good_free_copy()) {
                    report_forget("Recognized existing DOI in URL; dropping URL");
                    $template->forget($url_type);
                }
            }
            return false;  // URL matched existing DOI, so we did not use it
        }
        if ($template->add_if_new('doi', $doi)) {
            $doi = $template->get('doi');
            if (doi_works($doi)) {
                if (is_null($url_sent)) {
                    if (mb_strpos(mb_strtolower($url), ".pdf") === false && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
                        if ($template->has_good_free_copy()) {
                            report_forget("Recognized DOI in URL; dropping URL");
                            $template->forget($url_type);
                        }
                    } else {
                        report_info("Recognized DOI in URL.  Leaving *.pdf URL.");
                    }
                }
            } else {
                $template->mark_inactive_doi();
            }
            return true; // Added new DOI
        }
        return false; // Did not add it
    } elseif ($template->has('doi')) { // Did not find a doi, perhaps we were wrong
        $template->tidy_parameter('doi'); // Sanitize DOI before comparing
        $doi = $template->get('doi');
        if (mb_stripos($url, $doi) !== false) { // DOIs are case-insensitive
            if (doi_works($doi) && is_null($url_sent) && mb_strpos(mb_strtolower($url), ".pdf") === false && not_bad_10_1093_doi($doi) && !preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
                if ($template->has_good_free_copy()) {
                    report_forget("Recognized the existing DOI in URL; dropping URL");
                    $template->forget($url_type);
                }
            }
            return false;  // URL matched existing DOI, so we did not use it
        }
    }

    // JSTOR

    if (mb_stripos($url, "jstor.org") !== false) {
        $sici_pos = mb_stripos($url, "sici");
        if ($sici_pos) { // Outdated url style
            use_sici($template); // Grab what we can.  We do not want this URL incorrectly parsed below, or even waste time trying.
            return false;
        }
        if (preg_match("~^/(?:\w+/)*(\d{5,})[^\d%\-]*(?:\?|$)~", mb_substr($url, (int) mb_stripos($url, 'jstor.org') + 9), $match) ||
                            preg_match("~^https?://(?:www\.)?jstor\.org\S+(?:stable|discovery)/(?:10\.7591/|)(\d{5,}|(?:j|J|histirel|jeductechsoci|saoa|newyorkhist)\.[a-zA-Z0-9\.]+)$~", $url, $match)) {
            if (is_null($url_sent)) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                }
            }
            if ($template->has('jstor')) {
                quietly('report_inaction', "Not using redundant URL (jstor parameter set)");
            } else {
                quietly('report_modification', "Converting URL to JSTOR parameter " . jstor_link(urldecode($match[1])));
                $template->set('jstor', urldecode($match[1]));
            }
            if ($template->wikiname() === 'cite web') {
                $template->change_name_to('cite journal');
            }
            return true;
        } else {
            return false; // Jstor URL yielded nothing
        }
    } else {
        if (preg_match(REGEXP_BIBCODE, urldecode($url), $bibcode)) {
            if ($template->blank('bibcode')) {
                quietly('report_modification', "Converting url to bibcode parameter");
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return $template->add_if_new('bibcode', urldecode($bibcode[1]));
            } elseif (is_null($url_sent) && urldecode($bibcode[1]) === $template->get('bibcode')) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                }
            }
        } elseif (mb_stripos($url, '.nih.gov') !== false) {

            if (preg_match("~^https?://(?:www\.|)pubmedcentral\.nih\.gov/articlerender.fcgi\?.*\bartid=(\d{4,})"
                                            . "|^https?://(?:www\.|pmc\.|)ncbi\.nlm\.nih\.gov/(?:m/|labs/|)pmc/articles/(?:PMC|instance)?(\d{4,})"
                                            . "|^https?://pmc\.ncbi\.nlm\.nih\.gov/(?:m/|labs/|)articles/(?:PMC)?(\d{4,})~i", $url, $match)) {
                if (preg_match("~\?term~i", $url)) {  // ALWAYS ADD new @$mathch[] below
                    return false; // A search such as https://www.ncbi.nlm.nih.gov/pmc/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
                }
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                if ($template->blank('pmc')) {
                    quietly('report_modification', "Converting URL to PMC parameter");
                }
                $new_pmc = (string) @$match[1] . @$match[2] . @$match[3];
                // php stan does not understand that this could because of the insanity of regex and 8-bit characters and PHP bugs end up being empty
                if ($new_pmc === '') { // @phpstan-ignore-line
                    bot_debug_log("PMC oops");
                    return false;
                }
                if (is_null($url_sent)) {
                    if (mb_stripos($url, ".pdf") !== false) {
                        $test_url = "https://www.ncbi.nlm.nih.gov/pmc/articles/PMC" . $new_pmc . "/";
                        curl_setopt($ch_pmc, CURLOPT_URL, $test_url);
                        $the_pmc_body = bot_curl_exec($ch_pmc);
                        $httpCode = (int) curl_getinfo($ch_pmc, CURLINFO_HTTP_CODE);
                        if ($httpCode > 399 || $httpCode === 0 || mb_strpos($the_pmc_body, 'Administrative content — journal masthead, notices, indexes, etc - PMC') !== false) { // Some PMCs do NOT resolve. So leave URL
                            return $template->add_if_new('pmc', $new_pmc);
                        }
                    }
                    if (mb_stripos(str_replace("printable", "", $url), "table") === false) {
                        $template->forget($url_type); // This is the same as PMC auto-link
                    }
                }
                return $template->add_if_new('pmc', $new_pmc);

            } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?\?term=(\d+)$~', $url, $match)) {
                $pos_pmid = $match[1];
                $old_pmid = $template->get('pmid');
                if ($old_pmid === '' || ($old_pmid === $pos_pmid)) {
                    $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $pos_pmid .'/');
                    $template->add_if_new('pmid', $pos_pmid);
                    return true;
                } else {
                    report_warning(echoable($url) . ' does not match PMID of ' . echoable($old_pmid));
                }
                return false;
            } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?\?term=.*$~', $url) && ($template->has('pmid') || $template->has('pmc'))) {
                report_info('Dropped non-specific pubmed search URL, since PMID is present');
                $template->forget($url_type);
                return false;
            } elseif (preg_match("~^https?://(?:www\.|)ncbi\.nlm\.nih\.gov/(?:m/)?"
            . "(?:pubmed/|"
            . "/eutils/elink\.fcgi\S+dbfrom=pubmed\S+/|"
            . "entrez/query\.fcgi\S+db=pubmed\S+?|"
            . "pmc/articles/pmid/)"
            . ".*?=?(\d{4,})~i", $url, $match) ||
                    preg_match("~^https?://(?:pubmed|www)\.ncbi\.nlm\.nih\.gov/(?:|entrez/eutils/elink.fcgi\?dbfrom=pubmed(?:|\&tool=sumsearch.org/cite)\&retmode=ref\&cmd=prlinks\&id=)(\d{4,})/?(?:|#.+|-.+|\?.+)$~", $url, $match)
                ) {
                if (preg_match("~\?term~i", $url) && !preg_match("~pubmed\.ncbi\.nlm\.nih\.gov/\d{4,}/\?from_term=~", $url)) {
                    if (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?(\?term=.*)$~', $url, $matches)) {
                        $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $matches[1]);
                    }
                    return false; // A search such as https://www.ncbi.nlm.nih.gov/pubmed/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493
                }
                if ($template->blank('pmid')) {
                    quietly('report_modification', "Converting URL to PMID parameter");
                }
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                return $template->add_if_new('pmid', $match[1]);

            } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/entrez/eutils/elink.fcgi\?.+tool=sumsearch\.org.+id=(\d+)$~', $url, $match)) {
                if ($url_sent) {
                    return false;  // Many do not work
                }
                if ($template->blank(['doi', 'pmc'])) {
                    return false;  // This is a redirect to the publisher, not pubmed
                }
                if ($match[1] === $template->get('pmc')) {
                        $template->forget($url_type); // Same as PMC-auto-link
                } elseif ($match[1] === $template->get('pmid')) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    } else {
                        $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $match[1]);
                    }
                }
                return false;
            } elseif (preg_match('~^https?://.*ncbi\.nlm\.nih\.gov/pubmed/?(\?term=.*)$~', $url, $matches)) {
                $template->set($url_type, 'https://pubmed.ncbi.nlm.nih.gov/' . $matches[1]);
                return false;
            } elseif (preg_match('~^(https://pubmed\.ncbi\.nlm\.nih\.gov/\d+)/#:~', $url, $matches)) {
                $template->set($url_type, $matches[1]);
                return false;
            }

        } elseif (mb_stripos($url, 'europepmc.org') !== false) {
            if (preg_match("~^https?://(?:www\.|)europepmc\.org/articles?/pmc/?(\d{4,})~i", $url, $match) ||
                    preg_match("~^https?://(?:www\.|)europepmc\.org/scanned\?pageindex=(?:\d+)\&articles=pmc(\d{4,})~i", $url, $match)) {
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                if ($template->blank('pmc')) {
                    quietly('report_modification', "Converting Europe URL to PMC parameter");
                }
                if (is_null($url_sent) && mb_stripos($url, ".pdf") === false) {
                    $template->forget($url_type); // This is same as PMC-auto-link
                }
                return $template->add_if_new('pmc', $match[1]);
            } elseif (preg_match("~^https?://(?:www\.|)europepmc\.org/(?:abstract|articles?)/med/(\d{4,})~i", $url, $match)) {
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                if ($template->blank('pmid')) {
                    quietly('report_modification', "Converting Europe URL to PMID parameter");
                }
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return $template->add_if_new('pmid', $match[1]);
            }
            return false;
        } elseif (mb_stripos($url, 'pubmedcentralcanada.ca') !== false) {
            if (preg_match("~^https?://(?:www\.|)pubmedcentralcanada\.ca/pmcc/articles/PMC(\d{4,})(?:|/.*)$~i", $url, $match)) {
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal');
                }
                if ($template->blank('pmc')) {
                    quietly('report_modification', "Converting Canadian URL to PMC parameter");
                }
                if (is_null($url_sent)) {
                    $template->forget($url_type);  // Always do this conversion, since website is gone!
                }
                return $template->add_if_new('pmc', $match[1]);
            }
            return false;
        } elseif (mb_stripos($url, 'citeseerx') !== false) {
            if (preg_match("~^https?://citeseerx\.ist\.psu\.edu/viewdoc/(?:summary|download)(?:\;jsessionid=[^\?]+|)\?doi=([0-9.]*)(?:&.+)?~", $url, $match)) {
                if ($template->blank('citeseerx')) {
                    quietly('report_modification', "URL is hard-coded citeseerx; converting to use citeseerx parameter.");
                }
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                        if ($template->wikiname() === 'cite web') {
                            $template->change_name_to('cite journal');
                        }
                    }
                }
                return $template->add_if_new('citeseerx', urldecode($match[1])); // We cannot parse these at this time
            }
            return false;

        } elseif (mb_stripos($url, 'arxiv') !== false) {
            if (preg_match("~\barxiv\.org/.*(?:pdf|abs|ftp/arxiv/papers/\d{4})/(.+?)(?:\.pdf)?$~i", $url, $match)) {
                /* ARXIV
                * See https://arxiv.org/help/arxiv_identifier for identifier formats
                */
                if (preg_match("~[A-z\-\.]+/\d{7}~", $match[1], $arxiv_id) // pre-2007
                        || preg_match("~\d{4}\.\d{4,5}(?:v\d+)?~", $match[1], $arxiv_id) // post-2007
                        ) {
                    quietly('report_modification', "Converting URL to arXiv parameter");
                    $ret = $template->add_if_new('arxiv', $arxiv_id[0]); // Have to add before forget to get cite type right
                    if (is_null($url_sent)) {
                        if ($template->has_good_free_copy() || $template->has('arxiv') || $template->has('eprint')) {
                            $template->forget($url_type);
                        }
                    }
                    return $ret;
                }
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite arxiv');
                }
            }
            return false;

        } elseif (preg_match("~^https?://(?:www\.|)amazon(?P<domain>\.[\w\.]{1,7})/.*dp/(?P<id>\d+X?)~i", $url, $match)) {

            if ($template->wikiname() === 'cite web') {
                $template->change_name_to('cite book');
            }
            if ($match['domain'] === ".com") {
                if (is_null($url_sent)) {
                    $template->forget($url_type);
                    if (mb_stripos($template->get('publisher'), 'amazon') !== false) {
                        $template->forget('publisher');
                    }
                }
                if ($template->blank('asin')) {
                    quietly('report_modification', "Converting URL to ASIN parameter");
                    return $template->add_if_new('asin', $match['id']);
                }
            } else {
                if ($template->has('isbn')) { // Already have ISBN
                    quietly('report_inaction', "Not converting ASIN URL: redundant to existing ISBN.");
                } else {
                    if ($template->blank('id')) { // TODO - deal with when already does and does not have {{ASIN}}
                        quietly('report_modification', "Converting URL to ASIN template");
                        $template->set('id', $template->get('id') . " {{ASIN|{$match['id']}|country=" . str_replace([".co.", ".com.", "."], "", $match['domain']) . "}}");
                    } else {
                        return false;  // do not continue and delete it, because of TODO above
                    }
                }
                if (is_null($url_sent)) {
                    $template->forget($url_type); // will forget accessdate too
                    if (mb_stripos($template->get('publisher'), 'amazon') !== false) {
                        $template->forget('publisher');
                    }
                }
            }
        } elseif (mb_stripos($url, 'handle') !== false || mb_stripos($url, 'persistentId=hdl:') !== false) {
            // Special case of hdl.handle.net/123/456
            if (preg_match('~^https?://hdl\.handle\.net/(\d{2,}.*/.+)$~', $url, $matches)) {
                $url = 'https://hdl.handle.net/handle/' . $matches[1];
            }
            // Hostname
            $handle1 = false;
            foreach (HANDLES_HOSTS as $hosts) {
                if (preg_match('~^https?://' . str_replace('.', '\.', $hosts) . '(/.+)$~', $url, $matches)) {
                    $handle1 = $matches[1];
                    break;
                }
            }
            if ($handle1 === false) {
                return false;
            }
            // file path
            $handle = false;
            foreach (HANDLES_PATHS as $handle_path) {
                if (preg_match('~^' . $handle_path . '(.+)$~', $handle1, $matches)) {
                    $handle = $matches[1];
                    break;
                }
            }
            if ($handle === false) {
                return false;
            }
            // take off session stuff - urlappend seems to be used for page numbers and such
            $handle = str_ireplace('%3B', ';', $handle);
            while (preg_match('~^(.+)(?:/browse\?|;jsessionid|;sequence=|\?sequence=|&isAllowed=|&origin=|&rd=|\?value=|&type=|/browse-title|&submit_browse=|;ui=embed)~',
                                                 $handle, $matches)) {
                $handle = $matches[1];
            }
            $handle = hdl_decode($handle);
            if (preg_match('~^(.+)\%3Bownerid=~', $handle, $matches) || preg_match('~^(.+)\;ownerid=~', $handle, $matches)) {
                if (hdl_works($matches[1])) {
                    $handle = $matches[1];
                }
            }
            // Verify that it works as a hdl - first with urlappend, since that is often page numbers
            if (preg_match('~^(.+)\?urlappend=~', $handle, $matches)) {  // should we shorten it?
                if (hdl_works($handle) === false) {
                    $handle = $matches[1];  // @codeCoverageIgnore
                } elseif (hdl_works($handle) === null && (hdl_works($matches[1]) === null || hdl_works($matches[1]) === false)) {
                    // Do nothing
                } elseif (hdl_works($handle) === null) {
                    $handle = $matches[1]; // @codeCoverageIgnore
                } else { // Both work
                    $long = hdl_works($handle);
                    $short = hdl_works($matches[1]);
                    if ($long === $short) { // urlappend does nothing
                        $handle = $matches[1]; // @codeCoverageIgnore
                    }
                }
            }
            while (preg_match('~^(.+)/$~', $handle, $matches)) { // Trailing slash
                $handle = $matches[1];
            }
            while (preg_match('~^/(.+)$~', $handle, $matches)) { // Leading slash
                $handle = $matches[1];
            }
            // Safety check
            if (mb_strlen($handle) < 6 || mb_strpos($handle, '/') === false) {
                return false;
            }
            if (mb_strpos($handle, '123456789') === 0) {
                return false;
            }

            $the_question = mb_strpos($handle, '?');
            if ($the_question !== false) {
                $handle = mb_substr($handle, 0, $the_question) . '?' . str_replace('%3D', '=', urlencode(mb_substr($handle, $the_question+1)));
            }

            // Verify that it works as a hdl
            $the_header_loc = hdl_works($handle);
            if ($the_header_loc === false || $the_header_loc === null) {
                return false;
            }
            if ($template->blank('hdl')) {
                quietly('report_modification', "Converting URL to HDL parameter");
            }
            if (is_null($url_sent)) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                }
            }
            if (preg_match('~^([^/]+/[^/]+)/.*$~', $handle, $matches)  // Might be padded with stuff
                && mb_stripos($the_header_loc, $handle) === false
                && mb_stripos($the_header_loc, $matches[1]) !== false) {  // Too long ones almost never resolve, but we have seen at least one
                $handle = $matches[1]; // @codeCoverageIgnore
            }
            return $template->add_if_new('hdl', $handle);
        } elseif (mb_stripos($url, 'zbmath.org') !== false) {
            if (preg_match("~^https?://zbmath\.org/\?(?:format=complete&|)q=an:([0-9][0-9][0-9][0-9]\.[0-9][0-9][0-9][0-9][0-9])~i", $url, $match)) {
                if ($template->blank('zbl')) {
                    quietly('report_modification', "Converting URL to ZBL parameter");
                }
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                        if ($template->wikiname() === 'cite web') {
                            $template->change_name_to('cite journal');
                        }
                    }
                }
                return $template->add_if_new('zbl', $match[1]);
            }
            if (preg_match("~^https?://zbmath\.org/\?(?:format=complete&|)q=an:([0-9][0-9]\.[0-9][0-9][0-9][0-9]\.[0-9][0-9])~i", $url, $match)) {
                if ($template->blank('jfm')) {
                    quietly('report_modification', "Converting URL to JFM parameter");
                }
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                        if ($template->wikiname() === 'cite web') {
                            $template->change_name_to('cite journal');
                        }
                    }
                }
                return $template->add_if_new('jfm', $match[1]);
            }
            return false;
        } elseif (preg_match("~^https?://mathscinet\.ams\.org/mathscinet-getitem\?mr=([0-9]+)~i", $url, $match)) {
            if ($template->blank('mr')) {
                quietly('report_modification', "Converting URL to MR parameter");
            }
            //if (is_null($url_sent)) {
            //    $template->forget($url_type); // This points to a review and not the article
            //}
            return $template->add_if_new('mr', $match[1]);
        } elseif (preg_match("~^https?://papers\.ssrn\.com(?:/sol3/papers\.cfm\?abstract_id=|/abstract=)([0-9]+)~i", $url, $match)) {
            if ($template->blank('ssrn')) {
                quietly('report_modification', "Converting URL to SSRN parameter");
            }
            if (is_null($url_sent)) {
                if ($template->has_good_free_copy()) {
                    $template->forget($url_type);
                    if ($template->wikiname() === 'cite web') {
                        $template->change_name_to('cite journal');
                    }
                }
            }
            return $template->add_if_new('ssrn', $match[1]);
        } elseif (mb_stripos($url, 'osti.gov') !== false) {
            if (preg_match("~^https?://(?:www\.|)osti\.gov/(?:scitech/|)(?:biblio/|)(?:purl/|)([0-9]+)(?:\.pdf|)~i", $url, $match)) {
                if ($template->blank('osti')) {
                    quietly('report_modification', "Converting URL to OSTI parameter");
                }
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                        if ($template->wikiname() === 'cite web') {
                            $template->change_name_to('cite journal');
                        }
                    }
                }
                return $template->add_if_new('osti', $match[1]);
            }
            if (preg_match("~^https?://(?:www\.|)osti\.gov/energycitations/product\.biblio\.jsp\?osti_id=([0-9]+)~i", $url, $match)) {
                if ($template->blank('osti')) {
                    quietly('report_modification', "Converting URL to OSTI parameter");
                }
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                        if ($template->wikiname() === 'cite web') {
                            $template->change_name_to('cite journal');
                        }
                    }
                }
                return $template->add_if_new('osti', $match[1]);
            }
            return false;
        } elseif (mb_stripos($url, 'worldcat.org') !== false) {
            if (preg_match("~^https?://(?:www\.|)worldcat\.org(?:/title/\S+)?/oclc/([0-9]+)~i", $url, $match)) {
                if (mb_strpos($url, 'edition') && ($template->wikiname() !== 'cite book')) {
                    report_warning('Not adding OCLC because is appears to be a weblink to a list of editions: ' . echoable($match[1]));
                    return false;
                }
                $check_me = $template->get('work') . $template->get('website') . $template->get('publisher');
                if (mb_stripos($check_me, 'oclc') !== false || mb_stripos($check_me, 'open library') !== false) {
                    return $template->add_if_new('oclc', $match[1]);
                }
                if ($template->blank('oclc')) {
                    quietly('report_modification', "Converting URL to OCLC parameter");
                }
                if ($template->wikiname() === 'cite web') {
                    // $template->change_name_to('cite book');  // Better template choice
                }
                if (is_null($url_sent) && $template->wikiname() === 'cite book') {
                    $template->forget($url_type);
                }
                return $template->add_if_new('oclc', $match[1]);
            } elseif (preg_match("~^https?://(?:www\.|)worldcat\.org/issn/(\d{4})(?:|-)(\d{3}[\dxX])$~i", $url, $match)) {
                if ($template->blank('issn')) {
                    quietly('report_modification', "Converting URL to ISSN parameter");
                }
                if ($template->wikiname() === 'cite web') {
                    $template->change_name_to('cite journal'); // Better template choice
                }
                if (is_null($url_sent)) {
                    $template->forget($url_type);
                }
                return $template->add_if_new('issn_force', $match[1] . '-' . $match[2]);
            }
            return false;
        } elseif (preg_match("~^https?://lccn\.loc\.gov/(\d{4,})$~i", $url, $match) &&
                            (mb_stripos($template->parsed_text(), 'library') === false)) { // Sometimes it is web cite to Library of Congress
            if ($template->wikiname() === 'cite web') {
                $template->change_name_to('cite book');  // Better template choice
            }
            if ($template->blank('lccn')) {
                quietly('report_modification', "Converting URL to LCCN parameter");
            }
            if (is_null($url_sent)) {
                $template->forget($url_type);
            }
            return $template->add_if_new('lccn', $match[1]);
        } elseif (preg_match("~^https?://openlibrary\.org/books/OL/?(\d{4,}[WM])(?:|/.*)$~i", $url, $match)) { // We do W "work" and M "edition", but not A, which is author
            if ($template->blank('ol')) {
                quietly('report_modification', "Converting URL to OL parameter");
            }
            if ($template->wikiname() === 'cite web') {
                $template->change_name_to('cite book');  // Better template choice
            }
            if (is_null($url_sent)) {
                $template->forget($url_type);
            }
            return $template->add_if_new('ol', $match[1]);
        } elseif (preg_match("~^https?://(?:search|www)\.proquest\.com/docview/(\d{4,})$~i", $url, $match) && $template->has('title') && $template->blank('id')) {
            if ($template->add_if_new('id', '{{ProQuest|' . $match[1] . '}}')) {
                quietly('report_modification', 'Converting URL to ProQuest parameter');
                if (is_null($url_sent)) {
                    if ($template->has_good_free_copy()) {
                        $template->forget($url_type);
                    }
                }
                return true;
            }
        } elseif (preg_match("~^https?:\/\/(?:www\.)?sciencedirect\.com\/book\/(978\d{10})(?:$|\/)~i", $url, $match) && $template->blank('isbn')) {
            if ($template->add_if_new('isbn', $match[1])) {
                return true;
            }
            /// THIS MUST BE LAST
        } elseif (($template->has('chapterurl') || $template->has('chapter-url') || $template->has('url') || ($url_type === 'url') || ($url_type === 'chapterurl') || ($url_type === 'chapter-url')) && preg_match("~^https?://web\.archive\.org/web/\d{14}(?:|fw_)/(https?://.*)$~", $url, $match) && $template->blank(['archiveurl', 'archive-url'])) {
            if (is_null($url_sent)) {
                quietly('report_modification', 'Extracting URL from archive');
                $template->set($url_type, $match[1]);
                $template->add_if_new('archive-url', $match[0]);
                return false; // We really got nothing
            }
        }
        /// THIS MUST BE LAST
    }
    return false;
}


function handleConferencePretendingToBeAJournal(Template $template, string $rawtext): void {
    $the_chapter = '';
    $the_issue = '';
    $the_journal = '';
    $the_page = '';
    $the_pages = '';
    $the_title = '';
    $the_volume = '';
    $this_array = [$template];
    $move_and_forget = function (string $para) use($template): void
    {
        // Try to keep parameters in the same order
        $para2 = str_replace('CITATION_BOT_PLACEHOLDER_', '', $para);
        if ($template->has($para2)) {
            $template->set($para, $template->get($para2));
            $template->rename($para, $para2);
        } else {
            $template->forget($para); // This can happen when there is less than ideal data, such as {{cite journal|jstor=3073767|pages=null|page=null|volume=n/a|issue=0|title=[No title found]|coauthors=Duh|last1=Duh|first1=Dum|first=Hello|last=By|author=Yup|author1=Nope|year=2002
        }
    };

    if (
        mb_stripos($rawtext, 'citation_bot_placeholder_comment') === false &&
        mb_stripos($rawtext, 'graph drawing') === false &&
        mb_stripos($rawtext, 'Lecture Notes in Computer Science') === false &&
        mb_stripos($rawtext, 'LNCS ') === false &&
        mb_stripos($rawtext, ' LNCS') === false && (
            !$template->blank(['pmc', 'pmid', 'doi', 'jstor']) || (
                mb_stripos($template->get('journal') . $template->get('title'), 'arxiv') !== false && !$template->blank(ARXIV_ALIASES)
            )
        )
    ) {
        // Have some good data
        $the_title = $template->get('title');
        $the_journal = str_replace(['[', ']'], '', $template->get('journal'));
        $the_chapter = $template->get('chapter');
        $the_volume = $template->get('volume');
        $the_issue = $template->get('issue');
        $the_page = $template->get('page');
        $the_pages = $template->get('pages');
        if ($template->get2('chapter') === null) {
            $no_start_chapter = true;
        } else {
            $no_start_chapter = false;
        }
        if ($template->get2('journal') === null) {
            $no_start_journal = true;
        } else {
            $no_start_journal = false;
        }
        $initial_author_params_save = $template->initial_author_params();
        $bad_data = false;
        if (mb_stripos($the_journal, 'Advances in Cryptology') === 0 && mb_stripos($the_title, 'Advances in Cryptology') === 0) {
            $the_journal = '';
            $template->forget('journal');
            $bad_data = true;
        }
        $ieee_insanity = false;
        if (
            conference_doi($template->get('doi')) &&
            in_array($template->wikiname(), ['cite journal', 'cite web'], true) &&
            ($template->has('isbn') ||
            (mb_stripos($the_title, 'proceedings') !== false && mb_stripos($the_journal, 'proceedings') !== false) ||
            (mb_stripos($the_title, 'proc. ') !== false && mb_stripos($the_journal, 'proc. ') !== false) ||
            (mb_stripos($the_title, 'Conference') !== false && mb_stripos($the_journal, 'Conference') !== false) ||
            (mb_stripos($the_title, 'Colloquium') !== false && mb_stripos($the_journal, 'Colloquium') !== false) ||
            (mb_stripos($the_title, 'Symposium') !== false && mb_stripos($the_journal, 'Symposium') !== false) ||
            (mb_stripos($the_title, 'Extended Abstracts') !== false && mb_stripos($the_journal, 'Extended Abstracts') !== false) ||
            (mb_stripos($the_title, 'Meeting on ') !== false && mb_stripos($the_journal, 'Meeting on ') !== false))
        ) {
            // IEEE/ACM/etc "book"
            $data_to_check = $the_title . $the_journal . $the_chapter . $template->get('series');
            if (mb_stripos($data_to_check, 'IEEE Standard for') !== false && $template->blank('journal')) {
                // Do nothing
            } elseif (mb_stripos($data_to_check, 'SIGCOMM Computer Communication Review') !== false) {
                // Actual journal with ISBN
                // Do nothing
            } elseif (
                mb_stripos($data_to_check, 'Symposium') === false &&
                mb_stripos($data_to_check, 'Conference') === false &&
                mb_stripos($data_to_check, 'Proceedings') === false &&
                mb_stripos($data_to_check, 'Proc. ') === false &&
                mb_stripos($data_to_check, 'Workshop') === false &&
                mb_stripos($data_to_check, 'Symp. On ') === false &&
                mb_stripos($data_to_check, 'Meeting on ') === false &&
                mb_stripos($data_to_check, 'Colloquium') === false &&
                mb_stripos($data_to_check, 'Extended Abstracts') === false &&
                mb_stripos($the_journal, 'Visual Languages and Human-Centric Computing') === false &&
                mb_stripos($the_journal, 'Active and Passive Microwave Remote Sensing for') === false
            ) {
                // Looks like conference done, but does not claim so
                if ($the_journal !== '') {
                    $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
                    $the_journal = '';
                }
                if ($the_title !== '') {
                    $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                    $the_title = '';
                }
                if ($the_chapter !== '') {
                    $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                    $the_chapter = '';
                }
                $bad_data = true;
            } elseif (
                mb_stripos($the_journal, 'Symposium') !== false ||
                mb_stripos($the_journal, 'Conference') !== false ||
                mb_stripos($the_journal, 'Proceedings') !== false ||
                mb_stripos($the_journal, 'Proc. ') !== false ||
                mb_stripos($the_journal, 'Workshop') !== false ||
                mb_stripos($the_journal, 'Symp. On ') !== false ||
                mb_stripos($the_journal, 'Meeting on ') !== false ||
                mb_stripos($the_journal, 'Colloquium') !== false ||
                mb_stripos($the_journal, 'Extended Abstracts') !== false ||
                mb_stripos($the_journal, 'Active and Passive Microwave Remote Sensing for') !== false ||
                mb_stripos($the_journal, 'Visual Languages and Human-Centric Computing') !== false
            ) {
                $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
                $ieee_insanity = true;
                $the_journal = '';
                $bad_data = true;
                if ($the_title !== '') {
                    $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                    $the_title = '';
                }
                if ($the_chapter !== '') {
                    $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                    $the_chapter = '';
                }
            }
        }
        if (
            mb_stripos($the_journal, 'Advances in Cryptology') === 0 ||
            mb_stripos($the_journal, 'IEEE Symposium') !== false ||
            mb_stripos($the_journal, 'IEEE Conference') !== false ||
            mb_stripos($the_journal, 'IEEE International Conference') !== false ||
            mb_stripos($the_journal, 'ACM International Symposium') !== false ||
            mb_stripos($the_journal, 'ACM Symposium') !== false ||
            mb_stripos($the_journal, 'Extended Abstracts') !== false ||
            mb_stripos($the_journal, 'IEEE International Symposium') !== false ||
            mb_stripos($the_journal, 'Symposium on Theoretical Aspects') !== false ||
            mb_stripos($the_journal, 'Lecture Notes in Computer Science') !== false ||
            mb_stripos($the_journal, 'International Conference on ') !== false ||
            mb_stripos($the_journal, 'ACM International Conference') !== false ||
            mb_stripos($the_journal, 'Proceedings of SPIE') !== false ||
            mb_stripos($the_journal, 'Proceedings of the SPIE') !== false ||
            mb_stripos($the_journal, 'SPIE Proc') !== false ||
            mb_stripos($the_journal, 'Proceedings of the Society of ') !== false ||
            (mb_stripos($the_journal, 'Proceedings of ') !== false && mb_stripos($the_journal, 'Conference') !== false) ||
            (mb_stripos($the_journal, 'Proc. ') !== false && mb_stripos($the_journal, 'Conference') !== false) ||
            (mb_stripos($the_journal, 'International') !== false && mb_stripos($the_journal, 'Conference') !== false) ||
            (mb_stripos($the_journal, 'International') !== false && mb_stripos($the_journal, 'Meeting') !== false) ||
            (mb_stripos($the_journal, 'International') !== false && mb_stripos($the_journal, 'Colloquium') !== false) ||
            (mb_stripos($the_journal, 'International') !== false && mb_stripos($the_journal, 'Symposium') !== false) ||
            mb_stripos($the_journal, 'SIGGRAPH') !== false ||
            mb_stripos($the_journal, 'Design Automation Conference') !== false
        ) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
            if ($the_title !== '') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
            }
            if ($the_chapter !== '') {
                $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                $the_chapter = '';
            }
        }
        if ($template->is_book_series('series') && $the_journal !== "") {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
            if ($the_title !== '') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
            }
            if ($the_chapter !== '') {
                $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                $the_chapter = '';
            }
        } elseif ($template->is_book_series('series') && $the_chapter === '' && $the_title !== '' && $template->has('doi')) {
            $bad_data = true;
            $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
            $the_title = '';
        }

        if ($the_pages === '_' || $the_pages === '0' || $the_pages === 'null' || $the_pages === 'n/a' || $the_pages === 'online' || $the_pages === 'Online' || $the_pages === 'Forthcoming' || $the_pages === 'forthcoming') {
            $template->rename('pages', 'CITATION_BOT_PLACEHOLDER_pages');
            $the_pages = '';
            $bad_data = true;
        }
        if ($the_page === '_' || $the_page === '0' || $the_page === 'null' || $the_page === 'n/a' || $the_page === 'online' || $the_page === 'Online' || $the_page === 'Forthcoming' || $the_page === 'forthcoming') {
            $template->rename('page', 'CITATION_BOT_PLACEHOLDER_page');
            $the_page = '';
            $bad_data = true;
        }
        if (
            $the_volume === '_' ||
            $the_volume === '0' ||
            $the_volume === 'null' ||
            $the_volume === 'n/a' ||
            $the_volume === 'Online edition' ||
            $the_volume === 'online' ||
            $the_volume === 'Online' ||
            $the_volume === 'in press' ||
            $the_volume === 'In press' ||
            $the_volume === 'ahead-of-print' ||
            $the_volume === 'Forthcoming' ||
            $the_volume === 'forthcoming'
        ) {
            $template->rename('volume', 'CITATION_BOT_PLACEHOLDER_volume');
            $the_volume = '';
            $bad_data = true;
        }
        if (
            $the_issue === '_' ||
            $the_issue === '0' ||
            $the_issue === 'null' ||
            $the_issue === 'ja' ||
            $the_issue === 'n/a' ||
            $the_issue === 'Online edition' ||
            $the_issue === 'online' ||
            $the_issue === 'Online' ||
            $the_issue === 'in press' ||
            $the_issue === 'In press' ||
            $the_issue === 'ahead-of-print' ||
            $the_issue === 'Forthcoming' ||
            $the_issue === 'forthcoming'
        ) {
            $template->rename('issue', 'CITATION_BOT_PLACEHOLDER_issue');
            $the_issue = '';
            $bad_data = true;
        }
        if (mb_strlen($the_title) > 15 && mb_strpos($the_title, ' ') !== false && mb_strtoupper($the_title) === $the_title && mb_strpos($the_title, 'CITATION') === false && mb_check_encoding($the_title, 'ASCII')) {
            $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
            $the_title = '';
            $bad_data = true;
        }
        if (mb_stripos($the_title, 'SpringerLink') !== false) {
            $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
            $the_title = '';
            $bad_data = true;
        }
        if (
            $the_title === '_' ||
            $the_title === 'null' ||
            $the_title === '[No title found]' ||
            $the_title === 'Archived copy' ||
            $the_title === 'JSTOR' ||
            $the_title === 'ShieldSquare Captcha' ||
            $the_title === 'Shibboleth Authentication Request' ||
            $the_title === 'Pubmed' ||
            $the_title === 'usurped title' ||
            $the_title === 'Pubmed Central' ||
            $the_title === 'Optica Publishing Group' ||
            $the_title === 'BioOne' ||
            $the_title === 'IEEE Xplore' ||
            $the_title === 'ScienceDirect' ||
            $the_title === 'Science Direct' ||
            $the_title === 'Validate User'
        ) {
            // title=none is often because title is "reviewed work....
            $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
            $the_title = '';
            $bad_data = true;
        }
        if (mb_strlen($the_journal) > 15 && mb_strpos($the_journal, ' ') !== false && mb_strtoupper($the_journal) === $the_journal && mb_strpos($the_journal, 'CITATION') === false && mb_check_encoding($the_journal, 'ASCII')) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if (mb_strlen($the_chapter) > 15 && mb_strpos($the_chapter, ' ') !== false && mb_strtoupper($the_chapter) === $the_chapter && mb_strpos($the_chapter, 'CITATION') === false && mb_check_encoding($the_chapter, 'ASCII')) {
            $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
            $the_chapter = '';
            $bad_data = true;
        }
        if (str_i_same($the_journal, 'Biochimica et Biophysica Acta') || str_i_same($the_journal, '[[Biochimica et Biophysica Acta]]')) {
            // Only part of the journal name
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if (
            str_i_same($the_journal, 'JSTOR') ||
            $the_journal === '_' ||
            str_i_same($the_journal, 'BioOne') ||
            str_i_same($the_journal, 'IEEE Xplore') ||
            str_i_same($the_journal, 'PubMed') ||
            str_i_same($the_journal, 'PubMed Central') ||
            str_i_same($the_journal, 'ScienceDirect') ||
            str_i_same($the_journal, 'Science Direct')
        ) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if ((mb_stripos($the_journal, 'arXiv:') === 0 || $the_journal === 'arXiv') && !$template->blank(ARXIV_ALIASES)) {
            $template->forget('journal');
            $the_journal = '';
            $bad_data = true;
            if ($template->wikiname() === 'cite journal') {
                $template->change_name_to('cite arxiv');
            }
        }
        if (mb_stripos($the_journal, 'arXiv') !== false) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if (mb_stripos($the_journal, 'ScienceDirect') !== false) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if ($the_chapter === '_') {
            $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
            $the_chapter = '';
            $bad_data = true;
        }
        if ($the_title !== '' && mb_stripos(str_replace('CITATION_BOT_PLACEHOLDER_TEMPLATE', '', $the_title), 'CITATION') === false) {
            // Templates are generally {{!}} and such
            if (str_i_same($the_title, $the_journal) && str_i_same($the_title, $the_chapter)) {
                // Journal === Title === Chapter INSANE!  Never actually seen
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
                $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                $the_title = '';
                $the_journal = '';
                $the_chapter = '';
                $bad_data = true;
            } elseif (str_i_same($the_title, $the_journal)) {
                // Journal === Title
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
                $the_title = '';
                $the_journal = '';
                $bad_data = true;
            } elseif (str_i_same($the_title, $the_chapter)) {
                // Chapter === Title
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                $the_title = '';
                $the_chapter = '';
                $bad_data = true;
            } elseif (mb_substr($the_title, -9, 9) === ' on JSTOR') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title'); // Ends in 'on jstor'
                $the_title = '';
                $bad_data = true;
            } elseif (mb_substr($the_title, -20, 20) === 'IEEE Xplore Document') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (mb_substr($the_title, 0, 12) === 'IEEE Xplore ') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (mb_substr($the_title, -12) === ' IEEE Xplore') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (preg_match('~.+(?: Volume| Vol\.| V. | Number| No\.| Num\.| Issue ).*\d+.*page.*\d+~i', $the_title)) {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (preg_match('~^\[No title found\]$~i', $the_title)) {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (mb_stripos($the_title, 'arXiv') !== false) {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            }
        }
        if ($template->has('coauthors')) {
            if ($template->has('first')) {
                $template->rename('first', 'CITATION_BOT_PLACEHOLDER_first');
            }
            if ($template->has('last')) {
                $template->rename('last', 'CITATION_BOT_PLACEHOLDER_last');
            }
            if ($template->has('first1')) {
                $template->rename('first1', 'CITATION_BOT_PLACEHOLDER_first1');
            }
            if ($template->has('last1')) {
                $template->rename('last1', 'CITATION_BOT_PLACEHOLDER_last1');
            }
            if ($template->has('author1')) {
                $template->rename('author1', 'CITATION_BOT_PLACEHOLDER_author1');
            }
            if ($template->has('author')) {
                $template->rename('author', 'CITATION_BOT_PLACEHOLDER_author');
            }
            $template->rename('coauthors', 'CITATION_BOT_PLACEHOLDER_coauthors');
            if ($template->blank(FLATTENED_AUTHOR_PARAMETERS)) {
                $template->initial_author_params_set([]);
                $bad_data = true;
            } else {
                if ($template->has('CITATION_BOT_PLACEHOLDER_first')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_first', 'first');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_last')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_last', 'last');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_first1')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_first1', 'first1');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_last1')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_last1', 'last1');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_author1')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_author1', 'author1');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_author')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_author', 'author');
                }
                $template->rename('CITATION_BOT_PLACEHOLDER_coauthors', 'coauthors');
            }
        }
        if ($bad_data) {
            if ($template->has('year') && $template->blank(['isbn', 'lccn', 'oclc'])) {
                // Often the pre-print year
                $template->rename('year', 'CITATION_BOT_PLACEHOLDER_year');
            }
            if ($template->has('doi')) {
                expand_by_doi($template);
            }
            if ($template->has('pmid')) {
                query_pmid_api([$template->get('pmid')], $this_array);
            }
            if ($template->has('pmc')) {
                query_pmc_api([$template->get('pmc')], $this_array);
            }
            if ($template->has('jstor')) {
                expand_by_jstor($template);
            }
            if ($template->blank(['pmid', 'pmc', 'jstor']) && ($template->has('eprint') || $template->has('arxiv'))) {
                expand_arxiv_templates($this_array);
            }
            if ($ieee_insanity && $template->has('chapter') && $template->has('title')) {
                $template->forget('CITATION_BOT_PLACEHOLDER_journal');
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_journal')) {
                if ($template->has('journal') && $template->get('journal') !== $template->get('CITATION_BOT_PLACEHOLDER_journal') && '[[' . $template->get('journal') . ']]' !== $template->get('CITATION_BOT_PLACEHOLDER_journal')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_journal');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_journal', 'journal');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_title')) {
                if ($template->has('title')) {
                    $newer = str_replace([".", ",", ":", ";", "?", "!", " ", "-", "'", '"'], '', mb_strtolower($template->get('title')));
                    $older = str_replace([".", ",", ":", ";", "?", "!", " ", "-", "'", '"'], '', mb_strtolower($template->get('CITATION_BOT_PLACEHOLDER_title')));
                    if ($newer !== $older && mb_strpos($older, $newer) === 0) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_title', 'title'); // New title lost sub-title
                    } elseif (str_replace(" ", '', $template->get('title')) === str_replace([" ", "'"], '', $template->get('CITATION_BOT_PLACEHOLDER_title'))) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_title', 'title'); // New title lost italics
                    } elseif ($template->get('title') === $template->get('CITATION_BOT_PLACEHOLDER_title')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_title', 'title');
                    } else {
                        $move_and_forget('CITATION_BOT_PLACEHOLDER_title');
                    }
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_title', 'title');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_chapter')) {
                if ($template->has('chapter')) {
                    $newer = str_replace([".", ",", ":", ";", "?", "!", " ", "-", "'", '"'], '', mb_strtolower($template->get('chapter')));
                    $older = str_replace([".", ",", ":", ";", "?", "!", " ", "-", "'", '"'], '', mb_strtolower($template->get('CITATION_BOT_PLACEHOLDER_chapter')));
                    if ($newer !== $older && mb_strpos($older, $newer) === 0) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter'); // New chapter lost sub-chapter
                    } elseif (str_replace(" ", '', $template->get('chapter')) === str_replace([" ", "'"], '', $template->get('CITATION_BOT_PLACEHOLDER_chapter'))) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter'); // New chapter lost italics
                    } elseif ($template->get('chapter') === $template->get('CITATION_BOT_PLACEHOLDER_chapter')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter');
                    } else {
                        $move_and_forget('CITATION_BOT_PLACEHOLDER_chapter');
                    }
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_issue')) {
                if ($template->has('issue') && $template->get('issue') !== $template->get('CITATION_BOT_PLACEHOLDER_issue')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_issue');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_issue', 'issue');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_volume')) {
                if ($template->has('volume') && $template->get('volume') !== $template->get('CITATION_BOT_PLACEHOLDER_volume')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_volume');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_volume', 'volume');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_page')) {
                if (($template->has('page') || $template->has('pages')) && $template->get('page') . $template->get('pages') !== $template->get('CITATION_BOT_PLACEHOLDER_page')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_page');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_page', 'page');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_pages')) {
                if (($template->has('page') || $template->has('pages')) && $template->get('page') . $template->get('pages') !== $template->get('CITATION_BOT_PLACEHOLDER_pages')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_pages');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_pages', 'pages');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_year')) {
                if ($template->has('year') && $template->get('year') !== $template->get('CITATION_BOT_PLACEHOLDER_year')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_year');
                } elseif ($template->has('date') && $template->get('date') !== $template->get('CITATION_BOT_PLACEHOLDER_year')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_year');
                } elseif ($template->has('date') && $template->get('date') === $template->get('CITATION_BOT_PLACEHOLDER_year')) {
                    $template->forget('date');
                    $template->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_coauthors')) {
                if ($template->has('last1') || $template->has('author1')) {
                    $template->forget('CITATION_BOT_PLACEHOLDER_first');
                    $template->forget('CITATION_BOT_PLACEHOLDER_last');
                    $template->forget('CITATION_BOT_PLACEHOLDER_first1');
                    $template->forget('CITATION_BOT_PLACEHOLDER_last1');
                    $template->forget('CITATION_BOT_PLACEHOLDER_author1');
                    $template->forget('CITATION_BOT_PLACEHOLDER_author');
                    $template->forget('CITATION_BOT_PLACEHOLDER_coauthors');
                } else {
                    $template->initial_author_params_set($initial_author_params_save);
                    if ($template->has('CITATION_BOT_PLACEHOLDER_first')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_first', 'first');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_last')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_last', 'last');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_first1')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_first1', 'first1');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_last1')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_last1', 'last1');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_author1')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_author1', 'author1');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_author')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_author', 'author');
                    }
                    $template->rename('CITATION_BOT_PLACEHOLDER_coauthors', 'coauthors');
                }
            }
        }
        if ($no_start_chapter && $template->blank('chapter')) {
            $template->forget('chapter');
        }
        if ($no_start_journal && $template->blank('journal')) {
            $template->forget('journal');
        }
    }
    if ($the_chapter === 'a' && $the_issue === 'b' && $the_journal === 'c' && $the_page === 'd' && $the_pages === 'e' && $the_title === 'f' && $the_volume === 'g') {
        report_info('static analyis is happy');
        // We set many of these variables to "", and then never use them again.
        // We do this it means that over time we can safely expnand this function.
        // But this makes static analysis unhappy.
    }
}



function clean_cite_odnb(Template $template): void
{
    if ($template->has('url')) {
        while (preg_match('~^(https?://www\.oxforddnb\.com/.+)(?:\;jsession|\?rskey|\#)~', $template->get('url'), $matches)) {
            $template->set('url', $matches[1]);
        }
    }
    if ($template->has('doi')) {
        $doi = $template->get('doi');
        if (doi_works($doi) === false) {
            if (preg_match("~^10\.1093/(?:\S+odnb-9780198614128-e-|ref:odnb|odnb/9780198614128\.013\.|odnb/)(\d+)$~", $doi, $matches)) {
                $try1 = '10.1093/ref:odnb/' . $matches[1];
                $try3 = '10.1093/odnb/9780198614128.013.' . $matches[1];
                if (doi_works($try1)) {
                    $template->set('doi', $try1);
                } elseif (doi_works($try3)) {
                    $template->set('doi', $try3);
                }
            }
        }
    }
    if ($template->has('id')) {
        $doi = $template->get('doi');
        $try1 = '10.1093/ref:odnb/' . $template->get('id');
        $try3 = '10.1093/odnb/9780198614128.013.' . $template->get('id');
        if (doi_works($try1) !== false) {
            // Template does this
        } elseif (doi_works($try3)) {
            if ($doi === '') {
                $template->rename('id', 'doi', $try3);
            } elseif ($doi === $try3) {
                $template->forget('id');
            } elseif (doi_works($doi)) {
                $template->forget('id');
            } else {
                $template->forget('doi');
                $template->rename('id', 'doi', $try3);
            }
        }
    }
    if ($template->has('doi')) {
        $works = doi_works($template->get('doi'));
        if ($works === false) {
            $template->add_if_new('doi-broken-date', date('Y-m-d'));
        } elseif ($works === true) {
            $template->forget('doi-broken-date');
        }
    }
}

/**
 * @param array<Template> &$templates
 */
function drop_urls_that_match_dois(array &$templates): void {  // Pointer to save memory
    static $ch_dx;
    static $ch_doi;
    if ($ch_dx === null) {
        if (TRAVIS) {
            $time = 3.0;
        } else {
            $time = 1.0; // @codeCoverageIgnore
        }
        $ch_dx = bot_curl_init($time, []);
        $ch_doi = bot_curl_init($time, []);
    }
    // Now that we have expanded URLs, try to lose them
    foreach ($templates as $template) {
        $doi = $template->get_without_comments_and_placeholders('doi');
        if ($template->has('url')) {
            $url = $template->get('url');
            $url_kind = 'url';
        } elseif ($template->has('chapter-url')) {
            $url = $template->get('chapter-url');
            $url_kind = 'chapter-url';
        } elseif ($template->has('chapterurl')) {
            $url = $template->get('chapterurl'); // @codeCoverageIgnore
            $url_kind = 'chapterurl';      // @codeCoverageIgnore
        } else {
            $url = '';
            $url_kind = '';
        }
        if ($doi && // IEEE code does not require "not incomplete"
            $url &&
            !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
            $template->blank(DOI_BROKEN_ALIASES) &&
            preg_match("~^https?://ieeexplore\.ieee\.org/document/\d{5,}/?$~", $url) && mb_strpos($doi, '10.1109') === 0) {
            report_forget("Existing IEEE resulting from equivalent DOI; dropping URL");
            $template->forget($url_kind);
        }

        if ($doi &&
                $url &&
                !$template->profoundly_incomplete() &&
                !preg_match(REGEXP_DOI_ISSN_ONLY, $doi) &&
                (mb_strpos($doi, '10.1093/') === false) &&
                $template->blank(DOI_BROKEN_ALIASES)) {
                set_time_limit(120);
            if (str_ireplace(PROXY_HOSTS_TO_DROP, '', $url) !== $url && $template->get('doi-access') === 'free') {
                report_forget("Existing proxy URL resulting from equivalent free DOI; dropping URL");
                $template->forget($url_kind);
            } elseif (str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $url) !== $url && $template->get('doi-access') === 'free') {
                report_forget("Existing proxy URL resulting from equivalent free DOI; dropping URL");
                $template->forget($url_kind);
            } elseif (str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $url) !== $url && $template->blank(['archive-url', 'archiveurl'])) {
                report_forget("Existing proxy URL resulting from equivalent DOI; fixing URL");
                $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (preg_match('~www.sciencedirect.com/science/article/B[^/\-]*\-[^/\-]+\-[^/\-]+/~', $url)) {
                report_forget("Existing Invalid ScienceDirect URL when DOI is present; fixing URL");
                $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (preg_match('~www.sciencedirect.com/science/article/pii/\S{0,16}$~i', $url)) { // Too Short
                report_forget("Existing Invalid ScienceDirect URL when DOI is present; fixing URL");
                $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (preg_match('~www.springerlink.com/content~i', $url)) { // Dead website
                report_forget("Existing Invalid Springer Link URL when DOI is present; fixing URL");
                $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (str_ireplace('insights.ovid.com/pubmed', '', $url) !== $url && $template->has('pmid')) {
                report_forget("Existing OVID URL resulting from equivalent PMID and DOI; dropping URL");
                $template->forget($url_kind);
            } elseif ($template->has('pmc') && str_ireplace('iopscience.iop.org', '', $url) !== $url) {
                report_forget("Existing IOP URL resulting from equivalent DOI; dropping URL");
                $template->forget($url_kind);;
                $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
            } elseif (str_ireplace('wkhealth.com', '', $url) !== $url) {
                report_forget("Existing Outdated WK Health URL resulting from equivalent DOI; fixing URL");
                $template->set($url_kind, "https://dx.doi.org/" . doi_encode($doi));
            } elseif ($template->has('pmc') && str_ireplace('bmj.com/cgi/pmidlookup', '', $url) !== $url && $template->has('pmid') && $template->get('doi-access') === 'free' && mb_stripos($url, 'pdf') === false) {
                report_forget("Existing The BMJ URL resulting from equivalent PMID and free DOI; dropping URL");
                $template->forget($url_kind);
            } elseif ($template->get('doi-access') === 'free' && $template->get('url-status') === 'dead' && $url_kind === 'url') {
                report_forget("Existing free DOI; dropping dead URL");
                $template->forget($url_kind);
            } elseif (doi_works($template->get('doi')) &&
                        !preg_match(REGEXP_DOI_ISSN_ONLY, $template->get('doi')) &&
                        $url_kind !== '' &&
                        (str_ireplace(CANONICAL_PUBLISHER_URLS, '', $template->get($url_kind)) !== $template->get($url_kind)) &&
                        $template->has_good_free_copy() &&
                        (mb_stripos($template->get($url_kind), 'pdf') === false)) {
                report_forget("Existing canonical URL resulting in equivalent free DOI/pmc; dropping URL");
                $template->forget($url_kind);
            } elseif (mb_stripos($url, 'pdf') === false && $template->get('doi-access') === 'free' && $template->has('pmc')) {
                curl_setopt($ch_dx, CURLOPT_URL, "https://dx.doi.org/" . doi_encode($doi));
                $ch_return = bot_curl_exec($ch_dx);
                if (mb_strlen($ch_return) > 50) { // Avoid bogus tiny pages
                    $redirectedUrl_doi = curl_getinfo($ch_dx, CURLINFO_EFFECTIVE_URL); // Final URL
                    if (mb_stripos($redirectedUrl_doi, 'cookie') !== false) {
                        break; // @codeCoverageIgnore
                    }
                    if (mb_stripos($redirectedUrl_doi, 'denied') !== false) {
                        break; // @codeCoverageIgnore
                    }
                    $redirectedUrl_doi = url_simplify($redirectedUrl_doi);
                    $url_short = url_simplify($url);
                    if (preg_match('~^https?://.+/pii/?(S?\d{4}[^/]+)~i', $redirectedUrl_doi, $matches ) === 1 ) { // Grab PII numbers
                        $redirectedUrl_doi = $matches[1];  // @codeCoverageIgnore
                    }
                    if (mb_stripos($url_short, $redirectedUrl_doi) !== false ||
                        mb_stripos($redirectedUrl_doi, $url_short) !== false) {
                        report_forget("Existing canonical URL resulting from equivalent free DOI; dropping URL");
                        $template->forget($url_kind);
                    } else { // See if $url redirects
                        /** @psalm-taint-escape ssrf */
                        $the_url = $url;
                        curl_setopt($ch_doi, CURLOPT_URL, $the_url);
                        $ch_return = bot_curl_exec($ch_doi);
                        if (mb_strlen($ch_return) > 60) {
                            $redirectedUrl_url = curl_getinfo($ch_doi, CURLINFO_EFFECTIVE_URL);
                            $redirectedUrl_url =url_simplify($redirectedUrl_url);
                            if (mb_stripos($redirectedUrl_url, $redirectedUrl_doi) !== false ||
                                            mb_stripos($redirectedUrl_doi, $redirectedUrl_url) !== false) {
                                report_forget("Existing canonical URL resulting from equivalent free DOI; dropping URL");
                                $template->forget($url_kind);
                            }
                        }
                    }
                }
                unset($ch_return);
            }
        }
        $url = $template->get($url_kind);
        if ($url && !$template->profoundly_incomplete() && str_ireplace(PROXY_HOSTS_TO_ALWAYS_DROP, '', $url) !== $url) {
            if (!$template->blank_other_than_comments('pmc')) {
                report_forget("Existing proxy URL resulting from equivalent PMC; dropping URL");
                $template->forget($url_kind);
            }
        }
    }
}
    
    
function url_simplify(string $url): string {
    $url = str_replace('/action/captchaChallenge?redirectUri=', '', $url);
    $url = urldecode($url);
    // IEEE is annoying
    if (preg_match('~ieeexplore.ieee.org.+arnumber=(\d+)(?:|[^\d].*)$~', $url, $matches)) {
        $url = 'https://ieeexplore.ieee.org/document/' . $matches[1];
    }
    $url .= '/';
    $url = str_replace(['/abstract/', '/full/', '/full+pdf/', '/pdf/', '/document/', '/html/', '/html+pdf/', '/abs/', '/epdf/', '/doi/', '/xprint/', '/print/', '.short', '.long', '.abstract', '.full', '///', '//'],
                                            ['/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/', '/'], $url);
    $url = mb_substr($url, 0, -1); // Remove the ending slash we added
    $url = (string) preg_split("~[\?\#]~", $url, 2)[0];
    return str_ireplace('https', 'http', $url);
}
