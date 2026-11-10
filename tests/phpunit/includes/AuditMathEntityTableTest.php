<?php
declare(strict_types=1);

/**
 * Two MathML entities mapped to the wrong LaTeX.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditMathEntityTableTest extends testBaseClass {

    public function testEmptySetIsSpelledCorrectly(): void {
        $this->assertSame('\\emptyset', MML_TAGS['&empty;']);
        $this->assertSame('A=\\emptyset', convert_mathml_to_latex('<mi>A</mi><mo>=</mo><mi>&empty;</mi>'));
    }

    public function testNotEqualIsNotSetMembership(): void {
        $this->assertSame('\\neq', MML_TAGS['&NotEqual;']);
        $this->assertSame('1\\neq2', convert_mathml_to_latex('<mn>1</mn><mo>&NotEqual;</mo><mn>2</mn>'));
    }
}
