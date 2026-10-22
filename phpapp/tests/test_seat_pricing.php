<?php
// Seat prices are configurable, not hardcoded (owner, 22 Oct 2026: "keep it
// open to configure in admin panel, don't hardcode it"). Unset means not set —
// 0 — never an invented figure; saved values take effect.
t_section('seat prices are configurable — none built in');

setting_set('billing_price_field_month', '');
setting_set('billing_price_portal_month', '');
setting_set('billing_portal_free', '');
$c = superadmin_seat_classes();
t_eq($c['FIELD']['price'],  0, 'an unset field-seat price is 0 (not set), not an invented ₹499');
t_eq($c['PORTAL']['price'], 0, 'an unset portal price is 0 (not set), not an invented ₹99');
t_eq($c['PORTAL']['free'],  0, 'no free portal seats are assumed');

// Saving new values (as the Billing form now does) takes effect.
setting_set('billing_price_field_month', '650');
setting_set('billing_price_portal_month', '150');
setting_set('billing_portal_free', '5');
$c = superadmin_seat_classes();
t_eq($c['FIELD']['price'],  650, 'a saved field-seat price takes effect');
t_eq($c['PORTAL']['price'], 150, 'a saved portal price takes effect');
t_eq($c['PORTAL']['free'],  5,   'a saved free-bundle size takes effect');

// A portal price of 0 is allowed (keep the portal free above the bundle).
setting_set('billing_price_portal_month', '0');
$c = superadmin_seat_classes();
t_eq($c['PORTAL']['price'], 0, 'portal can be set to 0 (free)');

// Leave them as we found them (not set).
setting_set('billing_price_field_month', '');
setting_set('billing_price_portal_month', '');
setting_set('billing_portal_free', '');
