<?php 
const TEMPLATES_WE_PROCESS = array('citation', 'cite arxiv', 'cite book', 'cite document', 
                             'cite encyclopaedia', 'cite encyclopedia', 'cite journal', 'cite web');
const TEMPLATES_WE_RENAME  = array('cite arxiv', 'cite book', 'cite document', 'cite journal', 'cite web');

const AUTHOR_PARAMETERS = array(
    1  => array('surname'  , 'forename'  , 'initials'  , 'first'  , 'last'  , 'author',
                'surname1' , 'forename1' , 'initials1' , 'first1' , 'last1' , 'author1', 'authors', 'vauthors', 'author-last', 'author-last1', 'author-first', 'author-first1'),
    2  => array('surname2' , 'forename2' , 'initials2' , 'first2' , 'last2' , 'author2', 'author-last2', 'author-first2', 'coauthors', 'coauthor'),
    3  => array('surname3' , 'forename3' , 'initials3' , 'first3' , 'last3' , 'author3', 'author-last3', 'author-first3'),
    4  => array('surname4' , 'forename4' , 'initials4' , 'first4' , 'last4' , 'author4', 'author-last4', 'author-first4'),
    5  => array('surname5' , 'forename5' , 'initials5' , 'first5' , 'last5' , 'author5', 'author-last5', 'author-first5'),
    6  => array('surname6' , 'forename6' , 'initials6' , 'first6' , 'last6' , 'author6', 'author-last6', 'author-first6'),
    7  => array('surname7' , 'forename7' , 'initials7' , 'first7' , 'last7' , 'author7', 'author-last7', 'author-first7'),
    8  => array('surname8' , 'forename8' , 'initials8' , 'first8' , 'last8' , 'author8', 'author-last8', 'author-first8'),
    9  => array('surname9' , 'forename9' , 'initials9' , 'first9' , 'last9' , 'author9', 'author-last9', 'author-first9'),
    10 => array('surname10', 'forename10', 'initials10', 'first10', 'last10', 'author10', 'author-last10', 'author-first10'),
    11 => array('surname11', 'forename11', 'initials11', 'first11', 'last11', 'author11', 'author-last11', 'author-first11'),
    12 => array('surname12', 'forename12', 'initials12', 'first12', 'last12', 'author12', 'author-last12', 'author-first12'),
    13 => array('surname13', 'forename13', 'initials13', 'first13', 'last13', 'author13', 'author-last13', 'author-first13'),
    14 => array('surname14', 'forename14', 'initials14', 'first14', 'last14', 'author14', 'author-last14', 'author-first14'),
    15 => array('surname15', 'forename15', 'initials15', 'first15', 'last15', 'author15', 'author-last15', 'author-first15'),
    16 => array('surname16', 'forename16', 'initials16', 'first16', 'last16', 'author16', 'author-last16', 'author-first16'),
    17 => array('surname17', 'forename17', 'initials17', 'first17', 'last17', 'author17', 'author-last17', 'author-first17'),
    18 => array('surname18', 'forename18', 'initials18', 'first18', 'last18', 'author18', 'author-last18', 'author-first18'),
    19 => array('surname19', 'forename19', 'initials19', 'first19', 'last19', 'author19', 'author-last19', 'author-first19'),
    20 => array('surname20', 'forename20', 'initials20', 'first20', 'last20', 'author20', 'author-last20', 'author-first20'),
    20 => array('surname20', 'forename20', 'initials20', 'first20', 'last20', 'author20', 'author-last20', 'author-first20'),
    21 => array('surname21', 'forename21', 'initials21', 'first21', 'last21', 'author21', 'author-last21', 'author-first21'),
    22 => array('surname22', 'forename22', 'initials22', 'first22', 'last22', 'author22', 'author-last22', 'author-first22'),
    23 => array('surname23', 'forename23', 'initials23', 'first23', 'last23', 'author23', 'author-last23', 'author-first23'),
    24 => array('surname24', 'forename24', 'initials24', 'first24', 'last24', 'author24', 'author-last24', 'author-first24'),
    25 => array('surname25', 'forename25', 'initials25', 'first25', 'last25', 'author25', 'author-last25', 'author-first25'),
    26 => array('surname26', 'forename26', 'initials26', 'first26', 'last26', 'author26', 'author-last26', 'author-first26'),
    27 => array('surname27', 'forename27', 'initials27', 'first27', 'last27', 'author27', 'author-last27', 'author-first27'),
    28 => array('surname28', 'forename28', 'initials28', 'first28', 'last28', 'author28', 'author-last28', 'author-first28'),
    29 => array('surname29', 'forename29', 'initials29', 'first29', 'last29', 'author29', 'author-last29', 'author-first29'),
    30 => array('surname30', 'forename30', 'initials30', 'first30', 'last30', 'author30', 'author-last30', 'author-first30'),
    31 => array('surname31', 'forename31', 'initials31', 'first31', 'last31', 'author31', 'author-last31', 'author-first31'),
    32 => array('surname32', 'forename32', 'initials32', 'first32', 'last32', 'author32', 'author-last32', 'author-first32'),
    33 => array('surname33', 'forename33', 'initials33', 'first33', 'last33', 'author33', 'author-last33', 'author-first33'),
    34 => array('surname34', 'forename34', 'initials34', 'first34', 'last34', 'author34', 'author-last34', 'author-first34'),
    35 => array('surname35', 'forename35', 'initials35', 'first35', 'last35', 'author35', 'author-last35', 'author-first35'),
    36 => array('surname36', 'forename36', 'initials36', 'first36', 'last36', 'author36', 'author-last36', 'author-first36'),
    37 => array('surname37', 'forename37', 'initials37', 'first37', 'last37', 'author37', 'author-last37', 'author-first37'),
    38 => array('surname38', 'forename38', 'initials38', 'first38', 'last38', 'author38', 'author-last38', 'author-first38'),
    39 => array('surname39', 'forename39', 'initials39', 'first39', 'last39', 'author39', 'author-last39', 'author-first39'),
    40 => array('surname40', 'forename40', 'initials40', 'first40', 'last40', 'author40', 'author-last40', 'author-first40'),
    41 => array('surname41', 'forename41', 'initials41', 'first41', 'last41', 'author41', 'author-last41', 'author-first41'),
    42 => array('surname42', 'forename42', 'initials42', 'first42', 'last42', 'author42', 'author-last42', 'author-first42'),
    43 => array('surname43', 'forename43', 'initials43', 'first43', 'last43', 'author43', 'author-last43', 'author-first43'),
    44 => array('surname44', 'forename44', 'initials44', 'first44', 'last44', 'author44', 'author-last44', 'author-first44'),
    45 => array('surname45', 'forename45', 'initials45', 'first45', 'last45', 'author45', 'author-last45', 'author-first45'),
    46 => array('surname46', 'forename46', 'initials46', 'first46', 'last46', 'author46', 'author-last46', 'author-first46'),
    47 => array('surname47', 'forename47', 'initials47', 'first47', 'last47', 'author47', 'author-last47', 'author-first47'),
    48 => array('surname48', 'forename48', 'initials48', 'first48', 'last48', 'author48', 'author-last48', 'author-first48'),
    49 => array('surname49', 'forename49', 'initials49', 'first49', 'last49', 'author49', 'author-last49', 'author-first49'),
    50 => array('surname50', 'forename50', 'initials50', 'first50', 'last50', 'author50', 'author-last50', 'author-first50'),
    51 => array('surname51', 'forename51', 'initials51', 'first51', 'last51', 'author51', 'author-last51', 'author-first51'),
    52 => array('surname52', 'forename52', 'initials52', 'first52', 'last52', 'author52', 'author-last52', 'author-first52'),
    53 => array('surname53', 'forename53', 'initials53', 'first53', 'last53', 'author53', 'author-last53', 'author-first53'),
    54 => array('surname54', 'forename54', 'initials54', 'first54', 'last54', 'author54', 'author-last54', 'author-first54'),
    55 => array('surname55', 'forename55', 'initials55', 'first55', 'last55', 'author55', 'author-last55', 'author-first55'),
    56 => array('surname56', 'forename56', 'initials56', 'first56', 'last56', 'author56', 'author-last56', 'author-first56'),
    57 => array('surname57', 'forename57', 'initials57', 'first57', 'last57', 'author57', 'author-last57', 'author-first57'),
    58 => array('surname58', 'forename58', 'initials58', 'first58', 'last58', 'author58', 'author-last58', 'author-first58'),
    59 => array('surname59', 'forename59', 'initials59', 'first59', 'last59', 'author59', 'author-last59', 'author-first59'),
    60 => array('surname60', 'forename60', 'initials60', 'first60', 'last60', 'author60', 'author-last60', 'author-first60'),
    61 => array('surname61', 'forename61', 'initials61', 'first61', 'last61', 'author61', 'author-last61', 'author-first61'),
    62 => array('surname62', 'forename62', 'initials62', 'first62', 'last62', 'author62', 'author-last62', 'author-first62'),
    63 => array('surname63', 'forename63', 'initials63', 'first63', 'last63', 'author63', 'author-last63', 'author-first63'),
    64 => array('surname64', 'forename64', 'initials64', 'first64', 'last64', 'author64', 'author-last64', 'author-first64'),
    65 => array('surname65', 'forename65', 'initials65', 'first65', 'last65', 'author65', 'author-last65', 'author-first65'),
    66 => array('surname66', 'forename66', 'initials66', 'first66', 'last66', 'author66', 'author-last66', 'author-first66'),
    67 => array('surname67', 'forename67', 'initials67', 'first67', 'last67', 'author67', 'author-last67', 'author-first67'),
    68 => array('surname68', 'forename68', 'initials68', 'first68', 'last68', 'author68', 'author-last68', 'author-first68'),
    69 => array('surname69', 'forename69', 'initials69', 'first69', 'last69', 'author69', 'author-last69', 'author-first69'),
    70 => array('surname70', 'forename70', 'initials70', 'first70', 'last70', 'author70', 'author-last70', 'author-first70'),
    71 => array('surname71', 'forename71', 'initials71', 'first71', 'last71', 'author71', 'author-last71', 'author-first71'),
    72 => array('surname72', 'forename72', 'initials72', 'first72', 'last72', 'author72', 'author-last72', 'author-first72'),
    73 => array('surname73', 'forename73', 'initials73', 'first73', 'last73', 'author73', 'author-last73', 'author-first73'),
    74 => array('surname74', 'forename74', 'initials74', 'first74', 'last74', 'author74', 'author-last74', 'author-first74'),
    75 => array('surname75', 'forename75', 'initials75', 'first75', 'last75', 'author75', 'author-last75', 'author-first75'),
    76 => array('surname76', 'forename76', 'initials76', 'first76', 'last76', 'author76', 'author-last76', 'author-first76'),
    77 => array('surname77', 'forename77', 'initials77', 'first77', 'last77', 'author77', 'author-last77', 'author-first77'),
    78 => array('surname78', 'forename78', 'initials78', 'first78', 'last78', 'author78', 'author-last78', 'author-first78'),
    79 => array('surname79', 'forename79', 'initials79', 'first79', 'last79', 'author79', 'author-last79', 'author-first79'),
    80 => array('surname80', 'forename80', 'initials80', 'first80', 'last80', 'author80', 'author-last80', 'author-first80'),
    81 => array('surname81', 'forename81', 'initials81', 'first81', 'last81', 'author81', 'author-last81', 'author-first81'),
    82 => array('surname82', 'forename82', 'initials82', 'first82', 'last82', 'author82', 'author-last82', 'author-first82'),
    83 => array('surname83', 'forename83', 'initials83', 'first83', 'last83', 'author83', 'author-last83', 'author-first83'),
    84 => array('surname84', 'forename84', 'initials84', 'first84', 'last84', 'author84', 'author-last84', 'author-first84'),
    85 => array('surname85', 'forename85', 'initials85', 'first85', 'last85', 'author85', 'author-last85', 'author-first85'),
    86 => array('surname86', 'forename86', 'initials86', 'first86', 'last86', 'author86', 'author-last86', 'author-first86'),
    87 => array('surname87', 'forename87', 'initials87', 'first87', 'last87', 'author87', 'author-last87', 'author-first87'),
    88 => array('surname88', 'forename88', 'initials88', 'first88', 'last88', 'author88', 'author-last88', 'author-first88'),
    89 => array('surname89', 'forename89', 'initials89', 'first89', 'last89', 'author89', 'author-last89', 'author-first89'),
    90 => array('surname90', 'forename90', 'initials90', 'first90', 'last90', 'author90', 'author-last90', 'author-first90'),
    91 => array('surname91', 'forename91', 'initials91', 'first91', 'last91', 'author91', 'author-last91', 'author-first91'),
    92 => array('surname92', 'forename92', 'initials92', 'first92', 'last92', 'author92', 'author-last92', 'author-first92'),
    93 => array('surname93', 'forename93', 'initials93', 'first93', 'last93', 'author93', 'author-last93', 'author-first93'),
    94 => array('surname94', 'forename94', 'initials94', 'first94', 'last94', 'author94', 'author-last94', 'author-first94'),
    95 => array('surname95', 'forename95', 'initials95', 'first95', 'last95', 'author95', 'author-last95', 'author-first95'),
    96 => array('surname96', 'forename96', 'initials96', 'first96', 'last96', 'author96', 'author-last96', 'author-first96'),
    97 => array('surname97', 'forename97', 'initials97', 'first97', 'last97', 'author97', 'author-last97', 'author-first97'),
    98 => array('surname98', 'forename98', 'initials98', 'first98', 'last98', 'author98', 'author-last98', 'author-first98'),
    99 => array('surname99', 'forename99', 'initials99', 'first99', 'last99', 'author99', 'author-last99', 'author-first99'),
);

