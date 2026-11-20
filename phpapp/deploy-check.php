<?php
// ============================================================================
//  EXAACT — DEPLOYMENT CHECK  (administrator browser tool, GENERATED FILE)
//
//  Answers one question: did my upload actually land?
//
//  Deploying happens through a browser File Manager, and the way it fails is
//  quiet — the files at the top level are replaced, the ones inside lib/ and
//  views/ are not, and the application keeps running the old code with no error
//  anywhere. This page compares every PHP file on the server against the
//  release it was built from and names the ones that are stale or missing.
//
//  HOW TO USE IT
//   1. Sign in to EXAACT as usual on your MAIN address (the control install).
//   2. In the same browser open:  /deploy-check.php
//
//  It is ONE file and it carries its own checksums, so it still works when the
//  sub-folders are the very thing that failed to upload.
//
//  READS ONLY. Opens no workspace, runs no migration, writes to no database,
//  changes no file. To anyone who is not a signed-in administrator it returns a
//  plain 404, so it is not discoverable.
//
//  DO NOT EDIT — regenerate with:  php tools/make_deploy_check.php
// ============================================================================

$RELEASE = '9999868 · 2026-11-20 01:35 UTC · 742 files';
$EXPECT  = [
    'api.php' => ['s'=>2282,'h'=>'1b2ca28973254f89'],
    'config.local.sample.php' => ['s'=>3110,'h'=>'bb24c4fcbeb55c85'],
    'cron.php' => ['s'=>26511,'h'=>'db71262177e4a85a'],
    'cron_ads.php' => ['s'=>5306,'h'=>'4aad92eab174ea01'],
    'diagnose.php' => ['s'=>10005,'h'=>'791b255b31782ab7'],
    'index.php' => ['s'=>145428,'h'=>'2ca115b30d758fe1'],
    'lib/access.php' => ['s'=>136912,'h'=>'8810ff5340450a6f'],
    'lib/access_state.php' => ['s'=>10685,'h'=>'4c7da7d6d793745e'],
    'lib/activity.php' => ['s'=>46874,'h'=>'e86aaefccb1bf465'],
    'lib/adspro.php' => ['s'=>28970,'h'=>'205ce39852f9f881'],
    'lib/adsroi.php' => ['s'=>12887,'h'=>'2418d515c52e3905'],
    'lib/adssync.php' => ['s'=>28574,'h'=>'19472065bb76a5a6'],
    'lib/advisor.php' => ['s'=>42552,'h'=>'a2d5c9a01e9815d7'],
    'lib/agreement.php' => ['s'=>30404,'h'=>'850a0f08f100b313'],
    'lib/ai.php' => ['s'=>26190,'h'=>'d08483ebea3044a0'],
    'lib/ai_formgen.php' => ['s'=>11931,'h'=>'3193de5d4fc90414'],
    'lib/approvals_hub.php' => ['s'=>10209,'h'=>'8906816d1ca3ed4f'],
    'lib/approvals_setup.php' => ['s'=>8824,'h'=>'ed490549302eb35e'],
    'lib/areas.php' => ['s'=>49385,'h'=>'e34afe78f1d1a253'],
    'lib/assets.php' => ['s'=>14725,'h'=>'53927f92ca350c91'],
    'lib/attend.php' => ['s'=>13919,'h'=>'695a4eae1fd9e2a9'],
    'lib/attendreview.php' => ['s'=>7794,'h'=>'cfcd8f928e63efad'],
    'lib/audits.php' => ['s'=>41605,'h'=>'f362d70e80a1632d'],
    'lib/backup.php' => ['s'=>24069,'h'=>'55dc55fb9bd907ad'],
    'lib/billable.php' => ['s'=>31721,'h'=>'c58ce56607a680b5'],
    'lib/billing.php' => ['s'=>19524,'h'=>'fe0562c9d18c9446'],
    'lib/bills.php' => ['s'=>11324,'h'=>'498117c26906b1a3'],
    'lib/billunits.php' => ['s'=>65507,'h'=>'59f83e882c2c6ae7'],
    'lib/books.php' => ['s'=>74589,'h'=>'f2e570e9db46790b'],
    'lib/booksbridge.php' => ['s'=>26242,'h'=>'3b2bc304b5c45cce'],
    'lib/booksui.php' => ['s'=>24743,'h'=>'167e036aaf00240e'],
    'lib/bulk.php' => ['s'=>2157,'h'=>'ca3be826f9a4414c'],
    'lib/callprofit.php' => ['s'=>5760,'h'=>'7f81b6e7a9d7835f'],
    'lib/candpool.php' => ['s'=>14733,'h'=>'66e086074ead175c'],
    'lib/candreview.php' => ['s'=>60100,'h'=>'b968d384e2011c26'],
    'lib/capa.php' => ['s'=>34318,'h'=>'cf9edf290b106bfd'],
    'lib/careers.php' => ['s'=>22969,'h'=>'70a9ec1b3acf87e3'],
    'lib/chain.php' => ['s'=>28776,'h'=>'57ec53f950ab8f5c'],
    'lib/commercial.php' => ['s'=>30223,'h'=>'e99c11b15d792501'],
    'lib/comp_config.php' => ['s'=>15349,'h'=>'0b29fea9ee44dbe4'],
    'lib/company.php' => ['s'=>12622,'h'=>'7c747d73981f31f6'],
    'lib/competence.php' => ['s'=>54296,'h'=>'0761c88493b8c827'],
    'lib/complaints.php' => ['s'=>35427,'h'=>'3946b876dd98817e'],
    'lib/compliance.php' => ['s'=>63934,'h'=>'1929c53295fa2343'],
    'lib/compose.php' => ['s'=>6857,'h'=>'676a541309cf956b'],
    'lib/confidentiality.php' => ['s'=>22526,'h'=>'e97b3810463cfdb8'],
    'lib/connect_advisor.php' => ['s'=>4420,'h'=>'f39961034078a66a'],
    'lib/connect_analytics.php' => ['s'=>10322,'h'=>'55ce5e2749ea0bda'],
    'lib/connect_bench.php' => ['s'=>19858,'h'=>'6cf7f987ebca62c4'],
    'lib/connect_bridge.php' => ['s'=>12010,'h'=>'56399a3846dc5e0d'],
    'lib/connect_capability.php' => ['s'=>16610,'h'=>'84e2c581bc36e29c'],
    'lib/connect_channels.php' => ['s'=>17045,'h'=>'5100b89577ea339f'],
    'lib/connect_client_bench.php' => ['s'=>10478,'h'=>'793a2fd9e8dc13e3'],
    'lib/connect_client_dash.php' => ['s'=>10875,'h'=>'3c5daccb1d991609'],
    'lib/connect_client_search.php' => ['s'=>6194,'h'=>'b09091878f09d826'],
    'lib/connect_concierge.php' => ['s'=>5334,'h'=>'96eaa25448a7e919'],
    'lib/connect_conflict.php' => ['s'=>13358,'h'=>'62ea412e04c515b6'],
    'lib/connect_credentials.php' => ['s'=>13244,'h'=>'e8e5b8106c5158c7'],
    'lib/connect_crew.php' => ['s'=>3256,'h'=>'329e3e96bfa354cd'],
    'lib/connect_cv.php' => ['s'=>6178,'h'=>'6e5123e99d862fa0'],
    'lib/connect_deploy.php' => ['s'=>8299,'h'=>'d3d261a16ddc833c'],
    'lib/connect_disputes.php' => ['s'=>4865,'h'=>'56ecbec1a7a03f91'],
    'lib/connect_engage.php' => ['s'=>26296,'h'=>'39422ac0547d20e3'],
    'lib/connect_engvoucher.php' => ['s'=>36966,'h'=>'86f2509529771dab'],
    'lib/connect_geo.php' => ['s'=>19346,'h'=>'9ee996bdd62de2ca'],
    'lib/connect_govern.php' => ['s'=>7181,'h'=>'22ce5ad54e44ce2a'],
    'lib/connect_hiring.php' => ['s'=>5373,'h'=>'f92a4ba2f9c8ccc7'],
    'lib/connect_identity.php' => ['s'=>58186,'h'=>'6500583342a99ab1'],
    'lib/connect_kpi.php' => ['s'=>25750,'h'=>'c866aa2a30f34cfa'],
    'lib/connect_market.php' => ['s'=>78584,'h'=>'d2a5c1eed8221bcd'],
    'lib/connect_match.php' => ['s'=>30007,'h'=>'3b21f28debfcd673'],
    'lib/connect_msg.php' => ['s'=>16414,'h'=>'37fd232a1fba99cc'],
    'lib/connect_org.php' => ['s'=>38956,'h'=>'73886ad8fee22699'],
    'lib/connect_passport.php' => ['s'=>12721,'h'=>'601f5abe95baf95a'],
    'lib/connect_person.php' => ['s'=>7701,'h'=>'7210f0cae01d84e9'],
    'lib/connect_privacy.php' => ['s'=>17610,'h'=>'74640b7aef0d480c'],
    'lib/connect_pro.php' => ['s'=>71861,'h'=>'5373dc7ccbcc976d'],
    'lib/connect_qualtax.php' => ['s'=>24846,'h'=>'ff8da4316ac1a9d6'],
    'lib/connect_rating_disputes.php' => ['s'=>8981,'h'=>'0152a5d87d7e6917'],
    'lib/connect_ratings.php' => ['s'=>9327,'h'=>'da1eb1149c633e73'],
    'lib/connect_reqtools.php' => ['s'=>8395,'h'=>'7bff01ce54e08ac9'],
    'lib/connect_source.php' => ['s'=>8866,'h'=>'dfeb00f12cffb066'],
    'lib/connect_tax_graph.php' => ['s'=>34791,'h'=>'d2bc5c930179f0f6'],
    'lib/connect_taxonomy.php' => ['s'=>9836,'h'=>'3757c0d0d7daeb05'],
    'lib/connect_trust.php' => ['s'=>7228,'h'=>'27ac2d7be0dd9e28'],
    'lib/connect_verify.php' => ['s'=>20687,'h'=>'ea42fc218233bb53'],
    'lib/contracts.php' => ['s'=>135702,'h'=>'e564f0d17dc89b4b'],
    'lib/controldocs.php' => ['s'=>15272,'h'=>'e99fcf054f6e6b1e'],
    'lib/costing.php' => ['s'=>60952,'h'=>'3c1d7c02b92ef9d2'],
    'lib/costing_heads.php' => ['s'=>40151,'h'=>'347d406f1639a3e0'],
    'lib/costrecon.php' => ['s'=>11258,'h'=>'a6af5a16164f3458'],
    'lib/cpanel.php' => ['s'=>8181,'h'=>'9322634c72aed841'],
    'lib/crm.php' => ['s'=>361942,'h'=>'8de93fd02ec33771'],
    'lib/crmdash.php' => ['s'=>16482,'h'=>'82aa17a5a8d3ea34'],
    'lib/customer360.php' => ['s'=>24357,'h'=>'cb22da260409122c'],
    'lib/customforms.php' => ['s'=>12896,'h'=>'a60a82554280a959'],
    'lib/cvp.php' => ['s'=>73775,'h'=>'809b497cd0223a3b'],
    'lib/datacontrol.php' => ['s'=>37664,'h'=>'1206a5a742cd1266'],
    'lib/datatable.php' => ['s'=>15699,'h'=>'dfea30bbc69ccef3'],
    'lib/db.php' => ['s'=>59880,'h'=>'2a3541c73173a065'],
    'lib/decisionrules.php' => ['s'=>12116,'h'=>'7f7d5a9cd6e144ef'],
    'lib/dedupe.php' => ['s'=>12027,'h'=>'de5bdc1c0769610b'],
    'lib/deptorg.php' => ['s'=>36122,'h'=>'1407a36dc1683f07'],
    'lib/disclosure.php' => ['s'=>6740,'h'=>'ec97b627169b5d9e'],
    'lib/doc_templates.php' => ['s'=>22541,'h'=>'12907f537ff4a1de'],
    'lib/engagement.php' => ['s'=>13574,'h'=>'5e7f1b56d6898562'],
    'lib/entitlement_migrate.php' => ['s'=>10281,'h'=>'bfd1dc34dec3e38b'],
    'lib/entity360.php' => ['s'=>4909,'h'=>'3d405b448a70e1e0'],
    'lib/equipment.php' => ['s'=>24106,'h'=>'266e71060ecba007'],
    'lib/expenses.php' => ['s'=>58221,'h'=>'0fc45ccfc87cef70'],
    'lib/features.php' => ['s'=>16415,'h'=>'fbed148b050d20cd'],
    'lib/finevent.php' => ['s'=>10845,'h'=>'427a0be8d5561266'],
    'lib/formdesign.php' => ['s'=>70869,'h'=>'5eea52cab914fd77'],
    'lib/geofence.php' => ['s'=>20775,'h'=>'0bda93308e9f8e10'],
    'lib/helpers.php' => ['s'=>32450,'h'=>'81cbce146a45b663'],
    'lib/hiringreq.php' => ['s'=>131056,'h'=>'1b540969c73557de'],
    'lib/hwpoints.php' => ['s'=>18677,'h'=>'672ca54ebe9239f0'],
    'lib/idems.php' => ['s'=>853069,'h'=>'6006a29f1409305c'],
    'lib/idems_autoform.php' => ['s'=>9923,'h'=>'d4520363260f9c48'],
    'lib/identity.php' => ['s'=>45233,'h'=>'4267d813af8001e8'],
    'lib/impartiality.php' => ['s'=>18224,'h'=>'70a0c7b051724178'],
    'lib/indexes.php' => ['s'=>13279,'h'=>'92bb3debc6451b96'],
    'lib/industry.php' => ['s'=>39409,'h'=>'b5bd5ce5c0cc8b8b'],
    'lib/inspectorprofile.php' => ['s'=>3920,'h'=>'f995d0ac6cff433f'],
    'lib/install_mode.php' => ['s'=>4935,'h'=>'0c1d5ab96ff54b68'],
    'lib/invready.php' => ['s'=>6922,'h'=>'0f7637be388be1fc'],
    'lib/joblock.php' => ['s'=>13155,'h'=>'47c05d15e9e8e13b'],
    'lib/leads.php' => ['s'=>112158,'h'=>'922415bc2121801c'],
    'lib/licence.php' => ['s'=>31138,'h'=>'ae1f563479d4009c'],
    'lib/licenceissue.php' => ['s'=>33731,'h'=>'509a3d0e2d93d72f'],
    'lib/licencekey.php' => ['s'=>29869,'h'=>'db9ba00546be996a'],
    'lib/licencesync.php' => ['s'=>10254,'h'=>'c17cfa585e721f62'],
    'lib/lookups.php' => ['s'=>84028,'h'=>'75ef853c20a6f21f'],
    'lib/methods.php' => ['s'=>13959,'h'=>'14969626618c15f8'],
    'lib/mghsso.php' => ['s'=>10002,'h'=>'80f043e73ff44d13'],
    'lib/milestones.php' => ['s'=>38422,'h'=>'05a69210e9cd33c0'],
    'lib/mis.php' => ['s'=>27960,'h'=>'dbec071cf2188988'],
    'lib/mkt_billing.php' => ['s'=>19531,'h'=>'2a916695fe12469b'],
    'lib/mkt_credits.php' => ['s'=>9969,'h'=>'3165d4af5817062e'],
    'lib/mkt_escrow.php' => ['s'=>13034,'h'=>'d93f784413329f54'],
    'lib/mkt_fees.php' => ['s'=>7830,'h'=>'d357fad88aebd4ae'],
    'lib/mkt_gates.php' => ['s'=>3563,'h'=>'33cc1d5744a2da96'],
    'lib/mkt_ledger.php' => ['s'=>10121,'h'=>'a26d0ec31a84af92'],
    'lib/mkt_pay.php' => ['s'=>14969,'h'=>'786376f15b4fb714'],
    'lib/mkt_plans.php' => ['s'=>10874,'h'=>'c47b9e64a348f146'],
    'lib/mkt_rules.php' => ['s'=>12129,'h'=>'f541702a11884133'],
    'lib/mkt_subs.php' => ['s'=>10951,'h'=>'4e6d28aa7ffc7f07'],
    'lib/nav.php' => ['s'=>31887,'h'=>'6583f2f5e4630df3'],
    'lib/navindex.php' => ['s'=>16581,'h'=>'3588320cf5f54569'],
    'lib/ncdca.php' => ['s'=>44960,'h'=>'d64c2c2e6eddc5e1'],
    'lib/ncr.php' => ['s'=>35783,'h'=>'45066d8f78ed60f3'],
    'lib/nextaction.php' => ['s'=>15255,'h'=>'85f9797146341fa6'],
    'lib/numbering.php' => ['s'=>9566,'h'=>'a25fc05a5781006a'],
    'lib/onboarding.php' => ['s'=>4308,'h'=>'1c5b3e553b964601'],
    'lib/opportunities.php' => ['s'=>91586,'h'=>'7359e24e90ed8b28'],
    'lib/ops.php' => ['s'=>799303,'h'=>'4b70b46191752cc7'],
    'lib/orgadmin.php' => ['s'=>80268,'h'=>'c12cbfa088698eb0'],
    'lib/organogram.php' => ['s'=>20449,'h'=>'b2f7eb7b0c9d4c4f'],
    'lib/owner_home.php' => ['s'=>4387,'h'=>'9ecd071c5bba4b79'],
    'lib/packs.php' => ['s'=>14404,'h'=>'2c631d79aea60e49'],
    'lib/partnerimport.php' => ['s'=>23621,'h'=>'2a3a83a143b8b2a4'],
    'lib/party.php' => ['s'=>11622,'h'=>'55ad01fbf381e780'],
    'lib/pdf.php' => ['s'=>27213,'h'=>'d11c50106498d27f'],
    'lib/pdso.php' => ['s'=>65055,'h'=>'02af1816c0f6ab90'],
    'lib/pipelines.php' => ['s'=>16353,'h'=>'809cf75364fe7198'],
    'lib/platform_mail.php' => ['s'=>22419,'h'=>'95ffd0e9fea3845d'],
    'lib/portal.php' => ['s'=>131990,'h'=>'61d1dddb093a60cd'],
    'lib/position.php' => ['s'=>19441,'h'=>'a0a9d1a9e6a9b10c'],
    'lib/preflight.php' => ['s'=>5451,'h'=>'97e742bbddf7fbd8'],
    'lib/pricing_admin.php' => ['s'=>16307,'h'=>'d7af986c89fc5cfc'],
    'lib/projcosting.php' => ['s'=>149318,'h'=>'c28f6f5f744b42bc'],
    'lib/pwreset.php' => ['s'=>9509,'h'=>'50675e1d291df7b0'],
    'lib/qr.php' => ['s'=>17936,'h'=>'4d50b69b53bfa5e2'],
    'lib/qualitycase.php' => ['s'=>6179,'h'=>'280289c99f157cd7'],
    'lib/rating.php' => ['s'=>7766,'h'=>'f5bbd79552189e10'],
    'lib/receivables.php' => ['s'=>13894,'h'=>'1f35c368b2fc5591'],
    'lib/recruit.php' => ['s'=>141492,'h'=>'679db7be7f44af16'],
    'lib/recruit_approval.php' => ['s'=>180934,'h'=>'1c42bf018555c10c'],
    'lib/recruit_assign.php' => ['s'=>41534,'h'=>'4407af2317dae47f'],
    'lib/recruit_cc.php' => ['s'=>34246,'h'=>'80729bc2ce7f76bd'],
    'lib/recruit_exec.php' => ['s'=>24603,'h'=>'0546b6c003d3493c'],
    'lib/recruit_export.php' => ['s'=>7876,'h'=>'91b4b8f7905d50ba'],
    'lib/recruit_fulfil.php' => ['s'=>51701,'h'=>'3c914bbeb1483e3d'],
    'lib/recruit_iv.php' => ['s'=>67492,'h'=>'21f83821878c59d6'],
    'lib/recruit_jd.php' => ['s'=>11566,'h'=>'caf8a43b155e90a6'],
    'lib/recruit_kpi.php' => ['s'=>44791,'h'=>'01e1256f752079c1'],
    'lib/recruit_offer.php' => ['s'=>57685,'h'=>'833dafd741942d5c'],
    'lib/recruit_pool.php' => ['s'=>24785,'h'=>'549c7a30349353ff'],
    'lib/recruitpipe.php' => ['s'=>86108,'h'=>'18f34a4eda0503c9'],
    'lib/reportreview.php' => ['s'=>23222,'h'=>'ae43c4d37883823d'],
    'lib/reqfulfil.php' => ['s'=>16474,'h'=>'aeda367b914bbe2e'],
    'lib/reqversion.php' => ['s'=>90240,'h'=>'b16207cafd9e94cc'],
    'lib/reset.php' => ['s'=>11466,'h'=>'43a903038c87a40e'],
    'lib/retention.php' => ['s'=>6587,'h'=>'deb6a6d4a270fa36'],
    'lib/revrecon.php' => ['s'=>13441,'h'=>'eb8777d1398cdada'],
    'lib/risks.php' => ['s'=>10571,'h'=>'83a96e16878654a2'],
    'lib/saas_payments.php' => ['s'=>45249,'h'=>'e7d6c37566200fec'],
    'lib/saas_provision_cli.php' => ['s'=>4816,'h'=>'76f04fae8c24f082'],
    'lib/saas_sync_cli.php' => ['s'=>2939,'h'=>'5fb733d865f2f34b'],
    'lib/saas_tenants.php' => ['s'=>111383,'h'=>'b58ea4138e6da3c2'],
    'lib/sales_more.php' => ['s'=>39514,'h'=>'5b5017f6b021d6bd'],
    'lib/sales_simple.php' => ['s'=>26432,'h'=>'f158e3dfad65b53d'],
    'lib/samples.php' => ['s'=>14677,'h'=>'fa38e5f628b43f6a'],
    'lib/satisfaction.php' => ['s'=>16437,'h'=>'4cb166c77b0e2650'],
    'lib/schedboard.php' => ['s'=>10814,'h'=>'b44e9f8e5496c806'],
    'lib/schedule.php' => ['s'=>36573,'h'=>'470dcddca5ac672a'],
    'lib/search.php' => ['s'=>35435,'h'=>'619be1760f1b4f8b'],
    'lib/security.php' => ['s'=>42414,'h'=>'cf069b4894c513ec'],
    'lib/seed_connect.php' => ['s'=>22943,'h'=>'06f835c70073b5b5'],
    'lib/seed_costing.php' => ['s'=>7626,'h'=>'d377be1adc35b4da'],
    'lib/seed_demo.php' => ['s'=>114790,'h'=>'2ac4084f42c85800'],
    'lib/seed_demo_c.php' => ['s'=>125521,'h'=>'7765694ba131b91c'],
    'lib/seed_recruit_cc.php' => ['s'=>10345,'h'=>'b2ead314e97b24a3'],
    'lib/seed_scenario_s01.php' => ['s'=>34921,'h'=>'37246a2099961d2a'],
    'lib/seed_scenario_s02.php' => ['s'=>37739,'h'=>'154252c0747bd4e3'],
    'lib/seed_scenario_s03.php' => ['s'=>28140,'h'=>'fb92e0808d12db0e'],
    'lib/seed_scenario_s04.php' => ['s'=>15797,'h'=>'afbaeeaef614756d'],
    'lib/seed_scenario_s05.php' => ['s'=>10982,'h'=>'77ae3daf8c427816'],
    'lib/seed_scenario_s06.php' => ['s'=>13472,'h'=>'7e7c02ab15233f58'],
    'lib/selfserve.php' => ['s'=>76676,'h'=>'8da7922a75077225'],
    'lib/services.php' => ['s'=>28431,'h'=>'6fc99ee80df3ff34'],
    'lib/settingmeta.php' => ['s'=>13132,'h'=>'b6974b55eac391fe'],
    'lib/settlement.php' => ['s'=>5360,'h'=>'a591eb89078ac83e'],
    'lib/setup.php' => ['s'=>23072,'h'=>'7af387096568ab14'],
    'lib/setup_cockpit.php' => ['s'=>37879,'h'=>'bbcf94c682ba041c'],
    'lib/setupqa.php' => ['s'=>26758,'h'=>'851d056deae616e3'],
    'lib/stagegate.php' => ['s'=>27344,'h'=>'2503c46ea861318e'],
    'lib/superadmin.php' => ['s'=>22099,'h'=>'f3e910a1caa81d54'],
    'lib/tally.php' => ['s'=>44939,'h'=>'e04b2add6cf6fc1b'],
    'lib/tapi.php' => ['s'=>69389,'h'=>'5a3a65f638b08cdf'],
    'lib/tapi_dash.php' => ['s'=>20955,'h'=>'274ca4690a0f813e'],
    'lib/tapi_gov.php' => ['s'=>17290,'h'=>'111e443d157ce2ce'],
    'lib/tapi_score.php' => ['s'=>14322,'h'=>'e2256ab85fab255c'],
    'lib/tasks.php' => ['s'=>11239,'h'=>'82c11af4ebccd03c'],
    'lib/tenant_migrate.php' => ['s'=>20675,'h'=>'3a8be680c3200403'],
    'lib/tenant_signup.php' => ['s'=>17074,'h'=>'8cff333a3b2b95ec'],
    'lib/tenants.php' => ['s'=>23379,'h'=>'8bb1d9d0ef7cc82e'],
    'lib/terms.php' => ['s'=>29625,'h'=>'9b33b16b6836c601'],
    'lib/timesheet.php' => ['s'=>9604,'h'=>'a6a8bd9555d2fd07'],
    'lib/timezone.php' => ['s'=>1766,'h'=>'3f6dac267d9867fa'],
    'lib/tmplpreview.php' => ['s'=>3520,'h'=>'770170cf566d21da'],
    'lib/tosrm.php' => ['s'=>180894,'h'=>'9022f18abbcf6251'],
    'lib/trace_audit.php' => ['s'=>17883,'h'=>'b7fce5750cd3b3df'],
    'lib/trace_seed.php' => ['s'=>26988,'h'=>'e70c801fdc26c1cd'],
    'lib/trust.php' => ['s'=>56478,'h'=>'4d90df0f5c8397fc'],
    'lib/uire.php' => ['s'=>41223,'h'=>'67855daf043230d2'],
    'lib/urade.php' => ['s'=>35221,'h'=>'1284a8d26a3403e4'],
    'lib/urfe.php' => ['s'=>24902,'h'=>'ddec890bdd7b6775'],
    'lib/uvaae.php' => ['s'=>49896,'h'=>'df442e4432ef0dbc'],
    'lib/uvae.php' => ['s'=>40803,'h'=>'86f05a499753144e'],
    'lib/vendor360.php' => ['s'=>3923,'h'=>'107a364d8222c144'],
    'lib/visibility.php' => ['s'=>4843,'h'=>'281d50af3a4d26fa'],
    'lib/vocab.php' => ['s'=>19053,'h'=>'a9a1c5c84a2f8d0b'],
    'lib/voucher_approval.php' => ['s'=>40417,'h'=>'049983b34395151e'],
    'lib/webhookq.php' => ['s'=>7844,'h'=>'10e0dde19b283fdf'],
    'lib/workforce.php' => ['s'=>59629,'h'=>'da1b6c1ed976b169'],
    'lib/workspace.php' => ['s'=>15210,'h'=>'7cf36505c40be5c2'],
    'manifest.php' => ['s'=>1665,'h'=>'cd62f3f83b1a7969'],
    'phase1-inventory.php' => ['s'=>23590,'h'=>'37dcca72c7f637f2'],
    'refresh.php' => ['s'=>3674,'h'=>'c828dd3fd0db7613'],
    'router.php' => ['s'=>855,'h'=>'824052bfe3521b8a'],
    'tenants.sample.php' => ['s'=>1889,'h'=>'4768416662a6303d'],
    'tools/acceptance-10co.php' => ['s'=>5989,'h'=>'67994cb354317f64'],
    'tools/candidate-pool.php' => ['s'=>2595,'h'=>'a7379ad701bf74e0'],
    'tools/check-columns.php' => ['s'=>3097,'h'=>'0bac905d7ac2ba3c'],
    'tools/check-dupes.php' => ['s'=>3511,'h'=>'b29441eab390155a'],
    'tools/check-keys.php' => ['s'=>3938,'h'=>'93eebae62a9d204f'],
    'tools/check-strings.php' => ['s'=>2613,'h'=>'45d093de527a4f59'],
    'tools/cold-start.php' => ['s'=>5928,'h'=>'7d6054f24562bcca'],
    'tools/cost-reconciliation.php' => ['s'=>2884,'h'=>'43103a0ad282892b'],
    'tools/engagement-parity.php' => ['s'=>2433,'h'=>'caecc781a3ede147'],
    'tools/g3-seed.php' => ['s'=>6955,'h'=>'aa86eedab4155b1f'],
    'tools/g4-seed.php' => ['s'=>3269,'h'=>'ee13f091f6933cb5'],
    'tools/g5-data-audit.php' => ['s'=>4416,'h'=>'57925e6eebb6aaab'],
    'tools/g5-seed.php' => ['s'=>3454,'h'=>'028b959ca0476d86'],
    'tools/g6-seed.php' => ['s'=>2826,'h'=>'510d2e87fd1a411a'],
    'tools/g6b-seed.php' => ['s'=>4137,'h'=>'c8030e3d63576a47'],
    'tools/licence-issue.php' => ['s'=>6116,'h'=>'563a0af4621b9578'],
    'tools/permission-audit.php' => ['s'=>12272,'h'=>'754ed8413717268e'],
    'tools/phase1_inventory_engine.php' => ['s'=>53763,'h'=>'587c1ddbd38ed330'],
    'tools/reset-admin.php' => ['s'=>2377,'h'=>'5eba3ddcf695da09'],
    'tools/sbom.php' => ['s'=>5314,'h'=>'7da7803dd8b6a816'],
    'tools/seed-connect.php' => ['s'=>2576,'h'=>'f60af657b9feb4fe'],
    'tools/seed-demo.php' => ['s'=>3956,'h'=>'f2cf2d0bb5d680f0'],
    'tools/seed-scenario-s01.php' => ['s'=>2695,'h'=>'c1b2cdafec9b4705'],
    'tools/seed-scenario-s02.php' => ['s'=>2591,'h'=>'62a71ad01f79be96'],
    'tools/seed-scenario-s03.php' => ['s'=>1412,'h'=>'7bbc4dd8ac4f0398'],
    'tools/seed-scenario-s04.php' => ['s'=>1412,'h'=>'9e0e6ea7ecea379d'],
    'tools/seed-scenario-s05.php' => ['s'=>1483,'h'=>'23b5f892caeda2d4'],
    'tools/seed-scenario-s06.php' => ['s'=>1921,'h'=>'8f8d70cf53e84b5b'],
    'tools/smoke-router.php' => ['s'=>636,'h'=>'098054abc53c9c16'],
    'tools/trace-audit.php' => ['s'=>2789,'h'=>'d95c97624d3d470e'],
    'tools/trace-thread.php' => ['s'=>4962,'h'=>'cb20d74d1a6eb46e'],
    'views/admin.php' => ['s'=>1090,'h'=>'aeb34e3396fd4ac5'],
    'views/dashboard.php' => ['s'=>49378,'h'=>'e3b2f77bbdbaa1a2'],
    'views/detail.php' => ['s'=>31616,'h'=>'abc92b50b90fd075'],
    'views/forgot_password.php' => ['s'=>2716,'h'=>'c041f8176182b36c'],
    'views/form.php' => ['s'=>27096,'h'=>'a7bfacfbbb03806b'],
    'views/layout_bottom.php' => ['s'=>1408,'h'=>'2a9e353ce309d65c'],
    'views/layout_embed_bottom.php' => ['s'=>65,'h'=>'63f4c118c80caf4b'],
    'views/layout_embed_top.php' => ['s'=>831,'h'=>'935b74de46ce9938'],
    'views/layout_top.php' => ['s'=>38348,'h'=>'361ca39d49e144c6'],
    'views/list.php' => ['s'=>7104,'h'=>'3296cc51a5ed6147'],
    'views/login.php' => ['s'=>602,'h'=>'ff266c37dfd19bb0'],
    'views/login_page.php' => ['s'=>11338,'h'=>'734d686d97d0d88e'],
    'views/notfound.php' => ['s'=>212,'h'=>'0b4feab07b715eec'],
    'views/ops/_allocation_panel.php' => ['s'=>9018,'h'=>'76426e99a74cf039'],
    'views/ops/_billto.php' => ['s'=>3566,'h'=>'32adc002a4740d69'],
    'views/ops/_company_payments.php' => ['s'=>10420,'h'=>'04a68a98cbb89a4b'],
    'views/ops/_contract_sign.php' => ['s'=>4653,'h'=>'de84716682202d86'],
    'views/ops/_contract_signers.php' => ['s'=>3018,'h'=>'3990db07da17f16a'],
    'views/ops/_cx_closing.php' => ['s'=>2793,'h'=>'b1fce8acee6efab0'],
    'views/ops/_deputation_panel.php' => ['s'=>13606,'h'=>'79fdffaa2186ac34'],
    'views/ops/_exp_day_fields.php' => ['s'=>6749,'h'=>'d0cba7db53ec713f'],
    'views/ops/_glance_strip.php' => ['s'=>5313,'h'=>'e52df179c80ecccc'],
    'views/ops/_issue_panel.php' => ['s'=>8621,'h'=>'8f7e08e2edf793d4'],
    'views/ops/_job_billing.php' => ['s'=>11286,'h'=>'eca4b42766d077f8'],
    'views/ops/_log_form.php' => ['s'=>6556,'h'=>'070324e0ac66d4fb'],
    'views/ops/_milestones.php' => ['s'=>14368,'h'=>'08111f8a19e03346'],
    'views/ops/_ops_registers.php' => ['s'=>6510,'h'=>'403bb12d7870ae8e'],
    'views/ops/_perm_verb_grid.php' => ['s'=>5876,'h'=>'8c4af262a49e2628'],
    'views/ops/_perm_verb_grid_assets.php' => ['s'=>9794,'h'=>'6a34014ee132d672'],
    'views/ops/_recruit_triage.php' => ['s'=>13073,'h'=>'f8a23127583795ae'],
    'views/ops/_setup_sections.php' => ['s'=>3430,'h'=>'c80b52dfec51aebf'],
    'views/ops/_subscription_builder.php' => ['s'=>3981,'h'=>'702e6e3d22959301'],
    'views/ops/_subscription_paid.php' => ['s'=>11285,'h'=>'ba54b936cf3bbac9'],
    'views/ops/_subscription_plan.php' => ['s'=>7630,'h'=>'eb9f6fd31c353731'],
    'views/ops/_sx_ui.php' => ['s'=>10746,'h'=>'e19731895ba19138'],
    'views/ops/access.php' => ['s'=>9240,'h'=>'faa8963e6437c387'],
    'views/ops/access_notice.php' => ['s'=>2060,'h'=>'b25acc590f21d59a'],
    'views/ops/activities.php' => ['s'=>3751,'h'=>'22620c2f6225c534'],
    'views/ops/adspro.php' => ['s'=>13351,'h'=>'ab9092f4fbe2cbbc'],
    'views/ops/adsroi.php' => ['s'=>8594,'h'=>'f3eaf082491fd7aa'],
    'views/ops/advisor.php' => ['s'=>9193,'h'=>'592527ab30f1be7d'],
    'views/ops/agency_staff.php' => ['s'=>3970,'h'=>'abaaf8e4f95477fc'],
    'views/ops/agreement.php' => ['s'=>1958,'h'=>'692efd73750ecab3'],
    'views/ops/ai_forms.php' => ['s'=>6642,'h'=>'f593b555a7dad4e0'],
    'views/ops/ai_settings.php' => ['s'=>3916,'h'=>'ac3a734628f292f8'],
    'views/ops/ai_topup_pay.php' => ['s'=>2949,'h'=>'b0e78ac5ea5cdb3b'],
    'views/ops/approval_delegations.php' => ['s'=>6928,'h'=>'959d76879c9b5644'],
    'views/ops/approval_rules.php' => ['s'=>26272,'h'=>'49af8fd72c390266'],
    'views/ops/approvals.php' => ['s'=>9973,'h'=>'a84193fe0cab9c74'],
    'views/ops/approvals_setup.php' => ['s'=>3905,'h'=>'87bc1b9015e1f58b'],
    'views/ops/area_home.php' => ['s'=>4611,'h'=>'718227061d3895e3'],
    'views/ops/asset_register.php' => ['s'=>12566,'h'=>'5973afebeb7207d5'],
    'views/ops/attendance_recon.php' => ['s'=>3064,'h'=>'d7aa936250745627'],
    'views/ops/attendance_review.php' => ['s'=>2801,'h'=>'510ddd176e0a7d48'],
    'views/ops/audit_detail.php' => ['s'=>6317,'h'=>'12f351cdcc6a464b'],
    'views/ops/audit_form.php' => ['s'=>2331,'h'=>'a9a442d6ca2ccadb'],
    'views/ops/audits_list.php' => ['s'=>4123,'h'=>'be8889ef0738b867'],
    'views/ops/availability.php' => ['s'=>17528,'h'=>'37e0f573cb2082d9'],
    'views/ops/backup.php' => ['s'=>5706,'h'=>'d41a3e89843b1791'],
    'views/ops/billable_events.php' => ['s'=>11361,'h'=>'de6b1404d818ac74'],
    'views/ops/billing.php' => ['s'=>8045,'h'=>'cb1638b6facf39b7'],
    'views/ops/billing_pay.php' => ['s'=>7488,'h'=>'e0a9c1b93616ba0f'],
    'views/ops/books_bridge.php' => ['s'=>6061,'h'=>'9d89c6e1a4160652'],
    'views/ops/call_detail.php' => ['s'=>28295,'h'=>'41535e2a9a34e991'],
    'views/ops/call_form.php' => ['s'=>68685,'h'=>'599f688ab18db9bb'],
    'views/ops/call_profit.php' => ['s'=>10712,'h'=>'fc36bf0e2e4e2b8f'],
    'views/ops/calls.php' => ['s'=>11689,'h'=>'d7865a3523e372f3'],
    'views/ops/candidate_detail.php' => ['s'=>59133,'h'=>'76be987bfc6f381a'],
    'views/ops/candidate_form.php' => ['s'=>23912,'h'=>'90683fc31fe39634'],
    'views/ops/candidate_list.php' => ['s'=>3310,'h'=>'8955fac0d68ffecf'],
    'views/ops/candidate_pool.php' => ['s'=>5398,'h'=>'2cdc518e8b7a8c77'],
    'views/ops/capa_detail.php' => ['s'=>15203,'h'=>'ecabaafcce017518'],
    'views/ops/capa_form.php' => ['s'=>3908,'h'=>'0693bf097f403c91'],
    'views/ops/capa_list.php' => ['s'=>4603,'h'=>'97c1d4d8dd7bd777'],
    'views/ops/capacity_outlook.php' => ['s'=>2202,'h'=>'0e5bc8b8b7aa73c1'],
    'views/ops/careers_admin.php' => ['s'=>8247,'h'=>'874a655abdfbeaf7'],
    'views/ops/cdoc_detail.php' => ['s'=>3293,'h'=>'307e5c9bfef2dc21'],
    'views/ops/cdoc_form.php' => ['s'=>3536,'h'=>'5dcf952f5452bd2d'],
    'views/ops/cdocs_list.php' => ['s'=>2082,'h'=>'01f0478c1bbf2767'],
    'views/ops/cform_record_form.php' => ['s'=>1329,'h'=>'f334d93f2d2b94dc'],
    'views/ops/cform_record_view.php' => ['s'=>1343,'h'=>'9275bcd66f908d48'],
    'views/ops/cform_records.php' => ['s'=>2901,'h'=>'ba11725333160623'],
    'views/ops/cforms_admin.php' => ['s'=>4492,'h'=>'4c0c8835df9e3286'],
    'views/ops/change_password.php' => ['s'=>819,'h'=>'07a7c01d844eed39'],
    'views/ops/client_holds.php' => ['s'=>2903,'h'=>'5687a162f310c58e'],
    'views/ops/cockpit_forms.php' => ['s'=>1956,'h'=>'c70846e6e910e1ba'],
    'views/ops/cockpit_home.php' => ['s'=>6795,'h'=>'4f8fac9a8e081de4'],
    'views/ops/cockpit_modules.php' => ['s'=>14999,'h'=>'f0e1fcbd9a19e097'],
    'views/ops/cockpit_profile.php' => ['s'=>3536,'h'=>'2e86d3bf2bd4f402'],
    'views/ops/command_centre.php' => ['s'=>5154,'h'=>'f735c622f331be07'],
    'views/ops/comp_setup.php' => ['s'=>7385,'h'=>'7821208806df4fb8'],
    'views/ops/company.php' => ['s'=>7290,'h'=>'61f2692ced1c6268'],
    'views/ops/competence.php' => ['s'=>15651,'h'=>'ca44c656cf8b01a4'],
    'views/ops/complaint_detail.php' => ['s'=>16358,'h'=>'e20440677ea221dd'],
    'views/ops/complaint_form.php' => ['s'=>5326,'h'=>'ec96aab76573bf95'],
    'views/ops/complaints.php' => ['s'=>6387,'h'=>'bcf319a1a8b814a1'],
    'views/ops/complaints_policy.php' => ['s'=>2574,'h'=>'2d77d01b6217f346'],
    'views/ops/compliance.php' => ['s'=>5550,'h'=>'c6645a47fb823737'],
    'views/ops/conf_breach.php' => ['s'=>4350,'h'=>'87f7764d73f8e733'],
    'views/ops/confidentiality.php' => ['s'=>10985,'h'=>'b776a94d995c5093'],
    'views/ops/connect_access_requests.php' => ['s'=>3559,'h'=>'d41a12901ce83a6c'],
    'views/ops/connect_analytics.php' => ['s'=>11104,'h'=>'8dd877519f5c4e00'],
    'views/ops/connect_bench.php' => ['s'=>10892,'h'=>'9d85f88f0ae62847'],
    'views/ops/connect_capabilities.php' => ['s'=>5457,'h'=>'704f0c7a4dc3d083'],
    'views/ops/connect_channels.php' => ['s'=>9038,'h'=>'6343d3d8021eac82'],
    'views/ops/connect_concierge.php' => ['s'=>8668,'h'=>'5ab5d6d7d8d9cc88'],
    'views/ops/connect_front.php' => ['s'=>12697,'h'=>'dd35a00608d1a0c0'],
    'views/ops/connect_identity.php' => ['s'=>5849,'h'=>'14b648589596fcad'],
    'views/ops/connect_join.php' => ['s'=>9329,'h'=>'88bb7cc9b1e7a868'],
    'views/ops/connect_match_weights.php' => ['s'=>3856,'h'=>'14f2f37f5872848b'],
    'views/ops/connect_messages.php' => ['s'=>5961,'h'=>'30e18b49e07ffa4e'],
    'views/ops/connect_orgs.php' => ['s'=>4763,'h'=>'52cc8cfafc0fd31c'],
    'views/ops/connect_passport_public.php' => ['s'=>7127,'h'=>'3f62795610fdc8a5'],
    'views/ops/connect_passport_share.php' => ['s'=>3238,'h'=>'001e14194e7c30e0'],
    'views/ops/connect_qualifications.php' => ['s'=>17605,'h'=>'3e0dabe90148f43a'],
    'views/ops/connect_requirement.php' => ['s'=>59388,'h'=>'2986471bab5aa903'],
    'views/ops/connect_requirements.php' => ['s'=>10758,'h'=>'8bb5b90113e21c24'],
    'views/ops/connect_source.php' => ['s'=>4918,'h'=>'a8882cf9e99ec24b'],
    'views/ops/connect_talent.php' => ['s'=>9173,'h'=>'1b391a894a2345f8'],
    'views/ops/connect_taxonomy.php' => ['s'=>6402,'h'=>'0357e7d5e840b119'],
    'views/ops/connect_taxonomy_admin.php' => ['s'=>11051,'h'=>'db0b340f99a00f89'],
    'views/ops/connect_verify.php' => ['s'=>5528,'h'=>'47b1c892e99a1dd2'],
    'views/ops/consents.php' => ['s'=>3523,'h'=>'5dd8e9b08a104408'],
    'views/ops/contract_detail.php' => ['s'=>25207,'h'=>'8046a9bbad290fd7'],
    'views/ops/contract_openings.php' => ['s'=>3110,'h'=>'594330aca347203c'],
    'views/ops/contract_overrides.php' => ['s'=>8886,'h'=>'e22fdf59af73f743'],
    'views/ops/contracts_to_register.php' => ['s'=>6244,'h'=>'0a369cc93d61f989'],
    'views/ops/cost_reconciliation.php' => ['s'=>5536,'h'=>'8322d9fbc4cbf282'],
    'views/ops/cost_run.php' => ['s'=>11201,'h'=>'dfa54532b26e22e9'],
    'views/ops/costing_heads.php' => ['s'=>15423,'h'=>'aa940dfe37e0d272'],
    'views/ops/credit_note.php' => ['s'=>5629,'h'=>'c4d9d2bccd6d37eb'],
    'views/ops/crm/_quote_costing_tab.php' => ['s'=>4966,'h'=>'6bbf83ad573da464'],
    'views/ops/crm/approval_levels_inline.php' => ['s'=>6971,'h'=>'926169633cfce5b0'],
    'views/ops/crm/approval_rule_form.php' => ['s'=>3668,'h'=>'0dc628db27deb0a7'],
    'views/ops/crm/approval_rule_list.php' => ['s'=>3282,'h'=>'531cb94b6c0d3205'],
    'views/ops/crm/inquiry_form.php' => ['s'=>11107,'h'=>'afc56ace2f550cda'],
    'views/ops/crm/inquiry_list.php' => ['s'=>9719,'h'=>'a2c022184c51567e'],
    'views/ops/crm/quote_detail.php' => ['s'=>86458,'h'=>'5835c97a624be501'],
    'views/ops/crm/quote_external.php' => ['s'=>8907,'h'=>'7aa7bd1259a0c79d'],
    'views/ops/crm/quote_form.php' => ['s'=>50351,'h'=>'7e0cf1462d9ffd25'],
    'views/ops/crm/quote_list.php' => ['s'=>8069,'h'=>'00f644592ad593ca'],
    'views/ops/crm/reports.php' => ['s'=>3252,'h'=>'f30c7776ec26baf9'],
    'views/ops/crm/template_form.php' => ['s'=>3958,'h'=>'3671a20d6c01c474'],
    'views/ops/crm/template_list.php' => ['s'=>8793,'h'=>'9923952768e69153'],
    'views/ops/crm_dashboard.php' => ['s'=>14070,'h'=>'8e955c065d32eb1b'],
    'views/ops/custom_fields.php' => ['s'=>4958,'h'=>'f2f969e3c56dfed4'],
    'views/ops/customer360.php' => ['s'=>29946,'h'=>'130537b2f4af0414'],
    'views/ops/data_control.php' => ['s'=>19266,'h'=>'8f0ae88fcb869398'],
    'views/ops/data_requests.php' => ['s'=>4866,'h'=>'840d2672933c1726'],
    'views/ops/dedupe.php' => ['s'=>7066,'h'=>'6ddfbed6d6b16b97'],
    'views/ops/department_admin.php' => ['s'=>12013,'h'=>'70b3d5969a1c2461'],
    'views/ops/departments.php' => ['s'=>5364,'h'=>'173353934339e7a3'],
    'views/ops/departures.php' => ['s'=>6072,'h'=>'e741ab44fd0e3992'],
    'views/ops/deputations.php' => ['s'=>9928,'h'=>'64f37c872f20d2ad'],
    'views/ops/disclosure.php' => ['s'=>4387,'h'=>'ed150ed2c62611a3'],
    'views/ops/doc_templates.php' => ['s'=>5800,'h'=>'4d07685a58e926ac'],
    'views/ops/drule_detail.php' => ['s'=>2851,'h'=>'bcc2e35358281ff2'],
    'views/ops/drule_form.php' => ['s'=>3210,'h'=>'c78899841dbba75e'],
    'views/ops/drules_list.php' => ['s'=>1711,'h'=>'386819892e490314'],
    'views/ops/entity360.php' => ['s'=>701,'h'=>'15c95b885b7f2760'],
    'views/ops/equipment_form.php' => ['s'=>11381,'h'=>'aa04777211a8cdb5'],
    'views/ops/equipment_list.php' => ['s'=>2709,'h'=>'418d6eca782ea187'],
    'views/ops/evidence_review.php' => ['s'=>10250,'h'=>'0d5e95d804610f40'],
    'views/ops/flow_gaps.php' => ['s'=>3287,'h'=>'f2bdad6e4475e0b2'],
    'views/ops/form_designer.php' => ['s'=>19293,'h'=>'27b031e7b0dc2719'],
    'views/ops/gst_invoices.php' => ['s'=>7766,'h'=>'83b70e6c6233d288'],
    'views/ops/hierarchy.php' => ['s'=>58373,'h'=>'9c79ccc859ce1ad5'],
    'views/ops/hiring_request.php' => ['s'=>54378,'h'=>'e1f245b26dc111ea'],
    'views/ops/hiring_request_list.php' => ['s'=>2926,'h'=>'8d3ad4babef1d0fa'],
    'views/ops/hwpoints.php' => ['s'=>3338,'h'=>'679fdb6488397c4a'],
    'views/ops/idems/approval_rules.php' => ['s'=>7590,'h'=>'20729ce1dceb96ce'],
    'views/ops/idems/approver_map.php' => ['s'=>3169,'h'=>'af3592b23852e1f0'],
    'views/ops/idems/audit.php' => ['s'=>9969,'h'=>'c87823506d7dd3af'],
    'views/ops/idems/autoform.php' => ['s'=>1843,'h'=>'21f7d3b796eadd9c'],
    'views/ops/idems/builder.php' => ['s'=>36007,'h'=>'ad9b1250d05c804b'],
    'views/ops/idems/doc_detail.php' => ['s'=>70599,'h'=>'5521fc4e6b032b0f'],
    'views/ops/idems/doc_form.php' => ['s'=>13090,'h'=>'217d09816175b5ea'],
    'views/ops/idems/endorse_detail.php' => ['s'=>6842,'h'=>'0d0bb9d2cac2c51b'],
    'views/ops/idems/endorse_form.php' => ['s'=>6191,'h'=>'ca143cb1e88e4080'],
    'views/ops/idems/endorse_list.php' => ['s'=>3343,'h'=>'9d8b7216f4077e43'],
    'views/ops/idems/evidence.php' => ['s'=>7251,'h'=>'e8071e9c2392edf5'],
    'views/ops/idems/expediting_projects.php' => ['s'=>7487,'h'=>'985eba46c62f3fbf'],
    'views/ops/idems/expediting_register.php' => ['s'=>5565,'h'=>'c5cc9af5b82e1195'],
    'views/ops/idems/fill.php' => ['s'=>43762,'h'=>'1aef045ceb03289c'],
    'views/ops/idems/form_from_template.php' => ['s'=>5951,'h'=>'6d5564043e9f4610'],
    'views/ops/idems/learning.php' => ['s'=>5436,'h'=>'f48c6e84ca5636a3'],
    'views/ops/idems/my_signature.php' => ['s'=>2691,'h'=>'f3807693f90c0988'],
    'views/ops/idems/numbering.php' => ['s'=>3204,'h'=>'ef033baf9a5af1c3'],
    'views/ops/idems/phrases.php' => ['s'=>5950,'h'=>'57197d803cb89b23'],
    'views/ops/idems/register.php' => ['s'=>4622,'h'=>'ef8f3c7dc46dbe30'],
    'views/ops/idems/release_register.php' => ['s'=>3461,'h'=>'7483d987b817b057'],
    'views/ops/idems/report_types.php' => ['s'=>9367,'h'=>'170c091c55fb39f6'],
    'views/ops/idems/review.php' => ['s'=>9367,'h'=>'504ff2d9d8ee4929'],
    'views/ops/idems/smart.php' => ['s'=>3300,'h'=>'177c5ce1d1942bb1'],
    'views/ops/idems/template_preview.php' => ['s'=>3791,'h'=>'c3cf9d7740404fa5'],
    'views/ops/idems/templates.php' => ['s'=>14277,'h'=>'fe4b72e931302735'],
    'views/ops/idems/vendor_detail.php' => ['s'=>24871,'h'=>'9c0e67966fd2c6e3'],
    'views/ops/idems/vendor_register.php' => ['s'=>5260,'h'=>'2305ea33a98e653b'],
    'views/ops/idems/vet_review.php' => ['s'=>4751,'h'=>'69fb91e13962a24f'],
    'views/ops/idems/vetting_checklist.php' => ['s'=>3981,'h'=>'40eb0a58e25fe047'],
    'views/ops/idems/writing.php' => ['s'=>3588,'h'=>'3c241ff132871b7e'],
    'views/ops/identity.php' => ['s'=>11167,'h'=>'ffc0ba3f68494001'],
    'views/ops/identity_access.php' => ['s'=>4128,'h'=>'ff6f20e65d1a7b30'],
    'views/ops/impartiality.php' => ['s'=>8907,'h'=>'13d8584b2f8a0791'],
    'views/ops/incident_detail.php' => ['s'=>4503,'h'=>'2c7ba200a5fc2960'],
    'views/ops/incident_form.php' => ['s'=>5464,'h'=>'e475b7f16801c901'],
    'views/ops/incidents.php' => ['s'=>2842,'h'=>'b7d86d97e273a923'],
    'views/ops/industry.php' => ['s'=>6150,'h'=>'ce9826e516dd0a52'],
    'views/ops/inspector_form.php' => ['s'=>33891,'h'=>'c1770abafde31a08'],
    'views/ops/inspector_list.php' => ['s'=>3906,'h'=>'bce93ef5a50d93c3'],
    'views/ops/inspector_profile.php' => ['s'=>7251,'h'=>'50ffb7d7db054da2'],
    'views/ops/integrations.php' => ['s'=>2215,'h'=>'1f2932ba4a67f99c'],
    'views/ops/invoice_detail.php' => ['s'=>23289,'h'=>'3e7e4f0919002c45'],
    'views/ops/invoice_form.php' => ['s'=>7338,'h'=>'10f924f4e6ce968b'],
    'views/ops/invoice_print.php' => ['s'=>11137,'h'=>'490b71602dbefc4f'],
    'views/ops/invoices.php' => ['s'=>5206,'h'=>'fffceed85678fb70'],
    'views/ops/invoicing.php' => ['s'=>4380,'h'=>'01efe77afde56500'],
    'views/ops/issues.php' => ['s'=>5267,'h'=>'dcb81bc09ecdf357'],
    'views/ops/job_close.php' => ['s'=>6240,'h'=>'5cd26384f386e589'],
    'views/ops/job_detail.php' => ['s'=>92849,'h'=>'1c76636c42832c1b'],
    'views/ops/job_form.php' => ['s'=>58256,'h'=>'febdd33268c6e59e'],
    'views/ops/jobs.php' => ['s'=>7058,'h'=>'54c963c415ee7600'],
    'views/ops/lead_convert.php' => ['s'=>3639,'h'=>'4994bccc5c3b066d'],
    'views/ops/lead_detail.php' => ['s'=>36437,'h'=>'d6790425d4942d7d'],
    'views/ops/lead_form.php' => ['s'=>13316,'h'=>'4b62e8d6ec3009bd'],
    'views/ops/leads.php' => ['s'=>14514,'h'=>'fd384bd79901f753'],
    'views/ops/ledger.php' => ['s'=>4304,'h'=>'414f7ffeb7052c79'],
    'views/ops/licence.php' => ['s'=>10341,'h'=>'0d30ee2ac4f1a785'],
    'views/ops/licence_issue.php' => ['s'=>12371,'h'=>'6da20bc55a394a9a'],
    'views/ops/licence_issued.php' => ['s'=>2204,'h'=>'2c7cb641bad57031'],
    'views/ops/lookup_values.php' => ['s'=>10707,'h'=>'3f541f5634e5813c'],
    'views/ops/lookups.php' => ['s'=>8091,'h'=>'0ef1bdbf91e77b3d'],
    'views/ops/master_form.php' => ['s'=>2467,'h'=>'070f77709f4e7491'],
    'views/ops/master_list.php' => ['s'=>2490,'h'=>'2bfa4aca6e45fa7f'],
    'views/ops/masters.php' => ['s'=>12001,'h'=>'979ed08b2397dab4'],
    'views/ops/method_detail.php' => ['s'=>4149,'h'=>'6fa263fc474157f8'],
    'views/ops/method_form.php' => ['s'=>3213,'h'=>'7fe89933bdbb7c9b'],
    'views/ops/methods_list.php' => ['s'=>2048,'h'=>'4bd95c6498a08deb'],
    'views/ops/mis.php' => ['s'=>15764,'h'=>'94d775f961a7f0a0'],
    'views/ops/mkt_escrow.php' => ['s'=>7994,'h'=>'138ac744ad7ef5fd'],
    'views/ops/mkt_gates.php' => ['s'=>1969,'h'=>'af43ddabd3ca2144'],
    'views/ops/mkt_ledger.php' => ['s'=>5301,'h'=>'f40ec4dcf524421e'],
    'views/ops/mkt_plans.php' => ['s'=>18953,'h'=>'62d9f9926d380631'],
    'views/ops/mkt_rules.php' => ['s'=>8850,'h'=>'2eecad81e3d8280c'],
    'views/ops/module_locked.php' => ['s'=>2419,'h'=>'82de67be8483b36c'],
    'views/ops/my_approvals.php' => ['s'=>7336,'h'=>'02451dd7d2c0b2a7'],
    'views/ops/my_interviews.php' => ['s'=>2245,'h'=>'e27bb0992ced1b42'],
    'views/ops/my_jobs.php' => ['s'=>14032,'h'=>'159c4a98491545b8'],
    'views/ops/my_work.php' => ['s'=>5843,'h'=>'ec265183f9eba395'],
    'views/ops/ncr_detail.php' => ['s'=>8954,'h'=>'c5398e9ab34e9f9c'],
    'views/ops/ncr_form.php' => ['s'=>4330,'h'=>'e54b0782526cc326'],
    'views/ops/ncr_list.php' => ['s'=>2266,'h'=>'416909219ce7dee9'],
    'views/ops/notifications.php' => ['s'=>3968,'h'=>'07b038e4bc260647'],
    'views/ops/office_finance.php' => ['s'=>10379,'h'=>'5391012e7e28c8b5'],
    'views/ops/operations_home.php' => ['s'=>16889,'h'=>'5e9df2e4207f6766'],
    'views/ops/opportunities.php' => ['s'=>7083,'h'=>'cb35efd1f99ed59d'],
    'views/ops/opportunity_detail.php' => ['s'=>39812,'h'=>'09456912b6861f59'],
    'views/ops/opportunity_form.php' => ['s'=>6556,'h'=>'f49a383cb87c744c'],
    'views/ops/ops_desk.php' => ['s'=>5950,'h'=>'50cc74a4ae6338bb'],
    'views/ops/owner_home.php' => ['s'=>7755,'h'=>'9be8dd1b0498872a'],
    'views/ops/partner_import.php' => ['s'=>6405,'h'=>'dd873e0e376783b1'],
    'views/ops/person_erase.php' => ['s'=>2748,'h'=>'790e73d880be6d75'],
    'views/ops/pipeline_edit.php' => ['s'=>7535,'h'=>'8c3a481ed9efd038'],
    'views/ops/pipelines.php' => ['s'=>4890,'h'=>'66209f5a33fc84b9'],
    'views/ops/platform_setup.php' => ['s'=>16348,'h'=>'0e24ab8fe2b81fb3'],
    'views/ops/portal_user_perms.php' => ['s'=>4540,'h'=>'0fe213d62f20ec7a'],
    'views/ops/portal_users.php' => ['s'=>13024,'h'=>'2939cf629be0d7a8'],
    'views/ops/positions.php' => ['s'=>6531,'h'=>'a2b8b745a2be0986'],
    'views/ops/positions_import.php' => ['s'=>7450,'h'=>'06acd96271eb8540'],
    'views/ops/positions_org.php' => ['s'=>4761,'h'=>'f2346193aac18b77'],
    'views/ops/preflight.php' => ['s'=>2844,'h'=>'b076eae0d7c568c2'],
    'views/ops/preorder_checklist.php' => ['s'=>1902,'h'=>'71d84531e6250a45'],
    'views/ops/pricing_usage.php' => ['s'=>15803,'h'=>'134ce5fe3a7d8892'],
    'views/ops/privacy.php' => ['s'=>1458,'h'=>'cc20feb046ed0b1a'],
    'views/ops/product_package.php' => ['s'=>4732,'h'=>'a87a7a169d94fe14'],
    'views/ops/profitability_detail.php' => ['s'=>10185,'h'=>'f49f71530b916b7e'],
    'views/ops/profitability_list.php' => ['s'=>9655,'h'=>'8fed2c9925d511dc'],
    'views/ops/project_costing.php' => ['s'=>86158,'h'=>'8bb29949a3a858f5'],
    'views/ops/project_costing_print.php' => ['s'=>8890,'h'=>'c9bd04a7e38ab185'],
    'views/ops/project_costings.php' => ['s'=>7257,'h'=>'6b370a63d62ba78b'],
    'views/ops/raise_call.php' => ['s'=>9191,'h'=>'74e5158695003eb2'],
    'views/ops/rating_disputes.php' => ['s'=>5556,'h'=>'09fa077f65f93427'],
    'views/ops/ratings.php' => ['s'=>5089,'h'=>'bd3ffc93de714b83'],
    'views/ops/receipt_detail.php' => ['s'=>7020,'h'=>'b1d9c72a807c670f'],
    'views/ops/receipt_form.php' => ['s'=>4000,'h'=>'06e91efa64e86f40'],
    'views/ops/receipts.php' => ['s'=>3359,'h'=>'27bc04ca35a7b2eb'],
    'views/ops/receivables.php' => ['s'=>5635,'h'=>'53e496f95a581e31'],
    'views/ops/recruit_pipelines.php' => ['s'=>12010,'h'=>'0f10ee50c42c3fa4'],
    'views/ops/recruitment_cc.php' => ['s'=>47542,'h'=>'3f96d77ce4094b37'],
    'views/ops/recruitment_home.php' => ['s'=>15139,'h'=>'6dab318f5eb0a7f7'],
    'views/ops/recurring.php' => ['s'=>3953,'h'=>'2bb8c968338703fb'],
    'views/ops/reimbursable_dedup.php' => ['s'=>5091,'h'=>'7d1fa08d1a6e4cfd'],
    'views/ops/report_reviews.php' => ['s'=>5133,'h'=>'5430d377e91ab88c'],
    'views/ops/reports.php' => ['s'=>15006,'h'=>'c0238ade91734a27'],
    'views/ops/requisition_detail.php' => ['s'=>33583,'h'=>'bc10b46313595fe2'],
    'views/ops/requisition_form.php' => ['s'=>68447,'h'=>'83b3a4020c214fd6'],
    'views/ops/requisition_list.php' => ['s'=>2658,'h'=>'bb32a939b2dc11fb'],
    'views/ops/reset_data.php' => ['s'=>3854,'h'=>'fab684e410ed2055'],
    'views/ops/retention.php' => ['s'=>3191,'h'=>'4a77f1a998ca84ff'],
    'views/ops/revenue_reconciliation.php' => ['s'=>5594,'h'=>'0c49027d138f7f85'],
    'views/ops/review_detail.php' => ['s'=>8910,'h'=>'07d9520c1f5cfd04'],
    'views/ops/reviews_list.php' => ['s'=>3722,'h'=>'73d37de8c2018676'],
    'views/ops/risk_detail.php' => ['s'=>2785,'h'=>'6cb7db3ecd5524e8'],
    'views/ops/risk_form.php' => ['s'=>3356,'h'=>'f43057f55e88ff02'],
    'views/ops/risks_list.php' => ['s'=>2204,'h'=>'93906156b7d808ab'],
    'views/ops/role_workspaces.php' => ['s'=>4048,'h'=>'66938f5a5139889f'],
    'views/ops/saas_companies.php' => ['s'=>40162,'h'=>'a50515c38e714dfa'],
    'views/ops/sales_approvals.php' => ['s'=>1894,'h'=>'5150d3fb29f9d891'],
    'views/ops/sample_detail.php' => ['s'=>4311,'h'=>'4529a1888c336f30'],
    'views/ops/sample_form.php' => ['s'=>4129,'h'=>'dfabaedae75c6f9f'],
    'views/ops/samples_list.php' => ['s'=>2173,'h'=>'18bdce8f8a7621a9'],
    'views/ops/satisfaction_detail.php' => ['s'=>6501,'h'=>'09540942852b575c'],
    'views/ops/satisfaction_form.php' => ['s'=>1969,'h'=>'9dc84e16742605c5'],
    'views/ops/satisfaction_list.php' => ['s'=>3787,'h'=>'4d78826e8120b1d1'],
    'views/ops/sbu_pl.php' => ['s'=>11945,'h'=>'c83f680183a94a96'],
    'views/ops/schedule_board.php' => ['s'=>10666,'h'=>'351d543604cd020e'],
    'views/ops/search.php' => ['s'=>5963,'h'=>'dd6a5c4fca87e87c'],
    'views/ops/service_formats.php' => ['s'=>2923,'h'=>'0da7c4881ffb154c'],
    'views/ops/service_scope.php' => ['s'=>8596,'h'=>'580a7c044d31aa3c'],
    'views/ops/settings.php' => ['s'=>73653,'h'=>'35c12f9315912169'],
    'views/ops/setup.php' => ['s'=>5595,'h'=>'e4bcbe1609aa72c5'],
    'views/ops/setup_questions.php' => ['s'=>1325,'h'=>'415af61db2343f16'],
    'views/ops/site_docs.php' => ['s'=>4927,'h'=>'f03c1d9c89e53436'],
    'views/ops/sla_targets.php' => ['s'=>2640,'h'=>'e78061dbc1d27d5e'],
    'views/ops/sso.php' => ['s'=>5411,'h'=>'7e627345af817c60'],
    'views/ops/stage_gates.php' => ['s'=>10651,'h'=>'7f4c82ebccb47103'],
    'views/ops/subscription.php' => ['s'=>13841,'h'=>'0eb9c504cde3fe01'],
    'views/ops/subscription_invoice.php' => ['s'=>6945,'h'=>'67de42af41c1d614'],
    'views/ops/super_admin.php' => ['s'=>25523,'h'=>'276844b10ac9c368'],
    'views/ops/sx_board.php' => ['s'=>4010,'h'=>'2598db8655e8380f'],
    'views/ops/sx_dash.php' => ['s'=>7755,'h'=>'02ecebff991a172f'],
    'views/ops/sx_home.php' => ['s'=>7617,'h'=>'d71233a6f9ffa414'],
    'views/ops/sx_lead.php' => ['s'=>4006,'h'=>'4f438a8b297b9ead'],
    'views/ops/sx_orders.php' => ['s'=>4224,'h'=>'2b0a2b924425709e'],
    'views/ops/sx_renewals.php' => ['s'=>2886,'h'=>'6ae05e57195455cc'],
    'views/ops/sx_settings.php' => ['s'=>5093,'h'=>'1fd6dd22a647a57d'],
    'views/ops/sx_targets.php' => ['s'=>4456,'h'=>'1950c45d2974274f'],
    'views/ops/sx_today.php' => ['s'=>7356,'h'=>'e3d7cca0c8a8df7d'],
    'views/ops/system_status.php' => ['s'=>2399,'h'=>'90287c341b6166f4'],
    'views/ops/tally_export.php' => ['s'=>13104,'h'=>'4b8ef6be8b7b91b4'],
    'views/ops/tapi.php' => ['s'=>988,'h'=>'33442189ab50828c'],
    'views/ops/tapi_alerts.php' => ['s'=>3591,'h'=>'5c5b4b3a672220c0'],
    'views/ops/tapi_drill.php' => ['s'=>2439,'h'=>'b94ddc7da157384d'],
    'views/ops/tapi_kpi_edit.php' => ['s'=>3924,'h'=>'9db373db67ed0a99'],
    'views/ops/tapi_kpis.php' => ['s'=>1839,'h'=>'d1b6619884a5314f'],
    'views/ops/tapi_quality.php' => ['s'=>1897,'h'=>'528820bfbaad610c'],
    'views/ops/tapi_review.php' => ['s'=>3031,'h'=>'987b2221e47da38b'],
    'views/ops/tapi_scorecard.php' => ['s'=>2166,'h'=>'c547d85711e404f9'],
    'views/ops/tapi_snapshot.php' => ['s'=>2775,'h'=>'74aad9f368ad107f'],
    'views/ops/tasks.php' => ['s'=>4329,'h'=>'0c2ffedf67f10633'],
    'views/ops/tenants.php' => ['s'=>20840,'h'=>'a9ca05fa224fc775'],
    'views/ops/terminology.php' => ['s'=>4401,'h'=>'984504c0b825174e'],
    'views/ops/timesheet.php' => ['s'=>5962,'h'=>'bef076d2397f0133'],
    'views/ops/to_bill.php' => ['s'=>7030,'h'=>'c2399620b3458380'],
    'views/ops/trace.php' => ['s'=>2731,'h'=>'a2ca35689d6fe75c'],
    'views/ops/trace_thread.php' => ['s'=>4595,'h'=>'77a12293e74ad0d7'],
    'views/ops/two_factor.php' => ['s'=>7474,'h'=>'82d9491898763fa0'],
    'views/ops/user_form.php' => ['s'=>50744,'h'=>'21fec74fb0c46ca0'],
    'views/ops/users.php' => ['s'=>10885,'h'=>'b1f6e6fc1301c379'],
    'views/ops/vendor.php' => ['s'=>5070,'h'=>'bb8cb8c9dd7e8a3d'],
    'views/ops/vendor_users.php' => ['s'=>9001,'h'=>'6d0f1a0b7f9dd601'],
    'views/ops/verify.php' => ['s'=>8818,'h'=>'985a249a4ed58388'],
    'views/ops/voucher_approvals.php' => ['s'=>11973,'h'=>'d81c15c73fff3be3'],
    'views/ops/voucher_day.php' => ['s'=>7056,'h'=>'179330e3c0b2da0a'],
    'views/ops/voucher_detail.php' => ['s'=>25070,'h'=>'f2d834b9740ca3ce'],
    'views/ops/voucher_grid.php' => ['s'=>17991,'h'=>'511d0cd381022875'],
    'views/ops/voucher_list.php' => ['s'=>6208,'h'=>'be65cab4f133bad4'],
    'views/ops/voucher_print.php' => ['s'=>5842,'h'=>'74aca09b371630e0'],
    'views/ops/voucher_rules.php' => ['s'=>18980,'h'=>'e45e6c348e5064af'],
    'views/ops/welcome.php' => ['s'=>2992,'h'=>'9b2e2982b431759f'],
    'views/ops/work_norms.php' => ['s'=>3268,'h'=>'701f003f7ea5c766'],
    'views/partner_contract_new.php' => ['s'=>8784,'h'=>'25622c9ebacc2821'],
    'views/po_detail.php' => ['s'=>11492,'h'=>'9a115f0e658502a1'],
    'views/portal/accept.php' => ['s'=>1611,'h'=>'f3da5a665bc10077'],
    'views/portal/alerts.php' => ['s'=>1519,'h'=>'77aa6e37d664f4cc'],
    'views/portal/assistant.php' => ['s'=>2284,'h'=>'59037917099f7b3f'],
    'views/portal/bench.php' => ['s'=>8919,'h'=>'df179b07221b52e4'],
    'views/portal/bottom.php' => ['s'=>260,'h'=>'38c0e9ceb9238454'],
    'views/portal/call.php' => ['s'=>2374,'h'=>'2c0976ee3765839a'],
    'views/portal/calls.php' => ['s'=>1504,'h'=>'5bd2edcba3454040'],
    'views/portal/complaint_new.php' => ['s'=>3801,'h'=>'8f86b9a4ab5aab24'],
    'views/portal/complaints.php' => ['s'=>1893,'h'=>'c898dd644d85604a'],
    'views/portal/dashboard.php' => ['s'=>5490,'h'=>'f5be16ee1e25206c'],
    'views/portal/deputations.php' => ['s'=>5844,'h'=>'13f292b6a21029a4'],
    'views/portal/find.php' => ['s'=>12418,'h'=>'75a5a6aab6e7e126'],
    'views/portal/forgot.php' => ['s'=>1316,'h'=>'443297c5fc6bccfe'],
    'views/portal/hire.php' => ['s'=>23381,'h'=>'a434e3e40918d94a'],
    'views/portal/hire_req.php' => ['s'=>24260,'h'=>'7323f6a59a69a66b'],
    'views/portal/hiring.php' => ['s'=>8820,'h'=>'a5e62aaa7890c61a'],
    'views/portal/invoices.php' => ['s'=>2486,'h'=>'ff5b9b4f07b9fb21'],
    'views/portal/issue.php' => ['s'=>4390,'h'=>'8306150ab4c48390'],
    'views/portal/issues.php' => ['s'=>1308,'h'=>'6c16b1e94a076203'],
    'views/portal/login.php' => ['s'=>2361,'h'=>'b33a620fbbd48137'],
    'views/portal/message.php' => ['s'=>209,'h'=>'d6b5626385072953'],
    'views/portal/notfound.php' => ['s'=>364,'h'=>'98ea4b4496765970'],
    'views/portal/off.php' => ['s'=>798,'h'=>'f5b94195c2dc1c07'],
    'views/portal/password.php' => ['s'=>1136,'h'=>'19a6e5bbcaa9da54'],
    'views/portal/plans.php' => ['s'=>7458,'h'=>'4532c76c82216a72'],
    'views/portal/report_decide.php' => ['s'=>4143,'h'=>'08fc7918f15b1617'],
    'views/portal/reports.php' => ['s'=>2325,'h'=>'dbf1c37a223c3edb'],
    'views/portal/reputation.php' => ['s'=>4481,'h'=>'9357592fb58d2ffa'],
    'views/portal/request.php' => ['s'=>3081,'h'=>'d3ce954259e2e396'],
    'views/portal/reset.php' => ['s'=>1002,'h'=>'08984bb87b15598f'],
    'views/portal/roster.php' => ['s'=>9496,'h'=>'91b83a4530186d20'],
    'views/portal/talent.php' => ['s'=>10080,'h'=>'1dec998e9609120c'],
    'views/portal/team.php' => ['s'=>3405,'h'=>'bccdc3129af75ef9'],
    'views/portal/top.php' => ['s'=>6947,'h'=>'8405e058e87d436a'],
    'views/portal/voucher.php' => ['s'=>13237,'h'=>'5bc11678cdd34161'],
    'views/pro/applications.php' => ['s'=>2208,'h'=>'7c4febd8703426aa'],
    'views/pro/bookings.php' => ['s'=>3682,'h'=>'b07e3a9df97cfee4'],
    'views/pro/bottom.php' => ['s'=>22,'h'=>'c3d3f7a894cb6988'],
    'views/pro/credentials.php' => ['s'=>10113,'h'=>'7c0c7dbba0aa00bf'],
    'views/pro/cv.php' => ['s'=>3712,'h'=>'52cbb3c6be756837'],
    'views/pro/dashboard.php' => ['s'=>6226,'h'=>'41daa4da95fd9f0c'],
    'views/pro/documents.php' => ['s'=>2815,'h'=>'dbbbf382f29ff5bd'],
    'views/pro/forgot.php' => ['s'=>913,'h'=>'ede3db2555c64c3c'],
    'views/pro/jobs.php' => ['s'=>5181,'h'=>'f063d8ae279ae2fc'],
    'views/pro/login.php' => ['s'=>949,'h'=>'d8336c5e1def98ad'],
    'views/pro/message.php' => ['s'=>242,'h'=>'c7e07209f778aa1a'],
    'views/pro/messages.php' => ['s'=>4605,'h'=>'709ca4e9a174af0d'],
    'views/pro/plans.php' => ['s'=>7703,'h'=>'8b73442f416cd885'],
    'views/pro/privacy.php' => ['s'=>7620,'h'=>'f7e936c5cbb53131'],
    'views/pro/profile.php' => ['s'=>22747,'h'=>'a52bd223936d409a'],
    'views/pro/register.php' => ['s'=>1020,'h'=>'e25cf621a69b4be5'],
    'views/pro/reputation.php' => ['s'=>4006,'h'=>'9c78285a2f556949'],
    'views/pro/reset.php' => ['s'=>1350,'h'=>'943280f38cce9a67'],
    'views/pro/top.php' => ['s'=>5750,'h'=>'46d992395727c33d'],
    'views/pro/verify.php' => ['s'=>5152,'h'=>'834ecb35a53d27cb'],
    'views/pro/voucher.php' => ['s'=>17691,'h'=>'75ff8fc103830540'],
    'views/pro/vouchers.php' => ['s'=>4673,'h'=>'873ec56849934973'],
    'views/public/get_started.php' => ['s'=>9057,'h'=>'b1541ab2f85f7bcc'],
    'views/public/start.php' => ['s'=>8951,'h'=>'adef493a3d67ebd2'],
    'views/reset_password.php' => ['s'=>3677,'h'=>'bbbee0a4f6e37827'],
    'views/vendor/accept.php' => ['s'=>1615,'h'=>'1f104d773e83c106'],
    'views/vendor/alerts.php' => ['s'=>1519,'h'=>'e3e2bb09680b5ee1'],
    'views/vendor/assistant.php' => ['s'=>2174,'h'=>'3e867d28da2320fb'],
    'views/vendor/bottom.php' => ['s'=>192,'h'=>'80ac2cd745fce93c'],
    'views/vendor/dashboard.php' => ['s'=>3322,'h'=>'145b9fb19730bf7b'],
    'views/vendor/issue.php' => ['s'=>4390,'h'=>'6a1959ffc19a1d1f'],
    'views/vendor/issues.php' => ['s'=>1444,'h'=>'3d7683ed3ffd614e'],
    'views/vendor/login.php' => ['s'=>1256,'h'=>'6be191ad653e0e0c'],
    'views/vendor/message.php' => ['s'=>213,'h'=>'66b856f83d70e0d3'],
    'views/vendor/notfound.php' => ['s'=>373,'h'=>'5fc61e9aebd3ab35'],
    'views/vendor/off.php' => ['s'=>738,'h'=>'bc8ac100a03fd84a'],
    'views/vendor/opportunities.php' => ['s'=>2668,'h'=>'d41c751eb44e16e7'],
    'views/vendor/password.php' => ['s'=>1140,'h'=>'0ce31e97ab05f47a'],
    'views/vendor/qualification.php' => ['s'=>3160,'h'=>'076c8ef2c6e314e4'],
    'views/vendor/reports.php' => ['s'=>1594,'h'=>'1930c8ce4c8b021e'],
    'views/vendor/top.php' => ['s'=>5171,'h'=>'6bf89cdcd47555e6'],
];

