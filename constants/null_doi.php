<?php
declare(strict_types=1);

const NULL_DOI_ANNOYING = // TODO - manually check these from time to time
array(
'10.1511/2006.61.412',
'10.2225/vol9-issue3-fulltext-15',
'10.31754/2409-6105-2019-4-9-18',
);
const NULL_DOI_LIST =
array(
 /** Double check before removing - for example, these are liars - see NULL_DOI_ANNOYING above **/
'10.1511/2006.61.412', // goes to wrong page
'10.2225/vol9-issue3-fulltext-15', // "Forbid"
'10.31754/2409-6105-2019-4-9-18', // Nothing page
/** end annoying **/
'10.1007/BF00162691',
'10.1007/BF00182435',
'10.1007/BF00202951',
'10.1007/s10783-007-9033-2',
'10.1017/CBO9780511607141',
'10.1017/cbo9781139696562.001',
'10.1023/A:1017936319069',
'10.1036/0071422803',
'10.1038/npre.2012.7041',
'10.1068/p2952',
'10.1080/10503309912331332801',
'10.1093/ml/21.2.143',
'10.1093/phr/115.1.12',
'10.1106/X21V-YQKU-PMKP-XGTP',
'10.11157/rsrr1-1-10',
'10.11160/bah.79',
'10.1128/jmbe.v11.i1.154',
'10.1136/vr.136.14.350',
'10.1136/vr.83.20.528-a',
'10.1136/vr.90.3.53',
'10.1137/1.9780898719512.ch1',
'10.1177/0583102405059054',
'10.1177/154596839701100104',
'10.1215/lt-18350307-TC-JFR-01',
'10.1225/F00503',
'10.1258/j.jmb.2005.04-49',
'10.12788/ajo.2018.0018',
'10.1336/0313268762',
'10.1360/982005-575',
'10.1379/1466-1268(2000)005<0098:mhdhco>2.0.co;2',
'10.1385/1-59745-395-1:163',
'10.1385/NMM:8:1-2:217',
'10.14203/mri.v24i0.400',
'10.14334/wartazoa.v27i4.1692',
'10.14429/dsj.53.2282',
'10.14429/dsj.60.344',
'10.14496/dia.7104343513.14',
'10.1489/1544-581X(2004)072<0169:LOLSTP>2.0.CO;2',
'10.15026/57434',
'10.1515/crll.1878.84.242',
'10.1525/fq.1955.9.3.04a00070',
'10.1525/fq.1962.15.4.04a00060',
'10.1525/jps.1975.5.1-2.00p0373x',
'10.1525/ncl.1955.9.4.99p02537',
'10.15366/secuencias2018.48.001',
'10.15421/201683',
'10.1560/1BLK-B1RT-XB11-BWJH',
'10.1560/71EQ-CNDF-K3MQ-XYTA',
'10.1560/DJXH-QX0M-5P0H-DLMW',
'10.1560/H0A3-JJBU-RX53-WMXJ',
'10.1560/IJES.56.2-4.217',
'10.1560/IJPS_54_3_169',
'10.1560/IJPS.55.1.1',
'10.1560/IJPS.55.3-4.207',
'10.1560/IJPS.56.1-2.1',
'10.1560/IJPS.56.4.341',
'10.1560/IJPS.56.4.341',
'10.1560/IJPS.57.1-2.103',
'10.1560/IJPS.57.1-2.103',
'10.1560/IJPS.57.4.303',
'10.1560/IJPS.57.4.329',
'10.1560/IJPS.57.4.329',
'10.1560/IJPS.60.1-2.65',
'10.1560/rrj4-hu15-8bfm-wauk',
'10.17159/2413-3051/2013/v24i3a3138',
'10.17312/harringtonparkpress/2014.09.msws.007',
'10.17312/harringtonparkpress/2014.09.msws.010',
'10.17402/205',
'10.17694/bajece.06954',
'10.18226/21789061.v11i2p400',
'10.18282/amor.v2.i4.58',
'10.18809/jbms.2015.0111',
'10.18809/jbms.2016.0108',
'10.19137/qs.v19i1.963',
'10.20960/nh.27',
'10.20960/nh.559',
'10.21082/blpn.v12n2.2006.p83-88',
'10.21154/justicia.v12i2.328',
'10.21599/atjir.15384',
'10.22520/tubaked.2004-2.0007',
'10.2307/3818115',
'10.2307/jthought.43.1-2.55',
'10.23918/vesal2019.a7',
'10.24109/2176-6681.rbep.81i198.946',
'10.24115/S2446-6220202173A1397p.245-252',
'10.24321/0019.5138.201906',
'10.2466/pr0.71.8.1064-1066',
'10.26442/terarkh2018901089-93',
'10.2979/NWS.1997.9.2.193',
'10.2979/NWS.2006.18.2.24',
'10.31096/wua033-pls90b070',
'10.31318/2522-4190.2018.121.133109',
'10.3149/csm.0302.160',
'10.3149/CSM.0401.63',
'10.3149/CSM.0502.179',
'10.3149/jmh.0801.41',
'10.3149/jmh.0803.254',
'10.3149/jms.0703.353',
'10.3149/jms.1402.145',
'10.3149/jms.1703.210',
'10.31646/wa.252',
'10.3232/REB.2017.V4.N8.3069',
'10.3320/1.2759009',
'10.36076/ppj.2018.5.E573',
'10.3724/SP.J.1245.2011.00001',
'10.3828/978-0-85323-106-6',
'10.3828/978-0-85323-752-5',
'10.4103/0255-0857.38850',
'10.4103/0973-1229.34714',
'10.4103/0973-1229.51213',
'10.4103/0973-1229.87261',
'10.4103/0974-9233.164615',
'10.4103/1596-4078.182319',
'10.4103/2278-330X.110506',
'10.4267/pollution-atmospherique.4936',
'10.4435/BSPI.2015.1',
'10.4435/BSPI.2017.08',
'10.4435/BSPI.2017.19',
'10.4435/BSPI.2018.11',
'10.5047/meep.2019.00701.0001',
'10.51437/jgns.v1i1.29',
'10.51437/jgns.v1i1',
'10.5334/sta.az',
'10.5428/pcar20120511',
'10.5581/1516-8484.20110123',
'10.5604/01.3001.0012.8474',
'10.5604/20831862.1144420',
'10.5604/20842937.1134333',
'10.7313/upo9781904761679.011',
'10.7556/jaoa',
'10.7575/aiac.ijalel.v.6n.3p.71',
);

