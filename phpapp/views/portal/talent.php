<?php
// Connect — a professional's full profile for a client, IDENTITY-MASKED. The
// competence picture is open (skills, taxonomy, verified certs, projects, tier,
// availability); the person's name / phone / e-mail stay hidden until they
// approve a contact request or the client engages them. Masking is resolved
// upstream by connect_privacy_resolve into $view.
$pro = $pro ?? []; $view = $view ?? []; $certs = $certs ?? []; $projects = $projects ?? [];
$pending = !empty($pending); $tier = (string)($tier ?? 'Registered'); $avail = $avail ?? null;
$masked = !empty($view['identity_masked']);
$contactVisible = !empty($view['contact_visible']);
$setting = $view['settings']['contact'] ?? 'on_request';
$name = (string)($view['display_name'] ?? 'Technical professional');
$certStatusTone = ['VALID' => ['✓ valid', '#0f7d5a', '#e7f5ef'], 'EXPIRING' => ['⚠ expiring', '#a9720a', '#fbf3df'], 'EXPIRED' => ['⛔ expired', '#9a2a2a', '#fbeceb']];
$ctxReq = $ctx_req ?? null; $ctxApp = $ctx_app ?? null;
?>
<?php if ($ctxReq): ?>
  <p class="pnote" style="margin-bottom:8px"><a href="/portal/hire-req?id=<?= (int)$ctxReq['id'] ?>">← Back to <?= e($ctxReq['ref_code'] ?? 'requirement') ?> applicants</a></p>
<?php else: ?>
  <p class="pnote" style="margin-bottom:8px"><a href="/portal/find">← Back to search</a></p>
<?php endif; ?>

<?php if ($ctxReq): $ast = strtoupper((string)($ctxApp['status'] ?? '')); ?>
<div class="pcard" style="background:#eef6f4;border:1px solid #cfe6e2;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
  <div style="font-size:13.5px">Reviewing this professional for <strong><?= e($ctxReq['ref_code'] ?? 'your requirement') ?></strong> — <?= e($ctxReq['title'] ?? '') ?></div>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if ($ast === 'APPLIED'): ?>
      <form method="post" action="/portal/hire-req" class="inl">
        <input type="hidden" name="id" value="<?= (int)$ctxReq['id'] ?>"><input type="hidden" name="application_id" value="<?= (int)$ctxApp['id'] ?>"><input type="hidden" name="action" value="app_transition"><input type="hidden" name="to" value="SHORTLISTED">
        <button class="btn" type="submit">✓ Shortlist</button>
      </form>
    <?php elseif ($ast === 'SHORTLISTED'): ?>
      <span class="ppill ok">✓ Shortlisted</span>
      <form method="post" action="/portal/hire-req" class="inl">
        <input type="hidden" name="id" value="<?= (int)$ctxReq['id'] ?>"><input type="hidden" name="application_id" value="<?= (int)$ctxApp['id'] ?>"><input type="hidden" name="action" value="app_transition"><input type="hidden" name="to" value="OFFERED">
        <button class="btn secondary" type="submit">Make offer</button>
      </form>
    <?php elseif ($ast !== ''): ?>
      <span class="ppill"><?= e(ucfirst(strtolower($ast))) ?></span>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="pcard" style="border:1px solid var(--line,#e3ebea)">
  <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start">
    <div>
      <h2 class="ptitle" style="margin:0 0 3px">
        <?= e($name) ?>
        <?php if ($masked): ?><span title="Full name unlocks once this professional approves you, or once you engage them" style="font-size:14px">🔒</span><?php endif; ?>
      </h2>
      <?php if (!empty($pro['headline'])): ?><div class="muted" style="font-size:14px"><?= e($pro['headline']) ?></div><?php endif; ?>
      <div style="margin-top:7px;display:flex;gap:7px;flex-wrap:wrap;align-items:center">
        <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:999px;background:#eef5f4;color:#0a5c5c">🛡 <?= e($tier) ?></span>
        <?php if ($avail): $t=$avail['tone']; $st=$t==='ok'?'background:#e7f5ef;color:#0f7d5a':($t==='bad'?'background:#fbeceb;color:#9a2a2a':'background:#fbf3df;color:#a9720a'); ?>
          <span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:999px;<?= $st ?>"><?= e($avail['label']) ?></span>
        <?php endif; ?>
        <?php $city=(string)($pro['base_city'] ?? ($pro['base_state'] ?? '')); if ($city!==''): ?><span class="muted" style="font-size:12.5px">📍 <?= e($city) ?><?= (int)($pro['pan_india'] ?? 0)===1 ? ' · travels Pan-India' : '' ?></span><?php endif; ?>
      </div>
    </div>
    <div style="text-align:right;min-width:210px">
      <?php if ($contactVisible): ?>
        <div style="font-size:12px;color:var(--muted)">Contact</div>
        <?php if (!empty($view['mobile'])): ?><div style="font-weight:700"><?= e($view['mobile']) ?></div><?php endif; ?>
        <?php if (!empty($view['email'])): ?><div style="font-size:13px"><?= e($view['email']) ?></div><?php endif; ?>
        <div class="ppill ok" style="margin-top:4px">✓ Shared with you</div>
      <?php elseif ($setting === 'hidden'): ?>
        <div class="muted" style="font-size:12.5px">Reachable through platform messages only.</div>
      <?php else: ?>
        <div class="muted" style="font-size:12.5px">Contact hidden<?php if (!empty($view['mobile_masked'])): ?> · <?= e($view['mobile_masked']) ?><?php endif; ?></div>
        <?php if ($pending): ?>
          <div class="ppill warn" style="margin-top:6px">⏳ Contact requested — awaiting approval</div>
        <?php else: ?>
          <form method="post" action="/portal/talent" style="margin-top:6px">
            <input type="hidden" name="action" value="reveal_request"><input type="hidden" name="pro_id" value="<?= (int)$pro['id'] ?>">
            <button class="btn" type="submit">Request contact details</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <form method="post" action="/portal/talent" style="margin-top:8px">
        <input type="hidden" name="action" value="bench_add"><input type="hidden" name="pro_id" value="<?= (int)$pro['id'] ?>">
        <button class="btn secondary" type="submit" style="width:100%">★ Add to my bench</button>
      </form>
    </div>
  </div>
