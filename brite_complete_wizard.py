#!/usr/bin/env python3
"""
BriteScheduler Completion Wizard
Run from the ROOT of the qualitypro/britescheduler checkout.

What it does:
- Makes a timestamped backup of files it changes.
- Creates a centralized PHP/PDO application layer.
- Creates MySQL migrations for multi-tenancy, clients, contractors,
  services, scheduling, invoices, payments, notifications, and audit logs.
- Creates authentication and tenant/role enforcement.
- Creates JSON API endpoints and functional dashboard pages.
- Replaces the unsafe legacy event endpoints with tenant-scoped endpoints.
- Can execute pending MySQL migrations using the mysql CLI.
- Never stores card numbers; payments are ledger/provider-reference records only.

Requires: PHP 8.1+, PDO MySQL, MySQL 8+, Python 3.9+.
"""

from __future__ import annotations
import argparse
import getpass
import os
import shutil
import subprocess
import sys
import textwrap
from datetime import datetime
from pathlib import Path

ROOT = Path.cwd()
STAMP = datetime.now().strftime("%Y%m%d-%H%M%S")
BACKUP = ROOT / ".brite-wizard-backup" / STAMP

FILES = {}

FILES[".env.example"] = r"""APP_ENV=development
APP_URL=http://localhost
APP_TIMEZONE=America/New_York
SESSION_NAME=britescheduler

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=britescheduler
DB_USER=britescheduler
DB_PASS=change-me
"""

FILES[".gitignore"] = r""".env
.env.local
.brite-wizard-backup/
vendor/
*.log
.DS_Store
"""

FILES["app/bootstrap.php"] = r"""<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function load_env(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if (getenv($k) === false) putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}
load_env($root . '/.env');

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(getenv('SESSION_NAME') ?: 'britescheduler');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Csrf.php';

/**
 * Build an absolute URL inside the BriteScheduler installation.
 */
function app_url(string $path = ''): string
{
    $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost/dashboard', '/');

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}
"""

FILES["app/Database.php"] = r"""<?php
declare(strict_types=1);

final class Database {
    private static ?PDO $pdo = null;

    public static function connection(): PDO {
        if (self::$pdo) return self::$pdo;
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'britescheduler';
        $user = getenv('DB_USER') ?: 'britescheduler';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }
}
"""

FILES["app/Http.php"] = r"""<?php
declare(strict_types=1);

function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
function request_data(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $v = json_decode(file_get_contents('php://input'), true);
        return is_array($v) ? $v : [];
    }
    return $_POST;
}
function require_method(string ...$allowed): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $allowed, true)) {
        json_response(['error' => 'Method not allowed'], 405);
    }
}
function int_or_null(mixed $v): ?int {
    return ($v === null || $v === '') ? null : (int)$v;
}
"""

FILES["app/Csrf.php"] = r"""<?php
declare(strict_types=1);

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function verify_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        json_response(['error' => 'Invalid CSRF token'], 419);
    }
}
"""

FILES["app/Auth.php"] = r"""<?php
declare(strict_types=1);

final class Auth {
    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        $q = Database::connection()->prepare(
            "SELECT id,email,first_name,last_name,status FROM users WHERE id=? AND status='active'"
        );
        $q->execute([(int)$_SESSION['user_id']]);
        return $q->fetch() ?: null;
    }

    public static function requireUser(): array {
        $u = self::user();
        if (!$u) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) json_response(['error'=>'Unauthenticated'], 401);
            header('Location: /sign-in.php'); exit;
        }
        return $u;
    }

    public static function memberships(int $userId): array {
        $q = Database::connection()->prepare(
            "SELECT m.tenant_id,m.role,t.name,t.slug
             FROM tenant_memberships m JOIN tenants t ON t.id=m.tenant_id
             WHERE m.user_id=? AND m.status='active' AND t.status='active' ORDER BY t.name"
        );
        $q->execute([$userId]);
        return $q->fetchAll();
    }

    public static function tenant(): array {
        $u = self::requireUser();
        $memberships = self::memberships((int)$u['id']);
        if (!$memberships) json_response(['error'=>'No active tenant membership'], 403);

        $requested = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : (int)$memberships[0]['tenant_id'];
        foreach ($memberships as $m) {
            if ((int)$m['tenant_id'] === $requested) {
                $_SESSION['tenant_id'] = $requested;
                $_SESSION['tenant_role'] = $m['role'];
                return $m;
            }
        }
        $_SESSION['tenant_id'] = (int)$memberships[0]['tenant_id'];
        $_SESSION['tenant_role'] = $memberships[0]['role'];
        return $memberships[0];
    }

    public static function tenantId(): int { return (int)self::tenant()['tenant_id']; }

    public static function requireRole(string ...$roles): array {
        $t = self::tenant();
        if (!in_array($t['role'], $roles, true)) json_response(['error'=>'Forbidden'], 403);
        return $t;
    }
}
"""

FILES["migrations/001_core.sql"] = r"""CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(100) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NOT NULL UNIQUE,
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/New_York',
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone VARCHAR(40) NULL,
  status ENUM('active','invited','disabled') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tenant_memberships (
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','admin','scheduler','accounting','contractor','client') NOT NULL,
  status ENUM('active','invited','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id,user_id),
  CONSTRAINT fk_tm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_tm_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  company_name VARCHAR(190) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  address1 VARCHAR(190) NULL,
  address2 VARCHAR(190) NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  postal_code VARCHAR(30) NULL,
  notes TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_clients_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_clients_tenant_name (tenant_id,last_name,first_name),
  INDEX idx_clients_tenant_email (tenant_id,email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contractors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  business_name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  hourly_rate DECIMAL(12,2) NULL,
  color VARCHAR(20) NULL,
  skills TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_contractors_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_contractors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_contractors_tenant_name (tenant_id,last_name,first_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_services_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_services_tenant (tenant_id,active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NULL,
  service_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  status ENUM('tentative','scheduled','confirmed','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  location VARCHAR(255) NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appt_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_appt_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
  CONSTRAINT fk_appt_creator FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_appt_tenant_start (tenant_id,starts_at),
  INDEX idx_appt_client (tenant_id,client_id,starts_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointment_contractors (
  appointment_id BIGINT UNSIGNED NOT NULL,
  contractor_id BIGINT UNSIGNED NOT NULL,
  status ENUM('assigned','accepted','declined','completed') NOT NULL DEFAULT 'assigned',
  pay_amount DECIMAL(12,2) NULL,
  PRIMARY KEY (appointment_id,contractor_id),
  CONSTRAINT fk_ac_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_ac_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contractor_availability (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  contractor_id BIGINT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NULL,
  available_date DATE NULL,
  starts_at TIME NOT NULL,
  ends_at TIME NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_ca_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_ca_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
  INDEX idx_ca_lookup (tenant_id,contractor_id,available_date,weekday)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  appointment_id BIGINT UNSIGNED NULL,
  invoice_number VARCHAR(50) NOT NULL,
  status ENUM('draft','sent','partial','paid','void','overdue') NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  balance_due DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_invoice_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_invoice_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_invoice_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  UNIQUE KEY uq_invoice_number (tenant_id,invoice_number),
  INDEX idx_invoice_status (tenant_id,status,due_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoice_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_item_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('pending','succeeded','failed','refunded','void') NOT NULL DEFAULT 'pending',
  method VARCHAR(50) NULL,
  provider VARCHAR(50) NULL,
  provider_reference VARCHAR(190) NULL,
  paid_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
  CONSTRAINT fk_payment_client FOREIGN KEY (client_id) REFERENCES clients(id),
  INDEX idx_payment_tenant_date (tenant_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  type VARCHAR(80) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notif_user (tenant_id,user_id,read_at,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_tenant (tenant_id,created_at),
  CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
"""

FILES["migrations/002_legacy_events_import.sql"] = r"""-- Compatibility migration.
-- The legacy `events` database/table is intentionally NOT auto-imported because its
-- user_id values cannot safely be mapped to tenant membership without operator review.
-- Export legacy events first, map them to a tenant/client/contractor, then import them
-- into `appointments`. This migration exists as an explicit audit marker.
SELECT 1;
"""

FILES["api/auth/login.php"] = r"""<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_method('POST'); verify_csrf();
$d=request_data(); $email=strtolower(trim($d['email']??'')); $password=$d['password']??'';
$q=Database::connection()->prepare("SELECT * FROM users WHERE email=? AND status='active'");
$q->execute([$email]); $u=$q->fetch();
if(!$u || !password_verify($password,$u['password_hash'])) json_response(['error'=>'Invalid email or password'],422);
session_regenerate_id(true); $_SESSION['user_id']=(int)$u['id'];
Database::connection()->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$u['id']]);
$m=Auth::memberships((int)$u['id']); if($m) $_SESSION['tenant_id']=(int)$m[0]['tenant_id'];
json_response(['ok'=>true,'redirect'=>app_url('/dashboard.v2.php')]);
"""

FILES["api/auth/logout.php"] = r"""<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_method('POST'); verify_csrf();
$_SESSION=[]; session_destroy(); json_response(['ok'=>true]);
"""

FILES["api/clients.php"] = r"""<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$u=Auth::requireUser(); $tid=Auth::tenantId(); $pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='GET'){
  $q=$pdo->prepare("SELECT * FROM clients WHERE tenant_id=? ORDER BY last_name,first_name"); $q->execute([$tid]);
  json_response(['clients'=>$q->fetchAll()]);
}
verify_csrf(); Auth::requireRole('owner','admin','scheduler','accounting'); $d=request_data();
if($_SERVER['REQUEST_METHOD']==='POST'){
  $q=$pdo->prepare("INSERT INTO clients(tenant_id,company_name,first_name,last_name,email,phone,address1,address2,city,state,postal_code,notes,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $q->execute([$tid,$d['company_name']??null,trim($d['first_name']??''),trim($d['last_name']??''),$d['email']??null,$d['phone']??null,$d['address1']??null,$d['address2']??null,$d['city']??null,$d['state']??null,$d['postal_code']??null,$d['notes']??null,$d['status']??'active']);
  json_response(['ok'=>true,'id'=>(int)$pdo->lastInsertId()],201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
  $id=(int)($d['id']??0);
  $q=$pdo->prepare("UPDATE clients SET company_name=?,first_name=?,last_name=?,email=?,phone=?,address1=?,address2=?,city=?,state=?,postal_code=?,notes=?,status=? WHERE id=? AND tenant_id=?");
  $q->execute([$d['company_name']??null,$d['first_name']??'',$d['last_name']??'',$d['email']??null,$d['phone']??null,$d['address1']??null,$d['address2']??null,$d['city']??null,$d['state']??null,$d['postal_code']??null,$d['notes']??null,$d['status']??'active',$id,$tid]);
  json_response(['ok'=>true]);
}
json_response(['error'=>'Method not allowed'],405);
"""

