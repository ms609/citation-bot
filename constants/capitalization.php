<?php
const LC_SMALL_WORDS = array(' and then ', ' of ',' the ',' and ',' an ',' or ',' nor ',' but ',' is ',' if ',' its ',
                             ' then ',' else ',' when ', ' at ',' from ',' by ',' on ',' off ',' for ',' in ',' over ',
                             ' to ',' into ',' with ',' U S A ',' USA ',' y ', ' für ', ' de ', ' zur ', ' der ', ' und ',
                             ' du ', ' et ', ' la ', ' le ', ' DNA ', ' UK ', ' FASEB ', ' van ', ' von ', ' AJHG ',
                             ' BBA ', ' BMC ', ' BMJ ', ' EMBO ', ' FEBS ', ' FEMS ', ' JAMA ', ' MNRAS ', ' NEJM ', 
                             ' NYT ', ' PCR ', ' PNAS ', ' RNA ',  ' zu ' , ' des ', ' aus ', ' dem ', ' del ', ' dei ', 
                             ' di ', ' ed ', ' du ', ' de ', ' dans ', ' les ', ' e ', ' den ', ' die ', ' das ', ' ein ', 
                             ' eine ', ' einen ', ' NYC ', ' d\'une ', ' d\'un ', ' el ',  ' los ', ' las ', ' as ',
                             ' nor ',' at ', ' up ', ' NY ', ' N Y ', ' för ', ' da ', ' SSRN ', ' AI ', ' woor ', ' do ', ' be ',
                             // After this line we list exceptions that need re-capitalizing after they've been decapitalized.
                             ' El Dorado ', ' Las Vegas ', ' Los Angeles ');
const UC_SMALL_WORDS = array(' and Then ', ' Of ',' The ',' And ',' An ',' Or ',' Nor ',' But ',' Is ',' If ',' Its ',
                             ' Then ',' Else ',' When ', ' At ',' From ',' By ',' On ',' Off ',' For ',' In ',' Over ',
                             ' To ',' Into ',' With ',' U S A ',' Usa ',' Y ', ' Für ', ' De ', ' Zur ', ' Der ', ' Und ',
                             ' Du ', ' Et ', ' La ', ' Le ', ' Dna ', ' Uk ', ' Faseb ', ' Van ', ' Von ', ' Ajhg ',
                             ' Bba ', ' Bmc ', ' Bmj ', ' Embo ', ' Febs ', ' Fems ', ' Jama ', ' Mnras ', ' Nejm ', 
                             ' Nyt ', ' Pcr ', ' Pnas ', ' Rna ',  ' Zu ' , ' Des ', ' Aus ', ' Dem ', ' Del ', ' Dei ', 
                             ' Di ', ' Ed ', ' Du ', ' De ', ' Dans ', ' Les ', ' E ', ' Den ', ' Die ', ' Das ', ' Ein ', 
                             ' Eine ', ' Einen ', ' Nyc ', ' D\'une ', ' D\'un ', ' El ',  ' Los ', ' Las ', ' As ',
                             ' Nor ',' At ', ' Up ', ' Ny ', ' N y ', ' För ', ' Da ', ' Ssrn ', ' Ai ', ' Woor ', ' Do ', ' Be ',
                             // After this line we list exceptions that need re-capitalizing after they've been decapitalized.
                             ' el Dorado ', ' las Vegas ', ' los Angeles ');

const JOURNAL_ACRONYMS = array(
' ACM SIGPLAN Notices ', ' ASME AES ', ' ASME MTD ', ' BioEssays ', ' BMJ ',
' CBD Ubiquitin ', ' CFSK-DT ', ' e-Neuroforum ', 
' Early Modern Japan: an Interdisciplinary Journal ', ' eLife ', ' EMBO J ', ' EMBO J. ', ' EMBO Journal ',
' EMBO Rep ', ' EMBO Rep. ', ' EMBO Reports ', ' eNeuro ', ' FASEB J ', ' FASEB J. ', ' FEBS J ', ' FEBS J. ', ' FEBS Journal ',
' HOAJ biology ', ' iConference ', ' IFAC-PapersOnLine ', ' ISRN Genetics ',
' JABS : Journal of Applied Biological Sciences ', ' JAMA Psychiatry ', ' Journal of Materials Chemistry A ',' Journal of the IEST ', 
' Molecular and Cellular Biology ', ' NASA Tech Briefs ', ' Ocean Science Journal : OSJ ', 
' PALAIOS ',  ' PLOS Biology ', ' PLOS Medicine ', ' PLOS Neglected Tropical Diseases ', ' PLOS ONE ', ' PNAS ',
' RNA ',
' S.A.P.I.EN.S ', ' Star Trek: The Official Monthly Magazine ',     ' Tellus A ', ' The EMBO Journal ', ' Time Out London ',
' z/Journal ', ' Zeitschrift für Geologische Wissenschaften ', ' Zeitschrift für Physik A: Hadrons and Nuclei ', ' Zeitschrift für Physik A Hadrons and Nuclei ', 
' ZooKeys ');
const UCFIRST_JOURNAL_ACRONYMS = array(
' Acm Sigplan Notices ', ' Asme Aes ', ' Asme Mtd ', ' Bioessays ', ' Bmj ',
' Cbd Ubiquitin ', ' Cfsk-Dt ', ' E-Neuroforum ', 
' Early Modern Japan: An Interdisciplinary Journal ', ' Elife ', ' Embo J ', ' Embo J. ', ' Embo Journal ', 
' Embo Rep ', ' Embo Rep. ', ' Embo Reports ', ' Eneuro ', ' Faseb J ', ' Faseb J. ', ' Febs J ', ' Febs J. ', ' Febs Journal ',
' Hoaj Biology ', ' Iconference ', ' Ifac-Papersonline ', ' Isrn Genetics ',
' Jabs : Journal Of Applied Biological Sciences ', ' Jama Psychiatry ', ' Journal Of Materials Chemistry A ', ' Journal Of The Iest ', 
' Molecular And Cellular Biology ', ' Nasa Tech Briefs ', ' Ocean Science Journal : Osj ', 
' Palaios ', ' Plos Biology ', ' Plos Medicine ', ' Plos Neglected Tropical Diseases ', ' Plos One ', ' Pnas ', 
' Rna ',
' S.a.p.i.en.s ', ' Star Trek: The Official Monthly Magazine ', ' Tellus A ', ' The Embo Journal ', ' Time Out London ',
' Z/journal ', ' Zeitschrift Für Geologische Wissenschaften ', ' Zeitschrift Für Physik A: Hadrons And Nuclei ', ' Zeitschrift Für Physik A Hadrons And Nuclei ', 
' Zookeys '); 
