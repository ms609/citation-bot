<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class WrappedCitationUrlTest extends testBaseClass {
    public function testWrappedUrlsKeepTheirDestinationOrRemainIntact(): void {
        $oup = 'https://academic.oup.com/crawlprevention/governor?content=';
        $proxy = 'https://login.libproxy.example.org/login?url=';
        $ancestry = 'https://www.ancestry.com/cs/offers/join?url=';
        $privacy = 'https://myprivacy.dpgmedia.nl/consent?callbackUrl=';
        $privacy_target = 'https://example.org/privacygate?redirectUri=';
        $bloomberg = 'https://www.bloomberg.com/tosv2.html?vid=&uuid=123&url=';
        foreach ([
            $privacy . rawurlencode(rawurlencode($privacy_target . '/caf%E9')) => 'https://example.org/caf%E9',
            $privacy . rawurlencode($privacy_target . '/caf%E9') => 'https://example.org/caf%E9',
            $bloomberg . base64_encode("/caf\xE9") => $bloomberg . base64_encode("/caf\xE9"),
            $bloomberg . base64_encode('/café') => 'https://www.bloomberg.com/café',
            $oup . '%2Fcaf%E9' => $oup . '%2Fcaf%E9',
            $oup . '%2F%2Fexample.org' => $oup . '%2F%2Fexample.org',
            $oup . '%2Fcaf%25E9%3Flogin%3Dtrue' => 'https://academic.oup.com/caf%E9',
            $proxy . 'https%3A%2F%2Fexample.org%2Fcaf%E9' => $proxy . 'https%3A%2F%2Fexample.org%2Fcaf%E9',
            $proxy . 'https%3A%2F%2Fexample.org%2Fcaf%2523e' => 'https://example.org/caf%23e',
            $ancestry . 'https://example.org/caf%C3%A9%23a+b' => 'https://example.org/caf%C3%A9%23a+b',
        ] as $input => $expected) {
            $template = $this->make_citation('{{cite web|title=Example|url=' . $input . '}}');
            clean_existing_urls($template, 'url');
            $this->assertSame($expected, $template->get('url'), $input);
            $this->assertTrue(mb_check_encoding($template->parsed_text(), 'UTF-8'));
        }
    }
}