const FLATTENED_AUTHOR_PARAMETERS = array('surname', 'forename', 'initials', 
    'first'  , 'last'  , 'author', 'author-last', 'author-first',
    'surname1' , 'forename1' , 'initials1' , 'first1' , 'last1' , 'author1' , 'author-last1', 'author-first1', 'authors', 'vauthors',
    'surname2' , 'forename2' , 'initials2' , 'first2' , 'last2' , 'author2' , 'author-last2', 'author-first2', 'coauthors', 'coauthor',
    'surname3' , 'forename3' , 'initials3' , 'first3' , 'last3' , 'author3' , 'author-last3', 'author-first3',
    'surname4' , 'forename4' , 'initials4' , 'first4' , 'last4' , 'author4' , 'author-last4', 'author-first4',
    'surname5' , 'forename5' , 'initials5' , 'first5' , 'last5' , 'author5' , 'author-last5', 'author-first5',
    'surname6' , 'forename6' , 'initials6' , 'first6' , 'last6' , 'author6' , 'author-last6', 'author-first6',
    'surname7' , 'forename7' , 'initials7' , 'first7' , 'last7' , 'author7' , 'author-last7', 'author-first7',
    'surname8' , 'forename8' , 'initials8' , 'first8' , 'last8' , 'author8' , 'author-last8', 'author-first8',
    'surname9' , 'forename9' , 'initials9' , 'first9' , 'last9' , 'author9' , 'author-last9', 'author-first9',
    'surname10', 'forename10', 'initials10', 'first10', 'last10', 'author10', 'author-last10', 'author-first10',
    'surname11', 'forename11', 'initials11', 'first11', 'last11', 'author11', 'author-last11', 'author-first11',
    'surname12', 'forename12', 'initials12', 'first12', 'last12', 'author12', 'author-last12', 'author-first12',
    'surname13', 'forename13', 'initials13', 'first13', 'last13', 'author13', 'author-last13', 'author-first13',
    'surname14', 'forename14', 'initials14', 'first14', 'last14', 'author14', 'author-last14', 'author-first14',
    'surname15', 'forename15', 'initials15', 'first15', 'last15', 'author15', 'author-last15', 'author-first15',
    'surname16', 'forename16', 'initials16', 'first16', 'last16', 'author16', 'author-last16', 'author-first16',
    'surname17', 'forename17', 'initials17', 'first17', 'last17', 'author17', 'author-last17', 'author-first17',
    'surname18', 'forename18', 'initials18', 'first18', 'last18', 'author18', 'author-last18', 'author-first18',
    'surname19', 'forename19', 'initials19', 'first19', 'last19', 'author19', 'author-last19', 'author-first19',
    'surname20', 'forename20', 'initials20', 'first20', 'last20', 'author20', 'author-last20', 'author-first20',
    'surname21', 'forename21', 'initials21', 'first21', 'last21', 'author21', 'author-last21', 'author-first21',
    'surname22', 'forename22', 'initials22', 'first22', 'last22', 'author22', 'author-last22', 'author-first22',
    'surname23', 'forename23', 'initials23', 'first23', 'last23', 'author23', 'author-last23', 'author-first23',
    'surname24', 'forename24', 'initials24', 'first24', 'last24', 'author24', 'author-last24', 'author-first24',
    'surname25', 'forename25', 'initials25', 'first25', 'last25', 'author25', 'author-last25', 'author-first25',
    'surname26', 'forename26', 'initials26', 'first26', 'last26', 'author26', 'author-last26', 'author-first26',
    'surname27', 'forename27', 'initials27', 'first27', 'last27', 'author27', 'author-last27', 'author-first27',
    'surname28', 'forename28', 'initials28', 'first28', 'last28', 'author28', 'author-last28', 'author-first28',
    'surname29', 'forename29', 'initials29', 'first29', 'last29', 'author29', 'author-last29', 'author-first29',
    'surname30', 'forename30', 'initials30', 'first30', 'last30', 'author30', 'author-last30', 'author-first30',
    'surname31', 'forename31', 'initials31', 'first31', 'last31', 'author31', 'author-last31', 'author-first31',
    'surname32', 'forename32', 'initials32', 'first32', 'last32', 'author32', 'author-last32', 'author-first32',
    'surname33', 'forename33', 'initials33', 'first33', 'last33', 'author33', 'author-last33', 'author-first33',
    'surname34', 'forename34', 'initials34', 'first34', 'last34', 'author34', 'author-last34', 'author-first34',
    'surname35', 'forename35', 'initials35', 'first35', 'last35', 'author35', 'author-last35', 'author-first35',
    'surname36', 'forename36', 'initials36', 'first36', 'last36', 'author36', 'author-last36', 'author-first36',
    'surname37', 'forename37', 'initials37', 'first37', 'last37', 'author37', 'author-last37', 'author-first37',
    'surname38', 'forename38', 'initials38', 'first38', 'last38', 'author38', 'author-last38', 'author-first38',
    'surname39', 'forename39', 'initials39', 'first39', 'last39', 'author39', 'author-last39', 'author-first39',
    'surname40', 'forename40', 'initials40', 'first40', 'last40', 'author40', 'author-last40', 'author-first40',
    'surname41', 'forename41', 'initials41', 'first41', 'last41', 'author41', 'author-last41', 'author-first41',
    'surname42', 'forename42', 'initials42', 'first42', 'last42', 'author42', 'author-last42', 'author-first42',
    'surname43', 'forename43', 'initials43', 'first43', 'last43', 'author43', 'author-last43', 'author-first43',
    'surname44', 'forename44', 'initials44', 'first44', 'last44', 'author44', 'author-last44', 'author-first44',
    'surname45', 'forename45', 'initials45', 'first45', 'last45', 'author45', 'author-last45', 'author-first45',
    'surname46', 'forename46', 'initials46', 'first46', 'last46', 'author46', 'author-last46', 'author-first46',
    'surname47', 'forename47', 'initials47', 'first47', 'last47', 'author47', 'author-last47', 'author-first47',
    'surname48', 'forename48', 'initials48', 'first48', 'last48', 'author48', 'author-last48', 'author-first48',
    'surname49', 'forename49', 'initials49', 'first49', 'last49', 'author49', 'author-last49', 'author-first49',
    'surname50', 'forename50', 'initials50', 'first50', 'last50', 'author50', 'author-last50', 'author-first50',
    'surname51', 'forename51', 'initials51', 'first51', 'last51', 'author51', 'author-last51', 'author-first51',
    'surname52', 'forename52', 'initials52', 'first52', 'last52', 'author52', 'author-last52', 'author-first52',
    'surname53', 'forename53', 'initials53', 'first53', 'last53', 'author53', 'author-last53', 'author-first53',
    'surname54', 'forename54', 'initials54', 'first54', 'last54', 'author54', 'author-last54', 'author-first54',
    'surname55', 'forename55', 'initials55', 'first55', 'last55', 'author55', 'author-last55', 'author-first55',
    'surname56', 'forename56', 'initials56', 'first56', 'last56', 'author56', 'author-last56', 'author-first56',
    'surname57', 'forename57', 'initials57', 'first57', 'last57', 'author57', 'author-last57', 'author-first57',
    'surname58', 'forename58', 'initials58', 'first58', 'last58', 'author58', 'author-last58', 'author-first58',
    'surname59', 'forename59', 'initials59', 'first59', 'last59', 'author59', 'author-last59', 'author-first59',
    'surname60', 'forename60', 'initials60', 'first60', 'last60', 'author60', 'author-last60', 'author-first60',
    'surname61', 'forename61', 'initials61', 'first61', 'last61', 'author61', 'author-last61', 'author-first61',
    'surname62', 'forename62', 'initials62', 'first62', 'last62', 'author62', 'author-last62', 'author-first62',
    'surname63', 'forename63', 'initials63', 'first63', 'last63', 'author63', 'author-last63', 'author-first63',
    'surname64', 'forename64', 'initials64', 'first64', 'last64', 'author64', 'author-last64', 'author-first64',
    'surname65', 'forename65', 'initials65', 'first65', 'last65', 'author65', 'author-last65', 'author-first65',
    'surname66', 'forename66', 'initials66', 'first66', 'last66', 'author66', 'author-last66', 'author-first66',
    'surname67', 'forename67', 'initials67', 'first67', 'last67', 'author67', 'author-last67', 'author-first67',
    'surname68', 'forename68', 'initials68', 'first68', 'last68', 'author68', 'author-last68', 'author-first68',
    'surname69', 'forename69', 'initials69', 'first69', 'last69', 'author69', 'author-last69', 'author-first69',
    'surname70', 'forename70', 'initials70', 'first70', 'last70', 'author70', 'author-last70', 'author-first70',
    'surname71', 'forename71', 'initials71', 'first71', 'last71', 'author71', 'author-last71', 'author-first71',
    'surname72', 'forename72', 'initials72', 'first72', 'last72', 'author72', 'author-last72', 'author-first72',
    'surname73', 'forename73', 'initials73', 'first73', 'last73', 'author73', 'author-last73', 'author-first73',
    'surname74', 'forename74', 'initials74', 'first74', 'last74', 'author74', 'author-last74', 'author-first74',
    'surname75', 'forename75', 'initials75', 'first75', 'last75', 'author75', 'author-last75', 'author-first75',
    'surname76', 'forename76', 'initials76', 'first76', 'last76', 'author76', 'author-last76', 'author-first76',
    'surname77', 'forename77', 'initials77', 'first77', 'last77', 'author77', 'author-last77', 'author-first77',
    'surname78', 'forename78', 'initials78', 'first78', 'last78', 'author78', 'author-last78', 'author-first78',
    'surname79', 'forename79', 'initials79', 'first79', 'last79', 'author79', 'author-last79', 'author-first79',
    'surname80', 'forename80', 'initials80', 'first80', 'last80', 'author80', 'author-last80', 'author-first80',
    'surname81', 'forename81', 'initials81', 'first81', 'last81', 'author81', 'author-last81', 'author-first81',
    'surname82', 'forename82', 'initials82', 'first82', 'last82', 'author82', 'author-last82', 'author-first82',
    'surname83', 'forename83', 'initials83', 'first83', 'last83', 'author83', 'author-last83', 'author-first83',
    'surname84', 'forename84', 'initials84', 'first84', 'last84', 'author84', 'author-last84', 'author-first84',
    'surname85', 'forename85', 'initials85', 'first85', 'last85', 'author85', 'author-last85', 'author-first85',
    'surname86', 'forename86', 'initials86', 'first86', 'last86', 'author86', 'author-last86', 'author-first86',
    'surname87', 'forename87', 'initials87', 'first87', 'last87', 'author87', 'author-last87', 'author-first87',
    'surname88', 'forename88', 'initials88', 'first88', 'last88', 'author88', 'author-last88', 'author-first88',
    'surname89', 'forename89', 'initials89', 'first89', 'last89', 'author89', 'author-last89', 'author-first89',
    'surname90', 'forename90', 'initials90', 'first90', 'last90', 'author90', 'author-last90', 'author-first90',
    'surname91', 'forename91', 'initials91', 'first91', 'last91', 'author91', 'author-last91', 'author-first91',
    'surname92', 'forename92', 'initials92', 'first92', 'last92', 'author92', 'author-last92', 'author-first92',
    'surname93', 'forename93', 'initials93', 'first93', 'last93', 'author93', 'author-last93', 'author-first93',
    'surname94', 'forename94', 'initials94', 'first94', 'last94', 'author94', 'author-last94', 'author-first94',
    'surname95', 'forename95', 'initials95', 'first95', 'last95', 'author95', 'author-last95', 'author-first95',
    'surname96', 'forename96', 'initials96', 'first96', 'last96', 'author96', 'author-last96', 'author-first96',
    'surname97', 'forename97', 'initials97', 'first97', 'last97', 'author97', 'author-last97', 'author-first97',
    'surname98', 'forename98', 'initials98', 'first98', 'last98', 'author98', 'author-last98', 'author-first98',
    'surname99', 'forename99', 'initials99', 'first99', 'last99', 'author99', 'author-last99', 'author-first99',

);


