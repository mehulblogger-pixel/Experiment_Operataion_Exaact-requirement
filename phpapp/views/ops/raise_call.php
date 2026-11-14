<div class="master-head">
  <div><h1>Raise an inspection <?= e(Tl('call')) ?></h1>
    <p class="sub" style="margin:2px 0 0">Pick the <?= e(Tl('client')) ?>, then the contract the work is under — the <?= e(Tl('call')) ?> fills itself in from there.</p></div>
</div>

<?php // Owner, 13 Nov 2026 (bounce list 1.6): contracts that were just opened and have no
      // work order yet, offered first — one press each. ?>
<?php if (!empty($fresh)): ?>
<div class="panel" style="margin-top:14px;border-left:3px solid var(--info,#2563eb)">
  <b>Newly opened — no <?= e(Tl('call')) ?> raised yet</b>
  <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
    <?php foreach (array_slice($fresh, 0, 12) as $fc): $fdesc = trim((string)($fc['quote_subject'] ?? '')) ?: trim((string)($fc['title'] ?? '')); ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <div style="flex:1 1 220px;min-width:0"><b><?= e($fc['contract_number']) ?></b> · <?= e($fc['client_name'] ?: '—') ?>
          <?php if ($fdesc !== ''): ?><div class="muted" style="font-size:13px"><?= e($fdesc) ?></div><?php endif; ?>
          <div class="muted" style="font-size:12px">opened <?= e(fdate(substr((string)$fc['opened_at'], 0, 10))) ?></div></div>
        <a class="btn small" style="white-space:nowrap" href="/call-new?contract_id=<?= (int)$fc['id'] ?>&amp;contract=<?= e(urlencode((string)$fc['contract_number'])) ?>">▶ Raise <?= e(Tl('call')) ?></a>
        <?php //  Owner, 14 Nov 2026 (option D): "no work yet — remind me on <date>". ?>
        <details class="fold rc-snooze" style="flex-basis:100%">
          <summary style="font-size:12.5px">No work yet — remind me later</summary>
          <form method="post" action="/raise-call" class="fold-body" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
            <input type="hidden" name="do" value="snooze"><input type="hidden" name="contract_id" value="<?= (int)$fc['id'] ?>">
            <div class="ff" style="margin:0"><label for="rcs-<?= (int)$fc['id'] ?>">Back on this list on</label>
              <input class="form-control" type="date" id="rcs-<?= (int)$fc['id'] ?>" name="until" required min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>"<?= trim((string)($fc['end_date'] ?? '')) !== '' ? ' max="' . e(substr((string)$fc['end_date'], 0, 10)) . '"' : '' ?>></div>
            <div class="ff" style="margin:0;flex:1;min-width:180px"><label for="rcn-<?= (int)$fc['id'] ?>">Note <span class="muted">optional</span></label>
              <input class="form-control" id="rcn-<?= (int)$fc['id'] ?>" name="note" maxlength="300" placeholder="e.g. client says work starts after the shutdown"></div>
            <button class="btn small secondary" type="submit">Put aside</button>
          </form>
        </details>
      </div>
    <?php endforeach; ?>
    <?php if (count($fresh) > 12): ?><p class="muted" style="margin:0;font-size:12.5px">+<?= count($fresh) - 12 ?> more — pick the <?= e(Tl('client')) ?> below.</p><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php //  What is not on the list, and why — so nothing looks lost (owner, 14 Nov 2026, options C + D). ?>
<?php if (!empty($freshLater) || !empty($freshSnoozed)): ?>
<div class="panel" style="margin-top:10px;font-size:13px">
  <?php if (!empty($freshLater)): ?><div><b>Starting later</b> <span class="muted">— each joins the list on its start date</span>
    <?php foreach (array_slice($freshLater, 0, 8) as $fl): ?><div class="muted"><?= e($fl['contract_number']) ?> · <?= e($fl['client_name'] ?: '—') ?> · starts <?= e(fdate(substr((string)$fl['start_date'], 0, 10))) ?></div><?php endforeach; ?></div><?php endif; ?>
  <?php if (!empty($freshSnoozed)): ?><div style="margin-top:6px"><b>Put aside</b> <span class="muted">— no work yet</span>
    <?php foreach (array_slice($freshSnoozed, 0, 8) as $fs): ?>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><span class="muted"><?= e($fs['contract_number']) ?> · <?= e($fs['client_name'] ?: '—') ?> · back on <?= e(fdate((string)$fs['fresh_snooze_until'])) ?><?= trim((string)$fs['fresh_snooze_note']) !== '' ? ' — ' . e($fs['fresh_snooze_note']) : '' ?> (<?= e($fs['fresh_snoozed_by']) ?>)</span>
        <form method="post" action="/raise-call" style="display:inline"><input type="hidden" name="do" value="snooze"><input type="hidden" name="contract_id" value="<?= (int)$fs['id'] ?>"><input type="hidden" name="until" value=""><button class="btn small btn-ghost" type="submit">Back on the list now</button></form></div>
    <?php endforeach; ?></div><?php endif; ?>
</div>
<?php endif; ?>

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
        <div class="pickrow">
          <div style="flex:1;min-width:0">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <b style="font-size:15px"><?= e($ct['contract_number']) ?></b>
              <span class="pill <?= $os === 'OPEN' ? 'p-ok' : ($os === 'PENDING' ? 'p-warn' : 'p-mut') ?>" style="font-size:11px"><?= e($os) ?></span>
            </div>
            <?php if ($desc !== ''): ?><div class="muted" style="font-size:13px;margin-top:2px"><?= e($desc) ?></div><?php endif; ?>
            <div class="muted" style="font-size:12px;margin-top:3px">
              <?php if (!empty($ct['quote_no'])): ?><?= e(Tl('quote')) ?> <?= e($ct['quote_no']) ?><?php if ((int)($ct['rev'] ?? 0) > 0): ?> r<?= (int)$ct['rev'] ?><?php endif; ?> · <?php endif; ?>
              <?php if (!empty($ct['end_date'])): ?>valid to <?= e(fdate($ct['end_date'])) ?> · <?php endif; ?>
              <?php if ((int)$ct['calls'] > 0): ?>
                <a href="/calls?contract=<?= e(urlencode((string)$ct['contract_number'])) ?>"><b><?= (int)$ct['calls'] ?></b> <?= e(Tlp('call')) ?> raised</a><?php if (!empty($ct['last_call'])): ?> · last <?= e(fdate(substr((string)$ct['last_call'], 0, 10))) ?><?php endif; ?>
              <?php else: ?>
                no <?= e(Tlp('call')) ?> yet
              <?php endif; ?>
            </div>
          </div>
          <a class="btn small" href="/call-new?contract_id=<?= (int)$ct['id'] ?>&contract=<?= e(urlencode((string)$ct['contract_number'])) ?>">Raise <?= e(Tl('call')) ?> ▶</a>
        </div>
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
