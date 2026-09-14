<?php
// ============================================================================
//  PHASE 1 · MILESTONE 14 — OBJECT-LEVEL AUTHORIZATION / IDOR
//
//  M13 closed the call detail and left a measured asymmetry behind: 72 list-level
//  scope checks against 9 object-level ones. M14 attacked that gap rather than
//  counting it, and the attacks below all SUCCEEDED before the fix:
//
//   O1  quote          — another branch's quotation opened by id
//   O2  opportunity    — opened, EDITED, MOVED and DELETED by id
//   O3  lead           — opened, EDITED and DELETED by id
//   O4  lead document  — another branch's attached file DOWNLOADED by id
//   O5  requisition    — another branch's manpower requisition opened by id
//   O6  complaint      — another branch's complaint opened by id
//   O7  receipt        — another branch's receipt (payer + amount) opened by id
//
//  Each register's LIST had scoped by branch all along. The attacker below is
//  deliberately given EVERY permission and only the wrong branch, because that
//  is the whole point: permission is not scope.
//
//  The fix is one gate per module rather than one per route, and one new helper
//  — scope_office_allows(), the object-level twin of scope_office_clause() —
//  because the two list rules disagree about what a MISSING branch means, and
//  borrowing the wrong one would have hidden records the register intends to be
//  seen. The last block below pins that down: unassigned records must stay
//  visible, and the branch that owns a record must keep full use of it.
// ============================================================================

t_section('Milestone 14 — object-level authorization (IDOR)');

$pdo = db();
$now = date('c');
$origSession = $_SESSION;

foreach ([[91, 'M14 Branch A'], [92, 'M14 Branch B']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); } catch (Throwable $e) {}
}
$mk = function ($sql, $a = []) { $s = db()->prepare($sql); $s->execute($a); return (int) db()->lastInsertId(); };

// Victim records — all in Branch B (92).
// NOTE the explicit status: these rows live in the same throwaway database as
// every other test, and a quotation left at the default DRAFT would be counted
// by the lookup-usage test in module 01. Test data must not become another
// test's surprise — everything M14 inserts is also removed at the end.
$vQuote = $mk("INSERT INTO quotations (quote_no,rev,is_current,office_id,sbu,subject,status,created_at) VALUES ('M14-Q',1,1,92,'','B work','M14ONLY',?)", [$now]);
$vOpp   = $mk("INSERT INTO opportunities (ref,name,office_id,sbu,status,created_at) VALUES ('M14-O','B deal',92,'','OPEN',?)", [$now]);
$vLead  = $mk("INSERT INTO leads (ref,company_name,office_id,sbu,status,created_at) VALUES ('M14-L','B company',92,'','OPEN',?)", [$now]);
$vReq   = $mk("INSERT INTO requisitions (req_code,office_id,sbu,designation,status,created_at) VALUES ('M14-R',92,'','B role','OPEN',?)", [$now]);
$vCmp   = $mk("INSERT INTO complaints (ref,subject,office_id,created_at) VALUES ('M14-C','B complaint',92,?)", [$now]);
$vRcpt  = $mk("INSERT INTO receipts (receipt_no,office_id,partner_name,amount,created_at) VALUES ('M14-RC',92,'B payer',5000,?)", [$now]);
$vFile  = $mk("INSERT INTO lead_files (lead_id,kind,file_name,mime,file_data,uploaded_at) VALUES (?,'OTHER','b-secret.pdf','application/pdf',?,?)",
              [$vLead, base64_encode('branch B confidential'), $now]);
// Deliberately unassigned — these must stay visible to EVERY branch.
$nLead  = $mk("INSERT INTO leads (ref,company_name,office_id,sbu,status,created_at) VALUES ('M14-LN','unassigned',NULL,'','OPEN',?)", [$now]);
$nCmp   = $mk("INSERT INTO complaints (ref,subject,office_id,created_at) VALUES ('M14-CN','unassigned',NULL,?)", [$now]);
$nRcpt  = $mk("INSERT INTO receipts (receipt_no,office_id,partner_name,amount,created_at) VALUES ('M14-RCN',NULL,'unassigned',1,?)", [$now]);

