<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class mathToolsTest extends testBaseClass {

    public function testMathMLIsotopeNotation(): void {
        // Test isotope notation with mmultiscripts: ^{67}Ni
        $text_mml = '<math><mmultiscripts>Ni<mprescripts/><none/>67</mmultiscripts></math>';
        $expected = '<math>^{67}\\mathrm{Ni}</math>';
        $this->assertSame($expected, wikify_external_text($text_mml));
    }

    public function testMathMLIsotopeNotationWithNamespace(): void {
        // Test with mml: namespace prefix
        $text_mml = '<mml:math><mml:mmultiscripts>Ni<mml:mprescripts/><mml:none/>67</mml:mmultiscripts></mml:math>';
        $expected = '<math>^{67}\\mathrm{Ni}</math>';
        $this->assertSame($expected, wikify_external_text($text_mml));
    }

    public function testMathMLIsotopeWithoutNoneTag(): void {
        // mmultiscripts with a prescript but no <none/> tag should not be
        // treated as a left-superscript: falls through to the base element.
        $text_mml = '<math><mmultiscripts>Ni<mprescripts/>67</mmultiscripts></math>';
        $result = wikify_external_text($text_mml);
        $this->assertSame('<math>Ni</math>', $result);
    }

    public function testMathMLIsotopeWithNoneOnRight(): void {
        // <none/> on the right (mass number) side must not be treated as a
        // left superscript: preg_split count is 2 but first part is non-empty.
        $text_mml = '<math><mmultiscripts>Ni<mprescripts/>67<none/></mmultiscripts></math>';
        $result = wikify_external_text($text_mml);
        $this->assertSame('<math>Ni</math>', $result);
    }

    public function testMathMLIsotopeWithNonChemicalBase(): void {
        $mathml = '<mmultiscripts>XYZ<mprescripts/><none/>67</mmultiscripts>';
        $this->assertSame('^{67}XYZ', convert_mathml_to_latex($mathml));
    }

    public function testMathMLSuperscript(): void {
        // Test simple superscript: x^{2}
        $text_mml = '<math><msup><mi>x</mi><mn>2</mn></msup></math>';
        $result = wikify_external_text($text_mml);
        $this->assertSame('<math>x^{2}</math>', $result);
    }

    public function testMathMLSubscript(): void {
        // Test simple subscript: H_{2}
        $text_mml = '<math><msub><mi>H</mi><mn>2</mn></msub></math>';
        $result = wikify_external_text($text_mml);
        $this->assertSame('<math>H_{2}</math>', $result);
    }

    public function testMathMLSubSuperscript(): void {
        // Test subscript and superscript: x_{1}^{2}
        $text_mml = '<math><msubsup><mi>x</mi><mn>1</mn><mn>2</mn></msubsup></math>';
        $result = wikify_external_text($text_mml);
        $this->assertSame('<math>x_{1}^{2}</math>', $result);
    }

    public function testMathMLSubSuperscriptWithIdentifiers(): void {
        // msubsup should handle <mi> children, consistent with msub/msup fixes.
        // e.g. R_{K}^{*} from <msubsup><mi>R</mi><mi>K</mi><mi>*</mi></msubsup>
        $text_mml = '<math><msubsup><mi>R</mi><mi>K</mi><mi>*</mi></msubsup></math>';
        $result = wikify_external_text($text_mml);
        $this->assertSame('<math>R_{K}^{*}</math>', $result);
    }

    public function testMathMLRoot(): void {
        // Test nth root: \sqrt[3]{x}
        $text_mml = '<math><mroot><mi>x</mi><mn>3</mn></mroot></math>';
        $result = wikify_external_text($text_mml);
        $this->assertStringContainsString('sqrt', $result);
        $this->assertStringContainsString('x', $result);
        $this->assertStringContainsString('3', $result);
    }

    public function testMathMLFraction(): void {
        $mathml = '<mfrac><mn>1</mn><mn>2</mn></mfrac>';
        $this->assertSame('\\frac{1}{2}', convert_mathml_to_latex($mathml));
    }

    public function testMathMLUnderWithExpression(): void {
        $mathml = '<munder><mo>lim</mo><mrow><mi>x</mi><mo>→</mo><mn>0</mn></mrow></munder>';
        $this->assertSame('\\underset{x\\rightarrow{}0}{lim}', convert_mathml_to_latex($mathml));
    }

    public function testMathMLUnderWithoutExpressionReturnsBase(): void {
        $mathml = '<munder><mo>lim</mo></munder>';
        $this->assertSame('lim', convert_mathml_to_latex($mathml));
    }

    public function testMathMLUnderFallbackStripsUnknownMarkup(): void {
        $mathml = '<munder><semantics>limit</semantics></munder>';
        $this->assertSame('limit', convert_mathml_to_latex($mathml));
    }

    public function testMathMLUnderOverFallbackPreservesAvailableTerms(): void {
        $mathml = '<munderover><mo>∑</mo><mn>0</mn></munderover>';
        $this->assertSame('\\sum0', convert_mathml_to_latex($mathml));
    }

    public function testMathMLUnderOver(): void {
        // Test underover (sum notation): \sum_{0}^{n}
        $text_mml = '<math><munderover><mo>∑</mo><mn>0</mn><mi>n</mi></munderover></math>';
        $result = wikify_external_text($text_mml);
        $this->assertStringContainsString('_', $result);
        $this->assertStringContainsString('^', $result);
        $this->assertStringContainsString('0', $result);
        $this->assertStringContainsString('n', $result);
    }

    public function testUnicodeGreekConversion(): void {
        // Simulate processing as in convert_mathml_to_latex
        // You can use the UNICODE_MATH_MAP directly, since it's available via constants/math.php
        $input = '{\displaystyle γ + π = α}';
        $expected = '{\displaystyle \gamma + \pi = \alpha}';
        $output = str_replace(array_keys(UNICODE_MATH_MAP), array_values(UNICODE_MATH_MAP), $input);
        $this->assertSame($expected, $output, "Unicode Greek letters should be converted to LaTeX macros.");
    }

    public function testArrowNotMergedWithFollowingLetter(): void {
        // Regression test: b→sℓℓ was producing \rightarrows which is an unknown LaTeX command.
        // The {} after \rightarrow terminates the command name so it never merges with the next letter.
        $text = '<math>b→sℓℓ</math>';
        $result = wikify_external_text($text);
        $this->assertStringNotContainsString('\rightarrows', $result, "\\rightarrows is not a valid LaTeX command");
        $this->assertSame('<math>b\rightarrow{}s\ell\ell</math>', $result);
    }

    public function testArrowBetweenParticleSymbols(): void {
        // Regression test: B+→K+ℓ+ℓ- was producing \rightarrowK which is an unknown LaTeX command.
        $text = '<math>B+→K+ℓ+ℓ-</math>';
        $result = wikify_external_text($text);
        $this->assertStringNotContainsString('\rightarrowK', $result, "\\rightarrowK is not a valid LaTeX command");
        $this->assertSame('<math>B+\rightarrow{}K+\ell+\ell-</math>', $result);
    }

    public function testMathMLSubscriptWithIdentifier(): void {
        // Regression test: <msub><mi>R</mi><mi>K</mi></msub> was producing RK (losing the subscript)
        // because the msub pattern only matched <mn> (number) subscripts, not <mi> (identifier) ones.
        $text = '<math><msub><mi>R</mi><mi>K</mi></msub></math>';
        $result = wikify_external_text($text);
        $this->assertSame('<math>R_{K}</math>', $result);
    }

    public function testMathMLSuperscriptWithIdentifier(): void {
        // <msup> should handle <mi> superscripts, e.g. x^{n} (variable to variable power)
        $text = '<math><msup><mi>x</mi><mi>n</mi></msup></math>';
        $result = wikify_external_text($text);
        $this->assertSame('<math>x^{n}</math>', $result);
    }
}
