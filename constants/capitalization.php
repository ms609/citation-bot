<?php
const LC_SMALL_WORDS = array(/* The following will be automatically updated to alphabetical order */
          " AAP ", " AAUP ", " ABC ", " AC ", " ACM ", " AGU ", " AI ", " AIAA ", " AJHG ", 
          " al ", " an ", " and ", " and then ", " as ", " at ", " at ", " aus ", " av ", 
          " BBA ", " BBC ", " be ", " bei ", " BJPsych ", " BMC ", " BMJ ", " but ", " by ", 
          " CBC ", " CNS ", " d'un ", " d'une ", " D.C. ", " da ", " dans ", " das ", " DC ", 
          " de ", " dei ", " del ", " della ", " delle ", " dem ", " den ", " der ", " des ", 
          " di ", " die ", " DNA ", " do ", " du ", " e ", " ed ", " ee ", " ein ", " eine ", " einen ", 
          " el ", " else ", " EMBO ", " en ", " et ", " FASEB ", " FDA ", " FEBS ", " FEMS ", 
          " for ", " from ", " för ", " für ", " IEEE ", " if ", " ILR ", " in ", " into ", 
          " is ", " its ", " JAMA ", " JAMA: ", " la ", " las ", " le ", " les ", " los ", 
          " mit ", " MNRAS ", " mot ", " N.Y. ", " N.Y.) ", " NASA ", " NEJM ", " non ", 
          " nor ", " NRC ", " NY ", " NYC ", " NYT ", " NZ ", " och ", " OECD ", " of ", 
          " off ", " og ", " on ", " or ", " over ", " PCR ", " per ", " PNAS ", " PS: ", 
          " R&D ", " RNA ", " RTÉ ", " S&P ", " SAE ", " SSRN ", " TCI: ", " the ", " then ", 
          " till ", " to ", " UK ", " um ", " und ", " up ", " USA ", " van ", " vir ", 
          " von ", " voor ", " when ", " with ", " within ", " woor ", " y ", " zu ", " zum ", 
          " zur ", /* The above will be automatically updated to alphabetical order */ 
          // After this line we list exceptions that need re-capitalizing after they have been decapitalized.
          " El Dorado ", " Las Vegas ", " Los Angeles ", " N Y ", " U S A ");
const UC_SMALL_WORDS = array(/* The following will be automatically updated to alphabetical order */ 
          " Aap ", " Aaup ", " Abc ", " Ac ", " Acm ", " Agu ", " Ai ", " Aiaa ", " Ajhg ", 
          " Al ", " An ", " And ", " and Then ", " As ", " At ", " At ", " Aus ", " Av ", 
          " Bba ", " Bbc ", " Be ", " Bei ", " Bjpsych ", " Bmc ", " Bmj ", " But ", " By ", 
          " Cbc ", " Cns ", " D'un ", " D'une ", " D.c. ", " Da ", " Dans ", " Das ", " Dc ", 
          " De ", " Dei ", " Del ", " Della ", " Delle ", " Dem ", " Den ", " Der ", " Des ", 
          " Di ", " Die ", " Dna ", " Do ", " Du ", " E ", " Ed ", " Ee ", " Ein ", " Eine ", " Einen ", 
          " El ", " Else ", " Embo ", " En ", " Et ", " Faseb ", " Fda ", " Febs ", " Fems ", 
          " For ", " From ", " För ", " Für ", " Ieee ", " If ", " Ilr ", " In ", " Into ", 
          " Is ", " Its ", " Jama ", " Jama: ", " La ", " Las ", " Le ", " Les ", " Los ", 
          " Mit ", " Mnras ", " Mot ", " N.y. ", " N.y.) ", " Nasa ", " Nejm ", " Non ", 
          " Nor ", " Nrc ", " Ny ", " Nyc ", " Nyt ", " Nz ", " Och ", " Oecd ", " Of ", 
          " Off ", " Og ", " On ", " Or ", " Over ", " Pcr ", " Per ", " Pnas ", " Ps: ", 
          " R&d ", " Rna ", " Rté ", " S&p ", " Sae ", " Ssrn ", " Tci: ", " The ", " Then ", 
          " Till ", " To ", " Uk ", " Um ", " Und ", " Up ", " Usa ", " Van ", " Vir ", 
          " Von ", " Voor ", " When ", " With ", " Within ", " Woor ", " Y ", " Zu ", " Zum ", 
          " Zur ", /* The above will be automatically updated to alphabetical order */ 
          // After this line we list exceptions that need re-capitalizing after they have been decapitalized.
          " el Dorado ", " las Vegas ", " los Angeles ", " N y ", " U S a ");
          // For ones that start with lower-case, include both ELife and Elife versions in misspelled array