FILES["api/contractors.php"] = r"""<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$u=Auth::requireUser(); $tid=Auth::tenantId(); $pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $q=$pdo->prepare("SELECT * FROM contractors WHERE tenant_id=? ORDER BY last_name,first_name");$q->execute([$tid]);json_response(['contractors'=>$q->fetchAll()]);
}
verify_csrf();Auth::requireRole('owner','admin','scheduler');$d=request_data();
if($_SERVER['REQUEST_METHOD']==='POST'){
 $q=$pdo->prepare("INSERT INTO contractors(tenant_id,first_name,last_name,business_name,email,phone,hourly_rate,color,skills,status) VALUES(?,?,?,?,?,?,?,?,?,?)");
 $q->execute([$tid,$d['first_name']??'',$d['last_name']??'',$d['business_name']??null,$d['email']??null,$d['phone']??null,$d['hourly_rate']??null,$d['color']??null,$d['skills']??null,$d['status']??'active']);
 json_response(['ok'=>true,'id'=>(int)$pdo->lastInsertId()],201);
}
if($_SERVER['REQUEST_METHOD']==='PUT'){
 $id=(int)($d['id']??0);$q=$pdo->prepare("UPDATE contractors SET first_name=?,last_name=?,business_name=?,email=?,phone=?,hourly_rate=?,color=?,skills=?,status=? WHERE id=? AND tenant_id=?");
 $q->execute([$d['first_name']??'',$d['last_name']??'',$d['business_name']??null,$d['email']??null,$d['phone']??null,$d['hourly_rate']??null,$d['color']??null,$d['skills']??null,$d['status']??'active',$id,$tid]);json_response(['ok'=>true]);
}
json_response(['error'=>'Method not allowed'],405);
"""

FILES["api/services.php"] = r"""<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
Auth::requireUser();$tid=Auth::tenantId();$pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='GET'){$q=$pdo->prepare("SELECT * FROM services WHERE tenant_id=? ORDER BY name");$q->execute([$tid]);json_response(['services'=>$q->fetchAll()]);}
verify_csrf();Auth::requireRole('owner','admin','scheduler');$d=request_data();
if($_SERVER['REQUEST_METHOD']==='POST'){$q=$pdo->prepare("INSERT INTO services(tenant_id,name,description,duration_minutes,price,active) VALUES(?,?,?,?,?,?)");$q->execute([$tid,$d['name']??'',$d['description']??null,(int)($d['duration_minutes']??60),$d['price']??0,!empty($d['active'])?1:0]);json_response(['ok'=>true,'id'=>(int)$pdo->lastInsertId()],201);}
json_response(['error'=>'Method not allowed'],405);
"""

FILES["api/appointments.php"] = r"""<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

$u   = Auth::requireUser();
$tid = Auth::tenantId();
$pdo = Database::connection();

function appointment_datetime(string $value, string $field): string
{
    $value = trim($value);

    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
        DateTimeInterface::ATOM
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);

        if ($dt && $dt->format($format) === $value) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    try {
        $dt = new DateTime($value);
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        throw new RuntimeException("Invalid {$field}");
    }
}

function validate_appointment_status(string $status): string
{
    $allowed = [
        'tentative',
        'scheduled',
        'confirmed',
        'in_progress',
        'completed',
        'cancelled',
        'no_show'
    ];

    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException('Invalid appointment status');
    }

    return $status;
}

function tenant_reference(
    PDO $pdo,
    string $table,
    int $tenantId,
    $id,
    string $label
): ?int {
    $id = int_or_null($id);

    if ($id === null) {
        return null;
    }

    $allowedTables = ['clients', 'services', 'contractors'];

    if (!in_array($table, $allowedTables, true)) {
        throw new RuntimeException('Invalid reference table');
    }

    $q = $pdo->prepare(
        "SELECT id FROM {$table}
         WHERE id=? AND tenant_id=?"
    );

    $q->execute([$id, $tenantId]);

    if (!$q->fetchColumn()) {
        throw new RuntimeException("Invalid {$label}");
    }

    return $id;
}

function validate_contractors(
    PDO $pdo,
    int $tenantId,
    array $contractorIds
): array {
    $result = [];

    foreach ($contractorIds as $contractorId) {

        $contractorId = (int)$contractorId;

        if ($contractorId <= 0) {
            continue;
        }

        tenant_reference(
            $pdo,
            'contractors',
            $tenantId,
            $contractorId,
            'contractor'
        );

        $result[$contractorId] = $contractorId;
    }

    return array_values($result);
}

function check_contractor_conflicts(
    PDO $pdo,
    int $tenantId,
    array $contractorIds,
    string $startsAt,
    string $endsAt,
    ?int $excludeAppointmentId = null
): void {

    foreach ($contractorIds as $contractorId) {

        $sql = "
            SELECT
                a.id,
                a.title,
                a.starts_at,
                a.ends_at
            FROM appointments a
            INNER JOIN appointment_contractors ac
                ON ac.appointment_id=a.id
            WHERE
                a.tenant_id=?
                AND ac.contractor_id=?
                AND a.status NOT IN ('cancelled','no_show')
                AND a.starts_at < ?
                AND a.ends_at > ?
        ";

        $params = [
            $tenantId,
            $contractorId,
            $endsAt,
            $startsAt
        ];

        if ($excludeAppointmentId !== null) {
            $sql .= " AND a.id<>?";
            $params[] = $excludeAppointmentId;
        }

        $sql .= " LIMIT 1";

        $q = $pdo->prepare($sql);
        $q->execute($params);

        $conflict = $q->fetch();

        if ($conflict) {
            throw new RuntimeException(
                'Contractor scheduling conflict with "' .
                $conflict['title'] .
                '" (' .
                $conflict['starts_at'] .
                ' - ' .
                $conflict['ends_at'] .
                ')'
            );
        }
    }
}

function check_contractor_availability(
    PDO $pdo,
    int $tenantId,
    array $contractorIds,
    string $startsAt,
    string $endsAt
): void {

    $start = new DateTime($startsAt);
    $end   = new DateTime($endsAt);

    /*
     * Availability rows are single-day rules.
     * Do not allow an appointment to cross midnight.
     */
    if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
        throw new RuntimeException(
            'Appointments assigned to contractors cannot cross midnight'
        );
    }

    $date      = $start->format('Y-m-d');
    $startTime = $start->format('H:i:s');
    $endTime   = $end->format('H:i:s');

    // PHP: Sunday=0 through Saturday=6.
    $weekday = (int)$start->format('w');

    foreach ($contractorIds as $contractorId) {

        /*
         * Determine whether this contractor has availability
         * configured at all. Contractors with no rules remain
         * unrestricted for backward compatibility.
         */
        $configured = $pdo->prepare("
            SELECT COUNT(*)
            FROM contractor_availability
            WHERE tenant_id=?
              AND contractor_id=?
        ");

        $configured->execute([
            $tenantId,
            $contractorId
        ]);

        if ((int)$configured->fetchColumn() === 0) {
            continue;
        }

        /*
         * Date-specific UNAVAILABLE rules take highest precedence.
         * Any overlap blocks the appointment.
         */
        $blocked = $pdo->prepare("
            SELECT id
            FROM contractor_availability
            WHERE tenant_id=?
              AND contractor_id=?
              AND available_date=?
              AND is_available=0
              AND starts_at < ?
              AND ends_at > ?
            LIMIT 1
        ");

        $blocked->execute([
            $tenantId,
            $contractorId,
            $date,
            $endTime,
            $startTime
        ]);

        if ($blocked->fetchColumn()) {
            throw new RuntimeException(
                'Contractor is unavailable during the requested time'
            );
        }

        /*
         * If date-specific AVAILABLE rules exist for this date,
         * they override the normal weekly schedule.
         */
        $dateRules = $pdo->prepare("
            SELECT COUNT(*)
            FROM contractor_availability
            WHERE tenant_id=?
              AND contractor_id=?
              AND available_date=?
              AND is_available=1
        ");

        $dateRules->execute([
            $tenantId,
            $contractorId,
            $date
        ]);

        if ((int)$dateRules->fetchColumn() > 0) {

            $allowed = $pdo->prepare("
                SELECT id
                FROM contractor_availability
                WHERE tenant_id=?
                  AND contractor_id=?
                  AND available_date=?
                  AND is_available=1
                  AND starts_at <= ?
                  AND ends_at >= ?
                LIMIT 1
            ");

            $allowed->execute([
                $tenantId,
                $contractorId,
                $date,
                $startTime,
                $endTime
            ]);

            if (!$allowed->fetchColumn()) {
                throw new RuntimeException(
                    'Appointment is outside contractor availability'
                );
            }

            continue;
        }

        /*
         * Otherwise require the appointment to fit completely
         * inside a recurring weekly AVAILABLE rule.
         */
        $weekly = $pdo->prepare("
            SELECT id
            FROM contractor_availability
            WHERE tenant_id=?
              AND contractor_id=?
              AND available_date IS NULL
              AND weekday=?
              AND is_available=1
              AND starts_at <= ?
              AND ends_at >= ?
            LIMIT 1
        ");

        $weekly->execute([
            $tenantId,
            $contractorId,
            $weekday,
            $startTime,
            $endTime
        ]);

        if (!$weekly->fetchColumn()) {
            throw new RuntimeException(
                'Appointment is outside contractor availability'
            );
        }
    }
}

function validate_times(string $startsAt, string $endsAt): void
{
    if (strtotime($endsAt) <= strtotime($startsAt)) {
        throw new RuntimeException(
            'Appointment end time must be after start time'
        );
    }
}

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $start = $_GET['start'] ?? date('Y-m-01 00:00:00');
    $end   = $_GET['end']   ?? date('Y-m-t 23:59:59');

    $q = $pdo->prepare("
        SELECT
            a.*,
            c.first_name client_first,
            c.last_name client_last,
            s.name service_name,
            s.price service_price,
            s.duration_minutes,
            GROUP_CONCAT(DISTINCT ac.contractor_id) contractor_ids,
            GROUP_CONCAT(DISTINCT ct.color) contractor_colors
        FROM appointments a

        LEFT JOIN clients c
            ON c.id=a.client_id
            AND c.tenant_id=a.tenant_id

        LEFT JOIN services s
            ON s.id=a.service_id
            AND s.tenant_id=a.tenant_id

        LEFT JOIN appointment_contractors ac
            ON ac.appointment_id=a.id

        LEFT JOIN contractors ct
            ON ct.id=ac.contractor_id
            AND ct.tenant_id=a.tenant_id

        WHERE
            a.tenant_id=?
            AND a.starts_at < ?
            AND a.ends_at > ?

        GROUP BY a.id
        ORDER BY a.starts_at
    ");

    $q->execute([$tid, $end, $start]);

    json_response([
        'appointments' => $q->fetchAll()
    ]);
}

/*
|--------------------------------------------------------------------------
| Mutations
|--------------------------------------------------------------------------
*/

verify_csrf();

Auth::requireRole(
    'owner',
    'admin',
    'scheduler',
    'contractor'
);

$d = request_data();

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $clientId = tenant_reference(
            $pdo,
            'clients',
            $tid,
            $d['client_id'] ?? null,
            'client'
        );

        $serviceId = tenant_reference(
            $pdo,
            'services',
            $tid,
            $d['service_id'] ?? null,
            'service'
        );

        $contractorIds = validate_contractors(
            $pdo,
            $tid,
            $d['contractor_ids'] ?? []
        );

        $startsAt = appointment_datetime(
            $d['starts_at'] ?? '',
            'start time'
        );

        $endsAt = appointment_datetime(
            $d['ends_at'] ?? '',
            'end time'
        );

        validate_times($startsAt, $endsAt);

        $status = validate_appointment_status(
            $d['status'] ?? 'scheduled'
        );

        check_contractor_availability(
            $pdo,
            $tid,
            $contractorIds,
            $startsAt,
            $endsAt
        );

        check_contractor_conflicts(
            $pdo,
            $tid,
            $contractorIds,
            $startsAt,
            $endsAt
        );

        $pdo->beginTransaction();

        $q = $pdo->prepare("
            INSERT INTO appointments(
                tenant_id,
                client_id,
                service_id,
                title,
                description,
                starts_at,
                ends_at,
                status,
                location,
                is_public,
                created_by
            )
            VALUES(?,?,?,?,?,?,?,?,?,?,?)
        ");

        $q->execute([
            $tid,
            $clientId,
            $serviceId,
            trim($d['title'] ?? 'Appointment'),
            $d['description'] ?? null,
            $startsAt,
            $endsAt,
            $status,
            $d['location'] ?? null,
            !empty($d['is_public']) ? 1 : 0,
            $u['id']
        ]);

        $id = (int)$pdo->lastInsertId();

        foreach ($contractorIds as $contractorId) {

            $x = $pdo->prepare("
                INSERT INTO appointment_contractors(
                    appointment_id,
                    contractor_id
                )
                VALUES(?,?)
            ");

            $x->execute([
                $id,
                $contractorId
            ]);
        }

        $pdo->commit();

        json_response([
            'ok' => true,
            'id' => $id
        ], 201);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_response([
            'error' => $e->getMessage()
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| PUT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

    $id = (int)($d['id'] ?? 0);

    try {

        $owned = $pdo->prepare("
            SELECT id
            FROM appointments
            WHERE id=? AND tenant_id=?
        ");

        $owned->execute([$id, $tid]);

        if (!$owned->fetchColumn()) {
            throw new RuntimeException(
                'Appointment not found'
            );
        }

        $clientId = tenant_reference(
            $pdo,
            'clients',
            $tid,
            $d['client_id'] ?? null,
            'client'
        );

        $serviceId = tenant_reference(
            $pdo,
            'services',
            $tid,
            $d['service_id'] ?? null,
            'service'
        );

        $contractorIds = validate_contractors(
            $pdo,
            $tid,
            $d['contractor_ids'] ?? []
        );

        $startsAt = appointment_datetime(
            $d['starts_at'] ?? '',
            'start time'
        );

        $endsAt = appointment_datetime(
            $d['ends_at'] ?? '',
            'end time'
        );

        validate_times($startsAt, $endsAt);

        $status = validate_appointment_status(
            $d['status'] ?? 'scheduled'
        );

        check_contractor_availability(
            $pdo,
            $tid,
            $contractorIds,
            $startsAt,
            $endsAt
        );

        check_contractor_conflicts(
            $pdo,
            $tid,
            $contractorIds,
            $startsAt,
            $endsAt,
            $id
        );

        $pdo->beginTransaction();

        $q = $pdo->prepare("
            UPDATE appointments
            SET
                client_id=?,
                service_id=?,
                title=?,
                description=?,
                starts_at=?,
                ends_at=?,
                status=?,
                location=?,
                is_public=?
            WHERE id=? AND tenant_id=?
        ");

        $q->execute([
            $clientId,
            $serviceId,
            $d['title'] ?? 'Appointment',
            $d['description'] ?? null,
            $startsAt,
            $endsAt,
            $status,
            $d['location'] ?? null,
            !empty($d['is_public']) ? 1 : 0,
            $id,
            $tid
        ]);

        $pdo->prepare("
            DELETE FROM appointment_contractors
            WHERE appointment_id=?
        ")->execute([$id]);

        foreach ($contractorIds as $contractorId) {

            $x = $pdo->prepare("
                INSERT INTO appointment_contractors(
                    appointment_id,
                    contractor_id
                )
                VALUES(?,?)
            ");

            $x->execute([
                $id,
                $contractorId
            ]);
        }

        $pdo->commit();

        json_response(['ok' => true]);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_response([
            'error' => $e->getMessage()
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $id = (int)($d['id'] ?? 0);

    $q = $pdo->prepare("
        DELETE FROM appointments
        WHERE id=? AND tenant_id=?
    ");

    $q->execute([$id, $tid]);

    if (!$q->rowCount()) {
        json_response([
            'error' => 'Appointment not found'
        ], 404);
    }

    json_response(['ok' => true]);
}

json_response([
    'error' => 'Method not allowed'
], 405);
"""