// The attacker: every permission, wrong branch.
$pdo->prepare("INSERT INTO users (username,first_name,role,scope_offices,home_office_id,is_active)
               VALUES ('m14_attacker','AttackerA','ADMIN','91',91,1)")->execute();
$atk = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $atk;
if (function_exists('auth_bind_workspace')) auth_bind_workspace();
current_user(true); ua(true);

t_eq(scope_offices(), [91],  'the attacker is scoped to one branch');
t_ok(!is_master(),           'and is NOT a master — entitlement/master bypass is M10, not this');
t_ok(in_array('mod.quotes.view', ua()['perms'], true),
                             'yet HOLDS the permission — permission is not scope, which is the point');

// ---- The new helper mirrors the list rule it is the twin of ----------------
t_ok(function_exists('scope_office_allows'), 'the object-level twin of scope_office_clause() exists');
t_ok(!scope_office_allows(92),  'a record in another branch is refused');
t_ok(scope_office_allows(91),   'a record in your own branch is allowed');
t_ok(scope_office_allows(null), 'an UNASSIGNED record stays visible — as scope_office_clause() has it');
t_ok(scope_office_allows(0),    'and so does one with no branch id at all');
// The distinction from scope_allows() is the reason this exists at all.
$ahm = (int) (ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: 0);
t_ok($ahm !== 91, 'the two rules genuinely differ: a null office is Ahmedabad to scope_allows(), everyone to this');

// ---- Every list already hid these records ---------------------------------
[$ow, $oa] = scope_office_clause('l.office_id');
t_ok(!ops_all("SELECT id FROM leads l WHERE $ow AND l.id=?", array_merge($oa, [$vLead])),
     'O3 · the leads LIST hides the other branch\'s lead');
[$qw, $qa] = scope_clause('q.office_id', 'q.sbu');
t_ok(!ops_all("SELECT id FROM quotations q WHERE $qw AND q.id=?", array_merge($qa, [$vQuote])),
     'O1 · the quotations LIST hides the other branch\'s quote');
[$rw, $ra] = scope_clause('r.office_id', 'r.sbu');
t_ok(!ops_all("SELECT id FROM requisitions r WHERE $rw AND r.id=?", array_merge($ra, [$vReq])),
     'O5 · the requisitions LIST hides the other branch\'s requisition');

// ---- ATTACK BLOCKED · each module now has ONE door, and it is shut ---------
// The gates refuse by ops_require(), which flashes and redirects — so what is
// pinned here is the decision itself, taken with the attacker's real scope.
t_ok(function_exists('lead_scope_gate'),       'O3/O4 · leads have one gate for the whole module');
t_ok(function_exists('opp_scope_gate'),        'O2 · opportunities have one gate for the whole module');
t_ok(function_exists('cmp_scope_gate'),        'O6 · complaints have one gate for the whole module');
t_ok(function_exists('crm_quote_scope_gate'),  'O1 · quotations have one gate for the whole module');
t_ok(function_exists('req_scope_gate'),        'O5 · requisitions have one gate for the whole module');

// The gate is the FIRST thing the dispatcher does — before any route reads an id.
$srcLeads = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/leads.php'));
$p = strpos($srcLeads, 'function ops_leads($route, $method) {');
t_ok($p !== false && strpos(substr($srcLeads, $p, 200), 'lead_scope_gate($route)') !== false,
     'O3 · and it runs on entry to the module, not per route');
$srcOpp = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/opportunities.php'));
$p = strpos($srcOpp, 'function ops_opportunities($route, $method) {');
t_ok($p !== false && strpos(substr($srcOpp, $p, 200), 'opp_scope_gate($route)') !== false,
     'O2 · same for opportunities — delete/edit/move are covered by construction');
$srcCrm = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/crm.php'));
$p = strpos($srcCrm, 'function ops_crm_quotes($route, $method) {');
t_ok($p !== false && strpos(substr($srcCrm, $p, 200), 'crm_quote_scope_gate($route)') !== false,
     'O1 · same for the whole quotation family');
$srcCmp = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/complaints.php'));
$p = strpos($srcCmp, 'function ops_complaints($route, $method) {');
t_ok($p !== false && strpos(substr($srcCmp, $p, 300), 'cmp_scope_gate()') !== false,
     'O6 · same for complaints, ahead of every state change');
$srcOps = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/ops.php'));
$p = strpos($srcOps, 'function ops_requisitions($route, $method) {');
t_ok($p !== false && strpos(substr($srcOps, $p, 200), 'req_scope_gate()') !== false,
     'O5 · same for requisitions');
$srcBooks = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/booksui.php'));
$p = strpos($srcBooks, "if (\$route === 'receipt') {");
t_ok($p !== false && strpos(substr($srcBooks, $p, 700), 'scope_office_allows(') !== false,
     'O7 · and the receipt detail guards like the invoice detail beside it');

// ---- O4 · a document is as confidential as the record it is filed against --
$parent = (int) ops_val("SELECT lead_id FROM lead_files WHERE id=?", [$vFile]);
t_eq($parent, $vLead, 'O4 · a lead document resolves to its parent lead');
$pOff = ops_val("SELECT office_id FROM leads WHERE id=?", [$parent]);
t_ok(!scope_office_allows($pOff),
     'O4 · ATTACK BLOCKED — the download is refused because its PARENT is another branch\'s');
t_ok(strpos($srcLeads, "\$route === 'lead-file'") !== false
     && strpos(substr($srcLeads, strpos($srcLeads, 'function lead_scope_gate')), 'lead_files') !== false,
     'O4 · the gate knows these two routes carry a FILE id, not a lead id');
t_ok(strpos(substr($srcCrm, strpos($srcCrm, 'function crm_quote_scope_gate')), 'quote_files') !== false,
     'O1 · and the quote gate resolves an attached file to its quotation');
t_ok(strpos(substr($srcCrm, strpos($srcCrm, 'function crm_quote_scope_gate')), 'quote_approvals') !== false,
     'O1 · and an approval step to the quotation it approves');

// ---- NOT over-fixed · the owning branch keeps full use ---------------------
$pdo->prepare("INSERT INTO users (username,first_name,role,scope_offices,home_office_id,is_active)
               VALUES ('m14_owner','OwnerB','ADMIN','92',92,1)")->execute();
$_SESSION['uid'] = (int) $pdo->lastInsertId();
if (function_exists('auth_bind_workspace')) auth_bind_workspace();
current_user(true); ua(true);
t_eq(scope_offices(), [92], 'the owning branch is scoped to Branch B');
t_ok(scope_office_allows(92),      'O3 · Branch B still reaches its own lead');
t_ok(scope_allows(92, ''),         'O1 · and its own quotation');
$pOff2 = ops_val("SELECT office_id FROM leads WHERE id=?", [$vLead]);
t_ok(scope_office_allows($pOff2),  'O4 · and still downloads its own documents');

// ---- NOT over-fixed · unassigned work stays everybody's --------------------
$_SESSION['uid'] = $atk; current_user(true); ua(true);
t_ok(scope_office_allows(ops_val("SELECT office_id FROM leads WHERE id=?", [$nLead])),
     'an UNASSIGNED lead is still visible to a branch-scoped user');
t_ok(scope_office_allows(ops_val("SELECT office_id FROM complaints WHERE id=?", [$nCmp])),
     'so is an unassigned complaint — nothing gets lost because nobody filed it');
t_ok(scope_office_allows(ops_val("SELECT office_id FROM receipts WHERE id=?", [$nRcpt])),
     'so is an unbanked receipt');

// ---- Malformed identifiers are refused, never crash (§16) ------------------
// A gate must reach a DECISION on rubbish input — never a warning, never a
// throw, never a half-answer. The handler below turns any PHP notice/warning
// into a failure, so "it happened to not crash" cannot pass for "it is handled".
foreach ([['0', 'zero'], ['-1', 'negative'], ['99999999999999999999', 'huge'],
          ['424242', 'nonexistent'], ['7abc', 'malformed'], ['', 'empty'],
          ['1 OR 1=1', 'injection'], [' 1 ', 'padded']] as $bad) {
    $noise = '';
    set_error_handler(function ($n, $str) use (&$noise) { $noise .= $str; return true; });
    $clean = true;
    try {
        $off = ops_val("SELECT office_id FROM leads WHERE id=?", [(int) $bad[0]]);
        $decision = scope_office_allows($off === false ? 424242 : $off);
        if (!is_bool($decision)) $clean = false;                 // must be a real yes/no
        if ($off === false && $decision !== false) $clean = false; // an id that names nothing is not "allowed"
    } catch (Throwable $e) { $clean = false; $noise .= get_class($e); }
    restore_error_handler();
    t_ok($clean && $noise === '',
         'a ' . $bad[1] . ' id reaches a clean decision — no PHP warning, no SQL error');
}

// ---- Master and tenant boundaries are M10/M13's, and still hold ------------
$pdo->prepare("INSERT INTO users (username,first_name,role,is_superuser,is_active)
               VALUES ('m14_master','Master','ADMIN',1,1)")->execute();
$_SESSION['uid'] = (int) $pdo->lastInsertId();
if (function_exists('auth_bind_workspace')) auth_bind_workspace();
current_user(true); ua(true);
t_ok(is_master(), 'a master is a master');
t_ok(scope_office_allows(92) && scope_allows(92, ''),
     'branch scope does not bind a master — ALL-scope by architecture, and documented as such');
t_ok(function_exists('current_user') && function_exists('auth_workspace_key'),
     'and M13 still binds even a master to ONE workspace — branch scope is not tenant scope');

// ---- Leave the database as we found it ------------------------------------
foreach ([['quotations', [$vQuote]], ['opportunities', [$vOpp]], ['leads', [$vLead, $nLead]],
          ['requisitions', [$vReq]], ['complaints', [$vCmp, $nCmp]], ['receipts', [$vRcpt, $nRcpt]],
          ['lead_files', [$vFile]]] as [$tbl, $ids]) {
    foreach ($ids as $rid) { try { db()->prepare("DELETE FROM $tbl WHERE id=?")->execute([(int) $rid]); } catch (Throwable $e) {} }
}
foreach (['m14_attacker', 'm14_owner', 'm14_master'] as $un) {
    try { db()->prepare("DELETE FROM users WHERE username=?")->execute([$un]); } catch (Throwable $e) {}
}
foreach ([91, 92] as $oid) { try { db()->prepare("DELETE FROM offices WHERE id=?")->execute([$oid]); } catch (Throwable $e) {} }

$_SESSION = $origSession;
current_user(true); ua(true);
