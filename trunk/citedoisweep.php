<?
// $Id: # //

$slowMode=false;
$fastMode=false;
$editInitiator = '[cDs]';
$accountSuffix='_3';
$citedoi = true;
$citedoi_sweep = true;

$ON = true;
#$ON=false;
include("expandFns.php");

$citeDois= array(
"Cite doi/10.1002.2Fajmg.1320390318", "Cite doi/10.1002.2Fbit.20546", "Cite doi/10.1002.2Fccd.20931	", "Cite doi/10.1002.2Fccd.21394	", "Cite doi/10.1002.2Fcncr.10277
", "Cite doi/10.1002.2Fcne.10971	", "Cite doi/10.1002.2Fcne.901680105	", "Cite doi/10.1002.2Fcne.902320307
", "Cite doi/10.1002.2Fcne.902360102	", "Cite doi/10.1002.2Fcne.903190204	", "Cite doi/10.1002.2Felps.200390149
", "Cite doi/10.1002.2Fhlca.200390096	", "Cite doi/10.1002.2Fhlca.200690108	", "Cite doi/10.1002.2Fhrm.20157
", "Cite doi/10.1002.2Fjmor.10564	", "Cite doi/10.1002.2Fjps.2600660713	", "Cite doi/10.1002.2Fmpo.2950040207
", "Cite doi/10.1002.2Fps.1488	", "Cite doi/10.1002.2Fzaac.19875480505	", "Cite doi/10.1006.2Fabbi.2000.2131
", "Cite doi/10.1006.2Fjhev.1996.0058	", "Cite doi/10.1006.2Fjhev.1999.0379	", "Cite doi/10.1006.2Fjhev.2000.0421
", "Cite doi/10.1006.2Fjhev.2001.0457	", "Cite doi/10.1006.2Fjhev.2001.0525	", "Cite doi/10.1006.2Fplas.1994.1024
", "Cite doi/10.1006.2Fviro.1998.9367	", "Cite doi/10.1007.2F1-4020-2522-X 31	", "Cite doi/10.1007.2F978-0-387-09565-3 14
", "Cite doi/10.1007.2F978-1-4020-4896-8 15	", "Cite doi/10.1007.2F978-1-4020-5121-0 15	", "Cite doi/10.1007.2F978-1-4020-6806-5 11
", "Cite doi/10.1007.2F978-1-4020-9726-3 4	", "Cite doi/10.1007.2F978-3-540-75916-4	", "Cite doi/10.1007.2F978-3-540-75916-4 14
", "Cite doi/10.1007.2FBF00115242	", "Cite doi/10.1007.2FBF00142578	", "Cite doi/10.1007.2FBF00144504
", "Cite doi/10.1007.2FBF00165392	", "Cite doi/10.1007.2FBF00188296	", "Cite doi/10.1007.2FBF00198171
", "Cite doi/10.1007.2FBF00216006	", "Cite doi/10.1007.2FBF00220187	", "Cite doi/10.1007.2FBF00243505
", "Cite doi/10.1007.2FBF00251073	", "Cite doi/10.1007.2FBF00399520	", "Cite doi/10.1007.2FBF00442260
", "Cite doi/10.1007.2FBF00443274	", "Cite doi/10.1007.2FBF00485140	", "Cite doi/10.1007.2FBF00631969
", "Cite doi/10.1007.2FBF00635714	", "Cite doi/10.1007.2FBF00733345	", "Cite doi/10.1007.2FBF01027719
", "Cite doi/10.1007.2FBF01163357	", "Cite doi/10.1007.2FBF01199531	", "Cite doi/10.1007.2FBF01391052
", "Cite doi/10.1007.2FBF01506807	", "Cite doi/10.1007.2FBF01576902	", "Cite doi/10.1007.2FBF01612371
", "Cite doi/10.1007.2FBF01758773	", "Cite doi/10.1007.2FBF01772202	", "Cite doi/10.1007.2FBF01897145
", "Cite doi/10.1007.2FBF01897152	", "Cite doi/10.1007.2FBF02041873	", "Cite doi/10.1007.2FBF02208978
", "Cite doi/10.1007.2FBF02535072	", "Cite doi/10.1007.2FBFb0012538	", "Cite doi/10.1007.2FBFb0078809
", "Cite doi/10.1007.2Fs000160200002	", "Cite doi/10.1007.2Fs00018-003-3050-7	", "Cite doi/10.1007.2Fs00106-005-1267-5
", "Cite doi/10.1007.2Fs002130050553	", "Cite doi/10.1007.2Fs00216-008-2356-6	", "Cite doi/10.1007.2Fs00217-004-0912-7
", "Cite doi/10.1007.2Fs00262-006-0248-1	", "Cite doi/10.1007.2Fs00415-009-0126-9	", "Cite doi/10.1007.2Fs00424-007-0242-2
", "Cite doi/10.1007.2Fs00429-002-0280-7	", "Cite doi/10.1007.2Fs004290050227	", "Cite doi/10.1007.2Fs00436-008-0891-x
", "Cite doi/10.1007.2Fs00439-008-0559-8	", "Cite doi/10.1007.2Fs00454-008-9101-y	", "Cite doi/10.1007.2Fs00508-006-0658-2
", "Cite doi/10.1007.2Fs00702-004-0210-3	", "Cite doi/10.1007.2Fs10162-007-0082-y	", "Cite doi/10.1007.2Fs10508-008-9381-6
", "Cite doi/10.1007.2Fs10522-008-9170-6	", "Cite doi/10.1007.2Fs10545-009-1149-1	", "Cite doi/10.1007.2Fs10584-005-9026-x
", "Cite doi/10.1007.2Fs10584-006-9101-y	", "Cite doi/10.1007.2Fs10669-006-8666-3	", "Cite doi/10.1007.2Fs10750-007-9158-2
", "Cite doi/10.1007.2Fs10803-005-0020-y	", "Cite doi/10.1007.2Fs10803-006-0261-4	", "Cite doi/10.1007.2Fs10803-007-0438-5
", "Cite doi/10.1007.2Fs10803-008-0618-y	", "Cite doi/10.1007.2Fs10803-009-0840-2	", "Cite doi/10.1007.2Fs10933-007-9187-x
", "Cite doi/10.1007.2Fs10995-005-0066-7	", "Cite doi/10.1007.2Fs11306-008-0142-2	", "Cite doi/10.1007.2Fs11506-008-1001-3
", "Cite doi/10.1007.2Fs11606-007-0428-5	", "Cite doi/10.1007.2Fs11698-007-0018-0	", "Cite doi/10.1007.2Fs11842-005-0015-8
", "Cite doi/10.1007.2Fs12052-008-0084-1	", "Cite doi/10.1007.2Fs12052-008-0085-0	", "Cite doi/10.1007.2Fs704-002-8206-7
", "Cite doi/10.1016.25252Fj.tree.2004.10.012	", "Cite doi/10.1016.2F0006-8993.2887.2990406-9	", "Cite doi/10.1016.2F0006-8993.2892.2990546-L
", "Cite doi/10.1016.2F0006-8993.2895.2900128-D	", "Cite doi/10.1016.2F0016-7037.2863.2990071-1	", "Cite doi/10.1016.2F0019-1035.2888.2990116-9
", "Cite doi/10.1016.2F0022-1902.2860.2980083-8	", "Cite doi/10.1016.2F0029-5582.2858.2990345-6	", "Cite doi/10.1016.2F0029-5582.2862.2990775-7
", "Cite doi/10.1016.2F0165-1633.2881.2990057-5	", "Cite doi/10.1016.2F0167-7152.2894.2900090-U	", "Cite doi/10.1016.2F0193-3973.2892.2990010-F
", "Cite doi/10.1016.2F0306-4603.2890.2990067-8	", "Cite doi/10.1016.2F0377-2217.2895.2900069-0	", "Cite doi/10.1016.2F0378-4347.2893.29E0414-L
", "Cite doi/10.1016.2F0925-8388.2894.2991069-3	", "Cite doi/10.1016.2F0961-9534.2891.2990028-B	", "Cite doi/10.1016.2F0961-9534.2891.2990036-C
", "Cite doi/10.1016.2F0961-9534.2893.2990076-G	", "Cite doi/10.1016.2F0961-9534.2894.2990067-1	", "Cite doi/10.1016.2F1053-4822.2895.2990010-1
", "Cite doi/10.1016.2FS0002-9297.2807.2962935-8	", "Cite doi/10.1016.2FS0002-9343.2899.2980403-3	", "Cite doi/10.1016.2FS0003-2670.2896.2900563-6
", "Cite doi/10.1016.2FS0005-7894.2897.2980048-2	", "Cite doi/10.1016.2FS0008-6215.2800.2983508-9	", "Cite doi/10.1016.2FS0012-821X.2803.2900017-7
", "Cite doi/10.1016.2FS0012-8252.2800.2900019-2	", "Cite doi/10.1016.2FS0014-5793.2801.2903239-2	", "Cite doi/10.1016.2FS0022-1139.2801.2900415-8
", "Cite doi/10.1016.2FS0022-1139.2801.2900512-7	", "Cite doi/10.1016.2FS0022-1139.2897.2900096-1	", "Cite doi/10.1016.2FS0022-1139.2899.2900194-3
", "Cite doi/10.1016.2FS0031-0182.2800.2900192-9	", "Cite doi/10.1016.2FS0037-0738.2800.2900171-8	", "Cite doi/10.1016.2FS0047-2484.2803.2900029-0
", "Cite doi/10.1016.2FS0047-2727.2898.2900067-X	", "Cite doi/10.1016.2FS0048-9697.2801.2901122-6	", "Cite doi/10.1016.2FS0065-2660.2808.2900804-3
", "Cite doi/10.1016.2FS0076-6879.2805.2997021-3	", "Cite doi/10.1016.2FS0140-6736.2803.2913370-3	", "Cite doi/10.1016.2FS0140-6736.2804.2916934-1
", "Cite doi/10.1016.2FS0140-6736.2806.2969865-6	", "Cite doi/10.1016.2FS0140-6736.2808.2961414-2	", "Cite doi/10.1016.2FS0140-6736.2808.2961417-8
", "Cite doi/10.1016.2FS0140-6736.2809.2960028-3	", "Cite doi/10.1016.2FS0140-6736.2809.2960029-5	", "Cite doi/10.1016.2FS0140-6736.2809.2960030-1
", "Cite doi/10.1016.2FS0140-6736.2809.2960031-3	", "Cite doi/10.1016.2FS0140-6736.2809.2960032-5	", "Cite doi/10.1016.2FS0140-6736.2809.2960742-X
", "Cite doi/10.1016.2FS0140-6736.2809.2960879-5	", "Cite doi/10.1016.2FS0140-6736.2809.2961304-0	", "Cite doi/10.1016.2FS0140-6736.2860.2990675-9
", "Cite doi/10.1016.2FS0162-0134.2800.2900034-9	", "Cite doi/10.1016.2FS0165-6147.2897.2990649-0	", "Cite doi/10.1016.2FS0166-2236.2896.2910073-4
", "Cite doi/10.1016.2FS0169-5347.2803.2900093-4	", "Cite doi/10.1016.2FS0277-9536.2899.2900436-0	", "Cite doi/10.1016.2FS0304-3940.2899.2900313-4
", "Cite doi/10.1016.2FS0304-3975.2897.2900228-4	", "Cite doi/10.1016.2FS0306-2619.2899.2900057-4	", "Cite doi/10.1016.2FS0308-8146.2897.2900236-7
", "Cite doi/10.1016.2FS0370-2693.2803.2900333-2	", "Cite doi/10.1016.2FS0376-8716.2899.2900034-4	", "Cite doi/10.1016.2FS0896-6273.2803.2900472-0
", "Cite doi/10.1016.2FS0956-053X.2897.2910033-2	", "Cite doi/10.1016.2FS0959-440X.2800.2900096-8	", "Cite doi/10.1016.2FS0960-9822.2801.2900438-9
", "Cite doi/10.1016.2FS0960-9822.2803.2900507-4	", "Cite doi/10.1016.2FS0961-9534.2898.2900071-3	", "Cite doi/10.1016.2FS1286-4579.2899.2900242-7
", "Cite doi/10.1016.2FS1357-2725.2803.2900264-4	", "Cite doi/10.1016.2FS1474-4422.2808.2970062-0	", "Cite doi/10.1016.2Fj.acn.2005.09.001
", "Cite doi/10.1016.2Fj.ajhg.2008.04.002	", "Cite doi/10.1016.2Fj.appet.2009.04.022	", "Cite doi/10.1016.2Fj.asd.2007.06.003
", "Cite doi/10.1016.2Fj.biocel.2005.02.018	", "Cite doi/10.1016.2Fj.biopha.2007.12.004	", "Cite doi/10.1016.2Fj.biopsych.2006.08.025
", "Cite doi/10.1016.2Fj.bpg.2005.02.003	", "Cite doi/10.1016.2Fj.brainres.2008.03.090	", "Cite doi/10.1016.2Fj.ceb.2004.04.004
", "Cite doi/10.1016.2Fj.cell.2006.05.025	", "Cite doi/10.1016.2Fj.ces.2005.10.017	", "Cite doi/10.1016.2Fj.chemosphere.2009.05.008
", "Cite doi/10.1016.2Fj.chom.2008.05.013	", "Cite doi/10.1016.2Fj.chroma.2007.05.087	", "Cite doi/10.1016.2Fj.coldregions.2004.12.002
", "Cite doi/10.1016.2Fj.compfluid.2005.07.005	", "Cite doi/10.1016.2Fj.cpr.2007.07.002	", "Cite doi/10.1016.2Fj.cpr.2009.05.002
", "Cite doi/10.1016.2Fj.crpv.2003.09.023	", "Cite doi/10.1016.2Fj.crvi.2005.03.004	", "Cite doi/10.1016.2Fj.crvi.2005.04.004
", "Cite doi/10.1016.2Fj.cub.2007.11.027	", "Cite doi/10.1016.2Fj.cub.2008.10.025	", "Cite doi/10.1016.2Fj.cub.2008.10.028
", "Cite doi/10.1016.2Fj.cub.2008.12.034	", "Cite doi/10.1016.2Fj.cub.2009.01.023	", "Cite doi/10.1016.2Fj.det.2004.03.003
", "Cite doi/10.1016.2Fj.ejpain.2009.01.006	", "Cite doi/10.1016.2Fj.energy.2004.03.064	", "Cite doi/10.1016.2Fj.energy.2004.07.003
", "Cite doi/10.1016.2Fj.enpol.2009.02.011	", "Cite doi/10.1016.2Fj.envres.2009.04.014	", "Cite doi/10.1016.2Fj.epsl.2004.09.005
", "Cite doi/10.1016.2Fj.epsl.2006.04.025	", "Cite doi/10.1016.2Fj.epsl.2009.05.028	", "Cite doi/10.1016.2Fj.euroneuro.2004.12.005
", "Cite doi/10.1016.2Fj.euroneuro.2007.02.013	", "Cite doi/10.1016.2Fj.febslet.2005.02.047	", "Cite doi/10.1016.2Fj.geobios.2006.02.001
", "Cite doi/10.1016.2Fj.geobios.2007.02.006	", "Cite doi/10.1016.2Fj.geoderma.2005.04.003	", "Cite doi/10.1016.2Fj.gloenvcha.2003.10.001
", "Cite doi/10.1016.2Fj.gloenvcha.2003.10.007	", "Cite doi/10.1016.2Fj.gloenvcha.2003.10.009	", "Cite doi/10.1016.2Fj.gr.2007.10.001
", "Cite doi/10.1016.2Fj.iheduc.2008.03.001	", "Cite doi/10.1016.2Fj.ijhydene.2008.05.086	", "Cite doi/10.1016.2Fj.ijpe.2008.12.005
", "Cite doi/10.1016.2Fj.it.2004.02.012	", "Cite doi/10.1016.2Fj.jallcom.2008.06.059	", "Cite doi/10.1016.2Fj.jcrimjus.2006.11.016
", "Cite doi/10.1016.2Fj.jebo.2006.05.017	", "Cite doi/10.1016.2Fj.jfca.2009.03.001	", "Cite doi/10.1016.2Fj.jfluchem.2004.01.019
", "Cite doi/10.1016.2Fj.jfluchem.2006.04.014	", "Cite doi/10.1016.2Fj.jhevol.2003.08.003	", "Cite doi/10.1016.2Fj.jhevol.2005.04.005
", "Cite doi/10.1016.2Fj.jhevol.2005.04.006	", "Cite doi/10.1016.2Fj.jhevol.2005.08.010	", "Cite doi/10.1016.2Fj.jhevol.2007.06.004
", "Cite doi/10.1016.2Fj.jhevol.2008.02.003	", "Cite doi/10.1016.2Fj.jhevol.2008.05.002	", "Cite doi/10.1016.2Fj.jhevol.2008.05.007
", "Cite doi/10.1016.2Fj.jhevol.2008.05.013	", "Cite doi/10.1016.2Fj.jhevol.2008.10.005	", "Cite doi/10.1016.2Fj.jinorgbio.2005.02.004
", "Cite doi/10.1016.2Fj.jmb.2005.12.025	", "Cite doi/10.1016.2Fj.jnutbio.2006.12.007	", "Cite doi/10.1016.2Fj.joca.2009.07.004
", "Cite doi/10.1016.2Fj.jsbmb.2008.03.030	", "Cite doi/10.1016.2Fj.jup.2007.01.002	", "Cite doi/10.1016.2Fj.mcna.2006.04.003
", "Cite doi/10.1016.2Fj.media.2006.07.003	", "Cite doi/10.1016.2Fj.mehy.2006.02.035	", "Cite doi/10.1016.2Fj.mehy.2008.09.048
", "Cite doi/10.1016.2Fj.micinf.2005.04.006	", "Cite doi/10.1016.2Fj.molcel.2004.12.004	", "Cite doi/10.1016.2Fj.molcel.2006.03.028
", "Cite doi/10.1016.2Fj.mrrev.2007.09.001	", "Cite doi/10.1016.2Fj.neulet.2009.06.014	", "Cite doi/10.1016.2Fj.neuro.2006.06.008
", "Cite doi/10.1016.2Fj.neurobiolaging.2007.04.013	", "Cite doi/10.1016.2Fj.neuroimage.2009.01.060	", "Cite doi/10.1016.2Fj.neuroimage.2009.03.019
", "Cite doi/10.1016.2Fj.neuron.2006.01.014	", "Cite doi/10.1016.2Fj.neuron.2007.10.023	", "Cite doi/10.1016.2Fj.neuroscience.2004.08.017
", "Cite doi/10.1016.2Fj.nurt.2007.01.013	", "Cite doi/10.1016.2Fj.optlastec.2008.12.020	", "Cite doi/10.1016.2Fj.optm.2007.05.012
", "Cite doi/10.1016.2Fj.palaeo.2003.03.001	", "Cite doi/10.1016.2Fj.palaeo.2005.09.018	", "Cite doi/10.1016.2Fj.palaeo.2006.02.021
", "Cite doi/10.1016.2Fj.palaeo.2006.06.040	", "Cite doi/10.1016.2Fj.palaeo.2007.04.003	", "Cite doi/10.1016.2Fj.palaeo.2007.05.023
", "Cite doi/10.1016.2Fj.palaeo.2008.04.021	", "Cite doi/10.1016.2Fj.palaeo.2008.12.015	", "Cite doi/10.1016.2Fj.palaeo.2009.02.009
", "Cite doi/10.1016.2Fj.palaeo.2009.02.010	", "Cite doi/10.1016.2Fj.palaeo.2009.02.011	", "Cite doi/10.1016.2Fj.palaeo.2009.02.012
", "Cite doi/10.1016.2Fj.palaeo.2009.02.014	", "Cite doi/10.1016.2Fj.palaeo.2009.02.015	", "Cite doi/10.1016.2Fj.palaeo.2009.02.016
", "Cite doi/10.1016.2Fj.palaeo.2009.02.017	", "Cite doi/10.1016.2Fj.palwor.2007.08.002	", "Cite doi/10.1016.2Fj.parco.2005.07.005
", "Cite doi/10.1016.2Fj.pathophys.2009.01.005	", "Cite doi/10.1016.2Fj.pcl.2008.08.001	", "Cite doi/10.1016.2Fj.pharmthera.2008.08.003
", "Cite doi/10.1016.2Fj.phrs.2006.03.005	", "Cite doi/10.1016.2Fj.physe.2003.11.197	", "Cite doi/10.1016.2Fj.pnpbp.2006.01.010
", "Cite doi/10.1016.2Fj.pnpbp.2009.04.022	", "Cite doi/10.1016.2Fj.psc.2007.07.001	", "Cite doi/10.1016.2Fj.psc.2007.07.007
", "Cite doi/10.1016.2Fj.psyneuen.2008.09.017	", "Cite doi/10.1016.2Fj.psyneuen.2009.02.015	", "Cite doi/10.1016.2Fj.psyneuen.2009.03.005
", "Cite doi/10.1016.2Fj.rasd.2007.08.005	", "Cite doi/10.1016.2Fj.reprotox.2009.06.012	", "Cite doi/10.1016.2Fj.revmed.2006.11.019
", "Cite doi/10.1016.2Fj.rgg.2007.12.001	", "Cite doi/10.1016.2Fj.ridd.2009.01.006	", "Cite doi/10.1016.2Fj.ridd.2009.01.008
", "Cite doi/10.1016.2Fj.schres.2004.10.007	", "Cite doi/10.1016.2Fj.schres.2007.08.008	", "Cite doi/10.1016.2Fj.schres.2007.10.031
", "Cite doi/10.1016.2Fj.schres.2009.06.018	", "Cite doi/10.1016.2Fj.snb.2005.08.047	", "Cite doi/10.1016.2Fj.solidstatesciences.2005.06.015
", "Cite doi/10.1016.2Fj.str.2007.10.024	", "Cite doi/10.1016.2Fj.taap.2007.08.001	", "Cite doi/10.1016.2Fj.tics.2003.12.004
", "Cite doi/10.1016.2Fj.tox.2009.06.006	", "Cite doi/10.1016.2Fj.toxlet.2009.06.853	", "Cite doi/10.1016.2Fj.tplants.2005.07.006
", "Cite doi/10.1016.2Fj.tree.2003.08.009	", "Cite doi/10.1016.2Fj.tree.2004.10.012	", "Cite doi/10.1016.2Fj.tree.2008.07.015
", "Cite doi/10.1016.2Fj.tree.2008.10.006	", "Cite doi/10.1016.2Fj.tree.2009.01.003	", "Cite doi/10.1016.2Fj.vaccine.2008.09.065
", "Cite doi/10.1016.2Fj.vaccine.2009.03.091	", "Cite doi/10.1016.2Fj.ydbio.2006.04.437	", "Cite doi/10.1016.2Fj.ygyno.2004.01.046
", "Cite doi/10.1016.2Fj.ymben.2003.09.001	", "Cite doi/10.1016.2Fj.ympev.2004.12.002	", "Cite doi/10.1016.2Fj.ympev.2005.08.017
", "Cite doi/10.1016/j.epsl.2006.04.025	", "Cite doi/10.1016/j.palaeo.2009.02.016	", "Cite doi/10.1017.2FS0007114508965776
", "Cite doi/10.1017.2FS0012162206002040	", "Cite doi/10.1017.2FS0016756800207139	", "Cite doi/10.1017.2FS001675680100509X
", "Cite doi/10.1017.2FS0016756801006252	", "Cite doi/10.1017.2FS0021859600002410	", "Cite doi/10.1017.2FS0033291707001481
", "Cite doi/10.1017.2FS0140525X04210044	", "Cite doi/10.1017.2FS0263593300001334	", "Cite doi/10.1017.2FS0266078400005514
", "Cite doi/10.1017.2FS1461145708009309	", "Cite doi/10.1021.2Fac60222a002	", "Cite doi/10.1021.2Fbi062299p
", "Cite doi/10.1021.2Fcr00098a014	", "Cite doi/10.1021.2Fcr078207z	", "Cite doi/10.1021.2Fcr60244a003
", "Cite doi/10.1021.2Fic00050a023	", "Cite doi/10.1021.2Fic00142a001	", "Cite doi/10.1021.2Fic00231a038
", "Cite doi/10.1021.2Fic00294a018	", "Cite doi/10.1021.2Fic50003a051	", "Cite doi/10.1021.2Fic50013a036
", "Cite doi/10.1021.2Fic50034a025	", "Cite doi/10.1021.2Fic50070a039	", "Cite doi/10.1021.2Fic50085a037
", "Cite doi/10.1021.2Fic50095a008	", "Cite doi/10.1021.2Fic50175a017	", "Cite doi/10.1021.2Fic50199a056
", "Cite doi/10.1021.2Fie50175a006	", "Cite doi/10.1021.2Fj100144a009	", "Cite doi/10.1021.2Fj150511a004
", "Cite doi/10.1021.2Fj150552a005	", "Cite doi/10.1021.2Fj150574a041	", "Cite doi/10.1021.2Fja00019a014
", "Cite doi/10.1021.2Fja00055a073	", "Cite doi/10.1021.2Fja00074a011	", "Cite doi/10.1021.2Fja00271a043
", "Cite doi/10.1021.2Fja00528a065	", "Cite doi/10.1021.2Fja00764a022	", "Cite doi/10.1021.2Fja00817a034
", "Cite doi/10.1021.2Fja00882a063	", "Cite doi/10.1021.2Fja01088a038	", "Cite doi/10.1021.2Fja01100a527
", "Cite doi/10.1021.2Fja01108a015	", "Cite doi/10.1021.2Fja01195a063	", "Cite doi/10.1021.2Fja01320a506
", "Cite doi/10.1021.2Fja01462a016	", "Cite doi/10.1021.2Fja01652a057	", "Cite doi/10.1021.2Fja043822v
", "Cite doi/10.1021.2Fja0616433	", "Cite doi/10.1021.2Fja7103069	", "Cite doi/10.1021.2Fja8015457
", "Cite doi/10.1021.2Fja9030038	", "Cite doi/10.1021.2Fje60059a014	", "Cite doi/10.1021.2Fjf9014526
", "Cite doi/10.1021.2Fjm970790w	", "Cite doi/10.1021.2Fjo005702l	", "Cite doi/10.1021.2Fjo00871a048
", "Cite doi/10.1021.2Fjo8001722	", "Cite doi/10.1021.2Fjo980330q	", "Cite doi/10.1021.2Fjp068879d
", "Cite doi/10.1021.2Fol049666e	", "Cite doi/10.1021.2Fom701189e	", "Cite doi/10.1023.2FA:1005324621274
", "Cite doi/10.1023.2FA:1007712500496	", "Cite doi/10.1023.2FA:1012029312055	", "Cite doi/10.1023.2FA:1013038920600
", "Cite doi/10.1023.2FA:1016529817118	", "Cite doi/10.1023.2FA:1016667125469	", "Cite doi/10.1023.2FA:1017007911190
", "Cite doi/10.1023.2FA:1019076603956	", "Cite doi/10.1023.2FA:1022115604225	", "Cite doi/10.1023.2FA:1022829706609
", "Cite doi/10.1023.2FA:1026595431775	", "Cite doi/10.1023.2FA:1026751225741	", "Cite doi/10.1023.2FA:1026755309811
", "Cite doi/10.1023.2FB:CACO.0000036154.18162.43	", "Cite doi/10.1023.2FB:CLIM.0000037493.89489.3f	", "Cite doi/10.1023.2FB:NEUR.0000046573.28081.dd
", "Cite doi/10.1029.25252F2002GL016329	", "Cite doi/10.1029.25252F2004GL020670	", "Cite doi/10.1029.25252F2006GL028154
", "Cite doi/10.1029.25252F2008GL033510	", "Cite doi/10.1029.2F2001GB001829	", "Cite doi/10.1029.2F2001JD001143
", "Cite doi/10.1029.2F2002GL015650	", "Cite doi/10.1029.2F2002GL016329	", "Cite doi/10.1029.2F2003GL018680
", "Cite doi/10.1029.2F2004GC000854	", "Cite doi/10.1029.2F2004GL020670	", "Cite doi/10.1029.2F2004GL021750
", "Cite doi/10.1029.2F2005GL022751	", "Cite doi/10.1029.2F2005GL025080	", "Cite doi/10.1029.2F2005JD005776
", "Cite doi/10.1029.2F2006GL027977	", "Cite doi/10.1029.2F2006GL028017	", "Cite doi/10.1029.2F2006GL028154
", "Cite doi/10.1029.2F2006GL028443	", "Cite doi/10.1029.2F2007GL029703	", "Cite doi/10.1029.2F2007GL032179
", "Cite doi/10.1029.2F2007JD008437	", "Cite doi/10.1029.2F2008GL033510	", "Cite doi/10.1029.2F2008GL033985
", "Cite doi/10.1029.2F2008GL034424	", "Cite doi/10.1029.2F2008GL034614	", "Cite doi/10.1029.2F2008GL036465
", "Cite doi/10.1029.2F2008JD010421	", "Cite doi/10.1029.2F2009GL039191	", "Cite doi/10.1029.2F94JD03325
", "Cite doi/10.1029.2F97GL03092	", "Cite doi/10.1029/2002GL015650
", "Cite doi/10.1029/2004GL020670
", "Cite doi/10.1029/2006GL028154
	", "Cite doi/10.1029/94JD03325	", "Cite doi/10.1037.2F0021-9010.91.3.579
", "Cite doi/10.1037.2F0033-2909.114.2.363	", "Cite doi/10.1037.2F1064-1297.15.1.67	", "Cite doi/10.1038.25252F385250a0
", "Cite doi/10.1038.2F191144a0	", "Cite doi/10.1038.2F2261037a0	", "Cite doi/10.1038.2F227561a0
", "Cite doi/10.1038.2F246015a0	", "Cite doi/10.1038.2F261717a0	", "Cite doi/10.1038.2F330127a0
", "Cite doi/10.1038.2F339532a0	", "Cite doi/10.1038.2F344529a0	", "Cite doi/10.1038.2F345219a0
", "Cite doi/10.1038.2F35057062	", "Cite doi/10.1038.2F35068549	", "Cite doi/10.1038.2F35087573
", "Cite doi/10.1038.2F353225a0	", "Cite doi/10.1038.2F361390c0	", "Cite doi/10.1038.2F376348a0
", "Cite doi/10.1038.2F378165a0	", "Cite doi/10.1038.2F382056a0	", "Cite doi/10.1038.2F383495a0
", "Cite doi/10.1038.2F385250a0	", "Cite doi/10.1038.2F414601a	", "Cite doi/10.1038.2F414602a
", "Cite doi/10.1038.2F415863a	", "Cite doi/10.1038.2F434842a	", "Cite doi/10.1038.2F4371108a
", "Cite doi/10.1038.2F438006a	", "Cite doi/10.1038.2F438575a	", "Cite doi/10.1038.2F438929a
", "Cite doi/10.1038.2F446358a	", "Cite doi/10.1038.2F448844b	", "Cite doi/10.1038.2F449403a
", "Cite doi/10.1038.2F450349a	", "Cite doi/10.1038.2F457763a	", "Cite doi/10.1038.2F457780a
", "Cite doi/10.1038.2F459168a	", "Cite doi/10.1038.2F460801a	", "Cite doi/10.1038.2F460952a
", "Cite doi/10.1038.2Fcr.2008.66	", "Cite doi/10.1038.2Femboj.2009.116	", "Cite doi/10.1038.2Femboj.2009.222
", "Cite doi/10.1038.2Fembor.2008.109	", "Cite doi/10.1038.2Fhdy.2008.70	", "Cite doi/10.1038.2Fhdy.2009.36
", "Cite doi/10.1038.2Fjid.2008.111	", "Cite doi/10.1038.2Fnature01264	", "Cite doi/10.1038.2Fnature01763
", "Cite doi/10.1038.2Fnature01876	", "Cite doi/10.1038.2Fnature02029	", "Cite doi/10.1038.2Fnature02719
", "Cite doi/10.1038.2Fnature03258	", "Cite doi/10.1038.2Fnature03344	", "Cite doi/10.1038.2Fnature03345
", "Cite doi/10.1038.2Fnature03585	", "Cite doi/10.1038.2Fnature03671	", "Cite doi/10.1038.2Fnature04000
", "Cite doi/10.1038.2Fnature04130	", "Cite doi/10.1038.2Fnature04521	", "Cite doi/10.1038.2Fnature04629
", "Cite doi/10.1038.2Fnature04894	", "Cite doi/10.1038.2Fnature05040	", "Cite doi/10.1038.2Fnature05167
", "Cite doi/10.1038.2Fnature06018	", "Cite doi/10.1038.2Fnature06343	", "Cite doi/10.1038.2Fnature06862
", "Cite doi/10.1038.2Fnature06967	", "Cite doi/10.1038.2Fnature07741	", "Cite doi/10.1038.2Fnature07922
", "Cite doi/10.1038.2Fnature08169	", "Cite doi/10.1038.2Fnature08213	", "Cite doi/10.1038.2Fnature08239
", "Cite doi/10.1038.2Fnbt1407	", "Cite doi/10.1038.2Fnchembio.189	", "Cite doi/10.1038.2Fncpneuro0971
", "Cite doi/10.1038.2Fng1992	", "Cite doi/10.1038.2Fng2100	", "Cite doi/10.1038.2Fngeo217
", "Cite doi/10.1038.2Fngeo420	", "Cite doi/10.1038.2Fngeo434	", "Cite doi/10.1038.2Fngeo467
", "Cite doi/10.1038.2Fngeo533	", "Cite doi/10.1038.2Fnm1258	", "Cite doi/10.1038.2Fnm975
", "Cite doi/10.1038.2Fnn2010	", "Cite doi/10.1038.2Fnphys1341	", "Cite doi/10.1038.2Fnprot.2007.513
", "Cite doi/10.1038.2Fnrd1917	", "Cite doi/10.1038.2Fnrd2780	", "Cite doi/10.1038.2Fnrg1247
", "Cite doi/10.1038.2Fnrg1895	", "Cite doi/10.1038.2Fnrg1941	", "Cite doi/10.1038.2Fnrn1430
", "Cite doi/10.1038.2Foby.2001.108	", "Cite doi/10.1038.2Foby.2003.142	", "Cite doi/10.1038.2Foby.2007.84
", "Cite doi/10.1038.2Fsj.clpt.6100223	", "Cite doi/10.1038.2Fsj.clpt.6100407	", "Cite doi/10.1038.2Fsj.ejcn.1601343
", "Cite doi/10.1038.2Fsj.ejcn.1602899	", "Cite doi/10.1038.2Fsj.ejhg.5200750	", "Cite doi/10.1038.2Fsj.embor.7400555
", "Cite doi/10.1038.2Fsj.ijo.0802783	", "Cite doi/10.1038.2Fsj.mp.4001637	", "Cite doi/10.1038.2Fsj.npp.1300046
", "Cite doi/10.1038.2Fsj.npp.1301305	", "Cite doi/10.1038/344529a0	", "Cite doi/10.1038/414601a
", "Cite doi/10.1039.2FC29710001543	", "Cite doi/10.1039.2FC39860000517	", "Cite doi/10.1039.2FDT9750000316
", "Cite doi/10.1039.2FDT9790001251	", "Cite doi/10.1039.2FJR9320002078	", "Cite doi/10.1039.2Fb514631c
", "Cite doi/10.1042.2FBST0311095	", "Cite doi/10.1044.2F1058-0360.282004.2F014.29	", "Cite doi/10.1044.2F1059-0889.282006.2F014.29
", "Cite doi/10.1045.2Fapril2005-hammond	", "Cite doi/10.1045.2Fapril2005-lund	", "Cite doi/10.1046.2Fj.1360-0443.2003.00523.x
", "Cite doi/10.1046.2Fj.1468-2982.1998.1801027.x	", "Cite doi/10.1046.2Fj.1468-2982.2000.00064.x	", "Cite doi/10.1046.2Fj.1525-142x.2000.00077.x
", "Cite doi/10.1046.2Fj.1526-4610.2003.03096.x	", "Cite doi/10.1049.2Fel:20080522	", "Cite doi/10.1051.2F0004-6361:20042476
", "Cite doi/10.1051.2F0004-6361:200500193	", "Cite doi/10.1051.2Fgse:2002009	", "Cite doi/10.1053.2Fberh.2001.0191
", "Cite doi/10.1053.2Fjoms.2000.8744	", "Cite doi/10.1056.2FNEJM200007063430103	", "Cite doi/10.1056.2FNEJMc060133
", "Cite doi/10.1056.2FNEJMoa054512	", "Cite doi/10.1056.2FNEJMoa0803200	", "Cite doi/10.1056.2FNEJMp038231
", "Cite doi/10.1056.2FNEJMp058291	", "Cite doi/10.1057.2F9780230226203.0934	", "Cite doi/10.1063.2F1.1663238
", "Cite doi/10.1063.2F1.2900317	", "Cite doi/10.1070.2FRC1985v054n05ABEH003068	", "Cite doi/10.1073.2Fpnas.011597698
", "Cite doi/10.1073.2Fpnas.0301885101	", "Cite doi/10.1073.2Fpnas.0400596101	", "Cite doi/10.1073.2Fpnas.0401799101
", "Cite doi/10.1073.2Fpnas.0402909101	", "Cite doi/10.1073.2Fpnas.0409766102	", "Cite doi/10.1073.2Fpnas.0503660102
", "Cite doi/10.1073.2Fpnas.0509457102	", "Cite doi/10.1073.2Fpnas.0510005103	", "Cite doi/10.1073.2Fpnas.0510792103
", "Cite doi/10.1073.2Fpnas.0510817103	", "Cite doi/10.1073.2Fpnas.052518199	", "Cite doi/10.1073.2Fpnas.0602578103
", "Cite doi/10.1073.2Fpnas.0604090103	", "Cite doi/10.1073.2Fpnas.0604213103	", "Cite doi/10.1073.2Fpnas.0605128103
", "Cite doi/10.1073.2Fpnas.0605414103	", "Cite doi/10.1073.2Fpnas.0606966103	", "Cite doi/10.1073.2Fpnas.0608053104
", "Cite doi/10.1073.2Fpnas.0608443103	", "Cite doi/10.1073.2Fpnas.0608879103	", "Cite doi/10.1073.2Fpnas.0700419104
", "Cite doi/10.1073.2Fpnas.0700609104	", "Cite doi/10.1073.2Fpnas.0702081104	", "Cite doi/10.1073.2Fpnas.0702169104
", "Cite doi/10.1073.2Fpnas.0702214104	", "Cite doi/10.1073.2Fpnas.0704665104	", "Cite doi/10.1073.2Fpnas.0705414105
", "Cite doi/10.1073.2Fpnas.0710521105	", "Cite doi/10.1073.2Fpnas.0711261105	", "Cite doi/10.1073.2Fpnas.0711648105
", "Cite doi/10.1073.2Fpnas.0800388105	", "Cite doi/10.1073.2Fpnas.0800885105	", "Cite doi/10.1073.2Fpnas.0801921105
", "Cite doi/10.1073.2Fpnas.0801980105	", "Cite doi/10.1073.2Fpnas.0802812105	", "Cite doi/10.1073.2Fpnas.0804619106
", "Cite doi/10.1073.2Fpnas.0806887106	", "Cite doi/10.1073.2Fpnas.0808160106	", "Cite doi/10.1073.2Fpnas.0812355106
", "Cite doi/10.1073.2Fpnas.0812460106	", "Cite doi/10.1073.2Fpnas.0812570106	", "Cite doi/10.1073.2Fpnas.0812721106
", "Cite doi/10.1073.2Fpnas.0812764106	", "Cite doi/10.1073.2Fpnas.0900502106	", "Cite doi/10.1073.2Fpnas.0900906106
", "Cite doi/10.1073.2Fpnas.0900944106	", "Cite doi/10.1073.2Fpnas.0901008106	", "Cite doi/10.1073.2Fpnas.0901229106
", "Cite doi/10.1073.2Fpnas.0902037106	", "Cite doi/10.1073.2Fpnas.0902322106	", "Cite doi/10.1073.2Fpnas.0903307106
", "Cite doi/10.1073.2Fpnas.0904571106	", "Cite doi/10.1073.2Fpnas.0904826106	", "Cite doi/10.1073.2Fpnas.0904836106
", "Cite doi/10.1073.2Fpnas.1130343100	", "Cite doi/10.1073.2Fpnas.1734063100	", "Cite doi/10.1073.2Fpnas.2035108100
", "Cite doi/10.1073.2Fpnas.91.25.12278	", "Cite doi/10.1073.2Fpnas.96.6.3320	", "Cite doi/10.1073/pnas.0804619106
", "Cite doi/10.1073 pnas.0608443103	", "Cite doi/10.1074.2Fjbc.M104070200	", "Cite doi/10.1074.2Fjbc.M312743200
", "Cite doi/10.1074.2Fjbc.M408155200	", "Cite doi/10.1074.2Fjbc.M500326200	", "Cite doi/10.1074.2Fjbc.M508635200
", "Cite doi/10.1074.2Fjbc.M702314200	", "Cite doi/10.1074.2Fjbc.M802829200	", "Cite doi/10.1074.2Fjbc.R700031200
", "Cite doi/10.1075.2Fis.7.3.03mac	", "Cite doi/10.1076.2Fchin.7.4.265.8730	", "Cite doi/10.1080.2F00033793600200111
", "Cite doi/10.1080.2F00207450590956459	", "Cite doi/10.1080.2F00241160050150221	", "Cite doi/10.1080.2F00241160310001254
", "Cite doi/10.1080.2F00241160410002135	", "Cite doi/10.1080.2F00241160410002180	", "Cite doi/10.1080.2F0043824042000303700
", "Cite doi/10.1080.2F00438240802452668	", "Cite doi/10.1080.2F00497870590964165	", "Cite doi/10.1080.2F01460860290042611
", "Cite doi/10.1080.2F03014467400000351	", "Cite doi/10.1080.2F03115518008618934	", "Cite doi/10.1080.2F08912960500508689
", "Cite doi/10.1080.2F09297040490911131	", "Cite doi/10.1080.2F10253890410001728379	", "Cite doi/10.1080.2F10575639708048312
", "Cite doi/10.1080.2F109158199225620	", "Cite doi/10.1080.2F136820310000108133	", "Cite doi/10.1080.2F13682820210136269
", "Cite doi/10.1080.2F13682820310001615797	", "Cite doi/10.1080.2F13682820310001617001	", "Cite doi/10.1080.2F13682820601010027
", "Cite doi/10.1080.2F13682820701633207	", "Cite doi/10.1080.2F13682820802708080	", "Cite doi/10.1080.2F13682820802708098
", "Cite doi/10.1080.2F13682820902863090	", "Cite doi/10.1080.2F13803390490510095	", "Cite doi/10.1080.2F14015430600712056
", "Cite doi/10.1080.2F14616700701768170	", "Cite doi/10.1080.2F1521654042000223616	", "Cite doi/10.1080.2F15622970801901828
", "Cite doi/10.1080.2F713688125	", "Cite doi/10.1080/00241160410002180	", "Cite doi/10.1086.2F204689
", "Cite doi/10.1086.2F300781	", "Cite doi/10.1086.2F318206	", "Cite doi/10.1086.2F322340
", "Cite doi/10.1086.2F377003	", "Cite doi/10.1086.2F422422	", "Cite doi/10.1086.2F426704
", "Cite doi/10.1086.2F497981	", "Cite doi/10.1086.2F500614	", "Cite doi/10.1086.2F514848
", "Cite doi/10.1086.2F520541	", "Cite doi/10.1086.2F605648	", "Cite doi/10.1088.2F0004-637X.2F700.2F2.2FL154
", "Cite doi/10.1088.2F0953-8984.2F20.2F41.2F415104	", "Cite doi/10.1088.2F1126-6708.2F2004.2F04.2F050	", "Cite doi/10.1088.2F1367-2630.2F11.2F3.2F033011
", "Cite doi/10.1088.2F1748-9326.2F4.2F1.2F014012	", "Cite doi/10.1088.2F1755-1307.2F6.2F1.2F012008	", "Cite doi/10.1093.2Faje.2Fkwn297
", "Cite doi/10.1093.2Faje.2Fkwn348	", "Cite doi/10.1093.2Fbioinformatics.2Fbti011	", "Cite doi/10.1093.2Fbiomet.2F44.1-2.1
", "Cite doi/10.1093.2Fbrain.2Fawh022	", "Cite doi/10.1093.2Femboj.2F16.13.4107	", "Cite doi/10.1093.2Femboj.2F21.5.1084
", "Cite doi/10.1093.2Fhmg.2Fddh137	", "Cite doi/10.1093.2Fhmg.2Fddp137	", "Cite doi/10.1093.2Ficesjms.2Ffsn048
", "Cite doi/10.1093.2Fije.2Fdym295	", "Cite doi/10.1093.2Fije.2Fdyn024	", "Cite doi/10.1093.2Fjac.2Fdki018
", "Cite doi/10.1093.2Fjac.2Fdkl420	", "Cite doi/10.1093.2Fjhered.2Fesl036	", "Cite doi/10.1093.2Fjiplp.2Fjpn121
", "Cite doi/10.1093.2Fjnci.2Fdjn329	", "Cite doi/10.1093.2Fjpepsy.2F27.6.485	", "Cite doi/10.1093.2Fmolbev.2Fmsi013
", "Cite doi/10.1093.2Fmolbev.2Fmsm239	", "Cite doi/10.1093.2Fmolbev.2Fmsm279	", "Cite doi/10.1093.2Fmolbev.2Fmsp045
", "Cite doi/10.1093.2Fmolbev.2Fmsp096	", "Cite doi/10.1093.2Fnar.2Fgkg402	", "Cite doi/10.1093.2Fnar.2Fgkh121
", "Cite doi/10.1093.2Fnar.2Fgki096	", "Cite doi/10.1093.2Fnar.2Fgki442	", "Cite doi/10.1093.2Fnar.2Fgkj149
", "Cite doi/10.1093.2Fnar.2Fgkl453	", "Cite doi/10.1093.2Fnar.2Fgkl924	", "Cite doi/10.1093.2Fnar.2Fgkm588
", "Cite doi/10.1093.2Fnar.2Fgkn073	", "Cite doi/10.1093.2Fnar.2Fgkn530	", "Cite doi/10.1093.2Fnar.2Fgkn785
", "Cite doi/10.1093.2Fschbul.2Fsbn051	", "Cite doi/10.1095.2Fbiolreprod.104.028647	", "Cite doi/10.1095.2Fbiolreprod.109.078261
", "Cite doi/10.1095.2Fbiolreprod.109.078543	", "Cite doi/10.1096.2Ffj.04-1978com	", "Cite doi/10.1097.2F01.BRS.0000048651.92777.30
", "Cite doi/10.1097.2F01.PHM.0000113403.16617.93	", "Cite doi/10.1097.2F01.PRS.0000101502.22727.5D	", "Cite doi/10.1097.2F01.mao.0000226304.66822.6d
", "Cite doi/10.1097.2F01.mcd.0000220610.24908.a4	", "Cite doi/10.1097.2F01.nrl.0000106921.76055.24	", "Cite doi/10.1097.2FCHI.0b013e3180686d48
", "Cite doi/10.1097.2FEDE.0b013e31815c408a	", "Cite doi/10.1097.2FEDE.0b013e318177813d	", "Cite doi/10.1097.2FEDE.0b013e3181b09332
", "Cite doi/10.1097.2FFPC.0b013e3282f85e26	", "Cite doi/10.1097.2FLGT.0b013e31803c4de0	", "Cite doi/10.1097.2FMOP.0b013e3282f4f97b
", "Cite doi/10.1097.2FOLQ.0b013e3181901e32	", "Cite doi/10.1097.2FPRS.0b013e31817742da	", "Cite doi/10.1097.2FPSY.0b013e31815b00c4
", "Cite doi/10.1097.2FWAD.0b013e31816653bc	", "Cite doi/10.1098.2Frsbl.2009.0532	", "Cite doi/10.1098.2Frspb.1998.0385
", "Cite doi/10.1098.2Frspb.1998.0534	", "Cite doi/10.1098.2Frspb.2007.0465	", "Cite doi/10.1098.2Frspb.2007.0701
", "Cite doi/10.1098.2Frspb.2008.0785	", "Cite doi/10.1098.2Frspb.2008.1655	", "Cite doi/10.1098.2Frspb.2008.1762
", "Cite doi/10.1098.2Frspb.2009.0361	", "Cite doi/10.1098.2Frspb.2009.0752	", "Cite doi/10.1098.2Frsta.2001.0958
", "Cite doi/10.1098.2Frsta.2007.2052	", "Cite doi/10.1098.2Frsta.2008.0131	", "Cite doi/10.1098.2Frsta.2008.0136
", "Cite doi/10.1098.2Frstb.2000.0612	", "Cite doi/10.1098.2Frstb.2001.0971	", "Cite doi/10.1098.2Frstb.2008.0010
", "Cite doi/10.1098.2Frstb.2008.0242	", "Cite doi/10.1099.2Fjmm.0.2008.2F003459-0	", "Cite doi/10.1101.2Fgad.1127103
", "Cite doi/10.1101.2Fgad.1343705	", "Cite doi/10.1101.2Fgad.1643108	", "Cite doi/10.1101.2Fgr.6146507
", "Cite doi/10.1101.2Fgr.6406307	", "Cite doi/10.1101.2Fgr.7101908	", "Cite doi/10.1101.2Fsqb.2006.71.024
", "Cite doi/10.1103.2FPhysRevD.72.034004	", "Cite doi/10.1103.2FPhysRevE.77.032901	", "Cite doi/10.1103.2FPhysRevLett.100.192003
", "Cite doi/10.1103.2FPhysRevLett.102.020404	", "Cite doi/10.1103.2FPhysRevLett.74.2626	", "Cite doi/10.1103.2FPhysRevLett.74.2632
", "Cite doi/10.1103.2FPhysRevLett.98.041801	", "Cite doi/10.1103.2FPhysRevLett.98.181802	", "Cite doi/10.1103.2FRevModPhys.75.121
", "Cite doi/10.1107.2FS0365110X56000334	", "Cite doi/10.1108.2F09513550810863204	", "Cite doi/10.1109.2F17.62329
", "Cite doi/10.1109.2FIPDPS.2005.100	", "Cite doi/10.1109.2FeScience.2008.128	", "Cite doi/10.1111.25252Fj.1420-9101.2007.01483.x
", "Cite doi/10.1111.2F1467-9566.00289	", "Cite doi/10.1111.2F1468-2478.00042	", "Cite doi/10.1111.2F1475-4754.t01-1-00068
", "Cite doi/10.1111.2F1475-4983.00283	", "Cite doi/10.1111.2Fj.0013-9580.2004.13504.x	", "Cite doi/10.1111.2Fj.0022-3646.1981.00105.x
", "Cite doi/10.1111.2Fj.0022-3646.1982.00477.x	", "Cite doi/10.1111.2Fj.0031-0239.2000.00134.x	", "Cite doi/10.1111.2Fj.0031-0239.2004.00374.x
", "Cite doi/10.1111.2Fj.0031-0239.2004.00395.x	", "Cite doi/10.1111.2Fj.0031-0239.2004.00408.x	", "Cite doi/10.1111.2Fj.1360-0443.2007.01846.x
", "Cite doi/10.1111.2Fj.1365-2230.2008.02825.x	", "Cite doi/10.1111.2Fj.1365-246X.1989.tb06010.x	", "Cite doi/10.1111.2Fj.1365-2664.2005.01082.x
", "Cite doi/10.1111.2Fj.1365-2788.2005.00660.x	", "Cite doi/10.1111.2Fj.1365-2796.2009.02088.x	", "Cite doi/10.1111.2Fj.1365-3091.1981.tb01691.x
", "Cite doi/10.1111.2Fj.1399-0004.2008.00995.x	", "Cite doi/10.1111.2Fj.1399-6576.1995.tb04325.x	", "Cite doi/10.1111.2Fj.1420-9101.2007.01483.x
", "Cite doi/10.1111.2Fj.1439-0507.2007.01405.x	", "Cite doi/10.1111.2Fj.1439-0531.2006.00828.x	", "Cite doi/10.1111.2Fj.1440-1819.2006.01518.x
", "Cite doi/10.1111.2Fj.1463-6395.2007.00281.x	", "Cite doi/10.1111.2Fj.1463-6409.1983.tb00510.x	", "Cite doi/10.1111.2Fj.1467-789X.2007.00393.x
", "Cite doi/10.1111.2Fj.1467-8624.2007.01113.x	", "Cite doi/10.1111.2Fj.1467-9574.1972.tb00191.x	", "Cite doi/10.1111.2Fj.1468-1331.2009.02618.x
", "Cite doi/10.1111.2Fj.1468-2982.2007.01288.x	", "Cite doi/10.1111.2Fj.1468-2982.2008.01837.x	", "Cite doi/10.1111.2Fj.1469-185X.1972.tb00975.x
", "Cite doi/10.1111.2Fj.1469-185X.2008.00071.x	", "Cite doi/10.1111.2Fj.1469-7610.2004.00262.x	", "Cite doi/10.1111.2Fj.1469-7610.2005.01584.x
", "Cite doi/10.1111.2Fj.1471-4159.1990.tb01889.x	", "Cite doi/10.1111.2Fj.1474-9726.2005.00152.x	", "Cite doi/10.1111.2Fj.1475-4983.2005.00471.x
", "Cite doi/10.1111.2Fj.1475-4983.2006.00533.x	", "Cite doi/10.1111.2Fj.1475-4983.2006.00552.x	", "Cite doi/10.1111.2Fj.1475-4983.2006.00613.x
", "Cite doi/10.1111.2Fj.1502-3931.1973.tb01199.x	", "Cite doi/10.1111.2Fj.1502-3931.1975.tb01310.x	", "Cite doi/10.1111.2Fj.1502-3931.1987.tb00797.x
", "Cite doi/10.1111.2Fj.1502-3931.1988.tb01769.x	", "Cite doi/10.1111.2Fj.1502-3931.1989.tb01341.x	", "Cite doi/10.1111.2Fj.1502-3931.1989.tb01437.x
", "Cite doi/10.1111.2Fj.1502-3931.1990.tb01361.x	", "Cite doi/10.1111.2Fj.1502-3931.1993.tb01509.x	", "Cite doi/10.1111.2Fj.1502-3931.1993.tb01510.x
", "Cite doi/10.1111.2Fj.1502-3931.1995.tb01587.x	", "Cite doi/10.1111.2Fj.1502-3931.1996.tb01844.x	", "Cite doi/10.1111.2Fj.1502-3931.1997.tb00440.x
", "Cite doi/10.1111.2Fj.1502-3931.2008.00132.x	", "Cite doi/10.1111.2Fj.1502-3931.2008.00133.x	", "Cite doi/10.1111.2Fj.1502-3931.2008.00138.x
", "Cite doi/10.1111.2Fj.1502-3931.2008.00141.x	", "Cite doi/10.1111.2Fj.1502-3931.2009.00165.x	", "Cite doi/10.1111.2Fj.1502-3931.2009.00169.x
", "Cite doi/10.1111.2Fj.1526-4610.2005.05066.x	", "Cite doi/10.1111.2Fj.1526-4610.2005.05068.x	", "Cite doi/10.1111.2Fj.1526-4610.2006.00374.x
", "Cite doi/10.1111.2Fj.1526-4610.2007.00953.x	", "Cite doi/10.1111.2Fj.1529-8817.1969.tb02585.x	", "Cite doi/10.1111.2Fj.1540-8183.2005.04068.x
", "Cite doi/10.1111.2Fj.1540-8191.2008.00603.x	", "Cite doi/10.1111.2Fj.1600-0854.2007.00560.x	", "Cite doi/10.1111.2Fj.1651-2227.2008.01207.x
", "Cite doi/10.1111.2Fj.1743-6109.2009.01418.x	", "Cite doi/10.1111.2Fj.1745-7599.2009.00417.x	", "Cite doi/10.1112.2Fblms.2F7.3.225
", "Cite doi/10.1112.2Fjlms.2Fs1-41.1.385	", "Cite doi/10.1112.2Fplms.2Fs2-42.1.230	", "Cite doi/10.1115.2F1.1861926
", "Cite doi/10.1115.2F1.2807210	", "Cite doi/10.1115.2F1.2829328	", "Cite doi/10.1117.2F12.635041
", "Cite doi/10.1119.2F1.1621032	", "Cite doi/10.1119.2F1.16641	", "Cite doi/10.1121.2F1.1916017
", "Cite doi/10.1124.2Fjpet.109.153908	", "Cite doi/10.1126.25252Fscience.1087231	", "Cite doi/10.1126.25252Fscience.1102127
", "Cite doi/10.1126.25252Fscience.1102417	", "Cite doi/10.1126.25252Fscience.1136897	", "Cite doi/10.1126.25252Fscience.274.5292.1489
", "Cite doi/10.1126.25252Fscience.280.5364.731	", "Cite doi/10.1126.2Fscience.1059827	", "Cite doi/10.1126.2Fscience.1061457
", "Cite doi/10.1126.2Fscience.1063902	", "Cite doi/10.1126.2Fscience.1064034	", "Cite doi/10.1126.2Fscience.1064363
", "Cite doi/10.1126.2Fscience.1067575	", "Cite doi/10.1126.2Fscience.1069609	", "Cite doi/10.1126.2Fscience.1072708
", "Cite doi/10.1126.2Fscience.1075159	", "Cite doi/10.1126.2Fscience.1076181	", "Cite doi/10.1126.2Fscience.1076252
", "Cite doi/10.1126.2Fscience.1081056	", "Cite doi/10.1126.2Fscience.1082025	", "Cite doi/10.1126.2Fscience.1083797
", "Cite doi/10.1126.2Fscience.1085274	", "Cite doi/10.1126.2Fscience.1087231	", "Cite doi/10.1126.2Fscience.1090553
", "Cite doi/10.1126.2Fscience.1099727	", "Cite doi/10.1126.2Fscience.1101012	", "Cite doi/10.1126.2Fscience.1102127
", "Cite doi/10.1126.2Fscience.1102417	", "Cite doi/10.1126.2Fscience.1109004	", "Cite doi/10.1126.2Fscience.1113722
", "Cite doi/10.1126.2Fscience.1117389	", "Cite doi/10.1126.2Fscience.1118265	", "Cite doi/10.1126.2Fscience.1119089
", "Cite doi/10.1126.2Fscience.1120779	", "Cite doi/10.1126.2Fscience.1123253	", "Cite doi/10.1126.2Fscience.1128402
", "Cite doi/10.1126.2Fscience.1128834	", "Cite doi/10.1126.2Fscience.1128908	", "Cite doi/10.1126.2Fscience.1131728
", "Cite doi/10.1126.2Fscience.1133376	", "Cite doi/10.1126.2Fscience.1136110	", "Cite doi/10.1126.2Fscience.1136294
", "Cite doi/10.1126.2Fscience.1136897	", "Cite doi/10.1126.2Fscience.1137284	", "Cite doi/10.1126.2Fscience.1143205
", "Cite doi/10.1126.2Fscience.1143906	", "Cite doi/10.1126.2Fscience.1163886	", "Cite doi/10.1126.2Fscience.1165069
", "Cite doi/10.1126.2Fscience.1166586	", "Cite doi/10.1126.2Fscience.1169237	", "Cite doi/10.1126.2Fscience.1169514
", "Cite doi/10.1126.2Fscience.1169659	", "Cite doi/10.1126.2Fscience.1171255	", "Cite doi/10.1126.2Fscience.139.3559.1046
", "Cite doi/10.1126.2Fscience.140.3569.899	", "Cite doi/10.1126.2Fscience.141.3580.532	", "Cite doi/10.1126.2Fscience.173.4003.1238
", "Cite doi/10.1126.2Fscience.186.4161.311	", "Cite doi/10.1126.2Fscience.240.4855.996	", "Cite doi/10.1126.2Fscience.272.5266.1359
", "Cite doi/10.1126.2Fscience.274.5292.1489	", "Cite doi/10.1126.2Fscience.274.5292.1495	", "Cite doi/10.1126.2Fscience.277.5331.1453
", "Cite doi/10.1126.2Fscience.278.5343.1582	", "Cite doi/10.1126.2Fscience.280.5364.731	", "Cite doi/10.1126.2Fscience.281.5380.1173
", "Cite doi/10.1126.2Fscience.281.5381.1342	", "Cite doi/10.1126.2Fscience.284.5411.65	", "Cite doi/10.1126.2Fscience.288.5464.255
", "Cite doi/10.1126.2Fscience.289.5478.432	", "Cite doi/10.1126.2Fscience.289.5483.1337	", "Cite doi/10.1126.2Fscience.289.5486.1897
", "Cite doi/10.1126.2Fscience.295.5553.247b	", "Cite doi/10.1126.2Fscience.306.5705.2172	", "Cite doi/10.1126.2Fscience.312.5781.1731
", "Cite doi/10.1126.2Fscience.314.5798.401a	", "Cite doi/10.1126.2Fscience.316.5826.813	", "Cite doi/10.1126.2Fscience.323.5914.569
", "Cite doi/10.1126.2Fscience.7761828	", "Cite doi/10.1126.2Fscience.7761836	", "Cite doi/10.1126.2Fscisignal.274pe36
", "Cite doi/10.1126/science.288.5464.255	", "Cite doi/10.1128.2FAAC.00430-07	", "Cite doi/10.1128.2FAAC.00936-08
", "Cite doi/10.1128.2FAAC.50.5.1731-1737.2006	", "Cite doi/10.1128.2FAEM.68.6.3094-3101.2002	", "Cite doi/10.1128.2FCMR.00008-07
", "Cite doi/10.1128.2FJB.00952-08	", "Cite doi/10.1128.2FJB.187.20.6962-6971.2005	", "Cite doi/10.1128.2FJVI.77.18.9733-9737.2003
", "Cite doi/10.1128.2FMCB.01287-06	", "Cite doi/10.1130.25252F2006.2399.25252802.252529	", "Cite doi/10.1130.2F0016-7606.281996.29108.3C0195:GCGBPT.3E2.3.CO.3B2
", "Cite doi/10.1130.2F0016-7606.282000.29112.3C1459:CDPFPA.3E2.0.CO.3B2	", "Cite doi/10.1130.2F0091-7613.281990.29018.3C1153:TAFSIA.3E2.3.CO.3B2	", "Cite doi/10.1130.2F0091-7613.281993.29021.3C0805:LCFVTW.3E2.3.CO.3B2
", "Cite doi/10.1130.2F0091-7613.281997.29025.3C0483:HCIAPW.3E2.3.CO.3B2	", "Cite doi/10.1130.2F0091-7613.281998.29026.3C0331:TCTBCC.3E2.3.CO.3B2	", "Cite doi/10.1130.2F0091-7613.281999.29027.3C0987:APONAM.3E2.3.CO.3B2
", "Cite doi/10.1130.2F0091-7613.282000.2928.3C319:IMWATK.3E2.0.CO.3B2	", "Cite doi/10.1130.2F0091-7613.282002.29030.3C0687:CLACST.3E2.0.CO.3B2	", "Cite doi/10.1130.2F0091-7613.282003.29031.3C0557:UESFTL.3E2.0.CO.3B2
", "Cite doi/10.1130.2F1052-5173.282002.29012.3C0004:NEFACC.3E2.0.CO.3B2
", "Cite doi/10.1130.2F2006.2399.2802.29
", "Cite doi/10.1130.2FB25244.1	", "Cite doi/10.1130.2FG19193.1	", "Cite doi/10.1130.2FG20114.1
", "Cite doi/10.1130.2FG20363.1	", "Cite doi/10.1130.2FG21295.1	", "Cite doi/10.1130.2FG23794A.1
", "Cite doi/10.1130.2FG23894A.1	", "Cite doi/10.1130.2FG24385A.1	", "Cite doi/10.1130.2FG24446A.1
", "Cite doi/10.1130.2FGSAT01802A.1	", "Cite doi/10.1134.2FS1028334X07080107	", "Cite doi/10.1136.2Fbmj.328.7455.1529
", "Cite doi/10.1136.2Fbmj.38933.585764.AE	", "Cite doi/10.1136.2Fbmj.39554.592014.BE	", "Cite doi/10.1136.2Fbmj.b2719
", "Cite doi/10.1136.2Femj.2005.032854	", "Cite doi/10.1136.2Fhrt.2003.025700	", "Cite doi/10.1136.2Fhrt.2004.047746
", "Cite doi/10.1136.2Fjmg.2006.048637	", "Cite doi/10.1136.2Fjnnp.2003.025981	", "Cite doi/10.1136.2Fjnnp.2006.112334
", "Cite doi/10.1136.2Fjnnp.74.11.1466	", "Cite doi/10.1136.2Foem.2005.022400	", "Cite doi/10.1136.2Foem.2006.028209
", "Cite doi/10.1136.2Foem.2007.037994	", "Cite doi/10.1136.2Ftc.12.1.105	", "Cite doi/10.1137.2F0214061
", "Cite doi/10.1137.2F080719571	", "Cite doi/10.1139.2Fcjes-35-4-413	", "Cite doi/10.1139.2Fcjes-35-7-827
", "Cite doi/10.1139.2Fcjes-38-2-187	", "Cite doi/10.1142.2FS0218271897000029	", "Cite doi/10.1143.2FPTP.49.652
", "Cite doi/10.1144.2F0016-76492007-023	", "Cite doi/10.1144.2FGSL.SP.1989.047.01.04	", "Cite doi/10.1144.2FGSL.SP.2000.175.01.27
", "Cite doi/10.1144.2Fgsjgs.131.3.0289	", "Cite doi/10.1144.2Fgsjgs.131.6.0661	", "Cite doi/10.1144.2Fgsjgs.144.1.0001
", "Cite doi/10.1144.2Fgsjgs.149.4.0599	", "Cite doi/10.1144.2Fgsjgs.150.6.1035	", "Cite doi/10.1145.2F146382.146383
", "Cite doi/10.1145.2F272991.272995	", "Cite doi/10.1145.2F321386.321394	", "Cite doi/10.1145.2F362248.362272
", "Cite doi/10.1145.2F42372.42381
", "Cite doi/10.1146.25252Fannurev.earth.33.092203.122621
	", "Cite doi/10.1146.25252Fannurev.earth.36.031207.12411
", "Cite doi/10.1146.25252Fannurev.earth.36.031207.124116	", "Cite doi/10.1146.2Fannurev.aa.09.090171.001151	", "Cite doi/10.1146.2Fannurev.anthro.34.030905.154913
", "Cite doi/10.1146.2Fannurev.arplant.043008.091948	", "Cite doi/10.1146.2Fannurev.arplant.48.1.609	", "Cite doi/10.1146.2Fannurev.biochem.72.121801.161520
", "Cite doi/10.1146.2Fannurev.biochem.76.050106.093909	", "Cite doi/10.1146.2Fannurev.earth.33.092203.122621
", "Cite doi/10.1146.2Fannurev.earth.36.031207.12411
", "Cite doi/10.1146.2Fannurev.earth.36.031207.124116	", "Cite doi/10.1146.2Fannurev.earth.36.031207.124256	", "Cite doi/10.1146.2Fannurev.ecolsys.35.112202.130128
", "Cite doi/10.1146.2Fannurev.ecolsys.36.102003.152633	", "Cite doi/10.1146.2Fannurev.energy.30.050504.144308	", "Cite doi/10.1146.2Fannurev.energy.32.041706.124700
", "Cite doi/10.1146.2Fannurev.es.10.110179.001551	", "Cite doi/10.1146.2Fannurev.med.49.1.1	", "Cite doi/10.1146.2Fannurev.physchem.55.091602.094428
", "Cite doi/10.1146.2Fannurev.publhealth.26.021304.144445
", "Cite doi/10.1146/annurev.earth.33.092203.122621
	", "Cite doi/10.1152.2Fjn.00152.2009
", "Cite doi/10.1152.2Fjn.00423.2006	", "Cite doi/10.1155.2F2007.2F57619	", "Cite doi/10.1155.2F2009.2F308985
", "Cite doi/10.1155.2FMI.2005.63	", "Cite doi/10.1159.2F000108111	", "Cite doi/10.1159.2F000109767
", "Cite doi/10.1159.2F000132683	", "Cite doi/10.1159.2F000151589	", "Cite doi/10.1163.2F156854096X00871
", "Cite doi/10.1172.2FJCI112981	", "Cite doi/10.1172.2FJCI21949	", "Cite doi/10.1172.2FJCI30555
", "Cite doi/10.1172.2FJCI35931	", "Cite doi/10.1172.2FJCI37622	", "Cite doi/10.1172.2FJCI37771
", "Cite doi/10.1175.2F1520-0442.281999.29012.3C1117:TSOTTC.3E2.0.CO.3B2	", "Cite doi/10.1175.2F1520-0442.282002.29015.3C0179:LPOTFT.3E2.0.CO.3B2	", "Cite doi/10.1175.2F2007JCLI1838.1
", "Cite doi/10.1175.2F2008BAMS2370.1	", "Cite doi/10.1175.2FJCLI-3308.1	", "Cite doi/10.1175.2FJCLI3800.1
", "Cite doi/10.1176.2Fappi.psy.49.6.470	", "Cite doi/10.1177.2F0037768604043006	", "Cite doi/10.1177.2F0192623308329476
", "Cite doi/10.1177.2F0270467609333728	", "Cite doi/10.1177.2F0883073808315415	", "Cite doi/10.1177.2F096032719501400401
", "Cite doi/10.1177.2F1044207308325995	", "Cite doi/10.1177.2F1073858408327805	", "Cite doi/10.1177.2F1087724X08323844
", "Cite doi/10.1177.2F1362361305049027	", "Cite doi/10.1177.2F1362361305049028	", "Cite doi/10.1177.2F1362361306068507
", "Cite doi/10.1177.2F1362361307075702	", "Cite doi/10.1186.2F1471-2105-8-458	", "Cite doi/10.1186.2F1471-2148-7-188
", "Cite doi/10.1186.2F1471-2156-9-8	", "Cite doi/10.1186.2F1471-2199-8-86	", "Cite doi/10.1186.2F1471-2296-9-30
", "Cite doi/10.1186.2F1471-2458-7-354	", "Cite doi/10.1186.2F1471-2458-9-189	", "Cite doi/10.1186.2F1475-2875-5-23
", "Cite doi/10.1186.2F1476-5918-7-6	", "Cite doi/10.1186.2F1477-5751-8-5	", "Cite doi/10.1186.2F1550-2783-1-2-12
", "Cite doi/10.1186.2F1750-1172-1-26	", "Cite doi/10.1186.2F1750-1172-3-5	", "Cite doi/10.1186.2F1757-1626-1-329
", "Cite doi/10.1186.2Fgb-2005-6-5-r42	", "Cite doi/10.1189.2Fjlb.0607363	", "Cite doi/10.1192.2Fbjp.185.3.196
", "Cite doi/10.1196.2Fannals.1418.005	", "Cite doi/10.1207.2Fs15327752jpa8701 02	", "Cite doi/10.1208.2Fs12248-009-9128-x
", "Cite doi/10.1212.2F01.wnl.0000232737.72555.06	", "Cite doi/10.1212.2F01.wnl.0000262035.87304.89	", "Cite doi/10.1212.2F01.wnl.0000311390.87642.d8
", "Cite doi/10.1212.2F01.wnl.0000318293.28278.33	", "Cite doi/10.1212.2FWNL.0b013e3181b7c1d8	", "Cite doi/10.1227.2F01.NEU.0000219197.33182.3F
", "Cite doi/10.1242.2Fdmm.000331	", "Cite doi/10.1261.2Frna.1110608	", "Cite doi/10.1261.2Frna.751807
", "Cite doi/10.1261.2Frna.876308	", "Cite doi/10.1287.2Fmnsc.48.1.61.14272	", "Cite doi/10.1289.2Fehp.0800045
", "Cite doi/10.1289.2Fehp.0800182	", "Cite doi/10.1289.2Fehp.11342	", "Cite doi/10.1289.2Fehp.9783
", "Cite doi/10.1289.2Fehp.9784	", "Cite doi/10.1289.2Fehp.9785	", "Cite doi/10.1289.2Fehp.9786
", "Cite doi/10.1300.2FJ079v26n01 03	", "Cite doi/10.1348.2F135910708X283760	", "Cite doi/10.1348.2F174866407X272448
", "Cite doi/10.1371.2Fjournal.pbio.0040052	", "Cite doi/10.1371.2Fjournal.pbio.0050156	", "Cite doi/10.1371.2Fjournal.pbio.1000020
", "Cite doi/10.1371.2Fjournal.pcbi.1000204	", "Cite doi/10.1371.2Fjournal.pmed.0020163	", "Cite doi/10.1371.2Fjournal.pmed.0050029
", "Cite doi/10.1371.2Fjournal.pmed.0050112	", "Cite doi/10.1371.2Fjournal.pone.0001979	", "Cite doi/10.1371.2Fjournal.pone.0004022
", "Cite doi/10.1371.2Fjournal.pone.0004288	", "Cite doi/10.1371.2Fjournal.pone.0004389	", "Cite doi/10.1371.2Fjournal.pone.0004732
", "Cite doi/10.1371.2Fjournal.ppat.1000547	", "Cite doi/10.1378.2Fchest.129.1.156	", "Cite doi/10.1503.2Fcmaj.071483
", "Cite doi/10.1504.2FIJISCM.2006.008286	", "Cite doi/10.1523.2FJNEUROSCI.0477-04.2004	", "Cite doi/10.1523.2FJNEUROSCI.0962-09.2009
", "Cite doi/10.1523.2FJNEUROSCI.2145-09.2009	", "Cite doi/10.1523.2FJNEUROSCI.2301-05.2005	", "Cite doi/10.1523.2FJNEUROSCI.3768-06.2007
", "Cite doi/10.1523.2FJNEUROSCI.4010-07.2007	", "Cite doi/10.1530.2Fjrf.0.0380081	", "Cite doi/10.1534.2Fgenetics.103.025361
", "Cite doi/10.1534.2Fgenetics.105.041095	", "Cite doi/10.1534.2Fgenetics.105.046995	", "Cite doi/10.1534.2Fgenetics.107.071001
", "Cite doi/10.1534.2Fgenetics.107.078980	", "Cite doi/10.1534.2Fgenetics.107.080432	", "Cite doi/10.1534.2Fgenetics.108.099275
", "Cite doi/10.1542.2Fpeds.2007-2584	", "Cite doi/10.1542.2Fpeds.2007-3608	", "Cite doi/10.1554.25252F04-003
", "Cite doi/10.1554.2F04-003	", "Cite doi/10.1586.2F14737175.6.3.313	", "Cite doi/10.1590.2FS0100-83582007000300017
", "Cite doi/10.1590.2FS0103-50532008000600011	", "Cite doi/10.1590.2FS1415-790X2009000200002	", "Cite doi/10.1614.2FWS-05-010R
", "Cite doi/10.1614.2FWS-06-001R.1	", "Cite doi/10.1614.2FWS-08-181.1	", "Cite doi/10.1634.2Ftheoncologist.9-6-673
", "Cite doi/10.1666.25252F0094-8373.2525282003.252529029.25253C0349.25253AMDOCAA.25253E2.0.CO.25253B2	", "Cite doi/10.1666.25252F0094-8373.2525282005.252529031.25253C0035.25253ATMDOCI.25253E2.0.CO.25253B2	", "Cite doi/10.1666.2F0022-3360.282002.29076.3C0287:LECSSF.3E2.0.CO.3B2
", "Cite doi/10.1666.2F0022-3360.282003.29077.3C0646:EDANSO.3E2.0.CO.3B2	", "Cite doi/10.1666.2F0022-3360.282004.29078.3C0700:TLCCPF.3E2.0.CO.3B2	", "Cite doi/10.1666.2F0094-8373.282000.29026+0386.3ABPNGNS+2.0.CO.3B2
", "Cite doi/10.1666.2F0094-8373.282000.29026.3C0386:BPNGNS.3E2.0.CO.3B2	", "Cite doi/10.1666.2F0094-8373.282000.29026.3C0529:AHEAAF.3E2.0.CO.3B2	", "Cite doi/10.1666.2F0094-8373.282002.29028.3C0155:LGATIO.3E2.0.CO.3B2
", "Cite doi/10.1666.2F0094-8373.282003.29029.3C0349.3AMDOCAA.3E2.0.CO.3B2	", "Cite doi/10.1666.2F0094-8373.282003.29029.3C0349:MDOCAA.3E2.0.CO.3B2	", "Cite doi/10.1666.2F0094-8373.282005.29031.3C0035.3ATMDOCI.3E2.0.CO.3B2
", "Cite doi/10.1666.2F0094-8373.282005.29031.3C0035:TMDOCI.3E2.0.CO.3B2	", "Cite doi/10.1666.2F0094-8373.282005.29031.5B0094:WSSSGA.5D2.0.CO.3B2	", "Cite doi/10.1666.2F0094-8373.282005.29031.5B0503:BAAUOT.5D2.0.CO.3B2
", "Cite doi/10.1666.2F07-006.1	", "Cite doi/10.1666.2F07026.1	", "Cite doi/10.1666.2F07053.1
", "Cite doi/10.1677.2Fjoe.1.06683	", "Cite doi/10.2110.2Fjsr.2006.053	", "Cite doi/10.2110.2Fpalo.2003.P05-070R
", "Cite doi/10.2110.2Fpalo.2006.p06-085r	", "Cite doi/10.2140.2Fgt.2008.12.2587	", "Cite doi/10.2172.2F948543
", "Cite doi/10.2174.2F092986708785132951	", "Cite doi/10.2174.2F1389201043376715	", "Cite doi/10.2196.2Fjmir.7.1.e5
", "Cite doi/10.2217.2F14750708.4.4.451	", "Cite doi/10.2307.2F1034497	", "Cite doi/10.2307.2F108520
", "Cite doi/10.2307.2F1300291	", "Cite doi/10.2307.2F1301996	", "Cite doi/10.2307.2F1302218
", "Cite doi/10.2307.2F1303389	", "Cite doi/10.2307.2F1304992	", "Cite doi/10.2307.2F1306279
", "Cite doi/10.2307.2F1311112	", "Cite doi/10.2307.2F1685216
", "Cite doi/10.2307.2F1739764
", "Cite doi/10.2307.2F1750042	", "Cite doi/10.2307.2F1926560	", "Cite doi/10.2307.2F2321823
", "Cite doi/10.2307.2F2395681	", "Cite doi/10.2307.2F2400538	", "Cite doi/10.2307.2F2400788
", "Cite doi/10.2307.2F2401005	", "Cite doi/10.2307.2F2401237	", "Cite doi/10.2307.2F2421265
", "Cite doi/10.2307.2F2421501	", "Cite doi/10.2307.2F2446290	", "Cite doi/10.2307.2F2589145
", "Cite doi/10.2307.2F2657027	", "Cite doi/10.2307.2F2939827	", "Cite doi/10.2307.2F3090142
", "Cite doi/10.2307.2F3293691	", "Cite doi/10.2307.2F3515224	", "Cite doi/10.2307.2F3515337
", "Cite doi/10.2307.2F3515338	", "Cite doi/10.2307.2F3565062	", "Cite doi/10.2307.2F4016110
", "Cite doi/10.2307.2F4110037	", "Cite doi/10.2307.2F4178205
", "Cite doi/10.2307.2F51026
", "Cite doi/10.2307.2F56388	", "Cite doi/10.2307.2F987289	", "Cite doi/10.2459.2FJCM.0b013e3283232a45
", "Cite doi/10.2514.2F1.35703	", "Cite doi/10.2533.2F000942904777677605	", "Cite doi/10.2968.2F064002006
", "Cite doi/10.3109.2F01485019208987723	", "Cite doi/10.3171.2FJNS.2F2008.2F109.2F8.2F0325	", "Cite doi/10.4003.2F0740-2783-25.1.25
", "Cite doi/10.1130.2FB25215.1"

);

function nextPage(){
	global $citeDois;
	return "Template:" . trim(array_shift($citeDois));
}

$page = nextPage();

include("expand.php");