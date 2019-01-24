<?php

require_once __DIR__ . '/../testBaseClass.php';

final class apiFunctionsTest extends testBaseClass {
  
  public function testAdsabsApi() {
    $this->requires_secrets(function() {
      $bibcodes = [
       '2017NatCo...814879F', // 0
       '1974JPal...48..524M', // 1
       '1996GSAB..108..195R', // 2
       '1966Natur.211..116M', // 3
       '1995Sci...267...77R', // 4
       '1995Geo....23..967E', // 5
       '2003hoe..book.....K', // 6
       '2000A&A...361..952H', // 7
       ];
      $text = '{{Cite journal | bibcode = ' . implode('}}{{Cite journal | bibcode = ', $bibcodes) . '}}';
      $page = new TestPage();
      $page->parse_text($text);
      $templates = $page->extract_object('Template');
      $page->expand_templates_from_identifier('bibcode', $templates);
      $this->assertEquals('Nature', $templates[3]->get('journal'));
      $this->assertEquals('Geology', $templates[5]->get('journal'));
      $this->assertEquals('14879', $templates[0]->get('pages'));
      $this->assertNull($templates[6]->get('journal'));
      $this->assertEquals('Astronomy and Astrophysics', $templates[7]->get('journal'));
    });
  }
  
  public function testArxivDateUpgradeSeesDate() {
      $text = '{{Cite journal|date=September 2010|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
      $expanded = $this->process_citation($text);
      $this->assertEquals('September 2010', $expanded->get('date'));
      $this->assertNull($expanded->get('year'));
      
      $text = '{{Cite journal|date=September 2009|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
      $expanded = $this->process_citation($text);
      $this->assertNull($expanded->get('date'));
      $this->assertEquals('2010', $expanded->get('year'));
  }
  
  public function testExpansion_doi_not_from_crossrefRG() {
     $text = '{{Cite journal| doi= 10.13140/RG.2.1.1002.9609}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('Lesson Study as a form of in-School Professional Development', $expanded->get('title'));
     $this->assertEquals('2015', $expanded->get('year'));
     $this->assertEquals('Aoibhinn Ni Shuilleabhain', $expanded->get('author1'));
  }
  
    public function testExpansion_doi_not_from_crossrefJapanJournal() {
     $text = '{{cite journal|doi=10.11429/ppmsj1919.17.0_48}}';
     $expanded = $this->process_citation($text);
     $this->assertEquals('On the Interaction of Elementary Particles. I', $expanded->get('title'));
     $this->assertEquals('1935', $expanded->get('year'));
     $this->assertEquals('Proceedings of the Physico-Mathematical Society of Japan. 3Rd Series', $expanded->get('journal'));
     $this->assertEquals('17', $expanded->get('volume'));
     $this->assertEquals('YUKAWA', $expanded->get('last1'));
     $this->assertEquals('Hideki', $expanded->get('first1'));
    }
  // See https://www.doi.org/demos.html
  public function testExpansion_doi_not_from_crossrefBook() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1017/CBO9780511983658');  // This is cross-ref doi, so for DX DOI expansion
     $this->assertNull($expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefBookChapter() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1002/0470841559.ch1');  // This is cross-ref doi, so for DX DOI expansion
     $this->assertNull($expanded->parsed_text());
  }
  
  public function testExpansion_doi_not_from_crossrefDataCiteSubsets() {
     $expanded = $this->process_citation('{{Cite journal}}');
     expand_doi_with_dx($expanded, '10.1594/PANGAEA.726855');
     $this->assertNull($expanded->parsed_text());
  }
  DataCite:
  Earthquake Event, Authored by Automated System:

Geofon operator (2009): GEOFON event gfz2009kciu (NW Balkan Region) GeoForschungsZentrum Potsdam(GFZ). [ doi:10.1594/GFZ.GEOFON.gfz2009kciu ]
Mapped Visualisation of a Dataset:

Kraus, Stefan; del Valle, Rodolfo (2008): Geological map of Potter Peninsula (King George Island, South Shetland Islands, Antarctic Peninsula). Instituto Antártico Chileno, Punta Arenas, Chile & Instituto Antártico Argentino, Buenos Aires, Argentina. [ doi:10.1594/PANGAEA.667386 ]
Video of eye operation that supplements a medical journal:

B. Kirchhof (2009) Silicone oil bubbles entrapped in the vitreous base during silicone oil removal, Video Journal of Vitreoretinal Surgery. [ doi: 10.3207/2959859860 ]
 
  
  ISTIC:
  Journal article: 张愚.氢键复合物中键长变化与振动频率移动相关性重访[J].物理化学学报, 2012, 28(03):499-503. [ doi:10.3866/PKU.WHXB201112303 ]

    Science data: 阎广建, 康国婷, 任华忠等. 黑河综合遥感联合试验：盈科绿洲、花寨子荒漠和临泽草地加密观测区地基热像仪地表辐射温度观测数据集. 北京师范大学; 中国科学院遥感应用研究所; 中国科学院地理科学与资源研究所; 兰州交通大学. 2008. [ doi:10.3972/water973.0145.db ] 

      Science data: Yan Guangjian, Kang Guoting, Ren Huazhong, Chen Ling, He Tao, Wang Haoxing, Wang Tianxing, Liu Qiang, Li Hua, Xia Chuanfu, Zhou Chunyan, Chen Shaohui, Yang Tianfu. WATER: Dataset of LST (land surface temperature) observed by the thermal camera in the Yingke oasis, Huazhaizi desert steppe and Linze grassland foci experimental areas. Beijing Normal University; Institute of Remote Sensing Applications, Chinese Academy of Sciences; Institute of Geographic Sciences and Natural Resources Research, Chinese Academy of Sciences; Lanzhou Jiaotong University. 2008. [ doi:10.3972/water973.0145.db ]
  
  Dissertation: 刘乃安. 生物质材料热解失重动力学及其分析方法研究［D］.安徽:中国科学技术大学, 2000. [ doi:10.7666/d.y351065 ]
  
  JaLC:
  
  Journal Article: 竹本 賢太郎, 川東 正美, 久保 信行, 左近 多喜男, 大学におけるWebメールとターミナルサービスの研究, 標準化研究 Vol.7(2009), No.1 p.11-20 [ doi:10.11467/isss2003.7.1_11 ]

    Journal Article: 川崎 努, 植物における免疫誘導と病原微生物の感染戦略, ライフサイエンス 領域融合レビュー, 2, e008 (2013), [ doi:10.7875/leading.author.2.e008 ]

      mEDRA:
  
  Journal Article: Prodi, Romano. "L'industria dopo l'euro", L'industria-Rivista di economia e politica industriale 4, 559-566 (2002); [ doi:10.1430/8105 ]

Monograph: Attanasio, Piero. "The use of DOI system in eContent value chain: The case of Casalini Digital Division and mEDRA", White Paper (PDF). [ doi:10.1392/BC1.0 ]
      

}