FILES["api/invoices.php"] = r"""<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

Auth::requireUser();

$tid = Auth::tenantId();
$pdo = Database::connection();

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $q = $pdo->prepare("
        SELECT
            i.*,
            CONCAT(c.first_name,' ',c.last_name) AS client_name,
            a.title AS appointment_title,
            a.starts_at AS appointment_starts_at,

            COALESCE((
                SELECT SUM(p.amount)
                FROM payments p
                WHERE p.invoice_id=i.id
                  AND p.tenant_id=i.tenant_id
                  AND p.status='succeeded'
            ),0) AS amount_paid

        FROM invoices i

        JOIN clients c
          ON c.id=i.client_id
         AND c.tenant_id=i.tenant_id

        LEFT JOIN appointments a
          ON a.id=i.appointment_id
         AND a.tenant_id=i.tenant_id

        WHERE i.tenant_id=?

        ORDER BY i.created_at DESC
    ");

    $q->execute([$tid]);

    json_response([
        'invoices'=>$q->fetchAll()
    ]);
}

/*
|--------------------------------------------------------------------------
| Mutations
|--------------------------------------------------------------------------
*/

verify_csrf();

Auth::requireRole(
    'owner',
    'admin',
    'accounting'
);

$d = request_data();

/*
|--------------------------------------------------------------------------
| POST — Generate invoice from appointment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $appointmentId =
        (int)($d['appointment_id'] ?? 0);

    if ($appointmentId <= 0) {
        json_response([
            'error'=>'Appointment required'
        ],422);
    }

    try {

        /*
         * Fetch everything needed from trusted database data.
         * We do NOT trust the browser for service price,
         * client ownership, or appointment status.
         */
        $q = $pdo->prepare("
            SELECT
                a.id,
                a.client_id,
                a.service_id,
                a.title,
                a.starts_at,
                a.ends_at,
                a.status,
                s.name AS service_name,
                s.price AS service_price
            FROM appointments a

            LEFT JOIN services s
              ON s.id=a.service_id
             AND s.tenant_id=a.tenant_id

            WHERE a.id=?
              AND a.tenant_id=?
            LIMIT 1
        ");

        $q->execute([
            $appointmentId,
            $tid
        ]);

        $appointment=$q->fetch();

        if (!$appointment) {
            throw new RuntimeException(
                'Appointment not found'
            );
        }

        if (!$appointment['client_id']) {
            throw new RuntimeException(
                'Appointment must have a client before invoicing'
            );
        }

        if (!$appointment['service_id']) {
            throw new RuntimeException(
                'Appointment must have a service before invoicing'
            );
        }

        if ($appointment['status'] !== 'completed') {
            throw new RuntimeException(
                'Only completed appointments can be invoiced'
            );
        }

        /*
         * Application-level idempotency check.
         * The UNIQUE database constraint provides the
         * final concurrency-safe protection.
         */
        $existing=$pdo->prepare("
            SELECT
                id,
                invoice_number
            FROM invoices
            WHERE tenant_id=?
              AND appointment_id=?
            LIMIT 1
        ");

        $existing->execute([
            $tid,
            $appointmentId
        ]);

        if ($row=$existing->fetch()) {
            throw new RuntimeException(
                'Appointment already has invoice ' .
                $row['invoice_number']
            );
        }

        $price=round(
            (float)$appointment['service_price'],
            2
        );

        if ($price < 0) {
            throw new RuntimeException(
                'Service price is invalid'
            );
        }

        /*
         * Generate a tenant-scoped human-readable number.
         * Timestamp + appointment ID makes accidental
         * collisions extremely unlikely; the database
         * unique constraint is authoritative.
         */
        $invoiceNumber =
            'INV-' .
            date('Ymd-His') .
            '-' .
            $appointmentId;

        $dueAt = !empty($d['due_at'])
            ? $d['due_at']
            : date(
                'Y-m-d H:i:s',
                strtotime('+14 days')
            );

        $notes =
            trim($d['notes'] ?? '') ?: null;

        $pdo->beginTransaction();

        $insert=$pdo->prepare("
            INSERT INTO invoices(
                tenant_id,
                client_id,
                appointment_id,
                invoice_number,
                status,
                subtotal,
                tax_amount,
                total,
                balance_due,
                due_at,
                notes
            )
            VALUES(
                ?,?,?,?,
                'draft',
                ?,
                0,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $insert->execute([
            $tid,
            (int)$appointment['client_id'],
            $appointmentId,
            $invoiceNumber,
            $price,
            $price,
            $price,
            $dueAt,
            $notes
        ]);

        $invoiceId =
            (int)$pdo->lastInsertId();

        $description =
            trim(
                ($appointment['service_name'] ?: $appointment['title']) .
                ' - ' .
                date(
                    'M j, Y',
                    strtotime($appointment['starts_at'])
                )
            );

        $item=$pdo->prepare("
            INSERT INTO invoice_items(
                invoice_id,
                description,
                quantity,
                unit_price,
                amount
            )
            VALUES(?,?,?,?,?)
        ");

        $item->execute([
            $invoiceId,
            $description,
            1,
            $price,
            $price
        ]);

        $pdo->commit();

        json_response([
            'ok'=>true,
            'id'=>$invoiceId,
            'invoice_number'=>$invoiceNumber,
            'total'=>$price
        ],201);

    } catch(Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_response([
            'error'=>$e->getMessage()
        ],422);
    }
}

json_response([
    'error'=>'Method not allowed'
],405);
"""

