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



  public function testBibCodeResponceProcess() : void {
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
    $responce = Bibcode_Response_Processing($return, $ch, "No real URL");
    curl_close($ch);
    print_r($responce);
    $this->assertTrue(isset($response->docs));
    $this->assertSame(10, $response->numFound);
  }
}
