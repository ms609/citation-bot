<?php
/*
 * Tests for gadgetapi.php
 */
require_once __DIR__ . '/../testBaseClass.php';
 
final class gadgetTest extends testBaseClass {
  public function testGadget() {
      ob_start();
      $_POST['text'] = '{{cite|pmid=34213}}';
      $_POST['summary'] = 'Something Nice';
      require_once __DIR__ . '/../../gadgetapi.php';
      $json_text = ob_get_contents();
      ob_end_clean();
      @ob_end_flush(); @ob_end_flush(); // flush and close setup.php buffer
      $json = json_decode($json_text);
      $this->assertEquals("{{citation|pmid=34213|year=1979|last1=Weber|first1=F.|title=Antimicrobial resistance and serotypes of Streptococcus pneumoniae in Switzerland|journal=Schweizerische Medizinische Wochenschrift|volume=109|issue=11|pages=395–9|last2=Kayser|first2=F. H.}}", $json->expandedtext);
      $this->assertEquals("Something Nice | Alter: template type. Add: pages, issue, volume, journal, title, year, author pars. 1-2. Formatted [[WP:ENDASH|dashes]]. | You can [[WP:UCB|use this tool]] yourself. [[WP:DBUG|Report bugs here]]. ", $json->editsummary);
      $this->assertEquals("", $json->debug);
  }
}
