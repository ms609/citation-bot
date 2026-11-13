<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class DoiUnicodeCandidateTest extends testBaseClass {
    public function testDoiCandidateKeepsLiteralAndEscapedPlus(): void {
        $this->assertTrue(uopz_set_return('doi_works', static fn (string $doi): bool => $doi === '10.1234/café+appendix', true));
        try {
            foreach (['10.1234/café+appendix', '10%2E1234%2Fcaf%C3%A9%2Bappendix'] as $doi) {
                $template = $this->make_citation('{{cite journal|title=Example|note=Legacy caf%E9; Identifier ' . $doi . '}}');
                $template->prepare();
                $this->assertSame('10.1234/café+appendix', $template->get('doi'));
            }
        } finally {
            $this->assertTrue(uopz_unset_return('doi_works'));
        }
        $this->assertFalse(doi_valid('10.١٢٣٤/café'));
        $this->assertFalse(doi_valid('10.1234.٣/café'));
        $this->assertTrue(doi_valid('10.1234.3/café'));
    }
}