$IS_CLI = (PHP_SAPI === 'cli');

// ---- Authenticate against the application's existing login session ---------
$AUTH = null;
if (!$IS_CLI) {
    $httpsNow = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
             || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true,
                              'secure' => $httpsNow, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}
$UID = (int) ($_SESSION['uid'] ?? 0);
$_SESSION['saas_tenant'] = '';            // in memory only: resolve the CONTROL install
$_SERVER['HTTP_HOST']    = '';
$_SERVER['SERVER_NAME']  = $_SERVER['SERVER_NAME'] ?? 'localhost';

$CFG  = @require __DIR__ . '/config.php';
$WHY  = '';                     // why we could not confirm an administrator
if (!$IS_CLI) {
    if (!is_array($CFG))      $WHY = 'config';
    elseif ($UID <= 0)        $WHY = 'signin';
    else {
        try {
            $d = (array) ($CFG['db'] ?? []);
            $pdo = ($d['driver'] ?? '') === 'sqlite'
                ? new PDO('sqlite:' . $CFG['sqlite_path'])
                : new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'], [PDO::ATTR_TIMEOUT => 10]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $st = $pdo->prepare("SELECT id, username, is_superuser, is_active FROM users WHERE id = ?");
            $st->execute([$UID]);
            $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($u && (int) $u['is_superuser'] === 1 && (int) $u['is_active'] === 1) $AUTH = $u;
            else $WHY = 'notadmin';
        } catch (Throwable $e) { $WHY = 'db'; }
    }
}

