<?php
declare(strict_types=1);

const ITALICS_LIST =
 "Encyclopedia of Inorganic Chemistry|" .
 "Diagnostic and statistical manual|" .
 "Opus Caroli \(Libri Carolini\)|" .
 "Diagnostic and statistical manual|" .
 "Magnalia Christi Americana|" .
 "Sydney morning herald|" .
 "Chronicle\(s\) of Ioannina|" .
 "Homo sapiens sapiens|" .
 "Tyrannotitan chubutensis|" .
 "Escherichia coli|" .
 "Bugulasensu lato|" .
 "Massospondylus carinatus|" .
 "Campylobacter jejuni|" .
 "Adenanthera pavonina|" .
 "Burkholderia pseudomallei|" .
 "Helicobacter pylori|" .
 "Drosophila silvestris|" .
 "Luzula nivea|" .
 "Bacillus pumilus|" .
 "Citipati Osmolskae|" .
 "Stichodactyla helianthusas|" .
 "Plasmodium falciparum|" .
 "Pyrobaculum calidifontis|" .
 "Arabidopsis thaliana|" .
 "Listeria monocytogenes|" .
 "In Vitro|" .
 "Mycoplasma pneumoniae|" .
 "Balaur bondoc|" .
 "Paradisaea Raggiana|" .
 "Brachiosaurus altithorax|" .
 "Saccharomyces cerevisiae|" .
 "Staphylococcus aureus|" .
 "Plutella xylostella|" .
 "Bromus laevipes|" .
 "Trypanosoma brucei|" .
 "Hulsanpes perlei|" .
 "Buitreraptor gonzalezorum|" .
 "Bellusaurus sui|" .
 "Sinovenator changii|" .
 "Nedcolbertia justinhofmanni|" .
 "Clostridium botulinum|" .
 "Bactrocera dorsalis|" .
 "Communist Manifesto|" .
 "Umm al-Kitāb|" .
 "Acrocomia mexicana|" .
 "Agaricus hondensis|" .
 "Campylobacter jejuni|" .
 "Actinomyces bovis|" .
 "Balaur bondoc|" .
 "Acrocomia mexicana|" .
 "Caenorhabditis elegans|" .
 "Paradisaea Raggiana|" .
 "Screbinodus ornatus|" .
 "Rg Veda|" .
 "Homo erectus|" .
 "Piveteauia madagascariensis|" .
 "Aspergillus terreus|" .
 "Ignicoccus hospitalis|" .
 "Watsonulus eugnathoides|" .
 "Opus Caroli|" .
 "Libri Carolini|" .
 "Nanoarchaeum equitans|" .
 "Les Noces|" .
 "Aspergillus terreus|" .
 "Batillipes mirusand|" .
 "Batillipes noerrevangi|" .
 "Robustichthys luopingensis|" .
 "Uroplectes ansiedippenaarae|" .
 "Tornieria africana|" .
 "Pericope Adulterae|" .
 "Ilex asprella|" .
 "Pristionchus|" .
 "Arabidopsis|" .
 "Realpolitik|" .
 "Paratirolites|" .
 "Arianops|" .
 "Loxosceles|" .
 "Hyloscirtus|" .
 "Pseudomonas|" .
 "Baryonyx|" .
 "Monacha|" .
 "Plesiosorex|" .
 "Bushiellas|" .
 "Godartiana|" .
 "Hapalotremus|" .
 "Orcus|" .
 "Tolegnaro|" .
 "Noideattella|" .
 "Euschistus|" .
 "Arthoniais|" .
 "Acacia|" .
 "Lasalichthys|" .
 "Berardiusin|" .
 "Ilustrado|" .
 "Keśin|" .
 "Synorichthys|" .
 "Clusia|" .
 "Sinamia|" .
 "Ignicoccus|" .
 "Hencke|" .
 "Batillipes|" .
 "Chenopodium|" .
 "Diplodocus|" .
 "Hipparchs|" .
 "Lepidobatrachus|" .
 "Uroplectes|" .
 "Euroscaptor|" .
 "Montifringilla|" .
 "Leucosticte|" .
 "Candida|" .
 "Cannabis|" .
 "Aglyptorhynchus|" .
 "Egertonia|" .
 "Dapedium|" .
 "Pycnodus|" .
 "Metaceratodus|" .
 "Erwinia|" .
 "END_OF_CITE_list_junk";
//  All real ones need pipe on end
//  YOU MUST ESCAPE () and other FUNNY Characters