const NULL_DOI_BUT_GOOD = array(  // TODO - these need to be manually double checked once in a great while.  Note that a failed url on one computer does not mean it is gone, it might just be you
'10.1017/S0025100306002659',
'10.1093/anb/9780198606697.article.1302612',
'10.1093/ref:odnb/12950',
'10.1093/ref:odnb/34349',
'10.1093/ref:odnb/35778',
'10.1093/ref:odnb/37382',
'10.1093/ref:odnb/45776',
'10.1093/ref:odnb/49417',
'10.1093/ref:odnb/610',
'10.1093/ref:odnb/8581',
'10.1097/00043426-200306000-00002',
'10.1103/PhysRevLett.92.121101',
'10.1107/S2052252520007769',
'10.1124/jpet.103.049882',
'10.1124/jpet.103.055434',
'10.1124/jpet.103.060038',
'10.1124/jpet.104.068841',
'10.1124/jpet.104.076653',
'10.1124/jpet.106.104463',
'10.1124/jpet.106.104968',
'10.1124/jpet.113.206383',
'10.1124/jpet.118.254508',
'10.1124/pr.112.007054',
'10.1124/pr.56.2.6',
'10.1130/1052-5173(2003)013<4:TEFTGE>2.0.CO;2',
'10.1130/1052-5173(2003)13<0004:HLTOTP>2.0.CO;2',
'10.1130/1052-5173(2004)014<4:CAAPDO>2.0.CO;2',
'10.1130/GSAT01701A.1',
'10.1130/GSAT01701A.1',
'10.1130/GSAT01802A.1',
'10.1130/GSAT151A.1',
'10.1130/GSAT151A.1',
'10.1130/GSATG158A.1',
'10.1130/GSATG321A.1',
'10.1163/15685289760518153',
'10.11676/qxxb2020.072',
'10.1186/s40850-017-0025-y',
'10.1186/s40850-020-00057-3',
'10.12989/sem.2013.48.6.791',
'10.12989/sem.2017.62.3.365',
'10.13106/ijidb.2015.vol6.no1.5.',
'10.1358/dof.2007.032.09.1138229',
'10.14241/asgp.2023.01',
'10.14241/asgp.2023.03',
'10.14241/asgp.2023.17',
'10.14482/memor.22.5948',
'10.15171/apb.2018.062',
'10.15446/historelo.v12n23.76565',
'10.15585/mmwr.mm6601a6',
'10.15585/mmwr.mm6730a2',
'10.1633/jim.2006.37.1.083',
'10.1682/JRRD.2004.03.0293',
'10.1682/JRRD.2006.03.0025',
'10.1682/JRRD.2006.05.0041',
'10.1682/jrrd.2006.11.0147',
'10.1682/JRRD.2010.03.0035',
'10.1682/JRRD.2012.05.0096',
'10.1682/JRRD.2012.05.0099',
'10.17576/akad-2021-9103-12',
'10.17576/JKMJC-2018-3403-16',
'10.17576/pengurusan-2017-49-06',
'10.18520/cs/v112/i01/139-146',
'10.18520/cs/v112/i05/933-940',
'10.18520/v109/i6/1061-1069',
'10.18926/AMO/30942',
'10.2174/0929867043364757',
'10.2174/138920212803251373',
'10.2174/187152606776056706',
'10.21897/rmvz.13',
'10.21897/rmvz.276',
'10.22059/ijmge.2012.51321',
'10.22059/jfadram.2012.24776',
'10.22679/AVS.2021.6.2.003',
'10.2307/3677937',
'10.24894/GESN-EN.2005.62013',
'10.25024/kj.2011.51.4.110',
'10.25024/kj.2013.53.4.14',
'10.25911/5D63C47EE2628',
'10.25911/5d74e50054bb9',
'10.29075/9780876332764/101812/1',
'10.29104/phi-aqualac/2017-v9-2-10',
'10.29117/jcsis.2021.0290',
'10.29173/bluejay1721',
'10.31439/UNISCI-101',
'10.31439/unisci-98',
'10.3201/eid1003.030257',
'10.3201/eid1110.041279',
'10.3389/fncom.2014.00086',
'10.3389/fpls.2019.00360',
'10.3389/fpsyt.2013.00027',
'10.3746/pnf.2017.22.2.67',
'10.3819/ccbr.2008.30003',
'10.3916/c56-2018-08',
'10.4062/biomolther.2009.17.3.241',
'10.4062/biomolther.2012.20.5.446',
'10.4310/pamq.2006.v2.n2.a3',
'10.5027/andgeoV45n3-3117',
'10.5027/andgeoV47n1-3260',
'10.5139/IJASS.2012.13.1.14',
'10.5303/JKAS.2002.35.2.075',
'10.5303/jkas.2002.35.2.075',
'10.5303/JKAS.2013.46.1.41',
'10.5392/IJoC.2014.10.1.036',
'10.54097/hbem.v7i.6940',
'10.5479/si.00775630.183.1',
'10.5479/si.00775630.501.1',
'10.5479/si.00775630.86.1',
'10.5479/si.00810223.1.1',
'10.5479/si.00810223.38.1',
'10.5479/si.00810258.46.1',
'10.5479/si.00810266.74.1',
'10.5479/si.00810282.193',
'10.5479/si.00810282.284',
'10.5479/si.00963801.11-709.197',
'10.5479/si.00963801.26-1334.811',
'10.5479/si.00963801.35-1648.351',
'10.5479/si.00963801.48-2069.169',
'10.5479/si.03629236.110.i',
'10.5479/si.03629236.208.1',
'10.5483/BMBRep.2004.37.1.122',
'10.5483/BMBRep.2012.45.4.259',
'10.5506/APhysPolB.42.2175',
'10.5572/KOSAE.2008.24.4.439',
'10.5656/ksae.2009.48.1.053',
'10.5656/ksae.2009.48.4.467',
'10.5656/ksae.2012.09.0.022',
'10.5656/ksae.2014.01.1.075',
'10.5757/ASCT.2014.23.2.61',
'10.5805/SFTI.2013.15.5.797',
'10.6018/daimon/277171',
'10.6018/j103411',
'10.6241/concentric.ling.200701_33(1).0001',
'10.7233/jksc.2013.63.2.029',
'10.7233/jksc.2015.65.6.112',
'10.7314/APJCP.2012.13.10.5177',
'10.7314/APJCP.2013.14.6.3425',
'10.7314/apjcp.2013.14.7.4283',
'10.7731/KIFSE.2015.29.2.039',
'10.8080/4020100059499',
'10.12989/was.2018.27.2.137',
'10.1101/gr.082701.108',
'10.1261/rna.2338706',
'10.1261/rna.2340906',
'10.15581/017.23.67-84',
);
