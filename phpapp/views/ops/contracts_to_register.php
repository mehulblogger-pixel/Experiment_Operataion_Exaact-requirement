<?php
//  CONTRACTS TO REGISTER — Accounts' own list (owner, 13 Nov 2026, bounce list 3.1).
//
//  Everything that is waiting for a contract number, whatever it came from:
//    · won deals handed over without a quotation — registered RIGHT HERE, in one
//      press (the form used to live on the deal page, which the standard Finance
//      login may not open);
//    · accepted quotations — opened from here; their one-press registration is on
//      the quotation, which Accounts can open.
//  Behind the right Accounts already hold (crm.contract.register). It shows about a
//  deal only what the dashboard already showed them: reference, name, customer, value.
$deals = $deals ?? []; $quotes = $quotes ?? []; $offices = $offices ?? [];
$total = count($deals) + count($quotes);
?>
<div class="master-head">
  <div><h1>Contracts to register</h1>
    <p class="sub" style="margin:2px 0 0;max-width:760px">Won business waiting for its contract number. After you register one, the message tells you who approves it next.</p></div>
</div>

<?php if (!$total): ?>
  <div class="panel" style="margin-top:16px"><p style="margin:0">Nothing is waiting. Every won <?= e(Tl('quote')) ?> and every won deal handed to Accounts has its contract number.</p></div>
<?php endif; ?>

<?php if ($deals): ?>
<section aria-labelledby="ctr-deals" style="margin-top:16px">
  <h2 class="tab-sub" id="ctr-deals" style="margin:0 0 4px">Won deals — no <?= e(Tl('quote')) ?> <span class="muted">(<?= count($deals) ?>)</span></h2>
  <p class="sub" style="margin:0 0 10px">Sales won these directly, or the <?= e(Tl('client')) ?> sent an order without asking for a <?= e(Tl('quote')) ?>. One press registers the contract and gives it the next number.</p>
  <?php foreach ($deals as $d): $cust = $d['partner_disp'] ?: $d['partner_legal'] ?: $d['partner_name'] ?: '—'; ?>
    <div class="panel" id="deal-<?= (int)$d['id'] ?>" style="margin-bottom:10px">
      <div style="display:flex;gap:10px 18px;flex-wrap:wrap;align-items:baseline">
        <b><?= e($d['ref']) ?></b>
        <span><?= e($d['name']) ?></span>
        <span class="muted" style="margin-left:auto">Sent to Accounts <?= e(fdate(substr((string)$d['sent_to_accounts_at'], 0, 10))) ?></span>
      </div>
      <div class="kv-grid" style="margin-top:8px">
        <div><span class="k"><?= e(T('client')) ?></span><?= e($cust) ?></div>
        <div><span class="k">Agreed value</span><?= (float)$d['value'] ? e(fmoney($d['value'])) : '—' ?></div>
      </div>
      <form method="post" action="/opportunity-contract" style="margin-top:12px">
        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="from" value="list">
        <input type="hidden" name="auto_contract" value="1"><input type="hidden" name="branch_id" value="<?= (int)($d['office_id'] ?? 0) ?: '' ?>">
        <button class="btn" type="submit">✓ Register the contract — next number</button>
      </form>
      <details class="fold" style="margin-top:10px">
        <summary>Enter it differently <span class="sub">type the <?= e(Tl('client')) ?>'s own number, set the dates or the branch</span></summary>
        <div class="fold-body">
          <form method="post" action="/opportunity-contract">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="from" value="list">
            <div class="form-grid" style="gap:12px 16px">
              <div><label for="ctr-no-<?= (int)$d['id'] ?>">Contract number</label><input class="form-control" id="ctr-no-<?= (int)$d['id'] ?>" name="contract_number" maxlength="80" required placeholder="The number they gave"></div>
              <div><label for="ctr-br-<?= (int)$d['id'] ?>">Branch</label>
                <select class="form-control" id="ctr-br-<?= (int)$d['id'] ?>" name="branch_id">
                  <option value="">— as on the deal —</option>
                  <?php foreach ($offices as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === (int)($d['office_id'] ?? 0) ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
                </select></div>
              <div><label for="ctr-sd-<?= (int)$d['id'] ?>">Start date</label><input class="form-control" id="ctr-sd-<?= (int)$d['id'] ?>" type="date" name="start_date"></div>
              <div><label for="ctr-ed-<?= (int)$d['id'] ?>">End date</label><input class="form-control" id="ctr-ed-<?= (int)$d['id'] ?>" type="date" name="end_date"></div>
            </div>
            <button class="btn secondary" style="margin-top:12px" type="submit">Register with these details</button>
          </form>
        </div>
      </details>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($quotes): ?>
<section aria-labelledby="ctr-quotes" style="margin-top:20px">
  <h2 class="tab-sub" id="ctr-quotes" style="margin:0 0 4px">Accepted <?= e(Tlp('quote')) ?> <span class="muted">(<?= count($quotes) ?>)</span></h2>
  <p class="sub" style="margin:0 0 10px">Open one — its contract is registered in one press on the <?= e(Tl('quote')) ?> itself.</p>
  <div class="panel" style="padding:0;overflow:hidden">
    <div class="dt-scroll">
      <table class="dt">
        <thead><tr><th><?= e(T('quote')) ?></th><th><?= e(TH('client')) ?></th><th>What it's for</th><th class="num">Total</th><th>Accepted</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($quotes as $q): ?>
          <tr>
            <td><b><?= e($q['quote_no']) ?></b><?= (int)$q['rev'] ? ' <span class="muted">r' . (int)$q['rev'] . '</span>' : '' ?></td>
            <td><?= e($q['client_name'] ?: '—') ?></td>
            <td class="muted" style="font-size:13px"><?= e($q['subject'] ?: '—') ?></td>
            <td class="num"><?= e(fmoney($q['total_amount'])) ?></td>
            <td class="muted" style="font-size:12.5px"><?= trim((string)($q['accepted_date'] ?? '')) !== '' ? e(fdate(substr((string)$q['accepted_date'], 0, 10))) : '—' ?></td>
            <td class="num"><a class="btn small" href="/quote?id=<?= (int)$q['id'] ?>#contract">Open &amp; register</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php endif; ?>
