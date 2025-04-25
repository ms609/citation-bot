<?php

declare(strict_types=1);

const ITALICS_LIST =
    'Night of the Living Dead|' .
    'The Dream of the Rood|' .
    'Encyclopedia of Inorganic Chemistry|' .
    'Diagnostic and statistical manual|' .
    'Opus Caroli \(Libri Carolini\)|' .
    'The City Of God|' .
    'Miseducation of the Negro|' .
    'The Book of Illusions|' .
    'Capitalism, Socialism and Democracy|' .
    'Magnalia Christi Americana|' .
    'Sydney morning herald|' .
    'Chronicle\(s\) of Ioannina|' .
    'Homo sapiens sapiens|' .
    'Phaethornis longuemareus aethopyga|' .
    'Trypanosoma brucei gambiense|' .
    'Star Trek: Voyager|' .
    'Notechis scutatus scutatus|' .
    'Myrmecia reticulata \(Chlorophyta\)|' .
    'Fried Green Tomatoes|' .
    'Game of Thrones|' .
    'The Double-Cross System|' .
    'Catalogue of Women|' .
    'Biomphalaria sudanica sudanic|' .
    'Tyrannotitan chubutensis|' .
    'Escherichia coli|' .
    'Bugulasensu lato|' .
    'Massospondylus carinatus|' .
    'Adenanthera pavonina|' .
    'Burkholderia pseudomallei|' .
    'Helicobacter pylori|' .
    'Drosophila silvestris|' .
    'Luzula nivea|' .
    'Myrmecia reticulata|' .
    'Aedes koreicus|' .
    'Aedes japonicus|' .
    'Ustilago maydis|' .
    'Plasmodium falciparum|' .
    'Agaricus blazei|' .
    'Fusarium venenatum|' .
    'Aspergillus nidulans|' .
    'Trichoderma pseudokoningii|' .
    'Vitis Vinifera|' .
    'Mycoplasma pneumoniaein|' .
    'Inonotus radiatus|' .
    'Plasmodium berghei|' .
    'Dunkleosteus terrelli|' .
    'In Vitro|' .
    'Australopithecus afarensis|' .
    'Tabula Rasa|' .
    'Zoanthus sociathus|' .
    'Seize Mai|' .
    'Schistosoma mansoni|' .
    'Platydemus manokwari|' .
    'Melanodrymia aurantiaca|' .
    'Paramphislomum cervi|' .
    'Bacillus pumilus|' .
    'Citipati Osmolskae|' .
    'Sui Generis|' .
    'Betta splendens|' .
    'Mesonauta acora|' .
    'Entobdella Soleae|' .
    'Pterophyllum scalare|' .
    'Communist Manifesto|' .
    'Bacillus thuringiensis|' .
    'Magnosaurus nethercombensis|' .
    'Star Trek|' .
    'Smilodon fatalis|' .
    'Eucoleus aerophilus|' .
    'Alligator mississippiensis|' .
    'Palaenigma wrangeli|' .
    'Meringosphaera mediterranea|' .
    'Candida albicansas|' .
    'Candida albicans|' .
    'Tyrannosaurus rex|' .
    'Dolichorhynchops bonneri|' .
    'Staphylococcus aureus|' .
    'Pericope Adulterae|' .
    'Ranunculus auricomus|' .
    'Aspergillus fumigatus|' .
    'Tachycineta bicolor|' .
    'Entamoeba histolytica|' .
    'Fusarium pseudograminearum|' .
    'Caenorhabditis elegans|' .
    'Serratia marcescens|' .
    'Bicyclus anynana|' .
    'Argiope argentata|' .
    'Saccharomyces boulardiiin|' .
    'Bacillus anthracis|' .
    'Bargmannia elongata|' .
    'Phialella zappai|' .
    'Onychiurus fimats|' .
    'Phialella fragilis|' .
    'Skeletonema costatum|' .
    'Salmonella typhimurium|' .
    'Polycotylus latipinnis|' .
    'Coprosma lucida|' .
    'Mycobacterium tuberculosis|' .
    'Ariolimax californicus|' .
    'Streptococcus pneumoniae|' .
    'In Vivo|' .
    'Tereingaornis moisleyi|' .
    'A\. dolichophallus|' .
    'Necrodes littoralis|' .
    'Stachybotrys chartarum|' .
    'Ras Lilas|' .
    'Yarrowia lipolytica|' .
    'Stichodactyla helianthusas|' .
    'Schistocephalus solidus|' .
    'Pyrobaculum calidifontis|' .
    'Arabidopsis thaliana|' .
    'Fahraeusodus adentatus|' .
    'Homo sapiens|' .
    'Nanocthulhu lovecrafti|' .
    'Xylella fastidiosa|' .
    'Listeria monocytogenes|' .
    'Halszkaraptor escuilliei|' .
    'Mycoplasma pneumoniae|' .
    'Paradisaea Raggiana|' .
    'Rothia mucilaginosa|' .
    'Mitragyna speciosain|' .
    'Brachiosaurus altithorax|' .
    'Saccharomyces cerevisiae|' .
    'Plutella xylostella|' .
    'Bromus laevipes|' .
    'Trypanosoma brucei|' .
    'Hulsanpes perlei|' .
    'Buitreraptor gonzalezorum|' .
    'Bellusaurus sui|' .
    'Ganoderma lucidum|' .
    'Ganoderma tsugae|' .
    'Sinovenator changii|' .
    'Thermomonospora fusca|' .
    'Diplocynodon levantinicum|' .
    'Nedcolbertia justinhofmanni|' .
    'Clostridium botulinum|' .
    'Bactrocera dorsalis|' .
    'Umm al-Kitāb|' .
    'Acrocomia mexicana|' .
    'Brachylophosaurus canadensis|' .
    'Agaricus hondensis|' .
    'Campylobacter jejuni|' .
    'Actinomyces bovis|' .
    'Diplodocus carnegii|' .
    'Balaur bondoc|' .
    'Screbinodus ornatus|' .
    'Rg Veda|' .
    'Protogoniomorpha anacardii|' .
    'Homo erectus|' .
    'Piveteauia madagascariensis|' .
    'Aspergillus terreus|' .
    'Ignicoccus hospitalis|' .
    'Watsonulus eugnathoides|' .
    'Cardiodectes bellottii|' .
    'Opus Caroli|' .
    'Libri Carolini|' .
    'Dibamus taylori|' .
    'Nanoarchaeum equitans|' .
    'Les Noces|' .
    'Batillipes mirusand|' .
    'Batillipes noerrevangi|' .
    'Robustichthys luopingensis|' .
    'Uroplectes ansiedippenaarae|' .
    'Perccottus glenii|' .
    'Tornieria africana|' .
    'Ilex asprella|' .
    'Boluochia zhengi|' .
    'Geochen rhuax|' .
    'Gyps melitensis|' .
    'Titanis walleri|' .
    'Physcomitrella patens|' .
    'Carcharhinus brachyurus|' .
    'C\. obscurus|' .
    'Sphyrna zygaena|' .
    'China Daily|' .
    'Tabula Peutingeriana|' .
    'Diamond Sutra|' .
    'Gasterophilus pecorum|' .
    'Oxycarenus laetus|' .
    'Flemingia macrophylla|' .
    'Suuwassea emilieae|' .
    'Endoxocrinus parrae|' .
    'Ganoderma applanatum|' .
    'Burkholderia mallei|' .
    'Prionailurus viverrinus|' .
    'Homo habilis|' .
    'Glycine tomentella|' .
    'Culcita schmideliana|' .
    'Squilla armata|' .
    'Saurosuchus galilei|' .
    'Lathamus discolor|' .
    'Gymondinium pseudopalustre|' .
    'Woloszynskia apiculata|' .
    'Symbiodinium microadriaticum|' .
    'Alligator sinensis|' .
    'Lesothosaurus diagnosticus|' .
    'Galeamopus pabsti|' .
    'Enfants Terribles|' .
    'Puntius sophore|' .
    'Murraya koenigii|' .
    'Pseudomonas aeruginosa|' .
    'Cherax destructor|' .
    'Cocos nucifera|' .
    'Chironomus riparius|' .
    'Acathamoeba castellanii|' .
    'Ainiktozoon loganense|' .
    'Panthera palaeosinensis|' .
    'Paraponera clavata|' .
    'Lycium barbarum|' .
    'Poria cocos|' .
    'Emiliania huxleyi|' .
    'Res Publica|' .
    'Mimulus peregrinus|' .
    'Mixosaurus nordenskioeldii|' .
    'Streptococcus agalactiae|' .
    'Mycoplasma genitalium|' .
    'Notorynchus cepedianus|' .
    'Chauliodus macouni|' .
    'de Novo|' .
    'Clevosaurus latidens|' .
    'Cymbospondylus buchseri|' .
    'Klebsiella oxytoca|' .
    'Borrelia burgdorferi|' .
    'Besanosaurus leptorhynchus|' .
    'Drosophila melanogaster|' .
    'Praemegaceros portis|' .
    'Mussaurus patagonicus|' .
    'Ciona intestinalis|' .
    'Placobdelloides siamensis|' .
    'Leptanilla japonica|' .
    'Haematopus ostralegus|' .
    'Macoma balthica|' .
    'Boscia angustifolia|' .
    'Cephalotes atratus|' .
    'Angelica sinensis|' .
    'Dilophosaurus wetherilli|' .
    'Scutarx deltatylus|' .
    'Vernonia amygdalina|' .
    'Papilio memnon|' .
    'Loa loa|' .
    'Ixodes scapularis|' .
    'Bishara backa|' .
    'Shipingia luchangensis|' .
    'Eosuchus lerichei|' .
    'Homo nalediin|' .
    'Homo naledi|' .
    'Kallima inachus|' .
    'K\. inachus|' .
    'Schesslitziella haupti|' .
    'in vivo|' .
    'Suevoleviathan integer|' .
    'Pityogenes chalcographus|' .
    'Ips typographus|' .
    'Hauffiosaurus zanoni|' .
    'Argynnis hyperbius|' .
    'Streptococcus diacetilactis|' .
    'Panax ginseng|' .
    'Leuconostoc citrovorum|' .
    'Lynx pardinus|' .
    'Streptopelia senegalensis|' .
    'Oenococcus oeni|' .
    'Rhamphorhynchus|' .
    'Buchnera|' .
    'Praemegaceros|' .
    'Pristionchus|' .
    'Arabidopsis|' .
    'Realpolitik|' .
    'Paratirolites|' .
    'Arianops|' .
    'Ottoia|' .
    'Onychites|' .
    'Kallima|' .
    'Leptanilla|' .
    'Loxosceles|' .
    'Diplocynodon|' .
    'Leveillula|' .
    'Lewisepeira|' .
    'Daspletosaurus|' .
    'Pohlerodus|' .
    'Diaphera|' .
    'Hyloscirtus|' .
    'Argynnis|' .
    'Micrapis|' .
    'Pseudomonas|' .
    'Baryonyx|' .
    'Athrips|' .
    'Morpho|' .
    'Monacha|' .
    'Plesiosorex|' .
    'Amazona|' .
    'Malassezia|' .
    'Bushiellas|' .
    'Godartiana|' .
    'Hapalotremus|' .
    'Orcus|' .
    'Phialella|' .
    'Schistocephalus|' .
    'Philometra|' .
    'Armillaria|' .
    'Neurospora|' .
    'Symphysodon|' .
    'Glyptonotus|' .
    'Beggiatoa|' .
    'Ascomycota|' .
    'Puffinus|' .
    'Mycena|' .
    'Aspergillus|' .
    'Ricinus|' .
    'Grammia|' .
    'Apatosaurus|' .
    'Candidatus|' .
    'Phytophthora|' .
    'Chrysops|' .
    'Lophiomeryx|' .
    'Bachitherium|' .
    'Anoplotherium|' .
    'Saltatorellota|' .
    'Acanthodes|' .
    'Adenanthos|' .
    'Thraustochytrium|' .
    'Rhaponticum|' .
    'Luzula|' .
    'Arthropleura|' .
    'Paraprefica|' .
    'Leuzea|' .
    'Ureaplasma|' .
    'Euschistus|' .
    'Brachygastra|' .
    'Gaojiashania|' .
    'Boreogomphodon|' .
    'Noideattella|' .
    'Tolegnaro|' .
    'Agnathus|' .
    'Strophodus|' .
    'Cannabis|' .
    'Drosophila|' .
    'Duyfken|' .
    'Polyptychodon|' .
    'Haratin|' .
    'Limulus|' .
    'Eschata|' .
    'Dianthus|' .
    'Arthoniais|' .
    'Arthropterygius|' .
    'Acacia|' .
    'Cerastes|' .
    'Berardiusin|' .
    'Ilustrado|' .
    'Keśin|' .
    'Synorichthys|' .
    'Clusia|' .
    'Sinamia|' .
    'Cambaytherium|' .
    'Ignicoccus|' .
    'Hencke|' .
    'Batillipes|' .
    'Chenopodium|' .
    'Diplodocus|' .
    'Hipparchs|' .
    'Lepidobatrachus|' .
    'Uroplectes|' .
    'Euroscaptor|' .
    'Montifringilla|' .
    'Leucosticte|' .
    'Aglyptorhynchus|' .
    'Phidiana|' .
    'Egertonia|' .
    'Dapedium|' .
    'Pycnodus|' .
    'Metaceratodus|' .
    'Erwinia|' .
    'Lactobacillus|' .
    'Candidain|' .
    'Candida|' .
    'Lasalichthys|' .
    'Dicynodon|' .
    'Iudaea-Palestina|' .
    'Cymbeline|' .
    'Gordonia|' .
    'Symbiodinium|' .
    'Dhammapada|' .
    'Xenopusembryo|' .
    'Chlamydomonas|' .
    'Anaplecta|' .
    'Arumberia|' .
    'Ridda|' .
    'Dictyochloropsis|' .
    'Laccognathus|' .
    'Stereum|' .
    'Karma|' .
    'Cena|' .
    'Satyricon|' .
    'Nakbain|' .
    'Nakba|' .
    'Saurichthys|' .
    'Analects|' .
    'Leishmania|' .
    'tryA|' .
    'Rangifer|' .
    'Graphidaceae|' .
    'Bacillus|' .
    'Pennellidae|' .
    'Manuherikia|' .
    'Acidorhynchus|' .
    'Echmatemys|' .
    'Plesiocathartes|' .
    'Progymnasmata|' .
    'Rhizopus|' .
    'Stenopterygius|' .
    'Phymatoderma|' .
    'Allopanax|' .
    'Boreopanax|' .
    'Accipiter|' .
    'Santacruzodon|' .
    'Escherichia|' .
    'END_OF_CITE_list_junk';
