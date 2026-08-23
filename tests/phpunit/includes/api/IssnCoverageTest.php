<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class IssnCoverageTest extends testBaseClass {

    public function testUseIssnPreservesExistingWork(): void {
        $template = $this->make_citation('{{cite news|issn=0140-0460|journal=Existing work}}');

        use_issn($template);

        $this->assertNull($template->get2('newspaper'));
        $this->assertSame('Existing work', $template->get2('journal'));
    }

    public function testUseIssnIgnoresSeries(): void {
        $template = $this->make_citation('{{cite news|issn=0140-0460|series=Existing series}}');

        use_issn($template);

        $this->assertNull($template->get2('newspaper'));
    }

    public function testUseIssnIgnoresBooksWithIsbn(): void {
        $template = $this->make_citation('{{cite book|issn=0140-0460|isbn=978-0-306-40615-7}}');

        use_issn($template);

        $this->assertNull($template->get2('newspaper'));
    }

    public function testUseIssnIgnoresPlaceholderValue(): void {
        $template = $this->make_citation('{{cite news|issn=9999-9999}}');

        use_issn($template);

        $this->assertNull($template->get2('newspaper'));
    }
}
