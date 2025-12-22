<?php

declare(strict_types=1);

function use_sici(Template $template): void {
    if (preg_match(REGEXP_SICI, urldecode($template->parsed_text()), $sici)) {
        quietly('report_action', "Extracting information from SICI");
        $template->add_if_new('issn', $sici[1]); // Check whether journal is set in add_if_new
        $template->add_if_new('year', (string) (int) $sici[2]);
        $template->add_if_new('volume', (string) (int) $sici[5]);
        if ($sici[6]) {
            $template->add_if_new('issue', (string) (int) $sici[6]);
        }
        $template->add_if_new('pages', (string) (int) $sici[7]);
        report_action("Found and used SICI");
    }
}
