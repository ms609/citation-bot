<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class CompactTemplateInsertionTest extends testBaseClass {
    public function testInsertedParameterDoesNotIntroduceTrailingSpace(): void {
        $template = $this->make_citation('{{cite journal|volume=5}}');
        $this->assertTrue($template->set('issue', '3'));
        $this->assertSame('{{cite journal|volume=5|issue=3}}', $template->parsed_text());
    }

    public function testCompactStyleWithMultipleExistingParameters(): void {
        $template = $this->make_citation('{{cite journal|title=Example|volume=5}}');
        $this->assertTrue($template->set('issue', '3'));
        $this->assertSame('{{cite journal|title=Example|volume=5|issue=3}}', $template->parsed_text());
    }
}
