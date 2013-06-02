#!/usr/bin/php
<?      
// $Id$
#$abort_mysql_connection = true; // Whilst there's a problem with login

foreach ($argv as $arg) {
  if (substr($arg, 0, 2) == "--") {
    $argument[substr($arg, 2)] = 1;
  } elseif (substr($arg, 0, 1) == "-") {
    $oArg = substr($arg, 1);
  } else {
    switch ($oArg) {
      case "P": case "A": case "T":
        $argument["pages"][] = $arg;
        break;
      default:
      $argument[$oArg][] = $arg;
    }
  }
}

error_reporting(E_ALL^E_NOTICE);
$slow_mode = ($argument["slow"] || $argument["slowmode"] || $argument["thorough"]) ? true : false;
$accountSuffix = '_' . ($argument['user'] ? $argument['user'][0] : '1'); // Keep this before including expandFns
include("expandFns.php");
$htmlOutput = false;
$editInitiator = '[Pu' . (revisionID() + 1) . '&beta;]';
define ("START_HOUR", date("H"));
#die (findISBN(""));

function nextPage($page){
  // touch last page
  if ($page) {
    touch_page($page);
  }

  // Get next page
  global $ON, $STOP;
	if (!$ON || $STOP) die ("\n** EXIT: Bot switched off.\n");
  if (date("H") != START_HOUR) die ("\n ** EXIT: It's " . date("H") . " o'clock!\n");
	$db = udbconnect("yarrow");
	$result = mysql_query ("SELECT /* SLOW_OK */ page FROM citation ORDER BY fast ASC") or die(mysql_error());
	$result = mysql_query("SELECT /* SLOW_OK */ page FROM citation ORDER BY fast ASC") or die (mysql_error());
	$result = mysql_fetch_row($result);
  mysql_close($db);
	return $result[0];
}
$ON = $argument["on"];
###########
/*/
/*
foreach ($argument["pages"] as $page) {
  $input[] = array("{{automatic taxobox$paras}}", $page);
  $input[] = array("{{automatic taxobox/sandbox$paras}}", $page);
};
//$paras = "|fossil range = Cretaceous";

foreach ($input as $code) {
  $output = explode("NewPP limit report", parse_wikitext($code[0], $code[1]));
  print "$code[0] @ $code[1] \n " . $output[1];
}
// Fossil range adds about 10,000 / 30,000 /. 10,000 to counts if it's set. "Cretaceous";
// The fossil range template itself adds 8311 / 11600 / 1552

die();
*/
###########