//  All real ones need pipe on end
//  YOU MUST ESCAPE (.|) and other FUNNY Characters

const CAMEL_CASE = [
    'DeSoto', 'PubChem', 'BitTorrent', 'Al2O3', 'NiMo', 'CuZn',
    'BxCyNz', 'ChemCam', 'StatsRef', 'BuzzFeed', 'DeBenedetti', 'DeVries',
    'TallyHo', 'JngJ', 'ENaCs', 'MensRights', 'McCarthy', 'AmpliSeq',
    'nRepeat', 'OpenStreetMap', 'DonThorsen', 'arXiv', 'eBay', 'aRMadillo',
    'HowNutsAreTheDutch', 'Liberalism', 'HoeGekIsNL', 'iMac', 'iPhone',
    'iPad', 'iTunes', 'FreeFab', 'HeartMath', 'MeToo', 'SysCon', 'DiMarco',
    ' Mc', ' Mac', 'DeepMind', 'BabySeq', 'ClinVar', 'UCbase', 'miRfunc',
    'GeneMatcher', 'TimeLapse', 'CapStarr', ' SpyTag', 'SpyCatcher',
    'SpyBank', 'TaqMan', 'PhyreRisk', 'piggyBac', 'HapMap', 'MiSeq',
    'QualComp', 'PastCast', 'InvAluable', 'NgAgo', ' MitoZoa',
    'InterMitoBase', 'LaserTank', 'GeneBase', 'DesignSignatures', 'HeLa',
    'QuadBase', 'GenBank', 'PowerPlex', 'ExInt', 'TissueInfo', 'HeliScope',
    'ConDeTri', 'HIrisPlex', 'CpGIMethPred', 'Quantum Dots', 'TopHat',
    'WikiProject', 'RefSeq', 'geneCo', 'SpringerReference', 'aMeta', 'ChIP',
    'OligArch', 'PyDamage', 'SayHerName', 'pDecays', 'BioMaterialia',
    'FlexMed', 'GaTate', 'iCloud', 'iPod', 'CamelCase', 'DryIce',
    'CinemaScope', 'AstroTurf', 'QuarkXPress', 'FedEx', 'YouTube',
    'PlayStation', 'NeXT', 'InterCaps', 'CorpoNym', 'ExxonMobil',
    'HarperCollins', 'ConAgra', 'BumpyCaps', 'BumpyCase', 'NerdCaps',
    'CapWords', 'compoundNames', 'HumpintheMiddle', 'HumpBack', 'InterCap',
    'mixedCase', 'WikiWord', 'WikiCase', 'ProperCase', 'StUdLyCaPs',
    'MasterCraft', 'MasterCard', 'SportsCenter', 'CompuServe', 'WordStar',
    'VisiCalc', 'WordPerfect', 'NetWare', 'LaserJet', 'MacWorks',
    'PostScript', 'PageMaker', 'ClarisWorks', 'HyperCard', 'PowerPoint',
    'WorldWideWeb', 'EchoStar', 'BellSouth', 'EastEnders', 'SpaceCamp',
    'SeaTac', 'PricewaterhouseCoopers', 'DataLab', 'DuBois', 'LivesMatter',
    'ScaFi', '(Mc', '(Mac', 'BioShock', 'BreadTube', 'PewDiePie', 'LepVax',
    'CrossMab', 'GyneFix', 'ProsPeCts', 'sCIentIfIC', 'PsyCholoGy',
    'DiGeorge', 'AstraZeneca', 'SemCluster', 'VirtuReal', '-Mc', '-Mac',
    'NexGard', 'AmBisome', 'AmbiOnp', 'SnapShot', 'LentiGlobin',
    'SmartTutor', 'TarBase', 'miRecords', 'miRTarBase', 'deepBase',
    'DeepImageTranslator', 'TikTok', ' pKa', ' MurA', 'pKa ', 'MurA ',
    'IgDhi', ' bKa’ ', '117mSn', 'ClinicalTrials', 'PtCo', 'ResearchGate',
    'TimeTree', 'PhysiCell', 'VirtualLeaf', 'PhenoScanner', 'GeoSteiner',
    'eCommerce', 'FrameNet', 'DeLury', 'GeGaLo', 'LeukArrest', 'IceCube',
    'NeuroImages', 'UppSten', 'AngloMania', 'HiRes', 'PolyCystic', 'é',
    'AlmaToo', 'CubeSat', 'DeArmitt', 'ProQuest', 'SemNet', 'ī',
    'LaundroGraph', 'ZoKrates', 'xJsnark', 'ç', 'GitHub', 'GitLab',
    'iThings', 'FuzzBench', 'TinyLock', 'EvoPass', 'eGovernment',
    'TinyGarble', 'PhagoBurn', 'KwaZulu', 'BioNetGen', 'DataBase',
    'GeneChip', 'TransRate', 'StringTie', 'featureCounts', 'DiffSplice',
    'QuantSeq', 'WebGestalt', 'flowCore', 'flowClust', 'MetaGenomic',
    'TlInGe', 'RapidArc', 'SmartArc', 'TomoHD', 'ViralZone', 'HyPhy',
    'MrBayes', 'SunTag', 'InterPro', 'SmProt', 'ChikDenMaZika', 'LitCovid',
    'GeneTree', 'GenAge', 'QnAs', 'BiDil', 'iAge', 'DevSec', 'SecOps',
    'DevOps', 'LeafCutter', 'CyBase','OxPhos', 'ArrayExpress',
    'BepiColombo', 'RuleMonkey', 'OxyCo', 'CdZnTe', 'EnChroma', 'FibroTest',
    'ActiTest', 'FloTrac', 'FibroScan', 'ColorBrewer', 'StagLab',
    'EveryManc', 'GaCl', 'DeepFace', 'WeChat', 'kDa ', 'Tg-AD', 'mHealth',
    'DomainKeys', 'mTc', 'SiCf', 'SiC', 'RoboCup', '-kDa', 'DrugBank',
    'MnSe', 'ZnTe', 'GaMnAs', 'MnxSb', 'InSb', 'CovidSim', 'xPharm',
    'PubMed', 'MedlineRanker', 'MiSearch', 'pubMed', 'MedEvi',
    'CytoJournal', 'NiAl', 'CaSe', 'SrSe', 'BaSe', 'EuSe',
    'MalariaControl.net', 'scFv', 'WikiLeaks', 'SysBio', 'SciFinder',
    'ClO4', 'baseMjondolo', 'eOceans', 'InSight', 'ActEarly', '23andMe',
    'CatScan', 'SpaceHort', 'NiAs', 'WhatsApp', 'HualcaHualca', ' Neo',
    'AdvocatingFor', ' #', 'LinkedIn', 'CdTe', 'GaAs', 'CuInGa',
    'D28kExhibit', 'ThePleasantvilleEffect', 'ImmGen', 'GeV', 'KrCl',
    'LiNi', 'DuBourg', 'MetaAnalysis', 'MoCx', 'MoPx', 'MoNx', 'PedCheck',
    'ImagiNation', 'HemOnc', 'nJunctions', 'SkyMed', 'InterNyet',
    'BattleZone', 'NoFap', 'CyberSightings', 'kHz', 'AngQb', 'QuickStats',
    'iDisorders', 'PremAir', '®', 'eCrime', 'AgInjuryNews', 'DreamWorks',
    'SpaceOps', 'DeMille', 'superVolcanoes', 'SuperVolcanoes', 'HotSpots',
    'SmartCity', 'RadarConf', 'PuneCon', 'LatinCloud', 'CloudNet',
    'FinTech', 'PowerTech', 'SecDev', 'CodeSonar', 'eScience', 'BioWatch',
    'IconSpace', 'HotWeb', 'SmartGrid', 'SmartNets', 'PiCom', 'CBDCom',
    'CyberSciTech', 'CyberTech', 'SciTech', 'BioRob', 'LexisNexis',
    'PlatCon', 'BigData', 'MobileCloud', 'BioInformatics', 'BioEngineering',
    'NetSoft', 'ReConFig', 'FPGAs', 'ReConFigurable', 'NetCod', 'PerCom',
    'PowerAfrica', 'PacificVis', 'BigComp', 'RoboSoft', 'PerAc',
    'QuickCast', 'QuickSort', 'EIConRus', 'CSCloud', 'EdgeCom', 'eBanking',
    'GeoConference', 'eConference', 'ConOps', 'EuroHaptics', 'WiMob',
    'WeRob', 'MultiTemp', 'MediVis', 'BioMedical', 'BlackSeaCom',
    'SecureCom', 'RobMech', 'PhysComp', 'IntelliSys', 'EnergyTech',
    'Conference on ElectroMagnetic Interference', 'SoutheastCon 2', 'CyCon',
    'DiffServ', 'ReSerVation', 'SysTol', 'IEEE SoutheastCon', 'eMagRes',
    'eSpace', 'ElectroChemical', 'SystemVerilog', 'MobiQuitous', 'MobiCom',
    'AlterEgo', 'DeathQuest', 'kVp', 'MeV', 'MVp', 'StarGuides ',
    'FabLearn', 'CompSysTech', 'MindTrek', 'MpoxRadar', 'eXperiences',
    'iConference', 'Digital EcoSystems', 'RoMoCo', 'LaRouche', 'eXtensible',
    'GlobalArctic Handbook', 'PlantOmics', 'SusTech', 'BrainNet',
    'AntiSuffrage', 'GeoTechnik', 'EcoComfort', 'SignGram', 'eLearning',
    'OneApp', 'DownUnder', 'CardioMed', 'NeuroImaging', 'NiTi', 'EuroSys',
    'GeoComputation', 'FactsBook', 'UbiComp', 'vCard', 'EuroQol',
    'CentroidAlign', 'CyloFold', 'FindNonCoding', 'GraphClust', 'miReader',
    'MiRror', 'PseudoViewer', 'RNAconTest', 'RNAiFold', 'ScanFold',
    'SimulFold', 'TurboFold', 'TurboKnot', 'FusionSeq', 'dRheb', 'GTPase',
    'NanoBiosensing', 'eHealth', 'iNet', 'NetGames', 'MultiMedia', 'PetaOp',
    'AppleScript', 'GeNeDis', 'MeDoc', 'eEnvironment', 'AlphaFold',
    'BirdLife', 'MetroLink', 'PhyloCode', 'JavaScript', 'AfricArXiv',
    'MoveOn', 'iSemantic', 'DigitalHeritage', 'iBroadway', 'TechSym',
    'HistoCrypt', 'AaTh', 'ProComm', 'DeSmet', 'HfMoTaTiZr', 'HfMoNbTaTiZr',
    'CoCrFeMnNi', 'alloy film', 'ProtSweep', '2Dsweep', 'DomainSweep',
    'McLaughlin', 'WikiPathways', 'MediaArtHistories', 'SunLine',
    'SnakeChunks', 'LeGuin', 'LaCour', 'GetOrganelle', 'eInclusion',
    'SuperCollider', 'CatSper', 'VectorBase', 'eRegistries', 'CrossFit',
    'IgMs', 'eLife', 'RxNorm', 'QuickTime', 'cDNA', 'PharmVar', 'GeneFocus',
    'AgBioData', 'AuthorReward', 'PomBase', 'WikiProteins', 'ezTag',
    'FlyBase', 'PubTator', 'RiceWiki', 'SciLite', 'ScispaCy', 'WormBase',
    'VirFinder', 'UniProt', 'GaiaData', 'FoodOn', 'GaiaHundred',
    'WikiJournal', 'StarCraft', 'EuResist', 'PhageScope', 'CoVaMa',
    'EuroMyositis', 'DavEnd', 'CoLaus', 'MinerAlocorticoid', 'miRiad',
    'kaSenzangakhona', 'BioChirality', 'DeGeer', 'ReFocus:',
    'FusionCatcher', 'cFos', 'ReOrienting', 'NatureServe', 'qNirvana',
    'DenitrificationAnammox', 'NomCom', 'ReScript', 'NanoBiotechnology',
    'PhotonIcs', 'NeuroPsychopharmacotherapy', 'eFieldnotes', 'rNying-ma',
    'MiniVess', 'GlobeCom', 'ASHRAE GreenGuide', 'BioIndustry', 'FeCo',
    'ProCite', 'Keypoint-MoSeq', 'DaEng', 'ChemInform', 'eBook',
    'MSqRob', 'dsCheck', 'PhyloWidget', 'SourceForge', 'TreeGraph',
    'JEvTrace', 'mProphet', 'siDirect', 'BioRxiv', 'InfoStation',
    'MicroFinance', 'DeFi', 'JetBlue', 'PacBio', 'MetroLivEnv', 'WorldMinds',
    'SmartCloud', 'DiCaprio', 'LaNada', 'BioCassava', 'ResFinder',
    'HyperRhetoric', 'HflXr', 'SteamBioAfrica', 'MindSpore', 'MathJax',
    'poreCov', 'PyArmadillo', 'QuasiFlow', 'RustBelt', 'AutoDock', 'TractoFlow',
    'libRoadRunner', 'pyJac', 'myGrid', 'Cp2TiCl', 'myExperiment',
    'CicArVarDB', 'InDel database', 'BioMoby', 'maxdBrowse', 'GazePlotter',
    'ScanGraph', 'MusicBrainz', 'WontBe', 'InChIs', 'PolyChord', 'CloudCom',
];

