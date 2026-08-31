<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class PubMedStructureTest extends testBaseClass {
    public function testCollectiveNameComesFromNameAttribute(): void {
        $xml = parse_entrez_xml_response(
            '<eSummaryResult><DocSum><Item Name="AuthorList" Type="List">' .
            '<Item Name="Author" Type="String">Smith J</Item>' .
            '<Item Name="CollectiveName" Type="String">Example Study Group</Item>' .
            '</Item></DocSum></eSummaryResult>'
        );

        $this->assertNotNull($xml);
        $author_list = $xml->DocSum->Item[0];
        $this->assertSame('Author', pubmed_item_name($author_list->Item[0]));
        $this->assertSame('CollectiveName', pubmed_item_name($author_list->Item[1]));
    }

    public function testEmptyFirstDocSumDoesNotDefineWholeBatch(): void {
        $xml = parse_entrez_xml_response(
            '<eSummaryResult>' .
            '<DocSum><Id>1</Id></DocSum>' .
            '<DocSum><Id>2</Id><Item Name="Title" Type="String">Useful record</Item></DocSum>' .
            '</eSummaryResult>'
        );

        $this->assertNotNull($xml);
        $this->assertFalse(pubmed_document_has_items($xml->DocSum[0]));
        $this->assertTrue(pubmed_document_has_items($xml->DocSum[1]));
    }
}