FILES["api/payments.php"] = r"""<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

Auth::requireUser();

$tid = Auth::tenantId();
$pdo = Database::connection();

/*
|--------------------------------------------------------------------------
| Recalculate invoice financial state from payment ledger
|--------------------------------------------------------------------------
*/

function recalculate_invoice(PDO $pdo, int $tenantId, int $invoiceId): array
{
    $q=$pdo->prepare("
        SELECT
            id,
            total,
            status
        FROM invoices
        WHERE id=?
          AND tenant_id=?
        FOR UPDATE
    ");

    $q->execute([
        $invoiceId,
        $tenantId
    ]);

    $invoice=$q->fetch();

    if (!$invoice) {
        throw new RuntimeException('Invoice not found');
    }

    $p=$pdo->prepare("
        SELECT COALESCE(SUM(amount),0)
        FROM payments
        WHERE tenant_id=?
          AND invoice_id=?
          AND status='succeeded'
    ");

    $p->execute([
        $tenantId,
        $invoiceId
    ]);

    $paid=round((float)$p->fetchColumn(),2);
    $total=round((float)$invoice['total'],2);
    $balance=max(0,round($total-$paid,2));

    /*
     * Preserve void invoices.
     * Otherwise payment state determines paid/partial.
     * An unpaid invoice remains draft/sent/overdue.
     */
    if ($invoice['status'] === 'void') {
        $newStatus='void';
    } elseif ($balance <= 0) {
        $newStatus='paid';
    } elseif ($paid > 0) {
        $newStatus='partial';
    } else {
        $newStatus=$invoice['status'];
    }

    $u=$pdo->prepare("
        UPDATE invoices
        SET balance_due=?,
            status=?
        WHERE id=?
          AND tenant_id=?
    ");

    $u->execute([
        $balance,
        $newStatus,
        $invoiceId,
        $tenantId
    ]);

    return [
        'total'=>$total,
        'paid'=>$paid,
        'balance_due'=>$balance,
        'status'=>$newStatus
    ];
}

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD']==='GET') {

    $q=$pdo->prepare("
        SELECT
            p.*,
            CONCAT(c.first_name,' ',c.last_name) AS client_name,
            i.invoice_number
        FROM payments p

        JOIN clients c
          ON c.id=p.client_id
         AND c.tenant_id=p.tenant_id

        LEFT JOIN invoices i
          ON i.id=p.invoice_id
         AND i.tenant_id=p.tenant_id

        WHERE p.tenant_id=?

        ORDER BY
            COALESCE(p.paid_at,p.created_at) DESC,
            p.id DESC
    ");

    $q->execute([$tid]);

    json_response([
        'payments'=>$q->fetchAll()
    ]);
}

/*
|--------------------------------------------------------------------------
| Mutations
|--------------------------------------------------------------------------
*/

verify_csrf();

Auth::requireRole(
    'owner',
    'admin',
    'accounting'
);

$d=request_data();

/*
|--------------------------------------------------------------------------
| POST — Record invoice payment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD']==='POST') {

    $invoiceId=(int)($d['invoice_id'] ?? 0);

    if ($invoiceId <= 0) {
        json_response([
            'error'=>'Invoice required'
        ],422);
    }

    $amount=round(
        (float)($d['amount'] ?? 0),
        2
    );

    if ($amount <= 0) {
        json_response([
            'error'=>'Payment amount must be greater than zero'
        ],422);
    }

    $status=$d['status'] ?? 'succeeded';

    $allowedStatuses=[
        'pending',
        'succeeded',
        'failed',
        'refunded',
        'void'
    ];

    if (!in_array($status,$allowedStatuses,true)) {
        json_response([
            'error'=>'Invalid payment status'
        ],422);
    }

    $currency=strtoupper(
        trim($d['currency'] ?? 'USD')
    );

    if (!preg_match('/^[A-Z]{3}$/',$currency)) {
        json_response([
            'error'=>'Invalid currency'
        ],422);
    }

    try {

        $pdo->beginTransaction();

        /*
         * Lock and validate invoice.
         * Client comes from the invoice — never from browser input.
         */
        $q=$pdo->prepare("
            SELECT
                id,
                client_id,
                invoice_number,
                status,
                total,
                balance_due
            FROM invoices
            WHERE id=?
              AND tenant_id=?
            FOR UPDATE
        ");

        $q->execute([
            $invoiceId,
            $tid
        ]);

        $invoice=$q->fetch();

        if (!$invoice) {
            throw new RuntimeException(
                'Invoice not found'
            );
        }

        if ($invoice['status']==='void') {
            throw new RuntimeException(
                'Cannot record payment against a void invoice'
            );
        }

        /*
         * Determine authoritative balance from the ledger,
         * not the potentially stale balance_due field.
         */
        $sum=$pdo->prepare("
            SELECT COALESCE(SUM(amount),0)
            FROM payments
            WHERE tenant_id=?
              AND invoice_id=?
              AND status='succeeded'
        ");

        $sum->execute([
            $tid,
            $invoiceId
        ]);

        $alreadyPaid=
            round((float)$sum->fetchColumn(),2);

        $total=
            round((float)$invoice['total'],2);

        $currentBalance=
            max(0,round($total-$alreadyPaid,2));

        if ($status==='succeeded') {

            if ($currentBalance <= 0) {
                throw new RuntimeException(
                    'Invoice is already paid'
                );
            }

            if ($amount > $currentBalance) {
                throw new RuntimeException(
                    'Payment exceeds remaining balance of $' .
                    number_format($currentBalance,2)
                );
            }
        }

        $method=
            trim($d['method'] ?? '') ?: null;

        $provider=
            trim($d['provider'] ?? '') ?: null;

        $providerReference=
            trim($d['provider_reference'] ?? '') ?: null;

        $notes=
            trim($d['notes'] ?? '') ?: null;

        $paidAt=
            trim($d['paid_at'] ?? '');

        if ($paidAt==='') {
            $paidAt=date('Y-m-d H:i:s');
        } else {
            try {
                $paidAt=(new DateTime($paidAt))
                    ->format('Y-m-d H:i:s');
            } catch(Throwable $e) {
                throw new RuntimeException(
                    'Invalid payment date'
                );
            }
        }

        $insert=$pdo->prepare("
            INSERT INTO payments(
                tenant_id,
                invoice_id,
                client_id,
                amount,
                currency,
                status,
                method,
                provider,
                provider_reference,
                paid_at,
                notes
            )
            VALUES(?,?,?,?,?,?,?,?,?,?,?)
        ");

        $insert->execute([
            $tid,
            $invoiceId,
            (int)$invoice['client_id'],
            $amount,
            $currency,
            $status,
            $method,
            $provider,
            $providerReference,
            $paidAt,
            $notes
        ]);

        $paymentId=
            (int)$pdo->lastInsertId();

        $financials=
            recalculate_invoice(
                $pdo,
                $tid,
                $invoiceId
            );

        $pdo->commit();

        json_response([
            'ok'=>true,
            'id'=>$paymentId,
            'invoice_id'=>$invoiceId,
            'invoice_number'=>$invoice['invoice_number'],
            'amount'=>$amount,
            'invoice'=>$financials
        ],201);

    } catch(Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        json_response([
            'error'=>$e->getMessage()
        ],422);
    }
}

json_response([
    'error'=>'Method not allowed'
],405);
"""

FILES["api/dashboard.php"] = r"""<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
Auth::requireUser();$tid=Auth::tenantId();$pdo=Database::connection();
function scalar(PDO $pdo,string $sql,array $p){$q=$pdo->prepare($sql);$q->execute($p);return $q->fetchColumn();}
json_response([
 'clients'=>(int)scalar($pdo,"SELECT COUNT(*) FROM clients WHERE tenant_id=? AND status='active'",[$tid]),
 'contractors'=>(int)scalar($pdo,"SELECT COUNT(*) FROM contractors WHERE tenant_id=? AND status='active'",[$tid]),
 'appointments_today'=>(int)scalar($pdo,"SELECT COUNT(*) FROM appointments WHERE tenant_id=? AND DATE(starts_at)=CURDATE() AND status NOT IN('cancelled','no_show')",[$tid]),
 'appointments_week'=>(int)scalar($pdo,"SELECT COUNT(*) FROM appointments WHERE tenant_id=? AND starts_at>=CURDATE() AND starts_at<DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND status NOT IN('cancelled','no_show')",[$tid]),
 'receivables'=>(float)scalar($pdo,"SELECT COALESCE(SUM(balance_due),0) FROM invoices WHERE tenant_id=? AND status IN('sent','partial','overdue')",[$tid]),
 'payments_month'=>(float)scalar($pdo,"SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id=? AND status='succeeded' AND paid_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')",[$tid]),
]);
"""

# Legacy FullCalendar adapters: keep old scheduling.php useful while enforcing tenant isolation.
FILES["events/events.json.php"] = r"""<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
Auth::requireUser();$tid=Auth::tenantId();$pdo=Database::connection();
$q=$pdo->prepare("SELECT id,title,description,starts_at,ends_at,status,is_public FROM appointments WHERE tenant_id=? AND starts_at>=DATE_SUB(NOW(),INTERVAL 1 YEAR) ORDER BY starts_at");
$q->execute([$tid]);$out=[];
foreach($q->fetchAll() as $a){$out[]=['event_id'=>$a['id'],'title'=>$a['title'],'description'=>$a['description'],'user_id'=>'','start'=>$a['starts_at'],'end'=>$a['ends_at'],'repeat_type'=>'0','is_public'=>(bool)$a['is_public'],'is_active'=>$a['status']!=='cancelled'];}
json_response(['events'=>$out]);
"""

FILES["events/save_event.php"] = r"""<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$u=Auth::requireUser();Auth::requireRole('owner','admin','scheduler');verify_csrf();
$tid=Auth::tenantId();$pdo=Database::connection();$d=request_data();
$parse=function($date,$time){$x=DateTime::createFromFormat('m/d/Y H:i',trim($date.' '.$time));return $x?$x->format('Y-m-d H:i:s'):null;};
$start=$parse($d['start_date']??'',$d['start_time']??'00:00');$end=$parse($d['end_date']??'',$d['end_time']??'00:00');
if(!$start||!$end) json_response(['error'=>'Invalid date/time'],422);
$id=(int)($d['event_id']??0);
if($id){$q=$pdo->prepare("UPDATE appointments SET title=?,description=?,starts_at=?,ends_at=?,is_public=?,status=? WHERE id=? AND tenant_id=?");$q->execute([$d['description']??'Appointment',$d['description']??null,$start,$end,($d['is_public']??'false')==='true'?1:0,($d['is_active']??'true')==='true'?'scheduled':'cancelled',$id,$tid]);}
else{$q=$pdo->prepare("INSERT INTO appointments(tenant_id,title,description,starts_at,ends_at,is_public,status,created_by) VALUES(?,?,?,?,?,?,?,?)");$q->execute([$tid,$d['description']??'Appointment',$d['description']??null,$start,$end,($d['is_public']??'false')==='true'?1:0,($d['is_active']??'true')==='true'?'scheduled':'cancelled',$u['id']]);$id=(int)$pdo->lastInsertId();}
json_response(['ok'=>true,'id'=>$id]);
"""

FILES["partials/app_header.php"] = r"""<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=Auth::requireUser();$tenant=Auth::tenant();$csrf=csrf_token();
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($pageTitle??'BriteScheduler')?></title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f5f7fb;color:#1f2937}.top{background:#172033;color:#fff;padding:14px 24px;display:flex;justify-content:space-between}.nav{background:#fff;padding:10px 24px;border-bottom:1px solid #ddd}.nav a{margin-right:18px;color:#334155;text-decoration:none}.wrap{max-width:1250px;margin:24px auto;padding:0 18px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;box-shadow:0 2px 8px #00000008}.metric{font-size:28px;font-weight:700}table{width:100%;border-collapse:collapse;background:#fff}th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}input,select,textarea,button{padding:9px;border:1px solid #cbd5e1;border-radius:6px}button{cursor:pointer}.row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}.row>*{flex:1;min-width:140px}.muted{color:#64748b}.danger{color:#b91c1c}
</style>
<script>
window.BRITE_CSRF=<?=json_encode($csrf)?>;
window.BRITE_BASE=<?=json_encode(rtrim($_ENV['APP_URL'] ?? 'http://localhost/dashboard','/'))?>;

async function api(url,opt={}){
    opt.headers={
        ...(opt.headers||{}),
        'X-CSRF-Token':window.BRITE_CSRF
    };

    if(url.startsWith('/')){
        url=window.BRITE_BASE+url;
    }

    let r=await fetch(url,opt);
    let j=await r.json();

    if(!r.ok)throw new Error(j.error||'Request failed');
    return j;
}
</script>
</head><body><div class="top"><b>BriteScheduler</b><span><?=htmlspecialchars($tenant['name'])?> · <?=htmlspecialchars($user['first_name'].' '.$user['last_name'])?> (<?=htmlspecialchars($tenant['role'])?>)</span></div>
<div class="nav"><a href="<?=htmlspecialchars(app_url('/dashboard.v2.php'))?>">Dashboard</a><a href="<?=htmlspecialchars(app_url('/clients.v2.php'))?>">Clients</a><a href="<?=htmlspecialchars(app_url('/contractors.v2.php'))?>">Contractors</a><a href="<?=htmlspecialchars(app_url('/contractor-availability.php'))?>">Availability</a><a href="<?=htmlspecialchars(app_url('/services.v2.php'))?>">Services</a><a href="<?=htmlspecialchars(app_url('/scheduling.php'))?>">Schedule</a><a href="<?=htmlspecialchars(app_url('/billing.php'))?>">Billing</a></div><main class="wrap">
"""

FILES["partials/app_footer.php"] = r"""</main></body></html>"""

FILES["dashboard.v2.php"] = r"""<?php $pageTitle='Dashboard';require __DIR__.'/partials/app_header.php';?>
<h1>Operations Dashboard</h1><div id="metrics" class="grid"></div>
<div class="card" style="margin-top:18px"><h2>System scope</h2><p class="muted">Tenant-isolated clients, contractors, scheduling, invoices and payments are active. Payment records are ledger entries; connect a PCI-compliant payment provider for card processing.</p></div>
<script>
api('/api/dashboard.php').then(d=>{let m=[['Active clients',d.clients],['Active contractors',d.contractors],['Appointments today',d.appointments_today],['Next 7 days',d.appointments_week],['Receivables','$'+Number(d.receivables).toFixed(2)],['Payments this month','$'+Number(d.payments_month).toFixed(2)]];document.querySelector('#metrics').innerHTML=m.map(x=>`<div class="card"><div class="muted">${x[0]}</div><div class="metric">${x[1]}</div></div>`).join('')});
</script><?php require __DIR__.'/partials/app_footer.php';?>
"""