</div>

<?php if (!empty($pro['skills']) || !empty($pro['disciplines'])): ?>
<div class="pcard">
  <h3 class="ptitle" style="font-size:15px;margin:0 0 8px">Skills &amp; disciplines</h3>
  <?php foreach (array_filter(array_map('trim', preg_split('/[,;]+/', (string)($pro['disciplines'] ?? '')))) as $d): ?><span class="ppill" style="background:#eef5f4;color:#0a5c5c;margin:0 5px 5px 0;display:inline-block"><?= e($d) ?></span><?php endforeach; ?>
  <?php foreach (array_slice(array_filter(array_map('trim', preg_split('/[,;]+/', (string)($pro['skills'] ?? '')))), 0, 40) as $s): ?><span class="ppill muted" style="margin:0 5px 5px 0;display:inline-block"><?= e($s) ?></span><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="pcard">
  <h3 class="ptitle" style="font-size:15px;margin:0 0 8px">Verified credentials</h3>
  <?php if (!$certs): ?>
    <p class="pempty" style="margin:0">No certificates listed yet.</p>
  <?php else: ?>
    <div class="pscroll"><table class="ptable">
      <thead><tr><th>Certificate</th><th>Authority</th><th>Valid to</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($certs as $c): [$lbl,$fg,$bg] = $certStatusTone[strtoupper((string)($c['status'] ?? 'VALID'))] ?? $certStatusTone['VALID']; ?>
        <tr>
          <td><strong><?= e($c['name'] ?? '') ?></strong><?php if (!empty($c['cert_number'])): ?> <span class="muted" style="font-size:12px">#<?= e($c['cert_number']) ?></span><?php endif; ?></td>
          <td><?= e($c['authority'] ?? '—') ?></td>
          <td><?= e($c['expiry_date'] ?: '—') ?></td>
          <td><span style="font-size:11.5px;font-weight:600;padding:2px 8px;border-radius:999px;color:<?= $fg ?>;background:<?= $bg ?>"><?= e($lbl) ?><?= ((int)($c['verified'] ?? 0)===1 ? ' · verified' : '') ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="pcard">
  <h3 class="ptitle" style="font-size:15px;margin:0 0 8px">Project experience</h3>
  <?php if (!$projects): ?>
    <p class="pempty" style="margin:0">No projects listed yet.</p>
  <?php else: foreach ($projects as $p): ?>
    <div style="padding:9px 0;border-bottom:1px solid var(--line,#eef1f0)">
      <strong><?= e($p['title'] ?? 'Project') ?></strong>
      <?php if (!empty($p['role'])): ?> — <?= e($p['role']) ?><?php endif; ?>
      <div class="muted" style="font-size:12.5px">
        <?= e(implode(' · ', array_filter([$p['client'] ?? '', $p['industry'] ?? '', $p['location'] ?? '', trim(((string)($p['start_date'] ?? '')).(!empty($p['end_date'])?' → '.$p['end_date']:''),' →')]))) ?>
      </div>
      <?php if (!empty($p['equipment']) || !empty($p['scope'])): ?><div style="font-size:12.5px;margin-top:2px"><?= e(trim(((string)($p['equipment'] ?? '')).' '.((string)($p['scope'] ?? '')))) ?></div><?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>

<p class="pnote" style="font-size:12.5px">This profile belongs to a real professional. Their name, phone and e-mail are kept private until they approve your request or you engage them — everything else you see is their verified competence record.</p>
