<?php
declare(strict_types=1);

const NULL_DOI_ANNOYING = // TODO - manually check these from time to time
array(
'10.1511/2006.61.412' => TRUE,
'10.1515/vzoo-2017-0029' => TRUE,
'10.2136/sh2012-53-6-lf' => TRUE,
'10.2225/vol9-issue3-fulltext-15' => TRUE,
'10.3134/ehtj.09.001' => TRUE,
'10.31754/2409-6105-2019-4-9-18' => TRUE,
'10.4103/0975-8844.103507' => TRUE,
'10.4435/BSPI.2015.1' => TRUE,
'10.4435/BSPI.2017.08' => TRUE,
'10.4435/BSPI.2017.19' => TRUE,
'10.4435/BSPI.2018.11' => TRUE,
'10.5852/cr-palevol2022v21a20' => TRUE,
'10.5852/cr-palevol2022v21a23' => TRUE,
'10.5852/cr-palevol2022v21a38' => TRUE,
'10.5852/cr-palevol2022v21a41' => TRUE,
'10.5852/cr-palevol2022v21a6' => TRUE,
'10.5852/cr-palevol2023v22a23' => TRUE,
);
const NULL_DOI_LIST =
array(
 /** Double check before removing - for example, these are liars - see NULL_DOI_ANNOYING above **/
'10.1511/2006.61.412' => TRUE, // goes to wrong page
'10.1515/vzoo-2017-0029' => TRUE,
'10.2136/sh2012-53-6-lf' => TRUE, // published landing page
'10.2225/vol9-issue3-fulltext-15' => TRUE, // "Forbid"
'10.3134/ehtj.09.001' => TRUE, // Spam  site
'10.31754/2409-6105-2019-4-9-18' => TRUE, // Nothing page
'10.4103/0975-8844.103507' => TRUE,
'10.4435/BSPI.2015.1' => TRUE, // spam site
'10.4435/BSPI.2017.08' => TRUE,
'10.4435/BSPI.2017.19' => TRUE,
'10.4435/BSPI.2018.11' => TRUE,
'10.5852/cr-palevol2022v21a20' => TRUE,
'10.5852/cr-palevol2022v21a23' => TRUE,
'10.5852/cr-palevol2022v21a38' => TRUE,
'10.5852/cr-palevol2022v21a41' => TRUE,
'10.5852/cr-palevol2022v21a6' => TRUE,
'10.5852/cr-palevol2023v22a23' => TRUE,
/**    **    **    **    **    **   end annoying    **    **    **    **    **    **/
'10.1001/jama.275.17.1339' => TRUE,
'10.1007/BF00162691' => TRUE,
'10.1007/BF00162818' => TRUE,
'10.1007/BF00182435' => TRUE,
'10.1007/BF00183519' => TRUE,
'10.1007/BF00190980' => TRUE,
'10.1007/BF00202951' => TRUE,
'10.1007/s10783-007-9033-2' => TRUE,
'10.1017/CBO9780511607141' => TRUE,
'10.1017/cbo9780511610486.005' => TRUE,
'10.1017/cbo9781139696562.001' => TRUE,
'10.1023/A:1004047600718' => TRUE,
'10.1023/A:1017936319069' => TRUE,
'10.1023/A:1021366131934' => TRUE,
'10.1036/0071422803' => TRUE,
'10.1038/npre.2012.7041' => TRUE,
'10.1042/csb0001011' => TRUE,
'10.1068/p2952' => TRUE,
'10.1080/10503309912331332801' => TRUE,
'10.1093/cq/54.2.630' => TRUE,
'10.1093/em/26.1.122' => TRUE,
'10.1093/ml/21.2.143' => TRUE,
'10.1093/phr/115.1.12' => TRUE,
'10.1093/phr/115.2.191' => TRUE,
'10.1106/X21V-YQKU-PMKP-XGTP' => TRUE,
'10.11157/rsrr1-1-10' => TRUE,
'10.11157/rsrr1-2-412' => TRUE,
'10.11160/bah.79' => TRUE,
'10.1128/jmbe.v11.i1.154' => TRUE,
'10.1136/vr.103.4.64' => TRUE,
'10.1136/vr.118.9.251-b' => TRUE,
'10.1136/vr.123.6.142' => TRUE,
'10.1136/vr.136.14.350' => TRUE,
'10.1136/vr.83.20.528-a' => TRUE,
'10.1136/vr.90.3.53' => TRUE,
'10.1137/1.9780898719512.ch1' => TRUE,
'10.1177/026635549100900202' => TRUE,
'10.1177/0583102405059054' => TRUE,
'10.1177/104239159100300102' => TRUE,
'10.1177/14634990122228638' => TRUE,
'10.1177/154596839701100104' => TRUE,
'10.1191/0959683606hol981rr' => TRUE,
'10.1215/lt-18350307-TC-JFR-01' => TRUE,
'10.1225/F00503' => TRUE,
'10.1258/j.jmb.2005.04-49' => TRUE,
'10.12744/ijnpt.2017.1.0002-0010' => TRUE,
'10.12788/ajo.2018.0018' => TRUE,
'10.13176/11.106' => TRUE,
'10.13176/11.54' => TRUE,
'10.1336/0313268762' => TRUE,
'10.1360/982005-575' => TRUE,
'10.1360/aps050082' => TRUE,
'10.1379/1466-1268(2000)005<0098:mhdhco>2.0.co;2' => TRUE,
'10.1379/1466-1268(2001)006<0377:goapao>2.0.co;2' => TRUE,
'10.1379/1466-1268(2001)006<0377:GOAPAO>2.0.CO;2' => TRUE,
'10.1385/1-59745-395-1:163' => TRUE,
'10.1385/NMM:8:1-2:217' => TRUE,
'10.14203/mri.v24i0.400' => TRUE,
'10.14240/jmhs.v2i3.32' => TRUE,
'10.14240/jmhs.v5i1.81' => TRUE,
'10.14334/wartazoa.v27i4.1692' => TRUE,
'10.14429/dsj.33.6188' => TRUE,
'10.14429/dsj.53.2282' => TRUE,
'10.14429/dsj.60.344' => TRUE,
'10.14496/dia.7104343513.14' => TRUE,
'10.1489/1544-581X(2004)072<0169:LOLSTP>2.0.CO;2' => TRUE,
'10.15026/57434' => TRUE,
'10.1515/crll.1878.84.242' => TRUE,
'10.1525/fq.1955.9.3.04a00070' => TRUE,
'10.1525/fq.1962.15.4.04a00060' => TRUE,
'10.1525/fq.1972.26.1.04a00030' => TRUE,
'10.1525/jps.1975.5.1-2.00p0373x' => TRUE,
'10.1525/ncl.1955.9.4.99p02537' => TRUE,
'10.15366/secuencias2018.48.001' => TRUE,
'10.15421/201683' => TRUE,
'10.1560/1blk-b1rt-xb11-bwjh' => TRUE,
'10.1560/1BLK-B1RT-XB11-BWJH' => TRUE,
'10.1560/71EQ-CNDF-K3MQ-XYTA' => TRUE,
'10.1560/DJXH-QX0M-5P0H-DLMW' => TRUE,
'10.1560/G2L1-8U80-5XNQ-G38C' => TRUE,
'10.1560/H0A3-JJBU-RX53-WMXJ' => TRUE,
'10.1560/IJES.56.2-4.217' => TRUE,
'10.1560/IJPS_54_3_169' => TRUE,
'10.1560/IJPS.55.1.1' => TRUE,
'10.1560/IJPS.55.3-4.207' => TRUE,
'10.1560/IJPS.56.1-2.1' => TRUE,
'10.1560/IJPS.56.4.341' => TRUE,
'10.1560/IJPS.57.1-2.103' => TRUE,
'10.1560/IJPS.57.1-2.35' => TRUE,
'10.1560/IJPS.57.4.303' => TRUE,
'10.1560/IJPS.57.4.329' => TRUE,
'10.1560/IJPS.60.1-2.65' => TRUE,
'10.1560/rrj4-hu15-8bfm-wauk' => TRUE,
'10.16893/IAFBTAC.22.8' => TRUE,
'10.17122/ogbus-2017-6-6-19' => TRUE,
'10.17159/2413-3051/2013/v24i3a3138' => TRUE,
'10.17312/harringtonparkpress/2014.09.msws.007' => TRUE,
'10.17312/harringtonparkpress/2014.09.msws.010' => TRUE,
'10.17402/205' => TRUE,
'10.17694/bajece.06954' => TRUE,
'10.17795/bhs-35330' => TRUE,
'10.17851/2358-9787.26.3.75-100' => TRUE,
'10.18052/www.scipress.com/ILSHS.53.60' => TRUE,
'10.18226/21789061.v11i2p400' => TRUE,
'10.18282/amor.v2.i4.58' => TRUE,
'10.18520/cs/v110/i6/996-999' => TRUE,
'10.18520/cs/v112/i01/52-61' => TRUE,
'10.18551/issn1997-0749.2014-06' => TRUE,
'10.18809/jbms.2015.0111' => TRUE,
'10.18809/jbms.2016.0108' => TRUE,
'10.19137/qs.v19i1.963' => TRUE,
'10.20960/nh.27' => TRUE,
'10.20960/nh.559' => TRUE,
'10.21040/eom/2016.2.7' => TRUE,
'10.21082/blpn.v12n2.2006.p83-88' => TRUE,
'10.21154/justicia.v12i2.328' => TRUE,
'10.21271/ZJPAS.31.2.12' => TRUE,
'10.2140/gtm.2004.7.431' => TRUE,
'10.21599/atjir.15384' => TRUE,
'10.2223/JPED.2083' => TRUE,
'10.22251/jlcci.2020.20.6.835' => TRUE,
'10.22353/mjbs.2004.02.13' => TRUE,
'10.22520/tubaked.2004-2.0007' => TRUE,
'10.2307/3818115' => TRUE,
'10.2307/jthought.43.1-2.55' => TRUE,
'10.23918/vesal2019.a7' => TRUE,
'10.24109/2176-6681.rbep.81i198.946' => TRUE,
'10.24115/S2446-6220202173A1397p.245-252' => TRUE,
'10.24321/0019.5138.201906' => TRUE,
'10.2466/PMS.99.7.1239-1242' => TRUE,
'10.2466/PR0.67.5.35-42' => TRUE,
'10.2466/PR0.69.8.1139-1146' => TRUE,
'10.2466/pr0.71.8.1064-1066' => TRUE,
'10.2466/PR0.85.5.67-77' => TRUE,
'10.2466/PR0.96.4.1015-1021' => TRUE,
'10.26442/terarkh2018901089-93' => TRUE,
'10.26515/rzsi/v117/i4/2017/121400' => TRUE,
'10.26628/ps.v90i5.917' => TRUE,
'10.26687/archnet-ijar.v10i2.962' => TRUE,
'10.2979/NWS.1997.9.2.193' => TRUE,
'10.2979/NWS.1998.10.1.79' => TRUE,
'10.2979/NWS.1998.10.3.224' => TRUE,
'10.2979/NWS.1999.11.1.118' => TRUE,
'10.2979/NWS.2000.12.1.84' => TRUE,
'10.2979/NWS.2002.14.2.18' => TRUE,
'10.2979/NWS.2003.15.3.145' => TRUE,
'10.2979/NWS.2005.17.1.119' => TRUE,
'10.2979/NWS.2006.18.2.24' => TRUE,
'10.30699/pjas.2.6.53' => TRUE,
'10.31096/wua033-pls90b070' => TRUE,
'10.31421/IJHS/12/1/622' => TRUE,
'10.3149/csm.0302.160' => TRUE,
'10.3149/CSM.0401.63' => TRUE,
'10.3149/CSM.0502.179' => TRUE,
'10.3149/jmh.0301.1' => TRUE,
'10.3149/jmh.0701.59' => TRUE,
'10.3149/jmh.0801.41' => TRUE,
'10.3149/jmh.0803.254' => TRUE,
'10.3149/jmh.1002.153' => TRUE,
'10.3149/jms.0703.353' => TRUE,
'10.3149/jms.0703.391' => TRUE,
'10.3149/jms.0802.213' => TRUE,
'10.3149/jms.0803.419' => TRUE,
'10.3149/jms.0902.183' => TRUE,
'10.3149/jms.0902.205' => TRUE,
'10.3149/jms.1002.209' => TRUE,
'10.3149/jms.1003.361' => TRUE,
'10.3149/jms.1201.25' => TRUE,
'10.3149/jms.1202.103' => TRUE,
'10.3149/jms.1203.173' => TRUE,
'10.3149/jms.1402.145' => TRUE,
'10.3149/jms.1402.191' => TRUE,
'10.3149/jms.1502.120' => TRUE,
'10.3149/jms.1602.124' => TRUE,
'10.3149/jms.1703.210' => TRUE,
'10.3149/jms.1801.63' => TRUE,
'10.3149/jms.1802.179' => TRUE,
'10.3149/jms.1803.218' => TRUE,
'10.3149/jms.1803.238' => TRUE,
'10.3149/jms.1901.37' => TRUE,
'10.3149/jms.2001.16' => TRUE,
'10.3149/jms.2003.179' => TRUE,
'10.3149/jms.2003.243' => TRUE,
'10.3149/jms.2101.24' => TRUE,
'10.3149/jms.2102.127' => TRUE,
'10.3149/jms.2102.206' => TRUE,
'10.3149/jms.2201.53' => TRUE,
'10.3149/jms.2201.64' => TRUE,
'10.3149/jms.2203.222' => TRUE,
'10.31538/nzh.v3i3.1021' => TRUE,
'10.31646/wa.252' => TRUE,
'10.3232/REB.2017.V4.N8.3069' => TRUE,
'10.3320/1.2759009' => TRUE,
'10.33513/JSPB/1801-03' => TRUE,
'10.3369/tethys.2011.8.06' => TRUE,
'10.34189/hbv.102.002' => TRUE,
'10.36076/ppj.2018.5.E573' => TRUE,
'10.36251/josi.136' => TRUE,
'10.36251/josi.40' => TRUE,
'10.3724/SP.J.1245.2011.00001' => TRUE,
'10.37837/2707-7683-2020-16' => TRUE,
'10.3828/978-0-85323-106-6' => TRUE,
'10.3828/978-0-85323-605-4' => TRUE,
'10.3828/978-0-85323-752-5' => TRUE,
'10.3846/cpc.2017.286' => TRUE,
'10.3920/978-90-8686-728-8_4' => TRUE,
'10.4056/sigs.1072907' => TRUE,
'10.4056/sigs.1113067' => TRUE,
'10.4056/sigs.1283367' => TRUE,
'10.4056/sigs.2014648' => TRUE,
'10.4056/sigs.2054696' => TRUE,
'10.4056/sigs.2225018' => TRUE,
'10.4056/sigs.23264' => TRUE,
'10.4056/sigs.2615838' => TRUE,
'10.4056/sigs.31864' => TRUE,
'10.4056/sigs.32535' => TRUE,
'10.4056/sigs.35575' => TRUE,
'10.4056/sigs.42644' => TRUE,
'10.4056/sigs.681272' => TRUE,
'10.4056/sigs.821804' => TRUE,
'10.4056/sigs.942153' => TRUE,
'10.4103/0255-0857.38850' => TRUE,
'10.4103/0973-1229.104495' => TRUE,
'10.4103/0973-1229.109335' => TRUE,
'10.4103/0973-1229.109343' => TRUE,
'10.4103/0973-1229.130283' => TRUE,
'10.4103/0973-1229.34714' => TRUE,
'10.4103/0973-1229.51213' => TRUE,
'10.4103/0973-1229.87261' => TRUE,
'10.4103/0974-9233.164615' => TRUE,
'10.4103/1596-4078.182319' => TRUE,
'10.4103/2278-330X.110506' => TRUE,
'10.4169/002557010x529815' => TRUE,
'10.4169/002557010X529815' => TRUE,
'10.4267/pollution-atmospherique.4936' => TRUE,
'10.46426/jp2kp.v20i1.42' => TRUE,
'10.46426/jp2kp.v20i2.49' => TRUE,
'10.5047/meep.2019.00701.0001' => TRUE,
'10.51437/jgns.v1i1.29' => TRUE,
'10.51437/jgns.v1i1' => TRUE,
'10.5334/sta.az' => TRUE,
'10.5428/pcar20120511' => TRUE,
'10.5465/AMR.2011.65554783' => TRUE,
'10.5558/tfc86339-3' => TRUE,
'10.5581/1516-8484.20110123' => TRUE,
'10.5604/01.3001.0012.8474' => TRUE,
'10.5604/20815735.1195358' => TRUE,
'10.5604/20831862.1144420' => TRUE,
'10.5604/20842937.1134333' => TRUE,
'10.5812/ircmj.9588' => TRUE,
'10.6019/blueprint_20130405' => TRUE,
'10.62015/np.2002.v10.503' => TRUE,
'10.7146/cns.v6i0.122249' => TRUE,
'10.7146/cns.v6i0.122251' => TRUE,
'10.7182/prtr.1.6.1.f04016025hh795up' => TRUE,
'10.7313/upo9781904761679.011' => TRUE,
'10.7313/upo9781907284991.018' => TRUE,
'10.7454/irhs.v1i1.50' => TRUE,
'10.7556/jaoa' => TRUE,
'10.7575/aiac.ijalel.v.6n.3p.71' => TRUE,
'10.9775/kvfd.2010.2081' => TRUE,
);

