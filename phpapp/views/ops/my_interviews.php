<?php
// One interviewer's own diary — see lib/recruit_iv.php for why this is not
// gated on recruitment access.
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$when = function ($s) {
    $s = trim((string) $s);
    if ($s === '') return ['—', ''];
    $ts = strtotime(str_replace('T', ' ', $s));
    if (!$ts) return [$s, ''];
    $d = date('Y-m-d', $ts);
    $rel = $d === date('Y-m-d') ? 'Today'
         : ($d === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow' : date('D j M', $ts));
    return [$rel, date('H:i', $ts)];
};
?>
<div class="master-head">
  <div>
    <h1>Interviews to attend</h1>
    <p class="muted">Interviews you have been put on the panel for, over the next 30 days.
      Nothing here is yours to decide — it is your diary.</p>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="panel">
    <p class="muted" style="margin:0">You are not on any interview panel in the next 30 days.
      When a recruiter puts you on one, it appears here and on <a href="/my-work">My Work</a>.</p>
  </div>
<?php else: ?>
  <div class="panel">
    <table class="table">
      <thead><tr><th>When</th><th>Time</th><th>Candidate</th><th>Round</th><th>Mode</th><th>Where</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r):
            [$day, $time] = $when($r['scheduled_at']);
            $nm = trim((string) ($r['c_first'] ?? '') . ' ' . (string) ($r['c_last'] ?? ''));
            $att = (int) ($r['attempt'] ?? 1); ?>
        <tr>
          <td><b><?= $e($day) ?></b></td>
          <td><?= $e($time) ?></td>
          <td><?= $e($nm !== '' ? $nm : '—') ?></td>
          <td><?= $e($r['round']) ?><?php if ($att > 1): ?>
                <span class="pill p-warn" style="font-size:11px">re-interview <?= $att ?></span><?php endif; ?></td>
          <td><?= $e($r['mode']) ?></td>
          <td><?= $e($r['location'] !== '' ? $r['location'] : '—') ?></td>
          <td><?php if (!empty($r['candidate_id']) && function_exists('can') && can('mod.hiring.view')): ?>
              <a href="/candidate?id=<?= (int) $r['candidate_id'] ?>#ct=Interviews">Open ›</a>
              <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
