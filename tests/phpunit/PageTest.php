<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testPageChangeSummary() {
      $page = $this->process_page('{{cite journal|chapter=chapter name|title=book name}}'); // Change to book from journal
      $this->assertEquals('Alter: template type. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].',$page->edit_summary());
      $page = $this->process_page('{{cite book||quote=a quote}}'); // Just lose extra pipe
      $this->assertEquals('Misc citation tidying. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].',$page->edit_summary());
      $page = $this->process_page('<ref>http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x</ref>');
      $this->assertFalse(strpos($page->parsed_text(), 'onlinelibrary.wiley.com')); // URL is gone
      $this->assertEquals('Alter: template type. Add: year, pages, issue, volume, journal, title, doi, author pars. 1-2. Removed URL that duplicated unique identifier. Converted bare reference to cite template. Formatted [[WP:ENDASH|dashes]]. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].' ,$page->edit_summary());                
      $page = $this->process_page('{{cite web|<!-- comment --> journal=Journal Name}}'); // Comment BEFORE parameter
      $this->assertEquals('Alter: template type. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].',$page->edit_summary());
      $this->assertEquals('{{cite journal|<!-- comment --> journal=Journal Name}}', $page->parsed_text());
      $page = $this->process_page('{{cite web|journal<!-- comment -->=Journal Name}}'); // Comment AFTER parameter
      $this->assertEquals('Alter: template type. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].',$page->edit_summary());
      $this->assertEquals('{{cite journal|journal<!-- comment -->=Journal Name}}', $page->parsed_text());
      $page = $this->process_page('{{cite book|url=http://a.fake.url.fake/|chapter=Chap|title=Title}}');
      $this->assertEquals('{{cite book|chapter-url=http://a.fake.url.fake/|chapter=Chap|title=Title}}', $page->parsed_text());
      $this->assertEquals('Add: chapter-url. Removed or converted URL. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]].' ,$page->edit_summary());
  }
 
  public function testBotRead() {
   $this->requires_secrets(function() {
      $page = new TestPage();
      $api = new WikipediaBot();
      $page->get_text_from('User:Blocked Testing Account/readtest', $api);
      $this->assertEquals('This page tests bots', $page->parsed_text());
   });
  }
  
  public function testBotExpandWrite() {
   $this->requires_secrets(function() {
      $api = new WikipediaBot();
      $page = new TestPage();
      $writeTestPage = 'User:Blocked Testing Account/writetest';
      $page->get_text_from($writeTestPage, $api);
      $trialCitation = '{{Cite journal | title Bot Testing | ' .
        'doi_broken_date=1986-01-01 | doi = 10.1038/nature09068}}';
      $page->overwrite_text($trialCitation);
      $this->assertTrue($page->write($api, "Testing bot write function"));
      
      $page->get_text_from($writeTestPage, $api);
      $this->assertEquals($trialCitation, $page->parsed_text());
      $page->expand_text();
      $this->assertTrue(strpos($page->edit_summary(), 'journal, ') > 3);
      $this->assertTrue(strpos($page->edit_summary(), ' Removed ') > 3);
      $this->assertTrue($page->write($api));
      
      $page->get_text_from($writeTestPage, $api);
      $this->assertTrue(strpos($page->parsed_text(), 'Nature') > 5);
   });
  }
 
  public function testEmptyPage() {
      $page = $this->process_page('');
      $page = $this->process_page('  ');
      $page = $this->process_page('  move along, nothing to see here ');
      $page = $this->process_page('  move along, nothing to see here {{}} ');
      $this->assertNull(NULL);
  }

  public function testUrlReferences() {
      $page = $this->process_page("URL reference test 1 <ref name='bob'>http://doi.org/10.1007/s12668-011-0022-5< / ref>\n Second reference: \n<ref >  [https://www.ncbi.nlm.nih.gov/pmc/articles/PMC3705692/] </ref> URL reference test 1");
      $this->assertEquals("URL reference test 1 <ref name='bob'>{{Cite journal |doi = 10.1007/s12668-011-0022-5|title = Reoccurring Patterns in Hierarchical Protein Materials and Music: The Power of Analogies|journal = Bionanoscience|volume = 1|issue = 4|pages = 153–161|year = 2011|last1 = Giesa|first1 = Tristan|last2 = Spivak|first2 = David I.|last3 = Buehler|first3 = Markus J.|arxiv = 1111.5297}}< / ref>\n Second reference: \n<ref >{{Cite journal |pmc = 3705692|year = 2013|last1 = Mahajan|first1 = P. T.|title = Indian religious concepts on sexuality and marriage|journal = Indian Journal of Psychiatry|volume = 55|issue = Suppl 2|pages = S256–S262|last2 = Pimple|first2 = P.|last3 = Palsetia|first3 = D.|last4 = Dave|first4 = N.|last5 = De Sousa|first5 = A.|pmid = 23858264|doi = 10.4103/0019-5545.105547}}</ref> URL reference test 1", $page->parsed_text());
      $page = $this->process_page(" text <ref name='dog' > 10.1063/1.2263373 </ref>");
      $this->assertTrue((boolean) strpos($page->parsed_text(), 'title'));
      $page = $this->process_page(" text <ref name='dog' >[http://doi.org/10.1007/s12668-011-0022-5 http://doi.org/10.1007/s12668-011-0022-5]</ref>");
      $this->assertTrue((boolean) strpos($page->parsed_text(), 'title'));
  }

  public function testUrlReferencesThatFail() {
      $text = 'testUrlReferencesThatFail <ref name="bob">http://this.fails/nothing< / ref> testUrlReferencesThatFail <ref >  http://this.fails/nothing </ref> testUrlReferencesThatFail <ref>10.1234/ABCDEFGHIJ.faker</ref>';
      $page = $this->process_page($text);
      $this->assertEquals($text, $page->parsed_text());
  }
 
   public function testUrlReferencesWithText0() {
      $text = "<ref>{{doi|10.2307/962034}}</ref>";
      $page = $this->process_page($text);
      $this->assertEquals('<ref>{{Cite journal | doi=10.2307/962034| jstor=962034|title = Alban Berg, Wilhelm Fliess and the Secret Programme of the Violin Concerto| journal=The Musical Times| volume=124| issue=1682| pages=218–223|year = 1983|last1 = Jarman|first1 = Douglas}}</ref>', $page->parsed_text());
  }
 
  public function testUrlReferencesWithText1() {
      $text = "<ref>Jarman, D. (1983). [https://www.jstor.org/discover/10.2307/962034?uid=3738032&amp;uid=373072751&amp;uid=2&amp;uid=3&amp;uid=60&amp;sid=21102523353593 Alban Berg, Wilhelm Fliess and the Secret Programme of the Violin Concerto]. ''The Musical Times'' Vol. 124, No. 1682 (Apr. 1983), pp. 218–223</ref>";
      $page = $this->process_page($text);
      $this->assertEquals('<ref>{{Cite journal |jstor = 962034|title = Alban Berg, Wilhelm Fliess and the Secret Programme of the Violin Concerto|journal = The Musical Times|volume = 124|issue = 1682|pages = 218–223|last1 = Jarman|first1 = Douglas|year = 1983}}</ref>', $page->parsed_text());
  }
  
  public function testUrlReferencesWithText2() {
      $text = "<ref>[[Murray Gell-Mann]] (1995) &quot;[http://onlinelibrary.wiley.com/doi/10.1002/cplx.6130010105/pdf What is complexity? Remarks on simplicity and complexity by the Nobel Prize-winning author of The Quark and the Jaguar]&quot; ''Complexity'' states the 'algorithmic information complexity' (AIC) of some string of bits is the shortest length computer program which can print out that string of bits.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals($text, $page->parsed_text());
  }
  
  public function testUrlReferencesWithText3() {
      $text = "<ref>Raymond O.  Silverstein, &quot;A note on the term 'Bantu' as first used by W. H. I. Bleek&quot;, ''African Studies'' 27 (1968), 211–212, [https://www.doi.org/10.1080/00020186808707298 doi:10.1080/00020186808707298].</ref>";
      $page = $this->process_page($text);
      $this->assertEquals('<ref>{{Cite journal | doi=10.1080/00020186808707298|title = A note on the term "Bantu" as first used by W. H. I. Bleek| journal=African Studies| volume=27| issue=4| pages=211–212|year = 1968|last1 = Silverstein|first1 = Raymond O.}}</ref>', $page->parsed_text());
  }
  
  public function testUrlReferencesWithText4() { // Has [[ ]] in it
      $text = "<ref>[[Chandra Prakash Kala|Kala, C.P.]] and Ratajc, P. 2012.[https://rd.springer.com/article/10.1007/s10531-012-0246-x &quot;High altitude biodiversity of the Alps and the Himalayas: ethnobotany, plant distribution and conservation perspective&quot;.] ''Biodiversity and Conservation'', 21 (4): 1115–1126.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals($text, $page->parsed_text());
  }
  
  public function testUrlReferencesWithText5() {
      $text = "<ref>Stoeckelhuber, Mechthild, Alexander Sliwa, and Ulrich Welsch. &quot;[http://onlinelibrary.wiley.com/doi/10.1002/1097-0185(20000701)259:3%3C312::AID-AR80%3E3.0.CO;2-X/full Histo‐physiology of the scent‐marking glands of the penile pad, anal pouch, and the forefoot in the aardwolf (Proteles cristatus)].&quot; The anatomical record 259.3 (2000): 312-326.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals('<ref>{{Cite journal | doi=10.1002/1097-0185(20000701)259:3<312::AID-AR80>3.0.CO;2-X|title = Histo-physiology of the scent-marking glands of the penile pad, anal pouch, and the forefoot in the aardwolf (Proteles cristatus)| journal=The Anatomical Record| volume=259| issue=3| pages=312–326|year = 2000|last1 = Stoeckelhuber|first1 = Mechthild| last2=Sliwa| first2=Alexander| last3=Welsch| first3=Ulrich}}</ref>', $page->parsed_text());
  }

  public function testUrlReferencesWithText6() {
      $text = "<ref>Emma Ambrose, Cas Mudde (2015). ''[http://www.tandfonline.com/doi/abs/10.1080/13537113.2015.1032033 Canadian Multiculturalism and the Absence of the Far Right]'' Nationalism and Ethnic Politics Vol. 21 Iss. 2.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals('<ref>{{Cite journal | doi=10.1080/13537113.2015.1032033|title = Canadian Multiculturalism and the Absence of the Far Right| journal=Nationalism and Ethnic Politics| volume=21| issue=2| pages=213–236|year = 2015|last1 = Ambrose|first1 = Emma| last2=Mudde| first2=Cas}}</ref>', $page->parsed_text());
  }
 
  public function testUrlReferencesWithText7() {
      $text = "<ref>Gregory, T. Ryan. (2008). [https://link.springer.com/article/10.1007/s12052-007-0001-z ''Evolution as Fact, Theory, and Path'']. ''Evolution: Education and Outreach'' 1 (1): 46–52.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals('<ref>{{Cite journal | doi=10.1007/s12052-007-0001-z|title = Evolution as Fact, Theory, and Path| journal=Evolution: Education and Outreach| volume=1| pages=46–52|year = 2008|last1 = Gregory|first1 = T. Ryan}}</ref>', $page->parsed_text());
  }
 
  public function testUrlReferencesWithText8() {
      $text = "<ref>James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals('<ref>{{Cite journal | doi=10.1145/358589.358596|title = Improving computer program readability to aid modification| journal=Communications of the ACM| volume=25| issue=8| pages=512–521|year = 1982|last1 = Elshoff|first1 = James L.| last2=Marcotty| first2=Michael}}</ref>', $page->parsed_text());
  }
 
  public function testUrlReferencesWithText9() { // Two "urls"
      $text = "<ref>http James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals($text, $page->parsed_text());
  }

  public function testUrlReferencesWithText10() { // See also
      $text = "<ref>See Also, James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals($text, $page->parsed_text());
  }
 
  public function testUrlReferencesWithText11() { // Two bad ones.  Make sure we do not loop or anything 
      $text = "<ref>See Also, James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $text = $text . $text;
      $page = $this->process_page($text);
      $this->assertEquals($text, $page->parsed_text());
  }
 
  public function testUrlReferencesWithText12() {  // One that does not work and returns exact same text
      $text = "<ref>James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.FAKER_DOES_NOT_WORK358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals($text, $page->parsed_text());
  }
 
  public function testUrlReferencesWithText13() {
      $text = "<ref></ref><ref>James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals($text, $page->parsed_text());
  }
  
  public function testUrlReferencesWithText14() {
      $text = "<ref>{{cite web}}</ref><ref>{{cite web}}</ref><ref>James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertEquals('<ref>{{cite web}}</ref><ref>{{cite web}}</ref><ref>{{Cite journal | doi=10.1145/358589.358596|title = Improving computer program readability to aid modification| journal=Communications of the ACM| volume=25| issue=8| pages=512–521|year = 1982|last1 = Elshoff|first1 = James L.| last2=Marcotty| first2=Michael}}</ref>', $page->parsed_text());
  }
 
  public function testRespectDatesZotero() {
      $text = '{{Use mdy dates}}{{cite web|url=https://www.nasa.gov/content/profile-of-john-glenn}}';
      $page = $this->process_page($text);
      $this->assertTrue((boolean) strpos($page->parsed_text(), '12-05-2016'));
      $text = '{{Use dmy dates}}{{cite web|url=https://www.nasa.gov/content/profile-of-john-glenn}}';
      $page = $this->process_page($text);
      $this->assertTrue((boolean) strpos($page->parsed_text(), '05-12-2016'));
  }
 
  public function testBadPage() {  // Use this when debugging pages that crash the bot
    $bad_page = ""; //  Replace with something like "Vietnam_War" when debugging
    if ($bad_page !== "") {
      $text = file_get_contents('https://en.wikipedia.org/w/index.php?title=' . $bad_page . '&action=raw');
      $page = new TestPage();
      $page->parse_text($text);
      $page->expand_text();
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
}
