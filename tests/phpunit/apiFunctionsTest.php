<?php
declare(strict_types=1);

require_once __DIR__ . '/../testBaseClass.php';

// Some of these are unit tests that poke specific funtions that do not require actually connecting to adsabs

final class apiFunctionsTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }

  public function testAdsabsApi() : void {
    $this->requires_bibcode(function() : void {
      $bibcodes = [
       '2017NatCo...814879F', // 0
       '1974JPal...48..524M', // 1
       '1996GSAB..108..195R', // 2
       '1966Natur.211..116M', // 3
       '1995Sci...267...77R', // 4
       '1995Geo....23..967E', // 5
       '2003....book.......', // 6 - book - bogus to test year only code
       '2000A&A...361..952H', // 7 - & symbol
       '1995astro.ph..8159B', // 8 - arxiv
       '1932Natur.129Q..18.', // 9 - dot end
       '2019arXiv190502552Q', // 10 - new arxiv
       ];
      $text = '{{Cite journal | bibcode = ' . implode('}}{{Cite journal | bibcode = ', $bibcodes) . '}}';
      $page = new TestPage();
      $page->parse_text($text);
      $templates = $page->extract_object('Template');
      $page->expand_templates_from_identifier('bibcode', $templates);
      $this->assertSame('14879', $templates[0]->get2('pages') . $templates[0]->get2('page'));
      $this->assertSame('Journal of Paleontology', $templates[1]->get2('journal'));
      $this->assertSame('Geological Society of America Bulletin', $templates[2]->get2('journal'));
      $this->assertSame('Nature', $templates[3]->get2('journal'));
      $this->assertSame('Science', $templates[4]->get2('journal'));
      $this->assertSame('Geology', $templates[5]->get2('journal'));
      $this->assertNull($templates[6]->get2('journal'));
      $this->assertNull($templates[6]->get2('title'));
      $this->assertSame('2003', $templates[6]->get2('year'));
      $this->assertSame('Astronomy and Astrophysics', $templates[7]->get2('journal'));
      $this->assertNull($templates[8]->get2('pages'));
      $this->assertNull($templates[8]->get2('page'));
      $this->assertNull($templates[8]->get2('class'));
      $this->assertSame('astro-ph/9508159', $templates[8]->get2('arxiv'));
      $this->assertSame('Nature', $templates[9]->get2('journal'));
      $this->assertSame('1905.02552', $templates[10]->get2('arxiv'));
      $this->assertNull($templates[10]->get2('journal'));
    });
    
    // Mostly just for code coverage, make sure code does not seg fault.
    $text = "fafa3faewf34af";
    $this->assertSame($text, bibcode_link($text));

    // Now verify that lack of requires_bibcode() blocks API in tests
    $bibcodes = [
       '2017NatCo...814879F', // 0
       '1974JPal...48..524M', // 1
       '1996GSAB..108..195R', // 2
       '1966Natur.211..116M', // 3
       '1995Sci...267...77R', // 4
       '1995Geo....23..967E', // 5
       '2003hoe..book.....K', // 6 - book
       '2000A&A...361..952H', // 7 - & symbol
       '1995astro.ph..8159B', // 8 - arxiv
       '1932Natur.129Q..18.', // 9 - dot end
       '2019arXiv190502552Q', // 10 - new arxiv
       ];
    $text = '{{Cite journal | bibcode = ' . implode('}}{{Cite journal | bibcode = ', $bibcodes) . '}}';
    $page = new TestPage();
    $page->parse_text($text);
    $this->assertSame($text, $page->parsed_text($text));
  }
  
  public function testArxivDateUpgradeSeesDate1() : void {
      $text = '{{Cite journal|date=September 2010|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('September 2010', $expanded->get2('date'));
      $this->assertNull($expanded->get2('year'));
  }
  public function testArxivDateUpgradeSeesDate2() : void {
      $text = '{{Cite journal|date=September 2009|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get2('date'));
      $this->assertSame('2010', $expanded->get2('year'));
  }
  
  public function testExpansion_doi_not_from_crossrefRG() : void {
     $text = '{{Cite journal| doi= 10.13140/RG.2.1.1002.9609}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('Lesson Study as a form of in-School Professional Development', $expanded->get2('title'));
     $this->assertSame('2015', $expanded->get2('year'));
     $this->assertSame('Aoibhinn Ni Shuilleabhain', $expanded->get2('author1'));
  }
  
   public function testExpansion_doi_not_from_crossrefJapanJournal() : void {
     $text = '{{cite journal|doi=10.11429/ppmsj1919.17.0_48}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('On the Interaction of Elementary Particles. I', $expanded->get2('title'));
     $this->assertSame('1935', $expanded->get2('year'));
     $this->assertSame('Proceedings of the Physico-Mathematical Society of Japan. 3Rd Series', $expanded->get2('journal'));
     $this->assertSame('17', $expanded->get2('volume'));
     $this->assertSame('YUKAWA', $expanded->get2('last1'));
     $this->assertSame('Hideki', $expanded->get2('first1'));
  }
  // See https://www.doi.org/demos.html  NOT ALL EXPAND AT THIS TIME
  public function testExpansion_doi_not_from_crossrefBook() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1017/CBO9780511983658');  // This is cross-ref doi, so for DX DOI expansion
     $this->assertSame('{{Cite book| year=1996 | isbn=9780521572903 | last1=Luo | first1=Zhi-Quan | last2=Pang | first2=Jong-Shi | last3=Ralph | first3=Daniel | title=Mathematical Programs with Equilibrium Constraints | publisher=Cambridge University Press }}', $expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefBookChapter() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1002/0470841559.ch1');  // This is cross-ref doi, so for DX DOI expansion
     $this->assertSame('{{Cite book| year=2003 | title=Internetworking LANs and WANs | chapter=Network Concepts | publisher=John Wiley & Sons | location=Chichester, UK }}', $expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefDataCiteSubsets() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/PANGAEA.726855');
     $this->assertSame('{{Cite journal| year=2009 | last1=Irino | first1=Tomohisa | last2=Tada | first2=Ryuji | title=Chemical and mineral compositions of sediments from ODP Site 127-797, supplement to: Irino, Tomohisa; Tada, Ryuji (2000): Quantification of aeolian dust (Kosa) contribution to the Japan Sea sediments and its variation during the last 200 ky. Geochemical Journal, 34(1), 59-93 }}', $expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossrefDataCiteEarthquake() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/GFZ.GEOFON.gfz2009kciu');
     $this->assertSame('{{Cite journal| year=2009 | author1=Geofon Operator | title=GEOFON event gfz2009kciu (NW Balkan Region) | publisher=Deutsches GeoForschungsZentrum GFZ }}', $expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefDataCiteMappedVisualization() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/PANGAEA.667386');
     $this->assertSame('{{Cite book| year=2008 | last1=Kraus | first1=Stefan | last2=del Valle | first2=Rodolfo | title=Geological map of Potter Peninsula (King George Island, South Shetland Islands, Antarctic Peninsula) | chapter=Impact of climate induced glacier melt on marine coastal systems, Antarctica (IMCOAST/IMCONet) | publisher=PANGAEA - Data Publisher for Earth & Environmental Science }}', $expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossrefDataCitevideo() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.3207/2959859860');
     $this->assertSame('{{Cite journal| year=2009 | last1=Kirchhof | first1=Bernd | title=Silicone oil bubbles entrapped in the vitreous base during silicone oil removal }}', $expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossref_fISTIC_Journal() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.3866/PKU.WHXB201112303');
     $this->assertSame('{{Cite journal| year=2012 | volume=28 | issue=3 | last1=Yu | first1=ZHANG | last3=Ning | first3=MA | last4=Wei-Zhou | first4=WANG | title=Correlation between Bond-Length Change and Vibrational Frequency Shift in Hydrogen-Bonded Complexes Revisited | journal=Acta Physico-Chimica Sinica }}', $expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossref_fISTIC_Data() : void {
      $expanded = $this->make_citation('{{Cite journal}}');
      expand_doi_with_dx($expanded, '10.3972/water973.0145.db');
      $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
  }
 
  public function testExpansion_doi_not_from_crossref_ISTIC_Thesis() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.7666/d.y351065');
     $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossref_JaLC_Journal() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.11467/isss2003.7.1_11');
     $this->assertSame('{{Cite journal| year=2009 | volume=7 | last1=竹本 | first1=賢太郎 | last2=川東 | first2=正美 | last3=久保 | first3=信行 | last4=左近 | first4=多喜男 | title=大学におけるWebメールとターミナルサービスの研究 | journal=Society for Standardization Studies }}', $expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossref_JaLC_Journal2() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.7875/leading.author.2.e008');
     $this->assertSame('{{Cite journal| year=2013 | volume=2 | last1=川崎 | first1=努. | title=植物における免疫誘導と病原微生物の感染戦略 | journal=領域融合レビュー }}', $expanded->parsed_text());
  }

  public function testExpansion_doi_not_from_crossref_mEDRA_Journal() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1430/8105');
     $this->assertSame("{{Cite journal| year=2002 | issue=4 | author1=Romano Prodi | title=L'Industria dopo l'euro | journal=L'Industria }}", $expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossref_mEDRA_Monograph() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1392/BC1.0');
     $this->assertSame('{{Cite journal| year=2004 | last1=Attanasio | first1=Piero | title=The use of Doi in eContent value chain | publisher=mEDRA }}', $expanded->parsed_text());
  }    

  // http://doi.airiti.com/
  // They allow you to easily find the RA, but they seem to no longer do meta-data http://www.airitischolar.com/doi/WhichRA/index.jsp
  public function testExpansion_doi_not_from_crossref_airiti_journal() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.6620/ZS.2018.57-30');
     $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
     // this used to work $this->assertSame('{{Cite journal|year = 2018|volume = 無|issue = 57|author1 = Jun Aoyama|author2 = Sam Wouthuyzen|author3 = Michael J. Miller|author4 = Hagi Y. Sugeha|author5 = Mari Kuroki|author6 = Shun Watanabe|author7 = Augy Syahailatua|author8 = Fadly Y. Tantu|author9 = Seishi Hagihara|author10 = Triyanto|author11 = Tsuguo Otake|author12 = Katsumi Tsukamoto|title = Reproductive Ecology and Biodiversity of Freshwater Eels around Sulawesi Island Indonesia|journal = Zoological Studies}}', $expanded->parsed_text());
  }

  // http://www.eidr.org/
  public function testExpansion_doi_not_from_crossref_eidr_Black_Panther_Movie() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.5240/7B2F-ED76-31F6-8CFB-4DB9-M');
     $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
  }
 
  // http://www.kisti.re.kr/eng/
  public function testExpansion_doi_not_from_crossref_kisti_journal() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.3743/KOSIM.2011.28.2.117');
     $this->assertSame('{{Cite journal| year=2011 | volume=28 | issue=2 | last1=Kim | first1=Byung-Kyu | last2=Kang | first2=Mu-Yeong | last3=Choi | first3=Seon-Heui | last4=Kim | first4=Soon-Young | last5=You | first5=Beom-Jong | last6=Shin | first6=Jae-Do | title=Citing Behavior of Korean Scientists on Foreign Journals in KSCD | journal=Journal of the Korean Society for Information Management }}', $expanded->parsed_text());
  }
  
  // https://publications.europa.eu/en/
  public function testExpansion_doi_not_from_crossref_europa_monograph() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.2788/14231');
     if ($expanded->has('author1')) {
       $this->assertSame('{{Cite journal| year=2007 | author1=European Commission. Joint Research Centre. Institute for Environment Sustainability | last2=Vogt | first2=Jürgen | last3=Foisneau | first3=Stéphanie | title=European river and catchment database, version 2.0 (CCM2) : Analysis tools | publisher=Publications Office }}', $expanded->parsed_text());
     } else {
       $this->assertSame('{{Cite journal| year=2007 | last1=Vogt | first1=Jürgen | last2=Foisneau | first2=Stéphanie | title=European river and catchment database, version 2.0 (CCM2) : Analysis tools | publisher=Publications Office }}', $expanded->parsed_text());
     }
  }
  
  public function testComplexCrossRef() : void {
     $text = '{{citation | title = Deciding the Winner of an Arbitrary Finite Poset Game is PSPACE-Complete| arxiv = 1209.1750| bibcode = 2012arXiv1209.1750G}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('Deciding the Winner of an Arbitrary Finite Poset Game is PSPACE-Complete', $expanded->get2('chapter'));
     $this->assertSame('Lecture Notes in Computer Science', $expanded->get2('series'));
     $this->assertSame('Automata, Languages, and Programming', $expanded->get2('title'));
  }
  
   public function testThesisDOI() : void {
     $doi = '10.17077/etd.g638o927';
     $text = "{{cite journal|doi=$doi}}";
     $template = $this->make_citation($text);
     expand_doi_with_dx($template, $doi);
     $this->assertSame($doi, $template->get2('doi'));
     $this->assertSame("The caregiver's journey", $template->get2('title'));
     $this->assertSame('The University of Iowa', $template->get2('publisher'));
     $this->assertSame('2018', $template->get2('year'));
     $this->assertSame('Schumacher', $template->get2('last1')); 
     $this->assertSame('Lisa Anne', $template->get2('first1'));
  }
  
   public function testJstor1() : void {
     $text = "{{cite journal|url=https://jstor.org/stable/832414?seq=1234}}";
     $template = $this->make_citation($text);
     $this->assertTrue(expand_by_jstor($template));
     $this->assertSame('832414', $template->get2('jstor'));
   }
  
   public function testJstor2() : void {
     $text = "{{cite journal|jstor=832414?seq=1234}}";
     $template = $this->make_citation($text);
     $this->assertTrue(expand_by_jstor($template));
     $this->assertNull($template->get2('url'));
   }
  
   public function testJstor3() : void {
     $text = "{{cite journal|jstor=123 123}}";
     $template = $this->make_citation($text);
     $this->assertFalse(expand_by_jstor($template));
   }
  
   public function testJstor4() : void {
     $text = "{{cite journal|jstor=i832414}}";
     $template = $this->make_citation($text);
     $this->assertFalse(expand_by_jstor($template));
   }
  
   public function testJstor5() : void {
     $text = "{{cite journal|jstor=4059223|title=This is not the right title}}";
     $template = $this->make_citation($text);
     $this->assertFalse(expand_by_jstor($template));
     $this->assertSame($text, $template->parsed_text());
  }
  
  public function testCrossRefAddSeries1() : void {
     $text = "{{Cite book | doi = 10.1063/1.2833100| title = A Transient Semi-Metallic Layer in Detonating Nitromethane}}";
     $template = $this->process_citation($text);
     $this->assertSame("AIP Conference Proceedings", $template->get2('series'));
  }
  public function testCrossRefAddSeries2() : void {
    // Kind of messed up, but "matches" enough to expand
     $text = "{{Cite book | doi = 10.1063/1.2833100| title = AIP Conference Proceedings}}";
     $template = $this->process_citation($text);
     $this->assertSame("2008", $template->get2('year'));
  }
  
  public function testCrossRefAddEditors() : void {
     $text = "{{Cite book | doi = 10.1117/12.135408}}";
     $template = $this->process_citation($text);
     $this->assertSame("Kopera", $template->get2('editor1-last'));
  }

  public function testBibcodeData1() : void {
     $text = "{{Cite book | bibcode = 2017NatCo...814879F}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '2017NatCo...814879F',
   'author' => 
  array (
    0 => 'Fredin, Ola',
    1 => 'Viola, Giulio',
    2 => 'Zwingmann, Horst',
    3 => 'Sørlie, Ronald',
    4 => 'Brönner, Marco',
    5 => 'Lie, Jan-Erik',
    6 => 'Grandal, Else Margrethe',
    7 => 'Müller, Axel',
    8 => 'Margreth, Annina',
    9 => 'Vogt, Christoph',
    10 => 'Knies, Jochen',
  ),
   'doctype' => 'article',
   'doi' => 
  array (
    0 => '10.1038/ncomms14879',
  ),
   'identifier' => 
  array (
    0 => '2017NatCo...814879F',
    1 => '10.1038/ncomms14879',
  ),
   'page' => 
  array (
    0 => '14879',
  ),
   'pub' => 'Nature Communications',
   'pubdate' => '2017-04-00',
   'title' => 
  array (
    0 => 'The inheritance of a Mesozoic landscape in western Scandinavia',
  ),
   'volume' => '8',
   'year' => '2017',
);
     process_bibcode_data($template, $results);
     $this->assertSame('Nature Communications', $template->get2('journal'));
     $this->assertSame('10.1038/ncomms14879',  $template->get2('doi'));
  }

  public function testBibcodeData2() : void {
     $text = "{{Cite book | bibcode = 1996GSAB..108..195R}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '1996GSAB..108..195R',
   'author' => 
  array (
    0 => 'Retallack, Gregory J.',
    1 => 'Veevers, John J.',
    2 => 'Morante, Ric',
  ),
   'doctype' => 'article',
   'doi' => 
  array (
    0 => '10.1130/0016-7606(1996)108<0195:GCGBPT>2.3.CO;2',
  ),
   'identifier' => 
  array (
    0 => '10.1130/0016-7606(1996)108<0195:GCGBPT>2.3.CO;2',
    1 => '1996GSAB..108..195R',
  ),
   'issue' => '2',
   'page' => 
  array (
    0 => '195DUMMY',
  ),
   'pub' => 'Geological Society of America Bulletin',
   'pubdate' => '1996-02-00',
   'title' => 
  array (
    0 => 'Global coal gap between Permian-Triassic extinction and Middle Triassic recovery of peat-forming plants',
  ),
   'volume' => '108',
   'year' => '1996',
);
     process_bibcode_data($template, $results);
     $this->assertSame('Geological Society of America Bulletin', $template->get2('journal'));
     $this->assertSame('10.1130/0016-7606(1996)108<0195:GCGBPT>2.3.CO;2', $template->get2('doi'));
     $this->assertNull($template->get2('page'));
     $this->assertNull($template->get2('pages')); // Added letters
  }
  
  public function testBibcodeData3() : void {
     $text = "{{Cite book | bibcode = 2000A&A...361..952H}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '2000A&A...361..952H',
   'author' => 
  array (
    0 => 'Hessman, F. V.',
    1 => 'Gänsicke, B. T.',
    2 => 'Mattei, J. A.',
  ),
   'doctype' => 'article',
   'identifier' => 
  array (
    0 => '2000A&A...361..952H',
  ),
   'page' => 
  array (
    0 => '952',
  ),
   'pub' => 'Astronomy and Astrophysics',
   'pubdate' => '2000-09-00',
   'title' => 
  array (
    0 => 'The history and source of mass-transfer variations in AM Herculis',
  ),
   'volume' => '361',
   'year' => '2000',
);
     process_bibcode_data($template, $results);
     $this->assertSame('Astronomy and Astrophysics', $template->get2('journal'));
     $this->assertNull($template->get2('doi'));
     $this->assertSame('952', $template->get2('page') . $template->get2('pages'));
  }
  
  public function testBibcodeData4() : void {
     $text = "{{Cite book | bibcode = 1995Sci...267...77R}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '1995Sci...267...77R',
   'author' => 
  array (
    0 => 'Retallack, G. J.',
  ),
   'doctype' => 'article',
   'doi' => 
  array (
    0 => '10.1126/science.267.5194.77',
  ),
   'identifier' => 
  array (
    0 => '1995Sci...267...77R',
    1 => '10.1126/science.267.5194.77',
  ),
   'issue' => '5194',
   'page' => 
  array (
    0 => '77',
  ),
   'pub' => 'Science',
   'pubdate' => '1995-01-00',
   'title' => 
  array (
    0 => 'Permain-Triassic Life Crisis on Land',
  ),
   'volume' => '267',
   'year' => '1995',
);
     process_bibcode_data($template, $results);
     $this->assertSame('Science', $template->get2('journal'));
     $this->assertSame('10.1126/science.267.5194.77',  $template->get2('doi'));
  }
  
  public function testBibcodeData5() : void {
     $text = "{{Cite book | bibcode = 1995Geo....23..967E}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '1995Geo....23..967E',
   'author' => 
  array (
    0 => 'Eshet, Yoram',
    1 => 'Rampino, Michael R.',
    2 => 'Visscher, Henk',
  ),
   'doctype' => 'article',
   'doi' => 
  array (
    0 => '10.1130/0091-7613(1995)023<0967:FEAPRO>2.3.CO;2',
  ),
   'identifier' => 
  array (
    0 => '1995Geo....23..967E',
    1 => '10.1130/0091-7613(1995)023<0967:FEAPRO>2.3.CO;2',
  ),
   'issue' => '11',
   'page' => 
  array (
    0 => '967',
  ),
   'pub' => 'Geology',
   'pubdate' => '1995-11-00',
   'title' => 
  array (
    0 => 'Fungal event and palynological record of ecological crisis and recovery across the Permian-Triassic boundary',
  ),
   'volume' => '23',
   'year' => '1995',
);
     process_bibcode_data($template, $results);
     $this->assertSame('Geology', $template->get2('journal'));
     $this->assertSame('10.1130/0091-7613(1995)023<0967:FEAPRO>2.3.CO;2',  $template->get2('doi'));
  }
  
  public function testBibcodeData6() : void {
     $text = "{{Cite book | bibcode = 1974JPal...48..524M}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '1974JPal...48..524M',
   'author' => 
  array (
    0 => 'Moorman, M.',
  ),
   'doctype' => 'article',
   'identifier' => 
  array (
    0 => '1974JPal...48..524M',
  ),
   'page' => 
  array (
    0 => '524',
  ),
   'pub' => 'Journal of Paleontology',
   'pubdate' => '1974-05-00',
   'title' => 
  array (
    0 => 'Microbiota of the late Proterozoic Hector Formation, Southwestern Alberta, Canada',
  ),
   'volume' => '48',
   'year' => '1974',
);
     process_bibcode_data($template, $results);
     $this->assertSame('Journal of Paleontology', $template->get2('journal'));
     $this->assertSame('1974',  $template->get2('year'));
  }
  
  public function testBibcodeData7() : void {
     $text = "{{Cite book | bibcode = 1966Natur.211..116M}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '1966Natur.211..116M',
   'author' => 
  array (
    0 => 'Melville, R.',
  ),
   'doctype' => 'article',
   'doi' => 
  array (
    0 => '10.1038/211116a0',
  ),
   'identifier' => 
  array (
    0 => '1966Natur.211..116M',
    1 => '10.1038/211116a0',
  ),
   'issue' => '5045',
   'page' => 
  array (
    0 => '116',
  ),
   'pub' => 'Nature',
   'pubdate' => '1966-07-00',
   'title' => 
  array (
    0 => 'Continental Drift, Mesozoic Continents and the Migrations of the Angiosperms',
  ),
   'volume' => '211',
   'year' => '1966',
);
     process_bibcode_data($template, $results);
     $this->assertSame('Nature', $template->get2('journal'));
     $this->assertSame('10.1038/211116a0',  $template->get2('doi'));
  }
  
  public function testBibcodeData8() : void {
     $text = "{{Cite book | bibcode = 1995astro.ph..8159B}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '1995astro.ph..8159B',
   'arxiv_class' => 
  array (
    0 => 'astro-ph',
    1 => 'hep-ph',
  ),
   'author' => 
  array (
    0 => 'Brandenberger, Robert H.',
  ),
   'doctype' => 'eprint',
   'identifier' => 
  array (
    0 => 'arXiv:astro-ph/9508159',
    1 => '1995astro.ph..8159B',
  ),
   'page' => 
  array (
    0 => 'astro-ph/9508159',
  ),
   'pub' => 'arXiv e-prints',
   'pubdate' => '1995-09-00',
   'title' => 
  array (
    0 => 'Formation of Structure in the Universe',
  ),
   'year' => '1995',
);
     process_bibcode_data($template, $results);
     $this->assertSame('1995', $template->get2('year'));
     $this->assertSame('astro-ph/9508159',  $template->get2('arxiv') . $template->get2('eprint'));
  }
  
  public function testBibcodeData9() : void {
     $text = "{{Cite book | bibcode = 1932Natur.129Q..18.}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '1932Natur.129Q..18.',
   'doctype' => 'article',
   'doi' => 
  array (
    0 => '10.1038/129018a0',
  ),
   'identifier' => 
  array (
    0 => '1932Natur.129Q..18.',
    1 => '10.1038/129018a0',
  ),
   'issue' => '3244',
   'page' => 
  array (
    0 => '18',
  ),
   'pub' => 'Nature',
   'pubdate' => '1932-01-00',
   'title' => 
  array (
    0 => 'Electric Equipment of the Dolomites Railway.',
  ),
   'volume' => '129',
   'year' => '1932',
);
     process_bibcode_data($template, $results);
     $this->assertSame('Nature', $template->get2('journal'));
     $this->assertSame('10.1038/129018a0',  $template->get2('doi'));
  }
  
  public function testBibcodeData10() : void {
     $text = "{{Cite book | bibcode = 2019arXiv190502552Q}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'bibcode' => '2019arXiv190502552Q',
   'arxiv_class' => 
  array (
    0 => 'q-bio.QM',
  ),
   'author' => 
  array (
    0 => 'Qin, Yang',
    1 => 'Freebairn, Louise',
    2 => 'Atkinson, Jo-An',
    3 => 'Qian, Weicheng',
    4 => 'Safarishahrbijari, Anahita',
    5 => 'Osgood, Nathaniel D',
  ),
   'doctype' => 'eprint',
   'identifier' => 
  array (
    0 => '2019arXiv190502552Q',
    1 => 'arXiv:1905.02552',
  ),
   'page' => 
  array (
    0 => 'arXiv:1905.02552',
  ),
   'pub' => 'arXiv e-prints',
   'pubdate' => '2019-05-00',
   'title' => 
  array (
    0 => 'Multi-Scale Simulation Modeling for Prevention and Public Health Management of Diabetes in Pregnancy and Sequelae',
  ),
   'year' => '2019',
);
     process_bibcode_data($template, $results);
     $this->assertSame('2019', $template->get2('year'));
     $this->assertSame('1905.02552',  $template->get2('arxiv') . $template->get2('eprint'));
  }
  
  public function testBibcodeData11() : void {
     $text = "{{Cite book | bibcode = 2003....book.......}}";
     $template = $this->make_citation($text);
     $results = (object) array();
     expand_book_adsabs($template, $results);
     $this->assertSame('2003', $template->get2('year'));
  }

  public function testBibcodeData12() : void {
     $text = "{{Cite book | bibcode = 1958ses..book.....S}}";
     $template = $this->make_citation($text);
     $results = (object) array(
   'numFound' => 1,
   'start' => 0,
   'docs' => 
  array (
    0 => 
    (object) array(
       'bibcode' => '1958ses..book.....S',
       'author' => 
      array (
        0 => 'Schwarzschild, Martin',
      ),
       'doctype' => 'book',
       'identifier' => 
      array (
        0 => '1958ses..book.....S',
      ),
       'pub' => 'Princeton',
       'pubdate' => '1958-00-00',
       'title' => 
      array (
        0 => 'Structure and evolution of the stars.',
      ),
       'year' => '1958',
    ),
  ),
);
     expand_book_adsabs($template, $results);
     $this->assertSame('1958', $template->get2('year'));
     $this->assertSame('structure and evolution of the stars', strtolower($template->get2('title')));
  }

  public function testBibCodeResponseProcess() : void {
    // Need a valid curl object to abuse
    $ch = curl_init();
    curl_setopt_array($ch,
           [CURLOPT_USERAGENT => BOT_USER_AGENT,
            CURLOPT_HEADER => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_URL => 'https://books.google.com/books/feeds/volumes/to0yXzq_EkQC']);
    @curl_exec($ch);
    $header_length = (int) @curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $return = str_repeat(' ', $header_length) .
       '{
  "responseHeader":{
    "status":0,
    "QTime":42,
    "params":{
      "q":"*:*",
      "fl":"arxiv_class,author,bibcode,doi,doctype,identifier,issue,page,pub,pubdate,title,volume,year",
      "start":"0",
      "internal_logging_params":"X-Amzn-Trace-Id=Root=1-62714946-419bd3e713f436ad3bc35353",
      "fq":"{!bitset}",
      "rows":"2000",
      "wt":"json"}},
  "response":{"numFound":10,"start":0,"docs":[
      {
        "bibcode":"1996GSAB..108..195R",
        "author":["Retallack, Gregory J.",
          "Veevers, John J.",
          "Morante, Ric"],
        "doctype":"article",
        "doi":["10.1130/0016-7606(1996)108<0195:GCGBPT>2.3.CO;2"],
        "identifier":["10.1130/0016-7606(1996)108<0195:GCGBPT>2.3.CO;2",
          "1996GSAB..108..195R"],
        "issue":"2",
        "page":["195"],
        "pub":"Geological Society of America Bulletin",
        "pubdate":"1996-02-00",
        "title":["Global coal gap between Permian-Triassic extinction and Middle Triassic recovery of peat-forming plants"],
        "volume":"108",
        "year":"1996"},
      {
        "bibcode":"1995Sci...267...77R",
        "author":["Retallack, G. J."],
        "doctype":"article",
        "doi":["10.1126/science.267.5194.77"],
        "identifier":["1995Sci...267...77R",
          "10.1126/science.267.5194.77"],
        "issue":"5194",
        "page":["77"],
        "pub":"Science",
        "pubdate":"1995-01-00",
        "title":["Permain-Triassic Life Crisis on Land"],
        "volume":"267",
        "year":"1995"},
      {
        "bibcode":"2000A&A...361..952H",
        "author":["Hessman, F. V.",
          "Gänsicke, B. T.",
          "Mattei, J. A."],
        "doctype":"article",
        "identifier":["2000A&A...361..952H"],
        "page":["952"],
        "pub":"Astronomy and Astrophysics",
        "pubdate":"2000-09-00",
        "title":["The history and source of mass-transfer variations in AM Herculis"],
        "volume":"361",
        "year":"2000"},
      {
        "bibcode":"1995Geo....23..967E",
        "author":["Eshet, Yoram",
          "Rampino, Michael R.",
          "Visscher, Henk"],
        "doctype":"article",
        "doi":["10.1130/0091-7613(1995)023<0967:FEAPRO>2.3.CO;2"],
        "identifier":["1995Geo....23..967E",
          "10.1130/0091-7613(1995)023<0967:FEAPRO>2.3.CO;2"],
        "issue":"11",
        "page":["967"],
        "pub":"Geology",
        "pubdate":"1995-11-00",
        "title":["Fungal event and palynological record of ecological crisis and recovery across the Permian-Triassic boundary"],
        "volume":"23",
        "year":"1995"},
      {
        "bibcode":"1974JPal...48..524M",
        "author":["Moorman, M."],
        "doctype":"article",
        "identifier":["1974JPal...48..524M"],
        "page":["524"],
        "pub":"Journal of Paleontology",
        "pubdate":"1974-05-00",
        "title":["Microbiota of the late Proterozoic Hector Formation, Southwestern Alberta, Canada"],
        "volume":"48",
        "year":"1974"},
      {
        "bibcode":"1966Natur.211..116M",
        "author":["Melville, R."],
        "doctype":"article",
        "doi":["10.1038/211116a0"],
        "identifier":["1966Natur.211..116M",
          "10.1038/211116a0"],
        "issue":"5045",
        "page":["116"],
        "pub":"Nature",
        "pubdate":"1966-07-00",
        "title":["Continental Drift, Mesozoic Continents and the Migrations of the Angiosperms"],
        "volume":"211",
        "year":"1966"},
      {
        "bibcode":"1995astro.ph..8159B",
        "arxiv_class":["astro-ph",
          "hep-ph"],
        "author":["Brandenberger, Robert H."],
        "doctype":"eprint",
        "identifier":["arXiv:astro-ph/9508159",
          "1995astro.ph..8159B"],
        "page":["astro-ph/9508159"],
        "pub":"arXiv e-prints",
        "pubdate":"1995-09-00",
        "title":["Formation of Structure in the Universe"],
        "year":"1995"},
      {
        "bibcode":"2017NatCo...814879F",
        "author":["Fredin, Ola",
          "Viola, Giulio",
          "Zwingmann, Horst",
          "Sørlie, Ronald",
          "Brönner, Marco",
          "Lie, Jan-Erik",
          "Grandal, Else Margrethe",
          "Müller, Axel",
          "Margreth, Annina",
          "Vogt, Christoph",
          "Knies, Jochen"],
        "doctype":"article",
        "doi":["10.1038/ncomms14879"],
        "identifier":["2017NatCo...814879F",
          "10.1038/ncomms14879"],
        "page":["14879"],
        "pub":"Nature Communications",
        "pubdate":"2017-04-00",
        "title":["The inheritance of a Mesozoic landscape in western Scandinavia"],
        "volume":"8",
        "year":"2017"},
      {
        "bibcode":"1932Natur.129Q..18.",
        "doctype":"article",
        "doi":["10.1038/129018a0"],
        "identifier":["1932Natur.129Q..18.",
          "10.1038/129018a0"],
        "issue":"3244",
        "page":["18"],
        "pub":"Nature",
        "pubdate":"1932-01-00",
        "title":["Electric Equipment of the Dolomites Railway."],
        "volume":"129",
        "year":"1932"},
      {
        "bibcode":"2019arXiv190502552Q",
        "arxiv_class":["q-bio.QM"],
        "author":["Qin, Yang",
          "Freebairn, Louise",
          "Atkinson, Jo-An",
          "Qian, Weicheng",
          "Safarishahrbijari, Anahita",
          "Osgood, Nathaniel D"],
        "doctype":"eprint",
        "identifier":["2019arXiv190502552Q",
          "arXiv:1905.02552"],
        "page":["arXiv:1905.02552"],
        "pub":"arXiv e-prints",
        "pubdate":"2019-05-00",
        "title":["Multi-Scale Simulation Modeling for Prevention and Public Health Management of Diabetes in Pregnancy and Sequelae"],
        "year":"2019"}]
  }}';
    $response = Bibcode_Response_Processing($return, $ch, "No real URL");
    curl_close($ch);
    $this->assertTrue(isset($response->docs));
    $this->assertSame(10, $response->numFound);
  }
}
