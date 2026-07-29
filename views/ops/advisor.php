<?php
// The advisor screen. Every finding opens into a full brief: what it costs, why
// it happened, the numbered steps, who does it, and the rows so it is checkable.
// The first finding is open by default — a page of collapsed headings reads as a
// list of complaints, and nobody expands a complaint.
$cashCount = 0;
foreach ($found as $f) if ($f['severity'] === 'CASH') $cashCount++;
$openKey = $open !== '' ? $open : ($found ? $found[0]['key'] : '');
$tone = ['CASH' => 'tone-bad', 'SPEED' => 'tone-warn', 'RISK' => 'tone-warn', 'DATA' => 'tone-ok'];
?>
<div class="crumbs"><a href="/">Home</a> › What to fix</div>
<div class="master-head">
  <div><h1>What to fix</h1>
  <p class="sub" style="margin:2px 0 0">Not a dashboard. Every item below says what it is costing you, why it happened, the exact steps to fix it on the screens in this system, and who should do it. Ordered by money, because twelve small problems matter less than one large one. Nothing here changes anything on its own.</p></div>
  <?php if ($found): ?><a class="btn secondary" href="/advisor?export=csv">⬇ CSV</a><?php endif; ?>
</div>

<?php if (!$found): ?>
  <div class="panel" style="margin-top:16px;text-align:center;padding:36px 16px">
    <div style="font-size:32px;line-height:1">✓</div>
    <p style="margin:10px 0 0;font-size:15px"><b>Nothing needs fixing right now.</b></p>
    <p class="muted" style="margin:6px 0 0;max-width:620px;display:inline-block">
      <?php if ($checked): ?>
        Checked, and all clear: <?= e(count($checked) > 1
              ? implode(', ', array_slice($checked, 0, -1)) . ', and ' . end($checked)
              : $checked[0]) ?>. This screen re-checks each time you open it.
      <?php else: ?>
        There is nothing here for this licence to check yet. The advisor looks across selling, doing and
        billing; add the modules and it starts reporting on them.
      <?php endif; ?>
    </p>
  </div>
