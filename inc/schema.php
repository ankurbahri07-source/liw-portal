<?php
declare(strict_types=1);

/** Portable schema (MySQL in production, SQLite in tests). Idempotent: safe to run on every start. */
function ensure_schema(): void
{
    static $done = false;
    if ($done) return;
    $sqlite = (cfg()['db_driver'] ?? 'mysql') === 'sqlite';
    $pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $opt = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $ine = 'CREATE TABLE IF NOT EXISTS ';
    $st = [
        $ine . "portal_users (id $pk, username VARCHAR(60) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, mobile VARCHAR(15) NULL,
            password_hash VARCHAR(255) NOT NULL, is_owner TINYINT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1,
            must_change TINYINT NOT NULL DEFAULT 0, sv INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, last_login DATETIME NULL)$opt",
        $ine . "portal_access (id $pk, user_id INT NOT NULL, app VARCHAR(20) NOT NULL, role VARCHAR(20) NOT NULL,
            perms VARCHAR(255) NOT NULL DEFAULT '', UNIQUE (user_id, app))$opt",
        $ine . "portal_audit (id $pk, actor_id INT NULL, action VARCHAR(40) NOT NULL, detail VARCHAR(500) NOT NULL DEFAULT '',
            ip VARCHAR(45) NULL, created_at DATETIME NOT NULL)$opt",
        $ine . "portal_login_attempts (id $pk, username VARCHAR(60) NOT NULL, ip VARCHAR(45) NOT NULL, created_at DATETIME NOT NULL)$opt",
        $ine . "portal_settings (k VARCHAR(50) NOT NULL PRIMARY KEY, v TEXT NULL)$opt",
    ];
    foreach ($st as $s) db()->exec($s);
    $done = true;
}
