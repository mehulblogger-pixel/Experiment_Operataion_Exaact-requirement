<?php
// Seat prices are configurable, not hardcoded. The Billing screen now saves the
// field-seat, portal-seat and free-bundle values, and the seat-class costing
// reads them. This tests that round-trip: defaults when unset, and saved values
// taking effect.
t_section('seat prices are configurable (Billing screen)');

// Defaults: field falls back to 499 (0 means "use the recommended default"),
// portal 99, ten free portal seats.
setting_set('billing_price_field_month', '0');
setting_set('billing_price_portal_month', '99');
setting_set('billing_portal_free', '10');
$c = superadmin_seat_classes();
t_eq($c['FIELD']['price'],  499, 'field seat defaults to 499');
t_eq($c['PORTAL']['price'], 99,  'portal token defaults to 99');
t_eq($c['PORTAL']['free'],  10,  'ten portal seats are free by default');

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

// Leave the defaults as we found them.
setting_set('billing_price_field_month', '0');
setting_set('billing_price_portal_month', '99');
setting_set('billing_portal_free', '10');
