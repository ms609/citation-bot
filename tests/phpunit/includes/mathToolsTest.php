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


    public function testMultiscriptFallbackReturnsTrimmedBaseWhenPrescriptShapeIsUnsupported(): void {
        $mathml = '<mmultiscripts>  Carbon  <mprescripts/>12<none/></mmultiscripts>';

        $this->assertSame('Carbon', convert_mathml_to_latex($mathml));
    }

    public function testMultiscriptChemicalElementWithNestedMassTag(): void {
        $mathml = '<mmultiscripts>U<mprescripts/><none/><mn>238</mn></mmultiscripts>';

        $this->assertSame('^{238}\\mathrm{U}', convert_mathml_to_latex($mathml));
    }

    public function testMultiscriptNonChemicalBaseWithNestedMassTag(): void {
        $mathml = '<mmultiscripts>particle<mprescripts/><none/><mn>7</mn></mmultiscripts>';

        $this->assertSame('^{7}particle', convert_mathml_to_latex($mathml));
    }

    public function testSuperscriptTrimsInnerWhitespace(): void {
        $mathml = "<msup>\n<mi> x </mi>\n<mn>  10 </mn>\n</msup>";

        $this->assertSame('x^{10}', convert_mathml_to_latex($mathml));
    }

    public function testSubscriptTrimsInnerWhitespace(): void {
        $mathml = "<msub>\n<mi> H </mi>\n<mi> aq </mi>\n</msub>";

        $this->assertSame('H_{aq}', convert_mathml_to_latex($mathml));
    }

    public function testSubSuperscriptSupportsMixedMiAndMnChildren(): void {
        $mathml = '<msubsup><mi>A</mi><mn>1</mn><mi>prime</mi></msubsup>';

        $this->assertSame('A_{1}^{prime}', convert_mathml_to_latex($mathml));
    }

    public function testFractionTrimsNumeratorAndDenominator(): void {
        $mathml = "<mfrac>\n<mi> a + b </mi>\n<mn> 2 </mn>\n</mfrac>";

        $this->assertSame('\\frac{a + b}{2}', convert_mathml_to_latex($mathml));
    }

    public function testRootTrimsRadicandAndIndex(): void {
        $mathml = "<mroot>\n<mi> x + 1 </mi>\n<mn> 4 </mn>\n</mroot>";

        $this->assertSame('\\sqrt[4]{x + 1}', convert_mathml_to_latex($mathml));
    }

    public function testUnderExtractsBaseFromTextBeforeFirstMathElement(): void {
        $mathml = '<munder>pre<mo>lim</mo><mrow><mi>x</mi><mo>→</mo><mn>∞</mn></mrow></munder>';

        $this->assertSame(
            '\\underset{x\\rightarrow{}\\infty}{prelim}',
            convert_mathml_to_latex($mathml)
        );
    }

    public function testUnderWithEmptyTrailingContentReturnsBaseOnly(): void {
        $mathml = '<munder>prefix<mi>x</mi></munder>';

        $this->assertSame('prefixx', convert_mathml_to_latex($mathml));
    }

    public function testUnderFallbackPreservesPlainTextFromNestedUnknownTags(): void {
        $mathml = '<munder><foo>alpha</foo><bar>beta</bar></munder>';

        $this->assertSame('alphabeta', convert_mathml_to_latex($mathml));
    }

    public function testUnderOverSupportsThreeIdentifierChildren(): void {
        $mathml = '<munderover><mi>f</mi><mi>a</mi><mi>b</mi></munderover>';

        $this->assertSame('f_{a}^{b}', convert_mathml_to_latex($mathml));
    }

    public function testUnderOverTrimsEachComponent(): void {
        $mathml = '<munderover><mo> ∫ </mo><mn> 0 </mn><mi> ∞ </mi></munderover>';

        $this->assertSame('\\int_{0}^{\\infty}', convert_mathml_to_latex($mathml));
    }

    public function testUnderOverWithFourMathChildrenFallsBackToFlattenedText(): void {
        $mathml = '<munderover><mi>a</mi><mi>b</mi><mi>c</mi><mi>d</mi></munderover>';

        $this->assertSame('abcd', convert_mathml_to_latex($mathml));
    }

    public function testUnderOverWithOneMathChildFallsBackToFlattenedText(): void {
        $mathml = '<munderover><mi>x</mi></munderover>';

        $this->assertSame('x', convert_mathml_to_latex($mathml));
    }

    public function testSimpleSquareRootTagConversion(): void {
        $mathml = '<msqrt><mi>x</mi><mo>+</mo><mn>1</mn></msqrt>';

        $this->assertSame('\\sqrt{x+1}', convert_mathml_to_latex($mathml));
    }

    public function testFencedTagConversion(): void {
        $mathml = '<mfenced><mi>x</mi><mo>+</mo><mi>y</mi></mfenced>';

        $this->assertSame('\\left(x+y\\right)', convert_mathml_to_latex($mathml));
    }

    public function testBothSupportedMspaceSpellings(): void {
        $this->assertSame(
            'a\\,b\\,c',
            convert_mathml_to_latex('<mi>a</mi><mspace/><mi>b</mi><mspace /><mi>c</mi>')
        );
    }

    public function testInvisibleTimesEntityIsRemoved(): void {
        $mathml = '<mi>x</mi><mo>&InvisibleTimes;</mo><mi>y</mi>';

        $this->assertSame('xy', convert_mathml_to_latex($mathml));
    }

    public function testNamedArrowEntityKeepsCommandBoundary(): void {
        $mathml = '<mi>x</mi><mo>&rarr;</mo><mi>speed</mi>';

        $this->assertSame('x\\rightarrow{}speed', convert_mathml_to_latex($mathml));
    }

    public function testUnicodeMinusAndMultiplicationAreConverted(): void {
        $mathml = '<mrow><mn>5</mn><mo>−</mo><mn>2</mn><mo>×</mo><mi>x</mi></mrow>';

        $this->assertSame('5-2\\timesx', convert_mathml_to_latex($mathml));
    }

    public function testUnicodeSetSymbolsAreConverted(): void {
        $mathml = '<mrow><mi>A</mi><mo>⊂</mo><mi>ℝ</mi><mo>∩</mo><mi>ℤ</mi></mrow>';

        $this->assertSame(
            'A\\subset\\mathbb{R}\\cap\\mathbb{Z}',
            convert_mathml_to_latex($mathml)
        );
    }

    public function testUnicodeIntegralAndInfinityAreConverted(): void {
        $mathml = '<mrow><mo>∫</mo><mn>0</mn><mo>→</mo><mo>∞</mo></mrow>';

        $this->assertSame(
            '\\int0\\rightarrow{}\\infty',
            convert_mathml_to_latex($mathml)
        );
    }

    public function testResidualMathRowTagsAreStripped(): void {
        $mathml = '<mrow><mrow><mi>x</mi></mrow><mo>+</mo><mrow><mi>y</mi></mrow></mrow>';

        $this->assertSame('x+y', convert_mathml_to_latex($mathml));
    }

    public function testUnknownXmlLikeTagsAreStrippedAfterKnownReplacements(): void {
        $mathml = '<foo><mi>x</mi></foo><bar><mo>+</mo><mi>y</mi></bar>';

        $this->assertSame('x+y', convert_mathml_to_latex($mathml));
    }

    public function testEmptyInputReturnsEmptyString(): void {
        $this->assertSame('', convert_mathml_to_latex(''));
    }

    public function testNamespacePrefixesAreRemovedBeforeConversion(): void {
        $mathml = '<mml:msup><mml:mi>x</mml:mi><mml:mn>2</mml:mn></mml:msup>';

        $this->assertSame('x^{2}', convert_mathml_to_latex($mathml));
    }

    public function testMultiscriptTrimsChemicalBaseAndMassNumber(): void {
        $mathml = '<mmultiscripts> Fe <mprescripts/><none/><mn> 56 </mn></mmultiscripts>';

        $this->assertSame('^{56}\\mathrm{Fe}', convert_mathml_to_latex($mathml));
    }

    public function testScriptElementsAcceptNumericBasesAndIdentifierScripts(): void {
        $this->assertSame(
            '2^{n}',
            convert_mathml_to_latex('<msup><mn>2</mn><mi>n</mi></msup>')
        );
        $this->assertSame(
            '2_{i}',
            convert_mathml_to_latex('<msub><mn>2</mn><mi>i</mi></msub>')
        );
        $this->assertSame(
            '2_{i}^{n}',
            convert_mathml_to_latex('<msubsup><mn>2</mn><mi>i</mi><mi>n</mi></msubsup>')
        );
    }

    public function testFractionAcceptsOperatorAndIdentifierChildren(): void {
        $mathml = '<mfrac><mo>∑</mo><mi>n</mi></mfrac>';

        $this->assertSame('\\frac{\\sum}{n}', convert_mathml_to_latex($mathml));
    }

    public function testRootAcceptsOperatorRadicandAndIdentifierIndex(): void {
        $mathml = '<mroot><mo>∑</mo><mi>n</mi></mroot>';

        $this->assertSame('\\sqrt[n]{\\sum}', convert_mathml_to_latex($mathml));
    }

    public function testUnderOverWithoutRecognizedMathChildrenFallsBackToText(): void {
        $mathml = '<munderover><semantics><annotation>range</annotation></semantics></munderover>';

        $this->assertSame('range', convert_mathml_to_latex($mathml));
    }

    public function testSimpleMathMLTagsAreConverted(): void {
        $mathml = '<mtext>velocity</mtext><mspace/><msqrt><mi>x</mi></msqrt>'
            . '<mfenced><mi>y</mi></mfenced>';

        $this->assertSame(
            '\\text{velocity}\\,\\sqrt{x}\\left(y\\right)',
            convert_mathml_to_latex($mathml)
        );
    }

    public function testNamedMathEntitiesAreConverted(): void {
        $mathml = '<mi>&alpha;</mi><mo>&times;</mo><mi>&beta;</mi><mo>&equals;</mo><mn>2</mn>';

        $this->assertSame(
            '\\alpha\\times\\beta\\Relbar2',
            convert_mathml_to_latex($mathml)
        );
    }

    public function testUnknownMathMLTagsAreRemovedButTheirTextIsPreserved(): void {
        $mathml = '<semantics><annotation>note:</annotation><mi>x</mi></semantics>';

        $this->assertSame('note:x', convert_mathml_to_latex($mathml));
    }

    public function testRawUnicodeMathSymbolsAreConvertedAfterTagProcessing(): void {
        $mathml = '<mrow><mi>ℤ</mi><mo>∪</mo><mi>ℝ</mi><mo>→</mo><mi>α</mi></mrow>';

        $this->assertSame(
            '\\mathbb{Z}\\cup\\mathbb{R}\\rightarrow{}\\alpha',
            convert_mathml_to_latex($mathml)
        );
    }

    public function testPlainTextWithoutMathMarkupIsUnchanged(): void {
        $this->assertSame(
            'ordinary text 123',
            convert_mathml_to_latex('ordinary text 123')
        );
    }
}
