<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class EncyclopediaEntryTitleTest extends testBaseClass {
    public function testMetadataDoesNotDuplicateAnExistingEntry(): void {
        $citation = $this->make_citation('{{cite encyclopedia|entry=Blegen, Judith|encyclopedia=Grove Music Online}}');
        $this->assertFalse($citation->add_if_new('title', 'Blegen, Judith'));
        $this->assertSame('Blegen, Judith', $citation->get('entry'));
        $this->assertSame('', $citation->get('title'));
    }

    public function testMetadataCanSupplyAMissingEntryTitle(): void {
        $citation = $this->make_citation('{{cite encyclopedia|encyclopedia=Grove Music Online}}');
        $this->assertTrue($citation->add_if_new('title', 'Blegen, Judith'));
    }
}