const NULL_DOI_BUT_GOOD = array(  // TODO - these need to be manually double checked once in a great while.  Note that a failed url on one computer does not mean it is gone, it might just be you
'10.1002/047084289X.rt358.pub2' => TRUE,
'10.1002/14651858.CD001431.pub5' => TRUE,
'10.1002/14651858.CD015477' => TRUE,
'10.1002/ps.2259' => TRUE,
'10.1007/3-540-46145-0_17' => TRUE,
'10.1007/978-94-024-1120-1_7' => TRUE,
'10.1007/s002340100589' => TRUE,
'10.1007/s10914-016-9364-7' => TRUE,
'10.1007/s11255-013-0410-6' => TRUE,
'10.1007/s40263-014-0163-5' => TRUE,
'10.1016/j.amjmed.2023.10.022' => TRUE,
'10.1016/j.biochi.2011.07.001' => TRUE,
'10.1016/j.copsyc.2019.06.024' => TRUE,
'10.1016/j.drugalcdep.2011.12.007' => TRUE,
'10.1016/j.earscirev.2020.103286' => TRUE,
'10.1016/j.icarus.2014.03.018' => TRUE,
'10.1016/S0021-9258(20)80600-0' => TRUE,
'10.1017/9781108890960' => TRUE,
'10.1017/CBO9780511498282' => TRUE,
'10.1017/CBO9781107415416' => TRUE,
'10.1017/CBO9781139062404' => TRUE,
'10.1017/S0020859000111332' => TRUE,
'10.1017/S0021875811000508' => TRUE,
'10.1017/s0022149x17000384' => TRUE,
'10.1017/S002246340300002X' => TRUE,
'10.1017/S0025100306002659' => TRUE,
'10.1017/S0025100306002830' => TRUE,
'10.1017/S0026749X00015845' => TRUE,
'10.1017/S0030605316001228' => TRUE,
'10.1017/S0043887119000157' => TRUE,
'10.1017/S0047404502020286' => TRUE,
'10.1017/S0950268811002615' => TRUE,
'10.1038/s42003-022-04132-y' => TRUE,
'10.1038/scientificamerican1062-93' => TRUE,
'10.1046/j.1360-0443.2003.00422.x' => TRUE,
'10.1046/j.1365-294x.2003.01965.x' => TRUE,
'10.1051/limn/2018030' => TRUE,
'10.1073/pnas.0711986105' => TRUE,
'10.1073/pnas.0901808106' => TRUE,
'10.1073/pnas.1817138116' => TRUE,
'10.1073/pnas.2208661120' => TRUE,
'10.1075/lal.37.06ch' => TRUE,
'10.1080/00277738.2016.1159450' => TRUE,
'10.1080/009059999109037' => TRUE,
'10.1080/02699050110119817' => TRUE,
'10.1080/03066150801983402' => TRUE,
'10.1080/07418820701485395' => TRUE,
'10.1080/08912963.2016.1166360' => TRUE,
'10.1080/13697130802054078' => TRUE,
'10.1080/17512433.2019.1637731' => TRUE,
'10.1086/tcj.57.20066240' => TRUE,
'10.1089/neu.2008.0461' => TRUE,
'10.1089/neu.2008.0586' => TRUE,
'10.1093/acref/9780195301731.013.38526' => TRUE,
'10.1093/acref/9780195301731.013.41463' => TRUE,
'10.1093/acref/9780195301731.013.45639' => TRUE,
'10.1093/acref/9780195301731.013.78541' => TRUE,
'10.1093/acrefore/9780190201098.013.1357' => TRUE,
'10.1093/acrefore/9780190228620.013.557' => TRUE,
'10.1093/acrefore/9780190228620.013.699' => TRUE,
'10.1093/acrefore/9780199340378.013.382' => TRUE,
'10.1093/acrefore/9780199340378.013.75' => TRUE,
'10.1093/acrefore/9780199389414.013.224' => TRUE,
'10.1093/aesa/10.1.1' => TRUE,
'10.1093/anb/9780198606697.001.0001/anb-9780198606697-e-1800262' => TRUE,
'10.1093/anb/9780198606697.article.0801438' => TRUE,
'10.1093/anb/9780198606697.article.1302612' => TRUE,
'10.1093/anb/9780198606697.article.1800262' => TRUE,
'10.1093/anb/9780198606697.article.1803850' => TRUE,
'10.1093/anb/9780198606697.article.2000789' => TRUE,
'10.1093/benz/9780199773787.article.B00145199' => TRUE,
'10.1093/benz/9780199773787.article.B00183827' => TRUE,
'10.1093/gao/9781884446054.article.T082129' => TRUE,
'10.1093/gao/9781884446054.article.t085978' => TRUE,
'10.1093/gao/9781884446054.article.T2085714' => TRUE,
'10.1093/gmo/9781561592630.article.00904' => TRUE,
'10.1093/gmo/9781561592630.article.02296' => TRUE,
'10.1093/gmo/9781561592630.article.03692' => TRUE,
'10.1093/gmo/9781561592630.article.05207' => TRUE,
'10.1093/gmo/9781561592630.article.23902' => TRUE,
'10.1093/gmo/9781561592630.article.25557' => TRUE,
'10.1093/gmo/9781561592630.article.26038' => TRUE,
'10.1093/gmo/9781561592630.article.26981' => TRUE,
'10.1093/gmo/9781561592630.article.29523' => TRUE,
'10.1093/gmo/9781561592630.article.29991' => TRUE,
'10.1093/gmo/9781561592630.article.40055' => TRUE,
'10.1093/gmo/9781561592630.article.40060' => TRUE,
'10.1093/gmo/9781561592630.article.42158' => TRUE,
'10.1093/gmo/9781561592630.article.45738' => TRUE,
'10.1093/gmo/9781561592630.article.A2242442' => TRUE,
'10.1093/gmo/9781561592630.article.J095300' => TRUE,
'10.1093/gmo/9781561592630.article.J441700' => TRUE,
'10.1093/gmo/9781561592630.article.L2232256' => TRUE,
'10.1093/gmo/9781561592630.article.L2294727' => TRUE,
'10.1093/gmo/9781561592630.article.O002751' => TRUE,
'10.1093/gmo/9781561592630.article.O008391' => TRUE,
'10.1093/gmo/9781561592630.article.O903864' => TRUE,
'10.1093/gmo/9781561592630.article.O904536' => TRUE,
'10.1093/molbev/msr044' => TRUE,
'10.1093/musqtl/gdw009' => TRUE,
'10.1093/odnb/9780198614128.013.107316' => TRUE,
'10.1093/odnb/9780198614128.013.108196' => TRUE,
'10.1093/ref:odnb/101006' => TRUE,
'10.1093/ref:odnb/101214' => TRUE,
'10.1093/ref:odnb/10191' => TRUE,
'10.1093/ref:odnb/103877' => TRUE,
'10.1093/ref:odnb/11650' => TRUE,
'10.1093/ref:odnb/12904' => TRUE,
'10.1093/ref:odnb/12950' => TRUE,
'10.1093/ref:odnb/12952' => TRUE,
'10.1093/ref:odnb/15029' => TRUE,
'10.1093/ref:odnb/21353' => TRUE,
'10.1093/ref:odnb/22444' => TRUE,
'10.1093/ref:odnb/22460' => TRUE,
'10.1093/ref:odnb/26056' => TRUE,
'10.1093/ref:odnb/26563' => TRUE,
'10.1093/ref:odnb/27347' => TRUE,
'10.1093/ref:odnb/29929' => TRUE,
'10.1093/ref:odnb/30133' => TRUE,
'10.1093/ref:odnb/30926' => TRUE,
'10.1093/ref:odnb/31166' => TRUE,
'10.1093/ref:odnb/31416' => TRUE,
'10.1093/ref:odnb/32917' => TRUE,
'10.1093/ref:odnb/32953' => TRUE,
'10.1093/ref:odnb/33171' => TRUE,
'10.1093/ref:odnb/33272' => TRUE,
'10.1093/ref:odnb/33369' => TRUE,
'10.1093/ref:odnb/34232' => TRUE,
'10.1093/ref:odnb/34349' => TRUE,
'10.1093/ref:odnb/34407' => TRUE,
'10.1093/ref:odnb/35778' => TRUE,
'10.1093/ref:odnb/35966' => TRUE,
'10.1093/ref:odnb/37382' => TRUE,
'10.1093/ref:odnb/38460' => TRUE,
'10.1093/ref:odnb/39057' => TRUE,
'10.1093/ref:odnb/39831' => TRUE,
'10.1093/ref:odnb/4556' => TRUE,
'10.1093/ref:odnb/45776' => TRUE,
'10.1093/ref:odnb/47538' => TRUE,
'10.1093/ref:odnb/49154' => TRUE,
'10.1093/ref:odnb/49395' => TRUE,
'10.1093/ref:odnb/49417' => TRUE,
'10.1093/ref:odnb/51012' => TRUE,
'10.1093/ref:odnb/51599' => TRUE,
'10.1093/ref:odnb/52455' => TRUE,
'10.1093/ref:odnb/56108' => TRUE,
'10.1093/ref:odnb/56279' => TRUE,
'10.1093/ref:odnb/5742' => TRUE,
'10.1093/ref:odnb/610' => TRUE,
'10.1093/ref:odnb/61643' => TRUE,
'10.1093/ref:odnb/66180' => TRUE,
'10.1093/ref:odnb/66775' => TRUE,
'10.1093/ref:odnb/68196' => TRUE,
'10.1093/ref:odnb/703' => TRUE,
'10.1093/ref:odnb/71981' => TRUE,
'10.1093/ref:odnb/73466' => TRUE,
'10.1093/ref:odnb/7418' => TRUE,
'10.1093/ref:odnb/74876' => TRUE,
'10.1093/ref:odnb/77340' => TRUE,
'10.1093/ref:odnb/7960' => TRUE,
'10.1093/ref:odnb/8581' => TRUE,
'10.1093/ref:odnb/93823' => TRUE,
'10.1093/ref:odnb/95151' => TRUE,
'10.1093/ww/9780199540884.013.24803' => TRUE,
'10.1093/ww/9780199540884.013.U244990' => TRUE,
'10.1093/ww/9780199540884.013.U284091' => TRUE,
'10.1097/00005053-200111000-00004' => TRUE,
'10.1097/00010694-199601000-00003' => TRUE,
'10.1097/00043426-200306000-00002' => TRUE,
'10.1098/rsos.170329' => TRUE,
'10.1101/2022.02.16.480423' => TRUE,
'10.1101/2024.01.20.576352' => TRUE,
'10.1101/315457' => TRUE,
'10.1101/326363' => TRUE,
'10.1101/329847' => TRUE,
'10.1101/820456' => TRUE,
'10.1101/cshperspect.a012914' => TRUE,
'10.1101/cshperspect.a012922' => TRUE,
'10.1101/cshperspect.a017715' => TRUE,
'10.1101/gr.070276.107' => TRUE,
'10.1101/gr.074492.107' => TRUE,
'10.1101/gr.076588.108' => TRUE,
'10.1101/gr.082701.108' => TRUE,
'10.1101/gr.094052.109' => TRUE,
'10.1101/gr.101386.109' => TRUE,
'10.1101/gr.116301.110' => TRUE,
'10.1101/gr.121392.111' => TRUE,
'10.1101/gr.123901.111' => TRUE,
'10.1101/gr.1311003' => TRUE,
'10.1101/gr.161968.113' => TRUE,
'10.1101/gr.162901' => TRUE,
'10.1101/gr.192799.115' => TRUE,
'10.1101/gr.196469.115' => TRUE,
'10.1101/gr.234971.118' => TRUE,
'10.1101/gr.2596504' => TRUE,
'10.1101/gr.275638.121' => TRUE,
'10.1101/gr.277663.123' => TRUE,
'10.1101/gr.3059305' => TRUE,
'10.1101/gr.3228405' => TRUE,
'10.1101/gr.3955206' => TRUE,
'10.1101/gr.5383506' => TRUE,
'10.1101/gr.6380007' => TRUE,
'10.1101/gr.6757907' => TRUE,
'10.1101/gr.7265208' => TRUE,
'10.1101/gr.9.2.195' => TRUE,
'10.1101/sqb.1963.028.01.066' => TRUE,
'10.1101/sqb.1974.038.01.006' => TRUE,
'10.1101/sqb.1983.047.01.088' => TRUE,
'10.1101/sqb.1983.047.01.132' => TRUE,
'10.1101/sqb.2010.75.037' => TRUE,
'10.1103/PhysRevLett.92.121101' => TRUE,
'10.1107/S0365110X63000797' => TRUE,
'10.1107/S0365110X65002670' => TRUE,
'10.1107/S2052252520007769' => TRUE,
'10.1108/02610150610687836' => TRUE,
'10.1111/ajag.12772' => TRUE,
'10.1111/head.12769' => TRUE,
'10.1111/IMCB.12479' => TRUE,
'10.1111/j.1572-0241.1998.02633.x' => TRUE,
'10.1111/j.1741-5705.2004.00235.x' => TRUE,
'10.1111/j.1749-818X.2008.00061.x' => TRUE,
'10.1111/jpy.12980' => TRUE,
'10.11137/2019_2_437_443' => TRUE,
'10.1117/1.1805558' => TRUE,
'10.1117/1.AP.2.3.036006' => TRUE,
'10.1117/1.JMM.13.4.041411' => TRUE,
'10.1117/12.135408' => TRUE,
'10.1117/12.2546838' => TRUE,
'10.1117/12.713914' => TRUE,
'10.1117/12.762110' => TRUE,
'10.1117/12.762514' => TRUE,
'10.1117/12.772953' => TRUE,
'10.1124/dmd.109.028605' => TRUE,
'10.1124/dmd.109.030551' => TRUE,
'10.1124/dmd.117.078980' => TRUE,
'10.1124/jpet.102.039883' => TRUE,
'10.1124/jpet.103.049882' => TRUE,
'10.1124/jpet.103.055434' => TRUE,
'10.1124/jpet.103.058602' => TRUE,
'10.1124/jpet.103.060038' => TRUE,
'10.1124/jpet.103.062984' => TRUE,
'10.1124/jpet.104.068841' => TRUE,
'10.1124/jpet.104.076653' => TRUE,
'10.1124/jpet.106.101998' => TRUE,
'10.1124/jpet.106.104463' => TRUE,
'10.1124/jpet.106.104968' => TRUE,
'10.1124/jpet.109.156711' => TRUE,
'10.1124/jpet.113.206383' => TRUE,
'10.1124/jpet.116.232876' => TRUE,
'10.1124/jpet.116.237412' => TRUE,
'10.1124/jpet.118.254508' => TRUE,
'10.1124/jpet.123.001681' => TRUE,
'10.1124/mol.109.061051' => TRUE,
'10.1124/mol.55.6.1101' => TRUE,
'10.1124/pharmrev.120.000131' => TRUE,
'10.1124/pr.111.005223' => TRUE,
'10.1124/pr.112.007054' => TRUE,
'10.1124/pr.115.012138' => TRUE,
'10.1124/pr.56.2.6' => TRUE,
'10.1124/pr.58.1.6' => TRUE,
'10.1124/pr.59.1.3' => TRUE,
'10.1126/sciadv.adf6182' => TRUE,
'10.1126/science.1105113' => TRUE,
'10.1128/mspheredirect.00157-19' => TRUE,
'10.1130/1052-5173(2003)013<4:TEFTGE>2.0.CO;2' => TRUE,
'10.1130/1052-5173(2003)13<0004:HLTOTP>2.0.CO;2' => TRUE,
'10.1130/1052-5173(2004)014<4:CAAPDO>2.0.CO;2' => TRUE,
'10.1130/1052-5173(2005)015[4:TSFHIO]2.0.CO;2' => TRUE,
'10.1130/G110GW.1' => TRUE,
'10.1130/GSAT-G198A.1' => TRUE,
'10.1130/GSAT01701A.1' => TRUE,
'10.1130/gsat01801gw.1' => TRUE,
'10.1130/GSAT01801GW.1' => TRUE,
'10.1130/GSAT01802A.1' => TRUE,
'10.1130/GSAT151A.1' => TRUE,
'10.1130/GSATG158A.1' => TRUE,
'10.1130/GSATG321A.1' => TRUE,
'10.1130/GSATG357A.1' => TRUE,
'10.1130/GSATG35A.1' => TRUE,
'10.1130/GSATG61A.1' => TRUE,
'10.1134/S003103011805009X' => TRUE,
'10.1136/adc.2004.056952' => TRUE,
'10.1136/adc.63.3.277' => TRUE,
'10.1136/adc.67.7_spec_no.808' => TRUE,
'10.1136/adc.76.6.518' => TRUE,
'10.1136/adc.83.4.353' => TRUE,
'10.1136/annrheumdis-2021-221795' => TRUE,
'10.1136/ard.2003.015925' => TRUE,
'10.1136/ard.2004.028217' => TRUE,
'10.1136/ard.2011.151191' => TRUE,
'10.1136/ard.32.6.493' => TRUE,
'10.1136/ard.36.2.121' => TRUE,
'10.1136/ard.47.1.84-b' => TRUE,
'10.1136/ard.61.suppl_2.ii70' => TRUE,
'10.1136/ard.61.suppl_3.iii8' => TRUE,
'10.1136/bjo.2005.070888' => TRUE,
'10.1136/bjo.2006.090712' => TRUE,
'10.1136/bjo.86.2.144' => TRUE,
'10.1136/bjsports-2015-095317' => TRUE,
'10.1136/bmj-2023-075294' => TRUE,
'10.1136/bmj.1.1016.917' => TRUE,
'10.1136/bmj.1.2372.1442-c' => TRUE,
'10.1136/bmj.1.2581.1523-b' => TRUE,
'10.1136/bmj.1.5011.182' => TRUE,
'10.1136/bmj.2.5042.451' => TRUE,
'10.1136/bmj.288.6435.1950' => TRUE,
'10.1136/bmj.3.5561.338' => TRUE,
'10.1136/bmj.301.6755.776' => TRUE,
'10.1136/bmj.302.6790.1465' => TRUE,
'10.1136/bmj.324.7333.329' => TRUE,
'10.1136/bmj.326.7399.1124' => TRUE,
'10.1136/bmj.330.7488.385' => TRUE,
'10.1136/bmj.331.7508.108-b' => TRUE,
'10.1136/bmj.331.7518.673' => TRUE,
'10.1136/bmj.b5465' => TRUE,
'10.1136/bmj.b800' => TRUE,
'10.1136/bmj.f288' => TRUE,
'10.1136/bmj.f360' => TRUE,
'10.1136/bmj.f3646' => TRUE,
'10.1136/bmj.f5307' => TRUE,
'10.1136/bmj.g2467' => TRUE,
'10.1136/bmj.g6661' => TRUE,
'10.1136/bmj.h3084' => TRUE,
'10.1136/bmj.h4601' => TRUE,
'10.1136/bmj.h6656' => TRUE,
'10.1136/bmj.i3857' => TRUE,
'10.1136/bmj.k351' => TRUE,
'10.1136/bmj.k3546' => TRUE,
'10.1136/bmj.m2512' => TRUE,
'10.1136/bmj.m2913' => TRUE,
'10.1136/bmj.m3021' => TRUE,
'10.1136/bmj.m3379' => TRUE,
'10.1136/bmj.m4037' => TRUE,
'10.1136/bmj.m4761' => TRUE,
'10.1136/bmj.n1058' => TRUE,
'10.1136/bmj.n1088' => TRUE,
'10.1136/bmj.n1734' => TRUE,
'10.1136/bmj.n747' => TRUE,
'10.1136/bmj.p2706' => TRUE,
'10.1136/bmjnph-2023-000789' => TRUE,
'10.1136/bmjopen-2011-000431' => TRUE,
'10.1136/bmjopen-2011-000653' => TRUE,
'10.1136/bmjopen-2017-017248' => TRUE,
'10.1136/bmjopen-2017-019376' => TRUE,
'10.1136/bmjopen-2020-042247' => TRUE,
'10.1136/emj.19.3.206' => TRUE,
'10.1136/emj.2006.035915' => TRUE,
'10.1136/gut.48.3.435' => TRUE,
'10.1136/ip.9.3.205' => TRUE,
'10.1136/jcp.11.5.406' => TRUE,
'10.1136/jcp.12.3.215' => TRUE,
'10.1136/jcp.19.3.284' => TRUE,
'10.1136/jcp.2004.019810' => TRUE,
'10.1136/jcp.2009.068874' => TRUE,
'10.1136/jcp.33.4.380' => TRUE,
'10.1136/jcp.52.4.245' => TRUE,
'10.1136/jech-2018-210838' => TRUE,
'10.1136/jmedgenet-2017-104620' => TRUE,
'10.1136/jmg.4.1.7' => TRUE,
'10.1136/jnnp-2016-315238' => TRUE,
'10.1136/jnnp-2017-317168' => TRUE,
'10.1136/jnnp-2019-321653' => TRUE,
'10.1136/jnnp.2007.133025' => TRUE,
'10.1136/jnnp.2007.139717' => TRUE,
'10.1136/jnnp.38.4.331' => TRUE,
'10.1136/jnnp.70.4.520' => TRUE,
'10.1136/practneurol-2014-001071' => TRUE,
'10.1136/rmdopen-2015-000052' => TRUE,
'10.1136/thx.2007.091223' => TRUE,
'10.1136/tobaccocontrol-2019-054940' => TRUE,
'10.1137/0215025' => TRUE,
'10.1146/annurev-polisci-050317-070830' => TRUE,
'10.11610/Connections.15.2.06' => TRUE,
'10.1163/156852700511612' => TRUE,
'10.1163/15685289760518153' => TRUE,
'10.1163/16000390-09401052' => TRUE,
'10.1163/25898833-12340008' => TRUE,
'10.1163/9789004207561_006' => TRUE,
'10.11646/zootaxa.4048.2.3' => TRUE,
'10.1167/iovs.14-15021' => TRUE,
'10.11676/qxxb2020.072' => TRUE,
'10.1172/JCI116388' => TRUE,
'10.1177/0091270009352087' => TRUE,
'10.1177/1073110517703105' => TRUE,
'10.1177/1532440015603814' => TRUE,
'10.1183/09031936.00042309' => TRUE,
'10.1183/09031936.00051108' => TRUE,
'10.1183/20734735.001918' => TRUE,
'10.1186/1999-3110-55-30' => TRUE,
'10.1186/s40850-017-0015-0' => TRUE,
'10.1186/s40850-017-0025-y' => TRUE,
'10.1186/s40850-018-0032-7' => TRUE,
'10.1186/s40850-020-00057-3' => TRUE,
'10.11865/zs.202033' => TRUE,
'10.1192/bjp.134.1.67' => TRUE,
'10.1257/aer.102.3.349' => TRUE,
'10.1261/rna.2338706' => TRUE,
'10.1261/rna.2340906' => TRUE,
'10.1261/rna.682507' => TRUE,
'10.12788/cutis.0844' => TRUE,
'10.12989/sem.2013.48.6.791' => TRUE,
'10.12989/sem.2017.62.3.365' => TRUE,
'10.12989/was.2018.27.2.137' => TRUE,
'10.13106/ijidb.2015.vol6.no1.5.' => TRUE,
'10.13169/worlrevipoliecon.11.4.0533' => TRUE,
'10.1351/pac-rec-12-05-10' => TRUE,
'10.1353/afa.2011.0012' => TRUE,
'10.1353/cli.2007.0025' => TRUE,
'10.1353/mov.2010.0012' => TRUE,
'10.1353/nlh.0.0065' => TRUE,
'10.1353/pbm.1998.0022' => TRUE,
'10.1358/dof.2007.032.09.1138229' => TRUE,
'10.1370/afm.667' => TRUE,
'10.1370/afm.754' => TRUE,
'10.1371/journal.pntd.0006929' => TRUE,
'10.1387/ijdb.052063jt' => TRUE,
'10.1400/34421' => TRUE,
'10.1400/41466' => TRUE,
'10.14241/asgp.2023.01' => TRUE,
'10.14241/asgp.2023.03' => TRUE,
'10.14241/asgp.2023.17' => TRUE,
'10.14482/memor.22.5948' => TRUE,
'10.1503/cmaj.070335' => TRUE,
'10.1503/cmaj.151104' => TRUE,
'10.1515/anre-2015-0009' => TRUE,
'10.1515/jsall-2016-0003' => TRUE,
'10.15171/apb.2016.033' => TRUE,
'10.15171/apb.2018.062' => TRUE,
'10.15171/apb.2019.008' => TRUE,
'10.1523/JNEUROSCI.0955-05.2005' => TRUE,
'10.1523/JNEUROSCI.16-11-03630.1996' => TRUE,
'10.1523/JNEUROSCI.19-18-07770.1999' => TRUE,
'10.1523/JNEUROSCI.23-09-03944.2003' => TRUE,
'10.1523/JNEUROSCI.3728-06.2007' => TRUE,
'10.15242/IIE.E0516006' => TRUE,
'10.15253/2175-6783.20202143686' => TRUE,
'10.15298/rusentj.32.3.02' => TRUE,
'10.1542/peds.2007-3448' => TRUE,
'10.1542/peds.2012-3931' => TRUE,
'10.1542/peds.2016-3436' => TRUE,
'10.1542/peds.98.1.63' => TRUE,
'10.15446/historelo.v12n23.76565' => TRUE,
'10.15537/smj.2015.4.10210' => TRUE,
'10.1558/bsor.v41i3.22' => TRUE,
'10.1558/bsrv.v25i2.194' => TRUE,
'10.1558/firn.35029' => TRUE,
'10.1558/ijsnr.v4i2.251' => TRUE,
'10.15581/017.23.67-84' => TRUE,
'10.15585/mmwr.mm6601a6' => TRUE,
'10.15585/mmwr.mm6730a2' => TRUE,
'10.15585/mmwr.mm6803a3' => TRUE,
'10.15845/on.v33i0.152' => TRUE,
'10.15845/on.v35i0.289' => TRUE,
'10.15845/on.v36i0.394' => TRUE,
'10.15845/on.v40i0.1309' => TRUE,
'10.1590/2358-2936e2021003' => TRUE,
'10.1592/phco.19.4.306.30934' => TRUE,
'10.1600/036364416x692442' => TRUE,
'10.1633/jim.2006.37.1.083' => TRUE,
'10.1677/joe.0.1650693' => TRUE,
'10.1682/JRRD.2004.03.0293' => TRUE,
'10.1682/JRRD.2006.03.0025' => TRUE,
'10.1682/JRRD.2006.05.0041' => TRUE,
'10.1682/jrrd.2006.11.0147' => TRUE,
'10.1682/jrrd.2007.02.0034' => TRUE,
'10.1682/JRRD.2009.08.0140' => TRUE,
'10.1682/jrrd.2010.03.0024' => TRUE,
'10.1682/JRRD.2010.03.0035' => TRUE,
'10.1682/JRRD.2011.09.0183' => TRUE,
'10.1682/JRRD.2011.11.0214' => TRUE,
'10.1682/JRRD.2012.05.0096' => TRUE,
'10.1682/JRRD.2012.05.0099' => TRUE,
'10.17159/2077-4907/2021/ldd.v25.15' => TRUE,
'10.17226/10264' => TRUE,
'10.17477/JCEA.2020.19.2.216' => TRUE,
'10.17509/gea.v7i2.1725' => TRUE,
'10.17576/akad-2021-9103-12' => TRUE,
'10.17576/geo-2021-1703-06' => TRUE,
'10.17576/JKMJC-2018-3403-16' => TRUE,
'10.17576/pengurusan-2017-49-06' => TRUE,
'10.17660/actahortic.1990.279.67' => TRUE,
'10.17660/actahortic.2001.558.14' => TRUE,
'10.17660/actahortic.2005.665.48' => TRUE,
'10.17660/ActaHortic.2010.868.52' => TRUE,
'10.17660/ActaHortic.2010.879.81' => TRUE,
'10.1787/24132764' => TRUE,
'10.18520/cs/v112/i01/139-146' => TRUE,
'10.18520/cs/v112/i05/923-932' => TRUE,
'10.18520/cs/v112/i05/933-940' => TRUE,
'10.18520/v109/i6/1061-1069' => TRUE,
'10.18820/24150509/JCH42.v1.8' => TRUE,
'10.18845/tm.v28i4.2438' => TRUE,
'10.18926/AMO/30942' => TRUE,
'10.20341/gb.2018.005' => TRUE,
'10.21504/amj.v3i1.732' => TRUE,
'10.21504/amj.v3i3.1034' => TRUE,
'10.21504/amj.v4i3.1437' => TRUE,
'10.21504/amj.v5i3.1655' => TRUE,
'10.21504/amj.v6i2.1118' => TRUE,
'10.2173/bow.sompig1.01' => TRUE,
'10.2174/0929867043364757' => TRUE,
'10.2174/0929867324666170526124053' => TRUE,
'10.2174/1381612033454856' => TRUE,
'10.2174/138161210790963869' => TRUE,
'10.2174/1381612821666150514104244' => TRUE,
'10.2174/1385272054038318' => TRUE,
'10.2174/138920112800624373' => TRUE,
'10.2174/138920212803251373' => TRUE,
'10.2174/1389202918666170815144627' => TRUE,
'10.2174/156802611795371341' => TRUE,
'10.2174/1570159x14666151208113700' => TRUE,
'10.2174/1570162033352110' => TRUE,
'10.2174/1570178616666190807101012' => TRUE,
'10.2174/157340607782360353' => TRUE,
'10.2174/157488407779422302' => TRUE,
'10.2174/187152606776056706' => TRUE,
'10.2174/187152710790966641' => TRUE,
'10.21827/krisis.40.1.37054' => TRUE,
'10.21829/myb.2007.1311238' => TRUE,
'10.21897/rmvz.13' => TRUE,
'10.21897/rmvz.276' => TRUE,
'10.22059/ijmge.2012.51321' => TRUE,
'10.22059/jfadram.2012.24776' => TRUE,
'10.22161/ijels.55.21' => TRUE,
'10.22201/iih.24485004e.2012.44.35787' => TRUE,
'10.22459/FPWT.07.2008' => TRUE,
'10.22679/AVS.2021.6.2.003' => TRUE,
'10.2298/AOO1202028D' => TRUE,
'10.2298/BALC1041131M' => TRUE,
'10.2298/BALC1445037F' => TRUE,
'10.2298/BALC1445097K' => TRUE,
'10.2298/BALC1445107C' => TRUE,
'10.2298/BALC1748007K' => TRUE,
'10.2298/BALC1748123B' => TRUE,
'10.2298/BALC1748269S' => TRUE,
'10.2298/BALC1950225M' => TRUE,
'10.2298/bg20130213jecmenica' => TRUE,
'10.2298/EKA1403029R' => TRUE,
'10.2298/GEI0701061D' => TRUE,
'10.2298/GEI1701127T' => TRUE,
'10.2298/JFI1066357P' => TRUE,
'10.2298/JSC101004087S' => TRUE,
'10.2298/MPNS0712581D' => TRUE,
'10.2298/PAN160220031M' => TRUE,
'10.2298/SARH1212792K' => TRUE,
'10.2298/vsp120205002s' => TRUE,
'10.2298/ZMSDN1136369M' => TRUE,
'10.2298/ZMSDN1449901J' => TRUE,
'10.2298/ZMSDN1553739G' => TRUE,
'10.2298/ZRVI0643031K' => TRUE,
'10.2298/ZRVI1653343D' => TRUE,
'10.2307/1356853' => TRUE,
'10.2307/3677937' => TRUE,
'10.2307/j.ctt1tg5nnd' => TRUE,
'10.23940/ijpe.20.07.p9.10671077' => TRUE,
'10.23968/2500-0055-2016-1-4-18-25' => TRUE,
'10.24039/cv20142240' => TRUE,
'10.24233/sribios.3.2.2022.367' => TRUE,
'10.2478/v10273-012-0028-9' => TRUE,
'10.24894/GESN-EN.2005.62013' => TRUE,
'10.25024/kj.2011.51.4.110' => TRUE,
'10.25024/kj.2013.53.4.14' => TRUE,
'10.25100/cm.v51i2.4266' => TRUE,
'10.25911/5D63C47EE2628' => TRUE,
'10.25911/5d74e50054bb9' => TRUE,
'10.26577/eje-2018-3-832' => TRUE,
'10.2903/j.efsa.2010.1752' => TRUE,
'10.29075/9780876332764/101812/1' => TRUE,
'10.29104/phi-aqualac/2017-v9-2-10' => TRUE,
'10.29117/jcsis.2021.0290' => TRUE,
'10.29173/bluejay1721' => TRUE,
'10.2979/aft.2005.52.1.137' => TRUE,
'10.3122/jabfm.2009.06.090037' => TRUE,
'10.31318/2522-4190.2018.121.133109' => TRUE,
'10.3133/ofr77405' => TRUE,
'10.3133/pp580' => TRUE,
'10.31439/UNISCI-101' => TRUE,
'10.31439/unisci-98' => TRUE,
'10.31696/2618-7043-2021-4-1-190-211' => TRUE,
'10.3171/2009.6.JNS081161' => TRUE,
'10.31810/RSEL.52.1.5' => TRUE,
'10.3201/eid0809.010536' => TRUE,
'10.3201/eid1003.030257' => TRUE,
'10.3201/eid1110.041279' => TRUE,
'10.3205/15gmds058' => TRUE,
'10.3205/iprs000167' => TRUE,
'10.3347/kjp.2001.39.3.209' => TRUE,
'10.3347/kjp.2007.45.2.95' => TRUE,
'10.3347/kjp.2009.47.1.73' => TRUE,
'10.3347/kjp.2009.47.S.S115' => TRUE,
'10.3347/kjp.2009.47.S.S69' => TRUE,
'10.3347/kjp.2010.48.3.253' => TRUE,
'10.3347/kjp.2013.51.1.31' => TRUE,
'10.3347/kjp.2014.52.2.183' => TRUE,
'10.3347/kjp.2018.56.5.463' => TRUE,
'10.3347/kjp.2020.58.4.343' => TRUE,
'10.3347/kjp.2021.59.1.35' => TRUE,
'10.3366/hls.2013.0056' => TRUE,
'10.3389/fbuil.2015.00023' => TRUE,
'10.3389/fimmu.2018.01931' => TRUE,
'10.3389/fmicb.2013.00035' => TRUE,
'10.3389/fmicb.2014.00172' => TRUE,
'10.3389/fncom.2014.00086' => TRUE,
'10.3389/fphar.2013.00045' => TRUE,
'10.3389/fpls.2016.01201' => TRUE,
'10.3389/fpls.2017.00873' => TRUE,
'10.3389/fpls.2019.00360' => TRUE,
'10.3389/fpsyg.2021.626122' => TRUE,
'10.3389/fpsyt.2013.00027' => TRUE,
'10.3389/fpsyt.2015.00175' => TRUE,
'10.3389/fpsyt.2019.00294' => TRUE,
'10.3399/bjgp11X572427' => TRUE,
'10.3399/bjgp18X698885' => TRUE,
'10.34019/2594-8296.2019.v25.28740' => TRUE,
'10.3406/befeo.1954.5607' => TRUE,
'10.3733/ca.v060n04p180' => TRUE,
'10.3733/hilg.v11n09p493' => TRUE,
'10.3733/hilg.v16n08p361' => TRUE,
'10.3746/pnf.2017.22.2.67' => TRUE,
'10.3748/wjg.v17.i19.2365' => TRUE,
'10.3748/wjg.v18.i30.4004' => TRUE,
'10.3748/wjg.v22.i20.4794' => TRUE,
'10.3819/ccbr.2008.30003' => TRUE,
'10.3916/c52-2017-10' => TRUE,
'10.3916/C55-2018-03' => TRUE,
'10.3916/c56-2018-08' => TRUE,
'10.3929/ethz-a-000300091' => TRUE,
'10.3929/ethz-a-009933926' => TRUE,
'10.3929/ethz-a-010412935' => TRUE,
'10.3929/ethz-b-000579582' => TRUE,
'10.3929/ETHZ-B-000631043' => TRUE,
'10.4062/biomolther.2009.17.3.241' => TRUE,
'10.4062/biomolther.2012.20.5.446' => TRUE,
'10.4067/S0717-95022016000400044' => TRUE,
'10.4100/jhse.2011.63.04' => TRUE,
'10.4102/hts.v73i3.4655' => TRUE,
'10.4135/9781446216989' => TRUE,
'10.4187/respcare.03319' => TRUE,
'10.4217/OPR.2012.34.3.305' => TRUE,
'10.4310/pamq.2006.v2.n2.a3' => TRUE,
'10.4331/wjbc.v4.i4.79' => TRUE,
'10.4337/9781845421472' => TRUE,
'10.4467/22996362PZ.18.045.10403' => TRUE,
'10.4467/2450050XSNR.22.013.17026' => TRUE,
'10.48550/arXiv.math/0101195' => TRUE,
'10.48550/arXiv.math/0507227' => TRUE,
'10.5012/BKCS.2002.23.3.404' => TRUE,
'10.5012/bkcs.2010.31.02.487' => TRUE,
'10.5012/JKCS.2005.49.6.603' => TRUE,
'10.5027/andgeoV41n3-a04' => TRUE,
'10.5027/andgeoV45n3-3117' => TRUE,
'10.5027/andgeov46n1-3142' => TRUE,
'10.5027/andgeoV46n2-3130' => TRUE,
'10.5027/andgeoV47n1-3260' => TRUE,
'10.5027/andgeoV47n3-3278' => TRUE,
'10.5027/andgeoV50n2-3487' => TRUE,
'10.5040/9781350082700.ch-001' => TRUE,
'10.5139/IJASS.2012.13.1.14' => TRUE,
'10.52536/2415-8216.2023-1.01' => TRUE,
'10.5303/JKAS.2002.35.2.075' => TRUE,
'10.5303/jkas.2002.35.2.075' => TRUE,
'10.5303/JKAS.2013.46.1.41' => TRUE,
'10.5377/ruc.v1i1.6764' => TRUE,
'10.53841/bpslg.2005.6.2.124' => TRUE,
'10.5392/IJoC.2014.10.1.036' => TRUE,
'10.54097/hbem.v7i.6940' => TRUE,
'10.5424/sjar/2013112-3673' => TRUE,
'10.5479/si.00775630.183.1' => TRUE,
'10.5479/si.00775630.501.1' => TRUE,
'10.5479/si.00775630.86.1' => TRUE,
'10.5479/si.00810223.1.1' => TRUE,
'10.5479/si.00810223.38.1' => TRUE,
'10.5479/si.00810258.46.1' => TRUE,
'10.5479/si.00810266.74.1' => TRUE,
'10.5479/si.00810282.158' => TRUE,
'10.5479/si.00810282.193' => TRUE,
'10.5479/si.00810282.238' => TRUE,
'10.5479/si.00810282.284' => TRUE,
'10.5479/si.00810282.549' => TRUE,
'10.5479/si.00810282.596' => TRUE,
'10.5479/si.00963801.11-709.197' => TRUE,
'10.5479/si.00963801.114-3470.271' => TRUE,
'10.5479/si.00963801.26-1334.811' => TRUE,
'10.5479/si.00963801.35-1648.351' => TRUE,
'10.5479/si.00963801.37-1699.43' => TRUE,
'10.5479/si.00963801.48-2069.169' => TRUE,
'10.5479/si.00963801.66-2560.1' => TRUE,
'10.5479/si.00963801.96-3198.215' => TRUE,
'10.5479/si.03629236.110.i' => TRUE,
'10.5479/si.03629236.188.1' => TRUE,
'10.5479/si.03629236.208.1' => TRUE,
'10.5479/si.03629236.237.1' => TRUE,
'10.5479/si.9781935623069.39' => TRUE,
'10.5483/BMBRep.2002.35.2.248' => TRUE,
'10.5483/BMBRep.2004.37.1.122' => TRUE,
'10.5483/bmbrep.2004.37.6.741' => TRUE,
'10.5483/BMBRep.2006.39.5.626' => TRUE,
'10.5483/BMBRep.2010.43.10.688' => TRUE,
'10.5483/BMBRep.2012.45.3.159' => TRUE,
'10.5483/BMBRep.2012.45.4.259' => TRUE,
'10.5483/BMBRep.2016.49.9.141' => TRUE,
'10.5506/APhysPolB.42.2175' => TRUE,
'10.5516/NET.2009.41.5.603' => TRUE,
'10.5516/NET.2009.41.8.995' => TRUE,
'10.5534/wjmh.200073' => TRUE,
'10.5572/KOSAE.2008.24.4.439' => TRUE,
'10.5656/ksae.2009.48.1.053' => TRUE,
'10.5656/ksae.2009.48.4.467' => TRUE,
'10.5656/ksae.2012.09.0.022' => TRUE,
'10.5656/ksae.2014.01.1.075' => TRUE,
'10.5681/apb.2014.013' => TRUE,
'10.5681/jcs.2014.005' => TRUE,
'10.5710/AMGH.24.12.2013.1889' => TRUE,
'10.5757/ASCT.2014.23.2.61' => TRUE,
'10.5771/0506-7286-1968-1-93' => TRUE,
'10.5805/SFTI.2013.15.5.797' => TRUE,
'10.5805/SFTI.2016.18.4.424' => TRUE,
'10.5817/CP2019-3-5' => TRUE,
'10.5840/maritain2017334' => TRUE,
'10.5851/kosfa.2014.34.1.7' => TRUE,
'10.5897/JENE12.093' => TRUE,
'10.5958/j.2231-4547.1.2.013' => TRUE,
'10.6018/daimon/277171' => TRUE,
'10.6018/j103411' => TRUE,
'10.6241/concentric.ling.200701_33(1).0001' => TRUE,
'10.7232/iems.2013.12.2.103' => TRUE,
'10.7233/jksc.2013.63.2.029' => TRUE,
'10.7233/jksc.2015.65.6.112' => TRUE,
'10.7314/APJCP.2012.13.10.5177' => TRUE,
'10.7314/apjcp.2012.13.12.5975' => TRUE,
'10.7314/APJCP.2012.13.8.4177' => TRUE,
'10.7314/APJCP.2013.14.6.3425' => TRUE,
'10.7314/apjcp.2013.14.7.4283' => TRUE,
'10.7314/apjcp.2013.14.9.4965' => TRUE,
'10.7314/apjcp.2014.15.19.8509' => TRUE,
'10.7314/APJCP.2014.15.21.9395' => TRUE,
'10.7314/apjcp.2014.15.8.3353' => TRUE,
'10.7314/APJCP.2015.16.4.1315' => TRUE,
'10.7314/apjcp.2015.16.8.3121' => TRUE,
'10.7318/KJFC/2012.27.6.598' => TRUE,
'10.7476/9786557080009' => TRUE,
'10.7731/KIFSE.2015.29.2.039' => TRUE,
'10.7770/cuhso-v1n1-art129' => TRUE,
'10.7770/rchdcp-V3N1-art348' => TRUE,
'10.7780/kjrs.2020.36.3.2' => TRUE,
'10.8080/4020100059499' => TRUE,
'10.9755/ejfa.2018.v30.i3.1639' => TRUE,
/** We cannot add hdls to this list, since we need the final URL - see below **/
);
const NULL_HDL_BUT_KNOWN = array(  // Do not report these, since they work usually.
'10399/1967' => TRUE,
'11250/2733873' => TRUE,
'11343/55221' => TRUE,
'11380/1197669' => TRUE,
'11383/1679348' => TRUE,
'11568/926084' => TRUE,
'11573/1659661' => TRUE,
'11577/3199318' => TRUE,
'1828/7796' => TRUE,
'20.500.11850/130560' => TRUE,
'20.500.11850/137906' => TRUE,
'20.500.11850/226164' => TRUE,
'20.500.11850/631043' => TRUE,
'2108/10507' => TRUE,
'2108/10510' => TRUE,
'2108/34834' => TRUE,
'2108/59410' => TRUE,
'2318/1651769' => TRUE,
);

