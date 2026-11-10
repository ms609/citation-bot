<?php
declare(strict_types=1);

/**
 * An ISBN-10 with a wrong check digit is not turned into a valid-looking ISBN-13.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditIsbnInvalidNotConvertedTest extends testBaseClass {

    public function testValidIsbn10IsConverted(): void {
        $this->assertSame('978-0306406157', changeisbn10Toisbn13('0306406152', 2020));
        $this->assertSame('978-0-306-40615-7', changeisbn10Toisbn13('0-306-40615-2', 2020));
    }

    public function testValidIsbn10EndingInXIsConverted(): void {
        $this->assertSame('978-0804429573', changeisbn10Toisbn13('080442957X', 2020));
    }

    public function testInvalidIsbn10IsLeftAlone(): void {
        $this->assertSame('0306406153', changeisbn10Toisbn13('0306406153', 2020));
        $this->assertSame('0-306-40615-3', changeisbn10Toisbn13('0-306-40615-3', 2020));
    }

    public function testInvalidIsbn10InTemplateIsLeftAlone(): void {
        $template = $this->make_citation('{{cite book|title=A title|year=2020|isbn=0306406153}}');
        $template->tidy_parameter('isbn');
        $this->assertSame('0306406153', $template->get2('isbn'));
    }
}
