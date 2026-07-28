<h2 class="ptitle">Ask us for an inspection</h2>
<p class="plead">Tell us what you need and roughly when. A coordinator picks it up, works out the scope and the
  engineer, and comes back to you — this form does not book anybody, and nothing is charged by sending it.</p>

<?php if (!empty($err)): ?><div class="pmsg err"><?= e($err) ?></div><?php endif; ?>

<form method="post" action="/portal/request" class="pcard" style="max-width:640px">
  <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px" for="rsub">What do you need? *</label>
  <input class="form-control" id="rsub" name="subject" maxlength="255" required
         placeholder="e.g. Final inspection of 12 pressure vessels before despatch">

  <label style="display:block;font-size:13px;color:var(--muted);margin:14px 0 5px" for="rdet">Detail *</label>
  <textarea class="form-control" id="rdet" name="detail" rows="5" required
    placeholder="Scope, quantities, the standard or specification it must be inspected against, and anything we should know about access to the site."></textarea>

  <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-top:14px">
    <div>
      <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px" for="rsite">Where</label>
      <input class="form-control" id="rsite" name="site" maxlength="255" placeholder="Works or site address">
    </div>
    <div>
      <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px" for="rwhen">Wanted by</label>
      <input class="form-control" id="rwhen" name="wanted_on" type="date">
    </div>
    <div>
      <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px" for="rcn">Who to speak to</label>
      <input class="form-control" id="rcn" name="contact_name" maxlength="150" value="<?= e($u['name'] ?? '') ?>">
    </div>
    <div>
      <label style="display:block;font-size:13px;color:var(--muted);margin:0 0 5px" for="rcp">Their telephone</label>
      <input class="form-control" id="rcp" name="contact_phone" maxlength="60">
    </div>
  </div>

  <button class="btn" type="submit" style="margin-top:18px">Send it</button>
</form>

<h3 class="ptitle" style="font-size:16px;margin-top:30px">What you have asked us</h3>
<?php if (!$rows): ?>
  <p class="pempty">Nothing yet.</p>
<?php else: ?>
<div class="pscroll"><table class="ptable">
  <thead><tr><th>Asked</th><th>What for</th><th>Where</th><th>Wanted by</th><th>Where it stands</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e(fdate(substr((string)$r['created_at'], 0, 10))) ?></td>
      <td><?= e($r['subject']) ?></td>
      <td><?= e($r['site'] ?: '—') ?></td>
      <td><?= e(fdate($r['wanted_on'])) ?></td>
      <td><?= e(PORTAL_REQ_STATUS[$r['status']] ?? $r['status']) ?>
        <?php if (!empty($r['reply'])): ?>
          <div class="pnote" style="margin-top:4px"><?= e($r['reply']) ?></div>
        <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
