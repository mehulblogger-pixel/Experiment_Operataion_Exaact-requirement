<div class="crumbs"><a href="/">Home</a> › <a href="/capa">Corrective actions</a> › Raise one</div>
<div class="master-head">
  <div><h1>Raise a corrective action</h1>
    <p class="sub" style="margin:2px 0 0"><?= $from
      ? 'Following ' . e($from['ref']) . ', which was checked and found not to have worked.'
      : 'Something went wrong, or is about to. Record it here and the cause, the plan and the check that it worked all hang off it.' ?></p></div>
  <a class="btn secondary" href="/capa">← Back</a>
</div>

<form method="post" action="/capa-new">
  <?php if ($from): ?><input type="hidden" name="follows_id" value="<?= (int)$from['id'] ?>"><?php endif; ?>
  <div class="panel">
    <div class="fgrid">
      <div class="ff"><label>Where it came from</label>
        <select class="form-control" name="source">
          <?php foreach (CAPA_SOURCES as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($from['source'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Their reference</label>
        <input class="form-control" name="source_ref" value="<?= e($from['source_ref'] ?? '') ?>" placeholder="e.g. the complaint or audit number"></div>
      <div class="ff"><label>How serious</label>
        <select class="form-control" name="severity">
          <?php foreach (CAPA_SEVERITY as $k=>$v): ?><option value="<?= e($k) ?>" <?= $k==='MINOR'?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Clause it touches</label>
        <select class="form-control" name="clause"><option value="">— not sure —</option>
          <?php foreach (audit_clause_options() as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($from['clause'] ?? '')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div class="ff"><label>Raised on</label>
        <input class="form-control" type="date" name="raised_on" value="<?= e(date('Y-m-d')) ?>"></div>
      <div class="ff"><label>Whose job it is</label>
        <input class="form-control" name="owner" placeholder="a person, not a department"></div>
      <div class="ff"><label>Action due by</label>
        <input class="form-control" type="date" name="due_on" value="<?= e(date('Y-m-d', strtotime('+' . capa_due_days() . ' days'))) ?>"></div>

      <div class="ff ff-wide"><label>In one line *</label>
        <input class="form-control" name="title" required maxlength="255"
               value="<?= $from ? e('After ' . $from['ref'] . ' did not work: ' . $from['title']) : '' ?>"></div>
      <div class="ff ff-wide"><label>What went wrong *</label>
        <textarea class="form-control" name="description" rows="6" required
                  placeholder="what happened, and how we know"><?= $from ? e("The first corrective action was carried out and checked, and the problem did not stop.\n\nWhat was tried:\n" . $from['action_plan'] . "\n\nWhat the check found:\n" . $from['effectiveness_note']) : '' ?></textarea></div>
      <div class="ff ff-wide"><label>What we did straight away <span class="muted">— the correction, before the cause is known</span></label>
        <textarea class="form-control" name="immediate_action" rows="3"
                  placeholder="e.g. recalled the report, told the client, stopped using the instrument"></textarea>
        <small class="muted">§8.7.1 — react to it and deal with the consequences. That is not the same as fixing the cause, and the standard asks for both.</small></div>
    </div>
  </div>
  <div class="form-actions"><button class="btn" type="submit">Raise it</button>
    <a class="btn secondary" href="/capa">Cancel</a></div>
</form>