const ITALICS_HARDCODE_IN  = [
    'The Myth ofPiers Plowman',
    'BioelectromagnetismPrinciples and Applications',
    'Practice inThe Nice Valour',
    'Tweeting theJihad: Social Media',
    ' of SectarianismCommunity',
    ' for WonderNineteenth Century Travel ',
    ' from the ShadowsAnalytical Feminist Contributions',
    ' der GattungEoeugnathus Brough',
    ' the CitizenMusic as Public ',
    ' to the SirensMusical Technologies ',
    ' the DynastyPalace Women ',
    'Divine CreaturesAnimal Mummies',
    'Small Greek WorldNetworks in',
    'Magia SexualisSex, Magic',
    ' is NothingLost in ',
    ' Without UsDisability ',
    'Human RightsThe Case ',
    ' Southeast AsiaNation and ',
    ' EcologicalAn Expedition ',
    'tive CognitionExperimental Expl',
    'Immigrant NarrativesOrientalism and Cultural',
    'Caesar\'s CalendarAncient Time ',
    ' a Sure StartHow gov',
    'Slum TravelersLadies and London',
    'Modern MongoliaFrom Khans',
    ' Magia SexualisSex, Magic',
    'in Montesquieu’sThe Spirit',
    'antiquity of Francis Bacon\'sNew Atlantis',
    'New MathematicsWestern Learning',
    'Poor PeopleResource Control ',
    ' ObligationsRoman Foundations ',
    ' SirensMusical Technologies   ',
    'Two RomesRome and ',
    'TantraSex, Secrecy',
    'in EducationThe good',
    'Eternal GodHeavenly Flesh',
    'Conference onResearch, Innovation',
    'Roman EmpireSoldiers,',
    'SilencedHow Apostasy and Blasphemy',
    'Cybernetics SocietyInformation Assurance',
    'International Conference onServices Computing',
    'IEEE InternationalElectron Devices',
    'Symposium onFoundations of',
    'IEEE InternationalConference',
    'Jewish QuestionIdentifying ',
    'The Port NelsonRelations',
    'Changes for DemocracyActors',
    'SpymasterDai Li ',
    'A New History of IrelandVolume',
    'Devoted to DeathSanta Muerte, the Skeleton Saint',
    'Knowing HowEssays on Knowledge, Mind, and Action',
    ' Rgene',
    'CytochromebGenes',
    'ElectrongValue',
    'thetoxGene',
    'GopashtamiandGovardhan',
    'PolishLebensraum',
    'theNachlassproblem',
    ' forAltalena:',
    ' in Plutarch\'sLives',
    'FromSolidarityto',
    ' gp91phoxPromoter',
    'in vitroAssays',
    'MarketizingHindutva',
    'TheBhagavadgītā,',
    'theOrigin of Species',
    'EncounteringHindutva',
    'ChineseHukouSystem',
    'CisLatreille',
    'the 21stCentury',
    ' JonesSir William',
    'ofClinical Microbiology',
    'Rallying theQaum:The',
    ' of theHaratinin Morocco',
    'future ofBorn in Flames',
    'ideology ofMakwerekwerein South Africa',
    'TheWhite Paperand the Making',
    'provocations ofThe Female Eunuch',
    'Moving beyondPvalues:',
    'from theHubble Space TelescopeKey',
    'Evidence ofpepSolar Neutrinos',
    'Notes forContra Ursum',
    'sThe Social Construction of Realityafter 50',
    'Love inShôjo Manga',
    'the re-reducedHipparcosintermediate ',
    'Godly AmbitionJohn Stott',
    'Becoming ChinesePassages to Modernity and Beyond',
    'Crusading PeaceChristendom, the',
    'Tourism in Danny Boyle\'sSlumdog Millionaire',
    'Study OfDagongmei: A Feminist',
    'on the National Capital RegionPlan for the Year 2000',
    'the Binary Dynamics ofRomeo and Juliet',
    'llegada de los IncasAproximaciones desde',
    'Biomedical LawLegal and Extra',
    'Homo erectusPleistocene Evidence from ',
    'Technical Issues andFuture Directions',
    'on EarthJoseph Smith',
    'Brain and MemoryModulation and ',
    'Qusayr AmraArt and the',
    ' ofDrosophilamotor circuits',
    'Understanding GenocideThe Social',
    'New Supreme CourtNational and',
    'the Limits of EmpireOpium and',
    'Roman EmpireVolume I: Maximilian',
    'History DerailedCentral and Eastern ',
    ' Last CampaignBritain and the ',
    ' and ConditionalsNew and Revised ',
    'Programmed Cell Death,General Principles forStudying Cell Death',
    'Thriving in AmericaCapitalism and',
    ' Social WorkHuman Rights',
];

