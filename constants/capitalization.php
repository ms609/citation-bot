<?php
const LC_SMALL_WORDS = array(/* The following will be automatically updated to alphabetical order */
          ' AAP ', ' AAUP ', ' ABC ', ' AC ', ' ACM ', ' AI ', ' AIAA ', ' AJHG ', ' al ', ' an ',
          ' and ', ' and then ', ' as ', ' at ', ' at ',
          ' aus ', ' av ', ' BBA ', ' BBC ', ' be ', ' bei ',  ' BJPsych ', ' BMC ', ' BMJ ', ' but ', ' by ',
          ' CBC ', ' CNS ', ' d\'un ', ' d\'une ', ' da ', ' dans ', 
          ' das ', ' DC ', ' de ', ' dei ', ' del ', ' della ', ' delle ', ' dem ', ' den ', ' der ', 
          ' des ', ' di ', ' die ', ' DNA ', ' do ', ' du ', ' e ', ' ed ', ' ein ', 
          ' eine ', ' einen ', ' el ', ' else ', ' EMBO ', ' en ', ' et ', ' FASEB ', ' FEBS ', 
          ' FEMS ', ' for ', ' from ', ' för ', ' für ', ' IEEE ', ' if ', ' ILR ', ' in ', ' into ', ' is ', 
          ' its ', ' JAMA ', ' la ', ' las ', ' le ', ' les ', ' los ', ' mit ',  ' MNRAS ', ' mot ', ' NASA ', ' NEJM ', ' non ',
          ' nor ', ' NRC ', ' NY ', ' NYC ', ' NYT ', ' NZ ', ' och ', ' OECD ', ' of ', ' off ', ' on ', ' og ', ' or ', 
          ' over ', ' PCR ', ' per ', ' PNAS ', ' PS: ', ' R&D ', ' RNA ', ' S&P ', ' SAE ',  ' SSRN ', ' the ', ' then ', ' till ', ' to ', ' UK ', 
          ' um ', ' und ', ' up ', ' USA ', ' van ', ' von ', ' voor ', ' when ', ' with ', ' within ', ' woor ', 
          ' y ', ' zu ', ' zum ', ' zur ', /* The above will be automatically updated to alphabetical order */ 
          // After this line we list exceptions that need re-capitalizing after they've been decapitalized.
          ' El Dorado ', ' Las Vegas ', ' Los Angeles ', ' N Y ', ' U S A ');
const UC_SMALL_WORDS = array(/* The following will be automatically updated to alphabetical order */
          ' Aap ', ' Aaup ', ' Abc ', ' Ac ', ' Acm ', ' Ai ', ' Aiaa ', ' Ajhg ', ' Al ', ' An ', ' And ',
          ' and Then ', ' As ', ' At ', ' At ',
          ' Aus ', ' Av ', ' Bba ', ' Bbc ', ' Be ', ' Bei ', ' Bjpsych ', ' Bmc ', ' Bmj ', ' But ', ' By ', 
          ' Cbc ', ' Cns ', ' D\'un ', ' D\'une ', ' Da ', ' Dans ', 
          ' Das ', ' Dc ', ' De ', ' Dei ', ' Del ', ' Della ', ' Delle ', ' Dem ', ' Den ', ' Der ', 
          ' Des ', ' Di ', ' Die ', ' Dna ', ' Do ', ' Du ', ' E ', ' Ed ', ' Ein ', 
          ' Eine ', ' Einen ', ' El ', ' Else ', ' Embo ', ' En ', ' Et ', ' Faseb ', ' Febs ', 
          ' Fems ', ' For ', ' From ', ' För ', ' Für ', ' Ieee ', ' If ', ' Ilr ', ' In ', ' Into ', ' Is ', 
          ' Its ', ' Jama ', ' La ', ' Las ', ' Le ', ' Les ', ' Los ', ' Mit ', ' Mnras ', ' Mot ', ' Nasa ', ' Nejm ', ' Non ',
          ' Nor ', ' Nrc ', ' Ny ', ' Nyc ', ' Nyt ', ' Nz ', ' Och ', ' Oecd ', ' Of ', ' Off ', ' On ', ' Og ', ' Or ', 
          ' Over ', ' Pcr ', ' Per ', ' Pnas ', ' Ps: ', ' R&d ', ' Rna ', ' S&p ', ' Sae ', ' Ssrn ', ' The ', ' Then ', ' Till ', ' To ', ' Uk ', 
          ' Um ', ' Und ', ' Up ', ' Usa ', ' Van ', ' Von ', ' Voor ', ' When ', ' With ', ' Within ', ' Woor ', 
          ' Y ', ' Zu ', ' Zum ', ' Zur ', /* The above will be automatically updated to alphabetical order */ 

          // After this line we list exceptions that need re-capitalizing after they've been decapitalized.
          ' el Dorado ', ' las Vegas ', ' los Angeles ', ' N y ', ' U S a ');

          // For ones that start with lower-case, include both ELife and Elife versions in misspelled array
