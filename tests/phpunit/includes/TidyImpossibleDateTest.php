<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * strtotime() rolls impossible dates over into the next month, so 31 June 2011
 * used to be "tidied" into July 2011 and 30 February 2012 into March 2012.
 * Such a date is a typo, so tidy_date() keeps only the year it can trust.
 * tidy_date() is applied to dates that come from Google Books and Zotero.
 */
final class TidyImpossibleDateTest extends testBaseClass {

    public function testDay31InA30DayMonthKeepsOnlyYear(): void {
        $this->assertSame('2011', tidy_date('2011-06-31'));
        $this->assertSame('2011', tidy_date('June 31, 2011'));
        $this->assertSame('2011', tidy_date('31 June 2011'));
        $this->assertSame('2001', tidy_date('2001-04-31'));
    }

    public function testFebruaryDatesThatDoNotExistKeepOnlyYear(): void {
        $this->assertSame('1913', tidy_date('1913-02-31'));
        $this->assertSame('2012', tidy_date('2012-02-30'));
        $this->assertSame('2010', tidy_date('2010-02-29'));
        $this->assertSame('2010', tidy_date('February 29, 2010'));
    }

    public function testLeapDayIsKept(): void {
        $this->assertSame('2012-02-29', tidy_date('29 February 2012'));
        $this->assertSame('2000-02-29', tidy_date('2000-02-29'));
    }

    public function testSlashDatesThatDoNotExistKeepOnlyYear(): void {
        $this->assertSame('1913', tidy_date('31/2/1913'));
        $this->assertSame('1913', tidy_date('2/31/1913'));
        $this->assertSame('2001', tidy_date('4/31/2001'));
        $this->assertSame('2001', tidy_date('29/2/2001'));
    }

    public function testRealSlashDatesAreStillConverted(): void {
        $this->assertSame('2000-02-29', tidy_date('29/2/2000'));
        $this->assertSame('2011-11-30', tidy_date('30/11/2011'));
        $this->assertSame('2014-01-24', tidy_date('01/24/2014'));
    }

    public function testDottedImpossibleDateKeepsOnlyYear(): void {
        $this->assertSame('2001', tidy_date('31.02.2001'));
        $this->assertSame('2001', tidy_date('31.4.2001'));
    }

    public function testImpossibleDateWithTimeKeepsOnlyYear(): void {
        $this->assertSame('1913', tidy_date('1913-02-31T10:00:00+00:00'));
        $this->assertSame('2001', tidy_date('2001-04-31 12:00:00'));
    }

    public function testUnknownDayIsMonthAndYear(): void {
        $this->assertSame('May 2001', tidy_date('2001-05-00'));
    }

    public function testUnknownMonthIsYear(): void {
        $this->assertSame('2001', tidy_date('2001-00-10'));
    }

    public function testGoogleDateRangesValidateBothEndpointsAndOrder(): void {
        $this->assertSame('', tidy_date('2011-06-31 - 2011-07-04'));
        $this->assertSame('', tidy_date('2012-02-28 - 2012-02-30'));
        $this->assertSame('', tidy_date('2012-03-10 - 2012-02-29'));
        $this->assertSame('29 February – 10 March 2012', tidy_date('2012-02-29 - 2012-03-10'));
        $this->assertSame('31 December 2011 – 1 January 2012', tidy_date('2011-12-31 - 2012-01-01'));
    }

    public function testTidyingTwiceGivesTheSameAnswer(): void {
        foreach (['31/2/1913', '2011-06-31', 'February 31, 1913', '2001-05-00', '31.02.2001'] as $input) {
            $once = tidy_date($input);
            $this->assertSame($once, tidy_date($once), $input);
        }
    }

    public function testRealDatesAreUntouched(): void {
        $this->assertSame('2012-02-28', tidy_date('2012-02-28'));
        $this->assertSame('2011-06-30', tidy_date('June 30, 2011'));
        $this->assertSame('May 2001', tidy_date('May 2001'));
        $this->assertSame('2001', tidy_date('2001'));
    }
}
