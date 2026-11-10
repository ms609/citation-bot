<?php
declare(strict_types=1);

/**
 * An arxiv.org link without a usable identifier must not turn cite web into cite arxiv.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditArxivUrlWithoutIdTest extends testBaseClass {

    public function testTemplateTypeIsKeptWhenNoIdentifierIsFound(): void {
        $template = $this->make_citation('{{cite web|title=A listing|url=https://arxiv.org/abs/recent-submissions}}');
        $template->get_identifiers_from_url();
        $this->assertSame('cite web', $template->wikiname());
        $this->assertNull($template->get2('arxiv'));
        $this->assertNull($template->get2('eprint'));
    }
}
