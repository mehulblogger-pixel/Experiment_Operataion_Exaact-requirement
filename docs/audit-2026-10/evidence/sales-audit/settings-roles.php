<?php
require '/home/user/Experiment_Operataion_Exaact-requirement/phpapp/tests/lib.php'; require '/home/user/Experiment_Operataion_Exaact-requirement/phpapp/tests/bootstrap.php';
foreach (array_keys(ORG_ROLES) as $r) { $p = role_perms($r); if (in_array('mod.settings.view',$p,true)) echo "$r has mod.settings.view\n"; }