// A bare 404 for every refusal was a mistake in a tool whose whole job is to
// tell the operator what is wrong: "page not found" then means BOTH "the file
// did not upload" and "you are not signed in", and those need opposite actions.
//
// So a visitor who is simply not signed in gets a short page saying so. It
// reveals only that the file exists — every file name, size and checksum stays
// behind the administrator check. Someone signed in who is NOT an
// administrator still gets a plain 404.
if (!$IS_CLI && !$AUTH) {
    if ($WHY === 'notadmin') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not Found\n";
        exit;
    }
    $msg = $WHY === 'signin'
        ? 'Sign in to EXAACT as an administrator in this same browser, then reload this page.'
        : ($WHY === 'config'
            ? 'This page could not read config.php. Check that config.php and config.local.php are both in the application folder.'
            : 'This page could not open the database. The settings in config.local.php may be wrong, or the database server may be down.');
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Deployment check</title>'
       . '<div style="max-width:560px;margin:0 auto;padding-block:48px;padding-left:16px;padding-right:16px;'
       . 'font:15px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a">'
       . '<h1 style="font-size:20px;margin:0 0 10px">Deployment check</h1>'
       . '<p style="margin:0 0 14px">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
       . '<p style="color:#64748b;font-size:13.5px;margin:0">The file is on the server and reachable &mdash; so if a '
       . '&ldquo;page not found&rdquo; sent you here, that part is already solved.</p></div>';
    exit;
}

