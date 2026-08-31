<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class AuthorSuffixTest extends testBaseClass {
    public function testCommaSeparatedJuniorSuffixIsPreserved(): void {
        $this->assertSame(
            'Smith, John Jr.',
            format_author('Smith, John, Jr.')
        );
    }

    public function testSpaceSeparatedJuniorSuffixIsPreserved(): void {
        $this->assertSame(
            'Smith, John Jr.',
            format_author('John Smith Jr.')
        );
    }

    public function testOrdinalSuffixIsPreserved(): void {
        $this->assertSame(
            'Smith, John 3rd',
            format_author('Smith, John, 3rd')
        );
    }
}
