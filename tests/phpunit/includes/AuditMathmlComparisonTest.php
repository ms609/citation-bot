<?php
declare(strict_types=1);

/**
 * A less-than sign inside MathML must not truncate the formula.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditMathmlComparisonTest extends testBaseClass {

    public function testLessThanDoesNotTruncate(): void {
        $this->assertSame('p<0.05', convert_mathml_to_latex('<mi>p</mi><mo>&lt;</mo><mn>0.05</mn>'));
    }

    public function testGreaterThanIsKept(): void {
        $this->assertSame('n>3', convert_mathml_to_latex('<mi>n</mi><mo>&gt;</mo><mn>3</mn>'));
    }

    public function testBothOperatorsInOneFormula(): void {
        $this->assertSame('0<x<1', convert_mathml_to_latex('<mn>0</mn><mo>&lt;</mo><mi>x</mi><mo>&lt;</mo><mn>1</mn>'));
    }

    public function testNamespacedTagsAreStillRemoved(): void {
        $this->assertSame('p<0.05', convert_mathml_to_latex('<mml:mrow><mml:mi>p</mml:mi><mml:mo>&lt;</mml:mo><mml:mn>0.05</mml:mn></mml:mrow>'));
    }
}
