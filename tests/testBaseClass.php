<?php
declare(strict_types=1);

require_once __DIR__ . '/../setup.php';

define("BAD_PAGE_API", "User:AManWithNoPlan/sandbox3"); // Remember that debug_print_backtrace(0, 6) can be helpful

abstract class testBaseClass extends PHPUnit\Framework\TestCase {

  private $testing_skip_bibcode= FALSE;
  private $testing_skip_wiki   = FALSE;
  
  function __construct() {
    parent::__construct();

   // Non-trusted builds
    if (!PHP_ADSABSAPIKEY) $this->testing_skip_bibcode = TRUE;
    if (!getenv('PHP_OAUTH_CONSUMER_TOKEN') || !getenv('PHP_OAUTH_CONSUMER_SECRET') ||
        !getenv('PHP_OAUTH_ACCESS_TOKEN')   || !getenv('PHP_OAUTH_ACCESS_SECRET')) {
       $this->testing_skip_wiki = TRUE;
    }

    AdsAbsControl::small_give_up();
    AdsAbsControl::big_give_up();
    Zotero::block_zotero();
    gc_collect_cycles();
  }

  protected function requires_secrets(callable $function) : void {
    if ($this->testing_skip_wiki) {
      echo 'A'; // For API, since W is taken
      ob_flush();
      $this->assertNull(NULL);
    } else {
      $function();
    }
  }

  // Only routines that absolutely need bibcode access since we are limited 
  protected function requires_bibcode(callable $function) : void {
    if ($this->testing_skip_bibcode) {
      echo 'B';
      ob_flush();
      AdsAbsControl::big_back_on();
      AdsAbsControl::big_give_up();
      AdsAbsControl::small_back_on();
      AdsAbsControl::small_give_up();
      $this->assertNull(NULL);
    } else {
      try {
        AdsAbsControl::big_back_on();
        AdsAbsControl::small_back_on();
        $function();
      } finally {
        AdsAbsControl::big_give_up();
        AdsAbsControl::small_give_up();
      }
    }
  }

  // Speeds up non-zotero tests
  protected function requires_zotero(callable $function) : void {
      try {
        Zotero::unblock_zotero();
        $function();
      } finally {
        Zotero::block_zotero();
      }
  } 
  
  protected function make_citation(string $text) : Template {
    Template::$all_templates = array();
    Template::$date_style = DATES_WHATEVER;
    $this->assertSame('{{', mb_substr($text, 0, 2));
    $this->assertSame('}}', mb_substr($text, -2));
    $template = new Template();
    $template->parse_text($text);
    return $template;
  }
  
  protected function prepare_citation(string $text) : Template {
    $template = $this->make_citation($text);
    $template->prepare();
    return $template;
  }
  
  protected function process_citation(string $text) : Template {
    $page = $this->process_page($text);
    $expanded_text = $page->parsed_text();
    $template = new Template();
    $template->parse_text($expanded_text);
    return $template;
  }
    
  protected function process_page(string $text) : TestPage { // Only used if more than just a citation template
    Template::$all_templates = array();
    Template::$date_style = DATES_WHATEVER;
    $page = new TestPage();
    $page->parse_text($text);
    $page->expand_text();
    return $page;
  }

  protected function parameter_parse_text_helper(string $text) : Parameter {
    $parameter = new Parameter();
    $parameter->parse_text($text);
    return $parameter;
  }

  protected function getDateAndYear(Template $input) : ?string {
    // Generates string that makes debugging easy and will throw error
    if (is_null($input->get2('year'))) return $input->get2('date') ; // Might be null too
    if (is_null($input->get2('date'))) return $input->get2('year') ;
    return 'Date is ' . $input->get2('date') . ' and year is ' . $input->get2('year');
  }

  protected function expand_via_zotero(string $text) :  Template {
    $expanded = $this->make_citation($text);
    Zotero::expand_by_zotero($expanded);
    $expanded->tidy();
    return $expanded;
  }
 
  protected function reference_to_template(string $text) : Template {
    $text=trim($text);
    if (preg_match("~^(?:<(?:\s*)ref[^>]*?>)(.*)(?:<\s*?\/\s*?ref(?:\s*)>)$~i", $text, $matches)) {
      $template = new Template();
      $template->parse_text($matches[1]);
      return $template;
    } else {
      trigger_error('Non-reference passsed to reference_to_template: ' . $text);
    }
  }
  
