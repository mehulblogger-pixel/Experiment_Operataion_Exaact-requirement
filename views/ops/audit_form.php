<div class="crumbs"><a href="/">Home</a> › <a href="/internal-audits">Internal audits</a> › Plan one</div>
<div class="master-head">
  <div><h1>Plan an internal audit</h1>
    <p class="sub" style="margin:2px 0 0">Name the auditor and whoever runs the area. If they are the same person
      this will refuse — §8.8.2, auditors shall not audit their own work.</p></div>
  <a class="btn secondary" href="/internal-audits">← Back</a>
</div>

<form method="post" action="/internal-audit-new">
  <div class="panel">
    <div class="fgrid">
      <div class="ff"><label>Planned for</label>
        <input class="form-control" type="date" name="planned_on" value="<?= e(date('Y-m-d')) ?>"></div>
      <div class="ff"><label>Auditor *</label>
        <input class="form-control" name="auditor" required placeholder="who is carrying it out"></div>
      <div class="ff"><label>Who runs this area</label>
        <input class="form-control" name="area_owner" placeholder="the person responsible for what is being audited">
        <small class="muted">Written down so the §8.8.2 check has something to compare against.</small></div>
      <div class="ff ff-wide"><label>Scope — what is being looked at</label>
        <input class="form-control" name="scope" placeholder="e.g. the Ahmedabad branch's report issue and record keeping"></div>
      <div class="ff ff-wide"><label>How <span class="muted">— records sampled, people interviewed, work witnessed</span></label>
        <input class="form-control" name="method"></div>
      <div class="ff ff-wide"><label>Clauses covered *</label>
        <div class="checkgrid">
          <?php foreach ($clauses as $k=>$v): ?>
            <label class="chk"><input type="checkbox" name="clauses[]" value="<?= e($k) ?>"> <?= e($v) ?></label>
          <?php endforeach; ?>
        </div>
        <small class="muted">The coverage board on the previous screen is built from these, so being honest here is what makes it useful.</small></div>
    </div>
  </div>
  <div class="form-actions"><button class="btn" type="submit">Plan it</button>
    <a class="btn secondary" href="/internal-audits">Cancel</a></div>
</form>
