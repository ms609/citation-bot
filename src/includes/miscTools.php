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
