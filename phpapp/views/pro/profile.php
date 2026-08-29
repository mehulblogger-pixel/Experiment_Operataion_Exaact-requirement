<?php
  $me = $me ?? []; $saved = $saved ?? false; $disciplines = $disciplines ?? [];
  $wt = array_filter(array_map('trim', explode(',', (string)($me['work_types'] ?? ''))));
  $md = array_filter(array_map('trim', explode(',', (string)($me['disciplines'] ?? ''))));
  $wtypes = function_exists('cx_pro_work_types') ? cx_pro_work_types() : [];
?>
<h1>My profile</h1>
<p class="muted" style="margin:0 0 14px">Tell clients and agencies how you work. Everything here is filterable when they search the pool.</p>
<?php if ($saved): ?><div class="msg ok">Saved.</div><?php endif; ?>
<form method="post" action="/pro/profile">
  <div class="card">
    <h2>You</h2>
    <label>Name</label><input name="name" value="<?= e($me['name'] ?? '') ?>">
    <label>Headline</label><input name="headline" value="<?= e($me['headline'] ?? '') ?>" placeholder="e.g. Senior QA/QC & Welding Inspector, CSWIP 3.1">
    <label>Mobile</label><input name="mobile" value="<?= e($me['mobile'] ?? '') ?>">
    <label>Skills</label><input name="skills" value="<?= e($me['skills'] ?? '') ?>" placeholder="e.g. Welding inspection, NDT (UT/RT), painting">
    <label>Languages</label><input name="languages" value="<?= e($me['languages'] ?? '') ?>" placeholder="e.g. English, Hindi, Gujarati">
    <p style="margin:12px 0 0"><a href="/pro/documents" class="btn sec" style="width:100%;text-align:center">📎 Manage photo, CV &amp; certificates →</a></p>
  </div>

  <div class="card">
    <h2>Disciplines</h2>
    <div><?php foreach ($disciplines as $d): $on = in_array($d['code'], $md, true); ?>
      <label class="chip"><input type="checkbox" name="disciplines[]" value="<?= e($d['code']) ?>" <?= $on?'checked':'' ?>> <?= e($d['name']) ?></label>
    <?php endforeach; ?></div>
  </div>

  <div class="card">
    <h2>How you want to work</h2>
    <div><?php foreach ($wtypes as $k => $lbl): $on = in_array($k, $wt, true); ?>
      <label class="chip"><input type="checkbox" name="work_types[]" value="<?= e($k) ?>" <?= $on?'checked':'' ?>> <?= e($lbl) ?></label>
    <?php endforeach; ?></div>
  </div>

  <div class="card">
    <h2>Where you'll work</h2>
    <label>Base city</label><input name="base_city" value="<?= e($me['base_city'] ?? '') ?>" placeholder="e.g. Vadodara">
    <label>Preferred locations</label><input name="preferred_locations" value="<?= e($me['preferred_locations'] ?? '') ?>" placeholder="e.g. Dahej, Hazira, Jamnagar">
    <div class="grid2">
      <label class="chip" style="margin:10px 0 0"><input type="checkbox" name="pan_india" value="1" <?= !empty($me['pan_india'])?'checked':'' ?>> Open to pan-India</label>
      <label class="chip" style="margin:10px 0 0"><input type="checkbox" name="overseas" value="1" <?= !empty($me['overseas'])?'checked':'' ?>> Open to overseas</label>
    </div>
    <label>Travel radius for site visits (km)</label><input type="number" name="travel_radius_km" value="<?= (int)($me['travel_radius_km'] ?? 0) ?>">
  </div>

  <div class="card">
    <h2>Availability &amp; rates</h2>
    <div class="grid2">
      <div><label>Availability</label>
        <select name="availability">
          <?php foreach (['AVAILABLE'=>'Available now','FROM'=>'Available from a date','BUSY'=>'Currently busy'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($me['availability']??'')===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?></select></div>
      <div><label>Available from</label><input type="date" name="available_from" value="<?= e($me['available_from'] ?? '') ?>"></div>
      <div><label>Notice needed (days)</label><input type="number" name="notice_days" value="<?= (int)($me['notice_days'] ?? 0) ?>"></div>
      <div><label>Per-visit rate (₹)</label><input type="number" name="per_visit_rate" value="<?= $me['per_visit_rate']?(int)$me['per_visit_rate']:'' ?>"></div>
      <div><label>Day-rate from (₹)</label><input type="number" name="day_rate_min" value="<?= $me['day_rate_min']?(int)$me['day_rate_min']:'' ?>"></div>
      <div><label>Day-rate to (₹)</label><input type="number" name="day_rate_max" value="<?= $me['day_rate_max']?(int)$me['day_rate_max']:'' ?>"></div>
    </div>
  </div>

  <div class="card">
    <h2>Job alerts</h2>
    <p class="muted" style="margin:0 0 8px">Get told about jobs, shortlists and messages where you already are. You choose the channels — turn any off anytime.</p>
    <?php $prefs = $prefs ?? []; ?>
    <label style="display:block;margin:6px 0"><input type="checkbox" name="notify_whatsapp" value="1" <?= !empty($prefs['whatsapp']) ? 'checked' : '' ?>> WhatsApp <span class="muted">(needs your mobile above)</span></label>
    <label style="display:block;margin:6px 0"><input type="checkbox" name="notify_sms" value="1" <?= !empty($prefs['sms']) ? 'checked' : '' ?>> SMS <span class="muted">(needs your mobile above)</span></label>
    <label style="display:block;margin:6px 0"><input type="checkbox" name="notify_email" value="1" <?= !empty($prefs['email']) ? 'checked' : '' ?>> Email</label>
  </div>

  <button class="btn" type="submit" style="width:100%">Save profile</button>
</form>
