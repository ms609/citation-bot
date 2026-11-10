<?php
declare(strict_types=1);

/**
 * Text between two formulas is sanitized like any other text.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditSanitizeStringTwoFormulasTest extends testBaseClass {

    public function testTextBetweenTwoFormulasIsEscaped(): void {
        $this->assertSame(
            '<math>a</math> in &#91;X&#93; &#124; Y and <math>b</math>',
            sanitize_string('<math>a</math> in [X] | Y and <math>b</math>')
        );
    }

    public function testFormulaContentsAreStillProtected(): void {
        $this->assertSame(
            '<math>x_{1}|y</math> and <math>[a]</math>',
            sanitize_string('<math>x_{1}|y</math> and <math>[a]</math>')
        );
    }

    public function testSingleFormulaIsUnchanged(): void {
        $this->assertSame('A &#91;note&#93; <math>a|b</math>', sanitize_string('A [note] <math>a|b</math>'));
    }
}