<?php else: ?>

  <div class="panel" style="margin-top:16px;display:flex;gap:20px;flex-wrap:wrap;align-items:center">
    <div>
      <div class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.4px">Cash sitting still</div>
      <div style="font-size:26px;font-weight:700;line-height:1.2"><?= e(fmoney($cash)) ?></div>
      <div class="muted" style="font-size:12.5px">across <?= (int)$cashCount ?> <?= $cashCount === 1 ? 'finding' : 'findings' ?> you can act on today</div>
    </div>
    <div style="flex:1;min-width:240px;border-left:1px solid var(--line);padding-left:20px">
      <div class="muted" style="font-size:12.5px">
        This is money already earned or already owed to you — work finished and never invoiced, invoices past their date,
        payments that arrived and were never matched. It is not a forecast. Each figure below links to the screen that collects it.
      </div>
    </div>
  </div>

  <div class="qcards" style="margin-top:12px">
    <?php foreach ($found as $f): ?>
      <a class="qcard <?= e($tone[$f['severity']] ?? 'tone-warn') ?>" href="/advisor?open=<?= e($f['key']) ?>#f-<?= e($f['key']) ?>">
        <div class="qic"><?= $f['severity'] === 'CASH' ? '₹' : '!' ?></div>
        <div class="qn"><?= (int)$f['n'] ?></div>
        <div class="ql"><?= e($f['title']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php foreach ($found as $f): $isOpen = ($f['key'] === $openKey); ?>
    <details class="panel" id="f-<?= e($f['key']) ?>" style="margin-top:16px;padding:0;overflow:hidden"<?= $isOpen ? ' open' : '' ?>>
      <summary style="padding:12px 16px;background:var(--soft);border-bottom:1px solid var(--line);cursor:pointer;list-style:none">
        <span class="pill <?= $f['severity'] === 'CASH' ? 'bad' : ($f['severity'] === 'DATA' ? '' : 'warn') ?>" style="float:right;margin-left:10px"><?= e($sev[$f['severity']] ?? $f['severity']) ?></span>
        <b style="font-size:14px"><?= e($f['title']) ?></b>
        <div class="muted" style="font-size:12.5px;margin-top:3px"><?= (int)$f['n'] ?> <?= $f['n'] === 1 ? 'record' : 'records' ?> · <?= $f['cost'] ?></div>
      </summary>

      <div style="padding:14px 16px">
        <div style="display:flex;gap:18px;flex-wrap:wrap">

          <div style="flex:2;min-width:300px">
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted)">Why this happened</div>
            <p style="margin:4px 0 14px;font-size:13.5px;line-height:1.55"><?= $f['why'] ?></p>

            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted)">How to fix it</div>
            <ol style="margin:6px 0 14px;padding-left:20px;font-size:13.5px;line-height:1.6">
              <?php foreach ($f['steps'] as $s): if (trim(strip_tags((string)$s)) === '') continue; ?>
                <li style="margin-bottom:5px"><?= $s ?></li>
              <?php endforeach; ?>
            </ol>

            <?php if (!empty($f['stop'])): ?>
              <div style="font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted)">So it stops happening</div>
              <p style="margin:4px 0 0;font-size:13.5px;line-height:1.55"><?= $f['stop'] ?></p>
            <?php endif; ?>
          </div>

          <div style="flex:1;min-width:210px">
            <div class="panel" style="margin:0;background:var(--soft)">
              <?php // A rupee figure is only shown where one was actually computed.
                    // "At stake ₹0" on a risk finding reads as "this is worth nothing",
                    // which is the opposite of what a missing report means. ?>
              <div style="font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted)">At stake</div>
              <div style="font-size:19px;font-weight:700;margin:2px 0 10px">
                <?= (float)$f['value'] > 0 ? e(fmoney((float)$f['value'])) : (int)$f['n'] . ' ' . ($f['n'] === 1 ? 'record' : 'records') ?>
              </div>

              <div style="font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted)">Who does it</div>
              <div style="font-size:13px;margin:2px 0 12px"><?= e($f['who']) ?></div>

              <?php if (!empty($f['link'])): ?>
                <a class="btn" style="width:100%;text-align:center" href="<?= e($f['link']) ?>"><?= e($f['link_label'] ?: 'Open the screen') ?></a>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if (!empty($f['rows'])):
          // Only call the sample "the biggest" where the rows carry a figure to be
          // big by. Where they do not, "the 12 biggest of 100" is a claim about an
          // order that does not exist.
          $hasDays = false; $hasAmount = false;
          foreach ($f['rows'] as $r) {
              if (isset($r['days'])) $hasDays = true;
              if (isset($r['amount']) && (float)$r['amount'] > 0) $hasAmount = true;
          }
          $shown = count($f['rows']);
          $lead = $shown >= (int)$f['n'] ? 'All ' . (int)$f['n']
                : ($hasAmount ? 'The ' . $shown . ' largest of ' . (int)$f['n']
                : ($hasDays ? 'The ' . $shown . ' worst of ' . (int)$f['n'] : $shown . ' of ' . (int)$f['n']));
        ?>
          <div style="font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-top:16px">
            <?= e($lead) ?> — so you can check rather than take our word for it
          </div>
          <div class="dt-scroll" style="margin-top:6px">
            <table class="dt">
              <caption class="sr-only"><?= e($f['title']) ?></caption>
              <thead><tr>
                <th scope="col">Reference</th><th scope="col">Date</th><th scope="col">Who / what</th>
                <th scope="col" style="text-align:right"><?= $hasDays ? 'Days late' : ($hasAmount ? 'Value' : '') ?></th>
              </tr></thead>
              <tbody>
              <?php foreach ($f['rows'] as $r): ?>
                <tr>
                  <td><?php if (!empty($f['row_url'])): ?><a href="<?= e($f['row_url'] . (int)($r['id'] ?? 0)) ?>"><b><?= e((string)($r['ref'] ?: '#' . (int)($r['id'] ?? 0))) ?></b></a><?php else: ?><b><?= e((string)($r['ref'] ?? '')) ?></b><?php endif; ?></td>
                  <td><?= trim((string)($r['d'] ?? '')) !== '' ? e(fdate(substr((string)$r['d'], 0, 10))) : '<span class="muted">—</span>' ?></td>
                  <td><?= e((string)($r['who'] ?? '') ?: '—') ?></td>
                  <td style="text-align:right"><?php
                    if (isset($r['days'])) echo (int)$r['days'];
                    elseif (isset($r['amount']) && (float)$r['amount'] > 0) echo e(fmoney((float)$r['amount']));
                    else echo '<span class="muted">—</span>';
                  ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </details>
  <?php endforeach; ?>

  <p class="muted" style="margin-top:16px;font-size:12.5px">
    Re-checked every time this screen is opened — there is no stored copy to go stale. A finding disappears from this list
    the moment the underlying records are put right.
  </p>
<?php endif; ?>
