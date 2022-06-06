<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class CrashTest extends testBaseClass {

  public function testBadPage2() : void {  // Use this when debugging pages that crash the bot
      $text = '{{cite journal|arxiv=2105.09963|doi= |last1=Gagliano |first1=Alexander |last2=Izzo |first2=Luca |last3=Kilpatrick |first3=Charles D. |last4=Mockler |first4=Brenna |author5=Wynn Vincente Jacobson-Galán |last6=Terreran |first6=Giacomo |last7=Dimitriadis |first7=Georgios |last8=Zenati |first8=Yossef |last9=Auchettl |first9=Katie |last10=Drout |first10=Maria R. |last11=Narayan |first11=Gautham |last12=Foley |first12=Ryan J. |last13=Margutti |first13=R. |last14=Rest |first14=Armin |last15=Jones |first15=D. O. |last16=Aganze |first16=Christian |last17=Aleo |first17=Patrick D. |last18=Burgasser |first18=Adam J. |last19=Coulter |first19=D. A. |last20=Gerasimov |first20=Roman |last21=Gall |first21=Christa |last22=Hjorth |first22=Jens |last23=Hsu |first23=Chih-Chun |last24=Magnier |first24=Eugene A. |last25=Mandel |first25=Kaisey S. |last26=Piro |first26=Anthony L. |last27=Rojas-Bravo |first27=César |last28=Siebert |first28=Matthew R. |last29=Stacey |first29=Holland |author30=Michael Cullen Stroh |title=An Early-Time Optical and Ultraviolet Excess in the type-Ic SN 2020oi |year=2021 |display-authors=1 }}';
      $expanded = $this->process_citation($text);
  }

}
