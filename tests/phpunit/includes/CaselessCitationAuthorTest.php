<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class CaselessCitationAuthorTest extends testBaseClass {
    public function testLiteralCreatorsUsePlainTextAndTheCalMattersFilter(): void {
        $url = 'https://calmatters.org/report';
        $template = $this->make_citation('{{cite web|url=' . $url . '}}');
        $item = [
            'title' => 'Example report',
            'itemType' => 'report',
            'creators' => [
                ['name' => 'Guest Commentary'],
                ['name' => "Research Council <ref>Note</ref> ''Group''"],
                ['firstName' => 'Jane', 'lastName' => 'Smith'],
            ],
        ];
        Zotero::process_zotero_response(json_encode([$item], JSON_THROW_ON_ERROR), $template, $url, 0, false, true, true);
        $this->assertSame('Research Council &#60;ref&#62;Note&#60;/ref&#62; &#0039;&#0039;Group&#0039;&#0039;', $template->get2('author1'));
        $this->assertSame('Smith', $template->get2('last2'));
        $this->assertStringNotContainsString('<ref>', $template->parsed_text());
    }

    public function testCitoidKeepsInstitutionalAuthorNamesWhole(): void {
        $url = 'https://example.test/report';
        foreach ([
            ['author' => [['', 'МИНИСТЕРСТВО ОБРАЗОВАНИЯ']]],
            ['creators' => [['fieldMode' => 1, 'lastName' => 'МИНИСТЕРСТВО ОБРАЗОВАНИЯ']]],
            ['creators' => [['name' => 'МИНИСТЕРСТВО ОБРАЗОВАНИЯ']]],
        ] as $authors) {
            $template = $this->make_citation('{{cite web|url=' . $url . '}}');
            $item = ['title' => 'Example report', 'itemType' => 'report'] + $authors;
            Zotero::process_zotero_response(json_encode([$item], JSON_THROW_ON_ERROR), $template, $url, 0, false, true, true);
            $this->assertSame('МИНИСТЕРСТВО ОБРАЗОВАНИЯ', $template->get2('author1'));
            $this->assertNull($template->get2('last1'));
        }
    }

    public function testCitoidKeepsExplicitOrganizationWithCommaAndStructuredPerson(): void {
        $url = 'https://example.test/report';
        $template = $this->make_citation('{{cite web|url=' . $url . '}}');
        $item = [
            'title' => 'Example report',
            'itemType' => 'report',
            'creators' => [
                ['fieldMode' => 1, 'lastName' => 'National Research Council, Division of Sciences'],
                ['firstName' => 'Jane', 'lastName' => 'Smith'],
            ],
        ];
        Zotero::process_zotero_response(json_encode([$item], JSON_THROW_ON_ERROR), $template, $url, 0, false, true, true);
        $this->assertSame('National Research Council, Division of Sciences', $template->get2('author1'));
        $this->assertNull($template->get2('last1'));
        $this->assertSame('Smith', $template->get2('last2'));
        $this->assertSame('Jane', $template->get2('first2'));
    }

    public function testCitoidLiteralCreatorsStillUseAuthorFilters(): void {
        $url = 'https://example.test/report';
        $template = $this->make_citation('{{cite web|url=' . $url . '}}');
        $item = [
            'title' => 'Example report',
            'itemType' => 'report',
            'rights' => 'Copyright Northstar Research Trust',
            'creators' => [
                ['name' => 'Staff Reporter'],
                ['name' => 'unknown'],
                ['name' => 'BBC News'],
                ['fieldMode' => 1, 'lastName' => 'Northstar Research Trust'],
                ['name' => 'Jane Smith jsmith@example.org @jsmith'],
                ['firstName' => 'John', 'lastName' => 'Doe'],
            ],
        ];
        Zotero::process_zotero_response(json_encode([$item], JSON_THROW_ON_ERROR), $template, $url, 0, false, true, true);
        $this->assertSame('Northstar Research Trust', $template->get2('publisher'));
        $this->assertSame('Jane Smith', $template->get2('author1'));
        $this->assertSame('Doe', $template->get2('last2'));
        $this->assertSame('John', $template->get2('first2'));
    }

    public function testUnstructuredCaselessNamesKeepTheirTextAndOrder(): void {
        foreach (['علي حسن', 'דן כהן', 'สม ชาย', 'राम शर्मा', '山田 太郎'] as $name) {
            $this->assertSame($name, format_author($name));
            $template = $this->make_citation('{{cite journal|title=Example article}}');
            $template->validate_and_add('author1', $name, '', '', false);
            $this->assertSame($name, $template->get('author1'));
        }
        $this->assertSame('明', clean_up_first_names('明'));
        $this->assertSame('İlhan', format_surname('İlhan'));
        $this->assertTrue(is_initials('ŁŻ'));
        $this->assertTrue(is_initials('ИП'));
        $this->assertSame('Smith, J', format_author('J. Smith'));
    }
}
