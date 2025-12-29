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

    public function testMathMLSuperscript(): void {
        // Test simple superscript: x^{2}
        $text_mml = '<math><msup><mi>x</mi><mn>2</mn></msup></math>';
        $result = wikify_external_text($text_mml);
        $this->assertStringContainsString('x', $result);
        $this->assertStringContainsString('^', $result);
        $this->assertStringContainsString('2', $result);
    }

    public function testMathMLSubscript(): void {
        // Test simple subscript: H_{2}O
        $text_mml = '<math><msub><mi>H</mi><mn>2</mn></msub></math>';
        $result = wikify_external_text($text_mml);
        $this->assertStringContainsString('H', $result);
        $this->assertStringContainsString('_', $result);
        $this->assertStringContainsString('2', $result);
    }

    public function testMathMLSubSuperscript(): void {
        // Test subscript and superscript: x_{1}^{2}
        $text_mml = '<math><msubsup><mi>x</mi><mn>1</mn><mn>2</mn></msubsup></math>';
        $result = wikify_external_text($text_mml);
        $this->assertStringContainsString('x', $result);
        $this->assertStringContainsString('_', $result);
        $this->assertStringContainsString('^', $result);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('2', $result);
    }

    public function testMathMLRoot(): void {
        // Test nth root: \sqrt[3]{x}
        $text_mml = '<math><mroot><mi>x</mi><mn>3</mn></mroot></math>';
        $result = wikify_external_text($text_mml);
        $this->assertStringContainsString('sqrt', $result);
        $this->assertStringContainsString('x', $result);
        $this->assertStringContainsString('3', $result);
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
}
