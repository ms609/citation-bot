<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class WikipediaWriteSafetyTest extends testBaseClass {
    public function testEditAssertionVariesWithCredentials(): void {
        $this->assertSame(
            ['assert' => 'bot', 'md5' => md5('Text')],
            WikipediaBot::edit_safety_parameters('Text', false)
        );
        $this->assertSame(
            ['assert' => 'user', 'md5' => md5('Text')],
            WikipediaBot::edit_safety_parameters('Text', true)
        );
    }

    public function testChecksumUsesExactUtf8Bytes(): void {
        $text = "{{cite web|title=Zürich|url=https://example.org}}
";
        $this->assertSame(md5($text), WikipediaBot::edit_safety_parameters($text, false)['md5']);
        $this->assertNotSame(md5(rtrim($text)), WikipediaBot::edit_safety_parameters($text, false)['md5']);
    }
}
