<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/** Offline coverage of the two conference coauthor restoration paths. */
final class ConferenceAuthorPlaceholderTest extends testBaseClass {
    public function testMixedAuthorAliasesAreRestoredInOriginalOrder(): void {
        $names = [
            'first' => 'Alice', 'last' => 'Smith',
            'first1' => 'Bob', 'last1' => 'Jones',
            'author1' => 'C. Writer', 'author' => 'D. Writer',
        ];
        $template = $this->make_citation('{{cite journal|coauthors=E. Colleague}}');
        foreach ($names as $field => $value) {
            $template->set($field, $value);
            $template->rename($field, 'CITATION_BOT_PLACEHOLDER_' . $field);
        }
        $template->rename('coauthors', 'CITATION_BOT_PLACEHOLDER_coauthors');
        conference_restore_author_placeholders($template);
        foreach ($names as $field => $value) {
            $this->assertSame($value, $template->get2($field));
            $this->assertNull($template->get2('CITATION_BOT_PLACEHOLDER_' . $field));
        }
        $this->assertSame('E. Colleague', $template->get2('coauthors'));
        $this->assertNull($template->get2('CITATION_BOT_PLACEHOLDER_coauthors'));
    }

    public function testMissingAuthorAliasesStayAbsent(): void {
        $template = $this->make_citation('{{cite journal|coauthors=Only colleague|first=Primary}}');
        $template->rename('first', 'CITATION_BOT_PLACEHOLDER_first');
        $template->rename('coauthors', 'CITATION_BOT_PLACEHOLDER_coauthors');
        conference_restore_author_placeholders($template);
        $this->assertSame('Primary', $template->get2('first'));
        $this->assertSame('Only colleague', $template->get2('coauthors'));
        $this->assertNull($template->get2('last1'));
        $this->assertNull($template->get2('author1'));
    }
}