// Includes many parameters usually from templates that we do not modify such as {{cite patent}}, because
// that information can also be presented using the generic {{citation}} template, which we do modify.
// This list even includes items that are no longer supported, since we need to leave fixing them to humans
// See https://en.wikipedia.org/wiki/Module:Citation/CS1/Whitelist
const PARAMETER_LIST = array(
'ARXIV', 'ASIN', 'ASIN-TLD', 'Author', 'Author#', 'BIBCODE',
'CITATION_BOT_PLACEHOLDER_BARE_URL',
'DOI', 'DoiBroken', 'EISSN', 'Editor', 'Editor#', 'EditorGiven',
'EditorGiven#', 'EditorSurname', 'EditorSurname#', 'Embargo', 'HDL',
'ID', 'ISBN', 'ISBN13', 'ISMN', 'ISSN', 'JFM', 'JSTOR', 'LCCN', 'MR',
'OCLC', 'OL', 'OSTI', 'PMC', 'PMID', 'PPPrefix', 'PPrefix', 'RFC',
'Ref', 'SSRN', 'URL', 'ZBL', 'access-date', 'accessdate', 'agency',
'air-date', 'airdate', 'albumlink', 'albumtype', 'archive-date',
'archive-format', 'archive-url', 'archivedate', 'archiveurl',
'article', 'artist', 'arxiv', 'asin', 'asin-tld', 'assign',
'assign#', 'assignee', 'at', 'author', 'author#', 'author#-first',
'author#-last', 'author#-link', 'author#-mask', 'author#link',
'author#mask', 'author-first', 'author-first#', 'author-format',
'author-last', 'author-last#', 'author-link', 'author-link#',
'author-mask', 'author-mask#', 'author-name-separator',
'author-separator', 'authorformat', 'authorlink', 'authorlink#',
'authormask', 'authormask#', 'authors', 'authors#', 'began', 'bibcode', 
'bibcode-access', 'biorxiv', 'book-title', 'booktitle', 'call-sign', 
'callsign', 'cartography', 'chapter', 'chapter-format', 'chapter-link', 
'chapter-url', 'chapter-url-access', 'chapterlink', 'chapterurl', 
'citation_bot_placeholder_bare_url',
'citeseerx', 'city', 'class', 'coauthor', 'coauthors',
'cointerviewers', 'collaboration', 'conference', 'conference-format',
'conference-url', 'conferenceurl', 'contribution',
'contribution-format', 'contribution-url', 'contributionurl',
'contributor', 'contributor#', 'contributor#-first',
'contributor#-given', 'contributor#-last', 'contributor#-link',
'contributor#-mask', 'contributor#-surname', 'contributor-first',
'contributor-first#', 'contributor-given', 'contributor-given#',
'contributor-last', 'contributor-last#', 'contributor-link',
'contributor-link#', 'contributor-mask', 'contributor-mask#',
'contributor-surname', 'contributor-surname#', 'country',
'country-code', 'credits', 'date', 'day', 'dead-url', 'deadurl',
'degree', 'department', 'description', 'df', 'dictionary',
'director', 'display-authors', 'display-editors', 'displayauthors',
'displayeditors', 'docket', 'doi', 'doi-access', 'doi-broken',
'doi-broken-date', 'doi-inactive-date', 'doi_brokendate',
'doi_inactivedate', 'edition', 'editor', 'editor#', 'editor#-first',
'editor#-given', 'editor#-last', 'editor#-link', 'editor#-mask',
'editor#-surname', 'editor#link', 'editor#mask', 'editor-first',
'editor-first#', 'editor-format', 'editor-given', 'editor-given#',
'editor-last', 'editor-last#', 'editor-link', 'editor-link#',
'editor-mask', 'editor-mask#', 'editor-name-separator',
'editor-separator', 'editor-surname', 'editor-surname#',
'editorformat', 'editorlink', 'editorlink#', 'editormask',
'editormask#', 'editors', 'editors#', 'eissn', 'embargo',
'encyclopaedia', 'encyclopedia', 'ended', 'entry', 'episode',
'episode-link', 'episodelink', 'eprint', 'event', 'event-format',
'event-url', 'eventurl', 'fdate', 'first', 'first#', 'format',
'gdate', 'given', 'given#', 'hdl', 'hdl-access', 'host', 'id',
'ignore-isbn-error', 'ignoreisbnerror', 'in', 'inset', 'institution',
'interviewer', 'interviewer#', 'interviewer#-first',
'interviewer#-last', 'interviewer#-link', 'interviewer#-mask',
'interviewer-first', 'interviewer-first#', 'interviewer-last',
'interviewer-last#', 'interviewer-link', 'interviewer-link#',
'interviewer-mask', 'interviewer-mask#', 'interviewerlink',
'interviewermask', 'interviewers', 'invent#', 'inventor',
'inventor#', 'inventor#-first', 'inventor#-given', 'inventor#-last',
'inventor#-link', 'inventor#-surname', 'inventor#link',
'inventor-first', 'inventor-first#', 'inventor-given',
'inventor-given#', 'inventor-last', 'inventor-last#',
'inventor-link', 'inventor-link#', 'inventor-surname',
'inventor-surname#', 'inventorlink', 'inventorlink#', 'isbn',
'isbn13', 'ismn', 'issn', 'issue', 'issue-date', 'jfm', 'journal',
'jstor', 'jstor-access', 'lang', 'language', 'last', 'last#',
'last-author-amp', 'lastauthoramp', 'lay-date', 'lay-format',
'lay-source', 'lay-summary', 'lay-url', 'laydate', 'laysource',
'laysummary', 'layurl', 'lccn', 'location', 'magazine',
'mailing-list', 'mailinglist', 'map', 'map-format', 'map-url',
'mapurl', 'medium', 'message-id', 'minutes', 'mode', 'month', 'mr',
'name-list-format', 'name-separator', 'network', 'newsgroup',
'newspaper', 'no-cat', 'no-pp', 'no-tracking', 'nocat', 'nopp',
'notestitle', 'notracking', 'number', 'oclc', 'ol', 'ol-access',
'orig-year', 'origyear', 'osti', 'osti-access', 'others', 'p',
'p-prefix', 'page', 'pages', 'patent-number', 'people', 'periodical',
'place', 'pmc', 'pmid', 'postscript', 'pp', 'pp-prefix', 'pridate',
'program', 'pubdate', 'publication-date', 'publication-number',
'publication-place', 'publicationdate', 'publicationplace',
'publisher', 'publisherid', 'quotation', 'quote', 'ref',
'registration', 'rfc', 'scale', 'script-chapter', 'script-title',
'season', 'section', 'section-format', 'section-url', 'sections',
'sectionurl', 'separator', 'series', 'series-link', 'series-no',
'series-number', 'series-separator', 'serieslink', 'seriesno',
'seriesnumber', 'sheet', 'sheets', 'ssrn', 'station', 'status',
'subject', 'subject#', 'subject#-link', 'subject#link',
'subject-link', 'subject-link#', 'subjectlink', 'subjectlink#',
'subscription', 'surname', 'surname#', 'template-doc-demo', 'time',
'time-caption', 'timecaption', 'title', 'title-link', 'titlelink',
'titleyear', 'trans-chapter', 'trans-map', 'trans-title',
'trans_chapter', 'trans_title', 'transcript', 'transcript-format',
'transcript-url', 'transcripturl', 'translator', 'translator#',
'translator#-first', 'translator#-given', 'translator#-last',
'translator#-link', 'translator#-mask', 'translator#-surname',
'translator-first', 'translator-first#', 'translator-given',
'translator-given#', 'translator-last', 'translator-last#',
'translator-link', 'translator-link#', 'translator-mask',
'translator-mask#', 'translator-surname', 'translator-surname#',
'type', 'url', 'url-access', 'vauthors', 'veditors', 'version',
'via', 'volume', 'website', 'work', 'year', 'zbl');
