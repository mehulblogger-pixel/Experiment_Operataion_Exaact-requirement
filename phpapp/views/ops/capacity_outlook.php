<div class="crumbs"><a href="/">Home</a> › <a href="/schedule">Scheduling board</a> › Capacity outlook</div>
<div class="master-head">
  <div><h1>Capacity outlook</h1>
    <p class="sub" style="margin:2px 0 0">Free inspector-days (supply) against unallocated demand, week by week — so a
      shortage is visible before it bites. The numbers are plain counts you can trace, not a black-box forecast.</p></div>
</div>

<section class="card" style="margin-bottom:14px">
  <form method="get" action="/capacity-outlook" style="display:flex;gap:8px;align-items:end">
    <div class="ff"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="ff"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="ff"><button class="btn" type="submit">Show</button></div>
  </form>
</section>

<section class="card">
  <table class="grid">
    <tr><th>Week</th><th>From</th><th>To</th><th style="text-align:right">Free inspector-days</th><th style="text-align:right">Unallocated demand</th><th style="text-align:right">Gap</th><th>Signal</th></tr>
    <?php foreach ($buckets as $b): ?>
    <tr<?= $b['tight'] ? ' style="background:rgba(180,35,43,.06)"' : '' ?>>
      <td><?= e($b['label']) ?></td>
      <td><?= e($b['from']) ?></td>
      <td><?= e($b['to']) ?></td>
      <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int)$b['supply'] ?></td>
      <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int)$b['demand'] ?></td>
      <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int)$b['gap'] > 0 ? '+'.(int)$b['gap'] : (int)$b['gap'] ?></td>
      <td><span class="badge <?= $b['tight'] ? 'RED' : 'GREEN' ?>"><?= $b['tight'] ? 'Tight' : 'OK' ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$buckets): ?><tr><td colspan="7">No data in this window.</td></tr><?php endif; ?>
  </table>
  <p class="sub" style="margin-top:10px">“Free inspector-days” counts available working days across resources in your scope. “Unallocated demand” counts open calls whose required date falls in the week and which carry no assigned resource yet.</p>
</section>
