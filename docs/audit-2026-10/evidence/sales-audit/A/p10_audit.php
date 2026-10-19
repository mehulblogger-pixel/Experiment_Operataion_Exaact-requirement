<?php
// AUDIT TRAIL: for each lifecycle action, which tables gained rows (any log / history / audit table)?
require __DIR__ . '/plib.php';
function counts() { $o = []; foreach (t_tables() as $t) { if ($t === 'sqlite_sequence') continue; try { $o[$t] = (int)ops_val("SELECT COUNT(*) FROM \"$t\""); } catch (Throwable $e) {} } return $o; }
function trace($label, callable $fn) {
    $a = counts(); $fn(); $b = counts(); $grew = [];
    foreach ($b as $t => $n) if ($n > ($a[$t] ?? 0)) $grew[] = "$t+" . ($n - ($a[$t] ?? 0));
    printf("    %-52s grew: %s\n", $label, $grew ? implode(', ', $grew) : '(nothing)');
}
H('rows written by each action (a history/audit table must appear for a status or price change)');
[$q] = new_quote('MM');
trace('quote-edit (price changed)', fn() => R('MM', 'quote-edit', 'POST', ['id' => $q], quote_post(['l_rate' => ['7777']])));
trace('quote-status DRAFT -> APPROVED (direct, no rule)', fn() => R('MM', 'quote-status', 'POST', ['id' => $q], ['to' => 'APPROVED']));
trace('quote-status APPROVED -> SENT', fn() => R('MM', 'quote-status', 'POST', ['id' => $q], ['to' => 'SENT']));
trace('quote-status SENT -> ACCEPTED', fn() => R('MM', 'quote-status', 'POST', ['id' => $q], ['to' => 'ACCEPTED']));
trace('quote-status ACCEPTED -> LOST', fn() => R('MM', 'quote-status', 'POST', ['id' => $q], ['to' => 'LOST', 'lost_reason' => 'PRICE']));
trace('quote-contract (FIN)', fn() => R('FIN', 'quote-contract', 'POST', ['id' => $q], ['auto_contract' => '1']));
$b = lastid('crm_inquiries'); R('MM', 'inquiry-new', 'POST', [], ['client_id' => (string)$GLOBALS['CLIENT'], 'contact_name' => 'R', 'contact_email' => 'r@x.invalid', 'sbu' => 'INSP', 'subject' => 'S']); $i = lastid('crm_inquiries');
trace('inquiry-edit (status -> DROPPED, subject changed)', fn() => R('MM', 'inquiry-edit', 'POST', ['id' => $i], ['client_id' => (string)$GLOBALS['CLIENT'], 'contact_name' => 'R', 'contact_email' => 'r@x.invalid', 'sbu' => 'INSP', 'subject' => 'Changed', 'status' => 'DROPPED']));
R('ADMIN', 'lead-new', 'POST', [], ['company_name' => 'Audit Lead Ltd', 'contact_name' => 'A', 'requirement' => 'x', 'value' => '5']); $l = lastid('leads');
trace('lead-edit (value 5 -> 500000)', fn() => R('ADMIN', 'lead-edit', 'POST', [], ['id' => $l, 'company_name' => 'Audit Lead Ltd', 'value' => '500000']));
R('ADMIN', 'opportunity-new', 'POST', [], ['name' => 'Audit deal', 'partner_id' => (string)$GLOBALS['CLIENT'], 'value' => '5']); $o = lastid('opportunities');
trace('opportunity-edit (value 5 -> 500000)', fn() => R('ADMIN', 'opportunity-edit', 'POST', [], ['id' => $o, 'value' => '500000']));
R('MM', 'project-costing-new', 'POST', [], ['title' => 'Audit PC', 'basis' => 'LONG']); $p = lastid('project_costings');
trace('project-costing-approve', fn() => R('MM2', 'project-costing-approve', 'POST', [], ['id' => $p]));
trace('project-costing-reopen', fn() => R('MM', 'project-costing-reopen', 'POST', [], ['id' => $p]));
echo "\n  quote_revisions summaries for #$q: " . json_encode(array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=? ORDER BY id", [$q]), 'summary')) . "\n";