// ---- Configuration health -------------------------------------------------
//
// Two files can hold this server's database credentials and only ONE of them is
// read. config.php is part of the application and is replaced by every upload;
// config.local.php is not sent by anyone and therefore survives. A sample file
// filled in by mistake is read by nothing at all, while still leaving a copy of
// the password in the folder.
//
// Nothing here ever prints a password. Values are compared by hash so two files
// can be reported as agreeing or differing without either being shown.
$loadArr = function ($rel) {
    $p = __DIR__ . '/' . $rel;
    if (!is_file($p)) return null;                       // not there
    $r = @include $p;
    return is_array($r) ? $r : false;                    // false = there but unreadable
};
$hasReal = function ($rel) {                             // still the shipped placeholders?
    $p = __DIR__ . '/' . $rel;
    if (!is_file($p)) return false;
    $txt = (string) @file_get_contents($p);
    return $txt !== '' && strpos($txt, 'your_db_name') === false && strpos($txt, 'your_db_password') === false;
};
$fp = fn($v) => $v === '' || $v === null ? '' : substr(hash('sha256', (string) $v), 0, 12);

$cfgLocal  = $loadArr('config.local.php');
$cfgSample = $loadArr('config.local.sample.php');
$liveDb    = (array) ($CFG['db'] ?? []);

