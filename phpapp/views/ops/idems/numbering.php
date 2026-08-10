<?php // IRN numbering rules ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/documents"><?= e(T_REG('report')) ?></a> › IRN rules</div>
<div class="master-head"><div><h1>IRN numbering rules</h1>
  <p class="sub" style="margin:2px 0 0">Design the Inspection Reference Number with no coding. The running serial is unique within everything that precedes it, so numbering never duplicates.</p></div></div>

<form method="post" action="/irn-rules" class="panel">
  <div class="form-grid">
    <div class="ff ff-wide"><label>IRN format <span class="muted">— must contain {SERIAL}</span></label>
      <input class="form-control" name="idems_irn_format" value="<?= e($format) ?>" style="font-family:monospace"></div>
    <div class="ff"><label>Company code</label><input class="form-control" name="idems_company_code" value="<?= e($company) ?>" maxlength="20"></div>
    <div class="ff"><label>Serial width (digits)</label><input class="form-control" type="number" min="3" max="10" name="idems_serial_width" value="<?= (int)$width ?>"></div>
  </div>
  <div style="margin:10px 0">
    <span class="muted">Available tokens:</span>
    <?php foreach ($tokens as $t=>$lbl): ?><code style="margin:0 4px" title="<?= e($lbl) ?>"><?= e($t) ?></code><?php endforeach; ?>
  </div>
  <div class="panel" style="background:var(--soft);margin:6px 0"><span class="muted">Live sample:</span> <strong style="font-family:monospace;font-size:15px"><?= e($sample) ?></strong></div>
  <div style="margin-top:12px"><button class="btn" type="submit">Save numbering rules</button></div>
</form>
<p class="muted" style="margin-top:10px">Example: <code>{COMPANY}/{BRANCH}/{YEAR}/{CLIENT}/{TYPE}/{SERIAL}</code> → <strong>MGH/AHD/2026/RIL/IR/000458</strong>. Add <code>{PROJECT}</code> to include the project code; an empty token drops out cleanly.</p>

<?php // §27 — per-office / per-branch numbering pattern override ?>
<div class="panel" style="margin-top:14px">
  <div class="ctitle" style="margin-top:0"><h3>Per-office numbering patterns <span class="muted">(optional)</span></h3></div>
  <p class="muted" style="margin:0 0 10px">Some branches must number the way their own clients demand. Give an office its own
    pattern here and its reports use it; leave it blank and the office follows the global format above. Each pattern must include <code>{SERIAL}</code>.</p>
  <table class="dt">
    <thead><tr><th>Office</th><th>Code</th><th>Pattern (blank = global)</th><th></th></tr></thead>
    <tbody>
    <?php foreach (($offices ?? []) as $o): ?>
      <tr>
        <form method="post" action="/irn-rules">
        <input type="hidden" name="_do" value="office_pattern">
        <input type="hidden" name="office_id" value="<?= (int)$o['id'] ?>">
        <td><strong><?= e($o['name']) ?></strong></td>
        <td class="muted"><?= e($o['code'] ?: '—') ?></td>
        <td><input class="form-control" name="irn_format" value="<?= e($o['irn_format']) ?>" placeholder="<?= e($format) ?>" style="font-family:monospace;min-width:280px"></td>
        <td class="num"><button class="btn small secondary" type="submit">Save</button></td>
        </form>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($offices)): ?><tr><td colspan="4" class="muted">No offices yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