FILES["clients.v2.php"] = r"""<?php $pageTitle='Clients';require __DIR__.'/partials/app_header.php';?>
<h1>Clients</h1><div class="card"><form id="f"><div class="row"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="company_name" placeholder="Company"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"><button>Add client</button></div></form></div>
<div class="card" style="margin-top:18px"><table><thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Status</th></tr></thead><tbody id="rows"></tbody></table></div>
<script>
async function load(){let d=await api('/api/clients.php');rows.innerHTML=d.clients.map(c=>`<tr><td>${esc(c.first_name)} ${esc(c.last_name)}</td><td>${esc(c.company_name||'')}</td><td>${esc(c.email||'')}</td><td>${esc(c.phone||'')}</td><td>${esc(c.status)}</td></tr>`).join('')}
function esc(s){return String(s).replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[x]))}
f.onsubmit=async e=>{e.preventDefault();let d=Object.fromEntries(new FormData(f));await api('/api/clients.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});f.reset();load()};load();
</script><?php require __DIR__.'/partials/app_footer.php';?>
"""

FILES["contractors.v2.php"] = r"""<?php $pageTitle='Contractors';require __DIR__.'/partials/app_header.php';?>
<h1>Contractors</h1><div class="card"><form id="f"><div class="row"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="business_name" placeholder="Business"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"><input name="hourly_rate" type="number" step=".01" placeholder="Hourly rate"><button>Add contractor</button></div></form></div>
<div class="card" style="margin-top:18px"><table><thead><tr><th>Name</th><th>Business</th><th>Email</th><th>Phone</th><th>Rate</th></tr></thead><tbody id="rows"></tbody></table></div>
<script>
function esc(s){return String(s??'').replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[x]))}
async function load(){let d=await api('/api/contractors.php');rows.innerHTML=d.contractors.map(c=>`<tr><td>${esc(c.first_name)} ${esc(c.last_name)}</td><td>${esc(c.business_name)}</td><td>${esc(c.email)}</td><td>${esc(c.phone)}</td><td>${c.hourly_rate?'$'+Number(c.hourly_rate).toFixed(2):''}</td></tr>`).join('')}
f.onsubmit=async e=>{e.preventDefault();let d=Object.fromEntries(new FormData(f));await api('/api/contractors.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});f.reset();load()};load();
</script><?php require __DIR__.'/partials/app_footer.php';?>
"""

FILES["billing.php"] = r"""<?php

$pageTitle='Billing';

require __DIR__.'/partials/app_header.php';

?>

<h1>Billing & Payments</h1>

<div id="billingError"
     class="danger"
     style="margin-bottom:12px"></div>

<div class="grid">

    <div class="card">
        <div class="muted">
            Outstanding Receivables
        </div>
        <div class="metric"
             id="receivablesMetric">
            $0.00
        </div>
    </div>

    <div class="card">
        <div class="muted">
            Total Invoiced
        </div>
        <div class="metric"
             id="invoicedMetric">
            $0.00
        </div>
    </div>

    <div class="card">
        <div class="muted">
            Successful Payments
        </div>
        <div class="metric"
             id="paymentsMetric">
            $0.00
        </div>
    </div>

</div>

<div class="card"
     style="margin-top:18px">

    <h2>Invoices</h2>

    <div style="overflow-x:auto">

        <table>

            <thead>
                <tr>
                    <th>#</th>
                    <th>Client</th>
                    <th>Appointment</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Due Date</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody id="invoiceRows"></tbody>

        </table>

    </div>

</div>

<div class="card"
     id="paymentCard"
     style="margin-top:18px;display:none">

    <h2>Record Payment</h2>

    <div id="paymentInvoice"
         style="margin-bottom:14px"></div>

    <form id="paymentForm">

        <input type="hidden"
               id="paymentInvoiceId">

        <div class="row">

            <label>
                Amount
                <input type="number"
                       id="paymentAmount"
                       min="0.01"
                       step="0.01"
                       required>
            </label>

            <label>
                Method
                <select id="paymentMethod">
                    <option value="cash">Cash</option>
                    <option value="check">Check</option>
                    <option value="card">Card / External Processor</option>
                    <option value="ach">ACH</option>
                    <option value="zelle">Zelle</option>
                    <option value="other">Other</option>
                </select>
            </label>

            <label>
                Payment Date
                <input type="datetime-local"
                       id="paymentDate">
            </label>

        </div>

        <div class="row">

            <label>
                Provider
                <input type="text"
                       id="paymentProvider"
                       placeholder="Optional">
            </label>

            <label>
                Provider / Transaction Reference
                <input type="text"
                       id="paymentReference"
                       placeholder="Optional">
            </label>

        </div>

        <div class="row">

            <label>
                Notes
                <textarea id="paymentNotes"
                          rows="3"
                          placeholder="Optional"></textarea>
            </label>

        </div>

        <button type="submit">
            Record Payment
        </button>

        <button type="button"
                id="cancelPayment">
            Cancel
        </button>

    </form>

</div>

<div class="card"
     style="margin-top:18px">

    <h2>Payment History</h2>

    <div style="overflow-x:auto">

        <table>

            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Reference</th>
                </tr>
            </thead>

            <tbody id="paymentRows"></tbody>

        </table>

    </div>

</div>

<script>

const billingError =
    document.getElementById('billingError');

const invoiceRows =
    document.getElementById('invoiceRows');

const paymentRows =
    document.getElementById('paymentRows');

const paymentCard =
    document.getElementById('paymentCard');

const paymentForm =
    document.getElementById('paymentForm');

const paymentInvoiceId =
    document.getElementById('paymentInvoiceId');

const paymentInvoice =
    document.getElementById('paymentInvoice');

const paymentAmount =
    document.getElementById('paymentAmount');

const paymentMethod =
    document.getElementById('paymentMethod');

const paymentDate =
    document.getElementById('paymentDate');

const paymentProvider =
    document.getElementById('paymentProvider');

const paymentReference =
    document.getElementById('paymentReference');

const paymentNotes =
    document.getElementById('paymentNotes');

const cancelPayment =
    document.getElementById('cancelPayment');

let invoices = [];
let payments = [];

function money(value){
    return '$' + Number(value || 0).toFixed(2);
}

function escapeHtml(value){

    return String(value ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
}

function displayDate(value){

    if(!value){
        return '';
    }

    const normalized =
        value.replace(' ','T');

    const d =
        new Date(normalized);

    if(Number.isNaN(d.getTime())){
        return value;
    }

    return d.toLocaleString();
}

function statusLabel(status){

    const labels={
        draft:'Draft',
        sent:'Sent',
        partial:'Partial',
        paid:'Paid',
        void:'Void',
        overdue:'Overdue'
    };

    return labels[status] || status;
}

function renderInvoices(){

    if(!invoices.length){

        invoiceRows.innerHTML=
            '<tr><td colspan="9" class="muted">No invoices yet.</td></tr>';

        return;
    }

    invoiceRows.innerHTML =
        invoices.map(x=>{

            const balance =
                Number(x.balance_due || 0);

            const paid =
                Number(x.amount_paid || 0);

            let action='';

            if(
                balance > 0 &&
                x.status !== 'void'
            ){
                action=`
                    <button type="button"
                            onclick="openPayment(${Number(x.id)})">
                        Record Payment
                    </button>
                `;
            }else if(x.status === 'paid'){
                action='Paid';
            }else{
                action='—';
            }

            return `
                <tr>
                    <td>${escapeHtml(x.invoice_number)}</td>
                    <td>${escapeHtml(x.client_name)}</td>
                    <td>${escapeHtml(x.appointment_title || '')}</td>
                    <td>${escapeHtml(statusLabel(x.status))}</td>
                    <td>${money(x.total)}</td>
                    <td>${money(paid)}</td>
                    <td>${money(balance)}</td>
                    <td>${escapeHtml(displayDate(x.due_at))}</td>
                    <td>${action}</td>
                </tr>
            `;
        }).join('');
}

function renderPayments(){

    if(!payments.length){

        paymentRows.innerHTML=
            '<tr><td colspan="7" class="muted">No payments yet.</td></tr>';

        return;
    }

    paymentRows.innerHTML =
        payments.map(x=>`
            <tr>
                <td>${escapeHtml(x.invoice_number || '')}</td>
                <td>${escapeHtml(x.client_name)}</td>
                <td>${money(x.amount)}</td>
                <td>${escapeHtml(x.method || '')}</td>
                <td>${escapeHtml(x.status)}</td>
                <td>${escapeHtml(displayDate(x.paid_at))}</td>
                <td>${escapeHtml(x.provider_reference || '')}</td>
            </tr>
        `).join('');
}

function renderMetrics(){

    const totalInvoiced =
        invoices
            .filter(x=>x.status !== 'void')
            .reduce(
                (sum,x)=>sum+Number(x.total || 0),
                0
            );

    const receivables =
        invoices
            .filter(x=>x.status !== 'void')
            .reduce(
                (sum,x)=>sum+Number(x.balance_due || 0),
                0
            );

    const successfulPayments =
        payments
            .filter(x=>x.status === 'succeeded')
            .reduce(
                (sum,x)=>sum+Number(x.amount || 0),
                0
            );

    document.getElementById(
        'invoicedMetric'
    ).textContent=money(totalInvoiced);

    document.getElementById(
        'receivablesMetric'
    ).textContent=money(receivables);

    document.getElementById(
        'paymentsMetric'
    ).textContent=money(successfulPayments);
}

async function loadBilling(){

    billingError.textContent='';

    try{

        const [invoiceData,paymentData] =
            await Promise.all([
                api('/api/invoices.php'),
                api('/api/payments.php')
            ]);

        invoices =
            invoiceData.invoices || [];

        payments =
            paymentData.payments || [];

        renderInvoices();
        renderPayments();
        renderMetrics();

    }catch(e){

        billingError.textContent=e.message;
    }
}

window.openPayment=function(id){

    const invoice =
        invoices.find(
            x=>Number(x.id)===Number(id)
        );

    if(!invoice){
        return;
    }

    paymentInvoiceId.value=
        invoice.id;

    paymentAmount.value=
        Number(invoice.balance_due).toFixed(2);

    paymentInvoice.innerHTML=`
        <strong>${escapeHtml(invoice.invoice_number)}</strong>
        · ${escapeHtml(invoice.client_name)}
        · Balance ${money(invoice.balance_due)}
    `;

    paymentProvider.value='';
    paymentReference.value='';
    paymentNotes.value='';

    /*
     * Leave date blank so the server records its
     * authoritative current time.
     */
    paymentDate.value='';

    paymentCard.style.display='block';

    paymentCard.scrollIntoView({
        behavior:'smooth',
        block:'start'
    });
};

cancelPayment.onclick=()=>{

    paymentCard.style.display='none';
    paymentForm.reset();
    paymentInvoiceId.value='';
};

paymentForm.addEventListener(
    'submit',
    async e=>{

        e.preventDefault();

        billingError.textContent='';

        const invoiceId =
            Number(paymentInvoiceId.value);

        const amount =
            Number(paymentAmount.value);

        if(!invoiceId){
            billingError.textContent=
                'Invoice required';
            return;
        }

        if(!amount || amount <= 0){
            billingError.textContent=
                'Enter a valid payment amount';
            return;
        }

        const payload={
            invoice_id:invoiceId,
            amount:amount,
            currency:'USD',
            status:'succeeded',
            method:paymentMethod.value,
            provider:
                paymentProvider.value || null,
            provider_reference:
                paymentReference.value || null,
            notes:
                paymentNotes.value || null
        };

        if(paymentDate.value){
            payload.paid_at=
                paymentDate.value;
        }

        try{

            const result =
                await api(
                    '/api/payments.php',
                    {
                        method:'POST',
                        headers:{
                            'Content-Type':
                                'application/json'
                        },
                        body:JSON.stringify(payload)
                    }
                );

            alert(
                `${money(result.amount)} payment recorded. ` +
                `Remaining balance: ${money(result.invoice.balance_due)}`
            );

            paymentCard.style.display='none';
            paymentForm.reset();
            paymentInvoiceId.value='';

            await loadBilling();

        }catch(e){

            billingError.textContent=
                e.message;
        }
    }
);

loadBilling();

</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
"""

