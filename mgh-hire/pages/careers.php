<?php
// =========================================================================
//  Public careers page (NO login). Lists open positions and takes applications.
//  An application creates a candidate at the first stage, auto-reads the CV,
//  and emails the HR inbox.
// =========================================================================

if (setting('careers_enabled','1') !== '1') {
    http_response_code(404);
    exit('Careers page is not available.');
}

$brand = brand_color(); $accent = accent_color();
$product = e(product_name());
$company = e(setting('company_name','')) ?: $product;

$applied = false; $err = '';
$openReqs = db()->query("SELECT * FROM requisitions WHERE status='approved' ORDER BY id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'apply') {
    $rid = (int)post('requisition_id');
    $req = null;
    foreach ($openReqs as $r) if ((int)$r['id'] === $rid) $req = $r;
    $name = post('name'); $email = post('email'); $phone = post('phone');

    // Auto-read the CV to fill any gaps + store the file.
    $cvName = ''; $cvData = ''; $skills = '';
    if (!empty($_FILES['cv']['tmp_name']) && is_uploaded_file($_FILES['cv']['tmp_name'])) {
        $bytes = file_get_contents($_FILES['cv']['tmp_name']);
        if (strlen($bytes) <= 8*1024*1024) {
            $cvName = basename($_FILES['cv']['name']);
            $cvData = 'data:'.($_FILES['cv']['type']?:'application/octet-stream').';base64,'.base64_encode($bytes);
            $ex = cv_extract_file($_FILES['cv']['tmp_name'], $cvName, $_FILES['cv']['type'] ?? '');
            if (!$name  && $ex['name'])  $name  = $ex['name'];
            if (!$email && $ex['email']) $email = $ex['email'];
            if (!$phone && $ex['phone']) $phone = $ex['phone'];
            $skills = $ex['skills'];
        }
    }
    // Pasted CV text is also read.
    if (post('cv_text')) {
        $ex = cv_extract(post('cv_text'));
        if (!$name  && $ex['name'])  $name  = $ex['name'];
        if (!$email && $ex['email']) $email = $ex['email'];
        if (!$phone && $ex['phone']) $phone = $ex['phone'];
        if (!$skills) $skills = $ex['skills'];
    }

    if (!$req)        $err = 'Please choose a position.';
    elseif (!$name)   $err = 'Please enter your name (or upload a CV we can read it from).';
    elseif (!$email && !$phone) $err = 'Please leave an email or phone so we can reach you.';
    else {
        $first = first_stage();
        $code  = next_code('CAN', 'seq_cand');
        db()->prepare("INSERT INTO candidates
            (code,requisition_id,name,email,phone,source,stage_id,status,cv_name,cv_data,notes,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
          ->execute([$code,$rid,$name,$email,$phone,'Careers page',$first['id']??0,'active',
                     $cvName,$cvData, $skills?('Skills (auto-read): '.$skills):'', now()]);
        $cid = (int)db()->lastInsertId();
        // Timeline (no logged-in actor on the public page).
        db()->prepare("INSERT INTO candidate_events (candidate_id,from_stage,to_stage,action,remarks,actor,created_at)
                       VALUES (?,?,?,?,?,?,?)")
            ->execute([$cid,0,$first['id']??0,'applied','Applied via careers page','Applicant',now()]);
        notify_new_application(['id'=>$cid,'name'=>$name,'email'=>$email,'phone'=>$phone], $req);
        $applied = true;
    }
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Careers · <?= $company ?></title>
<style>
  :root{--brand:<?= e($brand) ?>;--accent:<?= e($accent) ?>}
  *{box-sizing:border-box}
  body{margin:0;font-family:"Inter","Segoe UI",system-ui,Arial,sans-serif;background:#f6f7fb;color:#0f172a}
  .hero{background:linear-gradient(135deg,var(--brand),var(--accent));color:#fff;padding:44px 20px;text-align:center}
  .hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;opacity:.92}
  .wrap{max-width:820px;margin:-28px auto 40px;padding:0 16px}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px 24px;margin-bottom:18px;box-shadow:0 6px 24px rgba(15,23,42,.05)}
  h2{font-size:17px;margin:0 0 12px}
  .job{display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #eef2f7;flex-wrap:wrap}
  .job:last-child{border-bottom:none}
  .job b{font-size:15px}.job .meta{color:#64748b;font-size:13px}
  label{display:block;font-size:12.5px;font-weight:600;color:#334155;margin:12px 0 5px}
  input,select,textarea{width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:9px;font:inherit}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .btn{background:var(--brand);color:#fff;border:none;padding:12px 20px;border-radius:9px;font-weight:700;cursor:pointer;font-size:15px;margin-top:16px}
  .ok{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;padding:14px 16px;border-radius:10px;font-weight:600}
  .err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:11px 14px;border-radius:9px;font-weight:600;margin-bottom:10px}
  .muted{color:#64748b;font-size:13px}
  @media(max-width:640px){.row2{grid-template-columns:1fr}}
</style></head>
<body>
  <div class="hero">
    <h1>Careers at <?= $company ?></h1>
    <p><?= e(setting('careers_intro','Explore open positions and apply below.')) ?></p>
  </div>
  <div class="wrap">
    <?php if ($applied): ?>
      <div class="card"><div class="ok">✓ Thank you — your application has been received. Our team will be in touch.</div>
        <p class="muted" style="margin-top:12px"><a href="?p=careers">Apply for another position</a></p></div>
    <?php else: ?>

    <div class="card">
      <h2>Open positions</h2>
      <?php if (!$openReqs): ?><p class="muted">There are no open positions right now. Please check back soon.</p>
      <?php else: foreach ($openReqs as $r): ?>
        <div class="job">
          <div><b><?= e($r['title']) ?></b><div class="meta"><?= e($r['department']?:'—') ?> · <?= e($r['location']?:'—') ?> · <?= e($r['employment_type']) ?></div></div>
          <div class="meta"><?= (int)$r['positions'] ?> opening(s)</div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <?php if ($openReqs): ?>
    <div class="card">
      <h2>Apply now</h2>
      <p class="muted" style="margin-top:-6px">Upload your CV and we'll read your details automatically — you can correct anything before sending.</p>
      <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="do" value="apply"><?= csrf_field() ?>
        <label>Position *</label>
        <select name="requisition_id" required>
          <option value="">— choose —</option>
          <?php foreach ($openReqs as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['title']) ?><?= $r['location']?' · '.e($r['location']):'' ?></option><?php endforeach; ?>
        </select>
        <label>Upload CV (PDF / Word / text — optional but recommended)</label>
        <input type="file" name="cv" accept=".pdf,.doc,.docx,.txt">
        <div class="row2">
          <div><label>Full name *</label><input name="name" value="<?= e(post('name')) ?>"></div>
          <div><label>Email</label><input name="email" type="email" value="<?= e(post('email')) ?>"></div>
        </div>
        <div class="row2">
          <div><label>Phone</label><input name="phone" value="<?= e(post('phone')) ?>"></div>
          <div></div>
        </div>
        <label>Or paste your CV text (we'll read it)</label>
        <textarea name="cv_text" rows="3" placeholder="optional"></textarea>
        <button class="btn">Submit application</button>
      </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <p class="muted" style="text-align:center"><?= $product ?></p>
  </div>
</body></html>
