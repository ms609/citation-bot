<?php
const LC_SMALL_WORDS = array(/* The following will be automatically updated to alphabetical order */
          ' AAP ', ' ABC ', ' AC ', ' ACM ', ' AI ', ' AIAA ', ' AJHG ', ' al ', ' an ',
          ' and ', ' and then ', ' as ', ' at ', ' at ',
          ' aus ', ' av ', ' BBA ', ' BBC ', ' be ', ' BJPsych ', ' BMC ', ' BMJ ', ' but ', ' by ',
          ' CBC ', ' CNS ', ' d\'un ', ' d\'une ', ' da ', ' dans ', 
          ' das ', ' DC ', ' de ', ' de ', ' dei ', ' del ', ' della ', ' delle ', ' dem ', ' den ', ' der ', 
          ' des ', ' di ', ' die ', ' DNA ', ' do ', ' du ', ' du ', ' e ', ' ed ', ' ein ', 
          ' eine ', ' einen ', ' el ', ' else ', ' EMBO ', ' en ', ' et ', ' FASEB ', ' FEBS ', 
          ' FEMS ', ' for ', ' from ', ' för ', ' für ', ' IEEE ', ' if ', ' ILR ', ' in ', ' into ', ' is ', 
          ' its ', ' JAMA ', ' la ', ' las ', ' le ', ' les ', ' los ', ' MNRAS ', ' mot ', ' NASA ', ' NEJM ', ' non ',
          ' nor ', ' NY ', ' NYC ', ' NYT ', ' och ', ' OECD ', ' of ', ' off ', ' on ', ' og ', ' or ', 
          ' over ', ' PCR ', ' per ', ' PNAS ', ' R&D ', ' RNA ', ' SSRN ', ' the ', ' then ', ' till ', ' to ', ' UK ', 
          ' um ', ' und ', ' up ', ' USA ', ' van ', ' von ', ' voor ', ' when ', ' with ', ' within ', ' woor ', 
          ' y ', ' zu ', ' zum ', ' zur ', /* The above will be automatically updated to alphabetical order */ 
          // After this line we list exceptions that need re-capitalizing after they've been decapitalized.
          ' El Dorado ', ' Las Vegas ', ' Los Angeles ', ' N Y ', ' U S A ');
const UC_SMALL_WORDS = array(/* The following will be automatically updated to alphabetical order */
          ' Aap ', ' Abc ', ' Ac ', ' Acm ', ' Ai ', ' Aiaa ', ' Ajhg ', ' Al ', ' An ', ' And ',
          ' and Then ', ' As ', ' At ', ' At ',
          ' Aus ', ' Av ', ' Bba ', ' Bbc ', ' Be ', ' Bjpsych ', ' Bmc ', ' Bmj ', ' But ', ' By ', 
          ' Cbc ', ' Cns ', ' D\'un ', ' D\'une ', ' Da ', ' Dans ', 
          ' Das ', ' Dc ', ' De ', ' De ', ' Dei ', ' Del ', ' Della ', ' Delle ', ' Dem ', ' Den ', ' Der ', 
          ' Des ', ' Di ', ' Die ', ' Dna ', ' Do ', ' Du ', ' Du ', ' E ', ' Ed ', ' Ein ', 
          ' Eine ', ' Einen ', ' El ', ' Else ', ' Embo ', ' En ', ' Et ', ' Faseb ', ' Febs ', 
          ' Fems ', ' For ', ' From ', ' För ', ' Für ', ' Ieee ', ' If ', ' Ilr ', ' In ', ' Into ', ' Is ', 
          ' Its ', ' Jama ', ' La ', ' Las ', ' Le ', ' Les ', ' Los ', ' Mnras ', ' Mot ', ' Nasa ', ' Nejm ', ' Non ',
          ' Nor ', ' Ny ', ' Nyc ', ' Nyt ', ' Och ', ' Oecd ', ' Of ', ' Off ', ' On ', ' Og ', ' Or ', 
          ' Over ', ' Pcr ', ' Per ', ' Pnas ', ' R&d ', ' Rna ', ' Ssrn ', ' The ', ' Then ', ' Till ', ' To ', ' Uk ', 
          ' Um ', ' Und ', ' Up ', ' Usa ', ' Van ', ' Von ', ' Voor ', ' When ', ' With ', ' Within ', ' Woor ', 
          ' Y ', ' Zu ', ' Zum ', ' Zur ', /* The above will be automatically updated to alphabetical order */ 

          // After this line we list exceptions that need re-capitalizing after they've been decapitalized.
          ' el Dorado ', ' las Vegas ', ' los Angeles ', ' N y ', ' U S a ');

          // For ones that start with lower-case, include both ELife and Elife versions in misspelled array
