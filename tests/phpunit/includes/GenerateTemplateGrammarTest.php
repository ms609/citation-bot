<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class GenerateTemplateGrammarTest extends testBaseClass {
    public function testOneParameterErrorUsesSingularNoun(): void {
        // Source check avoids invoking an endpoint that exits and requires web setup.
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/generate_template.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('Exactly one parameter must be passed', $source);
        $this->assertStringNotContainsString('Exactly one parameters must be passed', $source);
    }
}
