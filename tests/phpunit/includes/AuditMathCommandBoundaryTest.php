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

    public function testAdjacentUnicodeSymbolsDoNotMergeCommands(): void {
        foreach ([
            'μο' => '\\mu{}o',
            'μΟ' => '\\mu{}O',
            'αο' => '\\alpha{}o',
            'μρ' => '\\mu\\rho',
            'μm' => '\\mu{}m',
            'π2' => '\\pi2',
            'π x' => '\\pi x',
            'ℝn' => '\\mathbb{R}n',
        ] as $input => $expected) {
            $this->assertSame($expected, convert_mathml_to_latex('<mi>' . $input . '</mi>'), $input);
        }
    }

    public function testAdjacentEntitiesDoNotMergeCommands(): void {
        foreach ([
            '&mu;&omicron;' => '\\mu{}o',
            '&mu;&Ogr;' => '\\mu{}O',
            '&alpha;&omicron;' => '\\alpha{}o',
            '&mu;&alpha;' => '\\mu\\alpha',
            '&mu;&dd;' => '\\mu{}d',
            '&Prime;m' => '\\prime\\prime{}m',
            '&nsub;o' => '\\not\\subset{}o',
        ] as $input => $expected) {
            $this->assertSame($expected, convert_mathml_to_latex('<mi>' . $input . '</mi>'), $input);
        }
    }

    public function testMixedEntityUnicodeSequenceUsesFinalOutputBoundaries(): void {
        foreach ([
            'μ&omicron;' => '\\mu{}o',
            '&mu;ο' => '\\mu{}o',
            '&mu;Ο' => '\\mu{}O',
            '&mu;μm' => '\\mu\\mu{}m',
            '&alpha;οx' => '\\alpha{}ox',
            '&mu;α' => '\\mu\\alpha',
        ] as $input => $expected) {
            $this->assertSame($expected, convert_mathml_to_latex('<mi>' . $input . '</mi>'), $input);
        }
    }

    public function testRemovedEntitiesDoNotErasePendingCommandBoundary(): void {
        foreach (['&InvisibleTimes;', '&Space;', '&ApplyFunction;'] as $invisible) {
            $this->assertSame(
                '\\mu{}m',
                convert_mathml_to_latex('<mi>&mu;</mi><mo>' . $invisible . '</mo><mi>m</mi>'),
                $invisible
            );
        }
        $this->assertSame('x\\rightarrow{}speed', convert_mathml_to_latex('<mi>x</mi><mo>&rarr;</mo><mi>speed</mi>'));
    }

    public function testHelperHandlesOverlappingKeysAndEmptyReplacements(): void {
        $this->assertSame(
            '\\mu{}m',
            mathml_replace_symbols(['μ' => '\\mu', '∅' => '', 'μμ' => '\\mu'], 'μ∅m')
        );
        $this->assertSame('plain text', mathml_replace_symbols([], 'plain text'));
        $this->assertSame("\xFF", mathml_replace_symbols(['μ' => '\\mu'], "\xFF"));
    }

    public function testMixedEncodingWithRemovedEntities(): void {
        foreach ([
            '&mu;&InvisibleTimes;&omicron;' => '\\mu{}o',
            '&mu;&Space;ο' => '\\mu{}o',
            'μ&ApplyFunction;ο' => '\\mu{}o',
            '&alpha;&Space;m' => '\\alpha{}m',
        ] as $input => $expected) {
            $this->assertSame($expected, convert_mathml_to_latex('<mi>' . $input . '</mi>'), $input);
        }
    }

    public function testProtectedComparisonEntitiesRemainLiteralOperators(): void {
        $this->assertSame(
            'p<0.05',
            convert_mathml_to_latex('<mi>p</mi><mo>&lt;</mo><mn>0.05</mn>')
        );
        $this->assertSame(
            'x>y',
            convert_mathml_to_latex('<mi>x</mi><mo>&gt;</mo><mi>y</mi>')
        );
    }

    public function testLongestSymbolKeyWinsAndOutputIsNotRecursivelyReplaced(): void {
        $this->assertSame(
            '\\Psi{}m',
            mathml_replace_symbols(['μ' => '\\mu', 'μμ' => '\\Psi'], 'μμm')
        );
        $this->assertSame(
            '\\mu{}o',
            mathml_replace_symbols(['μ' => '\\mu', 'ο' => 'o'], 'μο')
        );
        $this->assertSame(
            'plain',
            mathml_replace_symbols(['π' => '\\pi'], 'plain')
        );
    }
}
