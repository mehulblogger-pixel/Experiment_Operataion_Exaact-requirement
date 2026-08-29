<?php
  // Connect — freelancer Home. Everything the professional needs at a glance, fed
  // by functions that already exist (#1–#8 + K20 bookings). Phone-first tiles.
  $me = $me ?? []; $pct = (int)($pct ?? 0);
  $tier = $tier ?? 'registered'; $trust = $trust ?? null; $apps = $apps ?? []; $unread = (int)($unread ?? 0);
  $openjobs = (int)($openjobs ?? 0); $bookings = $bookings ?? ['total' => 0]; $prefs = $prefs ?? []; $passport_url = (string)($passport_url ?? ''); $avatar_id = (int)($avatar_id ?? 0);
  $tierLbl = function_exists('connect_verify_tier_label') ? connect_verify_tier_label($tier) : ucfirst((string)$tier);
  $tierRank = function_exists('connect_verify_tier_rank') ? connect_verify_tier_rank($tier) : 0;
  // Application pipeline counts.
  $pipe = ['APPLIED' => 0, 'SHORTLISTED' => 0, 'OFFERED' => 0, 'ACCEPTED' => 0];
  foreach ($apps as $a) { $s = strtoupper((string)$a['status']); if (isset($pipe[$s])) $pipe[$s]++; }
  $liveApps = $pipe['APPLIED'] + $pipe['SHORTLISTED'] + $pipe['OFFERED'];
?>
<div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
  <?php if ($avatar_id): ?>
    <img src="/pro/file?id=<?= $avatar_id ?>" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:1px solid var(--line);flex:0 0 auto">
  <?php else: ?>
    <a href="/pro/documents" title="Add a photo" style="width:56px;height:56px;border-radius:50%;border:1px dashed var(--line);display:flex;align-items:center;justify-content:center;flex:0 0 auto;font-size:20px;color:var(--muted);text-decoration:none">＋</a>
  <?php endif; ?>
  <div>
    <h1 style="margin:0">Hello, <?= e($me['name'] ?: 'there') ?></h1>
    <p class="muted" style="margin:2px 0 0">Your professional profile on the pool.</p>
  </div>
</div>

<style>
  .dtiles{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .dtile{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:15px;text-decoration:none;color:inherit;display:block}
  .dtile .lab{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
  .dtile .v{font-size:26px;font-weight:800;letter-spacing:-.02em;margin-top:3px}
  .dtile .d{font-size:12.5px;color:var(--muted);margin-top:2px}
  .dtile.wide{grid-column:1 / -1}
  .badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700}
  .b-reg{background:rgba(0,0,0,.06);color:var(--muted)} .b-ver{background:var(--okbg);color:var(--ok)}
  .meter{height:8px;border-radius:5px;background:var(--line);overflow:hidden;margin:6px 0 2px}
  .meter>i{display:block;height:100%;background:var(--teal)}
  .cta{display:inline-block;margin-top:8px;font-weight:700;color:var(--teal);font-size:13px}
</style>

<div class="dtiles">
  <!-- Profile strength -->
  <a class="dtile wide" href="/pro/profile">
    <div class="lab">Profile strength</div>
    <div class="meter"><i style="width:<?= $pct ?>%"></i></div>
    <div class="d"><strong style="color:var(--ink)"><?= $pct ?>%</strong> — <?= $pct < 100 ? 'complete it so the right jobs find you →' : 'complete; you\'re visible to clients and agencies.' ?></div>
  </a>

  <!-- Verification tier -->
  <a class="dtile" href="/pro/verify">
    <div class="lab">Verification</div>
    <div class="v" style="font-size:19px"><span class="badge <?= $tierRank >= 1 ? 'b-ver' : 'b-reg' ?>"><?= e($tierLbl) ?></span></div>
    <div class="d"><?= $tierRank >= 1 ? 'Verified — clients hire with confidence.' : 'Get verified to win more work.' ?></div>
    <span class="cta">Get verified →</span>
  </a>

  <!-- Trust score -->
  <a class="dtile" href="/pro/verify">
    <div class="lab">Trust Score</div>
    <div class="v"><?= $trust ? (int)$trust['score'] : 0 ?></div>
    <div class="d"><?= $trust ? e($trust['band']) : 'New' ?> · out of 1000</div>
  </a>

  <!-- Applications pipeline -->
  <a class="dtile" href="/pro/applications">
    <div class="lab">Applications</div>
    <div class="v"><?= $liveApps ?></div>
    <div class="d"><?= (int)$pipe['SHORTLISTED'] ?> shortlisted · <?= (int)$pipe['OFFERED'] ?> offered</div>
  </a>

  <!-- Messages -->
  <a class="dtile" href="/pro/messages">
    <div class="lab">Messages</div>
    <div class="v"><?= $unread ?></div>
    <div class="d"><?= $unread > 0 ? 'unread from the hiring desk' : 'no new messages' ?></div>
  </a>

  <!-- Bookings -->
  <a class="dtile" href="/pro/bookings">
    <div class="lab">My bookings</div>
    <div class="v"><?= (int)($bookings['active'] ?? 0) + (int)($bookings['booked'] ?? 0) ?></div>
    <div class="d"><?= (int)($bookings['active'] ?? 0) ?> active · <?= (int)($bookings['booked'] ?? 0) ?> booked</div>
  </a>

  <!-- Jobs for you -->
  <a class="dtile" href="/pro/jobs">
    <div class="lab">Open jobs</div>
    <div class="v"><?= $openjobs ?></div>
    <div class="d">live right now — apply in a tap</div>
  </a>
</div>

<!-- Share Passport -->
<?php if ($passport_url !== ''): ?>
<div class="card" style="margin-top:14px">
  <h2>Share your Passport</h2>
  <p class="muted" style="margin:0 0 8px">Your public, verifiable page — send it to anyone. It shows your verified credentials and trust, never your contact details.</p>
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <?php if (function_exists('qr_svg')): ?>
      <div style="width:104px;height:104px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:6px;flex:0 0 auto"><?= qr_svg($passport_url, 92) ?></div>
    <?php endif; ?>
    <div style="flex:1;min-width:180px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input id="ppurl" value="<?= e($passport_url) ?>" readonly onclick="this.select()" style="flex:1;min-width:160px;font-size:13px">
      <button class="btn" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('ppurl').value);this.textContent='Copied ✓'">Copy link</button>
      <a class="btn sec" href="<?= e($passport_url) ?>" target="_blank" rel="noopener">Open ↗</a>
      <div class="muted" style="font-size:12px;flex-basis:100%">Scan the QR to open your public Passport — print it on a card or CV.</div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Alerts status -->
<div class="card" style="margin-top:14px">
  <h2>Job alerts</h2>
  <p class="muted" style="margin:0">
    WhatsApp <strong style="color:<?= !empty($prefs['whatsapp']) ? 'var(--ok)' : 'var(--muted)' ?>"><?= !empty($prefs['whatsapp']) ? 'on' : 'off' ?></strong> ·
    SMS <strong style="color:<?= !empty($prefs['sms']) ? 'var(--ok)' : 'var(--muted)' ?>"><?= !empty($prefs['sms']) ? 'on' : 'off' ?></strong> ·
    Email <strong style="color:<?= !empty($prefs['email']) ? 'var(--ok)' : 'var(--muted)' ?>"><?= !empty($prefs['email']) ? 'on' : 'off' ?></strong>
    · <a href="/pro/profile">change →</a>
  </p>
</div>