const ITALICS_HARDCODE_OUT = [
    'The Myth of \'\'Piers Plowman\'\'',
    'Bioelectromagnetism: Principles and Applications',
    'Practice in \'\'The Nice Valour\'\'',
    'Tweeting the \'\'Jihad\'\': Social Media',
    ' of Sectarianism: Community',
    ' for Wonder: Nineteenth Century Travel ',
    ' from the Shadows: Analytical Feminist Contributions',
    ' der Gattung: Eoeugnathus Brough',
    ' the Citizen: Music as Public ',
    ' to the Sirens: Musical Technologies ',
    ' the Dynasty: Palace Women ',
    'Divine Creatures: Animal Mummies',
    'Small Greek World: Networks in',
    'Magia Sexualis: Sex, Magic',
    ' is Nothing: Lost in ',
    ' Without Us: Disability ',
    'Human Rights: The Case ',
    ' Southeast Asia: Nation and ',
    ' Ecological: An Expedition ',
    'tive Cognition: Experimental Expl',
    'Immigrant Narratives: Orientalism and Cultural',
    'Caesar\'s Calendar: Ancient Time ',
    ' a Sure Start: How gov',
    'Slum Travelers: Ladies and London',
    'Modern Mongolia: From Khans',
    ' Magia Sexualis: Sex, Magic',
    'in Montesquieu’s: The Spirit',
    'antiquity of Francis Bacon\'s: New Atlantis',
    'New Mathematics: Western Learning',
    'Poor People: Resource Control ',
    ' Obligations: Roman Foundations ',
    ' Sirens: Musical Technologies ',
    'Two Romes: Rome and ',
    'Tantra: Sex, Secrecy',
    'in Education: The good',
    'Eternal God: Heavenly Flesh',
    'Conference on Research, Innovation',
    'Roman Empire: Soldiers,',
    'Silenced: How Apostasy and Blasphemy',
    'Cybernetics Society Information Assurance',
    'International Conference on Services Computing',
    'IEEE International Electron Devices',
    'Symposium on Foundations of',
    'IEEE International Conference',
    'Jewish Question: Identifying ',
    'The Port Nelson \'\'Relations\'\'',
    'Changes for Democracy: Actors',
    'Spymaster: Dai Li ',
    'A New History of Ireland, Volume',
    'Devoted to Death: Santa Muerte, the Skeleton Saint',
    'Knowing How: Essays on Knowledge, Mind, and Action',
    ' \'\'R\'\' gene',
    'Cytochrome \'\'b\'\' Genes',
    'Electron \'\'g\'\' Value',
    'the \'\'tox\'\' Gene',
    'Gopashtami \'\'and\'\' Govardhan',
    'Polish \'\'Lebensraum\'\' ',
    'the \'\'Nachlass\'\' problem ',
    ' for \'\'Altalena\'\':',
    ' in Plutarch\'s \'\'Lives\'\' ',
    'From \'\'Solidarity\'\' to ',
    ' gp91phox Promoter',
    ' \'\'in vitro\'\' Assays ',
    'Marketizing \'\'Hindutva\'\' ',
    'The \'\'Bhagavadgītā\'\', ',
    'the \'\'Origin of Species\'\' ',
    'Encountering \'\'Hindutva\'\' ',
    'Chinese \'\'Hukou\'\' System ',
    '\'\'Cis\'\' Latreille',
    'the 21st Century',
    ' Jones: Sir William',
    'of Clinical Microbiology',
    'Rallying the \'\'Qaum\'\': The',
    ' of the \'\'Haratin\'\' in Morocco',
    'future of \'\'Born in Flames\'\'',
    'ideology of \'\'Makwerekwere\'\' in South Africa',
    'The \'\'White Paper\'\' and the Making',
    'provocations of \'\'The Female Eunuch\'\'',
    'Moving beyond \'\'P\'\' values:',
    'from the \'\'Hubble Space Telescope\'\' Key',
    'Evidence of \'\'pep\'\' Solar Neutrinos',
    'Notes for \'\'Contra Ursum\'\'',
    's \'\'The Social Construction of Reality\'\' after 50',
    'Love in \'\'Shôjo Manga\'\'',
    'the re-reduced \'\'Hipparcos\'\' intermediate ',
    'Godly Ambition: John Stott',
    'Becoming Chinese: Passages to Modernity and Beyond',
    'Crusading Peace: Christendom, the',
    'Tourism in Danny Boyle\'s \'\'Slumdog Millionaire\'\'',
    'Study Of \'\'Dagongmei\'\': A Feminist',
    'on the National Capital Region \'\'Plan for the Year 2000\'\'',
    'the Binary Dynamics of \'\'Romeo and Juliet\'\'',
    'llegada de los Incas. Aproximaciones desde',
    'Biomedical Law: Legal and Extra',
    'Homo erectus: Pleistocene Evidence from ',
    'Technical Issues and Future Directions',
    'on Earth: Joseph Smith',
    'Brain and Memory: Modulation and ',
    'Qusayr Amra: Art and the',
    ' of \'\'Drosophila\'\' motor circuits',
    'Understanding Genocide: The Social',
    'New Supreme Court: National and',
    'the Limits of Empire: Opium and',
    'Roman Empire: Volume I: Maximilian',
    'History Derailed: Central and Eastern ',
    ' Last Campaign: Britain and the ',
    ' and Conditionals: New and Revised ',
    'Programmed Cell Death, General Principles for Studying Cell Death',
    'Thriving in America: Capitalism and',
    ' Social Work: Human Rights',
];
