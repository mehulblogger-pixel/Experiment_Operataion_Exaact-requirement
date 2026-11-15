<?php
$pdo = db();
$pdo->beginTransaction(); echo "begin: ", var_export($pdo->inTransaction(), true), "\n";
tenant_signup_migrate(); echo "after tenant_signup_migrate: ", var_export($pdo->inTransaction(), true), "\n";
if (!$pdo->inTransaction()) $pdo->beginTransaction();
$pdo->exec("CREATE TABLE IF NOT EXISTS tenant_requests (id INT)"); echo "after bare CREATE TABLE IF NOT EXISTS (table exists): ", var_export($pdo->inTransaction(), true), "\n";
if (!$pdo->inTransaction()) $pdo->beginTransaction();
ensure_column('tenant_requests', 'ip', "VARCHAR(60) DEFAULT ''"); echo "after ensure_column (exists): ", var_export($pdo->inTransaction(), true), "\n";
if ($pdo->inTransaction()) $pdo->rollBack();