const JOURNAL_ACRONYMS = array(/* The following will be automatically updated to alphabetical order */
          ' Arch Dis Child Fetal Neonatal Ed ', ' ASME AES ', ' ASME MTD ', ' BioEssays ', ' bioRxiv ', ' bioRxiv ',
          ' BMJ ', ' CBD Ubiquitin ', ' CFSK-DT ', ' e-Journal ', ' e-Journal ', ' e-Neuroforum ', ' e-Neuroforum ',
          ' e-Print ', ' e-Print ', ' e-Prints ', ' e-Prints ', ' Early Modern Japan: an Interdisciplinary Journal ',
          ' EFSA ', ' eJournal ', ' eJournal ', ' eLife ', ' eLife ', ' eLS ', ' eLS ', ' EMBO J ', 
          ' EMBO J. ', ' EMBO Journal ', ' EMBO Rep ', ' EMBO Rep. ', ' EMBO Reports ', ' eNeuro ', ' eNeuro ', ' engrXiv ', ' ePlasty ',' ePlasty ',
          ' ePrint ', ' ePrint ', ' ePrints ', ' ePrints ', ' eScholarship ', ' eVolo ', ' eVolo ',
          ' FASEB J ', ' FASEB J. ', ' FEBS J ', ' FEBS J. ', ' FEBS Journal ', ' HannahArendt.net ', ' HOAJ biology ', ' hprints ', 
          ' iConference ', ' IFAC-PapersOnLine ', ' iPhone ', ' ISRN Genetics ', ' J Gerontol A Biol Sci Med Sci ',
          ' J SIAM ', ' J. SIAM ', ' JABS : Journal of Applied Biological Sciences ', 
          ' JAMA Psychiatry ', ' Journal of Materials Chemistry A ', ' Journal of the A.I.E.E. ', ' Journal of the IEST ', ' mAbs ', ' mAbs ', ' mBio ', ' mBio ',
          ' Meddelelser om Grønland ', ' Meddelelser om Grønland, ', ' Methods in Molecular Biology ', ' mHealth ', ' mHealth ', ' Molecular and Cellular Biology ', ' mSphere ', ' mSphere ', ' mSystems ', ' mSystems ', 
          ' NASA Tech Briefs ', ' Ny Forskning i Grammatik ', ' Ocean Science Journal : OSJ ', 
          ' PAJ: A Journal of Performance and Art ', ' PALAIOS ', ' PeerJ ', ' PLOS Biology ', ' PLOS Medicine ', ' PLOS Neglected Tropical Diseases ', 
          ' PLOS ONE ', ' PNAS ', ' RNA ', ' S.A.P.I.EN.S ', ' Star Trek: The Official Monthly Magazine ', 
          ' Tellus A ', ' The EMBO Journal ', ' Time Out London ', ' uHealth ', ' uHealth ',
          ' z/Journal ', ' z/Journal ', ' Zeitschrift für Geologische Wissenschaften ', ' Zeitschrift für Physik A Hadrons and Nuclei ', 
          ' Zeitschrift für Physik A: Hadrons and Nuclei ', ' ZooKeys '
          /* The above will be automatically updated to alphabetical order */ 
);
const UCFIRST_JOURNAL_ACRONYMS = array(/* The following will be automatically updated to alphabetical order */
          ' Arch Dis Child Fetal Neonatal ed ', ' Asme Aes ', ' Asme Mtd ', ' Bioessays ', ' Biorxiv ', ' BioRxiv ',
          ' Bmj ', ' Cbd Ubiquitin ', ' Cfsk-Dt ', ' E-journal ', ' E-Journal ', ' E-neuroforum ', ' E-Neuroforum ', 
          ' E-print ', ' E-Print ', ' E-prints ', ' E-Prints ', ' Early Modern Japan: An Interdisciplinary Journal ',
          ' Efsa ', ' Ejournal ', ' EJournal ', ' ELife ', ' Elife ', ' Els ', ' ELS ', ' Embo J ', 
          ' Embo J. ', ' Embo Journal ', ' Embo Rep ', ' Embo Rep. ', ' Embo Reports ', ' Eneuro ', ' ENeuro ', ' Engrxiv ', ' Eplasty ', ' EPlasty ',
          ' Eprint ', ' EPrint ', ' Eprints ',' EPrints ', ' Escholarship ', ' Evolo ', ' EVolo ', 
          ' Faseb J ', ' Faseb J. ', ' Febs J ', ' Febs J. ', ' Febs Journal ', ' Hannaharendt.net ', ' Hoaj Biology ', ' Hprints ', 
          ' Iconference ', ' Ifac-Papersonline ', ' Iphone ', ' Isrn Genetics ', ' J Gerontol a Biol Sci Med Sci ',
          ' J Siam ', ' J. Siam ', ' Jabs : Journal of Applied Biological Sciences ', 
          ' Jama Psychiatry ', ' Journal of Materials Chemistry A ', ' Journal of the A.i.i.e ', ' Journal of the Iest ', ' Mabs ', ' MAbs ', ' Mbio ', ' MBio ',
          ' Meddelelser Om Grønland ', ' Meddelelser Om Grønland, ', ' Methods in Molecular Biology (Clifton, N.j.) ', ' Mhealth ', ' MHealth ', ' Molecular and Cellular Biology ', ' Msphere ', ' MSphere ', ' Msystems ', ' MSystems ', 
          ' Nasa Tech Briefs ', ' NY Forskning I Grammatik ', ' Ocean Science Journal : Osj ', 
          ' Paj: A Journal of Performance and Art ', ' Palaios ', ' Peerj ', ' Plos Biology ', ' Plos Medicine ', ' Plos Neglected Tropical Diseases ', 
          ' Plos One ', ' Pnas ', ' Rna ', ' S.a.p.i.en.s ', ' Star Trek: The Official Monthly Magazine ', 
          ' Tellus a ', ' The Embo Journal ', ' Time Out London ', ' Uhealth ', ' UHealth ',
          ' Z/journal ', ' Z/Journal ', ' Zeitschrift Für Geologische Wissenschaften ', ' Zeitschrift für Physik a Hadrons and Nuclei ', 
          ' Zeitschrift Für Physik a: Hadrons And Nuclei ', ' Zookeys '
          /* The above will be automatically updated to alphabetical order */ 
); 