$localReal  = is_array($cfgLocal)  && $hasReal('config.local.php');
$sampleReal = is_array($cfgSample) && $hasReal('config.local.sample.php');
$phpReal    = $hasReal('config.php');

// Which file actually supplied the credentials in use.
$source = 'config.php';
if ($localReal && (string) ($cfgLocal['db']['name'] ?? '') === (string) ($liveDb['name'] ?? '')) $source = 'config.local.php';

// The admin password is synced INTO the login on the next page load whenever it
// changes, so a mismatch between the two files is not cosmetic: it silently
// changes who can sign in.
$adminDiffers = $localReal && isset($cfgLocal['admin']['pass'])
             && $fp($cfgLocal['admin']['pass'] ?? '') !== $fp($CFG['admin']['pass'] ?? '');

$cfgRows = [
    ['config.php', is_file(__DIR__ . '/config.php'), $phpReal, true,
     'Part of the application. REPLACED by every upload.'],
    ['config.local.php', is_file(__DIR__ . '/config.local.php'), $localReal, true,
     'Never sent by anyone, so it survives every upload. This is where credentials belong.'],
    ['config.local.sample.php', is_file(__DIR__ . '/config.local.sample.php'), $sampleReal, false,
     'A blank form to copy. The application never reads it.'],
];

