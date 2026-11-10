<?php
declare(strict_types=1);

/**
 * A LaTeX command produced from a symbol must not merge with a following letter.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditMathCommandBoundaryTest extends testBaseClass {

    public function testUnicodeGreekFollowedByLetter(): void {
        $this->assertSame('10\\mu{}m', convert_mathml_to_latex('<mn>10</mn><mi>μ</mi><mi>m</mi>'));
    }

    public function testEntityGreekFollowedByLetter(): void {
        $this->assertSame('10\\mu{}m', convert_mathml_to_latex('<mn>10</mn><mi>&mu;</mi><mi>m</mi>'));
    }

    public function testDegreeFollowedByLetter(): void {
        $this->assertSame('25^\\circ{}C', convert_mathml_to_latex('<mn>25</mn><mo>°</mo><mi>C</mi>'));
    }

    public function testCommandsNotFollowedByLetterAreUnchanged(): void {
        $this->assertSame('\\alpha\\times\\beta', convert_mathml_to_latex('<mi>&alpha;</mi><mo>&times;</mo><mi>&beta;</mi>'));
        $this->assertSame('\\alpha+2', convert_mathml_to_latex('<mi>α</mi><mo>+</mo><mn>2</mn>'));
    }

    public function testHelperLeavesPlainReplacementsAlone(): void {
        $this->assertSame('o and \\pi{}r', mathml_replace_symbols(['ο' => 'o', 'π' => '\\pi'], 'ο and πr'));
        $this->assertSame('', mathml_replace_symbols(['π' => '\\pi'], ''));
    }
}