const JOURNAL_ACRONYMS = array(/* The following will be automatically updated to alphabetical order */
          ' AAPOS ', ' Angew Chem Int Ed ', ' Arch Dis Child Fetal Neonatal Ed ', ' ASME AES ', ' ASME MTD ', 
          ' B/gcvs ', ' BioEssays ', ' bioRxiv ', ' bioRxiv ',
          ' BJOG ', ' BMJ ', ' CBD Ubiquitin ', ' CFSK-DT ', ' CMAJ ', " dell'Accademia ", ' Dtsch ', ' Dtsch. ',
          ' e-Journal ', ' e-Journal ', ' e-Neuroforum ', ' e-Neuroforum ',
          ' e-Print ', ' e-Print ', ' e-Prints ', ' e-Prints ', ' Early Modern Japan: an Interdisciplinary Journal ',
          ' EFSA ', ' eJournal ', ' eJournal ', ' eLife ', ' eLife ', ' eLS ', ' eLS ', ' EMBO J ', 
          ' EMBO J. ', ' EMBO Journal ', ' EMBO Rep ', ' EMBO Rep. ', ' EMBO Reports ', ' eNeuro ', ' eNeuro ', 
          ' engrXiv ', ' ePlasty ',' ePlasty ',
          ' ePrint ', ' ePrint ', ' ePrints ', ' ePrints ', ' eScholarship ', ' eVolo ', ' eVolo ',
          ' FASEB J ', ' FASEB J. ', ' FEBS J ', ' FEBS J. ', ' FEBS Journal ', ' für anorganische und allgemeine ', ' HannahArendt.net ',
          ' History of Science; An Annual Review of Literature ', ' HOAJ biology ', ' hprints ', 
          ' iConference ', ' IFAC-PapersOnLine ', ' im Gesundheitswesen ', ' iPhone ', ' ISRN Genetics ',
          ' J Gerontol A Biol Sci Med Sci ',
          ' J SIAM ', ' J. SIAM ', ' JABS : Journal of Applied Biological Sciences ', 
          ' JAMA Psychiatry ', ' Journal of Materials Chemistry A ', ' Journal of the A.I.E.E. ',
          ' Journal of the IEST ', ' Jpn ', ' Jpn. ', ' Latina/o ', ' Ltd ', ' mAbs ', ' mAbs ', ' mBio ', ' mBio ',
          ' Meddelelser om Grønland ', ' Meddelelser om Grønland, ', ' MERIP ', ' Methods in Molecular Biology ',
          ' mHealth ', ' mHealth ', ' Molecular and Cellular Biology ', ' mSphere ', ' mSphere ', ' mSystems ', ' mSystems ', 
          ' n.paradoxa ', ' NASA Tech Briefs ', ' Ny Forskning i Grammatik ', ' Ocean Science Journal : OSJ ', ' of the AAS ',
          ' PAJ: A Journal of Performance and Art ', ' PALAIOS ', ' PeerJ ', ' PhytoKeys ', ' PLOS Biology ', 
          ' PLOS Medicine ', ' PLOS Neglected Tropical Diseases ', 
          ' PLOS ONE ', ' PLOS ONE ',' PLOS ONE ',' PLOS ONE ', ' PNAS ', ' Published in: ', ' RNA ', ' S.A.P.I.EN.S ',
          ' Srp Arh Celok Lek ', ' Star Trek: The Official Monthly Magazine ', 
          ' Tellus A ', ' The EMBO Journal ', ' Time Out London ', ' tot de ', ' U.S. ', ' U.S.A. ', ' U.S.A. ', ' uHealth ', ' uHealth ', ' v Astronomicheskii Zhurna ',
          ' z/Journal ', ' z/Journal ', ' Zeitschrift für Geologische Wissenschaften ', ' Zeitschrift für Physik A Hadrons and Nuclei ', 
          ' Zeitschrift für Physik A: Hadrons and Nuclei ', ' ZooKeys '
          /* The above will be automatically updated to alphabetical order */ 
);
const UCFIRST_JOURNAL_ACRONYMS = array(/* The following will be automatically updated to alphabetical order */
          ' Aapos ', ' Angew Chem Int ed ', ' Arch Dis Child Fetal Neonatal ed ', ' Asme Aes ', ' Asme MTD ',
          ' B/GCVS ', ' Bioessays ', ' Biorxiv ', ' BioRxiv ',
          ' Bjog ', ' Bmj ', ' Cbd Ubiquitin ', ' CFSK-Dt ', ' Cmaj ', " Dell'Accademia ", ' DTSCH ', ' DTSCH. ',
          ' E-journal ', ' E-Journal ', ' E-neuroforum ', ' E-Neuroforum ', 
          ' E-print ', ' E-Print ', ' E-prints ', ' E-Prints ', ' Early Modern Japan: An Interdisciplinary Journal ',
          ' Efsa ', ' Ejournal ', ' EJournal ', ' ELife ', ' Elife ', ' Els ', ' ELS ', ' Embo J ', 
          ' Embo J. ', ' Embo Journal ', ' Embo Rep ', ' Embo Rep. ', ' Embo Reports ', ' Eneuro ', ' ENeuro ',
          ' Engrxiv ', ' Eplasty ', ' EPlasty ',
          ' Eprint ', ' EPrint ', ' Eprints ',' EPrints ', ' Escholarship ', ' Evolo ', ' EVolo ', 
          ' Faseb J ', ' Faseb J. ', ' Febs J ', ' Febs J. ', ' Febs Journal ', ' Für Anorganische und Allgemeine ', ' Hannaharendt.net ',
          ' History of Science; an Annual Review of Literature ', ' Hoaj Biology ', ' Hprints ', 
          ' Iconference ', ' Ifac-Papersonline ', ' Im Gesundheitswesen ', ' Iphone ', ' Isrn Genetics ',
          ' J Gerontol a Biol Sci Med Sci ',
          ' J Siam ', ' J. Siam ', ' Jabs : Journal of Applied Biological Sciences ', 
          ' Jama Psychiatry ', ' Journal of Materials Chemistry A ', ' Journal of the A.i.i.e ', 
          ' Journal of the Iest ', ' JPN ', ' JPN. ', ' Latina/O ', ' LTD ', ' Mabs ', ' MAbs ', ' Mbio ', ' MBio ',
          ' Meddelelser Om Grønland ', ' Meddelelser Om Grønland, ', ' Merip ',  ' Methods in Molecular Biology (Clifton, N.j.) ',
          ' Mhealth ', ' MHealth ', ' Molecular and Cellular Biology ', ' Msphere ', ' MSphere ', ' Msystems ', ' MSystems ', 
          ' N.Paradoxa ', ' Nasa Tech Briefs ', ' NY Forskning I Grammatik ', ' Ocean Science Journal : Osj ', ' of the Aas ',
          ' Paj: A Journal of Performance and Art ', ' Palaios ', ' Peerj ', ' Phytokeys ', ' Plos Biology ', 
          ' Plos Medicine ', ' Plos Neglected Tropical Diseases ', 
          ' Plos One ', ' PloS One ', ' PLoS One ', ' PLOS One ', ' Pnas ', ' Published In: ' , ' Rna ', ' S.a.p.i.en.s ',
          ' SRP Arh Celok Lek ', ' Star Trek: The Official Monthly Magazine ', 
          ' Tellus a ', ' The Embo Journal ', ' Time Out London ', ' Tot de ', ' U.s. ', ' U.S.a. ', ' U.s.a ', ' Uhealth ', ' UHealth ', ' V Astronomicheskii Zhurna ',
          ' Z/journal ', ' Z/Journal ', ' Zeitschrift Für Geologische Wissenschaften ', ' Zeitschrift für Physik a Hadrons and Nuclei ', 
          ' Zeitschrift Für Physik a: Hadrons And Nuclei ', ' Zookeys '
          /* The above will be automatically updated to alphabetical order */ 
); 

const OBVIOUS_FOREIGN_WORDS = array(' Abhandlungen ', ' Actes ', ' Annales ', ' Archiv ', ' Archives de ',
           ' Archives du  ', ' Archives des ', ' Beiträge ', ' Berichten ', ' Blätter ', ' Bulletin de ',
           ' Bulletin des ', ' Bulletin du ', ' Cahiers ', ' canaria ', ' Carnets ', ' Comptes rendus ', ' Fachberichte ', ' Historia ',
           ' Jahrbuch ', ' Journal du ', ' Journal de ', ' Journal des ', ' Journal für ', ' Mitteilungen ',
           ' Monatshefte ', ' Monatsschrift ', ' Mémoires ', ' Notizblatt ', ' Recueil ', ' Revista ', ' Revue ', ' Travaux ',
           ' Studien ', ' Wochenblatt ', ' Wochenschrift ', ' Études ', ' Mélanges ', " l'École ", ' Française ');