FILES["setup/create_admin.php"] = r"""<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
if ($argc < 6) { exit("Usage: php setup/create_admin.php tenant-slug \"Tenant Name\" email first last\n"); }
[$script,$slug,$tenantName,$email,$first,$last]=$argv;
$password=getenv('BRITE_ADMIN_PASSWORD'); if(!$password) exit("Set BRITE_ADMIN_PASSWORD first.\n");
$pdo=Database::connection();$pdo->beginTransaction();
try{
 $q=$pdo->prepare("INSERT INTO tenants(name,slug) VALUES(?,?)");$q->execute([$tenantName,$slug]);$tid=(int)$pdo->lastInsertId();
 $q=$pdo->prepare("INSERT INTO users(email,password_hash,first_name,last_name) VALUES(?,?,?,?)");$q->execute([strtolower($email),password_hash($password,PASSWORD_DEFAULT),$first,$last]);$uid=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO tenant_memberships(tenant_id,user_id,role) VALUES(?,?,'owner')")->execute([$tid,$uid]);
 $pdo->commit();echo "Created tenant {$tenantName} and owner {$email}\n";
}catch(Throwable $e){$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
"""

FILES["sign-in.v2.php"] = r"""<?php require_once __DIR__.'/app/bootstrap.php';$csrf=csrf_token();?><!doctype html><html><head><meta charset="utf-8"><title>BriteScheduler Sign In</title><style>body{font-family:system-ui;background:#f5f7fb}.box{max-width:360px;margin:10vh auto;background:white;padding:30px;border-radius:12px}input,button{box-sizing:border-box;width:100%;padding:11px;margin:7px 0}</style></head><body><div class="box"><h1>BriteScheduler</h1><form id="f"><input name="email" type="email" placeholder="Email" required><input name="password" type="password" placeholder="Password" required><button>Sign in</button><p id="e"></p></form></div><script>f.onsubmit=async x=>{x.preventDefault();let d=Object.fromEntries(new FormData(f));let r=await fetch(<?=json_encode(app_url('/api/auth/login.php'))?>,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':<?=json_encode($csrf)?>},body:JSON.stringify(d)}),j=await r.json();if(r.ok)location=j.redirect;else e.textContent=j.error}</script></body></html>"""

FILES["README-BRITE-V2.md"] = r"""# BriteScheduler multi-tenant application layer

Generated by `brite_complete_wizard.py`.

## Architecture
Every business row is tenant-scoped. Authentication is session based. Tenant membership
determines role: owner, admin, scheduler, accounting, contractor, or client. Do not accept
tenant IDs from browsers for authorization; the server resolves tenant from the session.

## Setup
1. Copy `.env.example` to `.env` and set a non-root MySQL user/password.
2. Run the wizard with `--migrate`, or execute migrations in numeric order.
3. Create the first owner:
   `BRITE_ADMIN_PASSWORD='use-a-long-password' php setup/create_admin.php acme "Acme LLC" owner@example.com First Last`
4. The wizard can activate the v2 dashboard/client/contractor/sign-in pages with `--activate`.

## Payments
The included payments module is an accounting ledger only. Never store PAN/CVV/card
credentials in BriteScheduler. Add Stripe/another PCI-compliant provider through hosted
checkout/tokenization and store only provider references.

## Legacy events
The old code used a separate `events` database and user IDs with no tenant boundary.
It is not automatically imported. Review and map legacy records before importing them as
appointments.
"""

def write_file(rel: str, content: str, activate: bool=False):
    path = ROOT / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    if path.exists():
        dest = BACKUP / rel
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, dest)
    path.write_text(content, encoding="utf-8")
    print(f"WRITE {rel}")

def merge_gitignore():
    p=ROOT/".gitignore"
    wanted=[".env",".env.local",".brite-wizard-backup/","vendor/","*.log"]
    old=p.read_text(encoding="utf-8") if p.exists() else ""
    lines=old.splitlines()
    for x in wanted:
        if x not in lines: lines.append(x)
    write_file(".gitignore","\n".join(lines).rstrip()+"\n")

def activate_pages():
    mapping={
      "dashboard.v2.php":"dashboard.php",
      "clients.v2.php":"clients.php",
      "contractors.v2.php":"contractors.php",
      "sign-in.v2.php":"sign-in.php",
    }
    for src,dst in mapping.items():
        target=ROOT/dst
        if target.exists():
            b=BACKUP/dst;b.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(target,b)
        shutil.copy2(ROOT/src,target)
        print(f"ACTIVATE {dst}")


FILES["api/contractor_availability.php"] = r"""<?php

require_once dirname(__DIR__).'/app/bootstrap.php';

Auth::requireUser();
$tid = Auth::tenantId();
$pdo = Database::connection();

/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $contractorId = (int)($_GET['contractor_id'] ?? 0);

    if ($contractorId <= 0) {
        json_response(['error'=>'Contractor required'],422);
    }

    $check = $pdo->prepare("
        SELECT id
        FROM contractors
        WHERE id=? AND tenant_id=?
    ");

    $check->execute([$contractorId,$tid]);

    if (!$check->fetchColumn()) {
        json_response(['error'=>'Contractor not found'],404);
    }

    $q = $pdo->prepare("
        SELECT
            id,
            contractor_id,
            weekday,
            available_date,
            starts_at,
            ends_at,
            is_available
        FROM contractor_availability
        WHERE tenant_id=?
          AND contractor_id=?
        ORDER BY
            CASE WHEN available_date IS NULL THEN 0 ELSE 1 END,
            weekday,
            available_date,
            starts_at
    ");

    $q->execute([$tid,$contractorId]);

    json_response([
        'availability'=>$q->fetchAll()
    ]);
}

/*
|--------------------------------------------------------------------------
| Mutations
|--------------------------------------------------------------------------
*/

verify_csrf();
Auth::requireRole('owner','admin','scheduler');

$d = request_data();

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $contractorId=(int)($d['contractor_id'] ?? 0);

        $check=$pdo->prepare("
            SELECT id
            FROM contractors
            WHERE id=? AND tenant_id=?
        ");

        $check->execute([$contractorId,$tid]);

        if (!$check->fetchColumn()) {
            throw new RuntimeException('Invalid contractor');
        }

        $weekday =
            ($d['weekday'] ?? '') === ''
                ? null
                : (int)$d['weekday'];

        $availableDate =
            trim($d['available_date'] ?? '') ?: null;

        if ($weekday === null && $availableDate === null) {
            throw new RuntimeException(
                'Choose a weekday or a specific date'
            );
        }

        if ($weekday !== null && ($weekday < 0 || $weekday > 6)) {
            throw new RuntimeException('Invalid weekday');
        }

        /*
         * A specific-date rule should not also contain
         * a recurring weekday.
         */
        if ($availableDate !== null) {
            $weekday=null;

            $dt=DateTime::createFromFormat(
                'Y-m-d',
                $availableDate
            );

            if (
                !$dt ||
                $dt->format('Y-m-d') !== $availableDate
            ) {
                throw new RuntimeException('Invalid date');
            }
        }

        $startsAt=trim($d['starts_at'] ?? '');
        $endsAt=trim($d['ends_at'] ?? '');

        if (
            !preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$startsAt) ||
            !preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$endsAt)
        ) {
            throw new RuntimeException('Invalid time');
        }

        if (strlen($startsAt)===5) {
            $startsAt .= ':00';
        }

        if (strlen($endsAt)===5) {
            $endsAt .= ':00';
        }

        if ($endsAt <= $startsAt) {
            throw new RuntimeException(
                'End time must be after start time'
            );
        }

        $isAvailable =
            !empty($d['is_available']) ? 1 : 0;

        $q=$pdo->prepare("
            INSERT INTO contractor_availability(
                tenant_id,
                contractor_id,
                weekday,
                available_date,
                starts_at,
                ends_at,
                is_available
            )
            VALUES(?,?,?,?,?,?,?)
        ");

        $q->execute([
            $tid,
            $contractorId,
            $weekday,
            $availableDate,
            $startsAt,
            $endsAt,
            $isAvailable
        ]);

        json_response([
            'ok'=>true,
            'id'=>(int)$pdo->lastInsertId()
        ],201);

    } catch(Throwable $e) {

        json_response([
            'error'=>$e->getMessage()
        ],422);
    }
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $id=(int)($d['id'] ?? 0);

    $q=$pdo->prepare("
        DELETE FROM contractor_availability
        WHERE id=? AND tenant_id=?
    ");

    $q->execute([$id,$tid]);

    if (!$q->rowCount()) {
        json_response([
            'error'=>'Availability rule not found'
        ],404);
    }

    json_response(['ok'=>true]);
}

json_response([
    'error'=>'Method not allowed'
],405);
"""


FILES["services.v2.php"] = r"""<?php
$pageTitle='Services';
require __DIR__.'/partials/app_header.php';
?>

<h1>Services</h1>

<div class="card">
    <h2>Add Service</h2>

    <form id="serviceForm">
        <div class="row">
            <input name="name"
                   placeholder="Service name"
                   required>

            <input name="duration_minutes"
                   type="number"
                   min="1"
                   value="60"
                   placeholder="Duration">

            <input name="price"
                   type="number"
                   min="0"
                   step=".01"
                   value="0.00"
                   placeholder="Price">

            <select name="active">
                <option value="1">Active</option>
                <option value="">Inactive</option>
            </select>

            <button>Add Service</button>
        </div>

        <div class="row">
            <textarea name="description"
                      placeholder="Service description"></textarea>
        </div>

        <p id="serviceError" class="danger"></p>
    </form>
</div>

<div class="card" style="margin-top:18px">
    <table>
        <thead>
        <tr>
            <th>Service</th>
            <th>Duration</th>
            <th>Price</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody id="serviceRows"></tbody>
    </table>
</div>

<script>
function esc(s){
    return String(s ?? '').replace(/[&<>"']/g,x=>({
        '&':'&amp;',
        '<':'&lt;',
        '>':'&gt;',
        '"':'&quot;',
        "'":'&#039;'
    }[x]));
}

async function loadServices(){
    try {
        const d=await api('/api/services.php');

        serviceRows.innerHTML=d.services.map(s=>`
            <tr>
                <td>
                    <strong>${esc(s.name)}</strong>
                    <div class="muted">${esc(s.description)}</div>
                </td>
                <td>${Number(s.duration_minutes)} min</td>
                <td>$${Number(s.price).toFixed(2)}</td>
                <td>${Number(s.active) ? 'Active' : 'Inactive'}</td>
            </tr>
        `).join('');
    } catch(e){
        serviceError.textContent=e.message;
    }
}

serviceForm.onsubmit=async e=>{
    e.preventDefault();
    serviceError.textContent='';

    try {
        const d=Object.fromEntries(new FormData(serviceForm));

        await api('/api/services.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify(d)
        });

        serviceForm.reset();
        serviceForm.duration_minutes.value=60;
        serviceForm.price.value='0.00';
        serviceForm.active.value='1';

        await loadServices();

    } catch(e){
        serviceError.textContent=e.message;
    }
};

loadServices();
</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
"""