const JOURNAL_ACRONYMS = array(/* The following will be automatically updated to alphabetical order */
          " (BBA) ", " (online ed.) ", " AAPOS ", " AAPS ", " ACS ", " Algebra i Analiz ", 
          " Angew Chem Int Ed ", " APS Division ", " Arch Dis Child Fetal Neonatal Ed ", 
          " ASAIO ", " ASME AES ", " ASME MTD ", " Avtomatika i Telemekhanika ", " B/gcvs ", " B/gcvs ", " B/gcvs ", " BioEssays ", 
          " bioRxiv ", " bioRxiv ", " BJOG ", " BMJ ", " CBD Ubiquitin ", " CFSK-DT ", 
          " CMAJ ", " dell'Accademia ", " Drug Des Devel Ther ", " Dtsch ", " Dtsch. ", 
          " e-Journal ", " e-Journal ", " e-Neuroforum ", " e-Neuroforum ", " e-Print ", 
          " e-Print ", " e-Prints ", " e-Prints ", " Early Modern Japan: an Interdisciplinary Journal ", 
          " eCrypt ", " eCrypt ", " EFSA ", " eGEMs ", " eGEMs ", " eJournal ", " eJournal ",  " Eksperimental'naia i Klinicheskaia ",
          " eLife ", " eLife ", " eLS ", " eLS ", " EMBO J ", " EMBO J. ", " EMBO Journal ", 
          " EMBO Rep ", " EMBO Rep. ", " EMBO Reports ", " eNeuro ", " eNeuro ", " engrXiv ", 
          " ePlasty ", " ePlasty ", " ePrint ", " ePrint ", " ePrints ", " ePrints ", " ePub ", 
          " ePub ", " ePub) ", " eScholarship ", " eVolo ", " eVolo ", " eWeek ", " eWeek ", 
          " FASEB J ", " FASEB J. ", " FEBS J ", " FEBS J. ", " FEBS Journal ", " Fizika Goreniya i Vzryva ", " Föreningen i Stockholm ", 
          " für anorganische und allgemeine ", " HannahArendt.net ", " History of Science; An Annual Review of Literature ", 
          " HOAJ biology ", " Hoppe-Seyler's ", " hprints ", "  i ee ", "  i ee ", " iConference ", " IEEE/ACM ", 
          " IEEE/ACM ", " IFAC-PapersOnLine ", " iJournal ", " iJournal ", " im Gesundheitswesen ", 
          " iPhone ", " iScience ", " iScience ", " ISME ", " ISRN Genetics ", " J Gerontol A Biol Sci Med Sci ", 
          " J Sch Nurs ", " J SIAM ", " J. SIAM ", " JABS : Journal of Applied Biological Sciences ", 
          " JAMA Psychiatry ", " Journal of Materials Chemistry A ", " Journal of the A.I.E.E. ", 
          " Journal of the IEST ", " Jpn ", " Jpn. ", " La Trobe ", " Latina/o ", " Ltd ", 
          " mAbs ", " mAbs ", " mBio ", " mBio ", " Med Sch ", " Meddelelser om Grønland ", 
          " Meddelelser om Grønland, ", " MERIP ", " Methods in Molecular Biology ", " mHealth ", 
          " mHealth ", " Molecular and Cellular Biology ", " Montana The Magazine of Western History ", 
          " mSphere ", " mSphere ", " mSystems ", " mSystems ", " n.paradoxa ", " NASA Tech Briefs ", 
          " NBER ", " NDT & E International ", " NeuroReport ", " Notes of the AAS ", " Ny Forskning i Grammatik ", 
          " Nyt Tidsskrift ", " Ocean Science Journal : Osj ", " PAJ: A Journal of Performance and Art ", 
          " PALAIOS ", " PeerJ ", " PhytoKeys ", " Pis'ma v Astronomicheskii ", " PLOS ", 
          " PLOS ", " PLOS ", " PNAS ", " Published in: ", " RNA ", " S.A.P.I.EN.S ", 
          " Sch ", " Scr. ", " Srp Arh Celok Lek ", " Star Trek: The Official Monthly Magazine ", 
          " STDs ", " Série A ", " Tellus A ", " The De Paulia ", " The EMBO Journal ", 
          " Time Off Magazine ", " Time Out London ", " tot de ", " Transactions and archaeological record of the Cardiganshire Antiquarian Society ", 
          " U.S. ", " U.S.A. ", " U.S.A. ", " uHealth ", " uHealth ", " USGS ", " v Astronomicheskii Zhurna ", 
          " WRIR ", " z/Journal ", " z/Journal ", " zbMATH ", " Zeitschrift für Geologische Wissenschaften ", 
          " Zeitschrift für Physik A Hadrons and Nuclei ", " Zeitschrift für Physik A: Hadrons and Nuclei ", 
          " ZooKeys ", /* The above will be automatically updated to alphabetical order */ 
);
const UCFIRST_JOURNAL_ACRONYMS = array(/* The following will be automatically updated to alphabetical order */ 
          " (Bba) ", " (online Ed.) ", " Aapos ", " Aaps ", " Acs ", " Algebra I Analiz ", 
          " Angew Chem Int ed ", " Aps Division ", " Arch Dis Child Fetal Neonatal ed ", 
          " Asaio ", " Asme Aes ", " Asme MTD ", " Avtomatika I Telemekhanika ", " B/GCVS ", " B/Gcvs ", " b/gcvs ", " Bioessays ", 
          " BioRxiv ", " Biorxiv ", " Bjog ", " Bmj ", " Cbd Ubiquitin ", " CFSK-Dt ", 
          " Cmaj ", " Dell'Accademia ", " Drug des Devel Ther ", " DTSCH ", " DTSCH. ", 
          " E-Journal ", " E-journal ", " E-Neuroforum ", " E-neuroforum ", " E-Print ", 
          " E-print ", " E-Prints ", " E-prints ", " Early Modern Japan: An Interdisciplinary Journal ", 
          " ECrypt ", " Ecrypt ", " Efsa ", " EGEMs ", " Egems ", " EJournal ", " Ejournal ", " Eksperimental'naia I Klinicheskaia ",
          " ELife ", " Elife ", " ELS ", " Els ", " Embo J ", " Embo J. ", " Embo Journal ", 
          " Embo Rep ", " Embo Rep. ", " Embo Reports ", " ENeuro ", " Eneuro ", " Engrxiv ", 
          " EPlasty ", " Eplasty ", " EPrint ", " Eprint ", " EPrints ", " Eprints ", " EPub ", 
          " Epub ", " EPub) ", " Escholarship ", " EVolo ", " Evolo ", " EWeek ", " Eweek ", 
          " Faseb J ", " Faseb J. ", " Febs J ", " Febs J. ", " Febs Journal ", " Fizika Goreniya I Vzryva ", " Föreningen I Stockholm ", 
          " Für Anorganische und Allgemeine ", " Hannaharendt.net ", " History of Science; an Annual Review of Literature ", 
          " Hoaj Biology ", " Hoppe-Seyler´s ", " Hprints ", "  I Ee ", "  I ee ", " Iconference ", " IEEE/Acm ", 
          " Ieee/Acm ", " Ifac-Papersonline ", " IJournal ", " Ijournal ", " Im Gesundheitswesen ", 
          " Iphone ", " IScience ", " Iscience ", " Isme ", " Isrn Genetics ", " J Gerontol a Biol Sci Med Sci ", 
          " J SCH Nurs ", " J Siam ", " J. Siam ", " Jabs : Journal of Applied Biological Sciences ", 
          " Jama Psychiatry ", " Journal of Materials Chemistry A ", " Journal of the A.i.i.e ", 
          " Journal of the Iest ", " JPN ", " JPN. ", " la Trobe ", " Latina/O ", " LTD ", 
          " MAbs ", " Mabs ", " MBio ", " Mbio ", " Med SCH ", " Meddelelser Om Grønland ", 
          " Meddelelser Om Grønland, ", " Merip ", " Methods in Molecular Biology (Clifton, N.j.) ", 
          " MHealth ", " Mhealth ", " Molecular and Cellular Biology ", " Montana the Magazine of Western History ", 
          " MSphere ", " Msphere ", " MSystems ", " Msystems ", " N.Paradoxa ", " Nasa Tech Briefs ", 
          " Nber ", " NDT & e International ", " Neuroreport ", " Notes of the Aas ", " NY Forskning I Grammatik ", 
          " NYT Tidsskrift ", " Ocean Science Journal : Osj ", " Paj: A Journal of Performance and Art ", 
          " Palaios ", " Peerj ", " Phytokeys ", " Pis'ma V Astronomicheskii ", " PLoS ", 
          " Plos ", " plos ",
          " Pnas ", " Published In: ", " Rna ", " S.a.p.i.en.s ", 
          " SCH ", " SCR. ", " SRP Arh Celok Lek ", " Star Trek: The Official Monthly Magazine ", 
          " STDS ", " Série a ", " Tellus a ", " The de Paulia ", " The Embo Journal ", 
          " Time off Magazine ", " Time Out London ", " Tot de ", " Transactions and Archaeological Record of the Cardiganshire Antiquarian Society ", 
          " U.s. ", " U.S.a. ", " U.s.a ", " UHealth ", " Uhealth ", " Usgs ", " V Astronomicheskii Zhurna ", 
          " Wrir ", " Z/Journal ", " Z/journal ", " ZbMATH ", " Zeitschrift Für Geologische Wissenschaften ", 
          " Zeitschrift für Physik a Hadrons and Nuclei ", " Zeitschrift Für Physik a: Hadrons And Nuclei ", 
          " Zookeys ", /* The above will be automatically updated to alphabetical order */ 
);

