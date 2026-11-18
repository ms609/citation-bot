<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class SpieVolumeEditorDataTest extends testBaseClass {
    private const string DOI = '10.1117/12.703370';
    private const string VOLUME_TITLE = 'Security, Steganography, and Watermarking of Multimedia Contents IX';

    private function volumeCitation(string $extra = ''): Template {
        return $this->make_citation('{{cite book|doi=' . self::DOI .
            '|title=' . self::VOLUME_TITLE . '|volume=6505|year=2007' . $extra . '}}');
    }

    public function testVerifiedVolumeMetadataHasIndependentSourcesAndSuffix(): void {
        $record = spie_volume_editor_data()['6505:2007'];
        $this->assertCount(2, $record['sources']);
        $this->assertSame('Delp III', $record['editors'][0]['family']);
        $this->assertSame('Wong', $record['editors'][1]['family']);
    }

    public function testAddsBothEditorsFromStaticVolumeRoster(): void {
        $template = $this->volumeCitation('|series=Proceedings of SPIE');
        $this->assertTrue(spie_add_verified_volume_editors($template, self::DOI));
        $this->assertSame('Delp III', $template->get2('editor-last1'));
        $this->assertSame('Edward J.', $template->get2('editor-first1'));
        $this->assertSame('Wong', $template->get2('editor-last2'));
        $this->assertSame('Ping Wah', $template->get2('editor-first2'));
        $this->assertFalse(spie_add_verified_volume_editors($template, self::DOI));
    }

    public function testUsesDateWhenYearIsAbsent(): void {
        $template = $this->make_citation('{{cite book|doi=' . self::DOI .
            '|title=' . self::VOLUME_TITLE . '|volume=6505|date=2007-02-27}}');
        $this->assertTrue(spie_add_verified_volume_editors($template, self::DOI));
        $this->assertSame('Wong', $template->get2('editor-last2'));
    }

    public function testRejectsWrongVolumeYearTitleDoiAndSeries(): void {
        $variants = [
            ['6506', '2007', self::VOLUME_TITLE, self::DOI, ''],
            ['6505', '2008', self::VOLUME_TITLE, self::DOI, ''],
            ['6505', '2007', 'Another conference', self::DOI, ''],
            ['6505', '2007', self::VOLUME_TITLE, '10.1234/unrelated', ''],
            ['6505', '2007', self::VOLUME_TITLE, self::DOI, '|series=Other Proceedings'],
        ];
        foreach ($variants as [$volume, $year, $title, $doi, $extra]) {
            $template = $this->make_citation('{{cite book|doi=' . $doi .
                '|title=' . $title . '|volume=' . $volume . '|year=' . $year . $extra . '}}');
            $this->assertFalse(spie_add_verified_volume_editors($template, $doi), $title);
            $this->assertNull($template->get2('editor-last1'));
        }
    }

    public function testPreservesExistingEditorsIncludingAlternativeAliasesAndHighNumbers(): void {
        foreach (['editor-last1=Other', 'editor1-last=Other', 'editor100-last=Other',
                  'editor-surname120=Other', 'editor2-forename=Other',
                  'editor-initials2=Other', 'editor3-initials=Other',
                  'editor=Other', 'editors=Other', 'veditors=Other', 'others=Other'] as $field) {
            $template = $this->volumeCitation('|' . $field);
            $original = $template->parsed_text();
            $this->assertFalse(spie_add_verified_volume_editors($template, self::DOI), $field);
            $this->assertSame($original, $template->parsed_text(), $field);
        }
    }

    public function testBlankEditorFieldsDoNotBlockEnrichment(): void {
        foreach (['editor-last1=', 'editor-last1=   ',
                  "editor-last1=\n ", 'editor-last1=|unused=value',
                  'editor2-forename=', 'veditors=', 'others='] as $field) {
            $template = $this->volumeCitation('|' . $field);
            $this->assertFalse(spie_citation_has_editors($template), $field);
            $this->assertTrue(spie_add_verified_volume_editors($template, self::DOI), $field);
            $this->assertSame('Delp III', $template->get2('editor-last1'), $field);
            $this->assertSame('Wong', $template->get2('editor-last2'), $field);
        }
    }

    public function testEditorDetectionDoesNotConvertToVanc(): void {
        $template = $this->volumeCitation('|editor-last1=|editor-first1=');
        $previousStyle = Template::$name_list_style;
        Template::$name_list_style = VancStyle::NAME_LIST_STYLE_VANC;
        try {
            // A serialized-text check would convert this newly added author to vauthors.
            $this->assertTrue($template->add_if_new('last1', 'Smith'));
            $this->assertFalse(spie_citation_has_editors($template));
            $this->assertSame('Smith', $template->get2('last1'));
            $this->assertNull($template->get2('vauthors'));
            $this->assertNull($template->get2('veditors'));
            $this->assertTrue(spie_add_verified_volume_editors($template, self::DOI));
            $this->assertSame('Smith', $template->get2('last1'));
            $this->assertNull($template->get2('vauthors'));
            $this->assertSame('Delp III', $template->get2('editor-last1'));
            $this->assertSame('Wong', $template->get2('editor-last2'));
            $this->assertNull($template->get2('veditors'));
        } finally {
            Template::$name_list_style = $previousStyle;
        }
    }

    public function testEveryLocalVolumeRosterIsValid(): void {
        $records = spie_volume_editor_data();
        $this->assertNotEmpty($records);
        foreach ($records as $key => $record) {
            [$volume, $year] = explode(':', $key, 2);
            $fields = spie_verified_volume_editor_fields($record['title'], $volume, $year);
            $this->assertCount(2 * count($record['editors']), $fields, $key);
        }
        foreach (['5029:2003', '5367:2004', '5744:2005'] as $key) {
            $this->assertSame('Galloway Jr.', $records[$key]['editors'][0]['family'], $key);
        }
        $this->assertSame('Delp III', $records['7254:2009']['editors'][0]['family']);
        $this->assertSame('Delp III', $records['7541:2010']['editors'][3]['family']);
    }

    public function testCorrectedSpieVolumeYear(): void {
        $records = spie_volume_editor_data();
        $this->assertArrayHasKey('12468:2023', $records);
        $this->assertArrayNotHasKey('12468:2022', $records);
    }

    public function testNoPartialEditorWritesWhenDataIsInvalid(): void {
        $this->assertSame([], spie_verified_volume_editor_fields(
            'Other title', '6505', '2007'));
        $this->assertSame([], spie_verified_volume_editor_fields(
            self::VOLUME_TITLE, '6505', '2008'));
        $this->assertSame([], spie_verified_volume_editor_fields(
            self::VOLUME_TITLE, 'not-a-volume', '2007'));
    }

    public function testAtomicEditorAdditionDoesNotLeavePartialRoster(): void {
        $template = $this->volumeCitation();
        $before = $template->parsed_text();
        $this->assertFalse($template->add_editor_fields_atomically([
            'editor-last1' => 'Delp III', 'editor-first1' => 'Edward J.',
            'editor-last2' => 'Null', 'editor-first2' => 'Ping Wah',
        ], ''));
        $this->assertSame($before, $template->parsed_text());
    }

    public function testExpandByDoiUsesOfflineRosterWithNoCrossrefEditors(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to prevent external DOI requests');
        }
        $stubs = [
            'doi_works' => true,
            'query_crossref' => null,
            'expand_doi_with_dx' => null,
        ];
        foreach ($stubs as $name => $value) {
            $this->assertTrue(uopz_set_return($name, $value));
        }
        try {
            $template = $this->volumeCitation('|series=Proceedings of SPIE');
            expand_by_doi($template);
            $this->assertSame('Delp III', $template->get2('editor-last1'));
            $this->assertSame('Wong', $template->get2('editor-last2'));
        } finally {
            foreach (array_keys($stubs) as $name) {
                $this->assertTrue(uopz_unset_return($name));
            }
        }
    }
}