if ($argument["pages"]) {
  foreach ($argument["pages"] as $page) {
    expand($page, $ON);
  }
} elseif ($argument["sandbox"] || $argument["sand"]) {
  expand("User:DOI bot/Zandbox", $ON);
} else {
   if ($ON) {
    echo "\n Fetching first page from backlog ... ";
    $page = nextPage($page);
    echo " done. ";
  } else {
   
    
    /*  die (expand_text("DELETION OF REF ARana 
         <ref name=ARanard/><ref name=MinNaing/> 
          <ref name=ARana/>, 
          
{{reflist|1|refs=
<ref name=Ranard>{{harv|Ranard|2009|pp=47–64}}</ref>
<ref name=ARanard>{{harv|Ranard|2009|pp=6, 18, 222, Endnote 15}}</ref>
<ref name=AR>{{harv|Ranard|2009|p=60, Fig. 62}}</ref>
<ref name=Ran>{{harv|Ranard|2009|pp=71–89}}</ref>
<ref name=R>{{harv|Ranard|2009|p=117}}</ref>
<ref name=Naing>{{harv|Naing|1974}}</ref>
<ref name=MinNaing>{{harv|Naing|1975|pp=2–25}}</ref>
<ref name=Shein>{{harv|Shein|1998|p=61–67}}</ref>
<ref name=GHlaMaung>{{harv|Maung|1968|p=81–85}}</ref>
<ref name=GHMaung>{{harv|Maung|1968|p=95–97}}</ref>
<ref name=ARana>{{harv|Ranard|2009|p=58, Fig. 60}}</ref>
<ref name=Hudson>{{harv|Hudson|1975|pp=60–72, 84, 124–128}}</ref>
}}

"));
    */
$problem_text =             <<<Deletionofreferences
      
{{Use British English|date=January 2012}}
{{Use Indian English|date=September 2011}}
{{Use dmy dates|date=January 2012}}
{{Infobox Hindu leader
| name = Jagadguru Rambhadracharya<br /> <small>{{lang|sa|??????????????????????}}<br />{{lang|hi|???????? ?????????????}}</small>
| image = Jagadguru Rambhadracharya.jpg
| image_size = 200px
| caption = Jagadguru Rambhadracharya delivering a sermon on 25 October 2009 in [[Moradabad]], Uttar Pradesh, India
| alt=Rambhadracharya
| birth_date = {{birth date and age|1950|1|14|df=y}}
| birth_place = Shandikhurd, [[Jaunpur district]], Uttar Pradesh, India
| birth_name = Giridhar Mishra
| sect = [[Ramanandi sect]]
| guru ={{ubl|Ishvardas (Mantra)|Ramprasad Tripathi (Sanskrit)|Ramcharandas (Sampradaya)}}
| philosophy = ''[[Vishishtadvaita|Vishishtadvaita Vedanta]]''
| honours = ''Dharmacakravart?'', ''Mah?mahop?dhy?ya'', ''?r?citrak??atulas?p??h?dh??vara'', ''Jagadguru R?m?nand?c?rya'', ''Mah?kavi'', ''Prasth?natray?bh??yak?ra'', and others
| quote = Humanity is my temple, and I am its worshiper. The disabled are my supreme God, and I am their grace seeker.<ref group="lower-roman" name="JRHU CD"/>
| founder = {{ubl|[[Jagadguru Rambhadracharya Handicapped University]]|[[Tulsi Peeth]]|Tulasi School for the Blind|Jagadguru Rambhadracharya Viklang Seva Sangh|''Kanch Mandir''|Jagadguru Rambhadracharya Viklang Shikshan Sansthan}}
| Literary works = ''[[Shriraghavkripabhashyam|?r?r?ghavak?p?bh??yam]]'' on [[Prasthanatrayi]], ''[[?r?bh?rgavar?ghav?yam]]'', ''[[Bh??gad?tam]]'', ''[[Gitaramayanam|G?tar?m?ya?am]]'', ''[[Srisitaramasuprabhatam|?r?s?t?r?masuprabh?tam]]'', ''[[Srisitaramakelikaumudi|?r?s?t?r?makelikaumudi]]'', ''[[Ashtavakra (epic)|A???vakra]]'', and others
| influenced =
| disciple=[[Abhiraj Rajendra Mishra]]
| signature=JagadguruRamabhadracharya013.png|alt=Thumb impression of Rambhadracharya
| footnotes= {{Reflist|group=lower-roman|refs=
<ref group="lower-roman" name="JRHU CD">
{{cite video |people=Rambhadracharya, Jagadguru (Speaker) |date=2003 |title=???????? ????????????? ??????? ????????????? | language=Hindi |trans_title=Jagadguru Rambhadracharya Handicapped University |medium=CD |location=Chitrakoot |publisher=Jagadguru Rambhadracharya Handicapped University |time=00:02:16 |quote=?????? ?? ???? ?????? ??? ??? ???? ?? ?????? ? ??? ??????? ??????? ???? ??? ??? ???? ?????????? ?}}</ref>
}}
{{IndicText|image=}}
}}
{{Rambhadracharya sidebar}}
'''Jagadguru Ramanandacharya Swami Rambhadracharya''',<ref group="lower-greek" name="IPA1"/><ref name="nagar125"/><ref name="tripathi94"/> (born  '''Giridhar Mishra''';  14 January 1950)<ref group="lower-greek" name="IPA2"/> is a [[Hindu]] religious leader, educationist, [[Sanskrit]] scholar, polyglot, poet, author, [[Commentary (philology)|textual commentator]], philosopher, composer, singer, playwright and ''[[Katha (storytelling format)|Katha]]'' artist based in [[Chitrakoot]], India.<ref name="speakerloksabha"/> He is one of four incumbent ''Jagadguru Ramanandacharya'',<ref group="lower-greek">Leaders of the [[Ramanandi sect|Ramananda monastic order]].</ref> and has held this title since 1988.<ref name="kbs-bio"/><ref name="agarwal-bio">Agarwal 2010, pp. 1108–1110.</ref><ref name="dinkarjagadguru">Dinkar 2008, p. 32.</ref>

Rambhadracharya is the founder and head of [[Tulsi Peeth]], a religious and social service institution in Chitrakoot named after Saint [[Tulsidas]].<ref>Nagar 2002, p. 91.</ref><ref name="the-eye"/> He is the founder and lifelong chancellor of the [[Jagadguru Rambhadracharya Handicapped University]] in Chitrakoot, which offers graduate and postgraduate courses exclusively to four types of disabled students.<ref name="tripathi94"/><ref name="speakerloksabha"/><ref>Dwivedi 2008, p. x.</ref><ref name="aicb-p68">Aneja 2005, p. 68.</ref><ref name="bhartiyapaksha"/> Rambhadracharya has been blind since the age of two months, but has never used [[Braille]] or any other aid to learn or compose.<ref name="aicb-p67">Aneja 2005, p. 67.</ref>

Rambhadracharya can speak 22&nbsp;languages and is a spontaneous poet<ref group="lower-greek">''Ashukavi''.</ref> and writer in Sanskrit, [[Hindi]], [[Awadhi language|Awadhi]], [[Maithili language|Maithili]], and several other languages.<ref name="kkbvp"/><ref name="dinkar-polyglot">Dinkar 2008, p. 39.</ref> He has authored more than 90&nbsp;books and 50&nbsp;papers,<ref name="tripathi94"/><ref name="rcm-judgement"/> including four epic poems,<ref group="lower-greek">Two each in [[Sanskrit]] and Hindi.</ref> a Hindi commentary on Tulsidas' [[Ramcharitmanas]], a Sanskrit commentary in verse on the [[Ashtadhyayi]], and Sanskrit commentaries on the [[Prasthanatrayi]] scriptures.<ref name="dinkarbiblio">Dinkar 2008, pp. 40–43.</ref> He is regarded as one of the greatest authorities on Tulsidas in India,<ref name="prasad-on-jr"/> and is the editor of a [[Textual criticism|critical edition]] of the Ramcharitmanas.<ref>Rambhadracharya (ed) 2006.</ref> He is a ''Katha'' artist for the [[Ramayana]] and the [[Bhagavata Purana|Bhagavata]]. His ''Katha'' programmes are held regularly in different cities in India and other countries, and are telecast on television channels like [[Sanskar TV]] and Sanatan TV.<ref name="programmes"/><ref name="sitanavmi"/>

==Birth and early life==
[[File:Shachidevi Mishra.png|thumb|150 px|alt=Shachidevi Mishra|An old photograph of Shachidevi Mishra, mother of Rambhadracharya]]
Jagadguru Rambhadracharya was born in a [[Saryupareen Brahmin]] family of the [[Vasishtha]] ''[[Gotra]]'' (lineage of the sage Vasishtha) in Shandikhurd village in the [[Jaunpur district]], Uttar Pradesh, India. He was born at 10:34&nbsp;pm on Saturday, 14&nbsp;January&nbsp;1950 ([[Magha (month)|Magha]] Krishna [[Ekadashi]]), during the [[Makar Sankranti]] festival, under the [[Anuradha (nakshatra)|Anuradha]] constellation.<ref name="tripathi94"/><ref name="bhartiyapaksha"/><ref name="nagarearlylife">Nagar 2002, pp. 37–53.</ref><ref name="aicb-p66">Aneja 2005, p. 66.</ref> Born to mother Shachidevi and father Pandit Rajdev Mishra, he was named ''Giridhar'' by his great aunt, a paternal cousin of his paternal grandfather, Pandit Suryabali Mishra. The great aunt was a devotee of [[Mirabai]], a female saint of the [[Bhakti movement|Bhakti era]] in medieval India, who used the name ''Giridhar'' to address the god [[Krishna]] in her compositions.<ref name="kkbvp"/><ref name="dinkarearlylife">Dinkar 2008, pp. 22–24.</ref>

===Loss of eyesight===
Giridhar lost his eyesight at the age of two months. On 24&nbsp;March&nbsp;1950, his eyes were infected by [[trachoma]]. There were no advanced facilities for treatment in the village, so he was taken to an elderly woman in a nearby village who was known to cure trachoma boils to provide relief. The woman applied a paste of myrobalan to Giridhar's eyes to burst the lumps, but his eyes started bleeding, resulting in the loss of his eyesight.<ref name="bhartiyapaksha"/><ref name="nagarearlylife"/><ref name="aicb-p66"/> His family took him to the King George Hospital in [[Lucknow]], where his eyes were treated for 21 days, but his sight could not be restored.<ref name="nagarearlylife"/> Various [[Ayurvedic]], [[Homeopathic]], [[Allopathic]], and other practitioners were approached in [[Sitapur]], Lucknow, and [[Bombay]], but to no avail.<ref name="dinkarearlylife"/> Rambhadracharya has been blind ever since. He cannot read or write, as he does not use [[Braille]]; he learns by listening and composes by dictating to scribes.<ref name="aicb-p67"/>

===Childhood accident===

In June 1953, at a juggler's monkey dance show in the village, the children—including Giridhar—suddenly ran away when the monkey began to touch them. Giridhar fell into a small dry well and was trapped for some time, until a teenage girl rescued him.<ref name="nagarearlylife"/> His grandfather told him that his life was saved because he had learned the following line of a verse in the [[Ramcharitmanas]] (1.192.4), from the episode of the manifestation of the god [[Rama]]:<ref name="nagarearlylife"/><ref>Prasad 1999, p. 133.</ref>
<center>
<poem>
?? ???? ?? ?????? ????? ?????? ?? ? ????? ?????? ?
yaha carita je g?vah?? haripada p?vah?? te na parah?? bhavak?p? ?
</poem>
{{Quote|Those who sing this lay attain to the feet of Hari (Vishnu) and never fall into the well of birth and death.}}
</center>
Giridhar's grandfather asked him to recite the verse always, and from then on, Giridhar has followed the practice of reciting it every time he takes water or food.<ref name="nagarearlylife"/>

===First composition===
Giridhar's initial education came from his paternal grandfather, as his father worked in Bombay. In the afternoons, his grandfather would narrate to him various episodes of the Hindu epics Ramayana and [[Mahabharata]], and devotional works like ''Vishramsagar'', ''Sukhsagar'', ''Premsagar'', and ''Brajvilas''. At the age of three, Giridhar composed his first piece of poetry—in Awadhi (a dialect of Hindi)—and recited it to his grandfather. In this verse, Krishna's foster mother [[Yashoda]] is fighting with a [[Gopi]] (milkmaid) for hurting Krishna.<ref name="nagarearlylife"/><ref name="dinkarearlylife"/>
{| style="margin-left:auto;margin-right:auto; width: 50em;"
|- style="text-align:center;"
|<poem>
[[Devanagari]]
???? ???????? ?? ?? ???? ????
??? ????? ???? ?????? ???? ???? ???? ?????
?????? ?????? ???? ?????? ???? ?? ??????? ????
?? ?????? ????? ????? ???? ?? ????
?????? ?? ??? ??? ????? ???? ?? ????
</poem>
| <poem>
[[IAST]]
mere giridh?r? j? se k?he lar??
tuma taru?? mero giridhara b?laka k?he bhuj? pakar??
susuki susuki mero giridhara rovata t? musuk?ta khar??
t? ahirina atisaya jhagar?? barabasa ?ya khar??
giridhara kara gahi kahata jasod? ??cara o?a kar??
</poem>
|-
| colspan="2" |
{{Quote|"'Why did you fight with my ''Giridhara'' (Krishna)? You are a young maiden, and my ''Giridhara'' (Krishna) is but a child, why did you hold his arm? My ''Giridhara'' (Krishna) is crying, sobbing repeatedly, and you stand there smirking! O [[Ahir]] lady (cowherd girl), you are excessively inclined to quarrel, and come and stand here uninvited'&nbsp;– so says Yashoda, holding on to the hand of ''Giridhara'' (Krishna) and covering [her face] with the end of her [[Sari]]", sings ''Giridhara'' (the poet).}}
|}

===Mastering Gita and Ramcharitmanas===
At the age of five, Giridhar memorised the entire [[Bhagavad Gita]], consisting of around 700&nbsp;verses with chapter and verse numbers, in 15&nbsp;days, with the help of his neighbour, Pandit Murlidhar Mishra. On [[Janmashtami]] day in 1955, he recited the entire Bhagavad Gita.<ref name="bhartiyapaksha"/><ref name="outlook"/><ref name="nagarearlylife"/><ref name="dinkarearlylife"/><ref name="parauha"/> He released the first Braille version of the scripture, with the original Sanskrit text and a Hindi commentary, at New Delhi on 30&nbsp;November&nbsp;2007, 52&nbsp;years after memorising the Gita.<ref name="Zee-2007"/><ref name="ANN-2007"/> When Giridhar was seven, he memorised the entire Ramcharitmanas of Tulsidas, consisting of around 10,900&nbsp;verses with chapter and verse numbers, in 60&nbsp;days, assisted by his grandfather. On [[Rama Navami]] day in 1957, he recited the entire epic while fasting.<ref name="bhartiyapaksha"/><ref name="nagarearlylife"/><ref name="dinkarearlylife"/><ref name="parauha"/> Later, Giridhar went on to memorise the [[Veda]]s, the [[Upanishads]], works of Sanskrit grammar, the [[Bhagavata Purana]], all the works of Tulsidas, and many other works in [[Sanskrit literature|Sanskrit]] and [[Indian literature|Indian]] literature.<ref name="outlook"/><ref name="dinkarearlylife"/>

===Upanayana and Katha discourses===

Giridhar's ''[[Upanayana]]'' (sacred thread ceremony) was performed on [[:hi:??????? ??????|Nirjala Ekadashi]] (the Ekadashi falling in the bright half of the lunar month of [[Jyeshtha]]) of 24 June 1961. On this day, besides being given the [[Gayatri Mantra]], he was initiated (given ''[[Diksha]]'') into the [[mantra]] of Rama by Pandit Ishvardas Maharaj of [[Ayodhya]]. Having mastered the Bhagavad Gita and Ramcharitmanas at a very young age, Giridhar started visiting the ''[[Katha (storytelling format)|Katha]]'' programmes held near his village once every three years in the [[Intercalation|intercalary]] month of ''[[Hindu calendar#Extra months|Purushottama]]''. The third time he attended, he presented a ''Katha'' on Ramcharitmanas, which was acclaimed by several famous exponents of the ''Katha'' art.<ref name="dinkarearlylife"/>

===Discrimination by family===

When Giridhar was eleven, he was stopped from joining his family in a wedding procession. His family thought that his presence would be a bad omen for the marriage.<ref name="nagarearlylife"/><ref name="aicb-p66"/> This incident left a strong impression on Giridhar; he says at the beginning of his autobiography:<ref>Nagar 2002, p. 37.</ref>

{{Quote|I am the same person who was considered to be inauspicious for accompanying a marriage party.&nbsp;... I am the same person who currently inaugurates the biggest of marriage parties or welfare ceremonies. What is all this? It is all due to the grace of God which turns a straw into a ''[[vajra]]'' and a ''vajra'' into a straw.}}

==Formal education==
===Schooling===
[[File:JagadguruRamabhadracharya009.jpg|thumb|180 px|alt=Young Giridhar Mishra|A young Giridhar Mishra in an undated photo]]
Although Giridhar did not have any formal schooling, he studied a great deal as a child. His family wished him to become a ''Kathavachak'' (a ''Katha'' artist) but Giridhar wanted to pursue his studies. His father explored possibilities for his education in [[Varanasi]] and thought of sending him to a special school for blind students. Giridhar's mother refused to send him there, saying that blind children were not treated well at the school.<ref name="aicb-p66"/> On 7&nbsp;July&nbsp;1967 Giridhar joined the Adarsh Gaurishankar Sanskrit College in the nearby Sujanganj village of Jaunpur to study Sanskrit ''[[Vyakarana]]'' (grammar), Hindi, English, Maths, History, and Geography.<ref name="dinkaredu">Dinkar 2008, pp. 25–27.</ref> In his autobiography he recalls this day as the day when the "Golden Journey" of his life began.<ref>Nagar 2002, p. 55.</ref> With an ability to memorise material by listening to it just once, Giridhar has not used Braille or other aids to study.<ref name="aicb-p67"/> In three months, he had memorised and mastered the entire ''Laghusiddh?ntakaumud?'' of [[Varadar?ja|Varadaraja]].<ref name="dinkaredu"/> He was top of his class for four years, and passed the ''Uttara Madhyama'' (higher secondary) examination in Sanskrit with first class and distinction.<ref name="parauha"/>

;First Sanskrit composition

At the Adarsh Gaurishankar Sanskrit College, Giridhar learnt the eight ''[[Sanskrit prosody#Ga?a|Ganas]]'' of Sanskrit prosody while studying ''Chandaprabh?'', a work on Sanskrit prosody. The next day, he composed his first Sanskrit verse, in the ''Bhuja?gapray?ta'' metre.<ref name="dinkaredu"/>
{| style="margin-left:auto;margin-right:auto; width: 50em;"
|- style="text-align:center;"
|
<poem>
Devanagari
????????????????????????
?????? ?????????????????? ?
????? ??? ???????? ?????
????? ???? ??? ???????????????? ?
</poem>
| style="width:50%;" |
<poem>
IAST
mah?ghora?ok?gnin?tapyam?na?
patanta? nir?s?rasa?s?rasindhau ?
n?tha? ja?a? mohap??ena baddha?
prabho p?hi m?? sevakakle?ahartta? ?
</poem>
|-
| colspan="2" |
{{Quote|O omnipotent Lord, remover of the distress of your worshippers! Protect me, who is being consumed by the extremely dreadful fire of sorrows, who is helplessly falling in the ocean of the mundane world, who is without any protector, who is ignorant, and who is bonded by the shackles of delusion.}}
|}

===Graduation and masters===
In 1971 Giridhar enrolled at the [[Sampurnanand Sanskrit University]] in Varanasi for higher studies in ''Vyakarana''.<ref name="dinkaredu"/> He topped the final examination for the ''Shastri'' (Bachelor of Arts)<ref name="gupta-kumar-ssu">Gupta and Kumar 2006, p. 745.</ref> degree in 1974, and then enrolled for the ''Acharya'' (Master of Arts)<ref name="gupta-kumar-ssu"/> degree at the same institute.<ref name="aicb-p67"/> While pursuing his master's degree, he visited New Delhi to participate in various national competitions at the All-India Sanskrit Conference, where he won five out of the eight gold medals—in ''Vyakarana'', ''[[Samkhya]]'', ''[[Nyaya]]'', ''[[Vedanta]]'', and Sanskrit ''[[Antakshari]]''.<ref name="kbs-bio"/><ref name="aicb-p67"/> [[Indira Gandhi]], then Prime Minister of India, presented the five gold medals, along with the ''Chalvaijayanti'' trophy for Uttar Pradesh, to Giridhar.<ref name="parauha"/> Impressed by his abilities, Gandhi offered to send him at her own expense to the United States for treatment for his eyes, but Giridhar turned down this offer, replying with an extemporaneous Sanskrit verse.<ref name="aicb-p67"/><ref>Nagar 2002, p. 72.</ref>
{| style="margin-left:auto;margin-right:auto; width: 50em;"
|- style="text-align:center;"
|
<poem>
Devanagari
??? ????????? ???????? ????????????????????
???????????????????? ?????????????? ?
???????????? ???????????? ???????????????????
??????????? ??????????? ?????????? ???????? ?
</poem>
|
<poem>
IAST
ki? d???avya? patitajagati vy?ptado?e'pyasatye
m?y?c?r?vratatanubh?t?? p?par?jadvic?re ?
d???avyo'sau cikuranikurai? p?r?avaktr?ravinda?
p?r??nando dh?ta?i?utanu? r?macandro mukunda? ?
</poem>
|-
| colspan="2" |
{{Quote|What is to be seen in this fallen world, which is false and filled with vices, is full of disputes and is governed by the sins of deceitful and wicked humans? Only Rama is worth seeing, whose flocks of hair cover his lotus-like face, who is completely blissful, who has the form of a child, and who is the giver of liberation.}}
|}

In 1976 Giridhar topped the final ''Acharya'' examinations in ''Vyakarana'', winning seven gold medals and the Chancellor's gold medal.<ref name="parauha"/> In a rare achievement, although he had only enrolled for a master's degree in ''Vyakarana'', he was declared ''Acharya'' of all subjects taught at the university on 30&nbsp;April&nbsp;1976.<ref name="aicb-p67"/>

===Doctorate and post-doctorate===
[[File:JagadguruRamabhadracharya010.jpg|thumb|180 px|left|alt=Rambhadracharya meditating during a Payovrata|Rambhadracharya meditating on the banks of Mandakini river during a ''[[Payovrata]]''. He is seated in the ''[[Sukhasana]]'' pose with fingers folded in the ''[[Mudra#Basic mudr?: Chin Mudr?|Chin Mudra]]''.]]
After completing his master's degree, Giridhar enrolled for the doctoral ''Vidyavaridhi'' (PhD)<ref name="Bhuyan-2002">Bhuyan 2002, p. 245.</ref> degree at the same institute, under Pandit Ramprasad Tripathi.<ref name="aicb-p67"/> He received a research fellowship from the [[University Grants Commission (India)|University Grants Commission]] (UGC), but even so, he faced financial hardship in these years.<ref name="aicb-p67"/> With great difficulty, he completed his ''Vidyavaridhi'' degree in Sanskrit grammar on 14 October 1981.<ref name="aicb-p67"/> His dissertation was titled ''Adhy?tmar?m?ya?e Ap??in?yaprayog?n?? Vimar?a?'', or ''Deliberation on the non-[[P??ini|Paninian]] usages in the [[Adhyatma Ramayana]]''. On completion of his doctorate, the UGC offered him the position of head of the ''Vyakarana'' department of the Sampurnanand Sanskrit University. However, Giridhar did not accept; he decided to devote his life to the service of religion, society, and the disabled.<ref name="aicb-p67"/>

On 9 May 1997, Giridhar (now known as Rambhadracharya) was awarded the post-doctorate ''Vachaspati'' (DLitt)<ref name="gupta-kumar-ssu"/><ref name="Bhuyan-2002"/> degree by Sampurnanand Sanskrit University for his Sanskrit dissertation ''A???dhy?yy?? Pratis?tra? ??bdabodhasam?k?a?am'', or ''Investigation into verbal knowledge of every [[Sutra|S?tra]] of the Ashtadhyayi''. The degree was presented to him by [[K. R. Narayanan]], then President of India.<ref>Nagar 2002, p. 89.</ref> In this work, Rambhadracharya explained each aphorism of the grammar of Panini in Sanskrit verses.<ref name="dinkaredu"/>

==Later life==
===1979–1988===

;Virakta Diksha
In 1976 Giridhar narrated a ''Katha'' on Ramcharitmanas to [[Swami Karpatri]], who advised him not to marry, to stay a lifelong ''[[Brahmacharya|Brahmachari]]'' (celibate bachelor) and to take initiation in a ''[[Vaishnava]] [[Sampradaya]]'' (a sect worshipping Vishnu, Krishna, or Rama as the supreme God).<ref name="vaishnava1916">{{cite book | editor1-first = Daniel Coit | editor1-last=Gilman | editor2-first=Harry Thurston | editor2-last=Peck | editor3-first=Frank Moore | editor3-last=Calby | year=1916 | title = New International Encyclopædia: Volume XXII | publisher=Dodd, Mead and Company | edition=Second | location = New York, New York, United States of America | url = http://ia600401.us.archive.org/21/items/newinternationa35unkngoog/newinternationa35unkngoog.pdf | accessdate=9 October 2011 | page=847}}</ref><ref name="dinkarlaterlife">Dinkar 2008, pp. 28–31.</ref> Giridhar took ''[[Sannyasa|vairagi]]'' (renouncer) initiation or ''Virakta Diksha'' in the [[Ramanandi sect|Ramananda Sampradaya]] on the ''[[Kartika (month)|Kartika]]'' full-moon day of 19&nbsp;November&nbsp;1983 from Shri Ramcharandas Maharaj Phalahari. He now came to be known as ''Rambhadradas''.<ref name="dinkarlaterlife"/>

;Six-month fasts
Following the fifth verse of the ''Dohavali'' composed by Tulsidas, Rambhadradas observed a six-month ''[[Payovrata]]'', a diet of only milk and fruits, at Chitrakoot in 1979.<ref name="dinkarlaterlife"/><ref>Poddar 1996, p. 10.</ref><ref name="Dubley-2011"/>
{| style="margin-left:auto;margin-right:auto; width: 50em;"
|- style="text-align:center;"
|
<poem>
Devanagari
?? ???? ?? ??? ??? ??? ??? ?? ??? ?
??? ?????? ?????? ?? ???? ???????? ?
</poem>
|
<poem>
IAST
paya ah?ra phala kh?i japu r?ma n?ma ?a?a m?sa ?
sakala suma?gala siddhi saba karatala tulas?d?sa ?
</poem>
|-
| colspan="2" |
{{Quote|Chant the name of Rama subsisting on a diet of milk and fruits for six months. Says Tulsidas, on doing so, all auspiciousness and accomplishments will be in one's hand.}}
|}

In 1983 he observed his second ''Payovrata'' beside the [[Chitrakoot#Sphatic Shila|Sphatik Shila]] in Chitrakoot.<ref name="dinkarlaterlife"/> The ''Payovrata'' has become a regular part of Rambhadradas' life. In 2002, in his sixth ''Payovrata'', he composed the Sanskrit epic ''?r?bh?rgavar?ghav?yam''.<!-- May not meet WP:Sources, so commenting out.--><!--<ref>{{cite book | language=Sanskrit | date=30 October 2002 | title = ?r?bh?rgavar?ghav?yam (Sa?sk?tamah?k?vyam) | trans_title=?r?bh?rgavar?ghav?yam (A Sanskrit epic poem) | first=Jagadguru | last=Rambhadracharya | place=Chitrakoot | publisher=Jagadguru Rambhadracharya Handicapped University | page=511}}</ref>--><ref>Dinkar 2008, p. 127.</ref> He continues to observe ''Payovrata''s, the latest (2010–2011) being his ninth.<ref name="virtues"/><ref name="research-institute"/>

;Tulsi Peeth
{{main|Tulsi Peeth}}
[[File:JagadguruRamabhadracharya002.jpg|thumb|right|alt=Rambhadracharya garlanding a statue of Tulsidas|Rambhadracharya garlanding a statue of Tulsidas at Tulsi Peeth, Chitrakoot, India, on 25 October 2009]]
In 1987 Rambhadradas established a religious and social service institution called '''Tulsi Peeth''' (The seat of [[Tulsi]]) in Chitrakoot, where, according to the Ramayana, Rama had spent twelve out of his fourteen years of exile.<ref name="the-eye"/> As the founder of the seat, the title of ''?r?citrak??atulas?p??h?dh??vara'' (literally, ''the Lord of the Tulsi Peeth at Chitrakoot'') was bestowed upon him by [[Sadhu]]s and intellectuals. In the Tulsi Peeth, he arranged for a temple devoted to Rama and his consort [[Sita]] to be constructed, which is known as ''Kanch Mandir'' ("glass temple").<ref name="the-eye"/>

===Post of Jagadguru Ramanandacharya===
Rambhadradas was chosen as the ''Jagadguru Ramanandacharya'' seated at the Tulsi Peeth by the Kashi Vidwat Parishad in Varanasi on 24&nbsp;June&nbsp;1988.<ref name="dinkarjagadguru"/> On 3&nbsp;February&nbsp;1989, at the ''[[Kumbh Mela]]'' in Allahabad, the appointment was unanimously supported by the ''[[Mahant]]s'' of the three ''[[Akhara]]s'', the four sub-''[[Sampradaya]]s'', the ''[[Khalsa]]s'' and saints of the Ramananda Sampradaya.<ref>Agarwal 2010, p. 781.</ref> On 1 August 1995 he was ritually anointed as the ''Jagadguru Ramanandacharya'' in Ayodhya by the Digambar Akhara.<ref name="kbs-bio"/> Thereafter he was known as ''Jagadguru Ramanandacharya Swami Rambhadracharya''.<ref name="nagar125">Nagar 2002, p. 125.</ref>

===Deposition in the Ayodhya case===

In July&nbsp;2003 Rambhadracharya deposed as an expert witness for religious matters (OPW 16) in Other Original Suit Number&nbsp;5 of the [[Ayodhya dispute|Ram Janmabhoomi Babri Masjid dispute]] case in the [[Allahabad High Court]].<ref name="Sharma-2003"/><ref name="Mid-day-2003-07-17"/><ref name="Mid-day-2003-07-21"/> Some portions of his affidavit and cross examination are quoted in the final judgement by the High Court.<ref name="rjbm-sa-verdict">Agarwal 2010, pp. 304, 309, 780–788, 1103–1110, 2004–2005, 4447, 4458–4459, 4537, 4891–4894, 4996.</ref><ref>Sharma 2010, pp. 21, 31.</ref><ref>Sharma 2010, p. 273.</ref> In his affidavit, he cited the ancient Hindu scriptures including the Ramayana, ''R?mat?pan?ya Upani?ad'', [[Skanda Purana]], [[Yajurveda]], [[Atharvaveda]], and others describing Ayodhya as a city holy to Hindus and the birthplace of Rama. He cited verses from two works composed by Tulsidas which, in his opinion, are relevant to the dispute. The first citation consisted of eight verses from a work called ''Doh? ?ataka'', which describe the destruction of a temple and construction of a mosque at the disputed site in 1528&nbsp;CE by [[Mughal emperors|Mughal]] ruler [[Babur]], who had ordered General Mir Baqui to destroy the Rama temple, considered a symbol of worship by infidels.<ref name="Mid-day-2003-07-17"/> The second citation was a verse from a work called ''Kavit?val?'', which mentions a mosque.<ref name="rjbm-sa-verdict"/> In his cross examination, he described in some detail the history of the Ramananda sect, its ''[[Matha]]s'', rules regarding ''Mahants'', formation and working of ''Akharas'', and Tulsidas' works.<ref name="rjbm-sa-verdict"/> Refuting the possibility of the original temple being to the north of the disputed area, as pleaded by the pro-mosque parties, he described the boundaries of the ''Janmabhoomi'' as mentioned in the ''Ayodhya Mahatmya'' section of Skanda Purana, which tallied with the present location of the disputed area, as noted by Justice Sudhir Agarwal.<ref name="rjbm-sa-verdict"/> However, he stated that he had no knowledge of whether there was a ''Ram Chabootra'' ("Platform of Rama") outside the area that was locked from 1950 to 1985 and where the ''Chati Poojan Sthal'' was, nor whether the idols of Rama, his brother [[Lakshmana]], and Sita were installed at ''Ram Chabootra'' outside the ''Janmabhoomi'' temple.<ref name="Mid-day-2003-07-17"/>

===Multilingualism===

Rambhadracharya can speak 22 languages<ref name="kkbvp"/><ref name="dinkar-polyglot"/> including Sanskrit, Hindi, English, French, [[Bhojpuri language|Bhojpuri]], [[Maithili language|Maithili]], [[Oriya language|Oriya]], [[Gujarati language|Gujarati]], [[Punjabi language|Punjabi]], [[Marathi language|Marathi]], [[Magahi language|Magadhi]], Awadhi, and [[Braj Bhasha|Braj]].<ref name="bhartiyapaksha"/> He has composed poems and literary works in many Indian languages, including Sanskrit, Hindi, and Awadhi.<ref name="speakerloksabha"/><ref name="bhartiyapaksha"/> He has translated many of his works of poetry and prose into other languages. He delivers ''Katha'' programmes in various languages, including Hindi, Bhojpuri, and Gujarati.<ref name="in-Dakor"/>

===Institutes for the disabled===
{{See also|Jagadguru Rambhadracharya Handicapped University}}
[[File:JRHU - Chancellor with students.jpg|thumb|alt=Rambhadracharya with mobility-impaired students|Rambhadracharya with mobility-impaired students in front of the main building of Jagadguru Rambhadracharya Handicapped University on 2 January 2005]]

On 23 August 1996 Rambhadracharya established the Tulsi School for the Blind in Chitrakoot, Uttar Pradesh.<ref name="the-eye"/><ref name="aicb-p68"/> He founded the Jagadguru Rambhadracharya Handicapped University, an institution of higher learning solely for disabled students, on 27 September 2001 in Chitrakoot.<ref name="aicb-p68"/><ref name="bhartiyapaksha"/> This is the first university in the world exclusively for the disabled.<ref name="Subhash-2005"/><ref name="Dikshit-2007"/> <!-- Following sentence is commented out till secondary source found.--> <!--It is run by a trust named ''Jagadguru Rambhadracharya Viklang Shikshan Sansthan'', which is responsible for implementation of all the university's plans.--> The university was created by an ordinance of the Uttar Pradesh Government, which was later passed as Uttar Pradesh State Act&nbsp;32&nbsp;(2001) by the Uttar Pradesh legislature.<ref name="creation"/><ref name="hou-jrhu">Gupta and Kumar 2006, p. 395.</ref> The act appointed Swami Rambhadracharya as the lifelong chancellor of the university. The university offers graduate, post-graduate, and doctorate degrees in various subjects, including Sanskrit, Hindi, English, Sociology, Psychology, Music, Drawing and Painting, Fine Arts, Special Education, Education, History, Culture and Archeology, Computer and Information Sciences, Vocational Education, Law, Economics, and [[Prosthetics]] and [[Orthotics]].<ref name="hou-jrhu"/> The university plans to start offering courses in Ayurveda and [[Medical Sciences]] from 2013.<ref name="amar-ujala"/> Admissions are restricted to the four types of disabled students—visually impaired, hearing impaired, mobility impaired, and mentally impaired—as defined by the Disability Act (1995) of the [[Government of India]]. According to the Government of Uttar Pradesh, the university is among the chief educational institutes for Information Technology and Electronics in the state.<ref name="DITE"/>

Rambhadracharya also founded an organisation called Jagadguru Rambhadracharya Viklang Seva Sangh, headquartered in [[Satna]], Madhya Pradesh. Its goal is to create community awareness and initiate child development programs in rural India. Its primary objective is to supplement the education programs of Jagadguru Rambhadracharya Handicapped University by helping disabled children get a good education. Aid is generally given in the form of facilities which enable easier access to education.<ref name="ngoboards"/> Rambhadracharya also runs a hundred-bed hospital in Gujarat.<ref name="aicb-p68"/>

===Critical edition of Ramcharitmanas===
{{main|Tulsi Peeth edition of the Ramcharitmanas}}
[[File:JagadguruRamabhadracharya008.jpg|thumb|alt=Rambhadracharya presenting the critical edition of Ramcharitmanas to Pratibha Patil|Rambhadracharya (right) presenting the critical edition of Ramcharitmanas edited by him to the president of India, [[Pratibha Patil]] (left)]]
The Ramcharitmanas was composed by Tulsidas in the late sixteenth century. It has been extremely popular in northern India over the last four hundred years, and is often referred to as the "Bible of northern India" by Western Indologists.<ref name="Ramcharitmanas"/> Rambhadracharya produced a critical edition of the Ramcharitmanas,<ref name="toi-fia"/> which was published as the Tulsi Peeth edition. Apart from the original text, for which Rambhadracharya has relied extensively on older manuscripts,<ref name="toi-fia"/> there were differences in spelling, grammar, and prosodic conventions between the Tulsi Peeth edition and contemporary editions of the Ramcharitmanas.<ref name="rcmtp-prologue">Rambhadracharya (ed) 2006, pp. 1–27.</ref><ref name="wbd-tpe"/>

In November&nbsp;2009, Rambhadracharya was accused of tampering with the epic,<ref name="toi-fia"/><ref name="dispute-deepens"/> but the dispute died down after Rambhadracharya expressed his regret for any annoyance or pain caused by the publication.<ref name="saints-calm-down"/> A writ petition was also filed against him but it was dismissed.<ref name="rcm-judgement"/> This edition was published in 2005 by Shri Tulsi Peeth Seva Nyas.<ref name="dinkarbiblio"/><ref name="nagar89"/>

==Works==
[[File:JagadguruRamabhadracharya006.jpg|thumb|right|alt=Release of ?r?bh?rgavar?ghaviyam by Atal Bihari Vajpayee|''?r?bh?rgavar?ghaviyam'' being released by Atal Behari Vajpayee (centre) in 2002. Rambhadracharya is to the left.]]
{{Main|Works of Jagadguru Rambhadracharya}}
Rambhadracharya has authored more than 90 books and 50 papers, including published books and unpublished manuscripts.<ref name="tripathi94"/><ref name="rcm-judgement"/> Various audio and video recordings have also been released. His major literary and musical compositions are listed below.<ref name="dinkarbiblio"/><ref name="nagar89">Nagar 2002, pp. 89–90.</ref>

===Poetry and plays===
* (1980) ''K?k? Vidura'' (???? ?????)&nbsp;– Hindi minor poem.
* (1980) ''Mukundasmara?am'' (??????????????)&nbsp;– Sanskrit minor poem.
* (1982) ''M?? ?abar?'' (??? ????)&nbsp;– Hindi minor poem.
* (1987) ''?r?j?nak?k?p?ka??k?astotram'' (????????????????????????????)&nbsp;– Sanskrit hymn of praise.
* (1991) ''R?ghavag?taguñjana'' (?????????????)&nbsp;– Hindi lyrical poem.
* (1992) ''?r?r?mavallabh?stotram'' (??????????????????????)&nbsp;– Sanskrit hymn of praise.
* (1993) ''Bhaktig?tasudh?'' (????????????)&nbsp;– Hindi lyrical poem.
* (1994) ''[[Arundhati (epic)|Arundhat?]]'' (????????)&nbsp;– Hindi epic poem.
* (1994) ''?r?ga?g?mahimnastotram'' (????????????????????????)&nbsp;– Sanskrit hymn of praise.
* (1995) ''?r?citrak??avih?rya??akam'' (?????????????????????????)&nbsp;– Sanskrit hymn of praise.
* (1996) ''?j?dacandra?ekharacaritam'' (????????????????????)&nbsp;– Sanskrit minor poem.
* (1996) ''?r?r?ghav?bhyudayam'' (?????????????????)&nbsp;– Single-act Sanskrit play-poem.
* (1997) ''A???dhy?yy?? Pratis?tra? ??bdabodhasam?k?a?am'' (?????????????? ??????????? ?????????????????)&nbsp;– Sanskrit commentary in verse on the Sutras of the Ashtadhyayi.
* (1997) ''?r?r?mabhaktisarvasvam'' (?????????????????????)&nbsp;– Sanskrit poem of one hundred verses.
* (2000) ''Saray?lahar?'' (????????)&nbsp;– Sanskrit minor poem.
* (2001) ''Laghuraghuvaram'' (??????????)&nbsp;– Sanskrit minor poem.
* (2002) ''[[?r?bh?rgavar?ghav?yam]]'' (??????????????????) &nbsp;– Sanskrit epic poem. The poet was awarded the 2004 [[Sahitya Akademi Award for Sanskrit]] for the epic.<ref name="sa2005"/><ref name="dna205"/>
* (2002) ''?r?r?ghavabh?vadar?anam'' (??????????????????)&nbsp;– Sanskrit minor poem.
* (2003) ''Kubj?patram'' (????????????)&nbsp;– Sanskrit letter poem.
* (2004) ''[[Bhrngadutam|Bh??gad?tam]]'' (??????????)&nbsp;– Sanskrit minor poem of the ''D?tak?vya'' (messenger-poem) category.
* (2008) ''[[Srisitaramakelikaumudi|?r?s?t?r?makelikaumud?]]'' (?????????????????????)&nbsp;– Hindi [[Hindi literature#Ritikavya Kaal (???????)(1700 to 1900)|R?tik?vya]] (procedural-era Hindi poem).<ref name="elucidating-moonlight"/>
* (2009) ''[[Srisitaramasuprabhatam|?r?s?t?r?masuprabh?tam]]'' (?????????????????????)&nbsp;– A Sanskrit [[suprabhatam]].<ref name="beautiful-dawn"/>
* (2010) ''[[Ashtavakra (epic)|A???vakra]]'' (?????????)&nbsp;– Hindi epic poem.<ref name="Ashtavakra"/><ref name="Bhaskar-2010"/>
* (2011) ''[[Gitaramayanam|G?tar?m?ya?am]]'' (???????????)&nbsp;– Sanskrit lyrical epic poem.<ref name="stps-gr">Sushil & Mishra 2011, p. 14</ref>
* (2011) ''Avadha Kai Ajoriy?'' (??? ?? ???????)&nbsp;– Awadhi lyrical poem.<ref name="Awadha"/>
* (2011) ''?r?s?t?sudh?nidhi?'' (?????????????????)&nbsp;– Sanskrit minor poem of the ''Stotraprabandhak?vya'' category.<ref name="ocean-of-nectar"/>

===Prose===
[[File:Ramabhadracharya Works - Collage.jpg|thumb|right|alt=Covers of some books of Rambhadracharya|Covers of some books edited or authored by Rambhadracharya.]]

====Sanskrit commentaries on Prasthanatrayi====
{{main|Shriraghavkripabhashyam}}
Rambhadracharya composed Sanskrit commentaries titled ''?r?r?ghavak?p?bh??yam'' on the [[Prasthanatrayi]] (the Brahma Sutra, the Bhagavad Gita, and eleven Upanishads). These commentaries were released on 10 April 1998 by [[Atal Behari Vajpayee]], then Prime Minister of India.<ref name="dinkarbiblio"/><ref>Nagar 2002, p. 88.</ref> Rambhadracharya composed ''?r?r?ghavak?p?bh??yam'' on [[Narada Bhakti Sutra]] in 1991. He thus revived—after five hundred years—the tradition of Sanskrit commentaries on the Prasthanatrayi. He also gave the Ramananda Sampradaya its second commentary on Prasthanatrayi in Sanskrit, the first being the ''?nandabh??yam'', composed by Ramananda himself.<ref name="subedi"/><ref name="dwivedi">Dwivedi 2007, pp. 315–317.</ref> Rambhadracharya's commentary in Sanskrit on the Prasthanatrayi was the first written in almost 500 years.<ref name="subedi"/>

====Other prose works====
[[File:Jagadguru Rambhadracharya at Baroda.JPG|thumb|220px|alt=Rambhadracharya delivering a discourse|Rambhadracharya delivering a discourse. He has delivered many discourses, some of which have been published as books.]]
* (1980) ''Bharata Mahim?'' (??? ?????)&nbsp;– Hindi discourse.
* (1981) ''Adhy?tmar?m?ya?e Ap??in?yaprayog?n?? Vimar?a?'' (??????????????? ?????????????????? ???????)&nbsp;– Sanskrit dissertation (PhD thesis).
* (1982) ''M?nasa Me? T?pasa Prasa?ga'' (???? ??? ???? ??????)&nbsp;– Hindi deliberation.
* (1983) ''Mahav?r?'' (???????)&nbsp;– Hindi commentary on Hanuman Chalisa.
* (1985) ''Sugr?va K? Agha Aura Vibh??a?a K? Karat?ti'' (??????? ?? ?? ?? ?????? ?? ??????)&nbsp;– Hindi discourse.
* (1985) ''?r?g?t?t?tparya'' (????????????????)&nbsp;– Hindi commentary on the Bhagavad Gita.
* (1988) ''San?tanadharma K? Vigrahasvar?pa Gom?t?'' (????????? ?? ???????????? ??????)&nbsp;– Hindi deliberation.
* (1988) ''?r?tulas?s?hitya me? K???a Kath?'' (???????????????? ??? ????????)&nbsp;– Hindi investigative research.
* (1989) ''M?nasa me? Sumitr?'' (???? ??? ????????)&nbsp;– Hindi discourse.
* (1990) ''S?ta Nirv?sana Nah??'' (???? ???????? ????)&nbsp;– Hindi critique.
* (1991) ''?r?n?radabhaktis?tre?u ?r?r?ghavak?p?bh??yam'' (????????????????????? ???????????????????)&nbsp;– Sanskrit commentary on the [[Narada Bhakti Sutra]].
* (1992) ''Prabhu Kari K?p? P??var? D?nh?'' (????? ??? ???? ?????? ??????)&nbsp;– Hindi discourse.
* (1993) ''Parama Ba?abh?g? Ja??yu'' (??? ??????? ?????)&nbsp;– Hindi discourse.
* (2001) ''?r?r?mastavar?jastotre ?r?r?ghavak?p?bh??yam'' (?????????????????????? ???????????????????)&nbsp;– Sanskrit commentary on the ''R?mastavar?jastotra''.
* (2001) ''?r? S?t?r?ma Viv?ha Dar?ana'' (???? ??????? ????? ?????)&nbsp;– Hindi discourse.
* (2004) ''Tuma P?vaka Ma?ha Karahu Niv?s?'' (??? ???? ??? ???? ??????)&nbsp;– Hindi discourse.
* (2005) ''Bh?v?rthabodhin?'' (?????????????)&nbsp;– Hindi commentary on the Ramcharitmanas.
* (2007) ''?r?r?sapañc?dhy?y?vimar?a?'' (?????????????????????????)&nbsp;– Hindi deliberation on ''R?sapañc?dhy?y?''.
* (2006) ''Ahalyoddh?ra'' (???????????)&nbsp;– Hindi discourse .
* (2008) ''Hara Te Bhe Hanum?na'' (?? ?? ?? ??????)&nbsp;– Hindi discourse.

===Audio and video===
* (2001) ''Bhajana Saray?'' (??? ????)&nbsp;– Audio CD with eight [[bhajan]]s (devotional hymns) in Hindi devoted to Rama. Composed, set to music, and sung by Rambhadracharya. Released by Yuki Cassettes, Delhi.<ref>{{cite video | people=Rambhadracharya, Swami (Lyricist, Musician and Singer) | date=2001 | id=YCD-119 |title=Bhajana Saray? | medium=CD |language=Hindi |trans_title=The river Sarayu of devotion | publisher=Yuki Cassettes |location=Delhi, India}}</ref>
* (2001) ''Bhajana Yamun?'' (??? ?????)&nbsp;– Audio CD with seven bhajans in Hindi devoted to Krishna. Composed, set to music, and sung by Rambhadracharya. Released by Yuki Cassettes, Delhi.<ref>{{cite video | people=Rambhadracharya, Swami (Lyricist, Musician and Singer) | date=2001 | id=YCD-120 |title=Bhajana Yamun? | medium=CD |language=Hindi |trans_title=The river Yamuna of devotion | publisher=Yuki Cassettes |location=Delhi, India}}</ref>
* (2009) ''?r? Hanumat Bhakti'' (???? ?????? ?????)&nbsp;– Audio CD with six bhajans in Hindi devoted to Hanuman, and composed by Tulsidas. Set to music and sung by Rambhadracharya. Released by Kuber Music, New Delhi.<ref>{{cite video | people=Rambhadracharya, Swami  (Musician and Singer) | date=2009 | id=KMCN-13 |title=?r? Hanumat Bhakti | medium=CD |language=Hindi |trans_title=Devotion to Hanuman | publisher=Kuber Music |location=New Delhi}}</ref>
* (2009) ''?r?s?t?r?masuprabh?tam'' (?????????????????????)&nbsp;– Audio CD of ''?r?s?t?r?masuprabh?tam'', a Sanskrit Suprabhata poem. Composed, set to music, and sung in the ''Vairagi'' [[Raga]] by Rambhadracharya. Released by Yuki Cassettes, Delhi.<ref>{{cite video | people=Rambhadracharya, Swami  (Lyricist, Musician and Singer) |date=2009 | id=YCD-155 |title=?r?s?t?r?masuprabh?tam | medium=CD |language=Sanskrit |trans_title=The beautiful dawn of Sita and Rama | publisher=Yuki Cassettes |location=Delhi, India}}</ref>
* (2009) ''Sundara K???a'' (?????? ?????)&nbsp;– DVD with a musical rendition of and commentary on the Sundar Kand of Ramcharitmanas. Spoken, set to music, and sung by Rambhadracharya. Released by Yuki Cassettes, Delhi.<ref>{{cite video | people=Rambhadracharya, Swami (Speaker, Musician and Singer) | date=2009 | id=DVD-2020 | title=Sundara K???a | medium=DVD |language=Hindi | trans_title=The Sundar Kand | publisher=Yuki Cassettes |location=Delhi, India}}</ref>

==Recognition, awards and honours==
===Recognition===
;Recognition in India
[[File:JRHU - Third Convocation.jpg|250px|thumb|alt=Rambhadracharya at third convocation of JRHU|Rambhadracharya (third from right) with Rajnath Singh (extreme right) at the third convocation of Jagadguru Rambhadracharya Handicapped University held on 14 January 2011]]
Rambhadracharya enjoys wide popularity in the Chitrakoot region.<ref name="toi-fia"/> [[Atal Behari Vajpayee]], the former prime minister of India, considered Rambhadracharya to be an "immensely learned person well versed in Vedic and Puranic literature besides the grammar", and commended his intelligence and memory.<ref name="nagar13" >Nagar 2002, p. 13.</ref> Dr. [[Murli Manohar Joshi]], a leader of the [[Bharatiya Janata Party]] who was present at the inauguration of ''Kanch Mandir'',<ref name="the-eye"/> said of Rambhadracharya that the "intense knowledge of the most revered is indeed adorable".<ref name="nagar15" >Nagar 2002, p. 15.</ref> [[Nanaji Deshmukh]], social activist and former leader of [[Bharatiya Jana Sangha]], called Rambhadracharya "an astonishing gem of the country".<ref name="nagar16" >Nagar 2002, p. 16.</ref> [[Swami Kalyandev]], a social activist and [[Padma Bhushan]] awardee, considered Rambhadracharya to be "an unprecedented intellectual and speaker, and an Acharya with great devotion".<ref name="nagar10" >Nagar 2002, p. 10.</ref> [[Somnath Chatterjee]], former [[Speaker of Lok Sabha]] and leader of the [[Communist Party of India (Marxist)]], called him a "celebrated Sanskrit scholar and educationist of great merit and achievement".<ref name="speakerloksabha"/> He is considered one of the greatest scholars on Tulsidas and Ramcharitmanas in India, and is cited as such.<ref name="prasad-on-jr"/><ref name="lpv"/><ref>Prasad 1999, p. 319.</ref><!-- Secondary reference does not say this.--><!--On the occasion of Sita Navmi in 2011, the birthday of [[Sita]], Bihar State Tourism Minister Sh. Sunil Kumar Pintoo promised Rambhadracharya to renovate and develop the Sita Kund in Sitamarhi. The Chief Minister of Bihar, [[Nitish Kumar]], praised the efforts of Rambhadracharya and decided to declare Sita Navmi as a state holiday from 2012.<ref name="sitanavmi"/><ref>{{cite news | title=Sita Navmi celebrated at Punaura Dham, Bihar on May/12, 2011 | date = 12 May 2011 | url = http://jagadgururambhadracharya.org/tulsipeethnews | accessdate =22 August 2011}}</ref>--> Rambhadracharya has been associated with [[Rajnath Singh]], a leader of the Bharatiya Janata Party, who, as the Chief Minister of Uttar Pradesh, was one of the first promoters of the Jagadguru Rambhadracharya Handicapped University.<ref name="rashtriya-sahara"/> He was presented with an honorary D Litt by Rambhadracharya on the third convocation of the university in 2011.<ref name="oneindia"/> Former Uttar Pradesh chief minister [[Ram Prakash Gupta]] and former speaker of the Uttar Pradesh legislative assembly [[Keshari Nath Tripathi]] said that he will continue to enrich society with his contributions.<ref name="tnn"/> [[Ramdev|Swami Ramdev]] considers Rambhadracharya to be the most learned person in the world at present.<ref>{{cite news | title=?????? ??? ??? ???????? ?? ?????? ????? | trans_title=No place holier than Chitrakoot in the World | date = 22 November 2011 | language=Hindi | last=Agrawal | first=Sachin | publisher=Shubh Bharat | url = http://shubhbharat.com/index.php?option=com_content&view=article&id=10829:2011-11-22-13-14-15&catid=78:2011-07-08-11-44-00&Itemid=107 | accessdate =14 December 2011 | quote={{lang|hi|???????? ??? ?? ?????? ??? ??? ???????? ????????????? ?? ???? ??????? ???? ?????? ??? ??? ???? ??? ????? ????? ? ???? ??? ?? ?? ????? ?????? ?? ???? ?????? ?? ??? ??? ????}} [He said that in the current age there is none who is more learned than Jagadguru Rambhadracharya in the world. Despite being bereft of physical vision, he sees the whole world from his divine eyes.]}} {{Dead link|date=April 2012|bot=H3llBot}}</ref> Film actor and producer [[Ramesh Wadkar]] has said that he will make a documentary film on Rambhadracharya.<ref>{{cite news | title=?????? ??? ??? ???????? ?? ?????? ????? | trans_title=Anna's movement [is an] insult to the martyrs| date = 4 December 2011 | language=Hindi | publisher=Amar Ujala | url = http://www.amarujala.com/city/Chitrakoot/Chitrakoot-52210-42.html | accessdate =9 June 2012}}</ref> He was a member of a delegation of saints and ''dharmacharyas'' associated with the Ram Janmabhoomi temple Empowered Committee. This delegation meet the then president [[A.P.J. Abdul Kalam]] and the then union Home Minister [[Shivraj Patil]] on July 19, 2005, handed over a memorandum to them, and urged to strengthen the security arrangements for important religious places in the country.<ref>{{cite web|url=http://panchjanya.com/arch/2005/7/31/File35.htm |title=??????? ?????? ?? ??????? ????????? ????|publisher=[[Panchjanya]]|date=July 31, 2005|trans_title=Ensure the protection of religious sites|language=Hindi}}</ref>

;International recognition

In 1992 Rambhadracharya led the Indian delegation at the Ninth World Conference on Ramayana, held in Indonesia.<ref name="aicb-p68"/><ref>Nagar 2002, pp. 87–88.</ref> He has travelled to several countries, including England, Mauritius, Singapore, and the United States to deliver discourses on Hindu religion and peace.<ref name="aicb-p68"/><ref name="wps"/> He has been profiled in the ''International Who's Who of Intellectuals''.<ref>{{cite book | title = International Who's Who of Intellectuals | edition=13th | publisher=International Biographical Centre | location=Cambridge, England | year=1999 | page=621}}</ref>

;Address at Millennium World Peace Summit

Rambhadracharya was one of the spiritual and religious Gurus from India at the Millennium World Peace Summit, organised by the United Nations in New York City from 28 to 31 August 2000. While addressing the gathering, he gave Sanskrit definitions for the words ''[[Bharata (term)|Bharata]]'' (the ancient name of India) and ''Hindu'', and touched upon the [[Nirguna Brahman|Nirguna]] and [[Saguna Brahman|Saguna]] aspects of God. In his speech on Peace, he called for developed and developing nations to come together to strive for the eradication of poverty, the fight against terrorism, and nuclear disarmament. At the end of his speech, he recited the [[Shanti Mantra]].<ref name="wps"/><ref name="wcrl"/>

===Awards and honours===
[[File:JagadguruRamabhadracharya007.jpg|thumb|alt=Rambhadracharya being presented the Vani Alankarana Puraskara|Rambhadracharya (left) being presented the Vani Alankarana Puraskara by [[Somnath Chatterjee]] (right) in 2006]]
Rambhadracharya has been honoured by several leaders and politicians, including [[A. P. J. Abdul Kalam]], Somnath Chatterjee, [[Shilendra Kumar Singh]], and Indira Gandhi.<ref name="speakerloksabha"/><ref name="the-hindu"/> Several state governments, including that of Uttar Pradesh, Madhya Pradesh, and [[Himachal Pradesh]] have conferred honours on him.

;Before Vairagi initiation

* 1974. Five gold medals at the ''Akhila Bharatiya Sanskrit Adhiveshan'' (All India Sanskrit Conference), New Delhi. Presented by Indira Gandhi, then Prime Minister of India.<ref name="kbs-bio"/><ref name="parauha"/>
* 1974. Gold Medal, ''Shastri'' (Bachelor of Arts)<ref name="gupta-kumar-ssu"/> examination, awarded by the Sampurnanand Sanskrit University, Varanasi.<ref name="dinkaredu"/>
* 1976. Gold medal for standing first in all-India Sanskrit debate competition. Presented by [[Marri Chenna Reddy|M. Channa Reddy]], then [[Governor of Uttar Pradesh]].<ref>Nagar 2002, p. 78.</ref>
* 1976. Cancellor's Gold Medal, awarded by the Sampurnanand Sanskrit University, Varanasi.<ref name="parauha"/>
* 1976. Seven gold medals, ''Acharya'' (Master of Arts)<ref name="gupta-kumar-ssu"/> examination, awarded by the Sampurnanand Sanskrit University, Varanasi.<ref name="parauha"/><ref name="dinkaredu"/>

;After Vairagi initiation

* 1998. ''Dharmachakravarti'', awarded by the World Religious Parliament, New Delhi, in recognition of meritorious contribution to world development.<ref name="kbs-awards">Chandra 2008, p. 21.</ref><ref>Nagar 2002, p. 182.</ref>
* 1999. ''Kaviraj Vidya Narayan Shastri Archana-Samman Award'', awarded by the Kaviraj Vidya Narayan Shastri Archana-Samman Committee, Bhagalpur, Bihar, for contributions to the Sanskrit language.<ref>Nagar 2002, p. 184.</ref>
* 1999. ''Mahakavi'', awarded by the Akhil Bharatiya Hindi Bhasha Sammelan, Bhagalpur, Bihar, for invaluable contributions to the popularisation and enrichment of Hindi language, literature, and culture.<ref>Nagar 2002, p. 183.</ref>
* 2000. ''Vishishta Puraskar'', awarded by the Uttar Pradesh Sanskrit Samsthana, Lucknow.
* 2000. ''Mahamahopadhyay'', awarded by the [[Lal Bahadur Shastri]] Sanskrit Vidyapeeth, New Delhi.<ref name="Vidyapeetha-4"/>
* 2002. ''Kavikularatna'', awarded by Sampurnanand Sanskrit University, Varanasi.<ref name="kbs-awards"/>
* 2003. ''Rajshekhar Samman'', awarded by the Madhya Pradesh Sanskrit Academy, Bhopal, for the ''?r?r?ghavak?p?bh??yam'' commentary on the Prasthanatrayi.<ref name="kbs-awards"/><ref>Sharma et al 2011, p. 840.</ref>
* 2003. ''Bhaurao Deoras Award'', awarded by the Bhaurao Deoras Seva Nyas, Lucknow.<ref name="tripathi94"/><ref name="tnn"/><ref name="Bhaurao"/>
* 2003. ''Diwaliben Award'' for Progress in Religion, awarded by the Dewaliben Mehta Charitable Trust, Mumbai. Presented by [[Prafullachandra Natwarlal Bhagwati|P. N. Bhagwati]], former Chief Justice of India.
* 2003. ''Ativishishta Puraskar'', by the Uttar Pradesh Sanskrit Samsthana, Lucknow.<ref name="kbs-awards"/>
* 2004. ''Awadh Ratna'', by the Awadh Vikas Parishad, Allahabad.<ref>Sharma et al 2011, p. 837.</ref>
* 2004.  President's Certificate of Honour or ''Badarayana Puraskar''. Presented by A. P. J. Abdul Kalam, then President of India.<ref name="tripathi94"/><ref name="kbs-awards"/>
* 2005. Sahitya Akademi Award in Sanskrit for the epic ''?r?bh?rgavar?ghav?yam''.<ref name="tripathi94"/><ref name="sa2005"/>
* 2006. ''Sanskrit Mahamahopadhyay'', awarded by the Hindi Sahitya Sammelan, Prayag.<ref name="tripathi94"/>
* 2006. ''Shreevani Alankaran'', awarded by the Jaydayal Dalmiya Shri Vani Trust for the epic ''?r?bh?rgavar?ghav?yam''. Presented by [[Somnath Chatterjee]], then [[Speaker of the Lok Sabha]].<ref name="tripathi94"/><ref name="speakerloksabha"/>
* 2006. ''[[B??abha??a|Banabhatta]] Award'', awarded by Madhya Pradesh Sanskrit Board, [[Bhopal]], for the epic ''?r?bh?rgavar?ghav?yam''.<ref name="kkbvp"/>
* 2007. ''Goswami Tulsidas Samarchan Samman'', awarded by the Tulsi Research Institute, Municipal Corporation, [[Allahabad]]. Presented by [[Ramesh Chandra Lahoti]], former [[Chief Justice of India]].
* 2007. ''Vachaspati Award'', awarded by the [[K. K. Birla Foundation]], New Delhi, for the epic ''?r?bh?rgavar?ghav?yam''. Presented by [[Shilendra Kumar Singh|S. K. Singh]], then Governor of [[Rajasthan]].<ref name="the-hindu"/><ref name="Birla"/>
* 2011. ''Tulsi Award 2011'', awarded by [[Morari Bapu]] on the eve of Tulsi Jayanti, anniversary of the birth of Tulsidas.<ref>Mishra 2011, p. 24.</ref><ref>{{cite news | last=Durg | first=City Reporter | location=Bhilai, Chattisgarh, India | title=???????? ?????? ?? ????? ??? ?? ?? | trans_title=Bhagavata Katha by visually impaired preceptor starts today | date = 30 October 2011 | url = http://www8.bhaskar.com/article/CHH-OTH-1599867-2529829.html | language=Hindi | accessdate =29 November 2011 | publisher=Dainik Bhaskar}}</ref>
* 2011. ''Dev Bhumi Award'', awarded by the Government of [[Himachal Pradesh]], [[Shimla]]. Presented by Joseph Kurien, then Chief Justice of Himachal Pradesh High Court.<ref name="prlog"/>

==Timeline==
{{main|Timeline of Rambhadracharya}}
{{Timeline of Jagadguru Rambhadracharya}}

==See also==
{{Wikipedia books|Jagadguru Rambhadracharya}}
*[[List of Hindu gurus and saints]]&nbsp;– List of other noteworthy gurus and saints of Hinduism.
*[[List of Sahitya Akademi Award winners for Sanskrit]]&nbsp;– List of Sanskrit language writers who have won the Sahitya Akademi Award.
{{Portal bar|Jagadguru Rambhadracharya|Biography|Disability|Education|Hinduism|India|Indian Education|Literature|Music|Philosophy|Poetry|Uttar Pradesh}}

==Notes==
{{IPA notice|section}}
{{Reflist|group=lower-greek|refs=
<ref name="IPA1">{{need-IPA}} {{lang-sa|??????????????????????????????????????????|pron=Jagadguru Rambhadracharya - Sanskrit Pronunciation.ogg}}, {{IPA-sa|????d??uru-ra?ma?n?nd?a?ca?rj?-s?a?mi-r??m?b??d?r??c??rj??|-|Jagadguru Rambhadracharya - Sanskrit Pronunciation.ogg}}; {{lang-hi|???????? ?????????????? ?????? ?????????????}}, {{IPA-hns|????d??uru ra?ma?n?nd?a?ca?rj? s?a?mi? r??mb??d?r??c??rj?|hi|Jagadguru Rambhadracharya - Hindi Pronunciation.ogg}}; [[IAST]]: Jagadguru R?m?nand?c?rya Sv?m? R?mabhadr?c?rya.
</ref>
<ref name="IPA2">{{need-IPA}} {{lang-sa|????????????}}, {{IPA-sa|?irid???r?-mi?r??|-|Giridhar Mishra - Sanskrit Pronunciation.ogg}}; {{lang-hi|?????? ?????}}, {{IPA-hns|?irid???r mi?r?|hi|Giridhar Mishra - Hindi Pronunciation.ogg}}; IAST: Giridhara Mi?ra.
</ref>
}}

==References==
{{reflist|2|colwidth=35em|refs=
<ref name="speakerloksabha">{{cite web |date=18 January 2007 |url=http://speakerloksabha.nic.in/Speech/SpeechDetails.asp?SpeechId=195 |title=Address at the Presentation of the 'Twelfth and Thirteenth Ramkrishna Jaidayal Dalmia Shreevani Alankaran, 2005 & 2006', New Delhi, 18 January 2007. |work=Speeches |publisher=The Office of Speaker Lok Sabha |accessdate=8 March 2011 |quote=Swami Rambhadracharya ... is a celebrated Sanskrit scholar and educationist of great merit and achievement. ... His academic accomplishments are many and several prestigious Universities have conferred their honorary degrees on him. A polyglot, he has composed poems in many Indian languages. He has also authored about 75 books on diverse themes having a bearing on our culture, heritage, traditions and philosophy which have received appreciation. A builder of several institutions, he started the Vikalanga Vishwavidyalaya at Chitrakoot, of which he is the lifelong Chancellor.}}</ref>

<ref name="kbs-bio">{{cite journal |last=Chandra |first=R. |month=September |year=2008 |title=???? ?????? |trans_title=Life Journey |journal=Kranti Bharat Samachar |language=Hindi |publisher=Rajesh Chandra Pandey |location=Lucknow, Uttar Pradesh |volume=8 |issue=11 |pages=22–23 |id=RNI No. 2000, UPHIN 2638}}</ref>

<ref name="kkbvp">{{cite web |url=http://www.kkbirlafoundation.com/downloads/pdf/vach-2007.pdf |title=???????? ???????? ???? |trans_title=Vachaspati Award 2007 |language=Hindi |publisher=K. K. Birla Foundation |accessdate=8 March 2011|archiveurl= http://web.archive.org/web/20110713154542/http://www.kkbirlafoundation.com/downloads/pdf/vach-2007.pdf|archivedate= 13 July 2011|deadurl= yes}}</ref>

<ref name="rcm-judgement">{{Cite journal |last1=Kant | first1=Pradeep |last2=Kumar |first2=Anil |date=19 May 2011 |title=Writ Petition No. 8023 (MB) of 2008: Shiv Asrey Asthana and others Vs Union of India and others |url=http://elegalix.allahabadhighcourt.in/elegalix/WebShowJudgment.do?judgmentID=1423192 |publisher=Allahabad High Court (Lucknow Bench) |accessdate=29 September 2011}}</ref>

<ref name="outlook">{{cite journal |first=Sutapa |last=Mukherjee |date=10 May 1999 |url=http://www.outlookindia.com/article.aspx?207437 |title=A Blind Sage's Vision: A Varsity For The Disabled At Chitrakoot |journal=Outlook |location=New Delhi |volume=5 |issue=<!--Between 12 and 24--> |accessdate=21 June 2011}}</ref>

<ref name="prasad-on-jr">Prasad 1999, p. xiv: "Acharya Giridhar Mishra is responsible for many of my interpretations of the epic. The meticulousness of his profound scholarship and his extraordinary dedication to all aspects of Rama's story have led to his recognition as  one of the greatest authorities on Tulasidasa in India today ... that the Acharya's knowledge of the Ramacharitamanasa is vast and breathtaking and that he is one of those rare scholars who know the text of the epic virtually by heart."</ref>

<ref name="tripathi94">{{cite book |editor-first=Radha Vallabh |editor-last=Tripathi | year=2012 |page=94 |title=??????????????????????? – Inventory of Sanskrit Scholars |location=New Delhi, India | publisher=Rashtriya Sanskrit Sansthan |isbn=978-93-8611-185-2 | url=http://www.sanskrit.nic.in/DigitalBook/I/Inventory%20of%20Sanskrit%20Scholars.pdf | accessdate=April 16, 2012}}</ref>

<ref name="lpv">{{cite book |editor-first=Lallan Prasad |editor-last=Vyas |year=1996 |page=62 |title=The Ramayana: Global View |location=Delhi, India |publisher=Har Anand Publications |isbn=978-81-241-0244-2 |quote= ... Acharya Giridhar Mishra, a blind Tulasi scholar of uncanny critical insight, ... }}</ref>

<ref name="programmes">Television channels:
* {{cite news |last=NBT News |first=Ghaziabad |date=21 January 2011 |url=http://navbharattimes.indiatimes.com/articleshow/7329118.cms |title=?? ?? ????? ??? ??????? ??? : ????????????? |trans_title=Perform devotion with the mind, and you will find Ram: Rambhadracharya |work=Navbharat Times |accessdate=24 June 2011 |language=Hindi}}<!--
-->
* {{cite news | last=Correspondent | first=Una |date=13 February 2011 |url=http://dainiktribuneonline.com/2011/02/%E0%A4%95%E0%A5%87%E0%A4%B5%E0%A4%B2-%E0%A4%97%E0%A5%81%E0%A4%B0%E0%A5%81-%E0%A4%AD%E0%A4%B5%E0%A4%B8%E0%A4%BE%E0%A4%97%E0%A4%B0-%E0%A4%95%E0%A5%87-%E0%A4%AA%E0%A4%BE%E0%A4%B0-%E0%A4%AA%E0%A4%B9/ |title=???? ???? ?????? ?? ??? ?????? ???? ?? : ???? ??? ?? ?????? |trans_title=Only the Guru can take across the ocean of the world: Baba Bal Ji Maharaj |language=Hindi |work=Dainik Tribune |accessdate=24 June 2011}}<!--
-->
* {{cite news | last=Correspondent | first=Rishikesh | work=Jagran Yahoo |date=7 June 2011 |url=http://in.jagran.yahoo.com/news/local/uttranchal/4_5_7835924_1.html |title=??:? ?? ??????? ??? ????? ? ???? |trans_title=Do not lose patience in sorrow and adversity | language=Hindi |accessdate=24 June 2011 | quote={{lang|hi|???????? ??? ??????? ?????? ????????????? ?????? ?? ??? ?? ...}} [Famous Ramkatha artist Swami Rambhadracharya said that ... ]}}<!--
-->
* {{cite news |date=26 June 2011 |url=http://anjoria.com/?p=4041 |title=???????? ??? ??????? ?? ??? ????? ????????? |trans_title=Enrapturing Bhojpuri programme in Singapore |language=Bhojpuri |work=Anjoria | accessdate=30 June 2011 |quote={{lang|bho|???? ??????? ?????? ?????? ??? ?????????? ???? ??????? ??????? ????????????? ?? ????? ?? ??????? ???? ???????? ????}} [In the Shri Lakshminarayan temple, the renowned and insightful expert of Ramcharitmanas Jagadguru Rambhadracharya honoured Rakesh with a certificate]}}<!--
-->
* {{cite web |title=Rambhadracharya Ji |url=http://www.sanatantv.com/rambhadracharya.php |work=Sanatan TV |accessdate=10 May 2011}}</ref>

<ref name="sitanavmi">{{cite news | last=Correspondent | first=Sitamarhi |date=5 May 2011 |url=http://in.jagran.yahoo.com/news/local/bihar/4_4_7679575.html |title=????? ????? ?? ?????? ?? ???? ???? ?????? ????????????? |trans_title=Rambhadracharya arrives to expound on Ramkatha with the eyes of his knowledge |language=Hindi |work=Jagran Yahoo |accessdate=24 June 2011}}</ref>

<!-- from Mastering Gita and Ramcharitmanas -->
<ref name="parauha">{{cite book |last=Parauha |first=Tulsidas |editor-last=Rambhadracharya |editor-first=Svami |pages=5–9 |date=14 January 2011 |title=??????????? (????????????? ????????????????????) |trans_title=G?tar?m?ya?am (The G?tas?t?bhir?mam Sanskrit lyrical epic poem) |chapter=????????????????????????????????????? ??????????? ?????????? |trans_chapter=The life and works of the great poet Jagadguru Rambhadracharya |language=Sanskrit |publisher=Jagadguru Rambhadracharya Handicapped University}}</ref>

<ref name="Zee-2007">{{cite news |date=3 December 2007 |url=http://zeenews.india.com/news/lifestyle/bhagwad-gita-in-braille-language_411003.html |title=Bhagavad Gita in Braille Language |work=Zee News |accessdate=24 April 2011}}</ref>

<ref name="ANN-2007">{{cite news |title=?? ????? ???? ??? ????????? |trans_title=Now, Bhagavad Gita in Braille script |language=Hindi |url=http://hindi.webdunia.com/news/news/regional/0712/06/1071206064_1.htm |work=Webdunia Hindi |agency=Asian News International |date=6 December 2007 |accessdate=2 July 2011}}</ref>

<!-- from Virakta Diksha -->
<ref name="Dubley-2011">{{cite news |last=Dubey |first=Hariprasad |date=13 April 2011 |url=http://in.jagran.yahoo.com/news/features/general/8_14_5390035.html |title=?????? ?????: ? ????? ???? ???????? |trans_title=Sacred Places: Stay in Chitrakoot for 6 months |accessdate=3 July 2011 |language=Hindi |work=Jagran Yahoo |quote={{lang|hi|???????? ?? ???? ?? ?? ??? ??? ??????? ?? ??? ?? ???????? ?? ?????? ???? ?? ?? ???? ?? ???? ??? ??? ???? ???? ??, ?? ??? ??? ??? ?? ????????? ??? ???? ????}} [Tulasidasa has admitted that if one stays on the banks of Payasvini river for six months, chanting the name of Rama and subsisting only on fruits, they obtain all types of powers or accomplishments.]}}</ref>

<ref name="virtues">{{cite news | last=Correspondent | first=Chitrakoot |date=5 January 2007 |url=http://in.jagran.yahoo.com/news/local/uttarpradesh/4_1_7135505.html |title=?????? ?????? ?????? ?? ??????? |trans_title=The education of India teaches virtues |language=Hindi |work=Jagran Yahoo |accessdate=2 July 2011}}</ref>

<ref name="virtues">{{cite news | last=Correspondent | first=Chitrakuta |date=25 July 2010 |url=http://in.jagran.yahoo.com/news/local/uttarpradesh/4_1_6598239.html |title=????? ??? ?????? ??? ???? ????? ?? ???? |trans_title=The sounds of prayers to Guru resonate in the pilgrimage |work=Jagran Yahoo |accessdate=2 July 2011 |language=Hindi}}</ref>

<ref name="research-institute">{{cite news | last=Correspondent | first=Chitrakuta |date=5 January 2011 |url=http://www.amarujala.com/city/Chitakut/Chitakut-16337-42.html |title=???? ??? ?????????????? ???? ?? ??? ??????? ????? |trans_title=International-level research institute to come up in the district |work=Amar Ujala |accessdate=2 July 2011 |language=Hindi}}</ref>

<ref name="the-eye">{{cite news | last=Correspondent | first=Chitrakut  |date=5 January 2011 |url=http://in.jagran.yahoo.com/news/local/uttarpradesh/4_1_7135652.html |title=???????????? ?? ??? ?? ?? ??? ?? |trans_title=Buaji became the eye of the visually impaired |language=Hindi |work=Jagran Yahoo |accessdate=24 June 2011}}</ref>

<!-- from Post of Jagadguru Ramanandacharya -->
<ref name="Sharma-2003">{{cite news |last=Sharma |first=Amit |date=1 May 2003 |url=http://www.indianexpress.com/oldStory/23063/ |title=No winners in VHP's Ayodhya blame game |work=The Indian Express  |location=India |accessdate=24 April 2011}}</ref>

<ref name="Mid-day-2003-07-17">{{cite news |date=17 July 2003 |url=http://www.mid-day.com/news/2003/jul/58790.htm |title=Babar destroyed Ram temple at Ayodhya |work=Mid-Day |accessdate=24 April 2011}}</ref>

<ref name="Mid-day-2003-07-21">
{{cite news |work=Mid-Day |date=21 July 2003 |url=http://www.mid-day.com/news/2003/jul/59146.htm |title=Ram Koop was constructed by Lord Ram |accessdate=24 April 2011}}</ref>

<ref name="in-Dakor">{{cite news |title=Gurudeva in Dakor, Gujarat |date=15 October 2009 |url=https://sites.google.com/site/jagadgururambhadracharya/news/headline2 |accessdate=22 August 2011}}</ref>

<!-- from Institutes for the disabled -->
<ref name="bhartiyapaksha">{{cite web |date=12 February 2010 |last=Shubhra |url=http://www.bhartiyapaksha.com/?p=9111 |title=???????? ????????????? ??????? ????????????? |trans_title=Jagadguru Rambhadracharya Handicapped University |language=Hindi |work=Bh?rat?ya Pak?a |accessdate=25 April 2011}}</ref>

<ref name="Subhash-2005">{{cite news |first=Tarun |last=Subhash |date=3 July 2005 |title=A Special University for Special Students: UP does a first&nbsp;– it establishes the country's first exclusive university for physically and mentally disabled students |url=http://www.disabilityindia.org/djinstjuly05C.cfm#up |work=Hindustan Times |location=India |accessdate=23 June 2011}}</ref>

<ref name="Dikshit-2007">{{cite news |last=Dikshit |first=Ragini |date=10 July 2007 |title=????????: ?????? ?? ????? ??????? ????????????? |trans_title=Chitrakuta: The world's first handicapped university |language=Hindi |work=Jansatta Express}}</ref>

<ref name="creation">Creation ordinance:
*{{cite web |author=Department of Information Technology and Electronics |title=????? ?? ?????? ??????? ????: ??????????? |trans_title=Right to Information Act 2005: Index |url=http://infotech.up.nic.in/hindi/suchana/suchana.htm |language=Hindi |publisher=Government of Uttar Pradesh |accessdate=25 June 2011}}<!--
-->
*{{cite book |last=Sinha |first=R. P. |date=1 December 2006 |page=104 |title=E-Governance in India: initiatives & issues |location=New Delhi |publisher=Concept Publishing |isbn=978-81-8069-311-3}}
</ref>

<ref name="amar-ujala">{{cite news | last=Correspondent | first=Mahoba |date=6 July 2011 |url=http://www.amarujala.com/city/Mahoba/Mahoba-33757-44.html |title=????????? ?? ??? ?????? ????? ????  |trans_title=Soon, a medical college for the disabled |language=Hindi |work=Amar Ujala |accessdate=9 July 2011}}</ref>

<ref name="DITE">{{cite web |author=Department of Information Technology and Electronics |url=http://infotech.up.nic.in/hindi/ourgoal/our_goal_3.htm |title=????????? ?????? |trans_title=Computer Education |language=Hindi |publisher=Government of Uttar Pradesh |accessdate=24 June 2011}}</ref>

<ref name="ngoboards">{{cite web |title=Jagadguru Rambhadracharya Viklang Seva Sangh |url=http://ngoboards.org/sites/ngoboards.org/files/about%20viklang%20seva%20sangh.doc |publisher=Jagadguru Rambhadracharya Viklang Seva Sangh |accessdate=23 August 2011}} {{Dead link|date=April 2012|bot=H3llBot}}</ref>

<ref name="oneindia">{{cite news |date=15 January 2011  |url=http://hindi.oneindia.in/news/2011/01/15/20110115240706-aid0122.html |title=???????? ??? ?????? ???? ?? ???? ????? |trans_title=Rajnath Singh awarded honorary degree in Chitrakuta |language=Hindi |work=One India Hindi |agency=Indo-Asian News Service |accessdate=26 May 2011}}</ref>

<ref name="rashtriya-sahara">{{cite web | last=SNB | first=Chitrakut |date=15 January 2011 |url=http://rashtriyasahara.samaylive.com/epapermain.aspx?queryed=9&boxid=3291131&parentid=18540&eddate=01/15/11&querypage=15 |title=????????????? ???? ?? ???????? ??????&nbsp;– ?????? ???? ????? ?? ????? ?? ???????? |trans_title=Convocation of Rambhadracharya Handicapped University&nbsp;– Rajnath Singh awarded honorary DLitt |work=Rashtriya Sahara |accessdate=24 June 2011 |language=Hindi}}</ref>

<!-- from Critical edition of Ramcharitmanas -->
<ref name="Ramcharitmanas">The Bible of Northern India:
* Lochtefeld 2001, p. 559.
* Macfie 2004, p. vii. "The choice of the subtitle is no exaggeration. The book is indeed the Bible of Northern India".
</ref>

<ref name="toi-fia">{{cite news | title = Fury in Ayodhya over Ramcharitmanas | url = http://articles.timesofindia.indiatimes.com/2009-11-01/india/28068936_1_seers-editions-disciples | accessdate =25 April 2011 | date = 1 November 2009 |work=The Times of India |location=India | first1=Manjari | last1=Mishra | first2=V. N. | last2=Arora}}</ref>

<ref name="wbd-tpe">{{cite news | first=Ram Sagar | last=Shukla | language=Hindi | publisher=Webdunia Hindi | title = ??????? ???? ?? ???? ?? ?????? | trans_title=Language and Spellings in the Ramcharitmanas | url = http://hindi.webdunia.com/samayik/article/article/0911/11/1091111004_1.htm | accessdate =29 April 2011 | date = 9 November 2009}}</ref>

<ref name="dispute-deepens">{{cite news |date=3 November 2009 |url=http://hindi.webdunia.com/news/news/regional/0911/03/1091103099_1.htm |title=??????? ???? ?? ????? ????? ?????? |trans_title=Dispute associated with Ramcharitmanas deepens |language=Hindi |work=Webdunia |accessdate=25 April 2011}}</ref>

<ref name="saints-calm-down">{{cite news |date=9 November 2009 |url=http://hindi.webdunia.com/news/news/regional/0911/09/1091109004_1.htm |title=????????????? ?? ??? ????? ?? ??? ???? ???? |trans_title=Saints calm down after Rambhadracharya expresses regret |language=Hindi |work=Webdunia |accessdate=25 April 2011}}</ref>

<!-- from Poetry and plays -->
<ref name="sa2005">{{cite web | title = Sahitya Akademi Awards 2005 | year=2005 | publisher=National Portal of India | url = http://india.gov.in/knowindia/sakademi_awards05.php | accessdate =24 April 2011|archiveurl= http://tesla.websitewelcome.com/~sahit/old_version/awa10318.htm#sanskrit|archivedate= 24 January 2008|deadurl= yes}}</ref>

<ref name="dna205">{{cite web | last=Press Trust of India | publisher=DNA India | title = Kolatkar, Dalal among Sahitya Akademi winners | url = http://www.dnaindia.com/india/report_kolatkar-dalal-among-sahitya-akademi-winners_1003524 | date = 22 December 2005 | accessdate=24 June 2011}}</ref>

<ref name="Bhaskar-2010">{{cite news | publisher=Dainik Bhaskar | title=??????? ?? ??? ???? ??? | trans_title=Orators speak out their views | language = Hindi | url = http://bollywood2.bhaskar.com/article/MP-OTH-997126-1582386.html | date=25 November 2010 | accessdate =9 September 2011}}</ref>

<!-- (books) -->
<ref name="elucidating-moonlight">{{cite book |last=Rambhadracharya |first=Swami |date=16 August 2008 |title=?r?s?t?r?makelikaumud? |trans_title=The elucidating moonlight for the childhood pastimes of Sita and Rama |language=Hindi |location=Chitrakuta |publisher=Jagadguru Rambhadracharya Handicapped University}}</ref>

<ref name="beautiful-dawn">{{cite book |first=Jagadguru |last=Rambhadracharya |date=14 January 2009 |title=?r?s?t?r?masuprabh?tam |trans_title=The beautiful dawn of Sita and Rama |language=Sanskrit |location=Chitrakoot |publisher=Jagadguru Rambhadracharya Handicapped University}}</ref>

<ref name="Ashtavakra">{{cite book |title=A???vakra Mah?k?vya |trans_title=The Epic Ashtavakra |first=Jagadguru |last=Rambhadracharya | language=Hindi |location=Chitrakuta |publisher=Jagadguru Rambhadracharya Handicapped University |date=14 January 2010}}</ref>

<ref name="Awadha">{{cite book |last=Rambhadracharya |first=Jagadguru |year=2011 |title=??? ?? ??????? |trans_title=The moonlight of Awadha |language=Awadhi |location=Chitrakuta |publisher=Jagadguru Rambhadracharya Handicapped University}}</ref>

<ref name="ocean-of-nectar">{{cite book |last=Rambhadracharya |first=Jagadguru |date=15 July 2011 |title=????????????????? |trans_title=The ocean of nectar of Sita |language=Sanskrit |location=Chitrakuta |publisher=Jagadguru Rambhadracharya Handicapped University}}</ref>

<!-- Prose -->
<!-- from Sanskrit commentaries on Prasthanatrayi -->
<ref name="subedi">{{cite web | last=Correspondent | first=Chitrakuta |date=12 January 2011 |url=http://in.jagran.yahoo.com/news/local/uttarpradesh/4_1_7168843.html |title=???? ???? ??? ????? ?? ?????? ?????? ?? ???? ???? |trans_title=Devotees dance in the blissful moments of the marriage of Sita and Rama | language=Hindi |work=Jagran Yahoo |accessdate=12 July 2011 |quote={{lang|hi|???????? ?? ??? ?????? ????? ???? ?????? ?? ??? ?? ????????????? ?? ???? ???? ????? ?????? ???? ?? ???? ?? ?? ??????????? ?? ?? ?? [sic] ??? ??? ???????? ?????? ??? ?????????? ?? ?? ?????}} [Acharya Chandra Dutt Subedi from Haridvar said that the first commentary on Prasthanatrayi was composed by Shankaracahrya, and now Jagadguru Swami Rambhadracahrya composed a commentary six hundred [sic] years after Vallabhacharya.]}}</ref>

<!-- Recognition, awards and honours -->
<!-- from Recognition -->
<ref name="wps">{{cite web | last=Rambhadracharya | first=Swami | title=???????: ?????? ?? ????? | language=Hindi | trans_title=Virtues: The Path of Peace | date = 17 December 2000 | url = http://www.panchjanya.com/17-12-2000/9sans.html | publisher=Panchjanya | accessdate=24 June 2011|archiveurl= http://web.archive.org/web/20051110171458/http://www.panchjanya.com/17-12-2000/9sans.html|archivedate= 10 November 2005|deadurl= yes}}</ref>

<ref name="wcrl">{{cite web | publisher=The World Council of Religious Leaders | title = Delegates | url = http://www.millenniumpeacesummit.com/news000905.html | accessdate=24 June 2011}}</ref>

<!-- from Awards and honours -->
<ref name="Vidyapeetha-4">{{cite web |url=http://www.slbsrsv.ac.in/newconvocation.asp |title=Shri Lal Bahadur Shastri Rashtriya Sanskrit Vidyapeetha&nbsp;– Convocation |publisher=Shri Lal Bahadur Shastri Rashtriya Sanskrit Vidyapeetha |accessdate=11 June 2011 |quote=The Fourth Convocation of the Vidyapeetha was organized on 11th of&nbsp;February,&nbsp;2000. ... Honorary title of Mahamahopadhyaya was conferred on Shri Swami Rambhadracharya (U.P.), ... by the Chancellor.}}</ref>

<ref name="tnn">{{cite news |date=17 March 2003 |title=Bhaurao Samman for Dattopanth Thengadi |url=http://articles.timesofindia.indiatimes.com/2003-03-17/lucknow/27275780_1_award-honour-sangh-workers |work=The Times of India |location=India |agency=TNN |accessdate=27 May 2011}}</ref>

<ref name="Bhaurao">{{cite news |date=30 March 2003 |url=http://www.panchjanya.com/30-3-2003/20back.html |title=???????? ?????? ????????????? ??? ?????? ????? ???????? ?????? ?? ?????? ????? ???? ??????&nbsp;– ???????? ??????? ?? ??????? ?? ?????? |trans_title=Bhaurao Devras Honour for Jagadguru Swami Rambhadracharya and eminent philosopher Dattopant Thengdi&nbsp;– Call for building a glorious nation |language=Hindi |work=Panchjanya |accessdate=29 April 2011|archiveurl= http://web.archive.org/web/20030504102726/http://www.panchjanya.com/30-3-2003/20back.html|archivedate= 4 May 2003|deadurl= yes}}</ref>

<ref name="the-hindu">{{cite news |author=Special Correspondent |date=20 February 2008 |url=http://www.hindu.com/2008/02/20/stories/2008022051411300.htm |title=Selected for Birla Foundation awards |work=The Hindu |location=India |accessdate=24 June 2011}}</ref>

<ref name="Birla">{{cite web |last=Special Correspondent |date=19 April 2008 |url=http://www.hindu.com/2008/04/19/stories/2008041954260500.htm |title=K.K. Birla Foundation awards presented |work=The Hindu |location=India |accessdate =24 June 2011}}</ref>

<ref name="prlog">{{cite news |date=4 March 2011 |url=http://www.prlog.org/11352079-himachal-pradesh-state-level-award-for-sandeep-marwah.html |title=Himachal Pradesh State Level Award For Sandeep Marwah |work=PRLog |accessdate=5 March 2011}}</ref>
}}

==Works cited==
{{refbegin|2|colwidth=35em|indent=yes}}
: {{Cite journal
 | last = Agarwal
 | first = Sudhir J.
 | title = Consolidated Judgment in OOS No. 1 of 1989, OOS No. 3 of 1989, OOS No. 4 of 1989 & OOS No. 5 of 1989
 | publisher = Allahabad High Court (Lucknow Bench)
 | location=Lucknow, Uttar Pradesh, India
 | date = 30 September 2010
 | url = http://elegalix.allahabadhighcourt.in/elegalix/DisplayAyodhyaBenchLandingPage.do
 | accessdate =24 April 2011
 | postscript = <!-- Bot inserted parameter. Either remove it; or change its value to "." for the cite to end in a ".", as necessary. -->{{inconsistent citations}}
}}
: {{cite book
 | last = Aneja
 | first = Mukta
 | editor1-first = J. K.
 | editor1-last = Kaul
 | editor2-last = Abraham
 | editor2-first = George
 | year = 2005
 | title  =  Abilities Redefined&nbsp;– Forty Life Stories Of Courage And Accomplishment
 | publisher = All India Confederation of the Blind
 | location = Delhi, India
 | chapter = Shri Ram Bhadracharyaji&nbsp;– A Religious Head With A Vision
 | url = http://www.aicb.in/images/success_story.pdf
 | accessdate  =25 April 2011 | pages = 66–68
}}
: {{cite book
 | last = Bhuyan
 | first = Devajit
 | year = 2002
 | title = Multiple Career Choices
 | publisher = Pustak Mahal
 | location = New Delhi, India
 | isbn = 978-81-223-0779-5
 | url = http://books.google.com/books?id=LtNbBNYQLmkC&printsec=frontcover
 | accessdate =9 September 2011
}}
: {{cite journal
 | last = Chandra
 | first = R.
 | month = September
 | year = 2008
 | title = ?????? ?? ????????
 | trans_title = Honours and Awards
 | language = Hindi
 | journal = Kranti Bharat Samachar
 | volume = 8
 | issue = 11
 | publisher = Rajesh Chandra Pandey
 | location = Lucknow, Uttar Pradesh, India
 | id = RNI No. 2000, UPHIN 2638
}}
: {{cite book
 | last = Dinkar
 | first = Dr. Vagish
 | title  =  ?????????????????? ???????
 | trans_title = Investigation into ?r?bh?rgavar?ghav?yam
 | publisher = Deshbharti Prakashan
 | location = Delhi, India
 | year = 2008
 | isbn = 978-81-908276-6-9
 | language = Hindi
}}
: {{cite book
 | last=Dwivedi
 | first=Hazari Prasad
 | year=2007
 | title = ?????? ?????? ???????? ?????????? ?
 | trans_title=The Complete Works of Hazari Prasad Dwivedi Volume 3
 | language=Hindi
 | editor1-first=Mukund
 | editor1-last=Dwivedi
 | publisher=Rajkamal
 | origyear=August 1981
 | edition=3rd corrected and extended
 | location=New Delhi
 | isbn=978-81-267-1358-5
}}
: {{cite book
 | last = Dwivedi
 | first = Gyanendra Kumar
 | year = 2008
 | title = Analysis and Design of Algorithm
 | publisher = Laxmi Publications
 | location = New Delhi, India
 | isbn = 978-81-318-0116-1
}}
: {{cite book
 | last1 = Gupta
 | first1 = Amita
 | last2 = Kumar
 | first2 = Ashish
 | title  =  Handbook of Universities
 | publisher = Atlantic Publishers and Distributors
 | date = 6 July 2006
 | location = New Delhi, India
 | isbn = 978-81-269-0608-6
 | url = http://books.google.com/books?id=Rs-jsaPenxYC&printsec=frontcover
 | accessdate =9 September 2011
}}
: {{cite book
 | last = Lochtefeld
 | first = James G.
 | year = 2001
 | title = The Illustrated Encyclopedia of Hinduism: N-Z
 | isbn = 978-0-8239-3180-4
 | publisher = Rosen Publishing Group
 | location=New York, New York, USA
}}
: {{cite book
 | last = Macfie
 | first = J. M.
 | year = 2004
 | title = The Ramayan of Tulsidas or the Bible of Northern India
 | location = Whitefish, Montana, USA
 | publisher = Kessinger
 | chapter = Preface
 | isbn = 978-1-4179-1498-2
 | url = http://books.google.com/?id=AbG4yfdE1b4C&printsec=frontcover&dq=ISBN9781417914982#v=onepage&q&f=false
 | accessdate =24 June 2011
}}
: {{cite journal
 | last = Mishra
 | first = Gita Devi
 | month = August
 | year = 2011
 | title = ???????? ???????? ?? ?? ????? ?????? ????
 | trans_title = Tulsi Award 2011 to Honorable Jagadguru
 | language = Hindi
 | editor1-first = Surendra Sharma
 | editor1-last = Sushil
 | journal = Shri Tulsi Peeth Saurabh
 | volume = 15
 | issue = 3
 | publisher = Shri Tulsi Peeth Seva Nyas
 | place = Ghaziabad, Uttar Pradesh, India
}}
: {{cite book
 | last = Nagar
 | first = Shanti Lal
 | title  =  The Holy Journey of a Divine Saint: Being the English Rendering of Swarnayatra Abhinandan Granth
 | editor1-first = Acharya Divakar
 | editor1-last = Sharma
 | editor2-first = Siva Kumar
 | editor2-last = Goyal
 | editor3-first = Surendra Sharma
 | editor3-last = Sushil
 | publisher = B. R. Publishing Corporation
 | edition = First, Hardback
 | location = New Delhi, India
 | year = 2002
 | isbn = 81-7646-288-8
}}
: {{cite book
 | last = Pandey
 | first = Ram Ganesh
 | year = 2008
 | title = ????? ???? ????: ??? ???????
 | trans_title = The Birthplace of Tulasidasa: Investigative Research
 | language = Hindi
 | location = Chitrakoot, Uttar Pradesh, India
 | publisher = Bharati Bhavan Publication
 | edition = Corrected and extended
 | origyear = First edition 2003
}}
: {{cite book
 | first=Hanuman Prasad
 | last=Poddar
 | year=1996
 | title=Doh?val?
 | language=Hindi
 | location=Gorakhpur, Uttar Pradesh, India
 | publisher=Gita Press
}}
: {{cite book
 | last = Prasad
 | first = Ram Chandra
 | title = Sri Ramacaritamanasa The Holy Lake Of The Acts Of Rama
 | publisher = Motilal Banarsidass
 | year = 1999
 | edition = Illustrated, reprint
 | origyear = First published 1991
 | location = Delhi, India
 | isbn = 81-208-0762-6
}}
: {{cite book
 | editor-last = Rambhadracharya
 | editor-first = Jagadguru
 | title = ???????????????&nbsp;– ??? ????? (???????? ???????)
 | trans_title = ?r?r?macaritam?nasa&nbsp;– Original Text (Tulas?p??ha edition)
 | edition = 4th
 | publisher = Jagadguru Rambhadracharya Handicapped University
 | location = Chitrakoot, Uttar Pradesh, India
 | date = 30 March 2006
 | language = Hindi
}}
: {{Cite journal
 | last = Sharma
 | first = Dharam Veer
 | title = Judgment in OOS No. 4 of 1989
 | publisher = Allahabad High Court (Lucknow Bench)
 | location=Lucknow, Uttar Pradesh, India
 | url = http://elegalix.allahabadhighcourt.in/elegalix/DisplayAyodhyaBenchLandingPage.do
 | date = 30 September 2010a
 | accessdate =24 April 2011
}}
: {{Cite journal
 | last = Sharma
 | first = Dharam Veer
 | title = Annexure V
 | publisher = Allahabad High Court (Lucknow Bench)
 | location=Lucknow, Uttar Pradesh, India
 | url = http://elegalix.allahabadhighcourt.in/elegalix/DisplayAyodhyaBenchLandingPage.do
 | date = 30 September 2010b
 | accessdate =24 April 2011
}}
: {{cite book
 | title  =  ??????????? (??????????????)
 | trans_title = Completion of 60 years (Felicitation Book)
 | editor1-first = Acharya Divakar
 | editor1-last = Sharma
 | editor2-first = Surendra Sharma
 | editor2-last = Sushil
 | editor3-first = Vandana
 | editor3-last = Shrivastav
 | publisher = Tulsi Mandal
 | location = Ghaziabad, Uttar Pradesh, India
 | date = 14 January 2011
 | language= Hindi
}}
: {{cite journal
 | last1=Sushil
 | first1=Surendra Sharma
 | last2=Mishra
 | first2=Abhiraj Rajendra
 | month=February
 | year=2011
 | title=??????????????????
 | trans_title=Praise of G?tar?m?ya?am
 | language=Hindi
 | volume=14 |issue=9
 | editor1-last=Sushil
 | editor1-first=Surendra Sharma
 | journal=Shri Tulsi Peeth Saurabh
 | publisher=Shri Tulsi Peeth Seva Nyas
 | location=Ghaziabad, Uttar Pradesh, India
}}

{{refend}}

==External links==
{{commons category|Jagadguru Rambhadracharya}}
{{Wikiquote|Jagadguru R?mabhadr?c?rya}}
* {{URL|http://www.jagadgururambhadracharya.org/|Official Website of Jagadguru Rambhadracharya}}
* {{URL|http://jagadgururambhadracharya.org/ViewContent/pdfs/Jagadguru%20Rambhadracharya%20-%20Ramacaritamanasa%20Bhavarthabodhini.pdf| Critical edition of Ramcharitmanas edited by Jagadguru Rambhadracharya, with a Hindi commentary}}
* {{URL|http://www.jrhu.com/|Jagadguru Rambhadracharya Handicapped University}}
* {{URL|http://www.youtube.com/user/namoraghavay|Youtube channel with information and discourses of Jagadguru Rambhadracharya}}
<!-- Twitter probably not needed * {{Twitter|JagadguruJi}} -->
* {{worldcat id|id=lccn-no00-31205|name=R?mabhadr?c?rya}}
{{s-start}}
{{s-ach|aw}}
{{s-bef | before = [[Kala Nath Shastry]]}}
{{s-ttl | title = Recipient of the [[List of Sahitya Akademi Award winners for Sanskrit|Sahitya Akademi Award winners for Sanskrit]] | years = 2005}}
{{s-aft | after = [[Harshadev Madhav]]}}
{{s-bef | before = [[Bhaskaracharya Tripathi]]}}
{{s-ttl | title = Recipient of the [[K. K. Birla Foundation|Vachaspati Award]] | years = 2007}}
{{s-aft | after = Harinarayan Dixit}}
{{s-end}}
{{Navboxes|list1=
{{Jagadguru Rambhadracharya|state=uncollapsed}}
{{Indian Philosophy}}
}}
{{Authority control|PND=132765004|LCCN=no/00/031205|VIAF=166884611|SELIBR=}}

{{good article}}

{{Persondata <!-- Metadata: see [[Wikipedia:Persondata]]. -->
| NAME = Rambhadracharya, Swami
| ALTERNATIVE NAMES = Rambhadracharya, Jagadguru; Rambhadracharya, Swami; Mishra, Giridhar; Mishra, Giridhara; R?mabhadr?c?rya, Jagadguru; R?mabhadr?c?rya, Sv?m?; Mi?ra, Giridhara
| SHORT DESCRIPTION = Vaishnava (Hindu) saint, poet, commentator, educationist, religious and social figure from India
| DATE OF BIRTH = 14 January 1950
| PLACE OF BIRTH = Jaunpur, Uttar Pradesh, India
}}
{{DEFAULTSORT:Rambhadracharya, Swami}}
[[Category:1950 births]]
[[Category:21st-century composers]]
[[Category:21st-century philosophers]]
[[Category:21st-century religious leaders]]
[[Category:Blind academics]]
[[Category:Blind musicians]]
[[Category:Hindi poets]]
[[Category:Hindu gurus]]
[[Category:Hindu religious leaders]]
[[Category:Indian academics]]
[[Category:Indian educationists]]
[[Category:Indian Hindus]]
[[Category:Indian philosophers]]
[[Category:Indian saints]]
[[Category:Living people]]
[[Category:People from Jaunpur]]
[[Category:Recipients of the Sahitya Akademi Award in Sanskrit]]
[[Category:Sanskrit poets]]
[[Category:Vaishnavite religious figures]]
[[Category:Article Feedback 5 Additional Articles]]

<!--Other languages-->
{{Link FA|hi}}
{{Link FA|pi}}
{{Link FA|sa}}

[[bh:?????????????]]
[[de:Rambhadracharya]]
[[es:Jagadguru Rambhadracharya]]
[[hif:Rambhadracharya]]
[[fr:Rambhadracharya]]
[[gu:???????? ?????????????]]
[[hi:???????? ?????????????]]
[[mr:?????????????]]
[[pa:??????? ???????????]]
[[pi:?????????????????????? Jagadgurur?mabhadr?c?rya?]]
[[ru:??????????????, ?????]]
[[sa:??????????????????????]]
[[simple:Jagadguru Rambhadracharya]]
[[ta:????????????????]]
[[te:?????????????]]
 

Deletionofreferences;

    
    die (expand_text(
            $problem_text
));
// die (expand_text("JSTOR lookup failure: {{Cite journal | jstor = 1303024}}")); - JSTOR API down
    
die(expand_text("
  More title tampering
{cite journal |author=Fazilleau et al. |title=Follicular helper T cells: lineage and location |journal=Immunity |volume=30 |issue=3 |pages=324–35 |year=2009 |month=March |pmid=19303387 |doi=10.1016/j.immuni.2009.03.003 
|last2=Mark |first2=L |last3=McHeyzer-Williams |first3=LJ |last4=McHeyzer-Williams |first4=MG |pmc=2731675}}</ref>.
"));

die(expand_text("

  Does not expand.  This appears to be a (long-term) problem with the JSTOR API.
{{Cite journal | jstor = 4494763}}


    

"));
    
die (expand_text('

Reference renaming:

{{ref doi|10.1016/S0016-6995(97)80056-3}}

.<ref name="Wilby1997">{{cite doi|10.1016/S0016-6995(97)80056-3 }}</ref>


'));
    
/*/
// For version 3:
die (expand_text("

{{cite journal | author = Ridzon R, Gallagher K, Ciesielski C ''et al.'' | year = 1997 | title = Simultaneous transmission of human immunodeficiency virus and hepatitis C virus from a needle-stick injury | url = | journal = N Engl J Med | volume = 336 | issue = | pages = 919–22 }}. (full stop to innards)<
<ref>http://www.ncbi.nlm.nih.gov/pubmed/15361495</ref>
", false));
/**/
  }
  /*$start_code = getRawWikiText($page, false, false);*/
  $slow_mode = true;

  print "\n";
  //
  
  while ($page) {
    $page = nextPage($page);
    $end_text = expand($page, $ON);
  }
  //write($page, $end_text, $editInitiator . "Re task #6 : Trial edit");
}
die ("\n Done. \n");