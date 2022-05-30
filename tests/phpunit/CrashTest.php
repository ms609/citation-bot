<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

 public function testFixLotsOfDOIs() : void {
  $text = '{{cite journal| doi= 10.1093/acref/9780195301731.001.0001/acref-9780195301731-e-41463}}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acref/9780195301731.013.41463', $template->get2('doi'));
  
  $text = '{{cite journal| doi= 10.1093/acrefore/9780190201098.001.0001/acrefore-9780190201098-e-1357}}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190201098.013.1357', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780190228613.001.0001/acrefore-9780190228613-e-1195 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190228613.013.1195', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780190228620.001.0001/acrefore-9780190228620-e-699 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190228620.013.699', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780190228637.001.0001/acrefore-9780190228637-e-181 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190228637.013.181', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780190236557.001.0001/acrefore-9780190236557-e-384 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190236557.013.384', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780190277734.001.0001/acrefore-9780190277734-e-191 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190277734.013.191', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780190846626.001.0001/acrefore-9780190846626-e-39 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190846626.013.39', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780190854584.001.0001/acrefore-9780190854584-e-45 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190854584.013.45', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780199329175.001.0001/acrefore-9780199329175-e-17 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199329175.013.17', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780199340378.001.0001/acrefore-9780199340378-e-568 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199340378.013.568', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780199366439.001.0001/acrefore-9780199366439-e-2 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199366439.013.2', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780199381135.001.0001/acrefore-9780199381135-e-7023 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199381135.013.7023', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/acrefore/9780199389414.001.0001/acrefore-9780199389414-e-224 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199389414.013.224', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/anb/9780198606697.001.0001/anb-9780198606697-e-1800262 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/anb/9780198606697.article.1800262', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/benz/9780199773787.001.0001/acref-9780199773787-e-00183827 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/benz/9780199773787.article.B00183827', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-7000082129 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gao/9781884446054.article.T082129', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/med/9780199592548.001.0001/med-9780199592548-chapter-199 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/med/9780199592548.003.0199', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-29929 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-29929 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/odnb/29929 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/ww/9780199540884.001.0001/ww-9780199540884-e-12345 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/ww/9780199540884.013.U12345', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-0000040055 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.40055', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-1002242442 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.A2242442', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-2000095300 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.J095300', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-4002232256}}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.L2232256', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-5000008391 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.O008391', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/ref:odnb/108196 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/9780198614128.013.108196 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/oxfordhb/9780199552238.003.0023', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/oso/9780198814122.001.0001/oso-9780198814122-chapter-5 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/oso/9780198814122.003.0005', $template->get2('doi'));
  
  $text = '{{cite journal| doi=10.1093/oso/9780190124786.001.0001/oso-9780190124786 }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/oso/9780190124786.001.0001', $template->get2('doi'));
 }
}
