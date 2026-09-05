<?php
// Pipeline editor — rename, reorder, add, activate/deactivate stages.
require_can('pipeline.edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = post('do');
    if ($do === 'add') {
        $maxSeq = (int)db()->query("SELECT COALESCE(MAX(seq),0) m FROM stages")->fetch()['m'];
        db()->prepare("INSERT INTO stages (seq,name,kind,active) VALUES (?,?,?,1)")
            ->execute([$maxSeq+10, post('name','New stage'), post('kind','step')]);
        flash('Stage added.');
    } elseif ($do === 'save') {
        $ids = $_POST['sid'] ?? [];
        foreach ($ids as $sid) {
            $sid = (int)$sid;
            db()->prepare("UPDATE stages SET name=?, seq=?, kind=?, active=? WHERE id=?")
                ->execute([
                    trim($_POST['name'][$sid] ?? ''),
                    (int)($_POST['seq'][$sid] ?? 0),
                    preg_replace('/[^a-z]/','', $_POST['kind'][$sid] ?? 'step') ?: 'step',
                    isset($_POST['active'][$sid]) ? 1 : 0,
                    $sid,
                ]);
        }
        flash('Pipeline saved.');
    }
    redirect('?p=pipeline');
}

$stages = stages_all(false);
$kinds = ['step'=>'Step','gate'=>'Decision gate','interview'=>'Interview','offer'=>'Offer','terminal'=>'Final (hired)'];
layout_top('Pipeline');
?>
<div class="card">
  <h2 class="mt0">Hiring pipeline</h2>
  <p class="muted">These are the steps every candidate moves through. This ships with your approved Recruitment &amp; Selection flow — rename, reorder (change the number) or switch steps off to fit any client. <b>Lower number = earlier step.</b></p>
  <form method="post">
    <input type="hidden" name="do" value="save"><?= csrf_field() ?>
    <table>
      <tr><th style="width:70px">Order</th><th>Stage name</th><th style="width:170px">Type</th><th style="width:70px">On</th></tr>
      <?php foreach ($stages as $s): $i=(int)$s['id']; ?>
        <tr>
          <td><input type="hidden" name="sid[]" value="<?= $i ?>"><input name="seq[<?= $i ?>]" type="number" value="<?= (int)$s['seq'] ?>" style="width:64px"></td>
          <td><input name="name[<?= $i ?>]" value="<?= e($s['name']) ?>"></td>
          <td><select name="kind[<?= $i ?>]"><?php foreach ($kinds as $k=>$lbl): ?><option value="<?= $k ?>" <?= $s['kind']===$k?'selected':'' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></td>
          <td style="text-align:center"><input type="checkbox" name="active[<?= $i ?>]" <?= $s['active']?'checked':'' ?> style="width:auto"></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <div style="margin-top:14px"><button class="btn">Save pipeline</button></div>
  </form>
</div>

<div class="card">
  <h2>Add a stage</h2>
  <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
    <input type="hidden" name="do" value="add"><?= csrf_field() ?>
    <div style="min-width:240px"><label>Name</label><input name="name" placeholder="e.g. Background check"></div>
    <div style="min-width:170px"><label>Type</label><select name="kind"><?php foreach ($kinds as $k=>$lbl): ?><option value="<?= $k ?>"><?= e($lbl) ?></option><?php endforeach; ?></select></div>
    <div><button class="btn ghost">Add stage</button></div>
  </form>
</div>
<?php layout_bottom();
