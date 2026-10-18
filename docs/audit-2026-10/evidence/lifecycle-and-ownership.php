<?php
/**
 * EXAACT audit — three inventories the remaining deliverables need.
 *
 *  A. LIFECYCLES (D3)  every status constant the code defines per object, so
 *                      docs/03-object-lifecycles.md can be checked against the
 *                      code rather than trusted.
 *  B. OWNERSHIP (D2)   every table in the live schema, mapped to the module
 *                      that owns it by name, with the ones nothing claims.
 *  C. KPI SURFACE (D7) every function that looks like it computes a headline
 *                      number, so each can be traced to its source.
 *
 * Read-only. Boots the app against a throwaway database via the test harness.
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

echo "================ A. STATUS SETS DEFINED IN CODE (D3) ================\n";
//  Status vocabularies are declared as constants ending in _STATUS / _STATUSES
//  or as *_STATUS maps. Reflection over declared constants beats grepping,
//  because it sees what the application actually loaded.
$consts = get_defined_constants(true)['user'] ?? [];
$sets = [];
foreach ($consts as $name => $val) {
    if (!is_array($val)) continue;
    if (!preg_match('/(STATUS|STATE|STAGE|LIFECYCLE)(ES)?$/', $name)) continue;
    $keys = array_keys($val);
    // A status map is string-keyed (CODE => label). Skip positional lists.
    if (!$keys || is_int($keys[0])) $keys = array_values($val);
    $sets[$name] = array_filter(array_map(fn($k) => is_string($k) ? $k : null, $keys));
}
ksort($sets);
foreach ($sets as $n => $v)
    printf("%-28s %2d  %s\n", $n, count($v), implode(' · ', array_slice($v, 0, 12)));
echo "status vocabularies found: " . count($sets) . "\n";

echo "\n================ B. TABLES AND WHO OWNS THEM (D2) ================\n";
$tables = [];
foreach (ops_all("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $r)
    $tables[] = $r['name'];
echo "tables in the live schema: " . count($tables) . "\n";

//  Ownership by name prefix. This is the honest, reproducible 80% — a table
//  whose name begins with a module's own vocabulary belongs to it. What matters
//  for the audit is the REMAINDER: tables nothing claims, which is where
//  duplicate and orphan entities hide.
//  CORRECTED 2026-10-18. The first version of this list omitted 'quotation' and
//  'nonconform', so `quotations` (the quote header — there is NO `quotes`
//  table) and `nonconformities` (the NCR header — there is no `ncr` table) fell
//  into the unclaimed pile and read like duplicate entities. They are not.
//  Spot-checking two of the unclaimed tables is what caught it, which is why
//  the unclaimed list below is published as a READING LIST and never as a
//  finding: an unclaimed table means this map does not know the word, not that
//  the table is an orphan.
$own = [
    'Sales'        => ['leads','lead_','inquir','quote','quotation','crm_','opportunit','pipeline','campaign','followup','ads_'],
    'Operations'   => ['calls','call_','jobs','job_','voucher','deputation','dep_','attend','schedule','availability','expense','bill_','reconcile','timesheet','rating','site_visit','assignment_','work_norms','travel_modes','engagement','recurring_services','holidays','install_beats'],
    'Reporting'    => ['idems','document','doc_','report','release_','phrase','tech_','endorse','expedit','irn','form_','evidence_','service_report'],
    'Quality'      => ['ncr','nonconform','capa','complaint','audit_','internal_audit','management_review','mgmt_review','mr_','equipment','calibrat','competence','impartial','confidential','sample','hold_','hw_point','departure','issue','risk','disclosure','retention','satisfaction','data_control','data_check','data_consent','data_request','method','drule','cdoc','controlled_docs','witness_','assessment_','inspection_criteria','security_incident','sw_validations','authorisations','credential'],
    'Money'        => ['invoice','receipt','credit_','ledger','tally','overhead','cost_','profit','revenue','billable','payment','contract','boss_','project_costing','subcon','fee_','billing_','books_','analytics_periods','po_line'],
    'Recruitment'  => ['hiring','candidate','interview','offer','requisition','recruit','onboard','joiner','leaver','agencies','qualification','positions','scorecard','salary_','comp_','hr_','back_office'],
    'Directory'    => ['business_partner','partner_','client','vendor','party','address','contact','master','lookup','office','department','sbu','company_capabilities','service_catalog','service_dependencies','service_scope','inspectors','inspector_'],
    'People/Access'=> ['users','user_','role','cx_role','perm','access','delegation','signature','hierarch','org_','person_','login_attempts','password_resets','sso_','saas_login','stage_gate','approval_delegations'],
    'Platform'     => ['setting','tenant','saas_tenant','licence','license','feature','ai_','email_','notif','audit_log','session','custom_','cform','terminolog','backup','migration','plan_','pricing','usage','kpi_','schema_guards','system_failures','integration_outbox','decision_rules','learned_suggestions','activities','asset_','install','sla_targets','release_rules','qap_scope'],
    'Marketplace'  => ['connect_','marketplace','portal_','cx_','pro_','passport','mkt_'],
];
$hit = []; $claimed = [];
foreach ($tables as $t) {
    foreach ($own as $mod => $prefixes) {
        foreach ($prefixes as $p) {
            if (strncmp($t, $p, strlen($p)) === 0) { $hit[$mod][] = $t; $claimed[$t] = $mod; break 2; }
        }
    }
}
foreach ($own as $mod => $_) printf("  %-14s %3d tables\n", $mod, count($hit[$mod] ?? []));
$orphans = array_values(array_diff($tables, array_keys($claimed)));
echo "  " . str_pad('UNCLAIMED', 14) . " " . str_pad((string)count($orphans), 3) . " tables\n";
echo "\nunclaimed tables (the ones worth reading — duplicates and orphans hide here):\n";
foreach (array_chunk($orphans, 6) as $row) echo "  " . implode(', ', $row) . "\n";

echo "\n================ C. ROW COUNTS, FOR SCALE (D6) ================\n";
$big = [];
foreach ($tables as $t) {
    try { $big[$t] = (int) ops_val("SELECT COUNT(*) FROM \"$t\""); } catch (Throwable $e) { $big[$t] = -1; }
}
arsort($big);
$empty = count(array_filter($big, fn($n) => $n === 0));
echo "tables with rows: " . count(array_filter($big, fn($n) => $n > 0))
   . " · empty: $empty · unreadable: " . count(array_filter($big, fn($n) => $n < 0)) . "\n";
echo "largest: ";
$i = 0; foreach ($big as $t => $n) { if ($n <= 0 || $i++ >= 10) continue; echo "$t($n) "; }
echo "\n";

echo "\n================ D. HEADLINE-NUMBER FUNCTIONS (D7) ================\n";
//  A function whose name says it counts or sums something is a candidate KPI.
//  Each one printed here has to be traceable to an authoritative source in D7.
$fns = get_defined_functions()['user'];
$kpi = array_values(array_filter($fns, fn($f) =>
    preg_match('/(_counts?|_total|_sum|_kpi|_stats?|_summary|_readiness|_pending_count|_ageing|_aging)$/', $f)));
sort($kpi);
echo count($kpi) . " candidate metric functions:\n";
foreach (array_chunk($kpi, 4) as $row) echo "  " . implode(', ', $row) . "\n";
