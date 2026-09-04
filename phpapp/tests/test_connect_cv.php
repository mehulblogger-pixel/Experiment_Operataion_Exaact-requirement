<?php
// CV-assisted prefill — deterministic mapping of CV text to the taxonomy.
t_section('connect CV prefill (K0+)');
connect_tax_graph_migrate(); connect_geo_migrate();
$cv = "Suresh Kumar\nSenior Pressure Vessel Inspector with 10 years in Oil & Gas.\n"
    . "Skills: ultrasonic testing, radiographic testing, welding inspection.\n"
    . "Certifications: CSWIP 3.1.\nBased in Jamnagar, Gujarat, India.";
$scan = connect_cv_scan($cv);
$names = array_map(fn($x)=>$x['name'], $scan['expertise']);
$byName = []; foreach($scan['expertise'] as $x) $byName[$x['name']]=$x;
t_ok(in_array('Pressure Vessel Inspector',$names,true),'detects the role Pressure Vessel Inspector');
t_eq($byName['Pressure Vessel Inspector']['relation'] ?? '','PRIMARY_ROLE','the first role becomes PRIMARY_ROLE');
t_ok(in_array('Ultrasonic Testing',$names,true) || in_array('Radiographic Testing',$names,true),'detects NDT methods as skills');
t_ok((bool)array_filter($scan['expertise'], fn($x)=>$x['kind']==='CERTIFICATION'),'detects a certification (CSWIP)');
t_ok(($scan['base_place']['name'] ?? '') === 'Jamnagar','detects the base city Jamnagar');
t_eq($scan['base_place']['state_name'] ?? '','Gujarat','the detected city carries its state');
// no false positives on empty / unrelated text
$empty = connect_cv_scan('the quick brown fox jumps over');
t_eq(count($empty['expertise']),0,'unrelated text yields no false suggestions');
// text extraction: plain text passes through
t_ok(strpos(connect_cv_extract_text('hello world CV','text/plain','cv.txt'),'hello world') !== false,'plain-text extraction passes through');
