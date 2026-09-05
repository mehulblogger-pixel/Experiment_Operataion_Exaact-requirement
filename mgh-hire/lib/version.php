<?php
// The single source of truth for the product version. The release script and
// any "About / support" screen read this, so a package can never be labelled
// differently from what the running app reports.
if (!defined('MGHHIRE_VERSION')) define('MGHHIRE_VERSION', '1.1.0');
