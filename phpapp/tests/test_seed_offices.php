<?php
// The demo seed used to INSERT its offices unconditionally, and the unload never
// removed them — so unload → load (or a forced reload) piled up duplicate
// Ahmedabad / Mumbai / Pune offices. Seeding is now idempotent by office code.
t_section('demo seed does not duplicate offices on reload');

seed_demo(true);
seed_demo(true);   // a second load must NOT add a second set

foreach (['AMD','MUM','PUN'] as $code) {
    t_eq((int) ops_val("SELECT COUNT(*) FROM offices WHERE code=?", [$code]), 1,
        "reloading the demo keeps a single $code office");
}
// And exactly one Ahmedabad is still the primary (is_ahmedabad) office.
t_ok((int) ops_val("SELECT COUNT(*) FROM offices WHERE is_ahmedabad=1") <= 1,
    'at most one office is flagged as the Ahmedabad/primary office');
