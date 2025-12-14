<?php

declare(strict_types=1);

function handleConferencePretendingToBeAJournal(Template $template): void {
    $the_chapter = '';
    $the_issue = '';
    $the_journal = '';
    $the_page = '';
    $the_pages = '';
    $the_title = '';
    $the_volume = '';
    if (
        mb_stripos($template->rawtext, 'citation_bot_placeholder_comment') === false &&
        mb_stripos($template->rawtext, 'graph drawing') === false &&
        mb_stripos($template->rawtext, 'Lecture Notes in Computer Science') === false &&
        mb_stripos($template->rawtext, 'LNCS ') === false &&
        mb_stripos($template->rawtext, ' LNCS') === false && (
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
        $initial_author_params_save = $template->initial_author_params;
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
                $template->initial_author_params = [];
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
            $template->this_array = [$template];
            if ($template->has('pmid')) {
                query_pmid_api([$template->get('pmid')], $template->this_array);
            }
            if ($template->has('pmc')) {
                query_pmc_api([$template->get('pmc')], $template->this_array);
            }
            if ($template->has('jstor')) {
                expand_by_jstor($template);
            }
            if ($template->blank(['pmid', 'pmc', 'jstor']) && ($template->has('eprint') || $template->has('arxiv'))) {
                expand_arxiv_templates($template->this_array);
            }
            $template->this_array = [];
            if ($ieee_insanity && $template->has('chapter') && $template->has('title')) {
                $template->forget('CITATION_BOT_PLACEHOLDER_journal');
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_journal')) {
                if ($template->has('journal') && $template->get('journal') !== $template->get('CITATION_BOT_PLACEHOLDER_journal') && '[[' . $template->get('journal') . ']]' !== $template->get('CITATION_BOT_PLACEHOLDER_journal')) {
                    $template->move_and_forget('CITATION_BOT_PLACEHOLDER_journal');
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
                        $template->move_and_forget('CITATION_BOT_PLACEHOLDER_title');
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
                        $template->move_and_forget('CITATION_BOT_PLACEHOLDER_chapter');
                    }
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_issue')) {
                if ($template->has('issue') && $template->get('issue') !== $template->get('CITATION_BOT_PLACEHOLDER_issue')) {
                    $template->move_and_forget('CITATION_BOT_PLACEHOLDER_issue');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_issue', 'issue');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_volume')) {
                if ($template->has('volume') && $template->get('volume') !== $template->get('CITATION_BOT_PLACEHOLDER_volume')) {
                    $template->move_and_forget('CITATION_BOT_PLACEHOLDER_volume');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_volume', 'volume');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_page')) {
                if (($template->has('page') || $template->has('pages')) && $template->get('page') . $template->get('pages') !== $template->get('CITATION_BOT_PLACEHOLDER_page')) {
                    $template->move_and_forget('CITATION_BOT_PLACEHOLDER_page');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_page', 'page');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_pages')) {
                if (($template->has('page') || $template->has('pages')) && $template->get('page') . $template->get('pages') !== $template->get('CITATION_BOT_PLACEHOLDER_pages')) {
                    $template->move_and_forget('CITATION_BOT_PLACEHOLDER_pages');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_pages', 'pages');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_year')) {
                if ($template->has('year') && $template->get('year') !== $template->get('CITATION_BOT_PLACEHOLDER_year')) {
                    $template->move_and_forget('CITATION_BOT_PLACEHOLDER_year');
                } elseif ($template->has('date') && $template->get('date') !== $template->get('CITATION_BOT_PLACEHOLDER_year')) {
                    $template->move_and_forget('CITATION_BOT_PLACEHOLDER_year');
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
                    $template->initial_author_params = $initial_author_params_save;
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
        report_info('static analyis is happy');  // We set many of these variables to "", and then never use them again.  We do this it means that over time we can safely expnand this function.  But this makes static analysis unhappy.
    }
}