FILES["contractor-availability.php"] = r"""<?php
$pageTitle='Contractor Availability';
require __DIR__.'/partials/app_header.php';
?>

<style>
.availability-layout{
    display:grid;
    grid-template-columns:340px minmax(0,1fr);
    gap:18px;
}
.availability-form label{
    display:block;
    font-weight:600;
    font-size:13px;
    margin-top:10px;
}
.availability-form input,
.availability-form select{
    width:100%;
    box-sizing:border-box;
    margin-top:4px;
}
.rule-available{
    font-weight:600;
}
.rule-unavailable{
    font-weight:600;
}
@media(max-width:900px){
    .availability-layout{
        grid-template-columns:1fr;
    }
}
</style>

<h1>Contractor Availability</h1>

<div class="availability-layout">

<div class="card">

    <h2>Add Availability Rule</h2>

    <form id="availabilityForm"
          class="availability-form">

        <label>Contractor</label>
        <select id="contractorId"
                name="contractor_id"
                required>
            <option value="">Select contractor</option>
        </select>

        <label>Rule Type</label>
        <select id="ruleType">
            <option value="weekly">
                Weekly recurring hours
            </option>
            <option value="date">
                Specific date
            </option>
        </select>

        <div id="weekdayContainer">
            <label>Weekday</label>
            <select id="weekday"
                    name="weekday">
                <option value="0">Sunday</option>
                <option value="1">Monday</option>
                <option value="2">Tuesday</option>
                <option value="3">Wednesday</option>
                <option value="4">Thursday</option>
                <option value="5">Friday</option>
                <option value="6">Saturday</option>
            </select>
        </div>

        <div id="dateContainer"
             style="display:none">
            <label>Specific Date</label>
            <input id="availableDate"
                   type="date"
                   name="available_date">
        </div>

        <label>Start</label>
        <input id="startsAt"
               name="starts_at"
               type="time"
               value="08:00"
               required>

        <label>End</label>
        <input id="endsAt"
               name="ends_at"
               type="time"
               value="17:00"
               required>

        <label>Status</label>
        <select id="isAvailable"
                name="is_available">
            <option value="1">Available</option>
            <option value="">
                Unavailable / Time Off
            </option>
        </select>

        <div style="margin-top:15px">
            <button type="submit">
                Add Rule
            </button>
        </div>

        <p id="availabilityError"
           class="danger"></p>

    </form>

</div>

<div class="card">

    <h2>Availability Rules</h2>

    <p class="muted">
        Specific-date rules will later override
        recurring weekly hours.
    </p>

    <table>
        <thead>
        <tr>
            <th>When</th>
            <th>Hours</th>
            <th>Status</th>
            <th></th>
        </tr>
        </thead>

        <tbody id="availabilityRows">
        </tbody>
    </table>

</div>

</div>

<script>

const contractorId =
    document.getElementById('contractorId');

const availabilityForm =
    document.getElementById('availabilityForm');

const ruleType =
    document.getElementById('ruleType');

const weekday =
    document.getElementById('weekday');

const availableDate =
    document.getElementById('availableDate');

const weekdayContainer =
    document.getElementById('weekdayContainer');

const dateContainer =
    document.getElementById('dateContainer');

const startsAt =
    document.getElementById('startsAt');

const endsAt =
    document.getElementById('endsAt');

const isAvailable =
    document.getElementById('isAvailable');

const availabilityRows =
    document.getElementById('availabilityRows');

const availabilityError =
    document.getElementById('availabilityError');

const weekdays=[
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday'
];

function esc(s){
    return String(s ?? '').replace(
        /[&<>"']/g,
        x=>({
            '&':'&amp;',
            '<':'&lt;',
            '>':'&gt;',
            '"':'&quot;',
            "'":'&#039;'
        }[x])
    );
}

function shortTime(value){

    if(!value) return '';

    const parts=value.split(':');

    let hour=Number(parts[0]);
    const minute=parts[1];

    const suffix=hour >= 12 ? 'PM' : 'AM';

    hour=hour % 12;

    if(hour===0) hour=12;

    return `${hour}:${minute} ${suffix}`;
}

async function loadContractors(){

    const d=await api('/api/contractors.php');

    contractorId.innerHTML=
        '<option value="">Select contractor</option>'+
        d.contractors.map(c=>
            `<option value="${c.id}">
                ${esc(c.first_name)} ${esc(c.last_name)}
            </option>`
        ).join('');
}

async function loadAvailability(){

    availabilityRows.innerHTML='';

    if(!contractorId.value){
        return;
    }

    const d=await api(
        '/api/contractor_availability.php?contractor_id='+
        encodeURIComponent(contractorId.value)
    );

    if(!d.availability.length){

        availabilityRows.innerHTML=`
            <tr>
                <td colspan="4"
                    class="muted">
                    No availability rules configured.
                </td>
            </tr>
        `;

        return;
    }

    availabilityRows.innerHTML=
        d.availability.map(r=>{

            const when=r.available_date
                ? esc(r.available_date)
                : weekdays[Number(r.weekday)];

            const status=Number(r.is_available)
                ? 'Available'
                : 'Unavailable';

            const css=Number(r.is_available)
                ? 'rule-available'
                : 'rule-unavailable';

            return `
                <tr>
                    <td>${when}</td>

                    <td>
                        ${shortTime(r.starts_at)}
                        –
                        ${shortTime(r.ends_at)}
                    </td>

                    <td class="${css}">
                        ${status}
                    </td>

                    <td>
                        <button
                            type="button"
                            onclick="deleteRule(${Number(r.id)})">
                            Delete
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
}

ruleType.onchange=()=>{

    const specific=
        ruleType.value==='date';

    weekdayContainer.style.display=
        specific ? 'none' : '';

    dateContainer.style.display=
        specific ? '' : 'none';

    weekday.disabled=specific;
    availableDate.disabled=!specific;

    if(specific){
        weekday.value='1';
    }else{
        availableDate.value='';
    }
};

contractorId.onchange=loadAvailability;

availabilityForm.onsubmit=async e=>{

    e.preventDefault();

    availabilityError.textContent='';

    try{

        if(!contractorId.value){
            throw new Error(
                'Select a contractor'
            );
        }

        const specific=
            ruleType.value==='date';

        if(specific && !availableDate.value){
            throw new Error(
                'Choose a specific date'
            );
        }

        const payload={
            contractor_id:
                Number(contractorId.value),

            weekday:
                specific
                    ? null
                    : Number(weekday.value),

            available_date:
                specific
                    ? availableDate.value
                    : null,

            starts_at:
                startsAt.value,

            ends_at:
                endsAt.value,

            is_available:
                isAvailable.value==='1'
        };

        await api(
            '/api/contractor_availability.php',
            {
                method:'POST',
                headers:{
                    'Content-Type':
                        'application/json'
                },
                body:JSON.stringify(payload)
            }
        );

        await loadAvailability();

    }catch(e){

        availabilityError.textContent=
            e.message;
    }
};

async function deleteRule(id){

    if(!confirm(
        'Delete this availability rule?'
    )){
        return;
    }

    try{

        await api(
            '/api/contractor_availability.php',
            {
                method:'DELETE',
                headers:{
                    'Content-Type':
                        'application/json'
                },
                body:JSON.stringify({id})
            }
        );

        await loadAvailability();

    }catch(e){

        availabilityError.textContent=
            e.message;
    }
}

document.addEventListener(
    'DOMContentLoaded',
    async ()=>{

        try{

            ruleType.dispatchEvent(
                new Event('change')
            );

            await loadContractors();

        }catch(e){

            availabilityError.textContent=
                e.message;
        }
    }
);

</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
"""


