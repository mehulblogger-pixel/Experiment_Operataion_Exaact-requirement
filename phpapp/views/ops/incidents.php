<div class="crumbs"><a href="/">Home</a> › <a href="/compliance">Where we stand</a> › <span>Incident register</span></div>

<div class="master-head">
  <div><h1>Incident register</h1>
    <p class="sub" style="margin:2px 0 0">Every security incident, with the six-hour reporting clock on it.</p></div>
  <div><a class="btn" href="/incident-new">+ Record an incident</a></div>
</div>

<div class="panel" style="border:1px solid var(--info);background:color-mix(in srgb,var(--info) 7%,transparent)">
  <b>The six hours start when somebody notices, not when the form is filled in</b>
  <div class="muted" style="margin-top:4px;font-size:13.5px;line-height:1.65">
    The CERT-In directions require a qualifying incident to be reported to
    <strong>incident@cert-in.org.in</strong> within six hours of it being noticed. Record it here as soon as you
    know, even if you do not yet know how bad it is — a half-filled entry with the right time on it is worth far
    more than a complete one written tomorrow. Fill in the rest as you learn it.
  </div>
</div>

<div class="panel" style="padding:0;overflow:hidden">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Ref</th><th>Detected</th><th>What happened</th><th>Severity</th>
      <th>Six-hour clock</th><th>CERT-In</th><th>Board / people</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $h = incident_hours_left($r); ?>
      <tr>
        <td><a href="/incident?id=<?= (int)$r['id'] ?>"><b><?= e($r['ref']) ?></b></a></td>
        <td><?= e(fdate($r['detected_at'])) ?></td>
        <td><?= e(INCIDENT_KINDS[$r['kind']] ?? $r['kind']) ?></td>
        <td><span class="pill <?= in_array($r['severity'], ['HIGH','CRITICAL'], true) ? 'p-bad' : 'p-mut' ?>"><?= e(INCIDENT_SEVERITY[$r['severity']] ?? $r['severity']) ?></span></td>
        <td><span class="pill <?= $h === null ? 'p-ok' : ($h <= 0 ? 'p-bad' : ($h < 2 ? 'p-warn' : 'p-info')) ?>"><?= e(incident_clock_text($r)) ?></span></td>
        <td><?= trim((string)$r['certin_reported_at']) !== ''
              ? '<span class="pill p-ok">' . e($r['certin_ref'] ?: 'reported') . '</span>'
              : '<span class="pill p-bad">not yet</span>' ?></td>
        <td><?php $dpb = trim((string)$r['dpb_reported_at']) !== ''; $ppl = trim((string)$r['people_told_at']) !== '';
          echo $dpb ? '<span class="pill p-ok">Board</span> ' : '<span class="pill p-mut">Board —</span> ';
          echo $ppl ? '<span class="pill p-ok">people</span>' : '<span class="pill p-mut">people —</span>'; ?></td>
        <td><span class="pill <?= $r['status'] === 'CLOSED' ? 'p-mut' : 'p-warn' ?>"><?= e($r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="8" style="text-align:center;padding:24px" class="muted">Nothing recorded — which is the way it should stay.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
