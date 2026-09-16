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

$RELEASE = '461fb94 · 2026-09-16 14:51 UTC · 660 files';
$EXPECT  = [
    'api.php' => ['s'=>2282,'h'=>'1b2ca28973254f89'],
    'config.local.sample.php' => ['s'=>2228,'h'=>'0813d712b25666dd'],
    'cron.php' => ['s'=>23081,'h'=>'b2ebb8eff370868e'],
    'cron_ads.php' => ['s'=>5306,'h'=>'4aad92eab174ea01'],
    'diagnose.php' => ['s'=>10005,'h'=>'791b255b31782ab7'],
    'index.php' => ['s'=>117010,'h'=>'31943462d15dddf6'],
    'lib/access.php' => ['s'=>71117,'h'=>'cb498f1e83cfa87b'],
    'lib/access_state.php' => ['s'=>8645,'h'=>'d8376157de232340'],
    'lib/activity.php' => ['s'=>24188,'h'=>'21bec9d2bce46908'],
    'lib/adspro.php' => ['s'=>27606,'h'=>'f2df3f7d9b3f3fbc'],
    'lib/adsroi.php' => ['s'=>12887,'h'=>'2418d515c52e3905'],
    'lib/adssync.php' => ['s'=>28174,'h'=>'325142a88491bfba'],
    'lib/advisor.php' => ['s'=>42525,'h'=>'1de07288d04eb86b'],
    'lib/agreement.php' => ['s'=>30404,'h'=>'850a0f08f100b313'],
    'lib/ai.php' => ['s'=>26123,'h'=>'9a1b149e7eb200b0'],
    'lib/ai_formgen.php' => ['s'=>11931,'h'=>'3193de5d4fc90414'],
    'lib/areas.php' => ['s'=>35905,'h'=>'3ec6e4c8ef577d7b'],
    'lib/assets.php' => ['s'=>14164,'h'=>'c741d1d0aff275bd'],
    'lib/attend.php' => ['s'=>13919,'h'=>'695a4eae1fd9e2a9'],
    'lib/attendreview.php' => ['s'=>7794,'h'=>'cfcd8f928e63efad'],
    'lib/audits.php' => ['s'=>41474,'h'=>'f12a8e9feea953ea'],
    'lib/backup.php' => ['s'=>18340,'h'=>'cd0bafb29a6b4a8e'],
    'lib/billable.php' => ['s'=>28937,'h'=>'912d22dd3639fc99'],
    'lib/billing.php' => ['s'=>14639,'h'=>'f1395bd56bae517b'],
    'lib/bills.php' => ['s'=>11324,'h'=>'498117c26906b1a3'],
    'lib/books.php' => ['s'=>62065,'h'=>'00aa2b6708be8169'],
    'lib/booksbridge.php' => ['s'=>25742,'h'=>'083a31fe1d565994'],
    'lib/booksui.php' => ['s'=>24128,'h'=>'071a6d60603af0aa'],
    'lib/bulk.php' => ['s'=>2157,'h'=>'ca3be826f9a4414c'],
    'lib/callprofit.php' => ['s'=>5760,'h'=>'7f81b6e7a9d7835f'],
    'lib/candpool.php' => ['s'=>9050,'h'=>'0e367f7bb35f314c'],
    'lib/capa.php' => ['s'=>34248,'h'=>'8b68c9bbc8270eeb'],
    'lib/careers.php' => ['s'=>21942,'h'=>'beaee2bd1ea43c25'],
    'lib/chain.php' => ['s'=>27590,'h'=>'38344fcbe18d90b5'],
    'lib/comp_config.php' => ['s'=>7659,'h'=>'27cd15fc2a212675'],
    'lib/company.php' => ['s'=>7536,'h'=>'cf72adf65cfa6d50'],
    'lib/competence.php' => ['s'=>54296,'h'=>'0761c88493b8c827'],
    'lib/complaints.php' => ['s'=>35352,'h'=>'2c5ab419a0c54607'],
    'lib/compliance.php' => ['s'=>63746,'h'=>'e69f2e68c5223121'],
    'lib/compose.php' => ['s'=>6280,'h'=>'ba51539fdee0ae7f'],
    'lib/confidentiality.php' => ['s'=>22493,'h'=>'ba4cb7c875e69d33'],
    'lib/connect_advisor.php' => ['s'=>4420,'h'=>'f39961034078a66a'],
    'lib/connect_analytics.php' => ['s'=>10322,'h'=>'55ce5e2749ea0bda'],
    'lib/connect_bench.php' => ['s'=>19261,'h'=>'452adce3bb2a9900'],
    'lib/connect_bridge.php' => ['s'=>8142,'h'=>'0cb82ce64f386984'],
    'lib/connect_capability.php' => ['s'=>16087,'h'=>'289fbc3197361214'],
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
    'lib/connect_identity.php' => ['s'=>18062,'h'=>'63c4f28bce150082'],
    'lib/connect_kpi.php' => ['s'=>25262,'h'=>'3d44c0796425cb27'],
    'lib/connect_market.php' => ['s'=>39876,'h'=>'0fd3332b96bb74f5'],
    'lib/connect_match.php' => ['s'=>29253,'h'=>'6a3ceb740240e242'],
    'lib/connect_msg.php' => ['s'=>16414,'h'=>'37fd232a1fba99cc'],
    'lib/connect_org.php' => ['s'=>15830,'h'=>'626fdf913d82ba09'],
    'lib/connect_passport.php' => ['s'=>11025,'h'=>'651b739f219f52b4'],
    'lib/connect_person.php' => ['s'=>6710,'h'=>'d57da3e1e9249c23'],
    'lib/connect_privacy.php' => ['s'=>17610,'h'=>'74640b7aef0d480c'],
    'lib/connect_pro.php' => ['s'=>62286,'h'=>'e807b7400ce28688'],
    'lib/connect_qualtax.php' => ['s'=>24094,'h'=>'26af62b7d906d441'],
    'lib/connect_rating_disputes.php' => ['s'=>8981,'h'=>'0152a5d87d7e6917'],
    'lib/connect_ratings.php' => ['s'=>7478,'h'=>'cf59bcdbfc71b896'],
    'lib/connect_reqtools.php' => ['s'=>8395,'h'=>'7bff01ce54e08ac9'],
    'lib/connect_source.php' => ['s'=>8866,'h'=>'dfeb00f12cffb066'],
    'lib/connect_tax_graph.php' => ['s'=>34791,'h'=>'d2bc5c930179f0f6'],
    'lib/connect_taxonomy.php' => ['s'=>9836,'h'=>'3757c0d0d7daeb05'],
    'lib/connect_trust.php' => ['s'=>7228,'h'=>'27ac2d7be0dd9e28'],
    'lib/connect_verify.php' => ['s'=>18988,'h'=>'55e8aee1eccae351'],
    'lib/contracts.php' => ['s'=>67221,'h'=>'e6d0e7c682bff5d1'],
    'lib/controldocs.php' => ['s'=>15272,'h'=>'e99fcf054f6e6b1e'],
    'lib/costing.php' => ['s'=>60974,'h'=>'a8ecf706366db1cf'],
    'lib/costrecon.php' => ['s'=>11258,'h'=>'a6af5a16164f3458'],
    'lib/cpanel.php' => ['s'=>8181,'h'=>'9322634c72aed841'],
    'lib/crm.php' => ['s'=>228703,'h'=>'5cff2ebec36138c9'],
    'lib/crmdash.php' => ['s'=>14139,'h'=>'01c4f1181a8fcf4d'],
    'lib/customer360.php' => ['s'=>18815,'h'=>'62cf444263106e61'],
    'lib/customforms.php' => ['s'=>12896,'h'=>'a60a82554280a959'],
    'lib/cvp.php' => ['s'=>60199,'h'=>'e90a428d9e8c4a71'],
    'lib/datacontrol.php' => ['s'=>37308,'h'=>'be66a03dcb445dac'],
    'lib/datatable.php' => ['s'=>15699,'h'=>'dfea30bbc69ccef3'],
    'lib/db.php' => ['s'=>44882,'h'=>'b2b6d2c1125e566a'],
    'lib/decisionrules.php' => ['s'=>12116,'h'=>'7f7d5a9cd6e144ef'],
    'lib/dedupe.php' => ['s'=>10889,'h'=>'89e689019ac0901e'],
    'lib/deptorg.php' => ['s'=>35388,'h'=>'6517b0f59b23fe0e'],
    'lib/disclosure.php' => ['s'=>6412,'h'=>'d93ca116db60f19a'],
    'lib/doc_templates.php' => ['s'=>21517,'h'=>'3e47c3f72458f0a0'],
    'lib/engagement.php' => ['s'=>13585,'h'=>'ff1a5552554877f4'],
    'lib/entitlement_migrate.php' => ['s'=>10281,'h'=>'bfd1dc34dec3e38b'],
    'lib/entity360.php' => ['s'=>4909,'h'=>'3d405b448a70e1e0'],
    'lib/equipment.php' => ['s'=>23618,'h'=>'78c1818b0b0ef3f5'],
    'lib/finevent.php' => ['s'=>8838,'h'=>'4ce2d3d330cd3231'],
    'lib/formdesign.php' => ['s'=>27024,'h'=>'0feb6ccff1be7076'],
    'lib/geofence.php' => ['s'=>20775,'h'=>'0bda93308e9f8e10'],
    'lib/helpers.php' => ['s'=>21408,'h'=>'0158217e1aae4477'],
    'lib/hiringreq.php' => ['s'=>47058,'h'=>'7585007a2b26e23b'],
    'lib/hwpoints.php' => ['s'=>16950,'h'=>'3b298a9d122d31a6'],
    'lib/idems.php' => ['s'=>786409,'h'=>'3d46d6301e9ec8e5'],
    'lib/idems_autoform.php' => ['s'=>9923,'h'=>'d4520363260f9c48'],
    'lib/identity.php' => ['s'=>44820,'h'=>'bd78e87f5794938e'],
    'lib/impartiality.php' => ['s'=>18224,'h'=>'70a0c7b051724178'],
    'lib/indexes.php' => ['s'=>11811,'h'=>'daffedd9f8750230'],
    'lib/industry.php' => ['s'=>30759,'h'=>'ddb3f3afbd4600db'],
    'lib/inspectorprofile.php' => ['s'=>3920,'h'=>'f995d0ac6cff433f'],
    'lib/install_mode.php' => ['s'=>4935,'h'=>'0c1d5ab96ff54b68'],
    'lib/invready.php' => ['s'=>6922,'h'=>'0f7637be388be1fc'],
    'lib/joblock.php' => ['s'=>12799,'h'=>'1b310fc2c11eb619'],
    'lib/leads.php' => ['s'=>73437,'h'=>'cf21309dd47e8edd'],
    'lib/licence.php' => ['s'=>31138,'h'=>'ae1f563479d4009c'],
    'lib/licenceissue.php' => ['s'=>28363,'h'=>'e750e57678199d20'],
    'lib/licencekey.php' => ['s'=>29242,'h'=>'c42b2078fe80352f'],
    'lib/licencesync.php' => ['s'=>10254,'h'=>'c17cfa585e721f62'],
    'lib/lookups.php' => ['s'=>74591,'h'=>'3c1032ff3c020222'],
    'lib/methods.php' => ['s'=>13959,'h'=>'14969626618c15f8'],
    'lib/mghsso.php' => ['s'=>10002,'h'=>'80f043e73ff44d13'],
    'lib/mis.php' => ['s'=>23675,'h'=>'07733bc4e9241217'],
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
    'lib/navindex.php' => ['s'=>12087,'h'=>'44ff31525a8f9819'],
    'lib/ncdca.php' => ['s'=>44960,'h'=>'d64c2c2e6eddc5e1'],
    'lib/ncr.php' => ['s'=>33936,'h'=>'ec00fe27bea070b0'],
    'lib/numbering.php' => ['s'=>9214,'h'=>'a00fd93a0150d3bf'],
    'lib/onboarding.php' => ['s'=>3867,'h'=>'c9f9c319c1f15cd3'],
    'lib/opportunities.php' => ['s'=>66290,'h'=>'4ff1bc5a2652ad81'],
    'lib/ops.php' => ['s'=>595384,'h'=>'686b7677d1983d17'],
    'lib/orgadmin.php' => ['s'=>76662,'h'=>'de9dbfea41642ec8'],
    'lib/organogram.php' => ['s'=>20449,'h'=>'b2f7eb7b0c9d4c4f'],
    'lib/owner_home.php' => ['s'=>3582,'h'=>'a0831edf5e9652a3'],
    'lib/packs.php' => ['s'=>14404,'h'=>'2c631d79aea60e49'],
    'lib/partnerimport.php' => ['s'=>23742,'h'=>'bc507ebf2a770c8f'],
    'lib/party.php' => ['s'=>11622,'h'=>'55ad01fbf381e780'],
    'lib/pdf.php' => ['s'=>26663,'h'=>'356a367da31c40a6'],
    'lib/pdso.php' => ['s'=>62942,'h'=>'b8624fad908da790'],
    'lib/pipelines.php' => ['s'=>14596,'h'=>'8f62abd870e47a71'],
    'lib/portal.php' => ['s'=>104411,'h'=>'c56a746efc289328'],
    'lib/position.php' => ['s'=>19369,'h'=>'66f6dff09b985620'],
    'lib/preflight.php' => ['s'=>5451,'h'=>'fbf0949ead05b0b8'],
    'lib/pricing_admin.php' => ['s'=>10272,'h'=>'7f36c21dab0d782d'],
    'lib/projcosting.php' => ['s'=>33732,'h'=>'48ba8a74f97ef790'],
    'lib/pwreset.php' => ['s'=>9509,'h'=>'50675e1d291df7b0'],
    'lib/qr.php' => ['s'=>17936,'h'=>'4d50b69b53bfa5e2'],
    'lib/qualitycase.php' => ['s'=>6179,'h'=>'280289c99f157cd7'],
    'lib/rating.php' => ['s'=>7766,'h'=>'f5bbd79552189e10'],
    'lib/receivables.php' => ['s'=>13029,'h'=>'bfbc04eeaf0fb181'],
    'lib/recruit.php' => ['s'=>69782,'h'=>'98a1add9135157ea'],
    'lib/recruit_approval.php' => ['s'=>108056,'h'=>'48d4870db8ba441d'],
    'lib/recruit_cc.php' => ['s'=>21325,'h'=>'167b198aef66c593'],
    'lib/recruit_export.php' => ['s'=>7887,'h'=>'70fa492cdd1a8d35'],
    'lib/recruit_iv.php' => ['s'=>36630,'h'=>'ea9e62e8603e3b38'],
    'lib/recruit_jd.php' => ['s'=>8735,'h'=>'29acfa48382e6b50'],
    'lib/recruit_offer.php' => ['s'=>39198,'h'=>'3238ab933c3acbe8'],
    'lib/recruitpipe.php' => ['s'=>41762,'h'=>'02f3bcea8840902f'],
    'lib/reportreview.php' => ['s'=>23222,'h'=>'ae43c4d37883823d'],
    'lib/reqfulfil.php' => ['s'=>11729,'h'=>'d6a5ae81a1809597'],
    'lib/reset.php' => ['s'=>11477,'h'=>'8d961eb9724047b9'],
    'lib/retention.php' => ['s'=>6587,'h'=>'deb6a6d4a270fa36'],
    'lib/revrecon.php' => ['s'=>13441,'h'=>'eb8777d1398cdada'],
    'lib/risks.php' => ['s'=>10571,'h'=>'83a96e16878654a2'],
    'lib/saas_provision_cli.php' => ['s'=>4816,'h'=>'76f04fae8c24f082'],
    'lib/saas_sync_cli.php' => ['s'=>2939,'h'=>'5fb733d865f2f34b'],
    'lib/saas_tenants.php' => ['s'=>83099,'h'=>'02111510016c2a4f'],
    'lib/samples.php' => ['s'=>14677,'h'=>'fa38e5f628b43f6a'],
    'lib/satisfaction.php' => ['s'=>15924,'h'=>'df9f9a6a9f86fa2c'],
    'lib/schedboard.php' => ['s'=>10397,'h'=>'0b2b9fe333604ef3'],
    'lib/schedule.php' => ['s'=>35990,'h'=>'81b963c982b619c8'],
    'lib/search.php' => ['s'=>26790,'h'=>'504ccabad437c5be'],
    'lib/security.php' => ['s'=>38300,'h'=>'59c69d7c557f1016'],
    'lib/seed_connect.php' => ['s'=>22943,'h'=>'06f835c70073b5b5'],
    'lib/seed_costing.php' => ['s'=>7626,'h'=>'d377be1adc35b4da'],
    'lib/seed_demo.php' => ['s'=>109046,'h'=>'b108775c3e881d88'],
    'lib/seed_demo_c.php' => ['s'=>125521,'h'=>'7765694ba131b91c'],
    'lib/seed_recruit_cc.php' => ['s'=>10345,'h'=>'b2ead314e97b24a3'],
    'lib/seed_scenario_s01.php' => ['s'=>34921,'h'=>'37246a2099961d2a'],
    'lib/seed_scenario_s02.php' => ['s'=>37739,'h'=>'154252c0747bd4e3'],
    'lib/seed_scenario_s03.php' => ['s'=>28140,'h'=>'fb92e0808d12db0e'],
    'lib/seed_scenario_s04.php' => ['s'=>15797,'h'=>'afbaeeaef614756d'],
    'lib/seed_scenario_s05.php' => ['s'=>10982,'h'=>'77ae3daf8c427816'],
    'lib/seed_scenario_s06.php' => ['s'=>13231,'h'=>'be2313ba45069404'],
    'lib/services.php' => ['s'=>28398,'h'=>'46e5e2c5fc65a39f'],
    'lib/settingmeta.php' => ['s'=>12701,'h'=>'092392a858cc10d8'],
    'lib/settlement.php' => ['s'=>5371,'h'=>'0b75fbf859930e58'],
    'lib/setup.php' => ['s'=>16806,'h'=>'6371d1ead78681ea'],
    'lib/setup_cockpit.php' => ['s'=>31439,'h'=>'3bf43713e1b2b0e3'],
    'lib/stagegate.php' => ['s'=>21633,'h'=>'9ca3dd19c9846c44'],
    'lib/superadmin.php' => ['s'=>10675,'h'=>'995da0045cd4e7ee'],
    'lib/tally.php' => ['s'=>43582,'h'=>'b555bf1d1298c5bb'],
    'lib/tapi.php' => ['s'=>67209,'h'=>'64834d5b55825df6'],
    'lib/tapi_dash.php' => ['s'=>19519,'h'=>'efd1e7a78a470736'],
    'lib/tapi_gov.php' => ['s'=>17290,'h'=>'111e443d157ce2ce'],
    'lib/tapi_score.php' => ['s'=>14322,'h'=>'e2256ab85fab255c'],
    'lib/tasks.php' => ['s'=>11239,'h'=>'82c11af4ebccd03c'],
    'lib/tenant_migrate.php' => ['s'=>19927,'h'=>'0b63124682d54a07'],
    'lib/tenant_signup.php' => ['s'=>11305,'h'=>'dfa7791f625c93fc'],
    'lib/tenants.php' => ['s'=>22348,'h'=>'7bba6e22de34aa8a'],
    'lib/terms.php' => ['s'=>23374,'h'=>'66c844cd0b0bf9c6'],
    'lib/timesheet.php' => ['s'=>9615,'h'=>'0850f4b87638e4a5'],
    'lib/tmplpreview.php' => ['s'=>3520,'h'=>'770170cf566d21da'],
    'lib/tosrm.php' => ['s'=>173360,'h'=>'7097b3996efc501f'],
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
    'lib/workforce.php' => ['s'=>39343,'h'=>'40796172e585e48a'],
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
    'tools/licence-issue.php' => ['s'=>6116,'h'=>'563a0af4621b9578'],
    'tools/phase1_inventory_engine.php' => ['s'=>53763,'h'=>'587c1ddbd38ed330'],
    'tools/reset-admin.php' => ['s'=>2377,'h'=>'5eba3ddcf695da09'],
    'tools/sbom.php' => ['s'=>5314,'h'=>'7da7803dd8b6a816'],
    'tools/seed-connect.php' => ['s'=>2576,'h'=>'f60af657b9feb4fe'],
    'tools/seed-demo.php' => ['s'=>3956,'h'=>'f2cf2d0bb5d680f0'],
    'tools/seed-scenario-s01.php' => ['s'=>2079,'h'=>'ba293239a674bd65'],
    'tools/seed-scenario-s02.php' => ['s'=>1975,'h'=>'abead3460517be53'],
    'tools/seed-scenario-s03.php' => ['s'=>1412,'h'=>'7bbc4dd8ac4f0398'],
    'tools/seed-scenario-s04.php' => ['s'=>1412,'h'=>'9e0e6ea7ecea379d'],
    'tools/seed-scenario-s05.php' => ['s'=>1483,'h'=>'23b5f892caeda2d4'],
    'tools/seed-scenario-s06.php' => ['s'=>1497,'h'=>'1bec7d4dda66cf5d'],
    'tools/smoke-router.php' => ['s'=>636,'h'=>'098054abc53c9c16'],
    'tools/trace-audit.php' => ['s'=>2789,'h'=>'d95c97624d3d470e'],
    'tools/trace-thread.php' => ['s'=>4962,'h'=>'cb20d74d1a6eb46e'],
    'views/admin.php' => ['s'=>1090,'h'=>'aeb34e3396fd4ac5'],
    'views/dashboard.php' => ['s'=>41733,'h'=>'ed34019f03f18dc7'],
    'views/detail.php' => ['s'=>34739,'h'=>'fc48123e14455cee'],
    'views/forgot_password.php' => ['s'=>2716,'h'=>'c041f8176182b36c'],
    'views/form.php' => ['s'=>24206,'h'=>'959912d692fa235f'],
    'views/layout_bottom.php' => ['s'=>880,'h'=>'e6b33e19b84eb59f'],
    'views/layout_embed_bottom.php' => ['s'=>65,'h'=>'63f4c118c80caf4b'],
    'views/layout_embed_top.php' => ['s'=>730,'h'=>'b20991bedef34be0'],
    'views/layout_top.php' => ['s'=>31725,'h'=>'b93fc5172d79e5a1'],
    'views/list.php' => ['s'=>3275,'h'=>'b234af3a9590aae2'],
    'views/login.php' => ['s'=>602,'h'=>'ff266c37dfd19bb0'],
    'views/login_page.php' => ['s'=>10767,'h'=>'c18991133beffa78'],
    'views/notfound.php' => ['s'=>212,'h'=>'0b4feab07b715eec'],
    'views/ops/_deputation_panel.php' => ['s'=>13672,'h'=>'46545d4243390803'],
    'views/ops/_issue_panel.php' => ['s'=>8621,'h'=>'8f7e08e2edf793d4'],
    'views/ops/_ops_registers.php' => ['s'=>4706,'h'=>'859424b21461a059'],
    'views/ops/_subscription_builder.php' => ['s'=>3772,'h'=>'05ed06dcfe749785'],
    'views/ops/access.php' => ['s'=>9410,'h'=>'bdac410759804f6d'],
    'views/ops/activities.php' => ['s'=>3831,'h'=>'7ef5fab5f699b18a'],
    'views/ops/adspro.php' => ['s'=>13410,'h'=>'a1badb673928f443'],
    'views/ops/adsroi.php' => ['s'=>8693,'h'=>'06e9e0bd9e28cf79'],
    'views/ops/advisor.php' => ['s'=>9256,'h'=>'f382fc92fd8a2ed0'],
    'views/ops/agency_staff.php' => ['s'=>4069,'h'=>'07de7264c0fb9d39'],
    'views/ops/agreement.php' => ['s'=>2064,'h'=>'42ff22625919f285'],
    'views/ops/ai_forms.php' => ['s'=>6775,'h'=>'1db181d672deb472'],
    'views/ops/ai_settings.php' => ['s'=>3976,'h'=>'85c7c7702303d4f6'],
    'views/ops/ai_topup_pay.php' => ['s'=>2522,'h'=>'d49277496bd6ae07'],
    'views/ops/approval_delegations.php' => ['s'=>6265,'h'=>'d6eedb718e5d4758'],
    'views/ops/approval_rules.php' => ['s'=>16619,'h'=>'c289a71a8dd1c8a8'],
    'views/ops/approvals.php' => ['s'=>6693,'h'=>'f828df72d1a093af'],
    'views/ops/area_home.php' => ['s'=>7789,'h'=>'e3516d928823295e'],
    'views/ops/asset_register.php' => ['s'=>12678,'h'=>'4e1059588b45df6f'],
    'views/ops/attendance_recon.php' => ['s'=>3064,'h'=>'d7aa936250745627'],
    'views/ops/attendance_review.php' => ['s'=>2870,'h'=>'74c55ed69d773259'],
    'views/ops/audit_detail.php' => ['s'=>6405,'h'=>'9eb00c768068be8c'],
    'views/ops/audit_form.php' => ['s'=>2166,'h'=>'e45e3d9dc06498fe'],
    'views/ops/audits_list.php' => ['s'=>4190,'h'=>'5b9eee70dcedf084'],
    'views/ops/availability.php' => ['s'=>15644,'h'=>'1c1fc6c31120dbbf'],
    'views/ops/backup.php' => ['s'=>5159,'h'=>'5053e0807421c572'],
    'views/ops/billable_events.php' => ['s'=>8103,'h'=>'11892681e9f2202f'],
    'views/ops/billing.php' => ['s'=>8817,'h'=>'cce993ae611d2c60'],
    'views/ops/billing_pay.php' => ['s'=>3412,'h'=>'3f6031e5af093770'],
    'views/ops/books_bridge.php' => ['s'=>6146,'h'=>'0fd4f65cf994e7de'],
    'views/ops/call_detail.php' => ['s'=>24634,'h'=>'18451665311bb57a'],
    'views/ops/call_form.php' => ['s'=>67751,'h'=>'a8af3f71f77a2107'],
    'views/ops/call_profit.php' => ['s'=>10827,'h'=>'9ceb75929d00146d'],
    'views/ops/calls.php' => ['s'=>11306,'h'=>'d906595ec4d7ae6d'],
    'views/ops/candidate_detail.php' => ['s'=>42741,'h'=>'f88653e75cb82e41'],
    'views/ops/candidate_form.php' => ['s'=>18921,'h'=>'97c81cbce2772ac0'],
    'views/ops/candidate_list.php' => ['s'=>2784,'h'=>'1e2c041b86f7e74d'],
    'views/ops/candidate_pool.php' => ['s'=>4450,'h'=>'6434745c74e6751f'],
    'views/ops/capa_detail.php' => ['s'=>15272,'h'=>'46828d551ec0c77d'],
    'views/ops/capa_form.php' => ['s'=>3726,'h'=>'7fdb96221e701a12'],
    'views/ops/capa_list.php' => ['s'=>4673,'h'=>'5bd3e7a16310d008'],
    'views/ops/capacity_outlook.php' => ['s'=>2285,'h'=>'93f7ffb047237b15'],
    'views/ops/careers_admin.php' => ['s'=>8311,'h'=>'1fac0a46d69d2da9'],
    'views/ops/cdoc_detail.php' => ['s'=>3415,'h'=>'56373c9ee675dba9'],
    'views/ops/cdoc_form.php' => ['s'=>3437,'h'=>'61145e220fe1cf1c'],
    'views/ops/cdocs_list.php' => ['s'=>2186,'h'=>'b94626083a19963e'],
    'views/ops/cform_record_form.php' => ['s'=>1484,'h'=>'f6218f03a0e9e05b'],
    'views/ops/cform_record_view.php' => ['s'=>1497,'h'=>'efaa80054139e073'],
    'views/ops/cform_records.php' => ['s'=>2976,'h'=>'9c1fe43984351952'],
    'views/ops/cforms_admin.php' => ['s'=>4593,'h'=>'88071f613b69f060'],
    'views/ops/change_password.php' => ['s'=>819,'h'=>'07a7c01d844eed39'],
    'views/ops/client_holds.php' => ['s'=>2877,'h'=>'3b40db29d53d153a'],
    'views/ops/cockpit_forms.php' => ['s'=>2066,'h'=>'6e3e25f0c6afa805'],
    'views/ops/cockpit_home.php' => ['s'=>6864,'h'=>'978d1eba5c974119'],
    'views/ops/cockpit_modules.php' => ['s'=>5449,'h'=>'a1e3647e2802cc69'],
    'views/ops/cockpit_profile.php' => ['s'=>3071,'h'=>'84f78f02c0bef441'],
    'views/ops/command_centre.php' => ['s'=>5231,'h'=>'be8e5e385e08143a'],
    'views/ops/comp_setup.php' => ['s'=>5249,'h'=>'9ee748c328fab799'],
    'views/ops/company.php' => ['s'=>6302,'h'=>'af23bb3d61ea191e'],
    'views/ops/competence.php' => ['s'=>15684,'h'=>'0158f2c7aabbdebd'],
    'views/ops/complaint_detail.php' => ['s'=>16443,'h'=>'ccb78feeceb225b2'],
    'views/ops/complaint_form.php' => ['s'=>5158,'h'=>'b692896b63a27389'],
    'views/ops/complaints.php' => ['s'=>6463,'h'=>'876ff390edca3c5d'],
    'views/ops/complaints_policy.php' => ['s'=>2574,'h'=>'2d77d01b6217f346'],
    'views/ops/compliance.php' => ['s'=>5629,'h'=>'0ed2e0057b547791'],
    'views/ops/conf_breach.php' => ['s'=>4483,'h'=>'0b18d4cb60be823c'],
    'views/ops/confidentiality.php' => ['s'=>11052,'h'=>'e1703216aa5d0738'],
    'views/ops/connect_analytics.php' => ['s'=>11183,'h'=>'10082989ff130ebf'],
    'views/ops/connect_bench.php' => ['s'=>10760,'h'=>'61a88885261a676f'],
    'views/ops/connect_capabilities.php' => ['s'=>5566,'h'=>'2ff076b5ea38c2bb'],
    'views/ops/connect_channels.php' => ['s'=>8554,'h'=>'1ce310de2a4a57e1'],
    'views/ops/connect_concierge.php' => ['s'=>8245,'h'=>'59099c8a4dbe8e50'],
    'views/ops/connect_front.php' => ['s'=>9981,'h'=>'196052913e44fe4b'],
    'views/ops/connect_identity.php' => ['s'=>5849,'h'=>'14b648589596fcad'],
    'views/ops/connect_join.php' => ['s'=>8192,'h'=>'9b05ca9a94b9b105'],
    'views/ops/connect_match_weights.php' => ['s'=>3856,'h'=>'14f2f37f5872848b'],
    'views/ops/connect_messages.php' => ['s'=>6019,'h'=>'5381f7ecfb56c87d'],
    'views/ops/connect_orgs.php' => ['s'=>4871,'h'=>'3a3949089aa483f0'],
    'views/ops/connect_passport_public.php' => ['s'=>6646,'h'=>'d3a171906f276469'],
    'views/ops/connect_passport_share.php' => ['s'=>3303,'h'=>'201da290922559b0'],
    'views/ops/connect_qualifications.php' => ['s'=>17690,'h'=>'f2245efebb17a36a'],
    'views/ops/connect_requirement.php' => ['s'=>48110,'h'=>'b3043134432a169c'],
    'views/ops/connect_requirements.php' => ['s'=>8322,'h'=>'c95760c33ba24574'],
    'views/ops/connect_source.php' => ['s'=>4918,'h'=>'a8882cf9e99ec24b'],
    'views/ops/connect_talent.php' => ['s'=>6868,'h'=>'9498ebf1bab76c69'],
    'views/ops/connect_taxonomy.php' => ['s'=>6471,'h'=>'9ff32e87638a2e5f'],
    'views/ops/connect_taxonomy_admin.php' => ['s'=>11160,'h'=>'1b219237df7dd86f'],
    'views/ops/connect_verify.php' => ['s'=>5029,'h'=>'f5e86444d6b668f3'],
    'views/ops/consents.php' => ['s'=>3636,'h'=>'135aebfdc257c26d'],
    'views/ops/contract_detail.php' => ['s'=>17580,'h'=>'ee86f8e379649b19'],
    'views/ops/contract_openings.php' => ['s'=>5171,'h'=>'5f2b2ca5bcae96d9'],
    'views/ops/contract_overrides.php' => ['s'=>8957,'h'=>'6d9de0085c1e6c37'],
    'views/ops/cost_reconciliation.php' => ['s'=>5649,'h'=>'5b7fa0d55a494a4b'],
    'views/ops/cost_run.php' => ['s'=>11414,'h'=>'5691c08e38b4e01c'],
    'views/ops/crm/approval_rule_form.php' => ['s'=>3857,'h'=>'5662b08eec4abd0e'],
    'views/ops/crm/approval_rule_list.php' => ['s'=>2506,'h'=>'6086b04020ffcea7'],
    'views/ops/crm/inquiry_form.php' => ['s'=>4637,'h'=>'5af6e49c375c49a0'],
    'views/ops/crm/inquiry_list.php' => ['s'=>4641,'h'=>'5917461194b40e2e'],
    'views/ops/crm/quote_detail.php' => ['s'=>61448,'h'=>'ff10b8023dc3decc'],
    'views/ops/crm/quote_external.php' => ['s'=>8932,'h'=>'b2bc432b39deb979'],
    'views/ops/crm/quote_form.php' => ['s'=>41886,'h'=>'e1fbb4b7d507a0fe'],
    'views/ops/crm/quote_list.php' => ['s'=>8038,'h'=>'36be301c9d7ffe37'],
    'views/ops/crm/reports.php' => ['s'=>3330,'h'=>'5645fbaf2ca88484'],
    'views/ops/crm/template_form.php' => ['s'=>4091,'h'=>'d85aff2b06d4b4c6'],
    'views/ops/crm/template_list.php' => ['s'=>5370,'h'=>'083649e5bf231d9c'],
    'views/ops/crm_dashboard.php' => ['s'=>13343,'h'=>'4272f40cf8e24183'],
    'views/ops/custom_fields.php' => ['s'=>5058,'h'=>'c4e1e1ce443dbc60'],
    'views/ops/customer360.php' => ['s'=>27179,'h'=>'414e38f8d2454d72'],
    'views/ops/data_control.php' => ['s'=>18461,'h'=>'2786ce2165d63373'],
    'views/ops/data_requests.php' => ['s'=>5004,'h'=>'9270ffd7da2c97df'],
    'views/ops/dedupe.php' => ['s'=>7190,'h'=>'1608f9878474d34d'],
    'views/ops/department_admin.php' => ['s'=>12173,'h'=>'b128a859a7310a0a'],
    'views/ops/departments.php' => ['s'=>5427,'h'=>'7d42e8fa2dabad62'],
    'views/ops/departures.php' => ['s'=>6145,'h'=>'d22b9da6974eb25c'],
    'views/ops/deputations.php' => ['s'=>10012,'h'=>'c986050943ce0eeb'],
    'views/ops/disclosure.php' => ['s'=>4496,'h'=>'2681013699047300'],
    'views/ops/doc_templates.php' => ['s'=>5529,'h'=>'8c94123ed592984e'],
    'views/ops/drule_detail.php' => ['s'=>2969,'h'=>'d861db08f6f1f1ac'],
    'views/ops/drule_form.php' => ['s'=>3100,'h'=>'cfec73b381d62447'],
    'views/ops/drules_list.php' => ['s'=>1809,'h'=>'dd03afe428e7cbfb'],
    'views/ops/entity360.php' => ['s'=>817,'h'=>'e3fafecf4c1bdcc7'],
    'views/ops/equipment_form.php' => ['s'=>11212,'h'=>'271d14e6ecb87f21'],
    'views/ops/equipment_list.php' => ['s'=>2770,'h'=>'155181d9e5f55818'],
    'views/ops/evidence_review.php' => ['s'=>10268,'h'=>'5681dcd46101eebe'],
    'views/ops/flow_gaps.php' => ['s'=>3363,'h'=>'48ceadbccf10fe16'],
    'views/ops/form_designer.php' => ['s'=>15499,'h'=>'0c60eeaec0d8f871'],
    'views/ops/hierarchy.php' => ['s'=>55996,'h'=>'0ed12718ecd77b34'],
    'views/ops/hiring_request.php' => ['s'=>17372,'h'=>'007df716749edddd'],
    'views/ops/hiring_request_list.php' => ['s'=>2365,'h'=>'8dfce095523d3af1'],
    'views/ops/hwpoints.php' => ['s'=>3338,'h'=>'679fdb6488397c4a'],
    'views/ops/idems/approval_rules.php' => ['s'=>6707,'h'=>'c2df5a10e140394e'],
    'views/ops/idems/approver_map.php' => ['s'=>3141,'h'=>'667d604d6e990e0e'],
    'views/ops/idems/audit.php' => ['s'=>10087,'h'=>'2d40607539c61006'],
    'views/ops/idems/autoform.php' => ['s'=>2050,'h'=>'f4cc7be726f0f7f2'],
    'views/ops/idems/builder.php' => ['s'=>36111,'h'=>'0a1d879529e97ddd'],
    'views/ops/idems/doc_detail.php' => ['s'=>65247,'h'=>'53c05a167b5e967c'],
    'views/ops/idems/doc_form.php' => ['s'=>13240,'h'=>'c0ac25568a5b2fd5'],
    'views/ops/idems/endorse_detail.php' => ['s'=>6984,'h'=>'afea639479d389dc'],
    'views/ops/idems/endorse_form.php' => ['s'=>6346,'h'=>'86fef287a45ac050'],
    'views/ops/idems/endorse_list.php' => ['s'=>3343,'h'=>'9d8b7216f4077e43'],
    'views/ops/idems/evidence.php' => ['s'=>7442,'h'=>'36523783a4c3b81a'],
    'views/ops/idems/expediting_projects.php' => ['s'=>7599,'h'=>'084a3e201887d26e'],
    'views/ops/idems/expediting_register.php' => ['s'=>5638,'h'=>'973013698187ff45'],
    'views/ops/idems/fill.php' => ['s'=>43934,'h'=>'20a4822fb4a1c2e3'],
    'views/ops/idems/form_from_template.php' => ['s'=>6068,'h'=>'e66aabd83f71990f'],
    'views/ops/idems/learning.php' => ['s'=>5604,'h'=>'89a8035384e352a9'],
    'views/ops/idems/my_signature.php' => ['s'=>2755,'h'=>'f57e0247a3ff7b58'],
    'views/ops/idems/numbering.php' => ['s'=>3320,'h'=>'89e6b7ae20d0d79b'],
    'views/ops/idems/phrases.php' => ['s'=>4110,'h'=>'d77fd023ae96dc6c'],
    'views/ops/idems/register.php' => ['s'=>4622,'h'=>'ef8f3c7dc46dbe30'],
    'views/ops/idems/release_register.php' => ['s'=>3581,'h'=>'9ae46e139d867e63'],
    'views/ops/idems/report_types.php' => ['s'=>9479,'h'=>'ee0ee51ab0ac83b8'],
    'views/ops/idems/review.php' => ['s'=>9565,'h'=>'9d03d9e0ef1b3efb'],
    'views/ops/idems/smart.php' => ['s'=>3500,'h'=>'0d8cd07c17f568d8'],
    'views/ops/idems/template_preview.php' => ['s'=>3949,'h'=>'d3fb4194e514e133'],
    'views/ops/idems/templates.php' => ['s'=>14397,'h'=>'d8e125fd19dc4301'],
    'views/ops/idems/vendor_detail.php' => ['s'=>25106,'h'=>'e5a059625921a50c'],
    'views/ops/idems/vendor_register.php' => ['s'=>5330,'h'=>'f64c0da7e0c65293'],
    'views/ops/idems/vet_review.php' => ['s'=>4732,'h'=>'e83148153149b493'],
    'views/ops/idems/vetting_checklist.php' => ['s'=>2968,'h'=>'e472035c88e4143f'],
    'views/ops/idems/writing.php' => ['s'=>3657,'h'=>'2e0d6d3d7dc9db62'],
    'views/ops/identity.php' => ['s'=>11188,'h'=>'9b18f607228919e8'],
    'views/ops/identity_access.php' => ['s'=>4240,'h'=>'b8ad133c94d262e0'],
    'views/ops/impartiality.php' => ['s'=>8922,'h'=>'69783bab2047a2e6'],
    'views/ops/incident_detail.php' => ['s'=>4636,'h'=>'a53ead3d767daee1'],
    'views/ops/incident_form.php' => ['s'=>5373,'h'=>'4efe80d38180fb14'],
    'views/ops/incidents.php' => ['s'=>2969,'h'=>'a8f2e53178bd649c'],
    'views/ops/industry.php' => ['s'=>4753,'h'=>'4570c1fa07664b54'],
    'views/ops/inspector_form.php' => ['s'=>23212,'h'=>'f47165cc6e52a813'],
    'views/ops/inspector_list.php' => ['s'=>2162,'h'=>'f6f7abf64150c70b'],
    'views/ops/inspector_profile.php' => ['s'=>7341,'h'=>'b2cd86e7959a880a'],
    'views/ops/integrations.php' => ['s'=>2285,'h'=>'ba2e5d07e0408e82'],
    'views/ops/invoice_detail.php' => ['s'=>21728,'h'=>'349bd6804aba4409'],
    'views/ops/invoice_form.php' => ['s'=>7186,'h'=>'f54a496202f68fe7'],
    'views/ops/invoice_print.php' => ['s'=>9333,'h'=>'821ed9c10083ae51'],
    'views/ops/invoices.php' => ['s'=>4104,'h'=>'79660bc91762be9f'],
    'views/ops/invoicing.php' => ['s'=>4458,'h'=>'a0a216540fa34c89'],
    'views/ops/issues.php' => ['s'=>5325,'h'=>'445463195ce9f4c4'],
    'views/ops/job_close.php' => ['s'=>6109,'h'=>'490c017ab59cb999'],
    'views/ops/job_detail.php' => ['s'=>88886,'h'=>'e705b1125771117a'],
    'views/ops/job_form.php' => ['s'=>58238,'h'=>'35edfd5b252af59a'],
    'views/ops/jobs.php' => ['s'=>7058,'h'=>'54c963c415ee7600'],
    'views/ops/lead_convert.php' => ['s'=>3141,'h'=>'14311346040445c5'],
    'views/ops/lead_detail.php' => ['s'=>26694,'h'=>'07c08f46bb848617'],
    'views/ops/lead_form.php' => ['s'=>13161,'h'=>'950ed3382cfc4101'],
    'views/ops/leads.php' => ['s'=>9740,'h'=>'33f468503ec7e9ab'],
    'views/ops/ledger.php' => ['s'=>4499,'h'=>'429efedfe0ba6216'],
    'views/ops/licence.php' => ['s'=>9595,'h'=>'fd45ac3765e46551'],
    'views/ops/licence_issue.php' => ['s'=>12495,'h'=>'5d30b99e548474d9'],
    'views/ops/licence_issued.php' => ['s'=>2319,'h'=>'197c336d23a9a3b4'],
    'views/ops/lookup_values.php' => ['s'=>7681,'h'=>'0daecb1919a3c9ed'],
    'views/ops/lookups.php' => ['s'=>7956,'h'=>'b4bc57423fe118f5'],
    'views/ops/master_form.php' => ['s'=>2642,'h'=>'35e45d9d09cd662b'],
    'views/ops/master_list.php' => ['s'=>2136,'h'=>'1d1942663bce97ab'],
    'views/ops/masters.php' => ['s'=>7447,'h'=>'4f78bc8efa5f00e9'],
    'views/ops/method_detail.php' => ['s'=>4270,'h'=>'1c92ae6509bbc815'],
    'views/ops/method_form.php' => ['s'=>3115,'h'=>'7e32fbfc5357533b'],
    'views/ops/methods_list.php' => ['s'=>2146,'h'=>'a038dbaacfbefdbc'],
    'views/ops/mis.php' => ['s'=>15856,'h'=>'4b0c8b74787ca055'],
    'views/ops/mkt_escrow.php' => ['s'=>8328,'h'=>'1879256ad30ed04e'],
    'views/ops/mkt_gates.php' => ['s'=>2077,'h'=>'d8b8e6d1787792aa'],
    'views/ops/mkt_ledger.php' => ['s'=>5424,'h'=>'3893ea528c0064c9'],
    'views/ops/mkt_plans.php' => ['s'=>18544,'h'=>'93088f2c39d2ce2c'],
    'views/ops/mkt_rules.php' => ['s'=>8994,'h'=>'91a1cd66dcd297fb'],
    'views/ops/module_locked.php' => ['s'=>2419,'h'=>'82de67be8483b36c'],
    'views/ops/my_approvals.php' => ['s'=>7411,'h'=>'c7d93a57f230b23b'],
    'views/ops/my_jobs.php' => ['s'=>15228,'h'=>'bb2e2bb4879b97c4'],
    'views/ops/my_work.php' => ['s'=>5894,'h'=>'bd095a0094c135e3'],
    'views/ops/ncr_detail.php' => ['s'=>9032,'h'=>'827310b0993e1532'],
    'views/ops/ncr_form.php' => ['s'=>4184,'h'=>'36b0d6a978bb32fb'],
    'views/ops/ncr_list.php' => ['s'=>2333,'h'=>'90f38b8b7a4abc50'],
    'views/ops/notifications.php' => ['s'=>4080,'h'=>'67b58946ad7d9e12'],
    'views/ops/office_finance.php' => ['s'=>10469,'h'=>'98394b1ad152f803'],
    'views/ops/operations_home.php' => ['s'=>14308,'h'=>'a89c0f3ebb4bb7ae'],
    'views/ops/opportunities.php' => ['s'=>7058,'h'=>'1523096121c96ace'],
    'views/ops/opportunity_detail.php' => ['s'=>32993,'h'=>'f0bdd18bf989d246'],
    'views/ops/opportunity_form.php' => ['s'=>6362,'h'=>'3cfae34282b36f58'],
    'views/ops/ops_desk.php' => ['s'=>6017,'h'=>'34c5282b6b15da06'],
    'views/ops/owner_home.php' => ['s'=>6289,'h'=>'b417b9c31ab009a5'],
    'views/ops/partner_import.php' => ['s'=>6489,'h'=>'f8c6211f9a1fd6a1'],
    'views/ops/person_erase.php' => ['s'=>2880,'h'=>'4421b7a3054d3081'],
    'views/ops/pipeline_edit.php' => ['s'=>7294,'h'=>'5f2cd07b3494554f'],
    'views/ops/pipelines.php' => ['s'=>4435,'h'=>'7af67ecc09eedeca'],
    'views/ops/portal_user_perms.php' => ['s'=>4594,'h'=>'6559eebd0116ac64'],
    'views/ops/portal_users.php' => ['s'=>12939,'h'=>'cc010a76e8beaa69'],
    'views/ops/positions.php' => ['s'=>6592,'h'=>'fa14eeee4bf5cc14'],
    'views/ops/positions_import.php' => ['s'=>7558,'h'=>'721b96bcf6cb0ce9'],
    'views/ops/positions_org.php' => ['s'=>4861,'h'=>'5cb74e99349f94d3'],
    'views/ops/preflight.php' => ['s'=>2904,'h'=>'d0ccc1a7191bd515'],
    'views/ops/preorder_checklist.php' => ['s'=>2022,'h'=>'3916a9e3e6d21839'],
    'views/ops/pricing_usage.php' => ['s'=>5066,'h'=>'6ef25584f0918ef8'],
    'views/ops/privacy.php' => ['s'=>1537,'h'=>'140a873cc030bd6e'],
    'views/ops/product_package.php' => ['s'=>4844,'h'=>'78c94172b82019bc'],
    'views/ops/profitability_detail.php' => ['s'=>10285,'h'=>'e1e400a2318fd76e'],
    'views/ops/profitability_list.php' => ['s'=>9655,'h'=>'8fed2c9925d511dc'],
    'views/ops/project_costing.php' => ['s'=>23384,'h'=>'8cca9a950dcd5a01'],
    'views/ops/project_costing_print.php' => ['s'=>6381,'h'=>'c68917f4350182a1'],
    'views/ops/project_costings.php' => ['s'=>3940,'h'=>'8ff6544dc0a9c748'],
    'views/ops/raise_call.php' => ['s'=>4894,'h'=>'65470026c81645ab'],
    'views/ops/rating_disputes.php' => ['s'=>5629,'h'=>'b25ddd44d36a6435'],
    'views/ops/ratings.php' => ['s'=>5158,'h'=>'a4f584f6df1690b3'],
    'views/ops/receipt_detail.php' => ['s'=>6447,'h'=>'22835245dde0758a'],
    'views/ops/receipt_form.php' => ['s'=>3653,'h'=>'a81c069f1be54463'],
    'views/ops/receipts.php' => ['s'=>3431,'h'=>'92fe379c6b582e34'],
    'views/ops/receivables.php' => ['s'=>5717,'h'=>'5f0d1ef9dc366ce7'],
    'views/ops/recruit_pipelines.php' => ['s'=>11827,'h'=>'c4c1d8ad49872179'],
    'views/ops/recruitment_cc.php' => ['s'=>37376,'h'=>'798b3ac5d4958c13'],
    'views/ops/recruitment_home.php' => ['s'=>14367,'h'=>'c1b7927e08f8e549'],
    'views/ops/recurring.php' => ['s'=>4054,'h'=>'77137957c72ee106'],
    'views/ops/reimbursable_dedup.php' => ['s'=>5209,'h'=>'95ba7f96fb4ccc26'],
    'views/ops/report_reviews.php' => ['s'=>5202,'h'=>'278f5b6a228a0349'],
    'views/ops/reports.php' => ['s'=>15068,'h'=>'3be87a0f606b8d82'],
    'views/ops/requisition_detail.php' => ['s'=>27086,'h'=>'59598e7d2be42a2b'],
    'views/ops/requisition_form.php' => ['s'=>39744,'h'=>'6d4c03796828af66'],
    'views/ops/requisition_list.php' => ['s'=>2651,'h'=>'c15db7b60de5f2b7'],
    'views/ops/reset_data.php' => ['s'=>3978,'h'=>'227a141a70167b40'],
    'views/ops/retention.php' => ['s'=>3293,'h'=>'04f92df980490a2e'],
    'views/ops/revenue_reconciliation.php' => ['s'=>5710,'h'=>'cbaf776aa30e638d'],
    'views/ops/review_detail.php' => ['s'=>9007,'h'=>'df426453d0470775'],
    'views/ops/reviews_list.php' => ['s'=>3792,'h'=>'60a66217ef8d60f6'],
    'views/ops/risk_detail.php' => ['s'=>2913,'h'=>'e78197af92175be9'],
    'views/ops/risk_form.php' => ['s'=>3267,'h'=>'1585b5d51448d327'],
    'views/ops/risks_list.php' => ['s'=>2313,'h'=>'59a705303f6f9412'],
    'views/ops/role_workspaces.php' => ['s'=>4115,'h'=>'148d8d7d88d6a6f1'],
    'views/ops/saas_companies.php' => ['s'=>24209,'h'=>'dcdbac6621941dd3'],
    'views/ops/sample_detail.php' => ['s'=>4435,'h'=>'a541cf7b8cacd076'],
    'views/ops/sample_form.php' => ['s'=>3984,'h'=>'4b9a12de7f70a5bf'],
    'views/ops/samples_list.php' => ['s'=>2276,'h'=>'4857db4853a8eb93'],
    'views/ops/satisfaction_detail.php' => ['s'=>6634,'h'=>'dfd79c7e933d995e'],
    'views/ops/satisfaction_form.php' => ['s'=>1844,'h'=>'5fc49ac09e7af1b9'],
    'views/ops/satisfaction_list.php' => ['s'=>3892,'h'=>'55daf8bce1bc6fc9'],
    'views/ops/sbu_pl.php' => ['s'=>12044,'h'=>'99176596c74c73a5'],
    'views/ops/schedule_board.php' => ['s'=>10243,'h'=>'21eb482bb6e01f15'],
    'views/ops/search.php' => ['s'=>4980,'h'=>'83f6410deba68358'],
    'views/ops/service_formats.php' => ['s'=>3036,'h'=>'ebbdc8d0a4949083'],
    'views/ops/service_scope.php' => ['s'=>8698,'h'=>'0199104c8ce5aefb'],
    'views/ops/settings.php' => ['s'=>69348,'h'=>'307c6d1ea9cf9056'],
    'views/ops/setup.php' => ['s'=>4814,'h'=>'2b39de10efe5b331'],
    'views/ops/site_docs.php' => ['s'=>5044,'h'=>'9e876a99f63d2805'],
    'views/ops/sla_targets.php' => ['s'=>2740,'h'=>'b96c71ffc40bd861'],
    'views/ops/sso.php' => ['s'=>5477,'h'=>'1ff5f650e95dd9a0'],
    'views/ops/stage_gates.php' => ['s'=>10388,'h'=>'3112a9a9bc40b7c4'],
    'views/ops/subscription.php' => ['s'=>5663,'h'=>'2fff730061f6df07'],
    'views/ops/super_admin.php' => ['s'=>25600,'h'=>'f4d5a83d44fb7a8b'],
    'views/ops/system_status.php' => ['s'=>2464,'h'=>'c1ae3435670a8195'],
    'views/ops/tally_export.php' => ['s'=>12826,'h'=>'140a438799127c55'],
    'views/ops/tapi.php' => ['s'=>1062,'h'=>'1b0edb54124461ba'],
    'views/ops/tapi_alerts.php' => ['s'=>3701,'h'=>'5d9b19d9c99ce3ce'],
    'views/ops/tapi_drill.php' => ['s'=>2550,'h'=>'f609e13e868fe7f5'],
    'views/ops/tapi_kpi_edit.php' => ['s'=>4068,'h'=>'a722d80423ea0f82'],
    'views/ops/tapi_kpis.php' => ['s'=>1954,'h'=>'3c5a106e90551269'],
    'views/ops/tapi_quality.php' => ['s'=>2013,'h'=>'10a1d081a2bbb671'],
    'views/ops/tapi_review.php' => ['s'=>3152,'h'=>'bacff987907ae50a'],
    'views/ops/tapi_scorecard.php' => ['s'=>2279,'h'=>'b5aec7c0907a3c2e'],
    'views/ops/tapi_snapshot.php' => ['s'=>2902,'h'=>'d1a0ef0be3421c2f'],
    'views/ops/tasks.php' => ['s'=>4329,'h'=>'0c2ffedf67f10633'],
    'views/ops/tenants.php' => ['s'=>20035,'h'=>'7a5f476af3133e93'],
    'views/ops/terminology.php' => ['s'=>4501,'h'=>'e6913921361f0d9c'],
    'views/ops/timesheet.php' => ['s'=>6023,'h'=>'6bb468da2014ba37'],
    'views/ops/to_bill.php' => ['s'=>7114,'h'=>'6fb7607ae9343044'],
    'views/ops/trace.php' => ['s'=>2789,'h'=>'97077c528b9d3ea3'],
    'views/ops/trace_thread.php' => ['s'=>4700,'h'=>'bbcce6ab3f5dedda'],
    'views/ops/two_factor.php' => ['s'=>7555,'h'=>'93b61f4ae4310265'],
    'views/ops/user_form.php' => ['s'=>37930,'h'=>'e0148fce95febac9'],
    'views/ops/users.php' => ['s'=>7431,'h'=>'9eab33a56cf5769d'],
    'views/ops/vendor.php' => ['s'=>5170,'h'=>'64920720e59c0caa'],
    'views/ops/vendor_users.php' => ['s'=>8944,'h'=>'772219bec83410a7'],
    'views/ops/verify.php' => ['s'=>8818,'h'=>'985a249a4ed58388'],
    'views/ops/voucher_detail.php' => ['s'=>28550,'h'=>'cb9eb6dbc9482f23'],
    'views/ops/voucher_list.php' => ['s'=>4388,'h'=>'e18c3d811b6157f3'],
    'views/ops/voucher_print.php' => ['s'=>5478,'h'=>'81b9eb2a01ced931'],
    'views/ops/welcome.php' => ['s'=>2992,'h'=>'9b2e2982b431759f'],
    'views/ops/work_norms.php' => ['s'=>3390,'h'=>'dd26779f61c4b1eb'],
    'views/po_detail.php' => ['s'=>9773,'h'=>'0a185197ec0e6cc6'],
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
    'views/portal/voucher.php' => ['s'=>13180,'h'=>'7af5e15b0c36db95'],
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
