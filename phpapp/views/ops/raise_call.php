<div class="crumbs"><a href="/">Home</a> › Raise inspection <?= e(Tl('call')) ?></div>
<div class="master-head">
  <div><h1>Raise an inspection <?= e(Tl('call')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Pick the <?= e(Tl('client')) ?>, then the contract the work is under — the <?= e(Tl('call')) ?> fills itself in from there.</p></div>
</div>

<!-- Step 1 — the client -->
<form method="get" action="/raise-call" class="panel" style="margin-top:14px">
  <label style="font-weight:600;display:block">1 · <?= e(T('client')) ?></label>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px">
    <select class="form-control searchable" name="client_id" onchange="this.form.submit()" style="min-width:min(320px,100%)">
      <option value="">— type or pick a <?= e(Tl('client')) ?> —</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $clientId == $c['id'] ? 'selected' : '' ?>><?= e(pname($c)) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn" type="submit">Show contracts</button></noscript>
  </div>
</form>

<?php if ($client): ?>
  <!-- Step 2 — the contract -->
  <div class="panel" style="margin-top:12px">
    <label style="font-weight:600;display:block">2 · Contract under <?= e(pname($client)) ?></label>

    <?php if (!$contracts): ?>
      <p class="muted" style="margin:10px 0 0">No open contract for this <?= e(Tl('client')) ?> yet. A contract is registered off an accepted <?= e(Tl('quote')) ?> — do that first, then the <?= e(Tl('call')) ?> is raised from it.</p>
      <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn secondary" href="/quotes?v=pending">See their <?= e(Tlp('quote')) ?></a>
        <a class="btn secondary" href="/call-new?client_id=<?= (int)$client['id'] ?>">Raise a direct <?= e(Tl('call')) ?> (no contract)</a>
      </div>

    <?php else: ?>
      <p class="muted" style="margin:6px 0 12px;font-size:13px">
        <?= count($contracts) ?> contract<?= count($contracts) === 1 ? '' : 's' ?> under this <?= e(Tl('client')) ?>.
        <?= count($contracts) === 1 ? 'Click it to raise the ' . e(Tl('call')) . '.' : 'Pick the one this ' . e(Tl('call')) . ' is against — each shows what it is for and how many ' . e(Tlp('call')) . ' already went under it.' ?>
      </p>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ($contracts as $ct):
          $os   = (string)($ct['open_status'] ?: 'OPEN');
          $desc = trim((string)($ct['quote_subject'] ?? '')) ?: trim((string)($ct['title'] ?? ''));
        ?>
        <a class="pickrow" href="/call-new?contract_id=<?= (int)$ct['id'] ?>&contract=<?= e(urlencode((string)$ct['contract_number'])) ?>">
          <div style="flex:1;min-width:0">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <b style="font-size:15px"><?= e($ct['contract_number']) ?></b>
              <span class="pill <?= $os === 'OPEN' ? 'p-ok' : ($os === 'PENDING' ? 'p-warn' : 'p-mut') ?>" style="font-size:11px"><?= e($os) ?></span>
            </div>
            <?php if ($desc !== ''): ?><div class="muted" style="font-size:13px;margin-top:2px"><?= e($desc) ?></div><?php endif; ?>
            <div class="muted" style="font-size:12px;margin-top:3px">
              <?php if (!empty($ct['quote_no'])): ?><?= e(Tl('quote')) ?> <?= e($ct['quote_no']) ?><?php if ((int)($ct['rev'] ?? 0) > 0): ?> r<?= (int)$ct['rev'] ?><?php endif; ?> · <?php endif; ?>
              <?php if (!empty($ct['end_date'])): ?>valid to <?= e(fdate($ct['end_date'])) ?> · <?php endif; ?>
              <b><?= (int)$ct['calls'] ?></b> <?= e(Tlp('call')) ?> raised<?php if (!empty($ct['last_call'])): ?> · last <?= e(fdate(substr((string)$ct['last_call'], 0, 10))) ?><?php endif; ?>
            </div>
          </div>
          <span class="btn small">Raise <?= e(Tl('call')) ?> ▶</span>
        </a>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:12px;border-top:1px dashed var(--line);padding-top:10px">
        <a class="btn secondary small" href="/call-new?client_id=<?= (int)$client['id'] ?>">None of these — raise a direct <?= e(Tl('call')) ?> (no contract)</a>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<style>
  .pickrow{text-decoration:none;color:inherit;border:1px solid var(--line);border-left:3px solid var(--accent);
    border-radius:10px;padding:13px 15px;display:flex;gap:12px;align-items:center;transition:.12s}
  .pickrow:hover{background:var(--soft,#f6f8fb);border-color:var(--accent)}
</style>
