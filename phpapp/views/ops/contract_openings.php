<div class="crumbs"><a href="/">Home</a> › Contract openings</div>
<div class="master-head">
  <div><h1>Contract openings</h1>
    <p class="sub" style="margin:2px 0 0">Won orders waiting for their contract number to be opened. A manager <b>endorses</b>, then the branch manager <b>approves</b> — then the order floats to operations.</p></div>
</div>

<?php if (!$rows): ?>
  <div class="panel" style="margin-top:16px"><p style="margin:0">Nothing is waiting. Every registered contract has been opened (or none is pending).</p></div>
<?php else: ?>
  <div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
    <div class="dt-scroll">
      <table class="dt">
        <thead><tr>
          <th>Contract</th><th><?= e(Tl('client')) ?></th><th>What it's for</th><th>Requested</th><th>Step</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $c):
          $endorsed = trim((string)($c['mgr_endorsed_at'] ?? '')) !== '';
          $step = $endorsed ? 'Awaiting branch-manager approval' : 'Awaiting manager endorsement';
          $desc = trim((string)($c['quote_subject'] ?? ''));
        ?>
          <tr>
            <td><b><?= e($c['contract_number'] ?: ('#' . (int)$c['id'])) ?></b>
              <?php if ($canSeeQuote && !empty($c['quote_id'])): ?><br><a class="muted" style="font-size:12px" href="/quote?id=<?= (int)$c['quote_id'] ?>#contract"><?= e($c['quote_no'] ?: 'quote') ?> ↗</a><?php endif; ?></td>
            <td><?= e($c['client_name'] ?: '—') ?></td>
            <td class="muted" style="font-size:13px"><?= e($desc !== '' ? $desc : '—') ?></td>
            <td class="muted" style="font-size:12.5px"><?= e($c['requested_by'] ?: '—') ?><?= !empty($c['requested_at']) ? '<br>' . e(fdate(substr((string)$c['requested_at'],0,10))) : '' ?></td>
            <td><span class="pill <?= $endorsed ? 'p-info' : 'p-warn' ?>" style="font-size:11px"><?= e($step) ?></span>
              <?php if ($endorsed): ?><br><span class="muted" style="font-size:11px">endorsed by <?= e($c['mgr_endorsed_by']) ?></span><?php endif; ?>
              <?php $coordWho = function_exists('call_coordinator_name') ? call_coordinator_name($c) : '';
                    if ($coordWho !== ''): ?><br><span class="muted" style="font-size:11px">👤 <?= e($coordWho) ?> to coordinate</span><?php endif; ?></td>
            <td class="num" style="white-space:nowrap">
              <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
                <?php if ($canEndorse && !$endorsed): ?>
                  <form method="post" action="/contract-open" style="display:inline;display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="do" value="endorse">
                    <?php // Forward to a coordinator in this office as part of endorsing —
                          // an office has several, so name the one who will own the calls. ?>
                    <?php if (!empty($myCoordinators)): ?>
                      <select name="coordinator_id" class="form-control" style="min-width:150px;font-size:12px" title="Forward to a coordinator in <?= e($myOfficeName ?: T('office')) ?>">
                        <option value="">— forward to (optional) —</option>
                        <?php foreach ($myCoordinators as $co): ?>
                          <option value="<?= (int)$co['id'] ?>"><?= e($co['name']) ?><?= !empty($co['role']) ? ' · ' . e(strtolower(str_replace('_',' ',$co['role']))) : '' ?></option>
                        <?php endforeach; ?>
                      </select>
                    <?php endif; ?>
                    <button class="btn small" type="submit">Endorse</button>
                  </form>
                <?php endif; ?>
                <?php if ($canApprove): ?>
                  <form method="post" action="/contract-open" style="display:inline">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="do" value="approve">
                    <button class="btn small" type="submit"<?= (!$endorsed && !is_master()) ? ' disabled title="A manager must endorse it first"' : '' ?>>Approve &amp; open</button>
                  </form>
                <?php endif; ?>
                <?php if ($canEndorse || $canApprove): ?>
                  <form method="post" action="/contract-open" style="display:inline" onsubmit="return confirm('Reject opening this contract number?')">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="do" value="reject">
                    <button class="btn small danger" type="submit">Reject</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="muted" style="margin-top:10px;font-size:12.5px">The endorser and the approver must be two different people (the Master Admin standing in for a one-person branch is the only exception).</p>
<?php endif; ?>