const OBVIOUS_FOREIGN_WORDS = array(" Abhandlungen ", " Actes ", " Annales ", " Archiv ", " Archives de ",
           " Archives du  ", " Archives des ", " Beiträge ", " Berichten ", " Blätter ", " Bulletin de ",
           " Bulletin des ", " Bulletin du ", " Cahiers ", " canaria ", " Carnets ", " Comptes rendus ",
           " Fachberichte ", " Historia ",
           " Jahrbuch ", " Journal du ", " Journal de ", " Journal des ", " Journal für ", " Mitteilungen ",
           " Monatshefte ", " Monatsschrift ", " Mémoires ", " Notizblatt ", " Recueil ", " Revista ",
           " Revue ", " Travaux ",
           " Studien ", " Wochenblatt ", " Wochenschrift ", " Études ", " Mélanges ", " l'École ",
           " Française ", " Estestvoznaniya ",
           " Voprosy ", " Istorii ", " Tekhniki ", " Matematika ", " Shkole ", " Ruch ", " Prawniczy ",
           " Ekonomiczny ", " Socjologiczny ", " Rivista ", " degli ", " studi ", " orientali ", " met den ",
           " Textes ", " pour nos ", " élèves ", " Lettre ", " Zeitschrift ", " für ", " Physik ", " Phonetik ",
           " allgemeine ", " Sprachwissenschaft ", " Maître ", " Phonétique ", " Arqueología ", " Códices ",
           " prehispánicos ", " coloniales ", " tempranos ", " Catálogo ",
           " Ekolist ", " revija ", " okolju ", " geographica ", " Slovenica ", " Glasnik ",
           " Muzejskega ", " Društva ", " Slovenijo ", " razgledi ", " Istorija ", " Mokslo ", " darbai ",
           " amžius ", " humanitarica ", " universitatis ", " Saulensis ", " oftalmologija ", " dienos ",
           " Lietuvos ", " muziejų ", " rinkiniai ", " Traduction ", " Terminologie ", " Rédaction ",
           " Etudes ", " irlandaises  ", " Studia ", " humaniora ", " Estonica ",
           " Archiwa ", " Biblioteki  ", " Muzea ", " Kościelne ", " Zbornik ",			   
           " Radova ", " Filozofskog  ", " Fakulteta ", " Prištini ");
 
