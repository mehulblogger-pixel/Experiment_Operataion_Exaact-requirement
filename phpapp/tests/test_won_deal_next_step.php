<?php
// Owner, 12 Nov 2026 (test book SAL-030): "Message 'Won. Raise the order when the
// customer confirms.' Banner 'Won — TB Refinery Ltd said yes.' Just check if any
// conflict." It did: the message said the customer had not confirmed, the banner
// said they had, and the screen's next step was "Send to Accounts". Now the message
// and the banner say the one next step the screen offers.
t_section('A won deal says one next step, the same everywhere');
t_eq(opp_won_next(['partner_id' => 5]), 'Next: on the Actions tab, send it to Accounts to register the contract.', 'won, customer on file: send it to Accounts');
t_ok(strpos(opp_won_next([]), 'add the customer') !== false, 'won with no customer yet: add the customer first');
t_ok(strpos(opp_won_next(['partner_id' => 5, 'sent_to_accounts_at' => date('c')]), 'with Accounts') !== false, 'already sent: it is with Accounts');
t_ok(strpos(opp_won_next(['partner_id' => 5, 'call_id' => 9]), 'work order is raised') !== false, 'an order raised: says so');
$src = (string)file_get_contents(__DIR__ . '/../lib/opportunities.php') . file_get_contents(__DIR__ . '/../views/ops/opportunity_detail.php');
t_ok(strpos($src, 'Raise the order when the customer confirms') === false, 'the contradicting message is gone');
t_ok(substr_count($src, 'opp_won_next(') >= 3, 'the message and the banner both use the same next step');
