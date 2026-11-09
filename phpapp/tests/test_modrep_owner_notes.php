<?php
// MOD-REP — the owner's notes of 9 Nov 2026, checked one by one:
//   report types / IRN rules / phrase library reachable from Admin; Governance
//   moved out of Reporting; PO lines copied with GST and totals; the contract
//   pop-up's automatic number; a costing unlinked from a quotation can be found
//   again; a call or job never loses its quotation because it was revised; a
//   discipline beside role and designation.
t_as_admin();
$tag = 'MR' . getmypid();

t_section('MOD-REP · Report set-up screens are in Admin; Governance left Reporting');
$admin = json_encode(ops_area_def('admin'));
foreach (['/report-types' => 'Report types', '/irn-rules' => 'IRN numbering rules', '/phrase-library' => 'Phrase library',
          '/compliance' => 'Where we stand', '/incidents' => 'Security incident register'] as $r => $l)
    t_ok(strpos($admin, json_encode($r)) !== false || strpos($admin, str_replace('/', '\/', $r)) !== false, "Admin has a tile for $l ($r)");
$rep = json_encode(ops_area_def('reporting'));
t_ok(strpos($rep, 'compliance') === false && strpos($rep, 'Governance') === false, 'Reporting no longer carries the Governance tab (it is about data protection, not reports)');

t_section('MOD-REP · A PO takes the quotation lines with GST and totals');
db()->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?, '', 1, 'ACTIVE')")->execute([$tag . ' Client']);
$pid = (int) db()->lastInsertId();
db()->prepare("INSERT INTO quotations (quote_no, rev, status, is_current, client_id, gst_pct, subject, total_amount) VALUES (?,0,'ACCEPTED',1,?,18,'probe',0)")->execute([$tag . '-Q', $pid]);
$qid = (int) db()->lastInsertId();
db()->prepare("INSERT INTO quote_lines (quote_id, line_no, description, location, qty, unit, rate) VALUES (?,1,'Third-party inspection','Pune',10,'MANDAY',5000)")->execute([$qid]);
db()->prepare("INSERT INTO partner_purchase_orders (partner_id, quotation_id, po_number, po_type, title, value, start_date, end_date, notes) VALUES (?,?,?,'REGULAR','t',0,'','','')")->execute([$pid, $qid, $tag . '-PO']);
$poId = (int) db()->lastInsertId();
$r = po_pull_quote_lines($poId, $qid);
t_ok(!empty($r['ok']) && (int) $r['count'] === 1, 'the quotation line is copied');
$l = ops_one("SELECT * FROM po_line_items WHERE purchase_order_id=?", [$poId]);
t_eq([(float) $l['gst_pct'], (float) $l['base_amount'], (float) $l['tax_amount'], (float) $l['total_amount']], [18.0, 50000.0, 9000.0, 59000.0],
     'with the quotation\'s 18% GST: base 50,000, tax 9,000, total 59,000 — not ₹0');
t_eq((string) $l['site'], 'Pune', 'the quoted location goes into the line\'s site');
// An older copy (no amounts) is repaired once.
db()->prepare("INSERT INTO po_line_items (purchase_order_id, description, item_type, quantity, rate, consumed) VALUES (?, 'old copy', 'MANDAY', 2, 1000, 0)")->execute([$poId]);
$oldId = (int) db()->lastInsertId();
setting_set('po_lines_backfill_v1', '');
po_lines_backfill();
$o = ops_one("SELECT * FROM po_line_items WHERE id=?", [$oldId]);
t_eq([(float) $o['base_amount'], (float) $o['total_amount']], [2000.0, 2360.0], 'a line copied before this fix gets its base and total (at the quotation\'s GST)');
t_eq(setting_get('po_lines_backfill_v1', ''), 'done', 'and the repair runs only once');

t_section('MOD-REP · A contract added from the client gets the next number');
$n1 = gen_contract_number(null);
t_ok((bool) preg_match('#^GEN/C/[^/]+/\d{5}$#', $n1), 'the next number is offered in the company format: ' . $n1);

