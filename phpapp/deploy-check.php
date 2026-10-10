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

$RELEASE = 'bd69ac3 · 2026-10-10 07:07 UTC · 682 files';
$EXPECT  = [
    'api.php' => ['s'=>2282,'h'=>'1b2ca28973254f89'],
    'config.local.sample.php' => ['s'=>2228,'h'=>'0813d712b25666dd'],
    'cron.php' => ['s'=>23812,'h'=>'9a49ea3b6161e98c'],
    'cron_ads.php' => ['s'=>5306,'h'=>'4aad92eab174ea01'],
    'diagnose.php' => ['s'=>10005,'h'=>'791b255b31782ab7'],
    'index.php' => ['s'=>123644,'h'=>'3b4a3f0ee0deb5b1'],
    'lib/access.php' => ['s'=>108906,'h'=>'66fd957255c52864'],
    'lib/access_state.php' => ['s'=>9063,'h'=>'aeed1346f40b86bc'],
    'lib/activity.php' => ['s'=>45254,'h'=>'adc0ae347fc9f0b2'],
    'lib/adspro.php' => ['s'=>27606,'h'=>'f2df3f7d9b3f3fbc'],
    'lib/adsroi.php' => ['s'=>12887,'h'=>'2418d515c52e3905'],
    'lib/adssync.php' => ['s'=>28174,'h'=>'325142a88491bfba'],
    'lib/advisor.php' => ['s'=>42525,'h'=>'1de07288d04eb86b'],
    'lib/agreement.php' => ['s'=>30404,'h'=>'850a0f08f100b313'],
    'lib/ai.php' => ['s'=>26123,'h'=>'9a1b149e7eb200b0'],
    'lib/ai_formgen.php' => ['s'=>11931,'h'=>'3193de5d4fc90414'],
    'lib/approvals_hub.php' => ['s'=>4789,'h'=>'e8b2f6a01ef230b0'],
    'lib/areas.php' => ['s'=>39126,'h'=>'2ecdcce0151ff4b9'],
    'lib/assets.php' => ['s'=>14415,'h'=>'019c1f61103da27d'],
    'lib/attend.php' => ['s'=>13919,'h'=>'695a4eae1fd9e2a9'],
    'lib/attendreview.php' => ['s'=>7794,'h'=>'cfcd8f928e63efad'],
    'lib/audits.php' => ['s'=>41605,'h'=>'f362d70e80a1632d'],
    'lib/backup.php' => ['s'=>18340,'h'=>'cd0bafb29a6b4a8e'],
    'lib/billable.php' => ['s'=>28937,'h'=>'912d22dd3639fc99'],
    'lib/billing.php' => ['s'=>14639,'h'=>'f1395bd56bae517b'],
    'lib/bills.php' => ['s'=>11324,'h'=>'498117c26906b1a3'],
    'lib/books.php' => ['s'=>62065,'h'=>'00aa2b6708be8169'],
    'lib/booksbridge.php' => ['s'=>25742,'h'=>'083a31fe1d565994'],
    'lib/booksui.php' => ['s'=>24679,'h'=>'1a45924f453699a8'],
    'lib/bulk.php' => ['s'=>2157,'h'=>'ca3be826f9a4414c'],
    'lib/callprofit.php' => ['s'=>5760,'h'=>'7f81b6e7a9d7835f'],
    'lib/candpool.php' => ['s'=>13127,'h'=>'ee1d58a962510a9a'],
    'lib/candreview.php' => ['s'=>59806,'h'=>'2db34cbe1e26423f'],
    'lib/capa.php' => ['s'=>34318,'h'=>'cf9edf290b106bfd'],
    'lib/careers.php' => ['s'=>22969,'h'=>'70a9ec1b3acf87e3'],
    'lib/chain.php' => ['s'=>27590,'h'=>'38344fcbe18d90b5'],
    'lib/comp_config.php' => ['s'=>15360,'h'=>'5864ccfc2501116d'],
    'lib/company.php' => ['s'=>7536,'h'=>'cf72adf65cfa6d50'],
    'lib/competence.php' => ['s'=>54296,'h'=>'0761c88493b8c827'],
    'lib/complaints.php' => ['s'=>35427,'h'=>'3946b876dd98817e'],
    'lib/compliance.php' => ['s'=>63934,'h'=>'1929c53295fa2343'],
    'lib/compose.php' => ['s'=>6280,'h'=>'ba51539fdee0ae7f'],
    'lib/confidentiality.php' => ['s'=>22526,'h'=>'e97b3810463cfdb8'],
    'lib/connect_advisor.php' => ['s'=>4420,'h'=>'f39961034078a66a'],
    'lib/connect_analytics.php' => ['s'=>10322,'h'=>'55ce5e2749ea0bda'],
    'lib/connect_bench.php' => ['s'=>19261,'h'=>'452adce3bb2a9900'],
    'lib/connect_bridge.php' => ['s'=>8142,'h'=>'0cb82ce64f386984'],
    'lib/connect_capability.php' => ['s'=>16610,'h'=>'84e2c581bc36e29c'],
    'lib/connect_channels.php' => ['s'=>16526,'h'=>'41d69896e83b8919'],
    'lib/connect_client_bench.php' => ['s'=>10478,'h'=>'793a2fd9e8dc13e3'],
    'lib/connect_client_dash.php' => ['s'=>9295,'h'=>'c35365dd188acd9a'],
    'lib/connect_client_search.php' => ['s'=>6194,'h'=>'b09091878f09d826'],
    'lib/connect_concierge.php' => ['s'=>4889,'h'=>'aa35763e70e2df44'],
    'lib/connect_conflict.php' => ['s'=>13358,'h'=>'62ea412e04c515b6'],
    'lib/connect_credentials.php' => ['s'=>13244,'h'=>'e8e5b8106c5158c7'],
    'lib/connect_crew.php' => ['s'=>3256,'h'=>'329e3e96bfa354cd'],
    'lib/connect_cv.php' => ['s'=>6178,'h'=>'6e5123e99d862fa0'],
    'lib/connect_deploy.php' => ['s'=>8299,'h'=>'d3d261a16ddc833c'],
    'lib/connect_disputes.php' => ['s'=>4865,'h'=>'56ecbec1a7a03f91'],
    'lib/connect_engage.php' => ['s'=>20986,'h'=>'59720837c5273d27'],
    'lib/connect_engvoucher.php' => ['s'=>31752,'h'=>'24272b8bfe5ea6a6'],
    'lib/connect_geo.php' => ['s'=>15539,'h'=>'5a1f51ac70ec64d2'],
    'lib/connect_govern.php' => ['s'=>7181,'h'=>'22ce5ad54e44ce2a'],
    'lib/connect_hiring.php' => ['s'=>5373,'h'=>'f92a4ba2f9c8ccc7'],
    'lib/connect_identity.php' => ['s'=>58186,'h'=>'6500583342a99ab1'],
    'lib/connect_kpi.php' => ['s'=>25816,'h'=>'6822491cfb7498be'],
    'lib/connect_market.php' => ['s'=>39876,'h'=>'0fd3332b96bb74f5'],
    'lib/connect_match.php' => ['s'=>29253,'h'=>'6a3ceb740240e242'],
    'lib/connect_msg.php' => ['s'=>16414,'h'=>'37fd232a1fba99cc'],
    'lib/connect_org.php' => ['s'=>37940,'h'=>'354fbcf0a236e3eb'],
    'lib/connect_passport.php' => ['s'=>11025,'h'=>'651b739f219f52b4'],
    'lib/connect_person.php' => ['s'=>7701,'h'=>'7210f0cae01d84e9'],
    'lib/connect_privacy.php' => ['s'=>17610,'h'=>'74640b7aef0d480c'],
    'lib/connect_pro.php' => ['s'=>62286,'h'=>'e807b7400ce28688'],
    'lib/connect_qualtax.php' => ['s'=>24846,'h'=>'ff8da4316ac1a9d6'],
    'lib/connect_rating_disputes.php' => ['s'=>8981,'h'=>'0152a5d87d7e6917'],
    'lib/connect_ratings.php' => ['s'=>7478,'h'=>'cf59bcdbfc71b896'],
    'lib/connect_reqtools.php' => ['s'=>8395,'h'=>'7bff01ce54e08ac9'],
    'lib/connect_source.php' => ['s'=>8866,'h'=>'dfeb00f12cffb066'],
    'lib/connect_tax_graph.php' => ['s'=>34791,'h'=>'d2bc5c930179f0f6'],
    'lib/connect_taxonomy.php' => ['s'=>9836,'h'=>'3757c0d0d7daeb05'],
    'lib/connect_trust.php' => ['s'=>7228,'h'=>'27ac2d7be0dd9e28'],
    'lib/connect_verify.php' => ['s'=>18988,'h'=>'55e8aee1eccae351'],
    'lib/contracts.php' => ['s'=>99023,'h'=>'c71027c2ca373528'],
    'lib/controldocs.php' => ['s'=>15272,'h'=>'e99fcf054f6e6b1e'],
    'lib/costing.php' => ['s'=>60974,'h'=>'a8ecf706366db1cf'],
    'lib/costrecon.php' => ['s'=>11258,'h'=>'a6af5a16164f3458'],
    'lib/cpanel.php' => ['s'=>8181,'h'=>'9322634c72aed841'],
    'lib/crm.php' => ['s'=>237768,'h'=>'bbfaba6630e78614'],
    'lib/crmdash.php' => ['s'=>14139,'h'=>'01c4f1181a8fcf4d'],
    'lib/customer360.php' => ['s'=>24205,'h'=>'04ed75b284858be6'],
    'lib/customforms.php' => ['s'=>12896,'h'=>'a60a82554280a959'],
    'lib/cvp.php' => ['s'=>61127,'h'=>'d51dac70fda3b174'],
    'lib/datacontrol.php' => ['s'=>37308,'h'=>'be66a03dcb445dac'],
    'lib/datatable.php' => ['s'=>15699,'h'=>'dfea30bbc69ccef3'],
    'lib/db.php' => ['s'=>58746,'h'=>'72427b242481ba0b'],
    'lib/decisionrules.php' => ['s'=>12116,'h'=>'7f7d5a9cd6e144ef'],
    'lib/dedupe.php' => ['s'=>12027,'h'=>'de5bdc1c0769610b'],
    'lib/deptorg.php' => ['s'=>35388,'h'=>'6517b0f59b23fe0e'],
    'lib/disclosure.php' => ['s'=>6445,'h'=>'b8a28d13d9d9c1ce'],
    'lib/doc_templates.php' => ['s'=>21517,'h'=>'3e47c3f72458f0a0'],
    'lib/engagement.php' => ['s'=>13585,'h'=>'ff1a5552554877f4'],
    'lib/entitlement_migrate.php' => ['s'=>10281,'h'=>'bfd1dc34dec3e38b'],
    'lib/entity360.php' => ['s'=>4909,'h'=>'3d405b448a70e1e0'],
    'lib/equipment.php' => ['s'=>24106,'h'=>'266e71060ecba007'],
    'lib/finevent.php' => ['s'=>8838,'h'=>'4ce2d3d330cd3231'],
    'lib/formdesign.php' => ['s'=>70278,'h'=>'82250b1cc55e3402'],
    'lib/geofence.php' => ['s'=>20775,'h'=>'0bda93308e9f8e10'],
    'lib/helpers.php' => ['s'=>25921,'h'=>'6d2898c6422db793'],
    'lib/hiringreq.php' => ['s'=>102909,'h'=>'d7dd93468b6072db'],
    'lib/hwpoints.php' => ['s'=>16950,'h'=>'3b298a9d122d31a6'],
    'lib/idems.php' => ['s'=>786654,'h'=>'ac696b399296a300'],
    'lib/idems_autoform.php' => ['s'=>9923,'h'=>'d4520363260f9c48'],
    'lib/identity.php' => ['s'=>44853,'h'=>'714f8f2be8e9f952'],
    'lib/impartiality.php' => ['s'=>18224,'h'=>'70a0c7b051724178'],
    'lib/indexes.php' => ['s'=>13279,'h'=>'92bb3debc6451b96'],
    'lib/industry.php' => ['s'=>30759,'h'=>'ddb3f3afbd4600db'],
    'lib/inspectorprofile.php' => ['s'=>3920,'h'=>'f995d0ac6cff433f'],
    'lib/install_mode.php' => ['s'=>4935,'h'=>'0c1d5ab96ff54b68'],
    'lib/invready.php' => ['s'=>6922,'h'=>'0f7637be388be1fc'],
    'lib/joblock.php' => ['s'=>13155,'h'=>'47c05d15e9e8e13b'],
    'lib/leads.php' => ['s'=>75176,'h'=>'19b13a485eb252b3'],
    'lib/licence.php' => ['s'=>31138,'h'=>'ae1f563479d4009c'],
    'lib/licenceissue.php' => ['s'=>28363,'h'=>'e750e57678199d20'],
    'lib/licencekey.php' => ['s'=>29242,'h'=>'c42b2078fe80352f'],
    'lib/licencesync.php' => ['s'=>10254,'h'=>'c17cfa585e721f62'],
    'lib/lookups.php' => ['s'=>79469,'h'=>'808505f814c0df0d'],
    'lib/methods.php' => ['s'=>13959,'h'=>'14969626618c15f8'],
    'lib/mghsso.php' => ['s'=>10002,'h'=>'80f043e73ff44d13'],
    'lib/mis.php' => ['s'=>26675,'h'=>'793805e19f98b98f'],
    'lib/mkt_billing.php' => ['s'=>8396,'h'=>'34d5dafe732275bb'],
    'lib/mkt_credits.php' => ['s'=>9152,'h'=>'a415feb6c7997cff'],
    'lib/mkt_escrow.php' => ['s'=>12953,'h'=>'38961c0e1478671e'],
    'lib/mkt_fees.php' => ['s'=>7830,'h'=>'d357fad88aebd4ae'],
    'lib/mkt_gates.php' => ['s'=>3563,'h'=>'33cc1d5744a2da96'],
    'lib/mkt_ledger.php' => ['s'=>10121,'h'=>'a26d0ec31a84af92'],
    'lib/mkt_pay.php' => ['s'=>11289,'h'=>'500559c4192f9fda'],
    'lib/mkt_plans.php' => ['s'=>10762,'h'=>'513801027f92ce90'],
    'lib/mkt_rules.php' => ['s'=>12129,'h'=>'f541702a11884133'],
    'lib/mkt_subs.php' => ['s'=>10123,'h'=>'7a5b146d8a81d7ce'],
    'lib/nav.php' => ['s'=>12573,'h'=>'cd985ebc1d1c0af4'],
    'lib/navindex.php' => ['s'=>12925,'h'=>'db8a2e8e2f960eb8'],
    'lib/ncdca.php' => ['s'=>44960,'h'=>'d64c2c2e6eddc5e1'],
    'lib/ncr.php' => ['s'=>34005,'h'=>'c798485b2013d240'],
    'lib/nextaction.php' => ['s'=>15255,'h'=>'85f9797146341fa6'],
    'lib/numbering.php' => ['s'=>9214,'h'=>'a00fd93a0150d3bf'],
    'lib/onboarding.php' => ['s'=>3867,'h'=>'c9f9c319c1f15cd3'],
    'lib/opportunities.php' => ['s'=>66604,'h'=>'389f5a46c713f72e'],
    'lib/ops.php' => ['s'=>711089,'h'=>'8810965925a72e3b'],
    'lib/orgadmin.php' => ['s'=>77714,'h'=>'c66e5bb654e8e16d'],
    'lib/organogram.php' => ['s'=>20449,'h'=>'b2f7eb7b0c9d4c4f'],
    'lib/owner_home.php' => ['s'=>3582,'h'=>'a0831edf5e9652a3'],
    'lib/packs.php' => ['s'=>14404,'h'=>'2c631d79aea60e49'],
    'lib/partnerimport.php' => ['s'=>23742,'h'=>'bc507ebf2a770c8f'],
    'lib/party.php' => ['s'=>11622,'h'=>'55ad01fbf381e780'],
    'lib/pdf.php' => ['s'=>26663,'h'=>'356a367da31c40a6'],
    'lib/pdso.php' => ['s'=>62942,'h'=>'b8624fad908da790'],
    'lib/pipelines.php' => ['s'=>14596,'h'=>'8f62abd870e47a71'],
    'lib/portal.php' => ['s'=>114128,'h'=>'afcf984120ca04b3'],
    'lib/position.php' => ['s'=>19441,'h'=>'a0a9d1a9e6a9b10c'],
    'lib/preflight.php' => ['s'=>5451,'h'=>'97e742bbddf7fbd8'],
    'lib/pricing_admin.php' => ['s'=>10272,'h'=>'7f36c21dab0d782d'],
    'lib/projcosting.php' => ['s'=>34494,'h'=>'3f2482fab1b534e8'],
    'lib/pwreset.php' => ['s'=>9509,'h'=>'50675e1d291df7b0'],
    'lib/qr.php' => ['s'=>17936,'h'=>'4d50b69b53bfa5e2'],
    'lib/qualitycase.php' => ['s'=>6179,'h'=>'280289c99f157cd7'],
    'lib/rating.php' => ['s'=>7766,'h'=>'f5bbd79552189e10'],
    'lib/receivables.php' => ['s'=>13029,'h'=>'bfbc04eeaf0fb181'],
    'lib/recruit.php' => ['s'=>138522,'h'=>'0d2186f83e7fa8ee'],
    'lib/recruit_approval.php' => ['s'=>171690,'h'=>'0fa9966f711c8227'],
    'lib/recruit_assign.php' => ['s'=>40788,'h'=>'bcdcf294d7fa64a5'],
    'lib/recruit_cc.php' => ['s'=>33057,'h'=>'bba9d9edaf43ee16'],
    'lib/recruit_exec.php' => ['s'=>24603,'h'=>'0546b6c003d3493c'],
    'lib/recruit_export.php' => ['s'=>7887,'h'=>'70fa492cdd1a8d35'],
    'lib/recruit_fulfil.php' => ['s'=>51701,'h'=>'3c914bbeb1483e3d'],
    'lib/recruit_iv.php' => ['s'=>38102,'h'=>'f42fdb14f4c786dc'],
    'lib/recruit_jd.php' => ['s'=>11577,'h'=>'fa60a38877273cec'],
    'lib/recruit_kpi.php' => ['s'=>44791,'h'=>'01e1256f752079c1'],
    'lib/recruit_offer.php' => ['s'=>50420,'h'=>'ca79a631b18f0fe2'],
    'lib/recruitpipe.php' => ['s'=>82698,'h'=>'508ab85becc96cf4'],
    'lib/reportreview.php' => ['s'=>23222,'h'=>'ae43c4d37883823d'],
    'lib/reqfulfil.php' => ['s'=>16474,'h'=>'aeda367b914bbe2e'],
    'lib/reqversion.php' => ['s'=>86517,'h'=>'af794e21b3565a11'],
    'lib/reset.php' => ['s'=>11477,'h'=>'8d961eb9724047b9'],
    'lib/retention.php' => ['s'=>6587,'h'=>'deb6a6d4a270fa36'],
    'lib/revrecon.php' => ['s'=>13441,'h'=>'eb8777d1398cdada'],
    'lib/risks.php' => ['s'=>10571,'h'=>'83a96e16878654a2'],
    'lib/saas_provision_cli.php' => ['s'=>4816,'h'=>'76f04fae8c24f082'],
    'lib/saas_sync_cli.php' => ['s'=>2939,'h'=>'5fb733d865f2f34b'],
    'lib/saas_tenants.php' => ['s'=>83099,'h'=>'02111510016c2a4f'],
    'lib/samples.php' => ['s'=>14677,'h'=>'fa38e5f628b43f6a'],
    'lib/satisfaction.php' => ['s'=>15957,'h'=>'14bfd7fb7267d70f'],
    'lib/schedboard.php' => ['s'=>10397,'h'=>'0b2b9fe333604ef3'],
    'lib/schedule.php' => ['s'=>35990,'h'=>'81b963c982b619c8'],
    'lib/search.php' => ['s'=>34331,'h'=>'3358f1a7be1c8b45'],
    'lib/security.php' => ['s'=>38300,'h'=>'59c69d7c557f1016'],
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
    'lib/services.php' => ['s'=>28431,'h'=>'6fc99ee80df3ff34'],
    'lib/settingmeta.php' => ['s'=>12701,'h'=>'092392a858cc10d8'],
    'lib/settlement.php' => ['s'=>5371,'h'=>'0b75fbf859930e58'],
    'lib/setup.php' => ['s'=>22705,'h'=>'43cbca244da74864'],
    'lib/setup_cockpit.php' => ['s'=>31439,'h'=>'3bf43713e1b2b0e3'],
    'lib/stagegate.php' => ['s'=>22623,'h'=>'699772501f2dbdbb'],
    'lib/superadmin.php' => ['s'=>10675,'h'=>'995da0045cd4e7ee'],
    'lib/tally.php' => ['s'=>43582,'h'=>'b555bf1d1298c5bb'],
    'lib/tapi.php' => ['s'=>68071,'h'=>'558f4e8113367330'],
    'lib/tapi_dash.php' => ['s'=>19519,'h'=>'efd1e7a78a470736'],
    'lib/tapi_gov.php' => ['s'=>17290,'h'=>'111e443d157ce2ce'],
    'lib/tapi_score.php' => ['s'=>14322,'h'=>'e2256ab85fab255c'],
    'lib/tasks.php' => ['s'=>11239,'h'=>'82c11af4ebccd03c'],
    'lib/tenant_migrate.php' => ['s'=>19927,'h'=>'0b63124682d54a07'],
    'lib/tenant_signup.php' => ['s'=>11305,'h'=>'dfa7791f625c93fc'],
    'lib/tenants.php' => ['s'=>22348,'h'=>'7bba6e22de34aa8a'],
    'lib/terms.php' => ['s'=>28589,'h'=>'6aa8716a9d4cae12'],
    'lib/timesheet.php' => ['s'=>9615,'h'=>'0850f4b87638e4a5'],
    'lib/tmplpreview.php' => ['s'=>3520,'h'=>'770170cf566d21da'],
    'lib/tosrm.php' => ['s'=>175958,'h'=>'97546ac06cc88324'],
    'lib/trace_audit.php' => ['s'=>17883,'h'=>'b7fce5750cd3b3df'],
    'lib/trace_seed.php' => ['s'=>26992,'h'=>'77cf9de62dacbf11'],
    'lib/trust.php' => ['s'=>55443,'h'=>'612b7b84607188ff'],
    'lib/uire.php' => ['s'=>41234,'h'=>'39afd823034db00b'],
    'lib/urade.php' => ['s'=>35221,'h'=>'1284a8d26a3403e4'],
    'lib/urfe.php' => ['s'=>24902,'h'=>'ddec890bdd7b6775'],
    'lib/uvaae.php' => ['s'=>49896,'h'=>'df442e4432ef0dbc'],
    'lib/uvae.php' => ['s'=>40814,'h'=>'6c1b059e106f1a0d'],
    'lib/vendor360.php' => ['s'=>3923,'h'=>'107a364d8222c144'],
    'lib/visibility.php' => ['s'=>4843,'h'=>'281d50af3a4d26fa'],
    'lib/vocab.php' => ['s'=>19053,'h'=>'a9a1c5c84a2f8d0b'],
    'lib/webhookq.php' => ['s'=>7844,'h'=>'10e0dde19b283fdf'],
    'lib/workforce.php' => ['s'=>58084,'h'=>'4ab7d01681a0d870'],
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
    'views/dashboard.php' => ['s'=>49286,'h'=>'29ae0ed9d6928512'],
    'views/detail.php' => ['s'=>35729,'h'=>'0619372afa855405'],
    'views/forgot_password.php' => ['s'=>2716,'h'=>'c041f8176182b36c'],
    'views/form.php' => ['s'=>24206,'h'=>'959912d692fa235f'],
    'views/layout_bottom.php' => ['s'=>880,'h'=>'e6b33e19b84eb59f'],
    'views/layout_embed_bottom.php' => ['s'=>65,'h'=>'63f4c118c80caf4b'],
    'views/layout_embed_top.php' => ['s'=>730,'h'=>'b20991bedef34be0'],
    'views/layout_top.php' => ['s'=>32999,'h'=>'a125779001d5bb82'],
    'views/list.php' => ['s'=>3275,'h'=>'b234af3a9590aae2'],
    'views/login.php' => ['s'=>602,'h'=>'ff266c37dfd19bb0'],
    'views/login_page.php' => ['s'=>10767,'h'=>'c18991133beffa78'],
    'views/notfound.php' => ['s'=>212,'h'=>'0b4feab07b715eec'],
    'views/ops/_allocation_panel.php' => ['s'=>9018,'h'=>'76426e99a74cf039'],
    'views/ops/_deputation_panel.php' => ['s'=>13672,'h'=>'46545d4243390803'],
    'views/ops/_glance_strip.php' => ['s'=>5257,'h'=>'ffa16f7b9f2ab7b2'],
    'views/ops/_issue_panel.php' => ['s'=>8621,'h'=>'8f7e08e2edf793d4'],
    'views/ops/_ops_registers.php' => ['s'=>6510,'h'=>'403bb12d7870ae8e'],
    'views/ops/_perm_verb_grid.php' => ['s'=>4352,'h'=>'d68d0e601820a876'],
    'views/ops/_perm_verb_grid_assets.php' => ['s'=>5401,'h'=>'9d11458fdc14e39e'],
    'views/ops/_subscription_builder.php' => ['s'=>3772,'h'=>'05ed06dcfe749785'],
    'views/ops/access.php' => ['s'=>9240,'h'=>'faa8963e6437c387'],
    'views/ops/access_notice.php' => ['s'=>2060,'h'=>'b25acc590f21d59a'],
    'views/ops/activities.php' => ['s'=>3751,'h'=>'22620c2f6225c534'],
    'views/ops/adspro.php' => ['s'=>13351,'h'=>'ab9092f4fbe2cbbc'],
    'views/ops/adsroi.php' => ['s'=>8605,'h'=>'3f2b37ed7cabdfd2'],
    'views/ops/advisor.php' => ['s'=>9193,'h'=>'592527ab30f1be7d'],
    'views/ops/agency_staff.php' => ['s'=>3970,'h'=>'abaaf8e4f95477fc'],
    'views/ops/agreement.php' => ['s'=>1958,'h'=>'692efd73750ecab3'],
    'views/ops/ai_forms.php' => ['s'=>6653,'h'=>'f278678379d12723'],
    'views/ops/ai_settings.php' => ['s'=>3916,'h'=>'ac3a734628f292f8'],
    'views/ops/ai_topup_pay.php' => ['s'=>2408,'h'=>'a6c85b5a75f2bc3e'],
    'views/ops/approval_delegations.php' => ['s'=>6151,'h'=>'dbb7ef0112381a62'],
    'views/ops/approval_rules.php' => ['s'=>23629,'h'=>'25c7ff05b5caa481'],
    'views/ops/approvals.php' => ['s'=>9059,'h'=>'10797e07fd17f681'],
    'views/ops/area_home.php' => ['s'=>4611,'h'=>'718227061d3895e3'],
    'views/ops/asset_register.php' => ['s'=>12566,'h'=>'5973afebeb7207d5'],
    'views/ops/attendance_recon.php' => ['s'=>3064,'h'=>'d7aa936250745627'],
    'views/ops/attendance_review.php' => ['s'=>2801,'h'=>'510ddd176e0a7d48'],
    'views/ops/audit_detail.php' => ['s'=>6317,'h'=>'12f351cdcc6a464b'],
    'views/ops/audit_form.php' => ['s'=>2331,'h'=>'a9a442d6ca2ccadb'],
    'views/ops/audits_list.php' => ['s'=>4123,'h'=>'be8889ef0738b867'],
    'views/ops/availability.php' => ['s'=>16445,'h'=>'37b02b2d2d47be55'],
    'views/ops/backup.php' => ['s'=>5050,'h'=>'fee58886e8fab69a'],
    'views/ops/billable_events.php' => ['s'=>8005,'h'=>'8bced33ab4bff106'],
    'views/ops/billing.php' => ['s'=>8709,'h'=>'2308aaa2d21e75bd'],
    'views/ops/billing_pay.php' => ['s'=>3306,'h'=>'68bea9d75e2df591'],
    'views/ops/books_bridge.php' => ['s'=>6061,'h'=>'9d89c6e1a4160652'],
    'views/ops/call_detail.php' => ['s'=>28235,'h'=>'0e139ddb0e2b7556'],
    'views/ops/call_form.php' => ['s'=>68264,'h'=>'157ad0c573f2f7fc'],
    'views/ops/call_profit.php' => ['s'=>10745,'h'=>'c94f52543758b527'],
    'views/ops/calls.php' => ['s'=>11689,'h'=>'d7865a3523e372f3'],
    'views/ops/candidate_detail.php' => ['s'=>54303,'h'=>'ca9992d36850b771'],
    'views/ops/candidate_form.php' => ['s'=>22976,'h'=>'5ee8fe3d830f65bb'],
    'views/ops/candidate_list.php' => ['s'=>2664,'h'=>'c0eca4a60f7dff4c'],
    'views/ops/candidate_pool.php' => ['s'=>4341,'h'=>'8e83a233d98c8708'],
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
    'views/ops/client_holds.php' => ['s'=>2760,'h'=>'f4a905d7edc11228'],
    'views/ops/cockpit_forms.php' => ['s'=>1956,'h'=>'c70846e6e910e1ba'],
    'views/ops/cockpit_home.php' => ['s'=>6795,'h'=>'4f8fac9a8e081de4'],
    'views/ops/cockpit_modules.php' => ['s'=>5336,'h'=>'48ae3bd61dfc3a84'],
    'views/ops/cockpit_profile.php' => ['s'=>3536,'h'=>'2e86d3bf2bd4f402'],
    'views/ops/command_centre.php' => ['s'=>5165,'h'=>'ae264a9f22a82fd4'],
    'views/ops/comp_setup.php' => ['s'=>7385,'h'=>'7821208806df4fb8'],
    'views/ops/company.php' => ['s'=>7290,'h'=>'61f2692ced1c6268'],
    'views/ops/competence.php' => ['s'=>15651,'h'=>'ca44c656cf8b01a4'],
    'views/ops/complaint_detail.php' => ['s'=>16356,'h'=>'7525ed828500d074'],
    'views/ops/complaint_form.php' => ['s'=>5326,'h'=>'ec96aab76573bf95'],
    'views/ops/complaints.php' => ['s'=>6387,'h'=>'bcf319a1a8b814a1'],
    'views/ops/complaints_policy.php' => ['s'=>2574,'h'=>'2d77d01b6217f346'],
    'views/ops/compliance.php' => ['s'=>5550,'h'=>'c6645a47fb823737'],
    'views/ops/conf_breach.php' => ['s'=>4350,'h'=>'87f7764d73f8e733'],
    'views/ops/confidentiality.php' => ['s'=>10985,'h'=>'b776a94d995c5093'],
    'views/ops/connect_access_requests.php' => ['s'=>3559,'h'=>'d41a12901ce83a6c'],
    'views/ops/connect_analytics.php' => ['s'=>11115,'h'=>'3da138fb5593a68d'],
    'views/ops/connect_bench.php' => ['s'=>10696,'h'=>'fc8b82397ba6e462'],
    'views/ops/connect_capabilities.php' => ['s'=>5457,'h'=>'704f0c7a4dc3d083'],
    'views/ops/connect_channels.php' => ['s'=>8494,'h'=>'001b6515fa19bcf6'],
    'views/ops/connect_concierge.php' => ['s'=>8130,'h'=>'e26f086a653a3778'],
    'views/ops/connect_front.php' => ['s'=>10653,'h'=>'8ee62fca290044c4'],
    'views/ops/connect_identity.php' => ['s'=>5849,'h'=>'14b648589596fcad'],
    'views/ops/connect_join.php' => ['s'=>8819,'h'=>'90938cf57917e368'],
    'views/ops/connect_match_weights.php' => ['s'=>3856,'h'=>'14f2f37f5872848b'],
    'views/ops/connect_messages.php' => ['s'=>5959,'h'=>'3d2f79a081ab9566'],
    'views/ops/connect_orgs.php' => ['s'=>4763,'h'=>'52cc8cfafc0fd31c'],
    'views/ops/connect_passport_public.php' => ['s'=>6646,'h'=>'d3a171906f276469'],
    'views/ops/connect_passport_share.php' => ['s'=>3238,'h'=>'001e14194e7c30e0'],
    'views/ops/connect_qualifications.php' => ['s'=>17605,'h'=>'3e0dabe90148f43a'],
    'views/ops/connect_requirement.php' => ['s'=>47980,'h'=>'85feee0182528893'],
    'views/ops/connect_requirements.php' => ['s'=>8250,'h'=>'8af69297db529e13'],
    'views/ops/connect_source.php' => ['s'=>4918,'h'=>'a8882cf9e99ec24b'],
    'views/ops/connect_talent.php' => ['s'=>6988,'h'=>'381e26f8e01e875b'],
    'views/ops/connect_taxonomy.php' => ['s'=>6402,'h'=>'0357e7d5e840b119'],
    'views/ops/connect_taxonomy_admin.php' => ['s'=>11051,'h'=>'db0b340f99a00f89'],
    'views/ops/connect_verify.php' => ['s'=>4960,'h'=>'4645eaaf41e844ce'],
    'views/ops/consents.php' => ['s'=>3523,'h'=>'5dd8e9b08a104408'],
    'views/ops/contract_detail.php' => ['s'=>20165,'h'=>'2793d8a1a7cd1b0f'],
    'views/ops/contract_openings.php' => ['s'=>5102,'h'=>'98769400b4ef5772'],
    'views/ops/contract_overrides.php' => ['s'=>8886,'h'=>'e22fdf59af73f743'],
    'views/ops/cost_reconciliation.php' => ['s'=>5547,'h'=>'3c2d6f2dcd59c0a8'],
    'views/ops/cost_run.php' => ['s'=>11344,'h'=>'5bb43d8aae74a6b9'],
    'views/ops/crm/approval_rule_form.php' => ['s'=>3705,'h'=>'f65697a269bb5247'],
    'views/ops/crm/approval_rule_list.php' => ['s'=>2383,'h'=>'dc4a5dad9dad6d4b'],
    'views/ops/crm/inquiry_form.php' => ['s'=>4541,'h'=>'bd15d063e6debfc5'],
    'views/ops/crm/inquiry_list.php' => ['s'=>4641,'h'=>'5917461194b40e2e'],
    'views/ops/crm/quote_detail.php' => ['s'=>69276,'h'=>'ef601cf8a95464dc'],
    'views/ops/crm/quote_external.php' => ['s'=>8833,'h'=>'9214fa347b102a30'],
    'views/ops/crm/quote_form.php' => ['s'=>41786,'h'=>'f886e84b980e37fe'],
    'views/ops/crm/quote_list.php' => ['s'=>8038,'h'=>'36be301c9d7ffe37'],
    'views/ops/crm/reports.php' => ['s'=>3263,'h'=>'f9d0840a7994e767'],
    'views/ops/crm/template_form.php' => ['s'=>3958,'h'=>'3671a20d6c01c474'],
    'views/ops/crm/template_list.php' => ['s'=>5237,'h'=>'b674d99c8347e839'],
    'views/ops/crm_dashboard.php' => ['s'=>13276,'h'=>'6bdd49395543c605'],
    'views/ops/custom_fields.php' => ['s'=>4958,'h'=>'f2f969e3c56dfed4'],
    'views/ops/customer360.php' => ['s'=>29931,'h'=>'2197023e362e8e81'],
    'views/ops/data_control.php' => ['s'=>18379,'h'=>'108fa751e31a4523'],
    'views/ops/data_requests.php' => ['s'=>4866,'h'=>'840d2672933c1726'],
    'views/ops/dedupe.php' => ['s'=>7066,'h'=>'6ddfbed6d6b16b97'],
    'views/ops/department_admin.php' => ['s'=>12013,'h'=>'70b3d5969a1c2461'],
    'views/ops/departments.php' => ['s'=>5364,'h'=>'173353934339e7a3'],
    'views/ops/departures.php' => ['s'=>6072,'h'=>'e741ab44fd0e3992'],
    'views/ops/deputations.php' => ['s'=>9928,'h'=>'64f37c872f20d2ad'],
    'views/ops/disclosure.php' => ['s'=>4387,'h'=>'ed150ed2c62611a3'],
    'views/ops/doc_templates.php' => ['s'=>5459,'h'=>'63767e1c0cdfca15'],
    'views/ops/drule_detail.php' => ['s'=>2851,'h'=>'bcc2e35358281ff2'],
    'views/ops/drule_form.php' => ['s'=>3210,'h'=>'c78899841dbba75e'],
    'views/ops/drules_list.php' => ['s'=>1711,'h'=>'386819892e490314'],
    'views/ops/entity360.php' => ['s'=>701,'h'=>'15c95b885b7f2760'],
    'views/ops/equipment_form.php' => ['s'=>11381,'h'=>'aa04777211a8cdb5'],
    'views/ops/equipment_list.php' => ['s'=>2709,'h'=>'418d6eca782ea187'],
    'views/ops/evidence_review.php' => ['s'=>10250,'h'=>'0d5e95d804610f40'],
    'views/ops/flow_gaps.php' => ['s'=>3287,'h'=>'f2bdad6e4475e0b2'],
    'views/ops/form_designer.php' => ['s'=>19293,'h'=>'27b031e7b0dc2719'],
    'views/ops/hierarchy.php' => ['s'=>57939,'h'=>'802fb089e84bfc73'],
    'views/ops/hiring_request.php' => ['s'=>33082,'h'=>'1a13dfa59dd6271f'],
    'views/ops/hiring_request_list.php' => ['s'=>2937,'h'=>'73541f4428e82608'],
    'views/ops/hwpoints.php' => ['s'=>3338,'h'=>'679fdb6488397c4a'],
    'views/ops/idems/approval_rules.php' => ['s'=>6569,'h'=>'eda930521882e015'],
    'views/ops/idems/approver_map.php' => ['s'=>3018,'h'=>'2bd921aa6ec82308'],
    'views/ops/idems/audit.php' => ['s'=>10024,'h'=>'c7d338510ff71a12'],
    'views/ops/idems/autoform.php' => ['s'=>1843,'h'=>'21f7d3b796eadd9c'],
    'views/ops/idems/builder.php' => ['s'=>36007,'h'=>'ad9b1250d05c804b'],
    'views/ops/idems/doc_detail.php' => ['s'=>65119,'h'=>'f143d6f2be76562e'],
    'views/ops/idems/doc_form.php' => ['s'=>13090,'h'=>'217d09816175b5ea'],
    'views/ops/idems/endorse_detail.php' => ['s'=>6842,'h'=>'0d0bb9d2cac2c51b'],
    'views/ops/idems/endorse_form.php' => ['s'=>6191,'h'=>'ca143cb1e88e4080'],
    'views/ops/idems/endorse_list.php' => ['s'=>3343,'h'=>'9d8b7216f4077e43'],
    'views/ops/idems/evidence.php' => ['s'=>7251,'h'=>'e8071e9c2392edf5'],
    'views/ops/idems/expediting_projects.php' => ['s'=>7498,'h'=>'6194b0fe07cad0a0'],
    'views/ops/idems/expediting_register.php' => ['s'=>5576,'h'=>'f47d2a051e0c0f50'],
    'views/ops/idems/fill.php' => ['s'=>43762,'h'=>'1aef045ceb03289c'],
    'views/ops/idems/form_from_template.php' => ['s'=>5951,'h'=>'6d5564043e9f4610'],
    'views/ops/idems/learning.php' => ['s'=>5480,'h'=>'ca4883b1ed4a430f'],
    'views/ops/idems/my_signature.php' => ['s'=>2691,'h'=>'f3807693f90c0988'],
    'views/ops/idems/numbering.php' => ['s'=>3204,'h'=>'ef033baf9a5af1c3'],
    'views/ops/idems/phrases.php' => ['s'=>3989,'h'=>'16c22a8446ebaa5f'],
    'views/ops/idems/register.php' => ['s'=>4622,'h'=>'ef8f3c7dc46dbe30'],
    'views/ops/idems/release_register.php' => ['s'=>3461,'h'=>'7483d987b817b057'],
    'views/ops/idems/report_types.php' => ['s'=>9360,'h'=>'cb01bd8b56f0addf'],
    'views/ops/idems/review.php' => ['s'=>9367,'h'=>'504ff2d9d8ee4929'],
    'views/ops/idems/smart.php' => ['s'=>3300,'h'=>'177c5ce1d1942bb1'],
    'views/ops/idems/template_preview.php' => ['s'=>3791,'h'=>'c3cf9d7740404fa5'],
    'views/ops/idems/templates.php' => ['s'=>14268,'h'=>'bf899d1f5c5fdffb'],
    'views/ops/idems/vendor_detail.php' => ['s'=>25003,'h'=>'c525cc63f746539c'],
    'views/ops/idems/vendor_register.php' => ['s'=>5271,'h'=>'fb124a6292a4fdcb'],
    'views/ops/idems/vet_review.php' => ['s'=>4751,'h'=>'69fb91e13962a24f'],
    'views/ops/idems/vetting_checklist.php' => ['s'=>2799,'h'=>'d6038adab34ca8c1'],
    'views/ops/idems/writing.php' => ['s'=>3588,'h'=>'3c241ff132871b7e'],
    'views/ops/identity.php' => ['s'=>11167,'h'=>'ffc0ba3f68494001'],
    'views/ops/identity_access.php' => ['s'=>4128,'h'=>'ff6f20e65d1a7b30'],
    'views/ops/impartiality.php' => ['s'=>8907,'h'=>'13d8584b2f8a0791'],
    'views/ops/incident_detail.php' => ['s'=>4503,'h'=>'2c7ba200a5fc2960'],
    'views/ops/incident_form.php' => ['s'=>5464,'h'=>'e475b7f16801c901'],
    'views/ops/incidents.php' => ['s'=>2842,'h'=>'b7d86d97e273a923'],
    'views/ops/industry.php' => ['s'=>4656,'h'=>'0361e6a641619d68'],
    'views/ops/inspector_form.php' => ['s'=>28824,'h'=>'facaee3c14d0ec6a'],
    'views/ops/inspector_list.php' => ['s'=>3816,'h'=>'0733dc81642bfc79'],
    'views/ops/inspector_profile.php' => ['s'=>7273,'h'=>'f3822457985b1a29'],
    'views/ops/integrations.php' => ['s'=>2215,'h'=>'1f2932ba4a67f99c'],
    'views/ops/invoice_detail.php' => ['s'=>21600,'h'=>'dca5ddcca1c40643'],
    'views/ops/invoice_form.php' => ['s'=>7338,'h'=>'10f924f4e6ce968b'],
    'views/ops/invoice_print.php' => ['s'=>9333,'h'=>'821ed9c10083ae51'],
    'views/ops/invoices.php' => ['s'=>4032,'h'=>'143bc30c85bb2872'],
    'views/ops/invoicing.php' => ['s'=>4380,'h'=>'01efe77afde56500'],
    'views/ops/issues.php' => ['s'=>5267,'h'=>'dcb81bc09ecdf357'],
    'views/ops/job_close.php' => ['s'=>6109,'h'=>'490c017ab59cb999'],
    'views/ops/job_detail.php' => ['s'=>89346,'h'=>'4df7513eaf19c61b'],
    'views/ops/job_form.php' => ['s'=>58267,'h'=>'fc27fcee363d06e0'],
    'views/ops/jobs.php' => ['s'=>7058,'h'=>'54c963c415ee7600'],
    'views/ops/lead_convert.php' => ['s'=>2983,'h'=>'36d24e0b5dfc08ab'],
    'views/ops/lead_detail.php' => ['s'=>26592,'h'=>'250c21c11b287ae2'],
    'views/ops/lead_form.php' => ['s'=>13316,'h'=>'4b62e8d6ec3009bd'],
    'views/ops/leads.php' => ['s'=>9683,'h'=>'92e376b49c03b080'],
    'views/ops/ledger.php' => ['s'=>4304,'h'=>'414f7ffeb7052c79'],
    'views/ops/licence.php' => ['s'=>9536,'h'=>'e8057addcfa2d6d0'],
    'views/ops/licence_issue.php' => ['s'=>12393,'h'=>'0971ae45fc16c7c4'],
    'views/ops/licence_issued.php' => ['s'=>2215,'h'=>'d235c3dc7e109928'],
    'views/ops/lookup_values.php' => ['s'=>10085,'h'=>'2575f8027b752163'],
    'views/ops/lookups.php' => ['s'=>7897,'h'=>'73766c1a1415e0eb'],
    'views/ops/master_form.php' => ['s'=>2467,'h'=>'070f77709f4e7491'],
    'views/ops/master_list.php' => ['s'=>2026,'h'=>'f70404fa878f3b3f'],
    'views/ops/masters.php' => ['s'=>12001,'h'=>'979ed08b2397dab4'],
    'views/ops/method_detail.php' => ['s'=>4149,'h'=>'6fa263fc474157f8'],
    'views/ops/method_form.php' => ['s'=>3213,'h'=>'7fe89933bdbb7c9b'],
    'views/ops/methods_list.php' => ['s'=>2048,'h'=>'4bd95c6498a08deb'],
    'views/ops/mis.php' => ['s'=>15784,'h'=>'72698e5cc784962e'],
    'views/ops/mkt_escrow.php' => ['s'=>8227,'h'=>'91cc9ea78d55268a'],
    'views/ops/mkt_gates.php' => ['s'=>1969,'h'=>'af43ddabd3ca2144'],
    'views/ops/mkt_ledger.php' => ['s'=>5312,'h'=>'e317bc521f86c81f'],
    'views/ops/mkt_plans.php' => ['s'=>18432,'h'=>'7410e6be555059e2'],
    'views/ops/mkt_rules.php' => ['s'=>8883,'h'=>'1eb57c8a8e0937b3'],
    'views/ops/module_locked.php' => ['s'=>2419,'h'=>'82de67be8483b36c'],
    'views/ops/my_approvals.php' => ['s'=>7347,'h'=>'a280d5d2e43a13ea'],
    'views/ops/my_jobs.php' => ['s'=>15228,'h'=>'bb2e2bb4879b97c4'],
    'views/ops/my_work.php' => ['s'=>5843,'h'=>'ec265183f9eba395'],
    'views/ops/ncr_detail.php' => ['s'=>8922,'h'=>'c24f08a446353eef'],
    'views/ops/ncr_form.php' => ['s'=>4330,'h'=>'e54b0782526cc326'],
    'views/ops/ncr_list.php' => ['s'=>2266,'h'=>'416909219ce7dee9'],
    'views/ops/notifications.php' => ['s'=>4012,'h'=>'8f87ad279024c197'],
    'views/ops/office_finance.php' => ['s'=>10390,'h'=>'0c4ba07f736f995d'],
    'views/ops/operations_home.php' => ['s'=>16892,'h'=>'8e5941034079c7ac'],
    'views/ops/opportunities.php' => ['s'=>6993,'h'=>'f6c105203b970888'],
    'views/ops/opportunity_detail.php' => ['s'=>32883,'h'=>'d7a470b99774e730'],
    'views/ops/opportunity_form.php' => ['s'=>6508,'h'=>'cb05777e648dc213'],
    'views/ops/ops_desk.php' => ['s'=>5950,'h'=>'50cc74a4ae6338bb'],
    'views/ops/owner_home.php' => ['s'=>6289,'h'=>'b417b9c31ab009a5'],
    'views/ops/partner_import.php' => ['s'=>6405,'h'=>'dd873e0e376783b1'],
    'views/ops/person_erase.php' => ['s'=>2748,'h'=>'790e73d880be6d75'],
    'views/ops/pipeline_edit.php' => ['s'=>7169,'h'=>'d72f4149566ccc5e'],
    'views/ops/pipelines.php' => ['s'=>4360,'h'=>'d3909c30e881c937'],
    'views/ops/portal_user_perms.php' => ['s'=>4461,'h'=>'7e54018feb626c27'],
    'views/ops/portal_users.php' => ['s'=>12861,'h'=>'cc442582bbfe4415'],
    'views/ops/positions.php' => ['s'=>6531,'h'=>'a2b8b745a2be0986'],
    'views/ops/positions_import.php' => ['s'=>7450,'h'=>'06acd96271eb8540'],
    'views/ops/positions_org.php' => ['s'=>4761,'h'=>'f2346193aac18b77'],
    'views/ops/preflight.php' => ['s'=>2844,'h'=>'b076eae0d7c568c2'],
    'views/ops/preorder_checklist.php' => ['s'=>1902,'h'=>'71d84531e6250a45'],
    'views/ops/pricing_usage.php' => ['s'=>4951,'h'=>'5ba046f01c3047e7'],
    'views/ops/privacy.php' => ['s'=>1458,'h'=>'cc20feb046ed0b1a'],
    'views/ops/product_package.php' => ['s'=>4732,'h'=>'a87a7a169d94fe14'],
    'views/ops/profitability_detail.php' => ['s'=>10185,'h'=>'f49f71530b916b7e'],
    'views/ops/profitability_list.php' => ['s'=>9655,'h'=>'8fed2c9925d511dc'],
    'views/ops/project_costing.php' => ['s'=>23259,'h'=>'10d83490d40f9dbb'],
    'views/ops/project_costing_print.php' => ['s'=>6381,'h'=>'c68917f4350182a1'],
    'views/ops/project_costings.php' => ['s'=>3873,'h'=>'83993381279cf9e9'],
    'views/ops/raise_call.php' => ['s'=>4805,'h'=>'0f81d9189bc08419'],
    'views/ops/rating_disputes.php' => ['s'=>5556,'h'=>'09fa077f65f93427'],
    'views/ops/ratings.php' => ['s'=>5089,'h'=>'bd3ffc93de714b83'],
    'views/ops/receipt_detail.php' => ['s'=>6332,'h'=>'a8433a9902e648b0'],
    'views/ops/receipt_form.php' => ['s'=>3805,'h'=>'d0366472167fa305'],
    'views/ops/receipts.php' => ['s'=>3359,'h'=>'27bc04ca35a7b2eb'],
    'views/ops/receivables.php' => ['s'=>5635,'h'=>'53e496f95a581e31'],
    'views/ops/recruit_pipelines.php' => ['s'=>11827,'h'=>'c4c1d8ad49872179'],
    'views/ops/recruitment_cc.php' => ['s'=>46428,'h'=>'c1d0be686cbdfda9'],
    'views/ops/recruitment_home.php' => ['s'=>15139,'h'=>'6dab318f5eb0a7f7'],
    'views/ops/recurring.php' => ['s'=>3953,'h'=>'2bb8c968338703fb'],
    'views/ops/reimbursable_dedup.php' => ['s'=>5102,'h'=>'da32e91a64ec4b37'],
    'views/ops/report_reviews.php' => ['s'=>5133,'h'=>'5430d377e91ab88c'],
    'views/ops/reports.php' => ['s'=>15006,'h'=>'c0238ade91734a27'],
    'views/ops/requisition_detail.php' => ['s'=>30525,'h'=>'e230d7f9c6cae8ad'],
    'views/ops/requisition_form.php' => ['s'=>66543,'h'=>'f43367970a8b44ee'],
    'views/ops/requisition_list.php' => ['s'=>2658,'h'=>'bb32a939b2dc11fb'],
    'views/ops/reset_data.php' => ['s'=>3876,'h'=>'68118be018101456'],
    'views/ops/retention.php' => ['s'=>3191,'h'=>'4a77f1a998ca84ff'],
    'views/ops/revenue_reconciliation.php' => ['s'=>5605,'h'=>'29873cdefc92bfea'],
    'views/ops/review_detail.php' => ['s'=>8910,'h'=>'07d9520c1f5cfd04'],
    'views/ops/reviews_list.php' => ['s'=>3722,'h'=>'73d37de8c2018676'],
    'views/ops/risk_detail.php' => ['s'=>2785,'h'=>'6cb7db3ecd5524e8'],
    'views/ops/risk_form.php' => ['s'=>3356,'h'=>'f43057f55e88ff02'],
    'views/ops/risks_list.php' => ['s'=>2204,'h'=>'93906156b7d808ab'],
    'views/ops/role_workspaces.php' => ['s'=>4048,'h'=>'66938f5a5139889f'],
    'views/ops/saas_companies.php' => ['s'=>24209,'h'=>'dcdbac6621941dd3'],
    'views/ops/sample_detail.php' => ['s'=>4311,'h'=>'4529a1888c336f30'],
    'views/ops/sample_form.php' => ['s'=>4129,'h'=>'dfabaedae75c6f9f'],
    'views/ops/samples_list.php' => ['s'=>2173,'h'=>'18bdce8f8a7621a9'],
    'views/ops/satisfaction_detail.php' => ['s'=>6501,'h'=>'09540942852b575c'],
    'views/ops/satisfaction_form.php' => ['s'=>1969,'h'=>'9dc84e16742605c5'],
    'views/ops/satisfaction_list.php' => ['s'=>3787,'h'=>'4d78826e8120b1d1'],
    'views/ops/sbu_pl.php' => ['s'=>11956,'h'=>'4e236e5072107aa4'],
    'views/ops/schedule_board.php' => ['s'=>10666,'h'=>'351d543604cd020e'],
    'views/ops/search.php' => ['s'=>5732,'h'=>'18f633a71a6d1d13'],
    'views/ops/service_formats.php' => ['s'=>2923,'h'=>'0da7c4881ffb154c'],
    'views/ops/service_scope.php' => ['s'=>8596,'h'=>'580a7c044d31aa3c'],
    'views/ops/settings.php' => ['s'=>72365,'h'=>'2eff5891b467e9be'],
    'views/ops/setup.php' => ['s'=>4915,'h'=>'3d515f379722674c'],
    'views/ops/site_docs.php' => ['s'=>4927,'h'=>'f03c1d9c89e53436'],
    'views/ops/sla_targets.php' => ['s'=>2640,'h'=>'e78061dbc1d27d5e'],
    'views/ops/sso.php' => ['s'=>5411,'h'=>'7e627345af817c60'],
    'views/ops/stage_gates.php' => ['s'=>10292,'h'=>'a42b4e0087a241e4'],
    'views/ops/subscription.php' => ['s'=>5599,'h'=>'d202fff8f46cefcd'],
    'views/ops/super_admin.php' => ['s'=>25600,'h'=>'f4d5a83d44fb7a8b'],
    'views/ops/system_status.php' => ['s'=>2399,'h'=>'90287c341b6166f4'],
    'views/ops/tally_export.php' => ['s'=>12750,'h'=>'831011525527b02d'],
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
    'views/ops/tenants.php' => ['s'=>19930,'h'=>'3a967825bf495de8'],
    'views/ops/terminology.php' => ['s'=>4401,'h'=>'984504c0b825174e'],
    'views/ops/timesheet.php' => ['s'=>5962,'h'=>'bef076d2397f0133'],
    'views/ops/to_bill.php' => ['s'=>7030,'h'=>'c2399620b3458380'],
    'views/ops/trace.php' => ['s'=>2731,'h'=>'a2ca35689d6fe75c'],
    'views/ops/trace_thread.php' => ['s'=>4595,'h'=>'77a12293e74ad0d7'],
    'views/ops/two_factor.php' => ['s'=>7474,'h'=>'82d9491898763fa0'],
    'views/ops/user_form.php' => ['s'=>44009,'h'=>'925120f53e152d03'],
    'views/ops/users.php' => ['s'=>8775,'h'=>'204b1b963fcf37a5'],
    'views/ops/vendor.php' => ['s'=>5070,'h'=>'bb8cb8c9dd7e8a3d'],
    'views/ops/vendor_users.php' => ['s'=>8866,'h'=>'a6d6c696ee0d48f8'],
    'views/ops/verify.php' => ['s'=>8818,'h'=>'985a249a4ed58388'],
    'views/ops/voucher_detail.php' => ['s'=>28466,'h'=>'f40ba5f2999e741c'],
    'views/ops/voucher_list.php' => ['s'=>4388,'h'=>'e18c3d811b6157f3'],
    'views/ops/voucher_print.php' => ['s'=>5478,'h'=>'81b9eb2a01ced931'],
    'views/ops/welcome.php' => ['s'=>2992,'h'=>'9b2e2982b431759f'],
    'views/ops/work_norms.php' => ['s'=>3290,'h'=>'6de15d53b6631cee'],
    'views/po_detail.php' => ['s'=>9572,'h'=>'90a76e6f2d63395d'],
    'views/portal/accept.php' => ['s'=>1611,'h'=>'f3da5a665bc10077'],
    'views/portal/alerts.php' => ['s'=>1519,'h'=>'77aa6e37d664f4cc'],
    'views/portal/assistant.php' => ['s'=>1279,'h'=>'ed9cc6341e5302e1'],
    'views/portal/bench.php' => ['s'=>8652,'h'=>'b789ca4d3e77c630'],
    'views/portal/bottom.php' => ['s'=>260,'h'=>'38c0e9ceb9238454'],
    'views/portal/call.php' => ['s'=>2348,'h'=>'e9bfa6995655492b'],
    'views/portal/calls.php' => ['s'=>1346,'h'=>'d9647d8d744a4798'],
    'views/portal/complaint_new.php' => ['s'=>3662,'h'=>'c53862ab78ef4620'],
    'views/portal/complaints.php' => ['s'=>1893,'h'=>'c898dd644d85604a'],
    'views/portal/dashboard.php' => ['s'=>5211,'h'=>'7f114d902246a289'],
    'views/portal/deputations.php' => ['s'=>5877,'h'=>'d80f8cb2f7a73958'],
    'views/portal/find.php' => ['s'=>12261,'h'=>'078a098140aaefe8'],
    'views/portal/hire.php' => ['s'=>22534,'h'=>'3b1243f6965c49f4'],
    'views/portal/hire_req.php' => ['s'=>16348,'h'=>'00f04064a8121cf1'],
    'views/portal/hiring.php' => ['s'=>8831,'h'=>'eedf44396241f48c'],
    'views/portal/invoices.php' => ['s'=>2441,'h'=>'bbdcb859642b93a3'],
    'views/portal/issue.php' => ['s'=>4197,'h'=>'233509febb755e4f'],
    'views/portal/issues.php' => ['s'=>1308,'h'=>'6c16b1e94a076203'],
    'views/portal/login.php' => ['s'=>2228,'h'=>'e3075e6f552c05a6'],
    'views/portal/message.php' => ['s'=>209,'h'=>'d6b5626385072953'],
    'views/portal/notfound.php' => ['s'=>364,'h'=>'98ea4b4496765970'],
    'views/portal/off.php' => ['s'=>798,'h'=>'f5b94195c2dc1c07'],
    'views/portal/password.php' => ['s'=>1136,'h'=>'19a6e5bbcaa9da54'],
    'views/portal/plans.php' => ['s'=>6036,'h'=>'0d0bbfcfa25af974'],
    'views/portal/report_decide.php' => ['s'=>4117,'h'=>'6cf1b536613df6cf'],
    'views/portal/reports.php' => ['s'=>2325,'h'=>'dbf1c37a223c3edb'],
    'views/portal/reputation.php' => ['s'=>4492,'h'=>'260979cec5992df3'],
    'views/portal/request.php' => ['s'=>3081,'h'=>'d3ce954259e2e396'],
    'views/portal/roster.php' => ['s'=>9251,'h'=>'cc1f322c0ecdda96'],
    'views/portal/talent.php' => ['s'=>10080,'h'=>'1dec998e9609120c'],
    'views/portal/team.php' => ['s'=>3308,'h'=>'15cc2ab9399b15d8'],
    'views/portal/top.php' => ['s'=>5893,'h'=>'ee346148c3a22442'],
    'views/portal/voucher.php' => ['s'=>13222,'h'=>'e40de6aa8a83b8a2'],
    'views/pro/applications.php' => ['s'=>2208,'h'=>'7c4febd8703426aa'],
    'views/pro/bookings.php' => ['s'=>3693,'h'=>'f348f74b6510728f'],
    'views/pro/bottom.php' => ['s'=>22,'h'=>'c3d3f7a894cb6988'],
    'views/pro/credentials.php' => ['s'=>9585,'h'=>'2e0df9d7c73f8dca'],
    'views/pro/cv.php' => ['s'=>3681,'h'=>'8256948ce18a5649'],
    'views/pro/dashboard.php' => ['s'=>6174,'h'=>'7e46104bc4fdbde7'],
    'views/pro/documents.php' => ['s'=>2765,'h'=>'8e61162f922622df'],
    'views/pro/forgot.php' => ['s'=>886,'h'=>'928cefdbfa6eeccc'],
    'views/pro/jobs.php' => ['s'=>4859,'h'=>'432f9d51e8157c92'],
    'views/pro/login.php' => ['s'=>889,'h'=>'de9818a1e9363fb7'],
    'views/pro/messages.php' => ['s'=>4605,'h'=>'709ca4e9a174af0d'],
    'views/pro/plans.php' => ['s'=>6291,'h'=>'857e15f984f785a0'],
    'views/pro/privacy.php' => ['s'=>7620,'h'=>'f7e936c5cbb53131'],
    'views/pro/profile.php' => ['s'=>22167,'h'=>'67650e9d12b0c6ee'],
    'views/pro/register.php' => ['s'=>906,'h'=>'cd351a3d8f969e8f'],
    'views/pro/reputation.php' => ['s'=>3955,'h'=>'493fbd0a4e0522ae'],
    'views/pro/reset.php' => ['s'=>1282,'h'=>'2d013d6328c066f4'],
    'views/pro/top.php' => ['s'=>4109,'h'=>'9ebdd550a114236f'],
    'views/pro/verify.php' => ['s'=>5083,'h'=>'9195c14bb425eb8d'],
    'views/pro/voucher.php' => ['s'=>17410,'h'=>'b4f490c642754845'],
    'views/pro/vouchers.php' => ['s'=>4684,'h'=>'1664b6935d7928d2'],
    'views/public/get_started.php' => ['s'=>8788,'h'=>'58316e2c3ca63fc1'],
    'views/reset_password.php' => ['s'=>3677,'h'=>'bbbee0a4f6e37827'],
    'views/vendor/accept.php' => ['s'=>1615,'h'=>'1f104d773e83c106'],
    'views/vendor/alerts.php' => ['s'=>1519,'h'=>'e3e2bb09680b5ee1'],
    'views/vendor/assistant.php' => ['s'=>1244,'h'=>'ac3213837c04474f'],
    'views/vendor/bottom.php' => ['s'=>192,'h'=>'80ac2cd745fce93c'],
    'views/vendor/dashboard.php' => ['s'=>3322,'h'=>'145b9fb19730bf7b'],
    'views/vendor/issue.php' => ['s'=>4197,'h'=>'41b1c1fbf9282782'],
    'views/vendor/issues.php' => ['s'=>1444,'h'=>'3d7683ed3ffd614e'],
    'views/vendor/login.php' => ['s'=>1256,'h'=>'6be191ad653e0e0c'],
    'views/vendor/message.php' => ['s'=>213,'h'=>'66b856f83d70e0d3'],
    'views/vendor/notfound.php' => ['s'=>373,'h'=>'5fc61e9aebd3ab35'],
    'views/vendor/off.php' => ['s'=>738,'h'=>'bc8ac100a03fd84a'],
    'views/vendor/opportunities.php' => ['s'=>2668,'h'=>'d41c751eb44e16e7'],
    'views/vendor/password.php' => ['s'=>1140,'h'=>'0ce31e97ab05f47a'],
    'views/vendor/qualification.php' => ['s'=>3160,'h'=>'076c8ef2c6e314e4'],
    'views/vendor/reports.php' => ['s'=>1594,'h'=>'1930c8ce4c8b021e'],
    'views/vendor/top.php' => ['s'=>4428,'h'=>'769fac7db59f28af'],
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

if ($IS_CLI) {
    echo "EXAACT deployment check — release {$RELEASE}\n";
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
