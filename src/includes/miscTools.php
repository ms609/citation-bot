<?php

declare(strict_types=1);

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

/** @param array<string> $list
    @return array<string> */
function prior_parameters(string $par, array $list=[]): array {
    if ($par === '') {
        $par = $list['0'];
    }
    array_unshift($list, $par);
    if (preg_match('~(\D+)(\d+)~', $par, $match) && mb_stripos($par, 's2cid') === false) {
        $before = (string) ((int) $match[2] - 1);
        switch ($match[1]) {
            case 'first':
            case 'initials':
            case 'forename':
            case 'contributor-first':
            case 'contributor-given':
                return ['last' . $match[2], 'surname' . $match[2], 'author' . $before, 'contributor-last' . $before, 'contributor-surname' . $before, 'contributor' . $before, 'contributor' . $before . '-surname', 'contributor' . $before . '-last'];
            case 'last':
            case 'surname':
            case 'author':
            case 'contributor-last':
            case 'contributor-surname':
            case 'contributor':
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
        case in_array($par, GROUP5);
            return prior_parameters('', array_merge(GROUP4, $list));
        case in_array($par, GROUP6);
            return prior_parameters('', array_merge(GROUP5, $list));
        case in_array($par, GROUP7);
            return prior_parameters('', array_merge(GROUP6, $list));
        case in_array($par, GROUP8);
            return prior_parameters('', array_merge(GROUP7, $list));
        case in_array($par, GROUP9);
            return prior_parameters('', array_merge(GROUP8, $list));
        case in_array($par, GROUP10);
            return prior_parameters('', array_merge(GROUP9, $list));
        case in_array($par, GROUP11);
            return prior_parameters('', array_merge(GROUP10, $list));
        case in_array($par, GROUP12);
            return prior_parameters('', array_merge(GROUP11, $list));
        case in_array($par, GROUP13);
            return prior_parameters('', array_merge(GROUP12, $list));
        case in_array($par, GROUP14);
            return prior_parameters('', array_merge(GROUP13, $list));
        case in_array($par, GROUP15);
            return prior_parameters('', array_merge(GROUP14, $list));
        case in_array($par, GROUP16);
            return prior_parameters('', array_merge(GROUP15, $list));
        case in_array($par, GROUP17);
            return prior_parameters('', array_merge(GROUP16, $list));
        case in_array($par, GROUP18);
            return prior_parameters('', array_merge(GROUP17, $list));
        case in_array($par, GROUP19);
            return prior_parameters('', array_merge(GROUP18, $list));
        case in_array($par, GROUP20);
            return prior_parameters('', array_merge(GROUP19, $list));
        case in_array($par, GROUP21);
            return prior_parameters('', array_merge(GROUP20, $list));
        case in_array($par, GROUP22);
            return prior_parameters('', array_merge(GROUP21, $list));
        case in_array($par, GROUP23);
            return prior_parameters('', array_merge(GROUP22, $list));
        case in_array($par, GROUP24);
            return prior_parameters('', array_merge(GROUP23, $list));
        case in_array($par, GROUP25);
            return prior_parameters('', array_merge(GROUP24, $list));
        case in_array($par, GROUP26);
            return prior_parameters('', array_merge(GROUP25, $list));
        case in_array($par, GROUP27);
            return prior_parameters('', array_merge(GROUP26, $list));
        case in_array($par, GROUP28);
            return prior_parameters('', array_merge(GROUP27, $list));
        case in_array($par, GROUP29);
            return prior_parameters('', array_merge(GROUP28, $list));
        case in_array($par, GROUP30);
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
                $url .=  $part . "&" ;
                break;
            case "cf":
                if ($it_is_blank || str_i_same($part_start1, 'all')) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "cs":
                if ($it_is_blank || str_i_same($part_start1, '0')) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "btnK":
                if ($it_is_blank || str_i_same($part_start1, 'Google+Search')) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "as_epq":
                if ($it_is_blank) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "btnG":
                if ($it_is_blank || str_i_same($part_start1, 'Search')) {
                    break;
                }
                $url .=  $part . "&" ;
                break;
            case "rct":
                if ($it_is_blank || str_i_same($part_start1, 'j')) {
                    break; // default
                }
                $url .=  $part . "&" ;
                break;
            case "resnum":
                if ($it_is_blank || str_i_same($part_start1, '11')) {
                    break; // default
                }
                $url .=  $part . "&" ;
                break;
            case "ie":
            case "oe":
                if ($it_is_blank || str_i_same($part_start1, 'utf-8')) {
                    break; // UTF-8 is the default
                }
                $url .=  $part . "&" ;
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
                $url .=  $part . "&" ;
                break;
            // @codeCoverageIgnoreStart
            default:
                report_minor_error("Unexpected Google URL component:    " . echoable($part));
                $url .=  $part . "&" ;
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
