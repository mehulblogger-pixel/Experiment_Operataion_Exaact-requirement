<?php
// Simple Sales — Pipeline board (the owner's Excel PIPELINE sheet). Open leads
// first, then the deal pipeline's own stages (opp_board), coloured cool → warm →
// green; won/lost columns are left to the full deals screen. Each card opens
// its record; change the stage there and the card moves.
require __DIR__ . '/_sx_ui.php';
$cols = ['#0c7f8e', '#1386b6', '#2c9a8a', '#3f9d58', '#6aab2e', '#8aa61a', '#a59a14'];
$who2 = (int) $who;
$q = $whoKey !== 'me' ? '&who=' . rawurlencode((string) $whoKey) : '';
$cardTone = function ($r) { $st = sx_due_state($r['next_action_on'] ?? ''); return $st === 'overdue' || $st === 'none' ? 'r' : ($st === 'today' ? 'o' : ($st === 'week' ? 'y' : '')); };
$cardNote = function ($r) { $st = sx_due_state($r['next_action_on'] ?? ''); return $st === 'none' ? '❗ no next step' : ($st === 'overdue' ? '🔴 overdue' : ($st === 'today' ? '🟠 today' : '')); };
?>
<div class="sx">
  <div class="sxhead"><h1>🧭 Pipeline board</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <?php if (count($pipes) > 1): ?><nav class="sxchips"><?php foreach ($pipes as $p): ?><a class="sxchip<?= (int) $p['id'] === (int) ($board['pipeline'] ?? 0) ? ' on' : '' ?>" href="/sales-board?pipeline=<?= (int) $p['id'] . e($q) ?>"><?= e($p['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
      <?php sx_person_chips('sales-board', $whoKey, $people, $seeAll, isset($_GET['pipeline']) ? '&pipeline=' . (int) $_GET['pipeline'] : ''); ?>
    </div></div>
  <p class="sxwhy"><b>Why you're here:</b> the whole pipeline at a glance. 🔴 overdue · 🟠 due today · ❗ no next step. Open a card to change its stage, and the card moves.</p>
  <div class="sxside" role="group" aria-label="Confirmed orders"><b>Confirmed orders</b>
    <div><span><?= e(function_exists('THP') ? THP('quote') : 'Quotes') ?> accepted · this financial year</span><strong><?= (int) $orders['n'] ?></strong></div>
    <div><span>Their value · before GST</span><strong><?= e(sx_short($orders['v'])) ?></strong></div>
    <a href="/quotes?status=ACCEPTED" style="color:#fff;font-weight:700;margin-left:auto">See them ›</a></div>
    <div class="sxboard">
      <section class="sxcol"><header class="sx-blue">🧲 Open leads<small><?= count($leads) ?> · <?= e(sx_short(array_sum(array_map(fn($l) => (float) $l['value'], $leads)))) ?> est.</small></header>
        <?php foreach (array_slice($leads, 0, 25) as $l): ?><a class="sxcard2 <?= e($cardTone($l)) ?>" href="/lead?id=<?= (int) $l['id'] ?>"><b><?= e($l['company_name']) ?></b><span><?= e(sx_short($l['value'])) ?> · <?= e($l['owner_name'] ?: '—') ?><?= $cardNote($l) !== '' ? ' · ' . e($cardNote($l)) : '' ?></span></a><?php endforeach; ?>
        <?php if (count($leads) > 25): ?><a class="sxcard2" href="/leads">+ <?= count($leads) - 25 ?> more ›</a><?php endif; ?>
      </section>
      <?php $i = 0; foreach (($board['columns'] ?? []) as $col): $s = $col['stage']; if (($s['kind'] ?? 'OPEN') !== 'OPEN') continue;
            $rows = array_values(array_filter($col['rows'], fn($o) => $o['status'] === 'OPEN' && (!$who2 || (int) $o['owner_user_id'] === $who2)));
            $v = array_sum(array_map(fn($o) => (float) $o['value'], $rows)); ?>
        <section class="sxcol"><header style="background:<?= e($cols[min($i++, count($cols) - 1)]) ?>"><?= e($s['name']) ?><small><?= count($rows) ?> · <?= e(sx_short($v)) ?> · <?= (int) $s['probability'] ?>% win</small></header>
          <?php foreach ($rows as $o): ?><a class="sxcard2 <?= e($cardTone($o)) ?>" href="/opportunity?id=<?= (int) $o['id'] ?>"><b><?= e($o['client_name'] ?: $o['partner_name'] ?: $o['name']) ?></b><span><?= e(sx_short($o['value'])) ?> · <?= e($o['owner_name'] ?: '—') ?><?= $cardNote($o) !== '' ? ' · ' . e($cardNote($o)) : '' ?></span></a><?php endforeach; ?>
          <?php if (!$rows): ?><p class="sxempty" style="padding:8px 11px">—</p><?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
</div>
