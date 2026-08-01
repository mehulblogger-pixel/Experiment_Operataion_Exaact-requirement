<?php
// =========================================================================
//  CLOUD WORKSPACE REGISTRY (sample)
//
//  Copy this file to  tenants.php  to run the app in cloud / SaaS mode, or —
//  easier — switch cloud mode on from Settings → Cloud workspaces, which writes
//  tenants.php for you and lets you add workspaces from the screen.
//
//  How it works: the subdomain on the incoming request picks the workspace's
//  own database. The base domain itself is the control install, where you add
//  and manage workspaces. Each workspace is a full, independent install with its
//  own admin, licence and data.
//
//  For each workspace you first create the database + user in cPanel → MySQL
//  Databases, and the subdomain in cPanel → Subdomains (pointing at THIS app
//  folder). Then list it below (or add it from the screen).
// =========================================================================
return [
    // The domain workspaces live under. The base domain (and www.) is the
    // control install; anything.<base_domain> is a workspace.
    'base_domain' => 'operations.example.com',

    // Extra hostnames that also mean the control install (optional).
    'aliases' => [],

    'tenants' => [
        // A MySQL workspace (production):
        'acme' => [
            'company' => 'Acme Inspection Pvt Ltd',
            'status'  => 'active',              // or 'suspended'
            'db' => [
                'host' => 'localhost',
                'name' => 'youracct_acme',
                'user' => 'youracct_acme',
                'pass' => 'the-database-password',
            ],
        ],

        // A SQLite workspace (handy for testing):
        // 'demo' => [
        //     'company' => 'Demo Workspace',
        //     'status'  => 'active',
        //     'sqlite'  => __DIR__ . '/tenant-demo.sqlite',
        // ],
    ],
];
