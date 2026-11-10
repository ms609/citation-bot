<?php
declare(strict_types=1);

/**
 * A curly or encoded single quote is not straightened when that would put it
 * next to an existing straight quote, whichever kind of quote it is.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditQuoteLookaroundTest extends testBaseClass {

    public function testCurlyQuoteAfterStraightQuoteIsKept(): void {
        $this->assertSame("it'\u{2019}s", straighten_quotes("it'\u{2019}s", false));
    }

    public function testNumericEntityBeforeStraightQuoteIsKept(): void {
        $this->assertSame("it&#8217;'s", straighten_quotes("it&#8217;'s", false));
    }

    public function testIsolatedQuotesAreStillStraightened(): void {
        $this->assertSame("it's", straighten_quotes("it\u{2019}s", false));
        $this->assertSame("it's", straighten_quotes("it&#8217;s", false));
        $this->assertSame("it's", straighten_quotes("it&rsquo;s", false));
    }
}
