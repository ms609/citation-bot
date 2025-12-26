<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class DoiTest extends testBaseClass {
    public function testExpansion_doi_not_from_crossrefRG(): void {
        $text = '{{Cite journal| doi= 10.13140/RG.2.1.1002.9609}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Lesson Study as a form of in-School Professional Development', $expanded->get2('title'));
        $this->assertSame('2015', $expanded->get2('date'));
        $this->assertSame('Aoibhinn Ni Shuilleabhain', $expanded->get2('author1'));
    }

    public function testExpansion_doi_not_from_crossrefJapanJournal(): void {
        $text = '{{cite journal|doi=10.11429/ppmsj1919.17.0_48}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('On the Interaction of Elementary Particles. I', $expanded->get2('title'));
        $this->assertSame('1935', $expanded->get2('date'));
        $this->assertSame('Proceedings of the Physico-Mathematical Society of Japan. 3rd Series', $expanded->get2('journal'));
        $this->assertSame('17', $expanded->get2('volume'));
        $this->assertSame('YUKAWA', $expanded->get2('last1'));
        $this->assertSame('Hideki', $expanded->get2('first1'));
    }

    /** See https://www.doi.org/demos.html  NOT ALL EXPAND AT THIS TIME */
    public function testExpansion_doi_not_from_crossrefBook(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1017/CBO9780511983658');  // This is cross-ref doi, so for DX DOI expansion
        $this->assertSame('{{Cite book| last1=Luo | first1=Zhi-Quan | last2=Pang | first2=Jong-Shi | last3=Ralph | first3=Daniel | title=Mathematical Programs with Equilibrium Constraints | date=1996 | publisher=Cambridge University Press | isbn=978-0-521-57290-3 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefBookChapter(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1002/0470841559.ch1');  // This is cross-ref doi, so for DX DOI expansion
        $this->assertSame('{{Cite book| title=Internetworking LANs and WANs | chapter=Network Concepts | date=2001 | publisher=Wiley | isbn=978-0-471-97514-4 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefDataCiteSubsets(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1594/PANGAEA.726855');
        $this->assertSame('{{Cite journal| last1=Irino | first1=Tomohisa | last2=Tada | first2=Ryuji | title=Chemical and mineral compositions of sediments from ODP Site 127-797 | date=2009 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefDataCiteEarthquake(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1594/GFZ.GEOFON.gfz2009kciu');
        $this->assertSame('{{Cite journal| author1=Geofon Operator | title=GEOFON event gfz2009kciu (NW Balkan Region) | date=2009 | publisher=Deutsches GeoForschungsZentrum GFZ }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefDataCiteMappedVisualization(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1594/PANGAEA.667386');
        $this->assertSame('{{Cite book| last1=Kraus | first1=Stefan | last2=del Valle | first2=Rodolfo | title=Geological map of Potter Peninsula (King George Island, South Shetland Islands, Antarctic Peninsula) | chapter=Impact of climate induced glacier melt on marine coastal systems, Antarctica (IMCOAST/IMCONet) | date=2008 | publisher=Pangaea }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefDataCitevideo(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.3207/2959859860');
        $this->assertSame('{{Cite journal| last1=Kirchhof | first1=Bernd | title=Silicone oil bubbles entrapped in the vitreous base during silicone oil removal | date=2009 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_fISTIC_Journal(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.3866/PKU.WHXB201112303');
        $this->assertSame('{{Cite journal| last3=Ning | first3=MA | last4=Wei-Zhou | first4=WANG | last1=Yu | first1=ZHANG | title=Correlation between Bond-Length Change and Vibrational Frequency Shift in Hydrogen-Bonded Complexes Revisited | journal=Acta Physico-Chimica Sinica | date=2012 | volume=28 | issue=3 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_fISTIC_Data(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.3972/water973.0145.db');
        $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_ISTIC_Thesis(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.7666/d.y351065');
        $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_JaLC_Journal(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.11467/isss2003.7.1_11');
        $this->assertSame('{{Cite journal| last1=竹本 | first1=賢太郎 | last2=川東 | first2=正美 | last3=久保 | first3=信行 | last4=左近 | first4=多喜男 | title=大学におけるWebメールとターミナルサービスの研究 | journal=Society for Standardization Studies | date=2009 | volume=7 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_JaLC_Journal2(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.7875/leading.author.2.e008');
        $this->assertSame('{{Cite journal| last1=川崎 | first1=努. | title=植物における免疫誘導と病原微生物の感染戦略 | journal=領域融合レビュー | date=2013 | volume=2 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_mEDRA_Journal(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1430/8105');
        $this->assertSame("{{Cite journal| author1=Romano Prodi | title=L'Industria dopo l'euro | journal=L'Industria | date=2002 | issue=4 }}", $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_mEDRA_Monograph(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1392/BC1.0');
        $this->assertSame('{{Cite journal| last1=Attanasio | first1=Piero | title=The use of Doi in eContent value chain | date=2004 | publisher=mEDRA }}', $expanded->parsed_text());
    }

    /** http://doi.airiti.com/. They allow you to easily find the RA, but they seem to no longer do meta-data http://www.airitischolar.com/doi/WhichRA/index.jsp */
    public function testExpansion_doi_not_from_crossref_airiti_journal(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.6620/ZS.2018.57-30');
        if ($expanded->parsed_text() === '{{Cite journal}}') {
            $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
        } else {
            $this->assertSame('{{Cite journal| author1=Jun Aoyama | author2=Sam Wouthuyzen | author3=Michael J. Miller | author4=Hagi Y. Sugeha | author5=Mari Kuroki | author6=Shun Watanabe | author7=Augy Syahailatua | author8=Fadly Y. Tantu | author9=Seishi Hagihara | author10=Triyanto | author11=Tsuguo Otake | author12=Katsumi Tsukamoto | title=Reproductive Ecology and Biodiversity of Freshwater Eels around Sulawesi Island Indonesia | journal=Zoological Studies | date=2018 | volume=無 | issue=57 }}', $expanded->parsed_text());
        }
    }

    /** http://www.eidr.org/ */
    public function testExpansion_doi_not_from_crossref_eidr_Black_Panther_Movie(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.5240/7B2F-ED76-31F6-8CFB-4DB9-M');
        $this->assertSame('{{Cite journal| last1=Coogler | first1=Ryan | title=Black Panther | date=2018 }}', $expanded->parsed_text());
    }

    /** http://www.kisti.re.kr/eng/ */
    public function testExpansion_doi_not_from_crossref_kisti_journal(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.3743/KOSIM.2011.28.2.117');
        $this->assertSame('{{Cite journal| last1=Kim | first1=Byung-Kyu | last2=Kang | first2=Mu-Yeong | last3=Choi | first3=Seon-Heui | last4=Kim | first4=Soon-Young | last5=You | first5=Beom-Jong | last6=Shin | first6=Jae-Do | title=Citing Behavior of Korean Scientists on Foreign Journals in KSCD | journal=Journal of the Korean Society for Information Management | date=2011 | volume=28 | issue=2 }}', $expanded->parsed_text());
    }

    /** https://publications.europa.eu/en/ */
    public function testExpansion_doi_not_from_crossref_europa_monograph(): void {
         $expanded = $this->make_citation('{{Cite journal}}');
         expand_doi_with_dx($expanded, '10.2788/14231');
        if ($expanded->has('author1')) {
            $this->assertSame('{{Cite journal| author1=European Commission. Joint Research Centre. Institute for Environment and Sustainability | last2=Vogt | first2=Jürgen | last3=Foisneau | first3=Stéphanie | title=European river and catchment database, version 2.0 (CCM2) : Analysis tools | date=2007 | publisher=Publications Office }}', $expanded->parsed_text());
        } else {
            $this->assertSame('FIX ME', $expanded->parsed_text());
        }
    }

    public function testComplexCrossRef(): void {
        $text = '{{citation | title = Deciding the Winner of an Arbitrary Finite Poset Game is PSPACE-Complete| arxiv = 1209.1750| bibcode = 2012arXiv1209.1750G}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Deciding the Winner of an Arbitrary Finite Poset Game is PSPACE-Complete', $expanded->get2('chapter'));
        $this->assertSame('Lecture Notes in Computer Science', $expanded->get2('series'));
        $this->assertSame('Automata, Languages, and Programming', $expanded->get2('title'));
    }

    public function testThesisDOI(): void {
        $doi = '10.17077/etd.g638o927';
        $text = "{{cite journal|doi=$doi}}";
        $template = $this->make_citation($text);
        expand_doi_with_dx($template, $doi);
        $this->assertSame($doi, $template->get2('doi'));
        $this->assertSame("The caregiver's journey", $template->get2('title'));
        $this->assertSame('The University of Iowa', $template->get2('publisher'));
        $this->assertSame('2018', $template->get2('date'));
        $this->assertSame('Schumacher', $template->get2('last1'));
        $this->assertSame('Lisa Anne', $template->get2('first1'));
    }

    public function testCrossRefAddSeries1(): void {
        $text = "{{Cite book | doi = 10.1063/1.2833100| title = A Transient Semi-Metallic Layer in Detonating Nitromethane}}";
        $template = $this->process_citation($text);
        $this->assertSame("AIP Conference Proceedings", $template->get2('series'));
    }

    public function testCrossRefAddSeries2(): void {
        // Kind of messed up, but "matches" enough to expand
        $text = "{{Cite book | doi = 10.1063/1.2833100| title = AIP Conference Proceedings}}";
        $template = $this->process_citation($text);
        $this->assertSame("2008", $template->get2('date'));
    }

    public function testCrossRefAddEditors(): void {
        $text = "{{Cite book | doi = 10.1117/12.135408}}";
        $template = $this->process_citation($text);
        $this->assertSame("Kopera", $template->get2('editor-last1'));
    }

    public function testBlankTypeFromDX1(): void {
        $text = "{{cite book| doi=10.14989/doctor.k19250 }}";
        $prepared = $this->process_citation($text);
        $this->assertSame('2015', $prepared->get2('date'));
    }

    public function testBlankTypeFromDX2(): void {
        $text = "{{Cite journal|doi=10.26099/aacp-5268}}";
        $prepared = $this->process_citation($text);
        $this->assertSame('Collins', $prepared->get2('last1'));
    }

    public function testCrossRefAlternativeAPI(): void {
        $text = "{{cite journal| doi=10.1080/00222938700771131 |s2cid=<!-- --> |pmid=<!-- --> |pmc=<!-- --> |arxiv=<!-- --> |jstor=<!-- --> |bibcode=<!-- --> }}";
        $prepared = $this->process_citation($text);
        $this->assertSame("Life cycles of ''Phialella zappai'' n. Sp., ''Phialella fragilis'' and ''Phialella'' sp. (Cnidaria, Leptomedusae, Phialellidae) from central California", $prepared->get2('title'));
    }

    public function testCrossRefAlternativeAPI2(): void {
        $text = "{{Cite book |date=2012-11-12 |title=The Analects of Confucius |url=http://dx.doi.org/10.4324/9780203715246 |doi=10.4324/9780203715246|isbn=9780203715246 |last1=Estate |first1=The Arthur Waley }}";
        $prepared = $this->process_citation($text);
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testCrossRefAlternativeAPI3(): void {
        $text = "{{cite book |last=Galbács |first=Peter |title=The Theory of New Classical Macroeconomics. A Positive Critique |location=Heidelberg/New York/Dordrecht/London |publisher=Springer |year=2015 |isbn= 978-3-319-17578-2|doi=10.1007/978-3-319-17578-2 |series=Contributions to Economics }}";
        $prepared = $this->process_citation($text);
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testCrossRefAlternativeAPI4(): void {
        $text = "{{Cite book |url=https://www.taylorfrancis.com/books/edit/10.4324/9781351295246/media-suicide-thomas-niederkrotenthaler-steven-stack |title=Media and Suicide: International Perspectives on Research, Theory, and Policy |date=2017-10-31 |publisher=Routledge |isbn=978-1-351-29524-6 |editor-last=Niederkrotenthaler |editor-first=Thomas |location=New York |doi=10.4324/9781351295246 |editor-last2=Stack |editor-first2=Steven}}";
        $prepared = $this->process_citation($text);
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testPoundDOI(): void {
        $text = "{{cite book |url=https://link.springer.com/chapter/10.1007%2F978-3-642-75924-6_15#page-1}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1007/978-3-642-75924-6_15', $expanded->get2('doi'));
    }

    public function testPlusDOI(): void {
        $doi = "10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U#page_scan_tab_contents=342342"; // Also check #page_scan_tab_contents stuff too
        $text = "{{cite journal|doi = $doi }}";
        $expanded = $this->process_citation($text);
        $this->assertSame("10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U", $expanded->get2('doi'));
    }

    public function testNewsdDOI(): void {
        $text = "{{cite news|url=http://doi.org/10.1021/cen-v076n048.p024;jsessionid=222}}"; // Also check jsesssion removal
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1021/cen-v076n048.p024', $expanded->get2('doi'));
    }

    public function testDoiExpansion1(): void {
        $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('cite journal', $prepared->wikiname());
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $prepared->get2('doi'));
    }

    public function testDoiExpansion2(): void {
        $text = "{{Cite web | url = http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite web', $expanded->wikiname());
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testDoiExpansion3(): void {
        // Recognize official DOI targets in URL with extra fragments - fall back to S2
        $text = '{{cite journal | url = https://link.springer.com/article/10.1007/BF00233701#page-1 | doi = 10.1007/BF00233701}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testDoiExpansion4(): void {
        // Replace this test with a real URL (if one exists)
        $text = "{{Cite web | url = http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf}}"; // Fake URL, real DOI
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite web', $expanded->wikiname());
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
        // Do not drop PDF files, in case they are open access and the DOI points to a paywall
        $this->assertSame('http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf', $expanded->get2('url'));
    }

    public function testDOI1093(): void {
        $text = '{{cite web |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002}}';
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('{{cite document |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002}}', $template->parsed_text());
    }

    public function testDOI1093_part2(): void {
        $text = '{{Cite web |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002}}';
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('{{Cite document |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002}}', $template->parsed_text());
    }

    public function testDOI1093WW(): void {
        $text = '{{cite web |doi=10.1093/ww/9780199540891.001.0001/ww-9780199540884-e-221850}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/ww/9780199540884.013.U221850', $template->get2('doi'));
    }
}
