<?php

declare(strict_types=1);

function id_to_param(Template $template): void
{
    set_time_limit(120);
    $id = $template->get('id');
    if (mb_trim($id)) {
        report_action("Trying to convert ID parameter to parameterized identifiers.");
    } else {
        return;
    }
    if ($id === "<small></small>" || $id === "<small> </small>" || $id === ".") {
        $template->forget('id');
        return;
    }
    while (preg_match("~\b(PMID|DOI|ISBN|ISSN|ARXIV|LCCN|CiteSeerX|s2cid|PMC)[\s:]*(\d[\d\s\-][^\s\}\{\|,;]*)(?:[,;] )?~iu", $id, $match)) {
        $the_type = mb_strtolower($match[1]);
        $the_data = $match[2];
        $the_all = $match[0];
        if ($the_type !== 'doi' && preg_match("~^([^\]\}\{\s\,\;\:\|\<\>]+)$~", $the_data, $matches)) {
            $the_data = $matches[1];
        }
        $template->add_if_new($the_type, $the_data);
        $id = str_replace($the_all, '', $id);
    }
    if (preg_match_all('~' . sprintf(self::PLACEHOLDER_TEXT, '(\d+)') . '~', $id, $matches)) {
        $num_placeholders = count($matches[1]);
        for ($i = 0; $i < $num_placeholders; $i++) {
            $subtemplate = self::$all_templates[$matches[1][$i]];
            $subtemplate_name = $subtemplate->wikiname();
            switch ($subtemplate_name) {
                case "arxiv":
                    if ($subtemplate->get('id')) {
                           $archive_parameter = mb_trim($subtemplate->get('archive') ? $subtemplate->get('archive') . '/' : '');
                           $template->add_if_new('arxiv', $archive_parameter . $subtemplate->get('id'));
                    } elseif ($subtemplate->has_multiple_params()) {
                        $template->add_if_new('arxiv', mb_trim($subtemplate->param_value(0)) . "/" . mb_trim($subtemplate->param_value(1)));
                    } else {
                        $template->add_if_new('arxiv', $subtemplate->param_value(0));
                    }
                    $id = str_replace($matches[0][$i], '', $id);
                    break;
                case "asin":
                case "oclc":
                case "bibcode":
                case "doi":
                case "isbn":
                case "issn":
                case "jfm":
                case "jstor":
                case "lccn":
                case "mr":
                case "osti":
                case "pmid":
                case "pmc":
                case "ssrn":
                case "citeseerx":
                case "s2cid":
                case "hdl":
                case "zbl":
                case "ol":
                case "lcc":
                case "ismn":
                case "biorxiv":
                    // Specific checks for particular templates:
                    if ($subtemplate_name === 'asin' && $subtemplate->has('country')) {
                        report_info("{{ASIN}} country parameter not supported: cannot convert.");
                        break;
                    }
                    if ($subtemplate_name === 'ol' && $subtemplate->has('author')) {
                        report_info("{{OL}} author parameter not supported: cannot convert.");
                        break;
                    }
                    if ($subtemplate_name === 'ol' && mb_stripos($subtemplate->parsed_text(), "ia:") !== false) {
                        report_info("{{OL}} ia: parameter not supported: cannot convert.");
                        break;
                    }
                    if (($subtemplate_name === 'jstor' && $subtemplate->has('sici')) || $subtemplate->has('issn')) {
                        report_info("{{JSTOR}} named parameters are not supported: cannot convert.");
                        break;
                    }
                    if ($subtemplate_name === 'oclc' && $subtemplate->has_multiple_params()) {
                        report_info("{{OCLC}} has multiple parameters: cannot convert. " . echoable($subtemplate->parsed_text()));
                        break;
                    }
                    if ($subtemplate_name === 'issn' && $subtemplate->has_multiple_params()) {
                        report_info("{{ISSN}} has multiple parameters: cannot convert. " . echoable($subtemplate->parsed_text()));
                        break;
                    }
                    if ($subtemplate_name === 'ismn' && $subtemplate->has_multiple_params()) {
                        report_info("{{ISMN}} has multiple parameters: cannot convert. " . echoable($subtemplate->parsed_text()));
                        break;
                    }
                    if ($subtemplate_name === 'biorxiv' && $subtemplate->has_multiple_params()) {
                        report_info("{{biorxiv}} has multiple parameters: cannot convert. " . echoable($subtemplate->parsed_text()));
                        break;
                    }
                    if ($subtemplate_name === 'lcc') {
                        if (preg_match('~^[\d\-]+$~', $subtemplate->param_value(0))) {
                            report_minor_error("Possible bad LCC template (did they mean LCCN) : " . echoable($subtemplate->param_value(0))); // @codeCoverageIgnore
                        }
                        break;
                    }

                    // All tests okay; move identifier to suitable parameter
                    $subtemplate_identifier = $subtemplate->has('id') ? $subtemplate->get('id') : $subtemplate->param_value(0);

                    $did_it = $template->add_if_new($subtemplate_name, $subtemplate_identifier);
                    if ($did_it) {
                        $id = str_replace($matches[0][$i], '', $id);
                    }
                    break;

                // TODO: Check if these have been added https://en.wikipedia.org/wiki/Template:Cite_journal
                case "circe":
                case "ill":
                case "cs":
                case "proquest":
                case "inist":
                case "gale":
                case "eric":
                case "naid":
                case "dtic":
                case "project muse":
                case "pii":
                case "ebscohost":
                case "libris":
                case "selibr":
                case "cobiss":
                case "crosbi":
                case "euclid":
                case "federal register":
                case "jpno":
                case "lancaster university library":
                case "listed invalid isbn":
                case "ncbibook2":
                case "ncj":
                case "researchgatepub":
                case "university of south wales pure":
                case "usgs index id":
                case "us patent":
                case "us trademark":
                case "zdb":
                case "subscription required":
                case "ncid":
                case "wikileaks cable":
                case "idp":
                case "bhl page":
                case "internet archive":
                case "youtube":
                case "nypl":
                case "bnf":
                case "dnb-idn":
                case "nara catalog record":
                case "urn":
                case "viaf":
                case "so-vid":
                case "philpapers":
                case "iccu":
                case "hathitrust":
                case "allmusic":
                case "hal":
                case "icd11":
                case "coden":
                case "blcat":
                case "cobiss.bih":
                case "cobiss.rs":
                case "cobiss.sr":
                case "harvtxt":
                case "mathnet":
                case "eissn":
                case "ndljp":
                case "orcid":
                case "pq":
                case "sudoc":
                case "upc":
                case "ceeol":
                case "nps history library":
                case "smaller":
                case "zenodo":
                case "!":
                case "hathitrust catalog":
                case "eccc":
                case "ean":
                case "ethos":
                case "chmid":
                case "factiva":
                case "mesh":
                case "dggs citation id":
                case "harvp":
                case "nla":
                case "catkey":
                case "hyphen":
                case "mit libraries":
                case "epa national catalog":
                case "unt key":
                case "eram":
                case "regreq":
                case "nobr":
                case "subscription":
                case "uspl":
                case "small":
                case "rism":
                case "jan":
                case "nbsp":
                case "abbr":
                case "closed access":
                case "interp":
                case "genbank":
                case "better source needed":
                case "free access":
                case "required subscription":
                case "fahrplan-ch":
                case "incomplete short citation":
                case "music":
                case "bar-ads":
                case "subscription or libraries":
                case "gallica":
                case "gnd":
                case "ncbibook":
                case "spaces":
                case "ndash":
                case "dggs":
                case "self-published source":
                case "nobreak":
                case "university of twente pure":
                case "mathscinet":
                case "discogs master":
                case "harv":
                case "registration required":
                case "snd":
                case "hsdl":
                case "academia.edu":
                case "gbooks":
                case "gburl": // TODO - should use
                case "isbnt":
                case "issn link":
                case "project euclid":
                case "circa":
                case "ndlpid":
                case "core output":
                case "core work":
                case "internet archive id":
                case "lccn8": // Assume not normal template for a reason
                case "google books": // Usually done for fancy formatting and because already has title-link/url
                case "url": // Untrustable: used by bozos
                    break;
                default:
                    report_minor_error("No match found for subtemplate type: " . echoable($subtemplate_name)); // @codeCoverageIgnore
            }
        }
    }
    if (mb_trim($id)) {
        $template->set('id', $id);
    } else {
        $template->forget('id');
    }
    if ($id === "<small></small>" || $id === "<small> </small>") {
        $template->forget('id');
        return;
    }
}
