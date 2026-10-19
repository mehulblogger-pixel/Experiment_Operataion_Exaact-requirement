<?php
// P1 — quote line model, formulas, rounding, currency, validity; P2 — line<->site pairing.
putenv('OPS_MAIL_ENABLED');
require __DIR__ . '/harness.php';
$bdm = mkuser('bdm1', 'BUSINESS_DEV_MANAGER');
echo "\n== P1 schema ==\n";
show('quotations columns', implode(',', t_columns('quotations')));
show('quote_lines columns', implode(',', t_columns('quote_lines')));
show('quote_locations columns', implode(',', t_columns('quote_locations')));
show('charge units (lookup charge_unit)', lk_options_or('charge_unit', CHARGE_UNITS));
show('payment terms master', lk_options_or('payment_term', PAYMENT_TERMS));

echo "\n== P1 formulas (through quote-new handler) ==\n";
$q = new_quote($bdm, [['Inspection', '3', '3333.33'], ['Discount', '1', '-999.99']]);
show('3 x 3333.33 + 1 x -999.99 @18%', qrow($q));
show('  lines', ops_all("SELECT line_no,qty,unit,rate,amount FROM quote_lines WHERE quote_id=?", [$q]));
$q = new_quote($bdm, [['Rounding', '0.333', '100.01']], ['gst_pct' => '18']);
show('qty 0.333 x 100.01 (qty col is DECIMAL(12,2))', ops_all("SELECT qty,rate,amount FROM quote_lines WHERE quote_id=?", [$q]));
show('  header', qrow($q));
$q = new_quote($bdm, [['Rounding GST', '1', '0.05']], ['gst_pct' => '18']);
show('1 x 0.05 @18% (GST 0.009 → rounded?)', qrow($q));
$q = new_quote($bdm, [['Zero', '1', '0']]);
show('all-zero quote (1 x 0)', qrow($q));
$q = new_quote($bdm, []);
show('quote with NO lines', qrow($q));
$q = new_quote($bdm, [['Disc only', '1', '-100'], ['Work', '1', '100']]);
show('discount exactly equal to work (total 0)', qrow($q));
$q = new_quote($bdm, [['USD job', '2', '500']], ['currency' => 'USD', 'gst_pct' => '0']);
$row = ops_one("SELECT currency,total_amount FROM quotations WHERE id=?", [$q]);
show('currency USD stored', $row);
show('  amount_in_words_inr on the PDF for that USD total', amount_in_words_inr((float) $row['total_amount']));
show('  cur_sym() used on screen totals', cur_sym());
$q = new_quote($bdm, [['Odd currency', '1', '10']], ['currency' => 'NOT-A-CURRENCY-CODE']);
show('currency free text "NOT-A-CURRENCY-CODE" (column VARCHAR(8))', ops_val("SELECT currency FROM quotations WHERE id=?", [$q]) ?: '(refused)');
$q = new_quote($bdm, [['Neg validity', '1', '10']], ['validity_days' => '-5']);
show('validity_days -5', ops_val("SELECT validity_days FROM quotations WHERE id=?", [$q]));
db()->prepare("UPDATE quotations SET status='SENT', sent_at=? WHERE id=?")->execute([date('c', strtotime('-400 days')), $q]);
show('  quote_validity() of a 400-day-old SENT quote with validity -5', quote_validity(crm_quote_get($q)));
$q = new_quote($bdm, [['Adv', '1', '10']], ['advance_pct' => '100.5']);
show('advance 100.5%', $q ? 'accepted' : 'refused');
$q = new_quote($bdm, [['Qty zero', '0', '5000']]);
show('qty 0 x 5000 line', $q ? ops_all("SELECT qty,rate,amount FROM quote_lines WHERE quote_id=?", [$q]) : 'refused');
$q = new_quote($bdm, [['Unit', '1', '10', 'BANANA']]);
show('unit code "BANANA" (not in master)', $q ? ops_val("SELECT unit FROM quote_lines WHERE quote_id=?", [$q]) : 'refused');

echo "\n== P2 line <-> site pairing ==\n";
$siteA = ['loc_id' => [''], 'loc_label' => ['Site A'], 'loc_type' => ['SITE'], 'loc_line1' => ['Plot 1'], 'loc_city' => ['Pune'], 'loc_state' => ['MH'], 'loc_pin' => ['411001']];
$qa = new_quote($bdm, [['Line on A', '1', '100']], $siteA);
$locA = (int) ops_val("SELECT id FROM quote_locations WHERE quote_id=?", [$qa]);
$siteB = ['loc_id' => [''], 'loc_label' => ['Site B'], 'loc_type' => ['SITE'], 'loc_line1' => ['Plot 9'], 'loc_city' => ['Surat'], 'loc_state' => ['GJ'], 'loc_pin' => ['395001']];
$qb = new_quote($bdm, [['Line', '1', '100']], $siteB);
$locB = (int) ops_val("SELECT id FROM quote_locations WHERE quote_id=?", [$qb]);
show("quote A id=$qa site id", $locA); show("quote B id=$qb site id", $locB);
// Edit quote B: keep its own site, but point its line at quote A's site, and a second line at a non-existent site.
$post = ['office_id' => (string) probe_office(), 'client_name' => 'Probe Client Pvt Ltd', 'subject' => 'Probe', 'gst_pct' => '18', 'validity_days' => '30', 'currency' => 'INR', 'advance_pct' => '0',
    'loc_id' => [$locB], 'loc_label' => ['Site B'], 'loc_type' => ['SITE'], 'loc_line1' => ['Plot 9'], 'loc_city' => ['Surat'], 'loc_state' => ['GJ'], 'loc_pin' => ['395001'],
    'l_desc' => ['Points at quote A site', 'Points at site 999999', 'Own site'], 'l_qty' => ['1', '1', '1'], 'l_rate' => ['100', '100', '100'],
    'l_unit' => ['MANDAY', 'MANDAY', 'MANDAY'], 'l_service' => ['', '', ''], 'l_sbu' => ['', '', ''], 'l_order' => ['LINE', 'LINE', 'LINE'],
    'l_location' => [$locA, 999999, $locB]];
$r = route($bdm, 'quote-edit', 'POST', ['id' => $qb], $post);
show('edit B flash', flashes($r));
show('quote B lines after edit (location_id / location text)', ops_all("SELECT description, location_id, location FROM quote_lines WHERE quote_id=?", [$qb]));
// Edit quote B with loc_id = quote A's site id (try to hijack/overwrite another quote's site)
$post2 = $post; $post2['loc_id'] = [$locA]; $post2['loc_label'] = ['HIJACK']; $post2['l_location'] = [$locA, '', ''];
$r = route($bdm, 'quote-edit', 'POST', ['id' => $qb], $post2);
show('edit B posting loc_id = A\'s site', flashes($r));
show('  quote A site row after', ops_one("SELECT id,quote_id,label FROM quote_locations WHERE id=?", [$locA]));
show('  quote B sites after', ops_all("SELECT id,quote_id,label FROM quote_locations WHERE quote_id=?", [$qb]));
// Two sites, one line with no site picked
$two = ['loc_id' => ['', ''], 'loc_label' => ['S1', 'S2'], 'loc_type' => ['SITE', 'SITE'], 'loc_line1' => ['a', 'b'], 'loc_city' => ['X', 'Y'], 'loc_state' => ['', ''], 'loc_pin' => ['', '']];
$q2 = new_quote($bdm, [['No site chosen', '1', '100', 'MANDAY', '']], $two);
show('two sites + one line without a site: saved?', ops_all("SELECT description, location_id FROM quote_lines WHERE quote_id=?", [$q2]));
