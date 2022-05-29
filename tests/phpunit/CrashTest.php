<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

 public function testFixLotsOfDOIs() : void {
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acref/9780195301731.013.41463', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190201098.013.1357', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190228613.013.1195', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190228620.013.699', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190228637.013.181', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190236557.013.384', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190277734.013.191', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190846626.013.39', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780190854584.013.45', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199329175.013.17', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199340378.013.568', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199366439.013.2', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199381135.013.7023', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/acrefore/9780199389414.013.224', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/anb/9780198606697.article.1800262', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/benz/9780199773787.article.B00183827', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gao/9781884446054.article.T082129', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gao/9781884446054.article.T2085714', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.40055', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.A2242442', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.J095300', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.L2232256', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/gmo/9781561592630.article.O008391', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/med/9780199592548.003.0199', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/oso/9780190124786.001.0001', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/oso/9780198814122.003.0005', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/oxfordhb/9780198824633.013.1', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/ref:odnb/33369', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/ref:odnb/74876', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/ww/9780199540884.013.U12345', $template->get2('doi'));
  
  $text = '{{cite journal| doi= }}';
  $template = $this->make_citation($text);
  $template->tidy_paramter('doi');
  $this->assertSame('10.1093/ww/9780199540884.013.U37305', $template->get2('doi'));
 }


}
