<?php
const LC_SMALL_WORDS = array(/* The following will be automatically updated to alphabetical order */
          ' AI ', ' AJHG ', ' al ', ' an ', ' and ', ' and then ', ' as ', ' at ', ' at ', ' aus ', ' BBA ', 
          ' be ', ' BMC ', ' BMJ ', ' but ', ' by ', ' d\'un ', ' d\'une ', ' da ', ' dans ', 
          ' das ', ' de ', ' de ', ' dei ', ' del ', ' della ', ' dem ', ' den ', ' der ', 
          ' des ', ' di ', ' die ', ' DNA ', ' do ', ' du ', ' du ', ' e ', ' ed ', ' ein ', 
          ' eine ', ' einen ', ' el ', ' else ', ' EMBO ', ' en ', ' et ', ' FASEB ', ' FEBS ', 
          ' FEMS ', ' for ', ' from ', ' för ', ' für ', ' if ', ' in ', ' into ', ' is ', 
          ' its ', ' JAMA ', ' la ', ' las ', ' le ', ' les ', ' los ', ' MNRAS ', ' NEJM ', 
          ' nor ', ' NY ', ' NYC ', ' NYT ', ' of ', ' off ', ' on ', ' og ', ' or ', 
          ' over ', ' PCR ', ' per ', ' PNAS ', ' RNA ', ' SSRN ', ' the ', ' then ', ' to ', ' UK ', 
          ' um ', ' und ', ' up ', ' USA ', ' van ', ' von ', ' when ', ' with ', ' woor ', 
          ' y ', ' zu ', ' zur ', /* The above will be automatically updated to alphabetical order */ 
          // After this line we list exceptions that need re-capitalizing after they've been decapitalized.
          ' El Dorado ', ' Las Vegas ', ' Los Angeles ', ' N Y ', ' U S A ');
const UC_SMALL_WORDS = array(/* The following will be automatically updated to alphabetical order */
          ' Ai ', ' Ajhg ', ' Al ', ' An ', ' And ', ' and Then ', ' As ', ' At ', ' At ', ' Aus ', ' Bba ', 
          ' Be ', ' Bmc ', ' Bmj ', ' But ', ' By ', ' D\'un ', ' D\'une ', ' Da ', ' Dans ', 
          ' Das ', ' De ', ' De ', ' Dei ', ' Del ', ' Della ', ' Dem ', ' Den ', ' Der ', 
          ' Des ', ' Di ', ' Die ', ' Dna ', ' Do ', ' Du ', ' Du ', ' E ', ' Ed ', ' Ein ', 
          ' Eine ', ' Einen ', ' El ', ' Else ', ' Embo ', ' En ', ' Et ', ' Faseb ', ' Febs ', 
          ' Fems ', ' For ', ' From ', ' För ', ' Für ', ' If ', ' In ', ' Into ', ' Is ', 
          ' Its ', ' Jama ', ' La ', ' Las ', ' Le ', ' Les ', ' Los ', ' Mnras ', ' Nejm ', 
          ' Nor ', ' Ny ', ' Nyc ', ' Nyt ', ' Of ', ' Off ', ' On ', ' Og ', ' Or ', 
          ' Over ', ' Pcr ', ' Per ', ' Pnas ', ' Rna ', ' Ssrn ', ' The ', ' Then ', ' To ', ' Uk ', 
          ' Um ', ' Und ', ' Up ', ' Usa ', ' Van ', ' Von ', ' When ', ' With ', ' Woor ', 
          ' Y ', ' Zu ', ' Zur ', /* The above will be automatically updated to alphabetical order */ 

          // After this line we list exceptions that need re-capitalizing after they've been decapitalized.
          ' el Dorado ', ' las Vegas ', ' los Angeles ', ' N y ', ' U S a ');