FILES["scheduling.php"] = r"""<?php
$pageTitle='Schedule';
require __DIR__.'/partials/app_header.php';
?>

<script src="<?=htmlspecialchars(app_url('/events/dist/index.global.min.js'))?>"></script>

<style>
.scheduler-layout{
    display:grid;
    grid-template-columns:320px minmax(0,1fr);
    gap:18px;
}
.scheduler-form label{
    display:block;
    font-size:13px;
    font-weight:600;
    margin-top:10px;
}
.scheduler-form input,
.scheduler-form select,
.scheduler-form textarea{
    width:100%;
    box-sizing:border-box;
    margin-top:4px;
}
.scheduler-form textarea{
    min-height:70px;
}
#calendar{
    min-height:700px;
}
@media(max-width:900px){
    .scheduler-layout{
        grid-template-columns:1fr;
    }
}
</style>

<h1>Schedule</h1>

<div class="scheduler-layout">

<div class="card">
    <h2 id="formTitle">New Appointment</h2>

    <form id="appointmentForm" class="scheduler-form">

        <input type="hidden" name="id" id="appointmentId">

        <label>Client</label>
        <select name="client_id" id="clientId">
            <option value="">No client</option>
        </select>

        <label>Service</label>
        <select name="service_id" id="serviceId">
            <option value="">No service</option>
        </select>

        <label>Contractor</label>
        <select id="contractorId">
            <option value="">Unassigned</option>
        </select>

        <label>Title</label>
        <input name="title"
               id="appointmentTitle"
               required
               placeholder="Appointment">

        <label>Start</label>
        <input name="starts_at"
               id="startsAt"
               type="datetime-local"
               required>

        <label>End</label>
        <input name="ends_at"
               id="endsAt"
               type="datetime-local"
               required>

        <label>Status</label>
        <select name="status" id="appointmentStatus">
            <option value="tentative">Tentative</option>
            <option value="scheduled" selected>Scheduled</option>
            <option value="confirmed">Confirmed</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="no_show">No Show</option>
        </select>

        <label>Location</label>
        <input name="location"
               id="appointmentLocation"
               placeholder="Location">

        <label>Description</label>
        <textarea name="description"
                  id="appointmentDescription"
                  placeholder="Notes"></textarea>

        <div style="margin-top:15px">
            <button type="submit" id="saveButton">Create Appointment</button>
            <button type="button" id="newButton">Clear</button>
            <button type="button" id="deleteButton"
                    style="display:none">Delete</button>
<button type="button"
        id="invoiceButton"
        style="display:none">
    Generate Invoice
</button>
        </div>

        <p id="appointmentError" class="danger"></p>
    </form>
</div>

<div class="card">
    <div id="calendar"></div>
</div>

</div>

<script>
let calendar;
let services=[];

const appointmentForm = document.getElementById('appointmentForm');
const appointmentId = document.getElementById('appointmentId');
const invoiceButton = document.getElementById('invoiceButton');
const clientId = document.getElementById('clientId');
const serviceId = document.getElementById('serviceId');
const contractorId = document.getElementById('contractorId');
const appointmentTitle = document.getElementById('appointmentTitle');
const startsAt = document.getElementById('startsAt');
const endsAt = document.getElementById('endsAt');
const appointmentStatus = document.getElementById('appointmentStatus');
const appointmentLocation = document.getElementById('appointmentLocation');
const appointmentDescription = document.getElementById('appointmentDescription');
const appointmentError = document.getElementById('appointmentError');
const formTitle = document.getElementById('formTitle');
const saveButton = document.getElementById('saveButton');
const newButton = document.getElementById('newButton');
const deleteButton = document.getElementById('deleteButton');

function esc(s){
    return String(s ?? '').replace(/[&<>"']/g,x=>({
        '&':'&amp;',
        '<':'&lt;',
        '>':'&gt;',
        '"':'&quot;',
        "'":'&#039;'
    }[x]));
}

function localDateTime(value){
    if(!value) return '';

    const d=new Date(value);

    const pad=n=>String(n).padStart(2,'0');

    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function sqlDateTime(value){
    if(!value) return null;
    return value.replace('T',' ') + ':00';
}

async function loadLookups(){

    const [clientsData,contractorsData,servicesData]=await Promise.all([
        api('/api/clients.php'),
        api('/api/contractors.php'),
        api('/api/services.php')
    ]);

    clientId.innerHTML=
        '<option value="">No client</option>'+
        clientsData.clients.map(c=>
            `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name)}</option>`
        ).join('');

    contractorId.innerHTML=
        '<option value="">Unassigned</option>'+
        contractorsData.contractors.map(c=>
            `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name)}</option>`
        ).join('');

    services=servicesData.services.filter(s=>Number(s.active));

    serviceId.innerHTML=
        '<option value="">No service</option>'+
        services.map(s=>
            `<option value="${s.id}">${esc(s.name)} — ${Number(s.duration_minutes)} min</option>`
        ).join('');
}

function resetForm(){
    invoiceButton.style.display = 'none';


    appointmentForm.reset();

    appointmentId.value='';
    appointmentStatus.value='scheduled';

    formTitle.textContent='New Appointment';
    saveButton.textContent='Create Appointment';
    deleteButton.style.display='none';
    appointmentError.textContent='';
}

serviceId.onchange=()=>{

    const service=services.find(
        s=>String(s.id)===String(serviceId.value)
    );

    if(!service || !startsAt.value) return;

    const start=new Date(startsAt.value);
    start.setMinutes(start.getMinutes()+Number(service.duration_minutes));

    endsAt.value=localDateTime(start);
};

async function loadEvents(info,success,failure){

    try {

        const d=await api(
            '/api/appointments.php?start='+
            encodeURIComponent(info.startStr)+
            '&end='+
            encodeURIComponent(info.endStr)
        );

        success(d.appointments.map(a=>({

            id:String(a.id),

            title:
                (a.client_first
                    ? `${a.client_first} ${a.client_last} — `
                    : '') +
                (a.service_name || a.title),

            start:a.starts_at,
            end:a.ends_at,

            backgroundColor:
                a.contractor_colors
                    ? a.contractor_colors.split(',')[0]
                    : undefined,

            borderColor:
                a.contractor_colors
                    ? a.contractor_colors.split(',')[0]
                    : undefined,

            extendedProps:a

        })));

    } catch(e){
        failure(e);
    }
}

async function persistCalendarMove(info){

    const event=info.event;
    const a=event.extendedProps;

    try{

        const contractorIds=String(a.contractor_ids || '')
            .split(',')
            .filter(Boolean)
            .map(Number);

        await api('/api/appointments.php',{
            method:'PUT',
            headers:{
                'Content-Type':'application/json'
            },
            body:JSON.stringify({
                id:Number(event.id),
                client_id:a.client_id || null,
                service_id:a.service_id || null,
                title:a.title || 'Appointment',
                description:a.description || null,
                starts_at:sqlDateTime(
                    localDateTime(event.start)
                ),
                ends_at:sqlDateTime(
                    localDateTime(event.end)
                ),
                status:a.status || 'scheduled',
                location:a.location || null,
                is_public:Number(a.is_public || 0) === 1,
                contractor_ids:contractorIds
            })
        });

        calendar.refetchEvents();

    }catch(e){

        info.revert();

        alert(e.message);
    }
}

function editAppointment(event){

    const a=event.extendedProps;

    appointmentId.value=event.id;

    clientId.value=a.client_id || '';
    serviceId.value=a.service_id || '';

    const contractors=String(a.contractor_ids || '')
        .split(',')
        .filter(Boolean);

    contractorId.value=contractors[0] || '';

    appointmentTitle.value=a.title || 'Appointment';
    startsAt.value=localDateTime(a.starts_at);
    endsAt.value=localDateTime(a.ends_at);
    appointmentStatus.value=a.status || 'scheduled';
    appointmentLocation.value=a.location || '';
    appointmentDescription.value=a.description || '';

    formTitle.textContent='Edit Appointment';
    saveButton.textContent='Save Changes';
    deleteButton.style.display='inline-block';
    invoiceButton.style.display =
        a.status === 'completed'
            ? 'inline-block'
            : 'none';


    window.scrollTo({top:0,behavior:'smooth'});
}

appointmentForm.onsubmit=async e=>{

    e.preventDefault();
    appointmentError.textContent='';

    try {

        const d=Object.fromEntries(
            new FormData(appointmentForm)
        );

        // Explicitly capture relationship selections.
        d.client_id = clientId.value || null;
        d.service_id = serviceId.value || null;

        d.contractor_ids =
            contractorId.value
                ? [Number(contractorId.value)]
                : [];

        d.starts_at=sqlDateTime(d.starts_at);
        d.ends_at=sqlDateTime(d.ends_at);

        const editing=Boolean(d.id);

        if(editing){
            d.id=Number(d.id);
        } else {
            delete d.id;
        }

        await api('/api/appointments.php',{
            method:editing ? 'PUT' : 'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify(d)
        });

        resetForm();
        calendar.refetchEvents();

    } catch(e){
        appointmentError.textContent=e.message;
    }
};

newButton.onclick=resetForm;

deleteButton.onclick=async ()=>{

    if(!appointmentId.value) return;

    if(!confirm('Delete this appointment?')) return;

    try {

        await api('/api/appointments.php',{
            method:'DELETE',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
                id:Number(appointmentId.value)
            })
        });

        resetForm();
        calendar.refetchEvents();

    } catch(e){
        appointmentError.textContent=e.message;
    }
};


invoiceButton.onclick = async () => {

    if (!appointmentId.value) {
        alert('Select an appointment first.');
        return;
    }

    if (!confirm(
        'Generate an invoice for this completed appointment?'
    )) {
        return;
    }

    if (typeof appointmentError !== 'undefined') {
        appointmentError.textContent = '';
    }

    try {

        const result = await api(
            '/api/invoices.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    appointment_id:
                        Number(appointmentId.value)
                })
            }
        );

        alert(
            `Invoice ${result.invoice_number} created for $${Number(result.total).toFixed(2)}`
        );

        window.location.href =
            window.BRITE_BASE + '/billing.php';

    } catch (e) {

        if (typeof appointmentError !== 'undefined') {
            appointmentError.textContent = e.message;
        } else {
            alert(e.message);
        }
    }
};

document.addEventListener('DOMContentLoaded',async ()=>{

    try {

        await loadLookups();

        calendar=new FullCalendar.Calendar(
            document.getElementById('calendar'),
            {
                initialView:'dayGridMonth',

                headerToolbar:{
                    left:'prev,next today',
                    center:'title',
                    right:'dayGridMonth,timeGridWeek,timeGridDay'
                },

                selectable:true,
                editable:true,
                eventDurationEditable:true,
                eventStartEditable:true,

                events:loadEvents,

                eventDrop:async info=>{
                    await persistCalendarMove(info);
                },

                eventResize:async info=>{
                    await persistCalendarMove(info);
                },

                select:info=>{
                    resetForm();

                    startsAt.value=localDateTime(info.start);

                    let end=new Date(info.start);
                    end.setHours(end.getHours()+1);

                    endsAt.value=localDateTime(end);
                },

                eventClick:info=>{
                    editAppointment(info.event);
                }
            }
        );

        calendar.render();

    } catch(e){
        appointmentError.textContent=e.message;
    }
});
</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
"""


FILES["migrations/003_invoice_appointment_unique.sql"] = r"""ALTER TABLE invoices
ADD UNIQUE KEY uq_invoice_appointment (
    tenant_id,
    appointment_id
);
"""

def migrate(env):
    mysql=shutil.which("mysql")
    if not mysql:
        print("mysql CLI not found; files generated but migrations were not executed.", file=sys.stderr); return False
    host=env.get("DB_HOST","127.0.0.1");port=env.get("DB_PORT","3306")
    name=env.get("DB_NAME","britescheduler");user=env.get("DB_USER","britescheduler");pw=env.get("DB_PASS","")
    # Create DB must be done by a privileged operator if it does not exist.
    for sqlfile in sorted((ROOT/"migrations").glob("*.sql")):
        version=sqlfile.name
        check=[mysql,f"-h{host}",f"-P{port}",f"-u{user}",f"-p{pw}",name,"-Nse",
               f"SELECT COUNT(*) FROM schema_migrations WHERE version='{version}'"] if version!="001_core.sql" else None
        if check:
            r=subprocess.run(check,capture_output=True,text=True)
            if r.returncode==0 and r.stdout.strip()=="1":
                print("SKIP",version);continue
        print("MIGRATE",version)
        proc=subprocess.run([mysql,f"-h{host}",f"-P{port}",f"-u{user}",f"-p{pw}",name],
                            input=sqlfile.read_text(),text=True)
        if proc.returncode: return False
        # 001 creates schema_migrations itself
        mark=subprocess.run([mysql,f"-h{host}",f"-P{port}",f"-u{user}",f"-p{pw}",name,
            "-e",f"INSERT IGNORE INTO schema_migrations(version) VALUES('{version}')"])
        if mark.returncode:return False
    return True

def read_env():
    out={}
    p=ROOT/".env"
    if p.exists():
        for line in p.read_text().splitlines():
            if "=" in line and not line.lstrip().startswith("#"):
                k,v=line.split("=",1);out[k.strip()]=v.strip().strip("\"'")
    return out

def main():
    ap=argparse.ArgumentParser()
    ap.add_argument("--migrate",action="store_true",help="execute MySQL migrations after generating")
    ap.add_argument("--activate",action="store_true",help="replace selected legacy root pages with v2 pages (backed up first)")
    ap.add_argument("--yes",action="store_true",help="non-interactive confirmation")
    args=ap.parse_args()

    if not (ROOT/".git").exists():
        sys.exit("Run this wizard from the BriteScheduler Git repository root (directory containing .git).")
    print("BriteScheduler completion wizard")
    print("Root:",ROOT)
    print("Backup:",BACKUP)
    if not args.yes:
        ans=input("Generate the multi-tenant application layer here? [y/N] ").strip().lower()
        if ans not in ("y","yes"): return

    BACKUP.mkdir(parents=True,exist_ok=True)
    for rel,content in FILES.items():
        if rel==".gitignore": continue
        write_file(rel,content)
    merge_gitignore()

    if not (ROOT/".env").exists():
        shutil.copy2(ROOT/".env.example",ROOT/".env")
        print("CREATE .env (edit DB credentials before migration)")

    if args.activate:
        activate_pages()
    else:
        print("NOTE: v2 pages generated but legacy root dashboard/client/contractor/sign-in pages were not replaced.")
        print("      Re-run with --activate after reviewing them.")

    if args.migrate:
        env=read_env()
        if env.get("DB_PASS") in ("","change-me"):
            print("Refusing migration: set DB credentials in .env first.",file=sys.stderr)
        else:
            migrate(env)

    print("\nDONE.")
    print("1) Review: git diff --stat && git diff")
    print("2) Configure .env (never commit it)")
    print("3) Run migrations: python3 brite_complete_wizard.py --migrate --yes")
    print("4) Create owner with setup/create_admin.php")
    print("5) Activate v2 pages after review: python3 brite_complete_wizard.py --activate --yes")
    print("6) Test locally, then git add/commit/push.")

if __name__=="__main__":
    main()
