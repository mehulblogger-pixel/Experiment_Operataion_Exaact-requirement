<?php
// Phase 3: read every sales figure the way each screen reads it, for master and Branch Manager A.
require __DIR__ . '/lib_boot.php';
$ids = json_decode(file_get_contents(__DIR__ . '/ids.json'), true);
$out = [];
foreach (['master', 'bmA', 'mm'] as $who) {
    as_user($ids['U'][$who]); $_GET = [];
    $o = ['who' => $who];
    // /crm-reports (lib/crm.php:3481)
    ops_crm_reports(); $v = $GLOBALS['__view_vars'];
    $o['crm_reports'] = ['fy' => $v['fy'], 'total' => $v['total'], 'totVal' => $v['totVal'], 'openVal' => $v['openVal'], 'wonVal' => $v['wonVal'],
                         'nWon' => $v['nWon'], 'nLost' => $v['nLost'], 'winRate' => $v['winRate'], 'months' => $v['months'], 'lost' => $v['lost'], 'bySbu' => $v['bySbu']];
    // /crm-dashboard (lib/crmdash.php:234), 12-month window
    $_GET = ['days' => 365]; ops_crmdash('crm-dashboard', 'GET'); $v = $GLOBALS['__view_vars'];
    $o['crm_dashboard'] = ['head' => $v['head'], 'top' => $v['top'], 'losses' => $v['losses'],
        'funnel' => array_map(fn($s) => [$s['stage']['name'], 'now' => $s['now'], 'value' => $s['value'], 'weighted' => $s['weighted'], 'reached' => $s['reached'], 'conv' => $s['conv_from_prev']], $v['funnel']['stages'] ?? []),
        'owners' => $v['owners']];
    // Home dashboard sales tiles — the SAME SQL as views/dashboard.php:441-452 (inline in the view)
    [$qw, $qa] = scope_clause('q.office_id', 'q.sbu');
    $qs = ops_one("SELECT
          SUM(CASE WHEN status IN ('DRAFT','PENDING_APPROVAL','APPROVED','SENT') THEN 1 ELSE 0 END) open_n,
          SUM(CASE WHEN status IN ('DRAFT','PENDING_APPROVAL','APPROVED','SENT') THEN total_amount ELSE 0 END) open_val,
          SUM(CASE WHEN status='SENT' THEN 1 ELSE 0 END) sent_n,
          SUM(CASE WHEN status='ACCEPTED' THEN total_amount ELSE 0 END) won_val,
          SUM(CASE WHEN status='ACCEPTED' THEN 1 ELSE 0 END) won_n,
          SUM(CASE WHEN status IN ('LOST','EXPIRED') THEN 1 ELSE 0 END) lost_n
          FROM quotations q WHERE is_current=1 AND $qw", $qa);
    $pend = (can('crm.quote.approve') || is_master()) ? (int)ops_val("SELECT COUNT(*) FROM quote_approvals a JOIN quotations q ON q.id=a.quote_id WHERE a.status='PENDING' AND q.status='PENDING_APPROVAL' AND $qw", $qa) : 0;
    $o['home'] = $qs + ['pendApprove_tile' => $pend, 'winRate' => ($qs['won_n'] + $qs['lost_n']) ? round($qs['won_n'] / ($qs['won_n'] + $qs['lost_n']) * 100) : 0];
    // Command Centre money band (financial_rollup, lib/finevent.php:93)
    $o['command_centre_committed'] = financial_rollup([])['committed'];
    // Pending-task + attention counts
    $o['crm_quotes_awaiting_me'] = crm_quotes_awaiting_me();
    $o['inquiries_due_count'] = inquiries_due_count();
    $o['quotes_expired_count'] = quotes_expired_count();
    $o['quotes_awaiting_contract_count'] = quotes_awaiting_contract_count();
    // Quote register headline counts (lib/crm.php:1480)
    $_GET = []; ops_crm_quotes('quotes', 'GET'); $o['quote_register_counts'] = $GLOBALS['__view_vars']['counts'];
    $out[] = $o;
}
file_put_contents(__DIR__ . '/recon_read.json', json_encode($out, JSON_PRETTY_PRINT));
echo json_encode($out, JSON_PRETTY_PRINT);
