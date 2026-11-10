<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Edge-case tests for user_messages.php that depend on the output-mode
 * constants (CI, HTML_OUTPUT, BIG_JOB_MODE): big-job output compression, the
 * 900-character cutoff, HTML output mode, and report_inline().
 *
 * Constants cannot be redefined once set, so every test runs in its own
 * PHPUnit-managed child process (which keeps its code coverage) and defines
 * exactly the constants it needs before loading user_messages.php.  This class
 * deliberately does NOT extend testBaseClass, because that would load setup.php
 * and define the constants first.  No network access and no uopz are required.
 */
final class UserMessagesIsolatedModesTest extends PHPUnit\Framework\TestCase {

    private function useMode(
        bool $ci,
        bool $html,
        bool $big_job = false,
        bool $logger = false,
        bool $doi_encode = false
    ): void {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        define('CI', $ci);
        define('HTML_OUTPUT', $html);
        define('WIKI_ROOT', 'https://en.wikipedia.org/w/index.php');
        if ($big_job) {
            define('BIG_JOB_MODE', 'YES');
        }
        if ($logger) {
            // Stand-in for setup.php's logger that makes log calls visible in the output.
            eval('function bot_debug_log(string $text): void { echo "[LOG:", $text, "]"; }'); // phpcs:ignore
        }
        if ($doi_encode) {
            // Same contract as TextTools.php::doi_encode(), without loading the whole bot.
            eval('function doi_encode(string $doi): string { return str_replace("%2F", "/", urlencode($doi)); }'); // phpcs:ignore
        }
        require_once dirname(__DIR__, 3) . '/src/includes/user_messages.php';
    }

