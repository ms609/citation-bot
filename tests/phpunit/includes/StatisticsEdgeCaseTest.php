<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Network-free edge cases for the statistics wikitext generator.
 */
final class StatisticsEdgeCaseTest extends PHPUnit\Framework\TestCase {

    private function now(): DateTimeImmutable {
        return new DateTimeImmutable('2026-01-02 03:04:05', new DateTimeZone('America/Chicago'));
    }

    public function testEqualCountsAreOrderedByName(): void {
        $text = statistics_generate_wikitext(['b-type' => 2, 'a-type' => 2, 'z-type' => 5, 'c-type' => 2], 11, $this->now());
        $z = mb_strpos($text, '<code>z-type</code>');
        $a = mb_strpos($text, '<code>a-type</code>');
        $b = mb_strpos($text, '<code>b-type</code>');
        $c = mb_strpos($text, '<code>c-type</code>');
        $this->assertIsInt($z);
        $this->assertIsInt($a);
        $this->assertIsInt($b);
        $this->assertIsInt($c);
        $this->assertLessThan($a, $z, 'The highest count comes first');
        $this->assertLessThan($b, $a, 'Ties are broken alphabetically');
        $this->assertLessThan($c, $b, 'Ties are broken alphabetically');
    }

    public function testTimestampIsReportedInUtc(): void {
        $text = statistics_generate_wikitext(['x' => 1], 1, $this->now());
        $this->assertStringContainsString("''Last updated: 2026-01-02 09:04:05 UTC''", $text);
    }

    public function testWindowSizeAppearsInEverySentence(): void {
        $text = statistics_generate_wikitext(['x' => 1], 1, $this->now(), 6);
        $this->assertStringContainsString('in the last 6 hours (updated once per day)', $text);
        $this->assertStringContainsString("Total edits in last 6 hours: '''1'''", $text);
        $this->assertStringNotContainsString('24 hours', $text);
    }

    public function testNoEditsProducesNoTable(): void {
        $text = statistics_generate_wikitext([], 0, $this->now(), 12);
        $this->assertStringContainsString('No edits were made in the last 12 hours.', $text);
        $this->assertStringNotContainsString('wikitable', $text);
        $this->assertStringNotContainsString('Total edits', $text);
    }

    public function testPipesInTagNamesCannotBreakTheTable(): void {
        $text = statistics_generate_wikitext(['odd|name' => 3], 3, $this->now());
        $this->assertStringContainsString('<code>odd&#124;name</code> || 3', $text);
        $this->assertStringNotContainsString('odd|name', $text);
    }

    public function testTableEndsWithTheTotalRow(): void {
        $text = statistics_generate_wikitext(['one' => 1, 'two' => 2], 3, $this->now());
        $this->assertStringContainsString("|-\n! Total || 3\n|}", $text);
        $this->assertStringContainsString('<code>two</code> || 2', $text);
        $this->assertStringContainsString('<code>one</code> || 1', $text);
    }
}
