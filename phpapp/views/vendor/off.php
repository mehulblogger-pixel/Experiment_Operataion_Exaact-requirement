<?php
// The vendor portal is off, so this address behaves as though it never existed:
// a plain 404, no shell, nothing that advertises a vendor portal waiting to be
// switched on. The status code is already 404 by the time this is included.
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Not found</title>
<link rel="stylesheet" href="/assets/css/app.css">
<?= theme_style_tag() ?>
</head><body>
<div style="max-width:520px;margin:70px auto;padding:0 22px">
  <h1 style="font-size:22px;margin:0 0 10px">Not found</h1>
  <p style="color:var(--muted);font-size:14.5px;line-height:1.6">There is nothing at this address.</p>
</div>
</body></html>
