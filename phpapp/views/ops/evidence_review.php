<div class="crumbs"><a href="/">Home</a> › Evidence review</div>
<div class="master-head">
  <div><h1>Evidence review</h1>
    <p class="sub" style="margin:2px 0 0">Where and when each photograph was <strong>taken</strong> — read from the
      photograph itself, not from where it was uploaded. Flagged, never blocked.</p></div>
  <a class="btn secondary" href="/">← Back</a>
</div>

<div class="panel" style="border-left:4px solid var(--accent)">
  <h3 class="tab-sub" style="margin-top:0">Why the upload location is not the inspection location</h3>
  <p style="margin:0">An engineer photographs the work at the plant at 11:40, drives home, and writes the report at
    nine that evening. So the location of the <em>report</em>, and even of the <em>upload</em>, is usually a kitchen
    table — and presenting either as “where the inspection happened” would be a lie a client could catch.</p>
  <p class="sub" style="margin:8px 0 0">What is used instead is the location the phone wrote <strong>inside the
    photograph</strong> when the shutter fired. It travels with the file, so the drive home changes nothing. Where a
    phone strips that data, the engineer’s <strong>site check-in</strong> — one tap, on site, at the time — carries
    the fact instead. <strong>Uploading in the evening is normal and is never flagged.</strong></p>
</div>

<div class="kpi-row">
  <div class="kpi"><span class="kic">📍</span><div class="k">Photographs located on site</div>
    <div class="v"><?= (int)$summary['pct'] ?>%</div>
    <div class="d"><?= (int)$summary['on_site'] ?> of <?= (int)$summary['photos'] ?></div></div>
  <div class="kpi"><span class="kic">✋</span><div class="k">Site check-ins</div>
    <div class="v"><?= (int)$summary['visits'] ?></div><div class="d">recorded standing there</div></div>
  <div class="kpi"><span class="kic">🚩</span><div class="k">Waiting to be looked at</div>
    <div class="v"><?= $summary['pending'] ? '<span class="down">' . (int)$summary['pending'] . '</span>' : '0' ?></div></div>
  <div class="kpi"><span class="kic">🔗</span><div class="k">Evidence chain</div>
    <div class="v" style="font-size:18px"><?= $summary['chain']['ok'] ? '<span class="up">intact</span>'
      : '<span class="down">' . count($summary['chain']['problems']) . ' problem(s)</span>' ?></div>
    <div class="d"><?= (int)$summary['chain']['entries'] ?> entries</div></div>
</div>

<?php if (!$summary['chain']['ok']): ?>
<div class="panel" style="border-left:4px solid var(--bad)">
  <h3 class="tab-sub" style="margin-top:0">The chain does not add up</h3>
  <p class="sub" style="margin-top:0">Each entry hashes the one before it, so altering or removing any evidence
    breaks every hash after it. This is what that break looks like.</p>
  <ul style="margin:0">
    <?php foreach (array_slice($summary['chain']['problems'], 0, 12) as $pr): ?>
      <li><strong><?= e($pr['kind']) ?></strong> — <?= e($pr['what']) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if (is_admin_level() || is_master()): ?>
<div class="panel">
  <h3 class="tab-sub" style="margin-top:0">Arriving and leaving</h3>
  <p class="sub" style="margin-top:0">Both are off by default, deliberately. A body whose engineers hand their
    phones in at a refinery gate cannot comply with either, and switching them on for everybody would make this
    software wrong for them on day one. Turn them on when your work allows it — a client shown
    <em>“on site 09:12 to 14:40, with a photograph at each end”</em> stops asking.</p>
  <form method="post" action="/checkin-settings" class="inline-add">
    <div class="ff ff-wide"><label>What is required</label>
      <label class="chk"><input type="checkbox" name="entry_exit" value="1" <?= checkin_entry_exit_required()?'checked':'' ?>>
        An arrival <strong>and</strong> a departure check-in before a <?= e(Tl('job')) ?> can be closed</label>
      <label class="chk"><input type="checkbox" name="photo" value="1" <?= checkin_photo_required()?'checked':'' ?>>
        A photograph with every check-in</label>
      <?php if (function_exists('geofence_on')): ?>
      <label class="chk"><input type="checkbox" name="geofence_on" value="1" <?= geofence_on()?'checked':'' ?>>
        <strong>Geofence</strong> — punch in/out only within range of the site (photo is then always required)</label>
      <label style="font-size:12.5px" class="muted">Default allowed distance
        <input class="form-control" type="number" name="geofence_radius_m" value="<?= (int)geofence_radius() ?>" min="20" max="20000" style="width:90px;display:inline-block"> m
        <span>— set a tighter or wider distance per party or per job.</span></label>
      <?php endif; ?></div>
    <button class="btn small" type="submit">Save</button>
  </form>
  <small class="muted">On a phone the photograph button opens the camera directly — no app to install. On a laptop
    it is an ordinary file chooser, which is why the requirement is a choice rather than a rule.</small>
