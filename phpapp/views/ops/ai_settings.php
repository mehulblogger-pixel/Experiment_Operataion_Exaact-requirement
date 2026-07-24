<div class="crumbs"><a href="/">Home</a> › <a href="/settings">Settings</a> › AI providers</div>
<div class="master-head">
  <div><h1>AI providers &amp; models</h1>
    <p class="sub" style="margin:2px 0 0">Store an API key per provider, refresh its live model list, and choose which models to use. Keys are masked and never shown in full.</p></div>
  <a class="btn secondary" href="/settings">← Back</a>
</div>

<form method="post" action="/ai-settings">
  <?php foreach ($providers as $p => $reg): $c = ($cfg[$p] ?? []) + ['enabled'=>0,'key'=>'','base'=>'','models'=>[],'active'=>[],'refreshed'=>'','note'=>'']; ?>
    <?php $avail = !empty($c['models']) ? $c['models'] : $reg['fallback']; $keySet = trim((string)$c['key']) !== ''; ?>
    <div class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <h3 class="tab-sub" style="margin:0"><?= e($reg['label']) ?>
          <?php if ($keySet): ?><span class="pill p-ok" style="font-size:11px">key set</span><?php endif; ?>
          <?php if (!empty($c['enabled'])): ?><span class="pill p-info" style="font-size:11px">enabled</span><?php endif; ?></h3>
        <label class="chk"><input type="checkbox" name="enabled[<?= e($p) ?>]" value="1" <?= !empty($c['enabled'])?'checked':'' ?>> Enable</label>
      </div>
      <div class="form-grid" style="margin-top:8px">
        <div class="ff"><label>API key <span class="muted"><?= e($reg['key_hint']) ?> · <a href="<?= e($reg['docs']) ?>" target="_blank" rel="noopener">get key ↗</a></span></label>
          <input class="form-control" name="key[<?= e($p) ?>]" value="<?= $keySet ? e(ai_key_mask($c['key'])) : '' ?>" placeholder="<?= $keySet?'leave to keep':'paste key' ?>" autocomplete="off">
          <?php if ($keySet): ?><label class="chk" style="margin-top:4px;font-size:12px"><input type="checkbox" name="clearkey[<?= e($p) ?>]" value="1"> Clear key</label><?php endif; ?></div>
        <div class="ff"><label>Base URL override <span class="muted">(optional)</span></label><input class="form-control" name="base[<?= e($p) ?>]" value="<?= e($c['base']) ?>" placeholder="<?= e($reg['list_url'] ?: 'no live list endpoint') ?>"></div>
        <div class="ff" style="align-self:end">
          <button class="btn small secondary" type="submit" name="refresh" value="<?= e($p) ?>">↻ Refresh models</button>
        </div>
      </div>

      <label style="font-size:12.5px;font-weight:600;color:var(--muted)">Active models <?= $c['refreshed']?'<span class="muted" style="font-weight:400">· refreshed '.e(substr($c['refreshed'],0,10)).'</span>':'' ?></label>
      <div class="checkgrid" style="margin-top:4px">
        <?php foreach ($avail as $m): ?>
          <label class="chk"><input type="checkbox" name="active[<?= e($p) ?>][]" value="<?= e($m) ?>" <?= in_array($m, (array)$c['active'], true)?'checked':'' ?>> <?= e($m) ?></label>
        <?php endforeach; ?>
      </div>
      <?php if ($c['note']): ?><p class="muted" style="font-size:12px;margin:6px 0 0">ℹ <?= e($c['note']) ?></p>
      <?php elseif ($reg['list_url']===''): ?><p class="muted" style="font-size:12px;margin:6px 0 0">This provider has no public model-list API — the known models above are shown.</p>
      <?php elseif (empty($c['models'])): ?><p class="muted" style="font-size:12px;margin:6px 0 0">Save the key, then <strong>Refresh models</strong> to pull the live list (retired models drop off automatically).</p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div style="margin:6px 0 20px"><button class="btn" type="submit" name="_do" value="save">Save all providers</button></div>
</form>

<p class="muted" style="max-width:720px">These keys let the app use AI for features like CV keyword analysis. Outbound access to the provider must be allowed by your host — if a refresh fails, the known-model list is used so you can still pick a model. Keys are stored in the app database and shown masked.</p>
