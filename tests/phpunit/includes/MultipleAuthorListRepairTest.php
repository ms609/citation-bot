<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class MultipleAuthorListRepairTest extends testBaseClass {
    public function testSurnameInitialsSeparatedByCommasAndConjunctions(): void {
        $expected = 'Smith, J.; Jones, K.; Brown, L.';
        $this->assertSame($expected, format_multiple_authors('Smith J, Jones K and Brown L'));
        $this->assertSame($expected, format_multiple_authors('Smith J, Jones K, & Brown L'));
        $this->assertSame($expected, format_multiple_authors('Smith J; Jones K; Brown L'));
    }

    public function testCommaWithinSurnameInitialPairsIsNotAnAuthorBoundary(): void {
        $this->assertSame(
            'Smith, J.; Jones, K.; Brown, L.',
            format_multiple_authors('Smith, J., Jones, K., and Brown, L.')
        );
    }

    public function testInitialBeforeSurnameIsSupported(): void {
        $this->assertSame('Smith, J.; Jones, K.', format_multiple_authors('J. Smith and K. Jones'));
    }

    public function testAmbiguousInputRetainsLegacyFallback(): void {
        $this->assertNull(split_explicit_initial_author_list('John,Bob,Kim,Billy'));
        $this->assertSame('John, Bob; Kim, Billy', format_multiple_authors('John,Bob,Kim,Billy'));
        $this->assertNull(split_explicit_initial_author_list('Smith AB, Jones CD, Brown'));
        $this->assertSame('A.', format_multiple_authors('a'));
    }

    public function testMalformedUtf8FallsBackWithoutException(): void {
        $this->assertNull(split_explicit_initial_author_list("Sm\xFFith J, Jones K"));
        $this->assertIsString(format_multiple_authors("Sm\xFFith J, Jones K"));
    }
}