    /** Run $operation and return everything it echoed. */
    private function capture(callable $operation): string {
        ob_start();
        try {
            $operation();
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    // ---- big-job mode --------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigJobModeCollapsesBoringMessagesToADot(): void {
        $this->useMode(false, false, true);
        $calls = [
            'report_inaction', 'report_add', 'report_forget',
            'report_modification', 'report_action', 'report_info',
        ];
        foreach ($calls as $function) {
            $output = $this->capture(static function () use ($function): void {
                $function('SECRET-BORING-TEXT');
            });
            $this->assertStringNotContainsString('SECRET-BORING-TEXT', $output, $function);
            $this->assertStringEndsWith('.', mb_rtrim($output), $function);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigJobModeStillShowsWarningsAndPhases(): void {
        $this->useMode(false, false, true);

        $output = $this->capture(static function (): void {
            report_warning('keep this warning');
            report_phase('keep this phase');
        });

        $this->assertStringContainsString('keep this warning', $output);
        $this->assertStringContainsString('keep this phase', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigJobModeSuppressesOverlongMessageAndLogsFiveHundredCharacters(): void {
        $this->useMode(false, false, true, true);

        $output = $this->capture(static function (): void {
            report_warning(str_repeat('A', 1000));
        });

        $this->assertStringContainsString('HUGE amount of text NOT printed', $output);
        $this->assertStringContainsString(
            '[LOG:HUGE amount of text NOT printed.  Here is a bit: ' . str_repeat('A', 500) . ']',
            $output
        );
        // Only the logged 500-character excerpt may appear, never the whole message.
        $this->assertStringNotContainsString(str_repeat('A', 501), $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigJobModeCutoffIsExclusiveAtNineHundredCharacters(): void {
        $this->useMode(false, false, true, true);

        $at_limit = $this->capture(static function (): void {
            report_warning(str_repeat('B', 900));
        });
        $this->assertStringContainsString(str_repeat('B', 900), $at_limit);
        $this->assertStringNotContainsString('HUGE amount of text', $at_limit);

        $over_limit = $this->capture(static function (): void {
            report_warning(str_repeat('B', 901));
        });
        $this->assertStringContainsString('HUGE amount of text NOT printed', $over_limit);
        $this->assertStringNotContainsString(str_repeat('B', 600), $over_limit);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBigJobModeCutoffCountsCharactersNotBytes(): void {
        $this->useMode(false, false, true, true);

        // 900 two-byte characters are 1800 bytes but still within the limit.
        $at_limit = $this->capture(static function (): void {
            report_warning(str_repeat('é', 900));
        });
        $this->assertStringContainsString(str_repeat('é', 900), $at_limit);
        $this->assertStringNotContainsString('HUGE amount of text', $at_limit);

        $over_limit = $this->capture(static function (): void {
            report_warning(str_repeat('é', 901));
        });
        $this->assertStringContainsString('HUGE amount of text NOT printed', $over_limit);
        // The log excerpt is 500 characters (not bytes) of the original text.
        $this->assertStringContainsString(str_repeat('é', 500), $over_limit);
        $this->assertStringNotContainsString(str_repeat('é', 501), $over_limit);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOverlongMessagesArePrintedInFullOutsideBigJobMode(): void {
        $this->useMode(false, false, false, true);

        $output = $this->capture(static function (): void {
            report_warning(str_repeat('C', 1000));
        });

        $this->assertStringContainsString(str_repeat('C', 1000), $output);
        $this->assertStringNotContainsString('HUGE amount of text', $output);
        $this->assertStringNotContainsString('[LOG:', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testReportInlinePrintsInBigJobModeWhenNotCi(): void {
        $this->useMode(false, false, true);

        $output = $this->capture(static function (): void {
            report_inline('inline text');
        });

        $this->assertSame(' inline text', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testReportInlineIsSilentOutsideBigJobMode(): void {
        $this->useMode(false, false, false);

        $output = $this->capture(static function (): void {
            report_inline('inline text');
        });

        $this->assertSame('', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testReportInlineIsSilentInCiEvenInBigJobMode(): void {
        $this->useMode(true, false, true);

        $output = $this->capture(static function (): void {
            report_inline('inline text');
        });

        $this->assertSame('', $output);
    }

    // ---- HTML output mode ----------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHtmlModeWrapsEachMessageKindInItsOwnClassedSpan(): void {
        $this->useMode(false, true);
        $expected = [
            'report_phase' => 'phase',
            'report_action' => 'subitem',
            'report_info' => 'subsubitem',
            'report_inaction' => 'boring',
            'report_warning' => 'warning',
            'report_modification' => 'changed',
            'report_add' => 'added',
            'report_forget' => 'removed',
        ];
        foreach ($expected as $function => $class) {
            $output = $this->capture(static function () use ($function): void {
                $function('message body');
            });
            $this->assertStringContainsString("<span class='" . $class . "'>", $output, $function);
            $this->assertStringContainsString('message body</span>', $output, $function);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHtmlModeBigJobStillWrapsCollapsedMessageInSpan(): void {
        $this->useMode(false, true, true);

        $output = $this->capture(static function (): void {
            report_add('SECRET-BORING-TEXT');
        });

        $this->assertStringContainsString("<span class='added'>", $output);
        $this->assertStringNotContainsString('SECRET-BORING-TEXT', $output);
        $this->assertStringContainsString('.</span>', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHtmlEchoPrintsHtmlTextWhenHtmlOutputIsOn(): void {
        $this->useMode(false, true);

        $output = $this->capture(static function (): void {
            html_echo('<b>html</b>', 'plain');
        });

        $this->assertSame('<b>html</b>', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHtmlEchoPrintsAlternateTextWhenHtmlOutputIsOff(): void {
        $this->useMode(false, false);

        $with_alternate = $this->capture(static function (): void {
            html_echo('<b>html</b>', 'plain');
        });
        $this->assertSame('plain', $with_alternate);

        $default_alternate = $this->capture(static function (): void {
            html_echo('<b>html</b>');
        });
        $this->assertSame('', $default_alternate);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHtmlEchoIsSilentInCiEvenWithHtmlOutput(): void {
        $this->useMode(true, true);

        $output = $this->capture(static function (): void {
            html_echo('<b>html</b>', 'plain');
        });

        $this->assertSame('', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEchoableEscapesEveryHtmlSignificantCharacterInHtmlMode(): void {
        $this->useMode(false, true);

        // ENT_HTML5 encodes the apostrophe as &apos; (not &#039;).
        $this->assertSame(
            '&lt;a href=&quot;x&quot;&gt;&apos;&amp;&lt;/a&gt;',
            echoable('<a href="x">\'&</a>')
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEchoableHandlesNullEmptyAndInvalidUtf8InHtmlMode(): void {
        $this->useMode(false, true);

        $this->assertSame('', echoable(null));
        $this->assertSame('', echoable(''));
        // ENT_SUBSTITUTE replaces the invalid byte instead of discarding the whole string.
        $this->assertSame("ab\u{FFFD}cd", echoable("ab\xFFcd"));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNewTabAriaLabelIsEscapedInHtmlMode(): void {
        $this->useMode(false, true);

        $this->assertSame(
            'A &quot;B&quot; &lt;C&gt; (opens in a new tab)',
            new_tab_aria_label('A "B" <C>')
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHtmlLinksUrlEncodeIdentifiersAndEscapeVisibleText(): void {
        $this->useMode(false, true, false, false, true);

        $lines = [
            pubmed_link('pmid', '1 2'),
            bibcode_link('2020A&A...1..2B'),
            jstor_link('abc/123'),
            wiki_link('Foo bar&baz'),
            doi_link('10.1000%2Fabc def'),
        ];

        $this->assertStringContainsString('href="https://www.ncbi.nlm.nih.gov/pubmed/1+2"', $lines[0]);
        $this->assertStringContainsString('>PMID 1 2</a>', $lines[0]);

        $this->assertStringContainsString('href="https://ui.adsabs.harvard.edu/abs/2020A%26A...1..2B"', $lines[1]);
        $this->assertStringContainsString('>2020A&amp;A...1..2B</a>', $lines[1]);

        $this->assertStringContainsString('href="https://www.jstor.org/citation/ris/abc%2F123"', $lines[2]);
        $this->assertStringContainsString('>JSTOR abc/123</a>', $lines[2]);

        $this->assertStringContainsString(
            'href="https://en.wikipedia.org/w/index.php?title=Foo_bar%26baz"',
            $lines[3]
        );
        $this->assertStringContainsString('>Wikipedia page: Foo bar&amp;baz</a>', $lines[3]);

        // The DOI is decoded first, so an encoded slash is not double-encoded.
        $this->assertStringContainsString('href="https://dx.doi.org/10.1000/abc+def"', $lines[4]);
        $this->assertStringContainsString('>10.1000%2Fabc def</a>', $lines[4]);

        foreach ($lines as $line) {
            $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $line);
            $this->assertStringContainsString('(opens in a new tab)"', $line);
        }
    }

    // ---- recoverable errors --------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMinorErrorWithLoggerBothLogsAndWarns(): void {
        $this->useMode(false, false, false, true);

        $output = $this->capture(static function (): void {
            report_minor_error('minor problem');
            echo '|CONTINUED';
        });

        $this->assertStringStartsWith('[LOG:minor problem]', $output);
        $this->assertSame(2, mb_substr_count($output, 'minor problem'));
        $this->assertStringContainsString('|CONTINUED', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMinorErrorWithoutLoggerStillWarnsAndContinues(): void {
        $this->useMode(false, false);
        $this->assertFalse(function_exists('bot_debug_log'));

        $output = $this->capture(static function (): void {
            report_minor_error('minor problem');
            echo '|CONTINUED';
        });

        $this->assertSame(1, mb_substr_count($output, 'minor problem'));
        $this->assertStringNotContainsString('[LOG:', $output);
        $this->assertStringContainsString('|CONTINUED', $output);
    }
}
