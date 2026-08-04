<?php $type = $type ?? []; $zipOk = $zipOk ?? false; ?>
<div class="crumbs"><a href="/">Home</a> › <a href="/report-types">Report types</a> › <a href="/report-builder?type=<?= (int)$type['id'] ?>"><?= e($type['name'] ?? '') ?></a> › Build from a file</div>
<div class="master-head"><div>
  <h1>Build the form from your file</h1>
  <p class="sub" style="margin:2px 0 0">Upload your ordinary Word report format. The app reads it and builds the entry form
    by itself — you don't insert any codes or match any keys.</p>
</div></div>

<?php if (!$zipOk): ?>
<div class="msg msg-warning" style="margin-top:14px">This server is missing the <code>zip</code>/<code>DOM</code> PHP extensions, so a plain
  file cannot be read automatically here. Ask your host to enable them, or use the <a href="/report-templates">template + {{token}}</a> method.</div>
<?php endif; ?>

<div class="panel settings-form" style="max-width:640px;margin-top:14px">
  <form method="post" action="/report-autoform?type=<?= (int)$type['id'] ?>" enctype="multipart/form-data">
    <div class="ff ff-wide">
      <label>Your report format (Word .docx)</label>
      <input class="form-control" type="file" name="tpl" accept=".docx" required>
      <small class="muted">The same file the report is normally typed into — with its headings, table and “Label:” lines.</small>
    </div>
    <button class="btn" type="submit" style="margin-top:12px" <?= $zipOk ? '' : 'disabled' ?>>Read the file &amp; detect fields</button>
  </form>
  <div class="muted" style="font-size:12.5px;margin-top:12px;line-height:1.7">
    <b>What it picks up</b><br>
    • “Label:” lines with a blank after them → a field, named from the label.<br>
    • A two-column table (label | blank) → one field per row.<br>
    • A table with headings and empty rows → a repeatable table.<br>
    You get a review screen next to tweak or drop anything before the form is created. Your file's layout is kept, so the
    finished report comes back in the very same format.
  </div>
</div>
