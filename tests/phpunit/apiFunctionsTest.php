<?php
declare(strict_types=1);

require_once(__DIR__ . '/../testBaseClass.php');

final class apiFunctionsTest extends testBaseClass {
  
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
  
  public function testArxivDateUpgradeSeesDate() : void {
   $this->requires_arxiv(function() : void {
      $text = '{{Cite journal|date=September 2010|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
      $expanded = $this->process_citation($text);
      $this->assertSame('September 2010', $expanded->get2('date'));
      $this->assertNull($expanded->get2('year'));
      
      $text = '{{Cite journal|date=September 2009|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get2('date'));
      $this->assertSame('2010', $expanded->get2('year'));
    });
  }
  
  public function testExpansion_doi_not_from_crossrefRG() : void {
    $this->requires_dx(function() : void {
     $text = '{{Cite journal| doi= 10.13140/RG.2.1.1002.9609}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('Lesson Study as a form of in-School Professional Development', $expanded->get2('title'));
     $this->assertSame('2015', $expanded->get2('year'));
     $this->assertSame('Aoibhinn Ni Shuilleabhain', $expanded->get2('author1'));
    });
  }
  
   public function testExpansion_doi_not_from_crossrefJapanJournal() : void {
    $this->requires_dx(function() : void {
     $text = '{{cite journal|doi=10.11429/ppmsj1919.17.0_48}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('On the Interaction of Elementary Particles. I', $expanded->get2('title'));
     $this->assertSame('1935', $expanded->get2('year'));
     $this->assertSame('Proceedings of the Physico-Mathematical Society of Japan. 3Rd Series', $expanded->get2('journal'));
     $this->assertSame('17', $expanded->get2('volume'));
     $this->assertSame('YUKAWA', $expanded->get2('last1'));
     $this->assertSame('Hideki', $expanded->get2('first1'));
    });
  }
  // See https://www.doi.org/demos.html  NOT ALL EXPAND AT THIS TIME
  public function testExpansion_doi_not_from_crossrefBook() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1017/CBO9780511983658');  // This is cross-ref doi, so for DX DOI expansion
     $this->assertSame('{{Cite book|year = 1996|isbn = 9780521572903|last1 = Luo|first1 = Zhi-Quan|last2 = Pang|first2 = Jong-Shi|last3 = Ralph|first3 = Daniel|title = Mathematical Programs with Equilibrium Constraints|publisher = Cambridge University Press}}', $expanded->parsed_text());
    });
  }
  
  public function testExpansion_doi_not_from_crossrefBookChapter() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1002/0470841559.ch1');  // This is cross-ref doi, so for DX DOI expansion
     $this->assertSame('{{Cite book|year = 2003|isbn = 0471975141|title = Internetworking LANs and WANs|chapter = Network Concepts|publisher = John Wiley & Sons|location = Chichester, UK}}', $expanded->parsed_text());
    });
  }
  
  public function testExpansion_doi_not_from_crossrefDataCiteSubsets() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/PANGAEA.726855');
     $this->assertSame('{{Cite journal|year = 2009|last1 = Irino|first1 = Tomohisa|last2 = Tada|first2 = Ryuji|title = Chemical and mineral compositions of sediments from ODP Site 127-797, supplement to: Irino, Tomohisa; Tada, Ryuji (2000): Quantification of aeolian dust (Kosa) contribution to the Japan Sea sediments and its variation during the last 200 ky. Geochemical Journal, 34(1), 59-93}}', $expanded->parsed_text());
    });
  }

  public function testExpansion_doi_not_from_crossrefDataCiteEarthquake() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/GFZ.GEOFON.gfz2009kciu');
     $this->assertSame('{{Cite journal|year = 2009|author1 = Geofon Operator|title = GEOFON event gfz2009kciu (NW Balkan Region)|publisher = Deutsches GeoForschungsZentrum GFZ}}', $expanded->parsed_text());
    });
  }
  
  public function testExpansion_doi_not_from_crossrefDataCiteMappedVisualization() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/PANGAEA.667386');
     $this->assertSame('{{Cite book|year = 2008|last1 = Kraus|first1 = Stefan|last2 = del Valle|first2 = Rodolfo|title = Geological map of Potter Peninsula (King George Island, South Shetland Islands, Antarctic Peninsula)|chapter = Impact of climate induced glacier melt on marine coastal systems, Antarctica (IMCOAST/IMCONet)|publisher = PANGAEA - Data Publisher for Earth & Environmental Science}}', $expanded->parsed_text());
    });
  }

  public function testExpansion_doi_not_from_crossrefDataCitevideo() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.3207/2959859860');
     $this->assertSame('{{Cite journal|year = 2009|last1 = Kirchhof|first1 = Bernd|title = Silicone oil bubbles entrapped in the vitreous base during silicone oil removal}}', $expanded->parsed_text());
    });
  }

  public function testExpansion_doi_not_from_crossref_fISTIC_Journal() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.3866/PKU.WHXB201112303');
     $this->assertSame('{{Cite journal|year = 2012|volume = 28|issue = 3|last1 = Yu|first1 = ZHANG|last3 = Ning|first3 = MA|last4 = Wei-Zhou|first4 = WANG|title = Correlation between Bond-Length Change and Vibrational Frequency Shift in Hydrogen-Bonded Complexes Revisited|journal = Acta Physico-Chimica Sinica}}', $expanded->parsed_text());
    });
  }
  
  public function testExpansion_doi_not_from_crossref_fISTIC_Data() : void {
    $this->requires_dx(function() : void {
      $expanded = $this->make_citation('{{Cite journal}}');
      expand_doi_with_dx($expanded, '10.3972/water973.0145.db');
      $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
    });
  }
 
  public function testExpansion_doi_not_from_crossref_ISTIC_Thesis() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.7666/d.y351065');
     $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
    });
  }

  public function testExpansion_doi_not_from_crossref_JaLC_Journal() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.11467/isss2003.7.1_11');
     $this->assertSame('{{Cite journal|year = 2009|volume = 7|last1 = 竹本|first1 = 賢太郎|last2 = 川東|first2 = 正美|last3 = 久保|first3 = 信行|last4 = 左近|first4 = 多喜男|title = 大学におけるWebメールとターミナルサービスの研究|publisher = 標準化研究学会}}',$expanded->parsed_text());
    });
  }

  public function testExpansion_doi_not_from_crossref_JaLC_Journal2() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.7875/leading.author.2.e008');
     $this->assertSame('{{Cite journal|year = 2013|volume = 2|last1 = 川崎|first1 = 努.|title = 植物における免疫誘導と病原微生物の感染戦略|journal = 領域融合レビュー}}', $expanded->parsed_text());
    });
  }

  public function testExpansion_doi_not_from_crossref_mEDRA_Journal() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1430/8105');
     $this->assertSame("{{Cite journal|year = 2002|issue = 4|author1 = Romano Prodi|title = L'Industria dopo l'euro|journal = L'Industria}}", $expanded->parsed_text());
    });
  }
  
  public function testExpansion_doi_not_from_crossref_mEDRA_Monograph() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1392/BC1.0');
     $this->assertSame('{{Cite journal|year = 2004|last1 = Attanasio|first1 = Piero|title = The use of Doi in eContent value chain|publisher = mEDRA}}', $expanded->parsed_text());
    });
  }    

  // http://doi.airiti.com/
  public function testExpansion_doi_not_from_crossref_airiti_journal() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.6620/ZS.2018.57-30');
     $this->assertSame('{{Cite journal|year = 2018|volume = 無|issue = 57|author1 = Jun Aoyama|author2 = Sam Wouthuyzen|author3 = Michael J. Miller|author4 = Hagi Y. Sugeha|author5 = Mari Kuroki|author6 = Shun Watanabe|author7 = Augy Syahailatua|author8 = Fadly Y. Tantu|author9 = Seishi Hagihara|author10 = Triyanto|author11 = Tsuguo Otake|author12 = Katsumi Tsukamoto|title = Reproductive Ecology and Biodiversity of Freshwater Eels around Sulawesi Island Indonesia|journal = Zoological Studies}}', $expanded->parsed_text());
    });
  }

  // http://www.eidr.org/
  public function testExpansion_doi_not_from_crossref_eidr_Black_Panther_Movie() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.5240/7B2F-ED76-31F6-8CFB-4DB9-M');
     $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
    });
  }
 
  // http://www.kisti.re.kr/eng/
  public function testExpansion_doi_not_from_crossref_kisti_journal() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.3743/KOSIM.2011.28.2.117');
     $this->assertSame('{{Cite journal|year = 2011|volume = 28|issue = 2|journal = 정보관리학회지|title = Kscd를 활용한 국내 과학기술자의 해외 학술지 인용행태 연구}}', $expanded->parsed_text());
    });
  }
  
  // https://publications.europa.eu/en/
  public function testExpansion_doi_not_from_crossref_europa_monograph() : void {
    $this->requires_dx(function() : void {
     $expanded = $this->make_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.2788/14231');
     if ($expanded->has('author1')) {
       $this->assertSame('{{Cite journal|year = 2007|author1 = European Commission. Joint Research Centre. Institute for Environment Sustainability|last2 = Vogt|first2 = Jürgen|last3 = Foisneau|first3 = Stéphanie|title = European river and catchment database, version 2.0 (CCM2) : Analysis tools|publisher = Publications Office}}', $expanded->parsed_text());
     } else {
       $this->assertSame('{{Cite journal|year = 2007|last1 = Vogt|first1 = Jürgen|last2 = Foisneau|first2 = Stéphanie|title = European river and catchment database, version 2.0 (CCM2) : Analysis tools|publisher = Publications Office}}', $expanded->parsed_text());
     }
     });
  }
  
  public function testComplexCrossRef() : void {
    $this->requires_arxiv(function() : void {
     $text = '{{citation | title = Deciding the Winner of an Arbitrary Finite Poset Game is PSPACE-Complete| arxiv = 1209.1750| bibcode = 2012arXiv1209.1750G}}';
     $expanded = $this->process_citation($text);
     $this->assertSame('Deciding the Winner of an Arbitrary Finite Poset Game is PSPACE-Complete', $expanded->get2('chapter'));
     $this->assertSame('Lecture Notes in Computer Science', $expanded->get2('series'));
     $this->assertSame('Automata, Languages, and Programming', $expanded->get2('title'));
    });
  }
  
   public function testThesisDOI() : void {
    $this->requires_dx(function() : void {
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
    });
  }
  
   public function testJstor1() : void {
     $text = "{{cite journal|url=https://jstor.org/stable/832414?seq=1234}}";
     $template = $this->make_citation($text);
     $this->assertTrue(expand_by_jstor($template));
     $this->assertNull($template->get2('jstor')); // We don't do that here
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
  
  public function testCrossRefAddSeries() : void {
     $text = "{{Cite book | doi = 10.1063/1.2833100| title = A Transient Semi-Metallic Layer in Detonating Nitromethane}}";
     $template = $this->process_citation($text);
     $this->assertSame("AIP Conference Proceedings", $template->get2('series'));
    
    // Next is kind of messed up, but "matches" enough to expand
     $text = "{{Cite book | doi = 10.1063/1.2833100| title = AIP Conference Proceedings}}";
     $template = $this->process_citation($text);
     $this->assertSame("2008", $template->get2('year'));
  }
  
  public function testCrossRefAddEditors() : void {
     $text = "{{Cite book | doi = 10.1117/12.135408}}";
     $template = $this->process_citation($text);
     $this->assertSame("Kopera", $template->get2('editor1-last'));
  }

}
