<?php
// Owner, 13 Nov 2026 — "Once contract is made the costing shall not be unlinked.
// At present when I clicked on unlink … on contract number raised against
// accepted / won quote it got unlinked." Rule (owner): locked from the moment the
// quotation is sent to the customer, and once it is won or has a contract — any
// one of these. Before that, unlinking stays allowed and logged.
t_section('A costing stays linked once its quotation is sent, won or contracted');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    t_as_admin();
    if (function_exists('crm_migrate')) crm_migrate(); if (function_exists('opp_migrate')) opp_migrate();
    $mk = function ($status, $sentAt = '', $contractNo = '') use ($pdo) {
        $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_name, status, sent_at, contract_number, created_at) VALUES (?,0,1,'C',?,?,?,?)")
            ->execute(['CSL-' . mt_rand(1000, 99999), $status, $sentAt, $contractNo, date('c')]);
        return (int)$pdo->lastInsertId();
    };
    t_eq(pc_unlink_block_quote($mk('DRAFT')), '', 'a draft quotation: the costing may still be unlinked');
    //  Owner, 14 Nov 2026 ("Lock from: quote submitted for approval"): the lock now starts here.
    t_ok(strpos(pc_unlink_block_quote($mk('PENDING_APPROVAL')), 'has gone for approval') !== false, 'waiting for approval: locked (from 14 Nov 2026)');
    t_ok(strpos(pc_unlink_block_quote($mk('APPROVED')), 'has been approved') !== false, 'approved but not yet sent: locked (from 14 Nov 2026)');
    t_ok(strpos(pc_unlink_block_quote($mk('SENT', date('c'))), 'has gone to the') !== false, 'sent to the customer: locked, and it says why');
    t_ok(strpos(pc_unlink_block_quote($mk('ACCEPTED', date('c'))), 'has been won') !== false, 'won: locked');
    t_ok(strpos(pc_unlink_block_quote($mk('ACCEPTED', date('c'), 'AHM/C/2627/00001')), 'has a contract (AHM/C/2627/00001)') !== false, 'with a contract: locked, naming the contract');
    t_ok(strpos(pc_unlink_block_quote($mk('ACCEPTED', '', 'X-1')), 'raise a revision') !== false, 'the message says the way to change the price is a revision');
    t_ok(pc_unlink_block_quote($mk('LOST', date('c'))) !== '', 'a quotation that went out and was lost keeps its costing too (it is what was offered)');
    t_eq(pc_unlink_block_quote(0), '', 'no quotation: nothing to lock');

    //  A revision is the same quotation re-priced: once ANY revision went out, the costing
    //  stays — whichever revision it is stored against, and whichever one is on screen.
    $r0 = $mk('SENT', date('c'));
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, parent_id, client_name, status, created_at) VALUES (?,1,1,?,'C','DRAFT',?)")->execute(['CSL-R-' . getmypid(), $r0, date('c')]);
    $r1 = (int)$pdo->lastInsertId();
    t_ok(pc_unlink_block_quote($r1) !== '', 'a fresh draft revision of a quotation that was already sent: still locked');
    $d0 = $mk('DRAFT');
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, parent_id, client_name, status, sent_at, created_at) VALUES (?,1,1,?,'C','ACCEPTED',?,?)")->execute(['CSL-R2-' . getmypid(), $d0, date('c'), date('c')]);
    t_ok(strpos(pc_unlink_block_quote($d0), 'has been won') !== false, 'a costing stored against the first revision is locked when a later revision is won');
    $lone = $mk('DRAFT');
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, parent_id, client_name, status, created_at) VALUES (?,1,1,?,'C','DRAFT',?)")->execute(['CSL-R3-' . getmypid(), $lone, date('c')]);
    t_eq(pc_unlink_block_quote($lone), '', 'a quotation revised before it was ever sent: still free to unlink');

    $pdo->prepare("INSERT INTO opportunities (ref, name, status, created_at) VALUES (?, 'Deal', 'OPEN', ?)")->execute(['CSL-O-' . getmypid(), date('c')]);
    $oid = (int)$pdo->lastInsertId();
    t_eq(pc_unlink_block_deal($oid), '', 'an open deal: the costing may be unlinked');
    $pdo->prepare("UPDATE opportunities SET status='WON' WHERE id=?")->execute([$oid]);
    t_ok(strpos(pc_unlink_block_deal($oid), 'has been won') !== false, 'a won deal: locked');
    $pdo->prepare("UPDATE opportunities SET sent_to_accounts_at=? WHERE id=?")->execute([date('c'), $oid]);
    t_ok(strpos(pc_unlink_block_deal($oid), 'handed to Accounts') !== false, 'handed to Accounts: locked');

    $src = (string)file_get_contents(__DIR__ . '/../lib/projcosting.php');
    t_ok(strpos($src, "flash('Not unlinked — ' . \$why, 'error')") !== false, 'the unlink request itself is refused on the server, not just hidden');
    $v = (string)file_get_contents(__DIR__ . '/../views/ops/crm/quote_detail.php') . file_get_contents(__DIR__ . '/../views/ops/opportunity_detail.php');
    //  14 Nov 2026: the quotation's card says "🔒 Locked — this quote / an earlier revision …" (the lock now starts at approval).
    t_eq([substr_count($v, '🔒 Locked — '), substr_count($v, '🔒 Stays linked')], [1, 1], 'the quotation ("🔒 Locked — …") and the deal ("🔒 Stays linked") show the lock instead of the Unlink button');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
