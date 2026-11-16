<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class WrittenToMarkupTest extends testBaseClass {
    public function testWrittenToUsesCssClassInsteadOfInvalidStyle(): void {
        // The write branch requires an external API. Assert its output literal
        // directly so the regression test remains entirely offline.
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/includes/WikipediaBot.php');
        $this->assertIsString($source);
        $this->assertStringContainsString("<span class='reddish'>Written to", $source);
        $this->assertStringNotContainsString("<span style='reddish'>Written to", $source);
    }
}