const JOURNAL_ACRONYMS = array(/* The following will be automatically updated to alphabetical order */
          ' ASME AES ', ' ASME MTD ', ' BioEssays ', ' bioRxiv ', ' BMJ ', ' CBD Ubiquitin ', ' CFSK-DT ', ' e-Journal ', ' e-Neuroforum ', 
          ' Early Modern Japan: an Interdisciplinary Journal ', ' eJournal ', ' eLife ', ' EMBO J ', 
          ' EMBO J. ', ' EMBO Journal ', ' EMBO Rep ', ' EMBO Rep. ', ' EMBO Reports ', ' eNeuro ', ' engrXiv ', ' ePlasty ',
          ' ePrints ', ' eVolo ',
          ' FASEB J ', ' FASEB J. ', ' FEBS J ', ' FEBS J. ', ' FEBS Journal ', ' HOAJ biology ', ' hprints ', 
          ' iConference ', ' IFAC-PapersOnLine ', ' iPhone ', ' ISRN Genetics ', ' JABS : Journal of Applied Biological Sciences ', 
          ' JAMA Psychiatry ', ' Journal of Materials Chemistry A ', ' Journal of the IEST ', ' mAbs ', ' mBio  ',
          ' Molecular and Cellular Biology ', ' mSphere ', ' mSystems ', 
          ' NASA Tech Briefs ', ' Ny Forskning i Grammatik ', ' Ocean Science Journal : OSJ ', 
          ' PALAIOS ', ' PLOS Biology ', ' PLOS Medicine ', ' PLOS Neglected Tropical Diseases ', 
          ' PLOS ONE ', ' PNAS ', ' RNA ', ' S.A.P.I.EN.S ', ' Star Trek: The Official Monthly Magazine ', 
          ' Tellus A ', ' The EMBO Journal ', ' Time Out London ', 
          ' z/Journal ', ' Zeitschrift für Geologische Wissenschaften ', ' Zeitschrift für Physik A Hadrons and Nuclei ', 
          ' Zeitschrift für Physik A: Hadrons and Nuclei ', ' ZooKeys '
          /* The above will be automatically updated to alphabetical order */ 
);
const UCFIRST_JOURNAL_ACRONYMS = array(/* The following will be automatically updated to alphabetical order */
          ' Asme Aes ', ' Asme Mtd ', ' Bioessays ', ' Biorxiv ', ' Bmj ', ' Cbd Ubiquitin ', ' Cfsk-Dt ', ' E-journal ', ' E-Neuroforum ', 
          ' Early Modern Japan: An Interdisciplinary Journal ', ' Ejournal ', ' Elife ', ' Embo J ', 
          ' Embo J. ', ' Embo Journal ', ' Embo Rep ', ' Embo Rep. ', ' Embo Reports ', ' Eneuro ', ' Engrxiv ', ' Eplasty ',
          ' Eprints ', ' Evolo ', 
          ' Faseb J ', ' Faseb J. ', ' Febs J ', ' Febs J. ', ' Febs Journal ', ' Hoaj Biology ', ' Hprints ', 
          ' Iconference ', ' Ifac-Papersonline ', ' Iphone ', ' Isrn Genetics ', ' Jabs : Journal of Applied Biological Sciences ', 
          ' Jama Psychiatry ', ' Journal of Materials Chemistry A ', ' Journal of the Iest ', ' Mabs ', ' Mbio ',
          ' Molecular and Cellular Biology ', ' Msphere ', ' Msystems ', 
          ' Nasa Tech Briefs ', ' Ny Forskning I Grammatik ', ' Ocean Science Journal : Osj ', 
          ' Palaios ', ' Plos Biology ', ' Plos Medicine ', ' Plos Neglected Tropical Diseases ', 
          ' Plos One ', ' Pnas ', ' Rna ', ' S.a.p.i.en.s ', ' Star Trek: The Official Monthly Magazine ', 
          ' Tellus a ', ' The Embo Journal ', ' Time Out London ', 
          ' Z/journal ', ' Zeitschrift Für Geologische Wissenschaften ', ' Zeitschrift für Physik a Hadrons and Nuclei ', 
          ' Zeitschrift Für Physik a: Hadrons And Nuclei ', ' Zookeys '
          /* The above will be automatically updated to alphabetical order */ 
); 
