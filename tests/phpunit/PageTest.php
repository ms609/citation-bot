<?php
declare(strict_types=1);

/*
 * Tests for Page.php
 */

require_once(__DIR__ . '/../testBaseClass.php');
 
final class PageTest extends testBaseClass {

  public function testPageChangeSummary1() : void {
      $page = $this->process_page('{{cite journal|chapter=chapter name|title=book name}}'); // Change to book from journal
      $this->assertSame('Alter: template type. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }

  public function testPageChangeSummary2() : void {
      $page = $this->process_page('{{cite book||quote=a quote}}'); // Just lose extra pipe
      $this->assertSame('Misc citation tidying. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }

  public function testPageChangeSummary31() : void {
      $page = $this->process_page('<ref>http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x</ref>');
      $this->assertSame('Alter: template type. Add: pages, issue, volume, journal, year, title, doi, authors 1-2. Converted bare reference to cite template. Formatted [[WP:ENDASH|dashes]]. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());                
  }
 
  public function testPageChangeSummary32() : void { // Mixture of droping chapter-url and moving URL to chapter-url.  Bogus template content
      $page = $this->process_page('{{cite book|chapter=X|chapter-url= https://mathscinet.ams.org/mathscinet-getitem?mr=2320282|last1=X|last2=X|first1=X|first2=X |url= https://books.google.com/books?id=to0yXzq_EkQC&pg=PP154|title=Y|isbn=XXX|year=XXX}}');
      $this->assertSame('Add: mr, date. Removed parameters. Some additions/deletions were parameter name changes. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());                
  }

  public function testPageChangeSummary4() : void {
      $page = $this->process_page('{{cite web|<!-- comment --> journal=Journal Name}}'); // Comment BEFORE parameter
      $this->assertSame('Alter: template type. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
      $this->assertSame('{{cite journal|<!-- comment --> journal=Journal Name}}', $page->parsed_text());
  }

  public function testPageChangeSummary5() : void {
      $page = $this->process_page('{{cite web|journal<!-- comment -->=Journal Name}}'); // Comment AFTER parameter
      $this->assertSame('Alter: template type. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
      $this->assertSame('{{cite journal|journal<!-- comment -->=Journal Name}}', $page->parsed_text());
  }

  public function testPageChangeSummary7() : void {
      $page = $this->process_page('{{cite news|url=http://zbmath.org/?format=complete&q=an:1111.22222}}'); // Very little done to cite news
      $this->assertSame('Add: zbl. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }
 
  public function testPageChangeSummary8() : void {
      $page = $this->process_page('{{cite journal|chapter-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234|title=mr=1234}}');
      $this->assertSame('{{cite journal|url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234|title=mr=1234|mr = 1234}}', $page->parsed_text());
      $this->assertSame('Add: mr, url. Removed proxy or dead URL that duplicated free-DOI or unique identifier. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }
  public function testPageChangeSummary9() : void {
      $page = $this->process_page('{{cite journal|chapterurl=https://mathscinet.ams.org/mathscinet-getitem?mr=1234|title=mr=1234}}');
      $this->assertSame('{{cite journal|url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234|title=mr=1234|mr = 1234}}', $page->parsed_text());
      $this->assertSame('Add: mr, url. Removed proxy or dead URL that duplicated free-DOI or unique identifier. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }
   
  public function testPageChangeSummary10() : void {
      $page = $this->process_page('{{cite journal|distribution-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234|title=mr=1234}}');
      $this->assertSame('Add: contribution-url. Removed parameters. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }
 
  public function testPageChangeSummary11() : void {
      $page = $this->process_page('{{cite journal|accessdate=12 Nov 2000}}');
      $this->assertSame('Removed access-date with no URL. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }
 
  public function testPageChangeSummary12() : void {
      $page = $this->process_page('{{cite journal|chapter-url=http://www.facebook.com/|title=X|journal=Y}}');
      $this->assertSame('Add: url. Removed proxy or dead URL that duplicated free-DOI or unique identifier. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }
 
  public function testPageChangeSummary13() : void {
      $page = $this->process_page('{{cite journal|notestitle=X}}');
      $this->assertSame('Alter: template type. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }
 
  public function testBotReadblocked() : void {
   $this->requires_secrets(function() : void {
      $page = new TestPage();
      $api = new WikipediaBot();
      $page->get_text_from('User:Blocked Testing Account/readtest', $api);
      $this->assertSame('', $page->parsed_text()); // We will not read anything since it is blocked!
   });
  }
 
  public function testBotRead() : void {
   $this->requires_secrets(function() : void {
      $page = new TestPage();
      $api = new WikipediaBot();
      $page->get_text_from('User:Citation_bot', $api);
      $this->assertTrue(strlen($page->parsed_text()) > 200);
   });
  }
 
  public function testBotReadNonExistant() : void {
   $this->requires_secrets(function() : void {
      $page = new TestPage();
      $api = new WikipediaBot();
      $this->assertSame(FALSE, $page->get_text_from('User:Blocked Testing Account/readtest/NOT_REAL_EVER', $api));
   });
  }
 
  public function testDontCrashOnDates() : void { // See zotero test testRespectDates for actually making sure that it is used
      $text = '{{Use dmy dates}}{{cite web}}';
      $page = $this->process_page($text);
      $text = '{{Use mdy dates}}{{cite web}}';
      $page = $this->process_page($text);
      $text = '{{Use mdy dates}}{{Use dmy dates}}{{cite web}}';
      $page = $this->process_page($text);
      $text = '{{dmy}}{{cite web}}';
      $page = $this->process_page($text);
      $text = '{{mdy}}{{cite web}}';
      $page = $this->process_page($text);
      $text = '{{mdy}}{{dmy}}{{cite web}}';
      $page = $this->process_page($text);
      $this->assertNull(NULL);
  }
 
  public function testBotReadRedirect() : void {
   $this->requires_secrets(function() : void {
      $page = new TestPage();
      $api = new WikipediaBot();
      $this->assertSame(FALSE, $page->get_text_from('Wikipedia:UCB', $api));
   });
  }

  public function testBotReadInvalidNamespace() : void {
   $this->requires_secrets(function() : void {
      $page = new TestPage();
      $api = new WikipediaBot();
      $this->assertSame(FALSE, $page->get_text_from('Bogus:UCBdfasdsfasdfd', $api));
   });
  }
 
  public function testBotReadInvalidPage() : void {
   $this->requires_secrets(function() : void {
      $page = new TestPage();
      $api = new WikipediaBot();
      $this->assertSame(FALSE, $page->get_text_from('.', $api));
   });
  }
  
  public function testBotExpandWrite() : void {
   $this->requires_secrets(function() : void {
      $api = new WikipediaBot();
      $page = new TestPage();
      $writeTestPage = 'User:Blocked Testing Account/writetest';
      $page->get_text_from($writeTestPage, $api);
      $trialCitation = '{{Cite journal | title Bot Testing | ' .
        'doi_broken_date=1986-01-01 | doi = 10.1038/nature09068}}';
      $page->overwrite_text($trialCitation);
      $page_result = $page->write($api, "Testing bot write function");
      if (TRAVIS && !$page_result) {
        echo 'T';  // ! API call failed: '''Your IP address is in a range which has been blocked on all wikis.''' The block was made by [//meta.wikimedia.org/wiki/User:Jon_Kolbert Jon Kolbert] (meta.wikimedia.org). The reason given is ''[[m:NOP|Open Proxy]]: Colocation webhost - Contact [[m:Special:Contact/stewards|stewards]] if you are affected ''. * Start of block: 02:23, 27 October 2019 * Expiration of block: 02:23, 27 October 2021
        ob_flush();
        $this->assertTrue(TRUE); // make CI happy
        return;
      } else {
        $this->assertTrue($page_result);
      }
      $page->get_text_from($writeTestPage, $api);
      $this->assertSame($trialCitation, $page->parsed_text());
      $page->expand_text();
      $this->assertTrue(strpos($page->edit_summary(), 'journal, ') > 3);
      $this->assertTrue(strpos($page->edit_summary(), ' Removed ') > 3);
      $this->assertTrue($page->write($api));
      
      $page->get_text_from($writeTestPage, $api);
      $this->assertTrue(strpos($page->parsed_text(), 'Nature') > 5);
   });
  }
 
  public function testNobots() : void {
    $this->requires_secrets(function() : void {
      $api = new WikipediaBot();
      $text = '{{cite thesis|url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}{{nobots}}';
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
      $this->assertSame(FALSE, $page->write($api, "Testing bot write function"));
   });
  }
 
  public function testNobots2() : void {
     $this->requires_secrets(function() : void {
      $api = new WikipediaBot();
      $text = '{{cite thesis|url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}{{bots|allow=not_you}}';
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
      $this->assertSame(FALSE, $page->write($api, "Testing bot write function"));
   });
  }
 
  public function testEmptyPage() : void {
      foreach (['', '  ', " \n ", '  move along, nothing to see here ', '  move along, nothing to see here {{}} ', ' }}}}{{{{ ', '{{{{}}', '{{{{    }}', '{{{{}}}}}}}}'] as $text) {
        $page = $this->process_page($text);
        $this->assertSame($text, $page->parsed_text());
      }
  }

  public function testUrlReferences() : void {
      $page = $this->process_page("URL reference test 1 <ref name='bob'>http://doi.org/10.1007/s12668-011-0022-5< / ref>\n Second reference: \n<ref >  [https://www.ncbi.nlm.nih.gov/pmc/articles/PMC3705692/] </ref> URL reference test 1");
      $this->assertSame("URL reference test 1 <ref name='bob'>{{Cite journal|url=http://doi.org/10.1007/s12668-011-0022-5|doi = 10.1007/s12668-011-0022-5|title = Reoccurring Patterns in Hierarchical Protein Materials and Music: The Power of Analogies|year = 2011|last1 = Giesa|first1 = Tristan|last2 = Spivak|first2 = David I.|last3 = Buehler|first3 = Markus J.|journal = Bionanoscience|volume = 1|issue = 4|pages = 153–161|arxiv = 1111.5297|s2cid = 5178100}}< / ref>\n Second reference: \n<ref >{{Cite journal|pmc = 3705692|year = 2013|last1 = Mahajan|first1 = P. T.|last2 = Pimple|first2 = P.|last3 = Palsetia|first3 = D.|last4 = Dave|first4 = N.|last5 = De Sousa|first5 = A.|title = Indian religious concepts on sexuality and marriage|journal = Indian Journal of Psychiatry|volume = 55|issue = Suppl 2|pages = S256–S262|doi = 10.4103/0019-5545.105547|pmid = 23858264}}</ref> URL reference test 1", $page->parsed_text());
  }
 
  public function testUrlReferencesAA() : void {
      $page = $this->process_page(" text <ref name='dog' > 10.1063/1.2263373 </ref>");
      $this->assertTrue((bool) strpos($page->parsed_text(), 'title'));
  }
 
  public function testUrlReferencesBB() : void {
      $page = $this->process_page(" text <ref name='dog' >[http://doi.org/10.1007/s12668-011-0022-5 http://doi.org/10.1007/s12668-011-0022-5]</ref>");
      $this->assertTrue((bool) strpos($page->parsed_text(), 'title'));
  }

  public function testUrlReferencesThatFail() : void {
      $text = 'testUrlReferencesThatFail <ref name="bob">http://this.fails/nothing< / ref> testUrlReferencesThatFail <ref >  http://this.fails/nothing </ref> testUrlReferencesThatFail <ref>10.1234/ABCDEFGHIJ.faker</ref>';
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
 
   public function testUrlReferencesWithText0() : void {
      $text = "<ref>{{doi|10.2307/962034}}</ref>";
      $page = $this->process_page($text);
      $this->assertSame('<ref>{{Cite journal|doi = 10.2307/962034|jstor = 962034|title = Alban Berg, Wilhelm Fliess and the Secret Programme of the Violin Concerto|last1 = Jarman|first1 = Douglas|journal = The Musical Times|year = 1983|volume = 124|issue = 1682|pages = 218–223}}</ref>', $page->parsed_text());
  }
 
  public function testUrlReferencesWithText1() : void {
      $text = "<ref>Jarman, D. (1983). [https://www.jstor.org/discover/10.2307/962034?uid=3738032&amp;uid=373072751&amp;uid=2&amp;uid=3&amp;uid=60&amp;sid=21102523353593 Alban Berg, Wilhelm Fliess and the Secret Programme of the Violin Concerto]. ''The Musical Times'' Vol. 124, No. 1682 (Apr. 1983), pp. 218–223</ref>";
      $page = $this->process_page($text);
      $this->assertSame('<ref>{{Cite journal|url=https://www.jstor.org/stable/962034?uid=3738032&amp;uid=373072751&amp;uid=2&amp;uid=3&amp;uid=60&amp;sid=21102523353593|jstor=962034|doi=10.2307/962034|title=Alban Berg, Wilhelm Fliess and the Secret Programme of the Violin Concerto|last1=Jarman|first1=Douglas|journal=The Musical Times|year=1983|volume=124|issue=1682|pages=218–223}}</ref>', $page->parsed_text());
  }
  
  public function testUrlReferencesWithText2() : void {
      $text = "<ref>[[Murray Gell-Mann]] (1995) &quot;[http://onlinelibrary.wiley.com/doi/10.1002/cplx.6130010105/pdf What is complexity? Remarks on simplicity and complexity by the Nobel Prize-winning author of The Quark and the Jaguar]&quot; ''Complexity'' states the 'algorithmic information complexity' (AIC) of some string of bits is the shortest length computer program which can print out that string of bits.</ref>";
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
  
  public function testUrlReferencesWithText3() : void {
      $text = "<ref>Raymond O.  Silverstein, &quot;A note on the term 'Bantu' as first used by W. H. I. Bleek&quot;, ''African Studies'' 27 (1968), 211–212, [https://www.doi.org/10.1080/00020186808707298 doi:10.1080/00020186808707298].</ref>";
      $page = $this->process_page($text);
      $this->assertSame('<ref>{{Cite journal|url=https://www.doi.org/10.1080/00020186808707298|doi = 10.1080/00020186808707298|title = A note on the term "Bantu" as first used by W. H. I. Bleek|year = 1968|last1 = Silverstein|first1 = Raymond O.|journal = African Studies|volume = 27|issue = 4|pages = 211–212}}</ref>', $page->parsed_text());
  }
  
  public function testUrlReferencesWithText4() : void { // Has [[ ]] in it
      $text = "<ref>[[Chandra Prakash Kala|Kala, C.P.]] and Ratajc, P. 2012.[https://rd.springer.com/article/10.1007/s10531-012-0246-x &quot;High altitude biodiversity of the Alps and the Himalayas: ethnobotany, plant distribution and conservation perspective&quot;.] ''Biodiversity and Conservation'', 21 (4): 1115–1126.</ref>";
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
  
  public function testUrlReferencesWithText5() : void {
      $text = "<ref>Stoeckelhuber, Mechthild, Alexander Sliwa, and Ulrich Welsch. &quot;[http://onlinelibrary.wiley.com/doi/10.1002/1097-0185(20000701)259:3%3C312::AID-AR80%3E3.0.CO;2-X/full Histo‐physiology of the scent‐marking glands of the penile pad, anal pouch, and the forefoot in the aardwolf (Proteles cristatus)].&quot; The anatomical record 259.3 (2000): 312-326.</ref>";
      $page = $this->process_page($text);
      $this->assertSame('<ref>{{Cite journal|url=http://onlinelibrary.wiley.com/doi/10.1002/1097-0185(20000701)259:3%3C312::AID-AR80%3E3.0.CO;2-X/full|doi=10.1002/1097-0185(20000701)259:3<312::AID-AR80>3.0.CO;2-X|title=Histo-physiology of the scent-marking glands of the penile pad, anal pouch, and the forefoot in the aardwolf (Proteles cristatus)|year=2000|last1=Stoeckelhuber|first1=Mechthild|last2=Sliwa|first2=Alexander|last3=Welsch|first3=Ulrich|journal=The Anatomical Record|volume=259|issue=3|pages=312–326|pmid=10861364}}</ref>', $page->parsed_text());
  }

  public function testUrlReferencesWithText6() : void {
      $text = "<ref>Emma Ambrose, Cas Mudde (2015). ''[http://www.tandfonline.com/doi/abs/10.1080/13537113.2015.1032033 Canadian Multiculturalism and the Absence of the Far Right]'' Nationalism and Ethnic Politics Vol. 21 Iss. 2.</ref>";
      $page = $this->process_page($text);
      $this->assertSame('<ref>{{Cite journal|url=http://www.tandfonline.com/doi/abs/10.1080/13537113.2015.1032033|doi = 10.1080/13537113.2015.1032033|title = Canadian Multiculturalism and the Absence of the Far Right|year = 2015|last1 = Ambrose|first1 = Emma|last2 = Mudde|first2 = Cas|journal = Nationalism and Ethnic Politics|volume = 21|issue = 2|pages = 213–236|s2cid = 145773856}}</ref>', $page->parsed_text());
  }
 
  public function testUrlReferencesWithText7() : void {
      $text = "<ref>Gregory, T. Ryan. (2008). [https://link.springer.com/article/10.1007/s12052-007-0001-z ''Evolution as Fact, Theory, and Path'']. ''Evolution: Education and Outreach'' 1 (1): 46–52.</ref>";
      $page = $this->process_page($text);
      $this->assertSame('<ref>{{Cite journal|url=https://link.springer.com/article/10.1007/s12052-007-0001-z|doi = 10.1007/s12052-007-0001-z|title = Evolution as Fact, Theory, and Path|year = 2008|last1 = Gregory|first1 = T. Ryan|journal = Evolution: Education and Outreach|volume = 1|pages = 46–52|s2cid = 19788314}}</ref>', $page->parsed_text());
  }
 
  public function testUrlReferencesWithText8() : void {
      $text = "<ref>James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertSame('<ref>{{Cite journal|url=http://doi.acm.org/10.1145/358589.358596|doi = 10.1145/358589.358596|title = Improving computer program readability to aid modification|year = 1982|last1 = Elshoff|first1 = James L.|last2 = Marcotty|first2 = Michael|journal = Communications of the ACM|volume = 25|issue = 8|pages = 512–521}}</ref>', str_replace('|s2cid = 30026641', '', $page->parsed_text())); // Sometimes do not get s2cid
  }
 
  public function testUrlReferencesWithText9() : void { // Two "urls"
      $text = "<ref>http James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }

  public function testUrlReferencesWithText10() : void { // See also
      $text = "<ref>See Also, James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
 
  public function testUrlReferencesWithText11() : void { // Two bad ones.  Make sure we do not loop or anything 
      $text = "<ref>See Also, James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $text = $text . $text;
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
 
  public function testUrlReferencesWithText12() : void {  // One that does not work and returns exact same text
      $text = "<ref>James L. Elshoff, Michael Marcotty, [http://fake.url/10.1145/358589.FAKER_DOES_NOT_WORK358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
 
  public function testUrlReferencesWithText13() : void {
      $text = "<ref></ref><ref>James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
  
  public function testUrlReferencesWithText14() : void {
      $text = "<ref>{{cite web}}</ref><ref>{{cite web}}</ref><ref>James L. Elshoff, Michael Marcotty, [http://doi.acm.org/10.1145/358589.358596 Improving computer program readability to aid modification], Communications of the ACM, v.25 n.8, p.512-521, Aug 1982.</ref>";
      $page = $this->process_page($text);
      $this->assertSame('<ref>{{cite web}}</ref><ref>{{cite web}}</ref><ref>{{Cite journal|url=http://doi.acm.org/10.1145/358589.358596|doi = 10.1145/358589.358596|title = Improving computer program readability to aid modification|year = 1982|last1 = Elshoff|first1 = James L.|last2 = Marcotty|first2 = Michael|journal = Communications of the ACM|volume = 25|issue = 8|pages = 512–521|s2cid = 30026641}}</ref>', $page->parsed_text());
  }
 
   public function testUrlReferencesWithText15() : void {
      $text = "<ref>[http://doi.acm.org/10.1145/358589.358596 http://doi.acm.org/10.1145/358589.3585964444]</ref>";
      $text = $text . $text;
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
 
  public function testUrlReferencesWithText16() : void {
    $this->requires_arxiv(function() : void {
      $text = "<ref>{{arxiv|0806.0013}}</ref>";
      $page = $this->process_page($text);
      $this->assertTrue((bool) stripos($page->parsed_text(), 'PhysRevD.78.081701'));
    });
  }
                        
  public function testUrlReferencesWithText17() : void {
    $this->requires_google(function() : void {
      $text = "<ref>{{oclc|23454}}</ref>";
      $page = $this->process_page($text);
      $this->assertTrue((bool) strpos($page->parsed_text(), 'it'));
    });
  }                    
                        
  public function testMagazine() : void {
      $text = '{{cite magazine|work=Yup}}';
      $page = $this->process_page($text);
      $this->assertTrue((bool) strpos($page->parsed_text(), 'magazine=Yup'));
  }

  public function testThesis() : void {
      $text = '{{cite thesis|url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('1234', $expanded->get2('mr'));
  }
 
  public function testNobots4() : void {
      $text = '{{cite thesis|url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}{{bots|allow=Citation Bot}}';
      $page = $this->process_page($text);
      $this->assertSame('{{cite thesis|url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234|mr = 1234}}{{bots|allow=Citation Bot}}', $page->parsed_text());
      $text = '{{cite thesis|url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}{{bots|allow=none}}';
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
 
  public function testODNB() : void {
   $text='{{Cite ODNB|title=Pierrepoint, Albert, (1905–1992)|ref=harv}} {{ODNBsub}}';
   $page = $this->process_page($text);
   $this->assertSame('{{Cite ODNB|title=Pierrepoint, Albert, (1905–1992)|ref=harv}} ', $page->parsed_text());
   $text='{{Cite ODNB|title=Pierrepoint, Albert,_(1905–1992)|ref=harv}} {{ODNBsub}}';
   $page = $this->process_page($text);
   $this->assertSame('{{Cite ODNB|title=Pierrepoint, Albert,_(1905–1992)|ref=harv}} ', $page->parsed_text());
   $text='{{Cite ODNB|title=Pierrepoint,_Albert,_(1905–1992)|ref=harv}} {{ODNBsub}}';
   $page = $this->process_page($text);
   $this->assertSame($text, $page->parsed_text()); // two underscores
   $text='{{Cite ODNB|title=Pierrepoint, Albert, (1905–1992)|ref=harv}}{{Yup}}{{ODNBsub}}';
   $page = $this->process_page($text);
   $this->assertSame($text, $page->parsed_text()); // template in the way
  }
 
  public function testBadWikiTextPage() : void {
      $text = "{{cite journal|doi=10.2307/962034}}{{cite journal|<ref></ref>doi=10.2307/962034}}";
      $page = $this->process_page($text);
      $this->assertSame($text, $page->parsed_text());
  }
 
  public function testWikiTitlePrint() : void {
    $text = 'John Smith';
    $output = wiki_link($text);
    $this->assertSame('Wikipedia page : John Smith', $output);
  }
 
  public function testCiteLSA() : void {
    $text = "{{Cite LSA|url=https://books.google.uk.co/books?id=to0yXzq_EkQC&pg=}}";
    $page = $this->process_page($text);
    $this->assertSame("{{Cite LSA|url=https://books.google.com/books?id=to0yXzq_EkQC}}", $page->parsed_text());
    $this->assertSame('Misc citation tidying. | You can [[WP:UCB|use this bot]] yourself. [[WP:DBUG|Report bugs here]]. ', $page->edit_summary());
  }
 
  public function testBadPage() : void {  // Use this when debugging pages that crash the bot
    $bad_page = ""; //  Replace with page name when debugging
    $bad_page = urlencode(str_replace(' ', '_', $bad_page));
    if ($bad_page !== "") {
      $ch = curl_init();
      curl_setopt_array($ch,
           [CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
            CURLOPT_URL => WIKI_ROOT . '?title=' . $bad_page . '&action=raw']);
      $text = curl_exec($ch);
      curl_close($ch);
      $page = new TestPage();
      $page->parse_text($text);
      AdsAbsControl::back_on();
      Zotero::unblock_zotero();
      $page->expand_text();
      AdsAbsControl::give_up();
      Zotero::block_zotero();
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
 
  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot using API to get page
    $bad_page = ""; //  Replace with page name when debugging
    $bad_page = str_replace(' ', '_', $bad_page);
    if ($bad_page !== "") {
      $api = new WikipediaBot();
      $page = new TestPage();
      $page->get_text_from($bad_page, $api);
      AdsAbsControl::back_on();
      Zotero::unblock_zotero();
      $page->expand_text();
      AdsAbsControl::give_up();
      Zotero::block_zotero();
      $this->assertTrue(FALSE); // prevent us from git committing with a website included
    }
    $this->assertTrue(TRUE);
  }
}
