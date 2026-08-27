<?php
  // My Work — everything waiting on the signed-in person, grouped into lanes.
  // Read-only launcher; every card links to the screen that already handles the item.
  $tone = ['info'=>'#2563eb', 'warn'=>'#b45309', 'bad'=>'var(--bad,#c0392b)', 'ok'=>'#15803d'];
  $laneMeta = [
    'reports' => ['My reports', 'Reports that need your attention'],
    'jobs'    => ['My jobs',    'Fieldwork to finish'],
    'money'   => ['My money',   'Your expense vouchers'],
    'do'      => ['Do now',     'Approvals, reviews and gates waiting on you'],
    'quality' => ['Quality',    'Findings assigned to you'],
  ];
  // Field inspectors are phone-first: their own reports / jobs / money come first.
  $order = $isInspector
    ? ['reports', 'jobs', 'money', 'do', 'quality']
    : ['do', 'reports', 'jobs', 'money', 'quality'];
?>
<div class="crumbs"><a href="/">Home</a> › My Work</div>
<div class="master-head">
  <div>
    <h1>My Work</h1>
    <p class="sub" style="margin:2px 0 0"><?= $name ? 'Everything waiting on ' . e($name) . ' right now.' : 'Everything waiting on you right now.' ?></p>
  </div>
</div>

<?php if ($inspectorUnlinked): ?>
  <div class="msg msg-warning" style="margin-top:14px">
    Your login is on an inspector role but is not linked to an inspector record yet, so your
    personal jobs, reports and vouchers cannot be listed. Ask an administrator to link your login
    (Users → your name → “Linked <?= e(Tl('engineer')) ?>”). Anything else waiting on you is shown below.
  </div>
<?php endif; ?>

<?php // Phase 3 §19 — Action Centre: the one prioritised "do this next" list, my written-down tasks
      // and the derived approvals merged, most urgent first (an overdue task out-ranks a routine approval). ?>
<?php if (!empty($actions)): ?>
  <div class="panel" style="margin-top:16px">
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px">
      <h3 class="tab-sub" style="margin:0">Next actions</h3>
      <a href="/tasks" class="muted" style="font-size:12.5px;text-decoration:none">My tasks →</a>
    </div>
    <p class="muted" style="margin:2px 0 10px;font-size:12px">Your written-down tasks and everything the system is waiting on you for, most urgent first.</p>
    <div style="display:flex;flex-direction:column">
      <?php foreach ($actions as $a): $c = $tone[$a['tone']] ?? $tone['info']; ?>
        <a href="<?= e($a['href']) ?>" style="display:flex;align-items:center;gap:12px;padding:9px 4px;border-top:1px solid var(--line,#e5e7eb);text-decoration:none;color:inherit">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= $c ?>;flex:none"></span>
          <span style="flex:1;min-width:0">
            <span style="font-weight:600;font-size:14px"><?= e($a['title']) ?></span>
            <?php if ($a['kind'] === 'task'): ?><span class="muted" style="font-size:11px;border:1px solid var(--line,#e5e7eb);border-radius:4px;padding:0 5px;margin-left:6px">task</span><?php endif; ?>
            <span class="muted" style="display:block;font-size:11.5px"><?= e($a['sub']) ?></span>
          </span>
          <?php if (!empty($a['overdue'])): ?><span style="font-size:11px;font-weight:700;color:var(--bad,#c0392b)">overdue</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($total === 0 && empty($actions)): ?>
  <div class="panel" style="margin-top:16px;text-align:center;padding:34px 16px">
    <div style="font-size:34px;line-height:1">✓</div>
    <h3 style="margin:8px 0 4px">You’re all caught up</h3>
    <p class="muted" style="margin:0 0 14px">Nothing is waiting on you right now.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
      <?php if ($isInspector): ?>
        <a class="btn small secondary" href="/my-jobs">My jobs</a>
        <a class="btn small secondary" href="/document-new">Start a report</a>
        <a class="btn small secondary" href="/vouchers">My vouchers</a>
      <?php else: ?>
        <a class="btn small secondary" href="/operations">Operations home</a>
        <a class="btn small secondary" href="/documents">Reports register</a>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($order as $lk): if (empty($lanes[$lk])) continue;
        [$ltitle, $lsub] = $laneMeta[$lk]; $items = $lanes[$lk]; ?>
    <div class="panel" style="margin-top:16px">
      <div style="margin-bottom:10px">
        <h3 class="tab-sub" style="margin:0"><?= e($ltitle) ?> <span class="muted" style="font-weight:600;font-size:12px">(<?= count($items) ?>)</span></h3>
        <p class="muted" style="margin:2px 0 0;font-size:12px"><?= e($lsub) ?></p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php foreach ($items as $t): $c = $tone[$t['tone']] ?? '#555';
              $plain = html_entity_decode(strip_tags((string)$t['label']), ENT_QUOTES); ?>
          <a href="<?= e($t['href']) ?>"
             aria-label="<?= e($t['n'] . ' ' . $plain . ' — ' . $t['sub']) ?>"
             style="text-decoration:none;flex:1 1 210px;min-width:200px;border:1px solid var(--line,#e5e7eb);border-left:3px solid <?= $c ?>;border-radius:10px;padding:12px 14px;background:var(--soft,#f8fafc);display:flex;flex-direction:column;gap:2px;min-height:76px">
            <div style="font-size:22px;font-weight:800;color:<?= $c ?>"><?= (int)$t['n'] ?> <span style="font-size:14px;font-weight:600"><?= $t['icon'] ?></span></div>
            <div style="font-weight:700;font-size:14px"><?= $t['label'] /* labels are pre-escaped */ ?></div>
            <div class="muted" style="font-size:12px"><?= e($t['sub']) ?></div>
            <div style="margin-top:auto;font-size:12.5px;font-weight:600;color:<?= $c ?>">Open →</div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