t_section('MOD-REP · A call or job keeps its quotation after the quotation is revised');
db()->prepare("UPDATE quotations SET is_current=0 WHERE id=?")->execute([$qid]);
db()->prepare("INSERT INTO quotations (quote_no, rev, parent_id, status, is_current, client_id, gst_pct, subject) VALUES (?,1,?,'DRAFT',1,?,18,'probe r1')")->execute([$tag . '-Q', $qid, $pid]);
$q2 = (int) db()->lastInsertId();
$ids = array_map(fn($x) => (int) $x['id'], call_quotes_for_client($pid));
t_ok(!in_array($qid, $ids, true), 'before: the revised-away quotation was not in the call\'s list — so saving the call cleared it');
$ids = array_map(fn($x) => (int) $x['id'], call_quotes_for_client($pid, $qid));
t_ok(in_array($qid, $ids, true), 'now: the quotation the call is on is always offered, so it stays linked');
$ids = array_map(fn($x) => (int) $x['id'], job_linkable_quotes($pid, $qid));
t_ok(in_array($qid, $ids, true), 'the same for a job');

t_section('MOD-REP · An unlinked costing can be found and attached again');
pc_migrate();
db()->prepare("INSERT INTO project_costings (code, title, basis, status, quote_id, client_id, created_at) VALUES (?, 'Probe costing', 'LONG', 'DRAFT', ?, ?, ?)")->execute([$tag . '-PC', $qid, $pid, date('c')]);
$cid = (int) db()->lastInsertId();
t_eq((int) (pc_for_quote($q2)['id'] ?? 0), $cid, 'a revised quotation still shows the costing linked to its earlier revision');
db()->prepare("UPDATE project_costings SET quote_id=NULL, opportunity_id=99 WHERE id=?")->execute([$cid]);
$un = array_map(fn($x) => (int) $x['id'], pc_unlinked(100, 'quote', $pid));
t_ok(in_array($cid, $un, true), 'once unlinked it is offered under "attach existing" — even while still tagged to a deal');
t_eq((int) (pc_unlinked(100, 'quote', $pid)[0]['id'] ?? 0), $cid, 'the same client\'s costings come first');
$found = array_map(fn($x) => (int) $x['id'], pc_all(200, ['link' => 'none', 'q' => $tag]));
t_eq($found, [$cid], 'and the register finds it: search + "Not on a quotation"');

t_section('MOD-REP · Discipline beside role and designation');
$tt = lk_type('trade');
t_ok((bool) $tt, 'the Trade / discipline list exists');
$mech = null; foreach (lk_root_values($tt['id']) as $v) if (stripos($v['label'], 'mech') !== false) { $mech = $v; break; }
if (!$mech) { lk_add_value($tt['id'], null, 'MECH' . substr($tag, -4), 'Mechanical'); foreach (lk_root_values($tt['id']) as $v) if ($v['label'] === 'Mechanical') $mech = $v; }
db()->prepare("INSERT INTO inspectors (name, status) VALUES (?, 'ACTIVE')")->execute([$tag . ' Person']);
$ins = (int) db()->lastInsertId();
db()->prepare("INSERT INTO users (username, password_hash, first_name, role, is_active, inspector_id, position_title) VALUES (?, 'x', 'Probe', 'INSPECTOR', 1, ?, 'Inspector')")->execute([strtolower($tag), $ins]);
$uid = (int) db()->lastInsertId();
user_discipline_save($uid, $ins, (string) $mech['id'], 'Inspector');
t_eq((int) ops_val("SELECT trade_id FROM inspectors WHERE id=?", [$ins]), (int) $mech['id'], 'the discipline is the team member\'s own (job matching reads it)');
t_eq((string) ops_val("SELECT designation FROM inspectors WHERE id=?", [$ins]), 'Inspector', 'a team member with no designation takes the login\'s');
$u = ops_one("SELECT * FROM users WHERE id=?", [$uid]);
t_eq(user_role_discipline_label($u), 'Inspector — ' . $mech['label'], 'shown together: "Inspector — Mechanical"');
user_discipline_save($uid, $ins, '', 'Inspector');
t_eq(user_role_discipline_label(ops_one("SELECT * FROM users WHERE id=?", [$uid])), 'Inspector', 'cleared, it shows the designation alone');
