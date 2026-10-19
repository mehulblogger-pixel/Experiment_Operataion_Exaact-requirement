<?php
require __DIR__ . '/plib.php';
H('smoke');
[$id, $r] = new_quote('MM'); show('MM quote-new', $r); echo "    ", qline($id), "\n";
$r = R('INSP', 'quotes'); show('INSP quotes', $r);