</div>
<?php endif; ?>

<div class="panel">
  <div class="ctitle" style="margin-top:0">
    <h3><?= $docId ? 'Every item on this report' : 'Flagged, and not yet looked at' ?>
      <span class="muted">(<?= count($rows) ?>)</span></h3>
    <?php if ($docId): ?><a class="btn small secondary" href="/evidence-review">Show the whole queue</a><?php endif; ?>
  </div>
  <table class="dt">
    <thead><tr><th>Report</th><th>File</th><th>Taken</th><th>Where it was taken</th>
      <th>Uploaded from</th><th>Flags</th><th>Looked at</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $fl = evidence_flags_of($r); ?>
      <tr>
        <td class="muted"><?= e($r['irn'] ?? '—') ?></td>
        <td><?= e($r['file_name']) ?><div class="muted" style="font-size:12px"><?= e($r['kind']) ?></div></td>
        <td class="muted"><?= e($r['taken_at'] ? substr((string)$r['taken_at'], 0, 16) : '—') ?>
          <div style="font-size:11px">received <?= e(substr((string)($r['received_at'] ?? ''), 0, 16) ?: '—') ?></div></td>
        <td><?php if (($r['geo_source'] ?? '') === 'EXIF'): ?>
              <a href="<?= e(geo_map_url($r['exif_lat'], $r['exif_lon'])) ?>" target="_blank" rel="noopener">
                <?= e(geo_pretty($r['exif_lat'], $r['exif_lon'])) ?></a>
              <div class="muted" style="font-size:11px">from the photograph</div>
            <?php else: ?><span class="muted">not in the photograph</span><?php endif; ?></td>
        <td class="muted"><?= ($r['up_lat'] ?? null) !== null ? e(geo_pretty($r['up_lat'], $r['up_lon'])) : '—' ?>
          <?php if (($r['up_lat'] ?? null) !== null): ?><div style="font-size:11px">not where it was taken</div><?php endif; ?></td>
        <td><?php foreach ($fl as $f): ?>
              <span class="pill <?= in_array($f, EV_SERIOUS, true) ? 'p-bad' : 'p-warn' ?>" style="margin:0 3px 3px 0;display:inline-block"
                    title="<?= e(EV_FLAGS[$f] ?? $f) ?>"><?= e(EV_FLAGS[$f] ?? $f) ?></span>
            <?php endforeach; ?>
            <?php if (!$fl): ?><span class="muted">—</span><?php endif; ?></td>
        <td style="min-width:230px">
          <?php if (!empty($r['reviewed_at'])): ?>
            <span class="pill p-ok"><?= e($r['reviewed_by']) ?></span>
            <div class="muted" style="font-size:12px"><?= e($r['review_note']) ?></div>
          <?php elseif ($fl): ?>
            <form method="post" action="/evidence-reviewed" style="display:flex;gap:4px;margin:0">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="doc" value="<?= (int)$docId ?>">
              <input class="form-control" style="width:150px" name="review_note" placeholder="what you checked" required>
              <button class="btn small secondary" type="submit">Accept</button>
            </form>
          <?php else: ?><span class="muted">nothing to look at</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7"><?= $docId ? 'No evidence on this report yet.'
        : 'Nothing waiting. Flags appear here when a photograph carries no location, was taken far from the check-in, or looks like a fake-GPS fix.' ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
