<?php // Standard engineering phrase library (editable master) ?>
<div class="master-head"><div><h1>Phrase library</h1>
  <p class="sub" style="margin:2px 0 0">The standard engineering sentences used by the writing assistant. The <strong>shorthand</strong> is what an inspector types; the <strong>phrase</strong> is what goes into the report. Add unlimited phrases of your own.</p></div>
  <a class="btn secondary" href="/writing-assistant">← Writing assistant</a>
</div>

<?php // MOD-REP (owner, 9 Nov 2026) — "how will people set up and use the phrase
      // library". Said on the page itself, in four steps. ?>
<details class="panel" style="margin-bottom:12px"<?= empty($rows) ? ' open' : '' ?>>
  <summary style="cursor:pointer;font-weight:600">ℹ️ How the phrase library works</summary>
  <ol style="margin:10px 0 0;padding-left:20px;line-height:1.7;font-size:13.5px">
    <li><b>Set up (once, here).</b> Add the sentences your company always writes — for each, a short <b>shorthand</b> (e.g. <code>weld ok</code>), the full <b>phrase</b>, its <b>category</b> (observation, acceptance, rejection…) and, if it belongs to one, the <b>discipline</b>. About 35 standard phrases are already here; switch off any you do not use.</li>
    <li><b>Inspectors use it while writing a report.</b> In any text box of a report, they type the shorthand — <code>dimension ok</code> — and press <b>✒️ Improve wording</b>. It is replaced by the full standard sentence. Lines with no matching shorthand are tidied (spelling, abbreviations) instead.</li>
    <li><b>Or copy from the list.</b> Reporting → <b>Technical writing</b> shows every active phrase by category; click one to copy it.</li>
    <li><b>It learns.</b> "Used" counts how often each phrase goes into reports; Reporting → <b>Learning insights</b> suggests wording that inspectors keep typing, which you can add here with one click.</li>
  </ol>
</details>

<div class="chip-row" style="margin-bottom:12px">
  <a class="ct" href="/phrase-library" style="<?= $cat===''?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>">All</a>
  <?php foreach ($cats as $k=>$v): ?><a class="ct" href="/phrase-library?cat=<?= e($k) ?>" style="<?= $cat===$k?'background:var(--brand);color:#fff;border-color:var(--brand)':'' ?>"><?= e($v) ?></a><?php endforeach; ?>
</div>

<form method="post" action="/phrase-library" class="panel">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
  <h3 class="tab-sub" style="margin-top:0"><?= $edit ? 'Edit phrase' : 'Add a phrase' ?></h3>
  <div class="form-grid">
    <div class="ff"><label>Category</label>
      <select class="form-control" name="category"><?php foreach ($cats as $k=>$v): ?><option value="<?= e($k) ?>" <?= (($edit['category'] ?? 'OBSERVATION')===$k)?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="ff"><label>Shorthand <span class="muted">— what the inspector types</span></label><input class="form-control" name="shorthand" value="<?= e($edit['shorthand'] ?? '') ?>" placeholder="e.g. dimension ok"></div>
    <div class="ff"><label>Discipline (optional)</label><input class="form-control" name="discipline" list="ph_disc" value="<?= e($edit['discipline'] ?? '') ?>" placeholder="e.g. Mechanical">
      <?php // The same list as a team member's discipline (Masters → Trade / discipline). ?>
      <datalist id="ph_disc"><?php foreach ((function_exists('lk_type') && lk_type('trade') && function_exists('lk_root_values') ? lk_root_values(lk_type('trade')['id']) : []) as $tv): ?><option value="<?= e($tv['label']) ?>"><?php endforeach; ?></datalist></div>
    <div class="ff ff-check"><input type="checkbox" name="active" <?= (!$edit || $edit['active'])?'checked':'' ?>><label>Active</label></div>
    <div class="ff ff-wide"><label>Phrase *</label><textarea class="form-control" name="phrase" rows="3" required><?= e($edit['phrase'] ?? '') ?></textarea></div>
  </div>
  <div style="margin-top:10px"><button class="btn" type="submit"><?= $edit ? 'Save phrase' : 'Add phrase' ?></button><?php if ($edit): ?> <a class="btn secondary" href="/phrase-library">Cancel</a><?php endif; ?></div>
</form>

<div class="panel" style="padding:0;overflow:hidden;margin-top:14px">
  <div class="tbl-scroll" style="overflow-x:auto">
  <table class="dt">
    <thead><tr><th>Category</th><th>Shorthand</th><th>Phrase</th><th class="num">Used</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted" style="padding:14px">No phrases in this category.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="muted"><?= e($cats[$r['category']] ?? $r['category']) ?><?= trim((string)($r['discipline'] ?? '')) !== '' ? '<br><span style="font-size:11px">' . e($r['discipline']) . '</span>' : '' ?></td>
        <td><?= $r['shorthand'] ? '<code>'.e($r['shorthand']).'</code>' : '<span class="muted">—</span>' ?></td>
        <td><?= e(mb_strimwidth($r['phrase'], 0, 130, '…')) ?><?= $r['is_system'] ? ' <span class="muted" style="font-size:11px">(standard)</span>' : '' ?></td>
        <td class="num"><?= (int)$r['usage_count'] ?></td>
        <td><?= $r['active'] ? '<span class="pill p-ok">Active</span>' : '<span class="pill p-mut">Off</span>' ?></td>
        <td style="white-space:nowrap"><a class="btn small secondary" href="/phrase-edit?id=<?= (int)$r['id'] ?>">Edit</a>
          <form method="post" action="/phrase-library" style="display:inline" onsubmit="return confirm('<?= $r['is_system']?'Deactivate this standard phrase?':'Remove this phrase?' ?>')"><input type="hidden" name="_do" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small secondary"><?= $r['is_system']?'Off':'✕' ?></button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
