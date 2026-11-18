<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class EditorListSafetyTest extends testBaseClass {
    public function testRecognizesEveryPopulatedEditorNameAlias(): void {
        foreach ([
            'editors', 'veditors', 'editor', 'editor1', 'editor150',
            'editor-last', 'editor-first1', 'editor-surname120',
            'editor-given1', 'editor-initials2', 'editor100-first',
            'editor1-last', 'editor2-surname', 'editor3-given',
        ] as $field) {
            $template = $this->make_citation('{{cite book|' . $field . '=Existing}}');
            $this->assertTrue($template->has_any_editor_parameter(), $field);
        }
    }

    public function testIgnoresEditorLinksMasksAndEmptyNames(): void {
        foreach ([
            'editor-link1', 'editor1-link', 'editorlink2',
            'editor-mask1', 'editor1-mask', 'editormask2',
            'display-editors',
        ] as $field) {
            $template = $this->make_citation('{{cite book|' . $field . '=Existing}}');
            $this->assertFalse($template->has_any_editor_parameter(), $field);
        }
        $this->assertFalse($this->make_citation('{{cite book|editor-last1=  }}')->has_any_editor_parameter());
    }

    public function testAtomicallyAddsValidEditorList(): void {
        $template = $this->make_citation('{{cite book|title=Proceedings}}');
        $this->assertTrue($template->add_editor_fields_atomically([
            'editor-last1' => 'Delp', 'editor-first1' => 'Edward',
            'editor-last2' => 'Wong', 'editor-first2' => 'Ping',
        ], 'crossref'));
        $this->assertSame('Delp', $template->get2('editor-last1'));
        $this->assertSame('Edward', $template->get2('editor-first1'));
        $this->assertSame('Wong', $template->get2('editor-last2'));
        $this->assertSame('Ping', $template->get2('editor-first2'));
        $this->assertTrue($template->has_any_editor_parameter());
        $this->assertTrue($template->api_has_used('crossref', ['editor-last2']));
    }

    public function testRejectedLaterEditorRollsBackNamesAndApiUsage(): void {
        $template = $this->make_citation('{{cite book|title=Proceedings}}');
        $before = $template->parsed_text();
        $this->assertFalse($template->add_editor_fields_atomically([
            'editor-last1' => 'Delp', 'editor-first1' => 'Edward',
            'editor-last2' => 'Null', 'editor-first2' => 'Jane',
        ], 'crossref'));
        $this->assertSame($before, $template->parsed_text());
        $this->assertFalse($template->api_has_used('crossref', ['editor-last1']));
    }

    public function testMatchingExistingEditorIsNotOverwritten(): void {
        $template = $this->make_citation('{{cite book|title=Proceedings}}');
        // Added after parsing, so this is not an author's original name list.
        $this->assertTrue($template->add_if_new('editor-last1', 'Smith', 'crossref'));
        $this->assertTrue($template->add_if_new('editor-first1', 'Jane', 'crossref'));
        $this->assertTrue($template->add_editor_fields_atomically([
            'editor-last1' => 'Smith', 'editor-first1' => 'Jane',
            'editor-last2' => 'Wong', 'editor-first2' => 'Ping',
        ], 'crossref'));
        $this->assertSame('Smith', $template->get2('editor-last1'));
        $this->assertSame('Jane', $template->get2('editor-first1'));
        $this->assertSame('Wong', $template->get2('editor-last2'));
    }

    public function testConflictingExistingEditorRejectsEntireBatch(): void {
        $template = $this->make_citation('{{cite book|title=Proceedings}}');
        $this->assertTrue($template->add_if_new('editor-last1', 'Other', 'crossref'));
        $before = $template->parsed_text();
        $this->assertFalse($template->add_editor_fields_atomically([
            'editor-last1' => 'Smith', 'editor-last2' => 'Wong',
        ], 'crossref'));
        $this->assertSame($before, $template->parsed_text());
    }

    public function testExistingAlternateAliasIsNotDuplicated(): void {
        $template = $this->make_citation('{{cite book|editor1-last=Existing}}');
        $before = $template->parsed_text();
        $this->assertTrue($template->has_any_editor_parameter());
        $this->assertFalse($template->add_editor_fields_atomically([
            'editor-last1' => 'Existing', 'editor-last2' => 'Wong',
        ], 'crossref'));
        $this->assertSame($before, $template->parsed_text());
    }

    public function testInvalidEditorFieldsCannotCausePartialWrites(): void {
        foreach (['editor-link2', 'editor-last31', 'author1'] as $invalid) {
            $template = $this->make_citation('{{cite book|title=Proceedings}}');
            $before = $template->parsed_text();
            $this->assertFalse($template->add_editor_fields_atomically([
                'editor-last1' => 'Smith', $invalid => 'Invalid',
            ], 'crossref'), $invalid);
            $this->assertSame($before, $template->parsed_text(), $invalid);
        }
        $this->assertFalse($this->make_citation('{{cite book}}')->add_editor_fields_atomically([]));
    }
}
