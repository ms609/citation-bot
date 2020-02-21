<?php
/*
 * Tests for gadgetapi.php
 */
require_once __DIR__ . '/../testBaseClass.php';
 
final class gadgetTest extends testBaseClass {
  public function testGadget() {
      global $FLUSHING_OKAY;
      global $SLOW_MODE;
      // Run Gadget API
      ob_start();
      $_POST['text'] = '{{cite|pmid=34213}}';
      $_POST['summary'] = 'Something Nice';
      require_once __DIR__ . '/../../gadgetapi.php';
      $json_text = ob_get_contents();
      ob_end_clean();
      // Reset everything
      $FLUSHING_OKAY = TRUE;
      $SLOW_MODE = TRUE;
      while (ob_get_level()) { ob_end_flush(); };
      ob_start(); // PHPUnit turns on a level of buffering itself -- Give it back to avoid "Risky Test"
      // Output checking time
      $json = json_decode($json_text);
      $this->assertSame('{{citation|pmid=34213|year=1979|last1=Weber|first1=F.|last2=Kayser|first2=F. H.|title=Antimicrobial resistance and serotypes of Streptococcus pneumoniae in Switzerland|journal=Schweizerische Medizinische Wochenschrift|volume=109|issue=11|pages=395–9}}', $json->expandedtext);
      $this->assertSame("Something Nice | Alter: template type. Add: pages, issue, volume, journal, title, year, author pars. 1-2. Formatted [[WP:ENDASH|dashes]]. | You can [[WP:UCB|use this tool]] yourself. [[WP:DBUG|Report bugs here]]. | via #UCB_Gadget ", $json->editsummary);
  }
}
