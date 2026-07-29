<?php
// The matrix, with every rule spelled out in a sentence underneath it. A grid of
// min/max/role columns is readable by the person who built it and by nobody
// else, and an approval matrix nobody can read is an approval matrix nobody
// trusts.
$r = $edit ?: [];
$showForm = $edit || $new;
$val = fn($k, $d = '') => htmlspecialchars((string)($r[$k] ?? $d), ENT_QUOTES);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/approvals">Approvals</a> › Rules</div>
<div class="master-head">
  <div><h1>Approval rules for deal stages</h1>
  <p class="sub" style="margin:2px 0 0">Who has to agree before a deal moves. With no rules here nothing is gated and the pipeline behaves exactly as it does today — a gate is something you choose, not something the software insists on.</p></div>
  <?php if (!$showForm): ?><a class="btn" href="/stage-gates?new=1">＋ New rule</a><?php endif; ?>
</div>

<?php if ($showForm): ?>
  <div class="panel" style="margin-top:16px">
    <h2 style="margin:0 0 10px;font-size:15px"><?= $edit ? 'Edit rule' : 'New rule' ?></h2>
    <form method="post" action="/stage-gate-save">
      <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
      <div class="form-grid">
        <div class="fg-full">
          <label class="form-label">Rule name</label>
          <input class="form-control" name="name" maxlength="140" required value="<?= $val('name') ?>"
                 placeholder="Large wins need the head of sales">
          <div class="muted" style="font-size:12px;margin-top:3px">This name is what appears on the deal when the gate stops it, so write it as the reason: “Large wins need the head of sales”, not “Rule 3”.</div>
        </div>

        <div>
          <label class="form-label">Applies to</label>
          <select class="form-control" name="entity_kind">
            <option value="OPPORTUNITY" <?= ($r['entity_kind'] ?? 'OPPORTUNITY') === 'OPPORTUNITY' ? 'selected' : '' ?>>Opportunities</option>
          </select>
          <div class="muted" style="font-size:12px;margin-top:3px">Leads win by converting into a customer, which is already its own act — gating them would gate the conversion, not the stage.</div>
        </div>

        <div>
          <label class="form-label">Pipeline</label>
          <select class="form-control" name="pipeline_id">
            <option value="0">Every pipeline</option>
            <?php foreach ($pipelines as $p): if (($p['entity_kind'] ?? '') !== 'OPPORTUNITY') continue; ?>
              <option value="<?= (int)$p['id'] ?>" <?= (int)($r['pipeline_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="muted" style="font-size:12px;margin-top:3px">“Every pipeline” also covers the ones you build later.</div>
        </div>

        <div>
          <label class="form-label">Which stage does it guard?</label>
          <select class="form-control" name="stage_kind">
            <option value="WON"  <?= ($r['stage_kind'] ?? 'WON') === 'WON'  ? 'selected' : '' ?>>Any winning stage</option>
            <option value="LOST" <?= ($r['stage_kind'] ?? '') === 'LOST' ? 'selected' : '' ?>>Any losing stage</option>
            <option value="OPEN" <?= ($r['stage_kind'] ?? '') === 'OPEN' ? 'selected' : '' ?>>Any open stage</option>
          </select>
        </div>

        <div>
          <label class="form-label">…or one named stage</label>
          <select class="form-control" name="stage_id">
            <option value="0">Use the kind above</option>
            <?php foreach ($stages as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= (int)($r['stage_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                <?= e($s['pipeline_name']) ?> — <?= e($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="muted" style="font-size:12px;margin-top:3px">A named stage wins over a kind when both could match.</div>
        </div>

        <div>
          <label class="form-label">From value</label>
          <input class="form-control" name="min_amount" type="number" step="0.01" min="0" value="<?= $val('min_amount', '0') ?>">
        </div>
        <div>
          <label class="form-label">Up to value</label>
          <input class="form-control" name="max_amount" type="number" step="0.01" min="0" value="<?= $val('max_amount', '0') ?>">
          <div class="muted" style="font-size:12px;margin-top:3px">Leave at 0 for “and above”. Bands can overlap; the narrowest rule is the one that applies.</div>
        </div>

        <div>
          <label class="form-label"><?= e(TH('sbu')) ?></label>
          <select class="form-control" name="sbu">
            <option value="">Any</option>
            <?php foreach ($sbus as $k => $lbl): ?>
              <option value="<?= e((string)$k) ?>" <?= (string)($r['sbu'] ?? '') === (string)$k ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="form-label">Approver — a named person</label>
          <select class="form-control" name="approver_user_id">
            <option value="0">Nobody in particular</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= (int)($r['approver_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                <?= e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="muted" style="font-size:12px;margin-top:3px">A named person is precise but stops when they are on leave. A role covers the desk rather than the individual.</div>
        </div>

        <div>
          <label class="form-label">…or anyone with this role</label>
          <select class="form-control" name="approver_role">
            <option value="">Anyone who may approve quotations</option>
            <?php foreach ($roles as $rk => $rl): ?>
              <option value="<?= e((string)$rk) ?>" <?= (string)($r['approver_role'] ?? '') === (string)$rk ? 'selected' : '' ?>><?= e((string)$rl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="fg-full">
          <label class="form-label" style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="active" value="1" <?= !isset($r['active']) || (int)$r['active'] === 1 ? 'checked' : '' ?>>
            <span>Rule is live</span>
          </label>
        </div>
      </div>
      <div style="margin-top:14px;display:flex;gap:8px">
        <button class="btn" type="submit">Save rule</button>
        <a class="btn secondary" href="/stage-gates">Cancel</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php if (!$rules): ?>
  <div class="panel" style="margin-top:16px">
    <p style="margin:0"><b>No rules, so nothing is gated.</b></p>
    <p class="muted" style="margin:6px 0 0;max-width:640px">
      Every deal moves the moment somebody drags it, exactly as before. The commonest first rule is one line:
      <b>wins above your average deal size need the sales head to agree</b> — it costs the team one click on the
      few deals that set the forecast, and nothing at all on the rest.
    </p>
    <?php if (!$showForm): ?><p style="margin:12px 0 0"><a class="btn" href="/stage-gates?new=1">＋ Write the first rule</a></p><?php endif; ?>
  </div>
<?php else: ?>
  <div class="panel" style="margin-top:16px;padding:0;overflow:hidden">
    <div class="dt-scroll">
      <table class="dt">
        <caption class="sr-only">Approval rules for deal stages</caption>
        <thead><tr>
          <th scope="col">Rule</th><th scope="col">Value band</th><th scope="col">Guards</th>
          <th scope="col">Approver</th><th scope="col" style="text-align:right">Used</th><th scope="col"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rules as $g): ?>
          <tr<?= (int)$g['active'] === 0 ? ' style="opacity:.55"' : '' ?>>
            <td>
              <b><?= e($g['name']) ?></b>
              <?php if ((int)$g['active'] === 0): ?><span class="pill">Off</span><?php endif; ?>
              <div class="muted" style="font-size:12px;margin-top:2px"><?= e(gate_explain($g)) ?></div>
            </td>
            <td><?= (float)$g['max_amount'] > 0
                    ? e(fmoney((float)$g['min_amount']) . ' – ' . fmoney((float)$g['max_amount']))
                    : ((float)$g['min_amount'] > 0 ? e(fmoney((float)$g['min_amount']) . '+') : '<span class="muted">Any</span>') ?></td>
            <td><?= (int)$g['stage_id'] > 0 ? e((string)$g['stage_name']) : e('Any ' . strtolower((string)$g['stage_kind']) . ' stage') ?></td>
            <td><?= !empty($g['approver_user_id'])
                    ? e(trim(((string)($g['first_name'] ?? '')) . ' ' . ((string)($g['last_name'] ?? ''))) ?: (string)$g['username'])
                    : (trim((string)$g['approver_role']) !== '' ? e((string)$g['approver_role']) : '<span class="muted">Quote approvers</span>') ?></td>
            <td style="text-align:right"><?= (int)$g['used'] ?></td>
            <td style="white-space:nowrap">
              <a class="btn small secondary" href="/stage-gates?edit=<?= (int)$g['id'] ?>">Edit</a>
              <form method="post" action="/stage-gate-delete" style="display:inline"
                    onsubmit="return confirm('Remove this rule? Deals it would have gated will move without approval.')">
                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                <button class="btn small secondary" type="submit">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="muted" style="margin-top:12px;font-size:12.5px">
    Where two rules could both apply, the narrowest wins: a rule naming one stage beats one naming a kind,
    a rule naming a <?= e(strtolower(TH('sbu'))) ?> beats one that takes any, and a rule with an upper limit beats an
    open-ended one. A rule that has already held real requests is switched off rather than deleted, so the history
    keeps its explanation.
  </p>
<?php endif; ?>