  protected function fill_cache() : void { // complete list of DOIs and HDLs that TRUE/FALSE in test suite as of 18 MAY 2022
    Zotero::create_ch_zotero();
    WikipediaBot::make_ch();
    doi_works('Z123Z');
    doi_works('XXX/978-XXX');
    doi_works('X');
    doi_works('10.Z123Z');
    doi_works('10.XXX/978-XXX');
    doi_works('10.X');
    doi_works('10.8550/arXiv.1234.56789');
    doi_works('10.8483/ijSci.364');
    doi_works('10.7717/peerj.3486#with_lotst_of_junk');
    doi_works('10.7717/peerj.3486');
    doi_works('10.7249/mg1078a.10');
    doi_works('10.7249/mg1078a');
    doi_works('10.7249/j.ctt4cgd90.10');
    doi_works('10.7249/j.ctt4cgd90');
    doi_works('10.7249/j');
    doi_works('10.7249');
    doi_works('10.5555/al.ap.specimen.nsw225972');
    doi_works('10.5555/al.ap.specimen');
    doi_works('10.5555/al.ap');
    doi_works('10.5555/al');
    doi_works('10.5555');
    doi_works('10.5284/1000184XXXXXXXXXX');
    doi_works('10.5284/1000184');
    doi_works('10.5260/chara.18.3.53');
    doi_works('10.5240/7B2F-ED76-31F6-8CFB-4DB9-M');
    doi_works('10.51134/sod.2013.039');
    doi_works('10.4928/amstec.23.1_1');
    doi_works('10.48550/arXiv.1234.56789');
    doi_works('10.4103/0019-5545.105547');
    doi_works('10.3897/zookeys.551.6767');
    doi_works('10.3897/zookeys.450.7452');
    doi_works('10.3897/zookeys.445.7778');
    doi_works('10.3897/zookeys.123.322222X');
    doi_works('10.3897/zookeys.123.322222');
    doi_works('10.3847/2041-8213/aadc10');
    doi_works('10.3421/32412xxxxxxx');
    doi_works('10.3406/befeo.1954.5607');
    doi_works('10.3265/Nefrologia.pre2010.May.10269');
    doi_works('10.3265/Nefrologia.NOTAREALDOI.broken');
    doi_works('10.3233/PRM-140291');
    doi_works('10.3140/RG.2.1.1002.9609');
    doi_works('10.2307/962034');
    doi_works('10.2307/832414');
    doi_works('10.2307/594900');
    doi_works('10.2307/40237667');
    doi_works('10.2307/27695659');
    doi_works('10.2307/1974136');
    doi_works('10.2307');
    doi_works('10.2173/bow.sompig1.01');
    doi_works('10.2015/2137303');
    doi_works('10.2015');
    doi_works('10.18483/ijSci.364');
    doi_works('10.1677/jme.0.0040213');
    doi_works('10.1635/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2');
    doi_works('10.14928/amstec.23.1_1');
    doi_works('10.1429/ppmsj1919.17.0_48');
    doi_works('10.1377/hblog20180605.966625');
    doi_works('10.1377/hblog20180605');
    doi_works('10.1377/forefront.20180605.966625');
    doi_works('10.1377');
    doi_works('10.13140/RG.2.1.1002.9609');
    doi_works('10.1206/0003-0082(2006)3508[1:EEALSF]2.0.CO;2');
    doi_works('10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2');
    doi_works('10.1175/1525-7541(2003)004&lt;1147:TVGPCP&gt;2.0.CO;2');
    doi_works('10.1164/rccm.200405-644ST');
    doi_works('10.1145/358589.358596');
    doi_works('10.11429/ppmsj1919.17.0_48');
    doi_works('10.1136/bmj.327.7429.1459');
    doi_works('10.1136/bmj.2.3798.759-a');
    doi_works('10.1136/bmj.1.5011.182');
    doi_works('10.1134/sod.2013.039');
    doi_works('10.1130/0091-7613(1995)023<0967:FEAPRO>2.3.CO;2');
    doi_works('10.1130/0016-7606(1996)108<0195:GCGBPT>2.3.CO;2');
    doi_works('10.1128/MCB.19.1.612');
    doi_works('10.1128/JCM.40.12.4512-4519.2002');
    doi_works('10.1126/science.267.5194.77');
    doi_works('10.1126/science.10.1126/SCIENCE.291.5501.24');
    doi_works('10.1117/12.135408');
    doi_works('10.1111/pala.12168');
    doi_works('10.1111/j.1749-6632.1979.tb32775.x');
    doi_works('10.1111/j.1550-7408.2002.tb00224.x');
    doi_works('10.1111/j.1525-1497.2004.30090.x');
    doi_works('10.1111/j.1475-4983.2012.01203.x/full');
    doi_works('10.1111/j.1475-4983.2012.01203.x/file');
    doi_works('10.1111/j.1475-4983.2012.01203.x');
    doi_works('10.1111/j.1475-4983.2012.01203');
    doi_works('10.1111/j.1475-4983.2002.32412432423423421314324234233242314234');
    doi_works('10.1111/j.1471-0528.1995.tb09132.xv2');
    doi_works('10.1111/j.1471-0528.1995.tb09132.x</a>');
    doi_works('10.1111/j.1471-0528.1995.tb09132.x#page_scan_tab_contents');
    doi_works('10.1111/j.1471-0528.1995.tb09132.x/abstract');
    doi_works('10.1111/j.1471-0528.1995.tb09132.x.full');
    doi_works('10.1111/j.1471-0528.1995.tb09132.x;jsessionid');
    doi_works('10.1111/j.1471-0528.1995.tb09132.x;');
    doi_works('10.1111/j.1471-0528.1995.tb09132.x');
    doi_works('10.1111/j.1471-0528.1995.tb09132.');
    doi_works('10.1111/(ISSN)1601-183X/issues');
    doi_works('10.1111/(ISSN)1601-183X');
    doi_works('10.1109/PESGM.2015.7285996');
    doi_works('10.1109/ISSCC.2007.373373');
    doi_works('10.1103/PhysRevD.78.081701');
    doi_works('10.1103/PhysRev.57.546');
    doi_works('10.1101/326363');
    doi_works('10.1093/zoolinnean/zly047/5049994');
    doi_works('10.1093/zoolinnean/zly047');
    doi_works('10.1093/ww/9780199540891.001.0001/ww-9780199540884-e-12345');
    doi_works('10.1093/ww/9780199540884.013.U37305');
    doi_works('10.1093/ww/9780199540884.013.U12345');
    doi_works('10.1093/SER/MWP005');
    doi_works('10.1093/ref:odnb/wrong_stuff');
    doi_works('10.1093/ref:odnb/74876');
    doi_works('10.1093/ref:odnb/33369');
    doi_works('10.1093/ref:odnb/29929');
    doi_works('10.1093/ref:odnb/108196');
    doi_works('10.1093/ref:odnb/107316');
    doi_works('10.1093/oxfordhb/9780199552238.013.023');
    doi_works('10.1093/oxfordhb/9780199552238.003.0023');
    doi_works('10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023');
    doi_works('10.1093/oxfordhb/9780199552238.001.0001');
    doi_works('10.1093/oxfordhb/9780198824633.013.1');
    doi_works('10.1093/oso/9780198814122.003.0005');
    doi_works('10.1093/oso/9780190124786.001.0001');
    doi_works('10.1093/oi/authority.xXXXXXXXXXX.pdf');
    doi_works('10.1093/oi/authority.x');
    doi_works('10.1093/oi/authority.9876543210');
    doi_works('10.1093/odnb/9780198614128.013.74876');
    doi_works('10.1093/odnb/9780198614128.013.108196');
    doi_works('10.1093/odnb/9780198614128.013.107316');
    doi_works('10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316');
    doi_works('10.1093/odnb/74876');
    doi_works('10.1093/odnb/108196');
    doi_works('10.1093/odnb/107316');
    doi_works('10.1093/oao/9781884446054.013.8000020158');
    doi_works('10.1093/nar/8.12.2823');
    doi_works('10.1093/med/9780199592548.003.0199');
    doi_works('10.1093/law:epil/9780199231690/law-9780199231690-e1301');
    doi_works('10.1093/law:epil/9780199231690/law-9780199231690-e1206');
    doi_works('10.1093/law:epil/9780199231690');
    doi_works('10.1093/law:epil');
    doi_works('10.1093/gmo/9781561592630.article.O008391');
    doi_works('10.1093/gmo/9781561592630.article.L2232256');
    doi_works('10.1093/gmo/9781561592630.article.J441700');
    doi_works('10.1093/gmo/9781561592630.article.J095300');
    doi_works('10.1093/gmo/9781561592630.article.A2242442');
    doi_works('10.1093/gmo/9781561592630.article.40055');
    doi_works('10.1093/gao/9781884446054.article.T2085714');
    doi_works('10.1093/gao/9781884446054.article.T082129');
    doi_works('10.1093/gao/9781884446054.article.T0082129');
    doi_works('10.1093/benz/9780199773787.article.B00183827');
    doi_works('10.1093/anb/9780198606697.article.1800262');
    doi_works('10.1093/acrefore/9780199389414.013.224');
    doi_works('10.1093/acrefore/9780199381135.013.7023');
    doi_works('10.1093/acrefore/9780199366439.013.2');
    doi_works('10.1093/acrefore/9780199340378.013.568');
    doi_works('10.1093/acrefore/9780199329175.013.17');
    doi_works('10.1093/acrefore/9780190854584.013.45');
    doi_works('10.1093/acrefore/9780190846626.013.39');
    doi_works('10.1093/acrefore/9780190277734.013.191');
    doi_works('10.1093/acrefore/9780190236557.013.384');
    doi_works('10.1093/acrefore/9780190228637.013.181');
    doi_works('10.1093/acrefore/9780190228620.013.699');
    doi_works('10.1093/acrefore/9780190228613.013.1195');
    doi_works('10.1093/acrefore/9780190228613.001.0001/acrefore-9780190228613-e-1195');
    doi_works('10.1093/acrefore/9780190228613.001.0001');
    doi_works('10.1093/acrefore/9780190228613.001');
    doi_works('10.1093/acrefore/9780190228613');
    doi_works('10.1093/acrefore/9780190201098.013.1357');
    doi_works('10.1093/acrefore/9780190201098.001.0001/acrefore-9780190201098-e-1357');
    doi_works('10.1093/acrefore/9780190201098.001.0001');
    doi_works('10.1093/acrefore/9780190201098.001');
    doi_works('10.1093/acrefore/9780190201098');
    doi_works('10.1093/acrefore');
    doi_works('10.1093/acref/9780199545568.001.0001');
    doi_works('10.1093/acref/9780199208951.013.q-author-00005-00000991');
    doi_works('10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022');
    doi_works('10.1093/acref/9780199204632.001.0001');
    doi_works('10.1093/acref/9780195301731.013.41463');
    doi_works('10.1093/1');
    doi_works('10.1093');
    doi_works('10.1088/1742-6596/1087/6/062024');
    doi_works('10.1080/13537113.2015.1032033');
    doi_works('10.1080/1323238x.2006.11910818');
    doi_works('10.1080/09553007714551541');
    doi_works('10.1080/00020186808707298');
    doi_works('10.1073/pnas.91.11.4776');
    doi_works('10.1073/pnas.242490299');
    doi_works('10.1073/pnas.171325998');
    doi_works('10.1063/5.0088162');
    doi_works('10.1063/1.4962420');
    doi_works('10.1063/1.2833100');
    doi_works('10.1063/1.2263373');
    doi_works('10.1063/1.1493184');
    doi_works('10.1046/j.1365-2699.1999.00329.x');
    doi_works('10.1039/A808518H');
    doi_works('10.1038/srep13100');
    doi_works('10.1038/ntheses.01928');
    doi_works('10.1038/ntheses');
    doi_works('10.1038/ncomms14879');
    doi_works('10.1038/nature11111');
    doi_works('10.1038/nature10000');
    doi_works('10.1038/nature09068');
    doi_works('10.1038/nature08244');
    doi_works('10.1038/546031a');
    doi_works('10.1038/211116a0');
    doi_works('10.1038/129018a0');
    doi_works('10.1038');
    doi_works('10.1021/jp101758y');
    doi_works('10.1021/jm00193a001');
    doi_works('10.1021/cen-v076n048.p024;jsessionid=222');
    doi_works('10.1021/cen-v076n048.p024');
    doi_works('10.1021/acs.analchem.8b04567');
    doi_works('10.1017/S0080456800002751');
    doi_works('10.1017/S0024282991000452');
    doi_works('10.1017/s0022381613000030');
    doi_works('10.1017/jpa.2018.43');
    doi_works('10.1016/j.physletb.2010.08.018');
    doi_works('10.1016/j.physletb.2010.03.064');
    doi_works('10.1016/j.laa.2012.05.036');
    doi_works('10.1016/j.ifacol.2017.08.010');
    doi_works('10.1016/j.cpc.2017.09.004');
    doi_works('10.1016/j.chaos.2004.07.021');
    doi_works('10.1016/j.biocontrol.2014.06.004');
    doi_works('10.1016/j.bbagen.2019.129466');
    doi_works('10.1016/b978-0-12-386454-3.00012-9');
    doi_works('10.1016/0301-0104(82)87006-7');
    doi_works('10.1016/0042-6822(66)90260-1');
    doi_works('10.1016/0022-3468(91)90004-d');
    doi_works('10.1007%2Fs001140100225');
    doi_works('10.1007/s12668-011-0022-5');
    doi_works('10.1007/s12052-007-0001-z');
    doi_works('10.1007/s11746-998-0245-y');
    doi_works('10.1007/s10551-007-9500-7');
    doi_works('10.1007/s007100170010');
    doi_works('10.1007/s00339-014-8468-2');
    doi_works('10.1007/s00214-007-0303-9');
    doi_works('10.1007/s001140100225');
    doi_works('10.1007/BF00428580');
    doi_works('10.1007/BF00233701#page-1');
    doi_works('10.1007/BF00233701');
    doi_works('10.1007/978-981-10-3180-9_1');
    doi_works('10.1007/978-3-642-75924-6_15#page-1');
    doi_works('10.1007/978-3-642-75924-6_15');
    doi_works('10.1007/978-3-642-39206-1_42');
    doi_works('10.1007/978-3-540-78646-7_75');
    doi_works('10.1007/978-3-540-74735-2_15');
    doi_works('10.1002/jcc.21074');
    doi_works('10.1002/1097-0185(20000701)259:3<312::AID-AR80>3.0.CO;2-X');
    doi_works('10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U#page_scan_tab_contents=342342');
    doi_works('10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U');
    doi_works('10.1002/(SICI)1097-0134(20000515)39:3<216::AID-PROT40>3.0.CO;2-#');
    doi_works('10.1002/(ISSN)1099-0739/homepage/EditorialBoard');
    doi_works('10.1002/(ISSN)1099-0739/homepage');
    doi_works('10.1002/(ISSN)1099-0739');
    doi_works('10.1000/100');
    doi_works('10.0000/Rubbish_bot_failure_test.x');
    doi_works('10.0000/Rubbish_bot_failure_test.');
    doi_works('10.0000');
    doi_works('# # # CITATION_BOT_PLACEHOLDER_TEMPLATE 3 # # #');
    doi_works('# # # CITATION_BOT_PLACEHOLDER_TEMPLATE 1 # # #');
    doi_works('# # # CITATION_BOT_PLACEHOLDER_TEMPLATE 0 # # #');
    doi_works('# # # CITATION_BOT_PLACEHOLDER_COMMENT 1 # # #');
    doi_works('# # # CITATION_BOT_PLACEHOLDER_COMMENT 0 # # #');
    doi_works('');
    hdl_works('');
    hdl_works('2158/1264947');
    hdl_works('2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116-358');
    hdl_works('2027/mdp.39015064245429?urlappend=%3Bseq=326');
    hdl_works('2027/mdp.39015064245429?urlappend=%253Bseq=326');
    hdl_works('2027/mdp.39015064245429');
    hdl_works('2027/loc.ark:/13960/t6349vh5n?urlappend=%3Bseq=672');
    hdl_works('2027/loc.ark:/13960/t6349vh5n?urlappend=%253Bseq=672');
    hdl_works('2027/loc.ark:/13960/t6349vh5n');
    hdl_works('20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35urlappend');
    hdl_works('20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35');
    hdl_works('20.1000/100?urlappend=%3Bseq=326');
    hdl_works('20.1000/100');
    hdl_works('20.1000/100');
    hdl_works('1807/32368');
    hdl_works('10125/dfsjladsflhdsfaewfsdfjhasjdfhldsaflkdshkafjhsdjkfhdaskljfhdsjklfahsdafjkldashafldsfhjdsa_TEST_DATA_FOR_BOT_TO_FAIL_ON');
    hdl_works('10125/20269');
  }
}
