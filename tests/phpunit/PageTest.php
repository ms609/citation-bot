<?php

/*
 * Tests for Page.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

    public function testCategoryMembers2() {
      $api = new WikipediaBot();
      $this->assertNull($api->category_members('GA-Class cricket articles of Low-importance'));
    }
    public function testCategoryMembers() {
      $api = new WikipediaBot();
      $this->assertNull($api->category_members('A category we expect to be empty')));
    }
 
  public function testMultiArxiv() {
      $text='{{cite|arxiv=math/0011268}}{{cite|arxiv=astro-ph/9604016}}{{cite|arxiv=1705.00527}}{{cite|arxiv=1805.07980}}';
      $page = $this->process_page($text);
      $this->assertSame('{{citation|arxiv=math/0011268|last1=Chenciner|first1=Alain|title=A remarkable periodic solution of the three-body problem in the case of equal masses|last2=Montgomery|first2=Richard|year=2000}}{{citation|arxiv=astro-ph/9604016|doi=10.1046/j.1365-8711.2000.04027.x|title=A new outcome of binary--binary scattering|journal=Monthly Notices of the Royal Astronomical Society|volume=318|issue=4|pages=L61â€“L63|year=2000|last1=Heggie|first1=D. C.|last2=Hut|first2=P.|last3=McMillan|first3=S. L. W.}}{{citation|arxiv=1705.00527|doi=10.1007/s11433-017-9078-5|title=More than six hundred new families of Newtonian periodic planar collisionless three-body orbits|journal=Science China Physics, Mechanics & Astronomy|volume=60|issue=12|year=2017|last1=Li|first1=Xiaoming|last2=Liao|first2=Shijun}}{{citation|arxiv=1805.07980|doi=10.1016/j.newast.2019.01.003|title=Collisionless periodic orbits in the free-fall three-body problem|journal=New Astronomy|volume=70|pages=22â€“26|year=2019|last1=Li|first1=Xiaoming|last2=Liao|first2=Shijun}}', $page->parsed_text());
  }
 
 
}