$cfgWarn = [];
if (!$localReal && $phpReal)
    $cfgWarn[] = ['bad', 'Your credentials live only in config.php, which every upload replaces. '
                       . 'One upload of that file takes the site down until you type them in again.'];
if ($sampleReal)
    $cfgWarn[] = ['bad', 'config.local.sample.php has real credentials in it, and the application never reads that file. '
                       . 'It is doing nothing except keeping a copy of your password in the folder.'];
if ($localReal && $phpReal)
    $cfgWarn[] = ['warn', 'Both config.php and config.local.php hold real credentials. config.local.php wins, '
                        . 'so config.php can safely be replaced with the clean copy from the code.']; 
if ($adminDiffers)
    $cfgWarn[] = ['warn', 'The administrator password in config.local.php differs from the one in use. '
                        . 'On the next page load the application will change the admin login to match config.local.php.']; 
if (!$cfgWarn && $localReal)
    $cfgWarn[] = ['ok', 'Credentials are in config.local.php only. Uploads cannot touch them.'];

// ---- Compare -------------------------------------------------------------
$ok = []; $stale = []; $missing = [];
foreach ($EXPECT as $rel => $want) {
    $path = __DIR__ . '/' . $rel;
    if (!is_file($path)) { $missing[] = ['f' => $rel, 'want' => $want['s']]; continue; }
    $got = ['s' => (int) filesize($path), 'h' => substr(hash_file('sha256', $path), 0, 16)];
    if ($got['h'] === $want['h']) { $ok[] = $rel; continue; }
    $stale[] = ['f' => $rel, 'want' => $want['s'], 'got' => $got['s'],
                'age' => (int) @filemtime($path)];
}
usort($stale, fn($a, $b) => strcmp($a['f'], $b['f']));
$total = count($EXPECT);
$bad   = count($stale) + count($missing);