const CAMEL_CASE = array('DeSoto', 'PubChem', 'BitTorrent', 'Al2O3', 'NiMo', 'CuZn', 'BxCyNz', 'ChemCam',
                         'StatsRef', 'BuzzFeed', 'DeBenedetti', 'DeVries', 'TallyHo', 'JngJ', 'ENaCs',
                         'MensRights', 'McCarthy', 'AmpliSeq', 'nRepeat', 'OpenStreetMap', 'DonThorsen',
                         'arXiv', 'eBay', 'aRMadillo', 'HowNutsAreTheDutch', 'Liberalism', 'HoeGekIsNL',
                         'iMac', 'iPhone', 'iPad', 'iTunes', 'FreeFab', 'HeartMath', 'MeToo', 'SysCon', 'DiMarco', ' Mc', ' Mac',
                         'DiMarco', 'DeepMind', 'BabySeq', 'ClinVar',  'UCbase', 'miRfunc', 'GeneMatcher',
                         'TimeLapse', 'CapStarr', ' SpyTag', 'SpyCatcher', 'SpyBank', 'TaqMan',
                         'PhyreRisk', 'piggyBac', 'HapMap', 'MiSeq', 'QualComp', 'PastCast', 'InvAluable',
                         'NgAgo', ' MitoZoa', 'InterMitoBase', 'LaserTank', 'GeneBase', 'DesignSignatures',
                         'HeLa', 'QuadBase', 'GenBank', 'PowerPlex', 'ExInt', 'TissueInfo', 'HeliScope',
                         'ConDeTri', 'HIrisPlex', 'CpGIMethPred', 'Quantum Dots', 'TopHat', 'WikiProject',
                         'RefSeq', 'geneCo', 'SpringerReference', 'aMeta', 'ChIP', 'OligArch',
                         'PyDamage', 'SayHerName', 'pDecays', 'BioMaterialia', 'FlexMed', 'GaTate',
                         'iCloud', 'iPod', 'CamelCase', 'DryIce', 'CinemaScope', 'AstroTurf',
                         'QuarkXPress', 'FedEx', 'YouTube', 'PlayStation', 'NeXT', 'InterCaps',
                         'CorpoNym', 'ExxonMobil', 'HarperCollins', 'ConAgra', 'BumpyCaps', 'BumpyCase',
                         'NerdCaps', 'CapWords', 'compoundNames', 'HumpintheMiddle', 'HumpBack', 'InterCap',
                         'mixedCase', 'WikiWord', 'WikiCase', 'ProperCase', 'StUdLyCaPs', 'MasterCraft',
                         'MasterCard', 'SportsCenter', 'CompuServe', 'WordStar', 'VisiCalc', 'WordPerfect',
                         'NetWare', 'LaserJet', 'MacWorks', 'PostScript', 'PageMaker', 'ClarisWorks', 'HyperCard',
                         'PowerPoint', 'WorldWideWeb', 'EchoStar', 'BellSouth', 'EastEnders', 'SpaceCamp',
                         'HarperCollins', 'SeaTac', 'PricewaterhouseCoopers', 'DataLab', 'DuBois',
                         'ScaFi', '(Mc', '(Mac', 'BioShock', 'BreadTube', 'PewDiePie', 'LepVax', 'CrossMab', 'GyneFix',
                         'ProsPeCts', 'sCIentIfIC', 'PsyCholoGy', 'DiGeorge', 'AstraZeneca', 'SemCluster',
                         'VirtuReal', '-Mc', '-Mac', 'NexGard', 'AmBisome', 'AmbiOnp', 'SnapShot', 'LentiGlobin',
                         'SmartTutor', 'TarBase', 'miRecords', 'miRTarBase', 'deepBase', 'DeepImageTranslator',
                         'TikTok', ' pKa', ' MurA', 'pKa ', 'MurA ',  'IgDhi', ' bKa’ ', '117mSn', 'ClinicalTrials',
                         'PtCo', 'ResearchGate', 'TimeTree', 'PhysiCell', 'VirtualLeaf', 'PhenoScanner',
                         'GeoSteiner', 'eCommerce', 'FrameNet', 'DeLury', 'GeGaLo', 'LeukArrest', 'IceCube',
                         'NeuroImages', 'UppSten', 'AngloMania', 'HiRes', 'PolyCystic', 'é', 'AlmaToo', 'CubeSat',
                         'DeArmitt', 'ProQuest', 'SemNet', 'ī', 'LaundroGraph', 'ZoKrates', 'xJsnark', 'ç',
                         'GitHub', 'GitLab', 'iThings', 'FuzzBench', 'TinyLock', 'EvoPass', 'eGovernment',
                         'TinyGarble', 'PhagoBurn', 'KwaZulu', 'BioNetGen', 'DataBase', 'GeneChip', 'TransRate',
                         'StringTie', 'featureCounts', 'DiffSplice', 'QuantSeq', 'WebGestalt', 'flowCore',
                         'flowClust', 'MetaGenomic', 'TlInGe', 'RapidArc', 'SmartArc', 'TomoHD', 'ViralZone',
                         'HyPhy', 'MrBayes', 'SunTag', 'InterPro', 'SmProt', 'ChikDenMaZika', 'LitCovid',
                         'GeneTree', 'GenAge', 'QnAs', 'BiDil', 'iAge', 'DevSec', 'SecOps', 'DevcOps',
                         'LeafCutter', 'CyBase','OxPhos', 'ArrayExpress', 'BepiColombo', 'RuleMonkey',
                         'OxyCo', 'CdZnTe',
                       );

const ITALICS_HARDCODE_IN  = ["PolishLebensraum",       "theNachlassproblem",        " forAltalena:",      " in Plutarch'sLives",       "FromSolidarityto",        " gp91phoxPromoter",  "in vitroAssays",        "MarketizingHindutva",       "TheBhagavadgītā,",       "theOrigin of Species",       "EncounteringHindutva",       "ChineseHukouSystem",        "CisLatreille"];
const ITALICS_HARDCODE_OUT = ["Polish ''Lebensraum'' ", "the ''Nachlass'' problem ", " for ''Altalena'':", " in Plutarch's ''Lives'' ", "From ''Solidarity'' to ", " gp91phox Promoter", " ''in vitro'' Assays ", "Marketizing ''Hindutva'' ", "The ''Bhagavadgītā'', ", "the ''Origin of Species'' ", "Encountering ''Hindutva'' ", "Chinese ''Hukou'' System ", "''Cis'' Latreille"];

