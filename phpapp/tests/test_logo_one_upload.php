<?php
// Owner, 25 Oct 2026: "Logo was already updated during the company setting but
// under Branding and Theme it again asked for the Logo." One upload on Your
// company now serves the header too; Branding's own upload only overrides it.
t_section('Logo — given once on Your company, used in the header too');

$own0 = (string) setting_get('logo_data', ''); $co0 = (string) setting_get('quote_lh_logo', '');
try {
    $jpg = base64_encode("\xFF\xD8\xFF\xE0" . str_repeat('x', 20));
    setting_set('logo_data', ''); setting_set('quote_lh_logo', '');
    t_eq(logo_src(), '', 'no logo anywhere: none in the header');
    setting_set('quote_lh_logo', $jpg);
    t_eq(logo_src(), 'data:image/jpeg;base64,' . $jpg, 'the company logo shows in the header without a second upload');
    t_ok(strpos(logo_html(), '<img src="data:image/jpeg;base64,') === 0, '…as a real image');
    setting_set('logo_data', 'data:image/png;base64,AAAA');
    t_eq(logo_src(), 'data:image/png;base64,AAAA', 'a logo uploaded under Branding still wins for the header');
    setting_set('logo_data', ''); setting_set('quote_lh_logo', 'not base64 <script>');
    t_eq(logo_src(), '', 'a damaged company logo value is never put in the page');

    $v = (string) file_get_contents(__DIR__ . '/../views/ops/settings.php');
    t_ok(strpos($v, 'Your company logo, from <a href="/company-profile">Your company</a>') !== false,
         'Branding shows the company logo and says nothing more is needed');
} finally { setting_set('logo_data', $own0); setting_set('quote_lh_logo', $co0); }