const MAP_DIACRITICS = array("À"=>"A", "Á"=>"A", "Â"=>"A", "Ã"=>"A",
	"Ä"=>"A", "Å"=>"A", "Æ"=>"AE", "Ç"=>"C", "È"=>"E", "É"=>"E",
	"Ê"=>"E", "Ë"=>"E", "Ì"=>"I", "Í"=>"I", "Î"=>"I", "Ï"=>"I",
	"Ð"=>"ETH", "Ñ"=>"N", "Ò"=>"O", "Ó"=>"O", "Ô"=>"O", "Õ"=>"O",
	"Ö"=>"O", "Ø"=>"O", "Ù"=>"U", "Ú"=>"U", "Û"=>"U", "Ü"=>"U",
	"Ý"=>"Y", "Þ"=>"THORN", "ß"=>"s", "à"=>"a", "á"=>"a", "â"=>"a",
	"ã"=>"a", "ä"=>"a", "å"=>"a", "æ"=>"ae", "ç"=>"c", "è"=>"e",
	"é"=>"e", "ê"=>"e", "ë"=>"e", "ì"=>"i", "í"=>"i", "î"=>"i",
	"ï"=>"i", "ð"=>"eth", "ñ"=>"n", "ò"=>"o", "ó"=>"o", "ô"=>"o",
	"õ"=>"o", "ö"=>"o", "ø"=>"o", "ù"=>"u", "ú"=>"u", "û"=>"u",
	"ü"=>"u", "ý"=>"y", "þ"=>"thorn", "ÿ"=>"y", "Ā"=>"A", "ā"=>"a",
	"Ă"=>"A", "ă"=>"a", "Ą"=>"A", "ą"=>"a", "Ć"=>"C", "ć"=>"c",
	"Ĉ"=>"C", "ĉ"=>"c", "Ċ"=>"C", "ċ"=>"c", "Č"=>"C", "č"=>"c",
	"Ď"=>"D", "ď"=>"d", "Đ"=>"D", "đ"=>"d", "Ē"=>"E", "ē"=>"e",
	"Ĕ"=>"E", "ĕ"=>"e", "Ė"=>"E", "ė"=>"e", "Ę"=>"E", "ę"=>"e",
	"Ě"=>"E", "ě"=>"e", "Ĝ"=>"G", "ĝ"=>"g", "Ğ"=>"G", "ğ"=>"g",
	"Ġ"=>"G", "ġ"=>"g", "Ģ"=>"G", "ģ"=>"g", "Ĥ"=>"H", "ĥ"=>"h",
	"Ħ"=>"H", "ħ"=>"h", "Ĩ"=>"I", "ĩ"=>"i", "Ī"=>"I", "ī"=>"i",
	"Ĭ"=>"I", "ĭ"=>"i", "Į"=>"I", "į"=>"i", "İ"=>"I", "ı"=>"i",
	"Ĵ"=>"J", "ĵ"=>"j", "Ķ"=>"K", "ķ"=>"k", "ĸ"=>"kra", "Ĺ"=>"L",
	"ĺ"=>"l", "Ļ"=>"L", "ļ"=>"l", "Ľ"=>"L", "ľ"=>"l", "Ŀ"=>"L",
	"ŀ"=>"l", "Ł"=>"L", "ł"=>"l", "Ń"=>"N", "ń"=>"n", "Ņ"=>"N",
	"ņ"=>"n", "Ň"=>"N", "ň"=>"n", "ŉ"=>"n", "Ŋ"=>"ENG", "ŋ"=>"eng",
	"Ō"=>"O", "ō"=>"o", "Ŏ"=>"O", "ŏ"=>"o", "Ő"=>"O", "ő"=>"o",
	"Ŕ"=>"R", "ŕ"=>"r", "Ŗ"=>"R", "ŗ"=>"r", "Ř"=>"R", "ř"=>"r",
	"Ś"=>"S", "ś"=>"s", "Ŝ"=>"S", "ŝ"=>"s", "Ş"=>"S", "ş"=>"s",
	"Š"=>"S", "š"=>"s", "Ţ"=>"T", "ţ"=>"t", "Ť"=>"T", "ť"=>"t",
	"Ŧ"=>"T", "ŧ"=>"t", "Ũ"=>"U", "ũ"=>"u", "Ū"=>"U", "ū"=>"u",
	"Ŭ"=>"U", "ŭ"=>"u", "Ů"=>"U", "ů"=>"u", "Ű"=>"U", "ű"=>"u",
	"Ų"=>"U", "ų"=>"u", "Ŵ"=>"W", "ŵ"=>"w", "Ŷ"=>"Y", "ŷ"=>"y",
	"Ÿ"=>"Y", "Ź"=>"Z", "ź"=>"z", "Ż"=>"Z", "ż"=>"z", "Ž"=>"Z",
	"ž"=>"z", "ſ"=>"s", "ƀ"=>"b", "Ɓ"=>"B", "Ƃ"=>"B", "ƃ"=>"b",
	"Ƅ"=>"SIX", "ƅ"=>"six", "Ɔ"=>"O", "Ƈ"=>"C", "ƈ"=>"c", "Ɖ"=>"D",
	"Ɗ"=>"D", "Ƌ"=>"D", "ƌ"=>"d", "ƍ"=>"delta", "Ǝ"=>"E",
	"Ə"=>"SCHWA", "Ɛ"=>"E", "Ƒ"=>"F", "ƒ"=>"f", "Ɠ"=>"G",
	"Ɣ"=>"GAMMA", "ƕ"=>"hv", "Ɩ"=>"IOTA", "Ɨ"=>"I", "Ƙ"=>"K",
	"ƙ"=>"k", "ƚ"=>"l", "ƛ"=>"lambda", "Ɯ"=>"M", "Ɲ"=>"N", "ƞ"=>"n",
	"Ɵ"=>"O", "Ơ"=>"O", "ơ"=>"o", "Ƣ"=>"OI", "ƣ"=>"oi", "Ƥ"=>"P",
	"ƥ"=>"p", "Ƨ"=>"TWO", "ƨ"=>"two", "Ʃ"=>"ESH", "ƫ"=>"t", "Ƭ"=>"T",
	"ƭ"=>"t", "Ʈ"=>"T", "Ư"=>"U", "ư"=>"u", "Ʊ"=>"UPSILON", "Ʋ"=>"V",
	"Ƴ"=>"Y", "ƴ"=>"y", "Ƶ"=>"Z", "ƶ"=>"z", "Ʒ"=>"EZH", "Ƹ"=>"EZH",
	"ƹ"=>"ezh", "ƺ"=>"ezh", "Ƽ"=>"FIVE", "ƽ"=>"five", "Ǆ"=>"DZ",
	"ǅ"=>"D", "ǆ"=>"dz", "Ǉ"=>"LJ", "ǈ"=>"L", "ǉ"=>"lj", "Ǌ"=>"NJ",
	"ǋ"=>"N", "ǌ"=>"nj", "Ǎ"=>"A", "ǎ"=>"a", "Ǐ"=>"I", "ǐ"=>"i",
	"Ǒ"=>"O", "ǒ"=>"o", "Ǔ"=>"U", "ǔ"=>"u", "Ǖ"=>"U", "ǖ"=>"u",
	"Ǘ"=>"U", "ǘ"=>"u", "Ǚ"=>"U", "ǚ"=>"u", "Ǜ"=>"U", "ǜ"=>"u",
	"ǝ"=>"e", "Ǟ"=>"A", "ǟ"=>"a", "Ǡ"=>"A", "ǡ"=>"a", "Ǣ"=>"AE",
	"ǣ"=>"ae", "Ǥ"=>"G", "ǥ"=>"g", "Ǧ"=>"G", "ǧ"=>"g", "Ǩ"=>"K",
	"ǩ"=>"k", "Ǫ"=>"O", "ǫ"=>"o", "Ǭ"=>"O", "ǭ"=>"o", "Ǯ"=>"EZH",
	"ǯ"=>"ezh", "ǰ"=>"j", "Ǳ"=>"DZ", "ǲ"=>"D", "ǳ"=>"dz", "Ǵ"=>"G",
	"ǵ"=>"g", "Ƕ"=>"HWAIR", "Ƿ"=>"WYNN", "Ǹ"=>"N", "ǹ"=>"n",
	"Ǻ"=>"A", "ǻ"=>"a", "Ǽ"=>"AE", "ǽ"=>"ae", "Ǿ"=>"O", "ǿ"=>"o",
	"Ȁ"=>"A", "ȁ"=>"a", "Ȃ"=>"A", "ȃ"=>"a", "Ȅ"=>"E", "ȅ"=>"e",
	"Ȇ"=>"E", "ȇ"=>"e", "Ȉ"=>"I", "ȉ"=>"i", "Ȋ"=>"I", "ȋ"=>"i",
	"Ȍ"=>"O", "ȍ"=>"o", "Ȏ"=>"O", "ȏ"=>"o", "Ȑ"=>"R", "ȑ"=>"r",
	"Ȓ"=>"R", "ȓ"=>"r", "Ȕ"=>"U", "ȕ"=>"u", "Ȗ"=>"U", "ȗ"=>"u",
	"Ș"=>"S", "ș"=>"s", "Ț"=>"T", "ț"=>"t", "Ȝ"=>"YOGH", "ȝ"=>"yogh",
	"Ȟ"=>"H", "ȟ"=>"h", "Ƞ"=>"N", "ȡ"=>"d", "Ȣ"=>"OU", "ȣ"=>"ou",
	"Ȥ"=>"Z", "ȥ"=>"z", "Ȧ"=>"A", "ȧ"=>"a", "Ȩ"=>"E", "ȩ"=>"e",
	"Ȫ"=>"O", "ȫ"=>"o", "Ȭ"=>"O", "ȭ"=>"o", "Ȯ"=>"O", "ȯ"=>"o",
	"Ȱ"=>"O", "ȱ"=>"o", "Ȳ"=>"Y", "ȳ"=>"y", "ȴ"=>"l", "ȵ"=>"n",
	"ȶ"=>"t", "ȷ"=>"j", "ȸ"=>"db", "ȹ"=>"qp", "Ⱥ"=>"A", "Ȼ"=>"C",
	"ȼ"=>"c", "Ƚ"=>"L", "Ⱦ"=>"T", "ȿ"=>"s", "ɀ"=>"z", "Ɂ"=>"STOP",
	"ɂ"=>"stop", "Ƀ"=>"B", "Ʉ"=>"U", "Ʌ"=>"V", "Ɇ"=>"E", "ɇ"=>"e",
	"Ɉ"=>"J", "ɉ"=>"j", "Ɋ"=>"Q", "ɋ"=>"q", "Ɍ"=>"R", "ɍ"=>"r",
	"Ɏ"=>"Y", "ɏ"=>"y", "ɐ"=>"a", "ɑ"=>"alpha", "ɒ"=>"alpha",
	"ɓ"=>"b", "ɔ"=>"o", "ɕ"=>"c", "ɖ"=>"d", "ɗ"=>"d", "ɘ"=>"e",
	"ə"=>"schwa", "ɚ"=>"schwa", "ɛ"=>"e", "ɜ"=>"e", "ɝ"=>"e",
	"ɞ"=>"e", "ɟ"=>"j", "ɠ"=>"g", "ɡ"=>"script", "ɣ"=>"gamma",
	"ɤ"=>"rams", "ɥ"=>"h", "ɦ"=>"h", "ɧ"=>"heng", "ɨ"=>"i",
	"ɩ"=>"iota", "ɫ"=>"l", "ɬ"=>"l", "ɭ"=>"l", "ɮ"=>"lezh", "ɯ"=>"m",
	"ɰ"=>"m", "ɱ"=>"m", "ɲ"=>"n", "ɳ"=>"n", "ɵ"=>"barred",
	"ɷ"=>"omega", "ɸ"=>"phi", "ɹ"=>"r", "ɺ"=>"r", "ɻ"=>"r", "ɼ"=>"r",
	"ɽ"=>"r", "ɾ"=>"r", "ɿ"=>"r", "ʂ"=>"s", "ʃ"=>"esh", "ʄ"=>"j",
	"ʅ"=>"squat", "ʆ"=>"esh", "ʇ"=>"t", "ʈ"=>"t", "ʉ"=>"u",
	"ʊ"=>"upsilon", "ʋ"=>"v", "ʌ"=>"v", "ʍ"=>"w", "ʎ"=>"y", "ʐ"=>"z",
	"ʑ"=>"z", "ʒ"=>"ezh", "ʓ"=>"ezh", "ʚ"=>"e", "ʞ"=>"k", "ʠ"=>"q",
	"ʣ"=>"dz", "ʤ"=>"dezh", "ʥ"=>"dz", "ʦ"=>"ts", "ʧ"=>"tesh",
	"ʨ"=>"tc", "ʩ"=>"feng", "ʪ"=>"ls", "ʫ"=>"lz", "ʮ"=>"h", "ʯ"=>"h");
