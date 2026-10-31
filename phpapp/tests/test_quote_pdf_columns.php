<?php
// Owner, 31 Oct 2026 (test book MOD-SAL, screenshot): on the quotation PDF the
// Rate and Amount columns were printed on top of each other. Six columns had
// only six edges, so both ended at the right margin. Checked by rendering a
// quotation (72 × ₹1,20,000) to an image; this guards that the two never share
// a right edge again.
t_section('Quotation PDF — Rate and Amount have their own columns');
$q = ['created_at' => date('c'), 'validity_days' => 30, 'client_name' => 'TB Client', 'contact_name' => '', 'contact_email' => '', 'contact_mobile' => '',
      'site_location' => '', 'location_type' => '', 'subject' => 'Test', 'currency' => 'INR', 'subtotal' => 8640000, 'gst_pct' => 18,
      'gst_amount' => 1555200, 'total_amount' => 10195200, 'id' => 1, 'quote_no' => 'Q-1'];
$lines = [['description' => 'Man Month Deputation', 'service_type' => '', 'order_type' => '', 'qty' => 72, 'unit' => 'MAN_MONTH', 'rate' => 120000, 'amount' => 8640000]];
$pdf = quote_pdf_build($q, $lines, []);
$raw = is_string($pdf) ? $pdf : '';
// Find where "120,000" and "8,640,000" start, and estimate where each ends.
$x = function ($s) use ($raw) { return preg_match('/([\d.]+) [\d.]+ Td \(' . preg_quote($s, '/') . '\) Tj/', $raw, $m) ? (float) $m[1] : null; };
$p = new SimplePDF();
$rateX = $x('120,000'); $amtX = $x('8,640,000');
t_ok($rateX !== null && $amtX !== null, 'both figures are printed');
if ($rateX !== null && $amtX !== null)
    t_ok($rateX + $p->strWidth('120,000', 9, false) + 8 < $amtX, 'the rate ends well before the amount begins (no overlap)');
