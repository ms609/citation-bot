<?php
declare(strict_types=1);

/*
 * Tests for gadgetapi.php
 */
require_once(__DIR__ . '/../testBaseClass.php');
 
final class gadgetTest extends testBaseClass {
 
  protected function setUp(): void {
   if (BAD_PAGE_HTTP !== '' || BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }
 
  public function testGadget() : void {
      ob_start();
      $_POST['text'] = '{{cite|pmid=34213}}';
      $_POST['summary'] = 'Something Nice';
      $_REQUEST["slow"] = "1";
      require(__DIR__ . '/../../gadgetapi.php');
      $json_text = ob_get_contents();
      ob_end_clean();
      // Reset everything
      while (ob_get_level()) { ob_end_flush(); };
      ob_start(); // PHPUnit turns on a level of buffering itself -- Give it back to avoid "Risky Test"
      unset($_POST);
      unset($_REQUEST);
      // Output checking time
      $json = json_decode($json_text);
      $this->assertSame('{{citation|pmid=34213|year=1979|last1=Weber|first1=F.|last2=Kayser|first2=F. H.|title=Antimicrobial resistance and serotypes of Streptococcus pneumoniae in Switzerland|journal=Schweizerische Medizinische Wochenschrift|volume=109|issue=11|pages=395–399}}', $json->expandedtext);
      $this->assertSame('Something Nice | Alter: template type. Add: pages, issue, volume, journal, title, year, authors 1-2. Removed Template redirect. | [[WP:UCB|Use this tool]]. [[WP:DBUG|Report bugs]]. | #UCB_Gadget ', $json->editsummary);
  }
}