// Group the problems by folder — "everything under views/ops is old" is the
// finding, and a flat list of forty files hides it.
$byDir = [];
foreach (array_merge($stale, $missing) as $r) {
    $dir = dirname($r['f']); $dir = $dir === '.' ? '(top level)' : $dir . '/';
    $byDir[$dir] = ($byDir[$dir] ?? 0) + 1;
}
arsort($byDir);

// The clock the application stamps its records with. Since 16 Nov 2026 (owner
// decision T1) the app sets India time itself, in lib/timezone.php; an older
// release set nothing and used whatever the server's PHP is set to. The line
// shows the clock the app uses, the server's own clock beside it, and whether
// the app's clock matches India time (QA audit V51.1, Reporting C18: the
// CERT-In six-hour clock and "when it was noticed" depend on it). The app's zone
// is READ from that file, not loaded, so this page never changes its own clock.
$__tz    = date_default_timezone_get();                       // the server's own PHP zone
$__srv   = new DateTime('now');
$__appTz = '';
$__tzSrc = is_file(__DIR__ . '/lib/timezone.php') ? (string) @file_get_contents(__DIR__ . '/lib/timezone.php') : '';
if ($__tzSrc !== '' && preg_match("/define\\('APP_TIMEZONE',\\s*'([A-Za-z_\\/+-]+)'\\)/", $__tzSrc, $__m)
    && in_array($__m[1], timezone_identifiers_list(), true)) $__appTz = $__m[1];
