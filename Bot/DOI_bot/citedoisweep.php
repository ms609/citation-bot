<?

error_reporting(E_ALL^E_NOTICE);
$slowMode=false;
$fastMode=false;
$editInitiator = '[cDs]';
$accountSuffix='_3';



$ON = true;
//$ON=false;
$linkto2 = '';
include("/home/verisimilus/public_html/Bot/DOI_bot/expandFns$linkto2.php");

$citeDois= array("Cite doi/10.1016.2FS0012-821X.2803.2900017-7", "Cite doi/10.1016.2FS0012-8252.2800.2900019-2", "Cite doi/10.1016.2FS0022-1139.2899.2900194-3", "Cite doi/10.1016.2FS0031-0182.2800.2900192-9", "Cite doi/10.1016.2FS0065-2660.2808.2900804-3", "Cite doi/10.1016.2FS0140-6736.2860.2990675-9", "Cite doi/10.1016.2FS0162-0134.2800.2900034-9", "Cite doi/10.1016.2FS0165-6147.2897.2990649-0", "Cite doi/10.1016.2FS0169-5347.2803.2900093-4", "Cite doi/10.1016.2FS0277-9536.2899.2900436-0", "Cite doi/10.1016.2FS0956-053X.2897.2910033-2", "Cite doi/10.1016.2FS0960-9822.2801.2900438-9", "Cite doi/10.1016.2FS0961-9534.2898.2900071-3", "Cite doi/10.1016.2Fj.asd.2007.06.003", "Cite doi/10.1016.2Fj.ces.2005.10.017", "Cite doi/10.1016.2Fj.coldregions.2004.12.002", "Cite doi/10.1016.2Fj.compfluid.2005.07.005", "Cite doi/10.1016.2Fj.crpv.2003.09.023", "Cite doi/10.1016.2Fj.crvi.2005.04.004", "Cite doi/10.1016.2Fj.cub.2007.11.027", "Cite doi/10.1016.2Fj.cub.2008.10.025", "Cite doi/10.1016.2Fj.cub.2008.10.028", "Cite doi/10.1016.2Fj.cub.2009.01.023", "Cite doi/10.1016.2Fj.energy.2004.03.064", "Cite doi/10.1016.2Fj.energy.2004.07.003", "Cite doi/10.1016.2Fj.epsl.2004.09.005", "Cite doi/10.1016.2Fj.epsl.2006.04.025", "Cite doi/10.1016.2Fj.geobios.2006.02.001", "Cite doi/10.1016.2Fj.gloenvcha.2003.10.007", "Cite doi/10.1016.2Fj.gloenvcha.2003.10.009", "Cite doi/10.1016.2Fj.gr.2007.10.001", "Cite doi/10.1016.2Fj.jcrimjus.2006.11.016", "Cite doi/10.1016.2Fj.jebo.2006.05.017", "Cite doi/10.1016.2Fj.jfca.2009.03.001", "Cite doi/10.1016.2Fj.jinorgbio.2005.02.004", "Cite doi/10.1016.2Fj.jsbmb.2008.03.030", "Cite doi/10.1016.2Fj.mrrev.2007.09.001", "Cite doi/10.1016.2Fj.nurt.2007.01.013", "Cite doi/10.1016.2Fj.optlastec.2008.12.020", "Cite doi/10.1016.2Fj.palaeo.2006.02.021", "Cite doi/10.1016.2Fj.palaeo.2006.06.040", "Cite doi/10.1016.2Fj.palaeo.2007.05.023","Cite doi/10.1016.2Fj.palaeo.2008.12.015", "Cite doi/10.1016.2Fj.palaeo.2009.02.009", "Cite doi/10.1016.2Fj.palaeo.2009.02.010", "Cite doi/10.1016.2Fj.palaeo.2009.02.011", "Cite doi/10.1016.2Fj.palaeo.2009.02.012", "Cite doi/10.1016.2Fj.palaeo.2009.02.014", "Cite doi/10.1016.2Fj.palaeo.2009.02.015", "Cite doi/10.1016.2Fj.palaeo.2009.02.016", "Cite doi/10.1016.2Fj.palaeo.2009.02.017", "Cite doi/10.1016.2Fj.palwor.2007.08.002", "Cite doi/10.1016.2Fj.parco.2005.07.005", "Cite doi/10.1016.2Fj.psyneuen.2008.09.017", "Cite doi/10.1016.2Fj.psyneuen.2009.02.015", "Cite doi/10.1016.2Fj.tplants.2005.07.006", "Cite doi/10.1016.2Fj.tree.2003.08.009", "Cite doi/10.1016.2Fj.tree.2004.10.012", "Cite doi/10.1016.2Fj.tree.2008.10.006", "Cite doi/10.1016.2Fj.tree.2009.01.003", "Cite doi/10.1016.2Fj.ympev.2005.08.017", "Cite doi/10.1016/j.epsl.2006.04.025", "Cite doi/10.1016/j.palaeo.2009.02.016", "Cite doi/10.1017.2FS001675680100509X", "Cite doi/10.1017.2FS0016756801006252", "Cite doi/10.1017.2FS0140525X04210044", "Cite doi/10.1021.2Fja00055a073", "Cite doi/10.1021.2Fja00271a043", "Cite doi/10.1021.2Fja01652a057", "Cite doi/10.1023.2FA:1005324621274", "Cite doi/10.1023.2FA:1012029312055", "Cite doi/10.1023.2FA:1013038920600");

function nextPage(){
global $citeDois, $page;
	if ($page == 'Template:' . $citeDois[0]) exit; else return 'Template:' . $citeDois[0];
	print "\n\n\n\n\n\n\n\n";
	global $citeDois;
	return "Template:" . array_shift($citeDois);
}

$page = nextPage();
$linkto2 = '2';
include("/home/verisimilus/public_html/Bot/DOI_bot/expand$linkto2.php");