$__app   = new DateTime('now', new DateTimeZone($__appTz !== '' ? $__appTz : $__tz));
$__ist   = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$__gap   = $__ist->getOffset() - $__app->getOffset();
$CLOCK   = 'the app uses ' . ($__appTz === 'Asia/Kolkata' ? 'India time (Asia/Kolkata)' : ($__appTz !== '' ? $__appTz : 'the server clock (' . $__tz . ')'))
         . ': ' . $__app->format('j M Y, H:i') . ' · server clock ' . $__srv->format('j M Y, H:i') . ' (' . $__tz . ')'
         . ($__gap === 0 ? ' · same as India time'
                         : ' · India time ' . $__ist->format('j M Y, H:i') . ' · differs from India time by ' . floor(abs($__gap) / 3600) . ' h ' . ((abs($__gap) % 3600) / 60) . ' min');

if ($IS_CLI) {
    echo "EXAACT deployment check — release {$RELEASE}\n";
    echo "Time zone: {$CLOCK}\n";
    echo str_repeat('=', 70) . "\n";
    printf("%d of %d files match. %d stale, %d missing.\n", count($ok), $total, count($stale), count($missing));
    foreach ($byDir as $dir => $n) echo "  {$dir}  {$n} file(s) out of date\n";
    foreach ($stale as $r)   echo "  STALE   {$r['f']}  (server " . number_format($r['got']) . " B, release " . number_format($r['want']) . " B)\n";
    foreach ($missing as $r) echo "  MISSING {$r['f']}\n";
    exit($bad ? 1 : 0);
}

$kb = fn($b) => number_format($b / 1024, 2) . ' KB';
$h  = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deployment check</title>
<style>
  :root{--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--ok:#047857;--okbg:#ecfdf5;--bad:#b91c1c;--badbg:#fef2f2;--card:#fff;--bg:#f8fafc}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .wrap{max-width:920px;margin:0 auto;padding-block:28px;padding-left:16px;padding-right:16px}
  h1{font-size:22px;margin:0 0 4px;letter-spacing:-.01em}
  .rel{color:var(--mut);font-size:13px;margin:0 0 20px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px 20px;margin-bottom:16px}
  .verdict{display:flex;gap:12px;align-items:flex-start;border-radius:14px;padding:18px 20px;margin-bottom:16px}
  .v-ok{background:var(--okbg);border:1px solid #a7f3d0;color:var(--ok)}
  .v-bad{background:var(--badbg);border:1px solid #fca5a5;color:var(--bad)}
  .verdict b{display:block;font-size:17px;margin-bottom:2px}
  .big{font-size:26px;font-weight:700;letter-spacing:-.02em}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
  .stat{border:1px solid var(--line);border-radius:11px;padding:12px 14px;background:var(--card)}
  .stat span{display:block;color:var(--mut);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  table{width:100%;border-collapse:collapse;font-size:13.5px}
  th,td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);vertical-align:top}
  th{color:var(--mut);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  code{font:12.5px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all}
  .tag{display:inline-block;padding:1px 7px;border-radius:999px;font-size:11px;font-weight:700}
  .t-stale{background:#fef3c7;color:#92400e}.t-miss{background:var(--badbg);color:var(--bad)}
  .scroll{overflow-x:auto}
  .steps{margin:10px 0 0 18px;padding:0}.steps li{margin:5px 0}
  @media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--ink:#e2e8f0;--mut:#94a3b8;--line:#1e293b;--card:#0f172a;--bg:#020617;--okbg:#052e22;--badbg:#2c0b0b}}
  :root[data-theme="dark"]{--ink:#e2e8f0;--mut:#94a3b8;--line:#1e293b;--card:#0f172a;--bg:#020617;--okbg:#052e22;--badbg:#2c0b0b}
</style>
<div class="wrap">
  <h1>Deployment check</h1>
  <p class="rel">Release <code><?= $h($RELEASE) ?></code> · signed in as <?= $h($AUTH['username'] ?? '') ?></p>
  <p class="rel">Time zone: <?= $h($CLOCK) ?></p>

  <?php if (!$bad): ?>
    <div class="verdict v-ok"><span style="font-size:22px">&#10003;</span>
      <div><b>Every file on this server matches the release.</b>
        All <?= (int) $total ?> files are up to date. The upload landed correctly.</div></div>
  <?php else: ?>
    <div class="verdict v-bad"><span style="font-size:22px">&#9888;&#65039;</span>
      <div><b><?= (int) $bad ?> file<?= $bad === 1 ? '' : 's' ?> did not upload.</b>
        The application is still running older code for these. Upload them again &mdash; overwrite, do not delete first.</div></div>
  <?php endif; ?>

  <div class="grid" style="margin-bottom:16px">
    <div class="stat"><span>Up to date</span><div class="big" style="color:var(--ok)"><?= count($ok) ?></div></div>
    <div class="stat"><span>Stale</span><div class="big" style="color:<?= $stale ? 'var(--bad)' : 'inherit' ?>"><?= count($stale) ?></div></div>
    <div class="stat"><span>Missing</span><div class="big" style="color:<?= $missing ? 'var(--bad)' : 'inherit' ?>"><?= count($missing) ?></div></div>
    <div class="stat"><span>Checked</span><div class="big"><?= (int) $total ?></div></div>
  </div>

  <div class="card">
    <h2 style="font-size:15px;margin:0 0 4px">Where your database settings live</h2>
    <p style="color:var(--mut);font-size:13px;margin:0 0 12px">Passwords are never shown on this page.</p>
    <div class="scroll"><table>
      <tr><th>File</th><th>On the server</th><th>Has real details</th><th>Read by the app</th></tr>
      <?php foreach ($cfgRows as [$f, $exists, $real, $used, $note]): ?>
      <tr>
        <td><code><?= $h($f) ?></code><br><span style="color:var(--mut);font-size:12px"><?= $h($note) ?></span></td>
        <td><?= $exists ? 'yes' : '<span style="color:var(--mut)">no</span>' ?></td>
        <td><?= $real ? '<b>yes</b>' : '<span style="color:var(--mut)">no</span>' ?></td>
        <td><?= $used ? 'yes' : '<span style="color:var(--mut)">never</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <p style="font-size:13.5px;margin:12px 0 0">In use right now: database <code><?= $h($liveDb['name'] ?? '?') ?></code>
      as user <code><?= $h($liveDb['user'] ?? '?') ?></code>, taken from <code><?= $h($source) ?></code>.</p>
    <?php foreach ($cfgWarn as [$kind, $text]): ?>
      <div style="margin-top:10px;padding:11px 13px;border-radius:10px;font-size:13.5px;<?=
        $kind === 'bad'  ? 'border:1px solid #fca5a5;background:var(--badbg);color:var(--bad)' :
       ($kind === 'warn' ? 'border:1px solid #fcd34d;background:#fffbeb;color:#78350f'
                         : 'border:1px solid #a7f3d0;background:var(--okbg);color:var(--ok)') ?>">
        <?= $h($text) ?>
      </div>
    <?php endforeach; ?>
    <?php if ($sampleReal || (!$localReal && $phpReal)): ?>
      <h3 style="font-size:13.5px;margin:14px 0 4px">How to put this right &mdash; in your File Manager</h3>
      <ol class="steps" style="font-size:13.5px">
        <?php if ($sampleReal && !$localReal): ?>
          <li><b>Rename</b> <code>config.local.sample.php</code> to <code>config.local.php</code>. Check first that the
            database name, user and password in it are the ones in use above &mdash; if they are not, correct them before renaming.</li>
        <?php elseif ($sampleReal && $localReal): ?>
          <li><b>Replace</b> <code>config.local.sample.php</code> with the blank copy from the code, or delete it.
            <code>config.local.php</code> already holds your real settings, so this file is only a spare copy of your password.</li>
        <?php else: ?>
          <li><b>Copy</b> <code>config.local.sample.php</code>, rename the copy to <code>config.local.php</code>,
            and put your real database name, user and password in it.</li>
        <?php endif; ?>
        <li><b>Reload the site.</b> If it still works, the new file is being read.</li>
        <li><b>Then</b> upload the clean <code>config.php</code> from the code over the one on the server, so your password
          is in one place only. Do this <em>last</em>, and only after step&nbsp;2 worked.</li>
      </ol>
      <p style="color:var(--mut);font-size:12.5px;margin:8px 0 0">Keep the administrator user and password the same in both
        files while you do this. If they differ, the application resets the admin login to match on the next page load.</p>
    <?php endif; ?>
  </div>

  <?php if ($byDir): ?>
  <div class="card">
    <h2 style="font-size:15px;margin:0 0 10px">Which folders are out of date</h2>
    <div class="scroll"><table>
      <tr><th>Folder</th><th>Files to re-upload</th></tr>
      <?php foreach ($byDir as $dir => $n): ?>
        <tr><td><code><?= $h($dir) ?></code></td><td><?= (int) $n ?></td></tr>
      <?php endforeach; ?>
    </table></div>
    <p style="color:var(--mut);font-size:13px;margin:12px 0 0">Re-upload these folders whole, overwriting what is there. Do not delete anything first.</p>
  </div>
  <?php endif; ?>

  <?php if ($stale || $missing): ?>
  <div class="card">
    <h2 style="font-size:15px;margin:0 0 10px">Every file that needs re-uploading</h2>
    <div class="scroll"><table>
      <tr><th>File</th><th></th><th>On this server</th><th>In the release</th></tr>
      <?php foreach ($missing as $r): ?>
        <tr><td><code><?= $h($r['f']) ?></code></td><td><span class="tag t-miss">missing</span></td>
            <td style="color:var(--mut)">not there</td><td><?= $h($kb($r['want'])) ?></td></tr>
      <?php endforeach; ?>
      <?php foreach ($stale as $r): ?>
        <tr><td><code><?= $h($r['f']) ?></code></td><td><span class="tag t-stale">old</span></td>
            <td><?= $h($kb($r['got'])) ?><?= $r['age'] ? '<br><span style="color:var(--mut);font-size:12px">' . $h(date('j M Y, H:i', $r['age'])) . '</span>' : '' ?></td>
            <td><?= $h($kb($r['want'])) ?></td></tr>
      <?php endforeach; ?>
    </table></div>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2 style="font-size:15px;margin:0 0 6px">If a file keeps showing as old after you re-upload it</h2>
    <ol class="steps">
      <li>Check you uploaded it into the <b>same folder</b> it is listed under above &mdash; a file dropped at the top level instead of inside <code>lib/</code> or <code>views/ops/</code> will not be used.</li>
      <li>Restart PHP in your hosting panel (<b>Developer Tools &rarr; Restart PHP container</b> on mPanel). PHP can keep a compiled copy of the old file in memory until it is restarted.</li>
      <li>Then reload this page. It reads the files fresh every time.</li>
    </ol>
  </div>

  <p style="color:var(--mut);font-size:12.5px">This page reads files and nothing else. It opens no workspace, runs no migration and writes to no database. Delete it once the deployment is confirmed.</p>
</div>
