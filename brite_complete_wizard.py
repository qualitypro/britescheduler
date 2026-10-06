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

FILES["migrations/004_tenant_business_profile.sql"] = r"""ALTER TABLE tenants
    ADD COLUMN business_email VARCHAR(190) NULL AFTER currency,
    ADD COLUMN business_phone VARCHAR(40) NULL AFTER business_email,
    ADD COLUMN website VARCHAR(190) NULL AFTER business_phone,
    ADD COLUMN address1 VARCHAR(190) NULL AFTER website,
    ADD COLUMN address2 VARCHAR(190) NULL AFTER address1,
    ADD COLUMN city VARCHAR(100) NULL AFTER address2,
    ADD COLUMN state VARCHAR(100) NULL AFTER city,
    ADD COLUMN postal_code VARCHAR(30) NULL AFTER state,
    ADD COLUMN invoice_footer TEXT NULL AFTER postal_code,
    ADD COLUMN payment_instructions TEXT NULL AFTER invoice_footer;
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

# BEGIN LIVE-SYNC OVERRIDES
# Generated from the tested live application.
# Later assignments intentionally override older templates above.

FILES['app/bootstrap.php'] = '<?php\ndeclare(strict_types=1);\n\n$root = dirname(__DIR__);\n\nfunction load_env(string $file): void {\n    if (!is_file($file)) return;\n    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {\n        $line = trim($line);\n        if ($line === \'\' || str_starts_with($line, \'#\') || !str_contains($line, \'=\')) continue;\n        [$k, $v] = array_map(\'trim\', explode(\'=\', $line, 2));\n        $v = trim($v, "\\"\'");\n        if (getenv($k) === false) putenv("$k=$v");\n        $_ENV[$k] = $v;\n    }\n}\nload_env($root . \'/.env\');\n\ndate_default_timezone_set(getenv(\'APP_TIMEZONE\') ?: \'UTC\');\nif (session_status() !== PHP_SESSION_ACTIVE) {\n    session_name(getenv(\'SESSION_NAME\') ?: \'britescheduler\');\n    session_set_cookie_params([\n        \'httponly\' => true,\n        \'secure\' => (!empty($_SERVER[\'HTTPS\']) && $_SERVER[\'HTTPS\'] !== \'off\'),\n        \'samesite\' => \'Lax\',\n        \'path\' => \'/\',\n    ]);\n    session_start();\n}\n\nrequire_once __DIR__ . \'/Database.php\';\nrequire_once __DIR__ . \'/Auth.php\';\nrequire_once __DIR__ . \'/Http.php\';\nrequire_once __DIR__ . \'/Csrf.php\';\n\n/**\n * Build an absolute URL inside the BriteScheduler installation.\n */\nfunction app_url(string $path = \'\'): string\n{\n    $base = rtrim($_ENV[\'APP_URL\'] ?? \'http://localhost/dashboard\', \'/\');\n\n    if ($path === \'\') {\n        return $base;\n    }\n\n    return $base . \'/\' . ltrim($path, \'/\');\n}\n'

FILES['app/Auth.php'] = '<?php\ndeclare(strict_types=1);\n\nfinal class Auth {\n    public static function user(): ?array {\n        if (empty($_SESSION[\'user_id\'])) return null;\n        $q = Database::connection()->prepare(\n            "SELECT id,email,first_name,last_name,status FROM users WHERE id=? AND status=\'active\'"\n        );\n        $q->execute([(int)$_SESSION[\'user_id\']]);\n        return $q->fetch() ?: null;\n    }\n\n    public static function requireUser(): array {\n        $u = self::user();\n        if (!$u) {\n            if (str_starts_with($_SERVER[\'REQUEST_URI\'] ?? \'\', \'/api/\')) json_response([\'error\'=>\'Unauthenticated\'], 401);\n            header(\'Location: \'.app_url(\'/sign-in.v2.php\')); exit;\n        }\n        return $u;\n    }\n\n    public static function memberships(int $userId): array {\n        $q = Database::connection()->prepare(\n            "SELECT m.tenant_id,m.role,t.name,t.slug\n             FROM tenant_memberships m JOIN tenants t ON t.id=m.tenant_id\n             WHERE m.user_id=? AND m.status=\'active\' AND t.status=\'active\' ORDER BY t.name"\n        );\n        $q->execute([$userId]);\n        return $q->fetchAll();\n    }\n\n    public static function tenant(): array {\n        $u = self::requireUser();\n        $memberships = self::memberships((int)$u[\'id\']);\n        if (!$memberships) json_response([\'error\'=>\'No active tenant membership\'], 403);\n\n        $requested = isset($_SESSION[\'tenant_id\']) ? (int)$_SESSION[\'tenant_id\'] : (int)$memberships[0][\'tenant_id\'];\n        foreach ($memberships as $m) {\n            if ((int)$m[\'tenant_id\'] === $requested) {\n                $_SESSION[\'tenant_id\'] = $requested;\n                $_SESSION[\'tenant_role\'] = $m[\'role\'];\n                return $m;\n            }\n        }\n        $_SESSION[\'tenant_id\'] = (int)$memberships[0][\'tenant_id\'];\n        $_SESSION[\'tenant_role\'] = $memberships[0][\'role\'];\n        return $memberships[0];\n    }\n\n    public static function tenantId(): int { return (int)self::tenant()[\'tenant_id\']; }\n\n    public static function homeUrl(?string $role = null): string {\n        if ($role === null) {\n            $role = (string)self::tenant()[\'role\'];\n        }\n\n        return match ($role) {\n            \'client\' =>\n                app_url(\'/client-dashboard.php\'),\n\n            \'contractor\' =>\n                app_url(\'/contractor-dashboard.php\'),\n\n            default =>\n                app_url(\'/dashboard.v2.php\'),\n        };\n    }\n\n    public static function requireRole(string ...$roles): array {\n        $t = self::tenant();\n        if (!in_array($t[\'role\'], $roles, true)) json_response([\'error\'=>\'Forbidden\'], 403);\n        return $t;\n    }\n}\n'

FILES['api/auth/login.php'] = '<?php\nrequire_once dirname(__DIR__,2).\'/app/bootstrap.php\';\nrequire_method(\'POST\'); verify_csrf();\n$d=request_data(); $email=strtolower(trim($d[\'email\']??\'\')); $password=$d[\'password\']??\'\';\n$q=Database::connection()->prepare("SELECT * FROM users WHERE email=? AND status=\'active\'");\n$q->execute([$email]); $u=$q->fetch();\nif(!$u || !password_verify($password,$u[\'password_hash\'])) json_response([\'error\'=>\'Invalid email or password\'],422);\nsession_regenerate_id(true); $_SESSION[\'user_id\']=(int)$u[\'id\'];\nDatabase::connection()->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$u[\'id\']]);\n$m=Auth::memberships((int)$u[\'id\']);\n\nif(!$m){\n    $_SESSION=[];\n    session_destroy();\n    json_response([\'error\'=>\'No active tenant membership\'],403);\n}\n\n$_SESSION[\'tenant_id\']=(int)$m[0][\'tenant_id\'];\n$_SESSION[\'tenant_role\']=$m[0][\'role\'];\n\njson_response([\n    \'ok\'=>true,\n    \'redirect\'=>Auth::homeUrl($m[0][\'role\'])\n]);\n'

FILES['api/auth/logout.php'] = "<?php\nrequire_once dirname(__DIR__,2).'/app/bootstrap.php';\nrequire_method('POST'); verify_csrf();\n$_SESSION=[]; session_destroy(); json_response(['ok'=>true]);\n"

FILES['api/auth/register.php'] = '<?php\n\ndeclare(strict_types=1);\n\nrequire_once dirname(__DIR__, 2) . \'/app/bootstrap.php\';\n\nrequire_method(\'POST\');\nverify_csrf();\n\n$d = request_data();\n\n$type = strtolower(trim((string)($d[\'type\'] ?? \'\')));\n$firstName = trim((string)($d[\'first_name\'] ?? \'\'));\n$lastName = trim((string)($d[\'last_name\'] ?? \'\'));\n$email = strtolower(trim((string)($d[\'email\'] ?? \'\')));\n$phone = trim((string)($d[\'phone\'] ?? \'\'));\n$company = trim((string)($d[\'company_name\'] ?? \'\'));\n$business = trim((string)($d[\'business_name\'] ?? \'\'));\n$password = (string)($d[\'password\'] ?? \'\');\n$confirm = (string)($d[\'password_confirm\'] ?? \'\');\n\nif (!in_array($type, [\'client\', \'contractor\'], true)) {\n    json_response([\'error\' => \'Invalid account type\'], 422);\n}\n\nif ($firstName === \'\' || $lastName === \'\') {\n    json_response([\'error\' => \'First and last name are required\'], 422);\n}\n\nif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {\n    json_response([\'error\' => \'A valid email address is required\'], 422);\n}\n\nif (strlen($password) < 8) {\n    json_response([\'error\' => \'Password must be at least 8 characters\'], 422);\n}\n\nif ($password !== $confirm) {\n    json_response([\'error\' => \'Passwords do not match\'], 422);\n}\n\n$db = Database::connection();\n\n/*\n * Public registration currently joins the primary active tenant.\n *\n * For the current single-business deployment this is the intended\n * behavior. Later, SaaS tenant registration can use invitations,\n * tenant slugs, or business-specific registration URLs.\n */\n$tenant = $db->query(\n    "SELECT id\n       FROM tenants\n      WHERE status=\'active\'\n      ORDER BY id\n      LIMIT 1"\n)->fetch();\n\nif (!$tenant) {\n    json_response([\'error\' => \'Registration is currently unavailable\'], 503);\n}\n\n$tenantId = (int)$tenant[\'id\'];\n\n$q = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1");\n$q->execute([$email]);\n\nif ($q->fetch()) {\n    json_response([\'error\' => \'An account with this email already exists\'], 409);\n}\n\ntry {\n\n    $db->beginTransaction();\n\n    $q = $db->prepare(\n        "INSERT INTO users\n            (email,password_hash,first_name,last_name,phone,status)\n         VALUES\n            (?,?,?,?,?,\'active\')"\n    );\n\n    $q->execute([\n        $email,\n        password_hash($password, PASSWORD_DEFAULT),\n        $firstName,\n        $lastName,\n        $phone !== \'\' ? $phone : null\n    ]);\n\n    $userId = (int)$db->lastInsertId();\n\n    $q = $db->prepare(\n        "INSERT INTO tenant_memberships\n            (tenant_id,user_id,role,status)\n         VALUES\n            (?,?,?,\'active\')"\n    );\n\n    $q->execute([\n        $tenantId,\n        $userId,\n        $type\n    ]);\n\n    if ($type === \'client\') {\n\n        $q = $db->prepare(\n            "INSERT INTO clients\n                (\n                    tenant_id,\n                    user_id,\n                    company_name,\n                    first_name,\n                    last_name,\n                    email,\n                    phone,\n                    status\n                )\n             VALUES\n                (?,?,?,?,?,?,?,\'active\')"\n        );\n\n        $q->execute([\n            $tenantId,\n            $userId,\n            $company !== \'\' ? $company : null,\n            $firstName,\n            $lastName,\n            $email,\n            $phone !== \'\' ? $phone : null\n        ]);\n\n    } else {\n\n        $q = $db->prepare(\n            "INSERT INTO contractors\n                (\n                    tenant_id,\n                    user_id,\n                    first_name,\n                    last_name,\n                    business_name,\n                    email,\n                    phone,\n                    status\n                )\n             VALUES\n                (?,?,?,?,?,?,?,\'active\')"\n        );\n\n        $q->execute([\n            $tenantId,\n            $userId,\n            $firstName,\n            $lastName,\n            $business !== \'\' ? $business : null,\n            $email,\n            $phone !== \'\' ? $phone : null\n        ]);\n    }\n\n    $db->commit();\n\n    /*\n     * Sign the newly registered user in automatically.\n     */\n    session_regenerate_id(true);\n\n    $_SESSION[\'user_id\'] = $userId;\n    $_SESSION[\'tenant_id\'] = $tenantId;\n    $_SESSION[\'tenant_role\'] = $type;\n\n    json_response([\n        \'ok\' => true,\n        \'role\' => $type,\n        \'redirect\' => Auth::homeUrl($type)\n    ], 201);\n\n} catch (Throwable $e) {\n\n    if ($db->inTransaction()) {\n        $db->rollBack();\n    }\n\n    error_log(\n        \'BriteScheduler registration error: \' .\n        $e->getMessage()\n    );\n\n    json_response([\n        \'error\' => \'Unable to create account\'\n    ], 500);\n}\n'

FILES['api/clients.php'] = '<?php\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\n$u=Auth::requireUser(); $tid=Auth::tenantId(); $pdo=Database::connection();\nif($_SERVER[\'REQUEST_METHOD\']===\'GET\'){\n  $q=$pdo->prepare("SELECT * FROM clients WHERE tenant_id=? ORDER BY last_name,first_name"); $q->execute([$tid]);\n  json_response([\'clients\'=>$q->fetchAll()]);\n}\nverify_csrf(); Auth::requireRole(\'owner\',\'admin\',\'scheduler\',\'accounting\'); $d=request_data();\nif($_SERVER[\'REQUEST_METHOD\']===\'POST\'){\n  $q=$pdo->prepare("INSERT INTO clients(tenant_id,company_name,first_name,last_name,email,phone,address1,address2,city,state,postal_code,notes,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");\n  $q->execute([$tid,$d[\'company_name\']??null,trim($d[\'first_name\']??\'\'),trim($d[\'last_name\']??\'\'),$d[\'email\']??null,$d[\'phone\']??null,$d[\'address1\']??null,$d[\'address2\']??null,$d[\'city\']??null,$d[\'state\']??null,$d[\'postal_code\']??null,$d[\'notes\']??null,$d[\'status\']??\'active\']);\n  json_response([\'ok\'=>true,\'id\'=>(int)$pdo->lastInsertId()],201);\n}\nif($_SERVER[\'REQUEST_METHOD\']===\'PUT\'){\n  $id=(int)($d[\'id\']??0);\n  $q=$pdo->prepare("UPDATE clients SET company_name=?,first_name=?,last_name=?,email=?,phone=?,address1=?,address2=?,city=?,state=?,postal_code=?,notes=?,status=? WHERE id=? AND tenant_id=?");\n  $q->execute([$d[\'company_name\']??null,$d[\'first_name\']??\'\',$d[\'last_name\']??\'\',$d[\'email\']??null,$d[\'phone\']??null,$d[\'address1\']??null,$d[\'address2\']??null,$d[\'city\']??null,$d[\'state\']??null,$d[\'postal_code\']??null,$d[\'notes\']??null,$d[\'status\']??\'active\',$id,$tid]);\n  json_response([\'ok\'=>true]);\n}\njson_response([\'error\'=>\'Method not allowed\'],405);\n'

FILES['api/contractors.php'] = '<?php\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\n$u=Auth::requireUser(); $tid=Auth::tenantId(); $pdo=Database::connection();\nif($_SERVER[\'REQUEST_METHOD\']===\'GET\'){\n $q=$pdo->prepare("SELECT * FROM contractors WHERE tenant_id=? ORDER BY last_name,first_name");$q->execute([$tid]);json_response([\'contractors\'=>$q->fetchAll()]);\n}\nverify_csrf();Auth::requireRole(\'owner\',\'admin\',\'scheduler\');$d=request_data();\nif($_SERVER[\'REQUEST_METHOD\']===\'POST\'){\n $q=$pdo->prepare("INSERT INTO contractors(tenant_id,first_name,last_name,business_name,email,phone,hourly_rate,color,skills,status) VALUES(?,?,?,?,?,?,?,?,?,?)");\n $q->execute([$tid,$d[\'first_name\']??\'\',$d[\'last_name\']??\'\',$d[\'business_name\']??null,$d[\'email\']??null,$d[\'phone\']??null,$d[\'hourly_rate\']??null,$d[\'color\']??null,$d[\'skills\']??null,$d[\'status\']??\'active\']);\n json_response([\'ok\'=>true,\'id\'=>(int)$pdo->lastInsertId()],201);\n}\nif($_SERVER[\'REQUEST_METHOD\']===\'PUT\'){\n $id=(int)($d[\'id\']??0);$q=$pdo->prepare("UPDATE contractors SET first_name=?,last_name=?,business_name=?,email=?,phone=?,hourly_rate=?,color=?,skills=?,status=? WHERE id=? AND tenant_id=?");\n $q->execute([$d[\'first_name\']??\'\',$d[\'last_name\']??\'\',$d[\'business_name\']??null,$d[\'email\']??null,$d[\'phone\']??null,$d[\'hourly_rate\']??null,$d[\'color\']??null,$d[\'skills\']??null,$d[\'status\']??\'active\',$id,$tid]);json_response([\'ok\'=>true]);\n}\njson_response([\'error\'=>\'Method not allowed\'],405);\n'

FILES['api/services.php'] = '<?php\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\nAuth::requireUser();$tid=Auth::tenantId();$pdo=Database::connection();\nif($_SERVER[\'REQUEST_METHOD\']===\'GET\'){$q=$pdo->prepare("SELECT * FROM services WHERE tenant_id=? ORDER BY name");$q->execute([$tid]);json_response([\'services\'=>$q->fetchAll()]);}\nverify_csrf();Auth::requireRole(\'owner\',\'admin\',\'scheduler\');$d=request_data();\nif($_SERVER[\'REQUEST_METHOD\']===\'POST\'){$q=$pdo->prepare("INSERT INTO services(tenant_id,name,description,duration_minutes,price,active) VALUES(?,?,?,?,?,?)");$q->execute([$tid,$d[\'name\']??\'\',$d[\'description\']??null,(int)($d[\'duration_minutes\']??60),$d[\'price\']??0,!empty($d[\'active\'])?1:0]);json_response([\'ok\'=>true,\'id\'=>(int)$pdo->lastInsertId()],201);}\njson_response([\'error\'=>\'Method not allowed\'],405);\n'

FILES['api/appointments.php'] = '<?php\n\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\n\n$u      = Auth::requireUser();\n$tenant = Auth::tenant();\n$tid    = (int)$tenant[\'tenant_id\'];\n$role   = (string)$tenant[\'role\'];\n$pdo    = Database::connection();\n\nfunction appointment_datetime(string $value, string $field): string\n{\n    $value = trim($value);\n\n    $formats = [\n        \'Y-m-d H:i:s\',\n        \'Y-m-d\\TH:i:s\',\n        \'Y-m-d\\TH:i\',\n        DateTimeInterface::ATOM\n    ];\n\n    foreach ($formats as $format) {\n        $dt = DateTime::createFromFormat($format, $value);\n\n        if ($dt && $dt->format($format) === $value) {\n            return $dt->format(\'Y-m-d H:i:s\');\n        }\n    }\n\n    try {\n        $dt = new DateTime($value);\n        return $dt->format(\'Y-m-d H:i:s\');\n    } catch (Throwable $e) {\n        throw new RuntimeException("Invalid {$field}");\n    }\n}\n\nfunction validate_appointment_status(string $status): string\n{\n    $allowed = [\n        \'tentative\',\n        \'scheduled\',\n        \'confirmed\',\n        \'in_progress\',\n        \'completed\',\n        \'cancelled\',\n        \'no_show\'\n    ];\n\n    if (!in_array($status, $allowed, true)) {\n        throw new RuntimeException(\'Invalid appointment status\');\n    }\n\n    return $status;\n}\n\nfunction tenant_reference(\n    PDO $pdo,\n    string $table,\n    int $tenantId,\n    $id,\n    string $label\n): ?int {\n    $id = int_or_null($id);\n\n    if ($id === null) {\n        return null;\n    }\n\n    $allowedTables = [\'clients\', \'services\', \'contractors\'];\n\n    if (!in_array($table, $allowedTables, true)) {\n        throw new RuntimeException(\'Invalid reference table\');\n    }\n\n    $q = $pdo->prepare(\n        "SELECT id FROM {$table}\n         WHERE id=? AND tenant_id=?"\n    );\n\n    $q->execute([$id, $tenantId]);\n\n    if (!$q->fetchColumn()) {\n        throw new RuntimeException("Invalid {$label}");\n    }\n\n    return $id;\n}\n\nfunction validate_contractors(\n    PDO $pdo,\n    int $tenantId,\n    array $contractorIds\n): array {\n    $result = [];\n\n    foreach ($contractorIds as $contractorId) {\n\n        $contractorId = (int)$contractorId;\n\n        if ($contractorId <= 0) {\n            continue;\n        }\n\n        tenant_reference(\n            $pdo,\n            \'contractors\',\n            $tenantId,\n            $contractorId,\n            \'contractor\'\n        );\n\n        $result[$contractorId] = $contractorId;\n    }\n\n    return array_values($result);\n}\n\nfunction check_contractor_conflicts(\n    PDO $pdo,\n    int $tenantId,\n    array $contractorIds,\n    string $startsAt,\n    string $endsAt,\n    ?int $excludeAppointmentId = null\n): void {\n\n    foreach ($contractorIds as $contractorId) {\n\n        $sql = "\n            SELECT\n                a.id,\n                a.title,\n                a.starts_at,\n                a.ends_at\n            FROM appointments a\n            INNER JOIN appointment_contractors ac\n                ON ac.appointment_id=a.id\n            WHERE\n                a.tenant_id=?\n                AND ac.contractor_id=?\n                AND a.status NOT IN (\'cancelled\',\'no_show\')\n                AND a.starts_at < ?\n                AND a.ends_at > ?\n        ";\n\n        $params = [\n            $tenantId,\n            $contractorId,\n            $endsAt,\n            $startsAt\n        ];\n\n        if ($excludeAppointmentId !== null) {\n            $sql .= " AND a.id<>?";\n            $params[] = $excludeAppointmentId;\n        }\n\n        $sql .= " LIMIT 1";\n\n        $q = $pdo->prepare($sql);\n        $q->execute($params);\n\n        $conflict = $q->fetch();\n\n        if ($conflict) {\n            throw new RuntimeException(\n                \'Contractor scheduling conflict with "\' .\n                $conflict[\'title\'] .\n                \'" (\' .\n                $conflict[\'starts_at\'] .\n                \' - \' .\n                $conflict[\'ends_at\'] .\n                \')\'\n            );\n        }\n    }\n}\n\nfunction check_contractor_availability(\n    PDO $pdo,\n    int $tenantId,\n    array $contractorIds,\n    string $startsAt,\n    string $endsAt\n): void {\n\n    $start = new DateTime($startsAt);\n    $end   = new DateTime($endsAt);\n\n    /*\n     * Availability rows are single-day rules.\n     * Do not allow an appointment to cross midnight.\n     */\n    if ($start->format(\'Y-m-d\') !== $end->format(\'Y-m-d\')) {\n        throw new RuntimeException(\n            \'Appointments assigned to contractors cannot cross midnight\'\n        );\n    }\n\n    $date      = $start->format(\'Y-m-d\');\n    $startTime = $start->format(\'H:i:s\');\n    $endTime   = $end->format(\'H:i:s\');\n\n    // PHP: Sunday=0 through Saturday=6.\n    $weekday = (int)$start->format(\'w\');\n\n    foreach ($contractorIds as $contractorId) {\n\n        /*\n         * Determine whether this contractor has availability\n         * configured at all. Contractors with no rules remain\n         * unrestricted for backward compatibility.\n         */\n        $configured = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM contractor_availability\n            WHERE tenant_id=?\n              AND contractor_id=?\n        ");\n\n        $configured->execute([\n            $tenantId,\n            $contractorId\n        ]);\n\n        if ((int)$configured->fetchColumn() === 0) {\n            continue;\n        }\n\n        /*\n         * Date-specific UNAVAILABLE rules take highest precedence.\n         * Any overlap blocks the appointment.\n         */\n        $blocked = $pdo->prepare("\n            SELECT id\n            FROM contractor_availability\n            WHERE tenant_id=?\n              AND contractor_id=?\n              AND available_date=?\n              AND is_available=0\n              AND starts_at < ?\n              AND ends_at > ?\n            LIMIT 1\n        ");\n\n        $blocked->execute([\n            $tenantId,\n            $contractorId,\n            $date,\n            $endTime,\n            $startTime\n        ]);\n\n        if ($blocked->fetchColumn()) {\n            throw new RuntimeException(\n                \'Contractor is unavailable during the requested time\'\n            );\n        }\n\n        /*\n         * If date-specific AVAILABLE rules exist for this date,\n         * they override the normal weekly schedule.\n         */\n        $dateRules = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM contractor_availability\n            WHERE tenant_id=?\n              AND contractor_id=?\n              AND available_date=?\n              AND is_available=1\n        ");\n\n        $dateRules->execute([\n            $tenantId,\n            $contractorId,\n            $date\n        ]);\n\n        if ((int)$dateRules->fetchColumn() > 0) {\n\n            $allowed = $pdo->prepare("\n                SELECT id\n                FROM contractor_availability\n                WHERE tenant_id=?\n                  AND contractor_id=?\n                  AND available_date=?\n                  AND is_available=1\n                  AND starts_at <= ?\n                  AND ends_at >= ?\n                LIMIT 1\n            ");\n\n            $allowed->execute([\n                $tenantId,\n                $contractorId,\n                $date,\n                $startTime,\n                $endTime\n            ]);\n\n            if (!$allowed->fetchColumn()) {\n                throw new RuntimeException(\n                    \'Appointment is outside contractor availability\'\n                );\n            }\n\n            continue;\n        }\n\n        /*\n         * Otherwise require the appointment to fit completely\n         * inside a recurring weekly AVAILABLE rule.\n         */\n        $weekly = $pdo->prepare("\n            SELECT id\n            FROM contractor_availability\n            WHERE tenant_id=?\n              AND contractor_id=?\n              AND available_date IS NULL\n              AND weekday=?\n              AND is_available=1\n              AND starts_at <= ?\n              AND ends_at >= ?\n            LIMIT 1\n        ");\n\n        $weekly->execute([\n            $tenantId,\n            $contractorId,\n            $weekday,\n            $startTime,\n            $endTime\n        ]);\n\n        if (!$weekly->fetchColumn()) {\n            throw new RuntimeException(\n                \'Appointment is outside contractor availability\'\n            );\n        }\n    }\n}\n\nfunction validate_times(string $startsAt, string $endsAt): void\n{\n    if (strtotime($endsAt) <= strtotime($startsAt)) {\n        throw new RuntimeException(\n            \'Appointment end time must be after start time\'\n        );\n    }\n}\n\n/*\n|--------------------------------------------------------------------------\n| GET\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'GET\') {\n\n    $start =\n        $_GET[\'start\']\n        ?? date(\'Y-m-01 00:00:00\');\n\n    $end =\n        $_GET[\'end\']\n        ?? date(\'Y-m-t 23:59:59\');\n\n\n    /*\n     * Base tenant filter.\n     */\n    $where = "\n        a.tenant_id=?\n        AND a.starts_at < ?\n        AND a.ends_at > ?\n    ";\n\n    $params=[\n        $tid,\n        $end,\n        $start\n    ];\n\n\n    /*\n     * CLIENT\n     *\n     * Resolve clients.id from authenticated\n     * users.id. Never trust client_id supplied\n     * by the browser.\n     */\n    if ($role === \'client\') {\n\n        $cq=$pdo->prepare("\n            SELECT id\n            FROM clients\n            WHERE tenant_id=?\n              AND user_id=?\n              AND status=\'active\'\n            LIMIT 1\n        ");\n\n        $cq->execute([\n            $tid,\n            (int)$u[\'id\']\n        ]);\n\n        $clientId=\n            (int)($cq->fetchColumn() ?: 0);\n\n        if ($clientId <= 0) {\n            json_response([\n                \'error\'=>\'Client profile not found\'\n            ],403);\n        }\n\n        $where .= "\n            AND a.client_id=?\n        ";\n\n        $params[]=$clientId;\n    }\n\n\n    /*\n     * CONTRACTOR\n     *\n     * Resolve contractors.id from the\n     * authenticated user and require an\n     * appointment_contractors assignment.\n     */\n    elseif ($role === \'contractor\') {\n\n        $cq=$pdo->prepare("\n            SELECT id\n            FROM contractors\n            WHERE tenant_id=?\n              AND user_id=?\n              AND status=\'active\'\n            LIMIT 1\n        ");\n\n        $cq->execute([\n            $tid,\n            (int)$u[\'id\']\n        ]);\n\n        $contractorId=\n            (int)($cq->fetchColumn() ?: 0);\n\n        if ($contractorId <= 0) {\n            json_response([\n                \'error\'=>\'Contractor profile not found\'\n            ],403);\n        }\n\n        $where .= "\n            AND EXISTS (\n                SELECT 1\n                FROM appointment_contractors own_ac\n                WHERE\n                    own_ac.appointment_id=a.id\n                    AND own_ac.contractor_id=?\n            )\n        ";\n\n        $params[]=$contractorId;\n    }\n\n\n    /*\n     * Only approved management roles may\n     * perform tenant-wide appointment reads.\n     */\n    elseif (\n        !in_array(\n            $role,\n            [\n                \'owner\',\n                \'admin\',\n                \'scheduler\',\n                \'accounting\'\n            ],\n            true\n        )\n    ) {\n\n        json_response([\n            \'error\'=>\'Forbidden\'\n        ],403);\n    }\n\n\n    $sql="\n        SELECT\n            a.*,\n\n            c.first_name client_first,\n            c.last_name client_last,\n\n            s.name service_name,\n            s.price service_price,\n            s.duration_minutes,\n\n            GROUP_CONCAT(\n                DISTINCT ac.contractor_id\n            ) contractor_ids,\n\n            GROUP_CONCAT(\n                DISTINCT ct.color\n            ) contractor_colors\n\n        FROM appointments a\n\n        LEFT JOIN clients c\n            ON c.id=a.client_id\n            AND c.tenant_id=a.tenant_id\n\n        LEFT JOIN services s\n            ON s.id=a.service_id\n            AND s.tenant_id=a.tenant_id\n\n        LEFT JOIN appointment_contractors ac\n            ON ac.appointment_id=a.id\n\n        LEFT JOIN contractors ct\n            ON ct.id=ac.contractor_id\n            AND ct.tenant_id=a.tenant_id\n\n        WHERE {$where}\n\n        GROUP BY a.id\n\n        ORDER BY a.starts_at\n    ";\n\n    $q=$pdo->prepare($sql);\n    $q->execute($params);\n\n    json_response([\n        \'appointments\'=>$q->fetchAll()\n    ]);\n}\n\n/*\n|--------------------------------------------------------------------------\n| Mutations\n|--------------------------------------------------------------------------\n*/\n\nverify_csrf();\n\nAuth::requireRole(\n    \'owner\',\n    \'admin\',\n    \'scheduler\'\n);\n\n$d = request_data();\n\n/*\n|--------------------------------------------------------------------------\n| POST\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {\n\n    try {\n\n        $clientId = tenant_reference(\n            $pdo,\n            \'clients\',\n            $tid,\n            $d[\'client_id\'] ?? null,\n            \'client\'\n        );\n\n        $serviceId = tenant_reference(\n            $pdo,\n            \'services\',\n            $tid,\n            $d[\'service_id\'] ?? null,\n            \'service\'\n        );\n\n        $contractorIds = validate_contractors(\n            $pdo,\n            $tid,\n            $d[\'contractor_ids\'] ?? []\n        );\n\n        $startsAt = appointment_datetime(\n            $d[\'starts_at\'] ?? \'\',\n            \'start time\'\n        );\n\n        $endsAt = appointment_datetime(\n            $d[\'ends_at\'] ?? \'\',\n            \'end time\'\n        );\n\n        validate_times($startsAt, $endsAt);\n\n        $status = validate_appointment_status(\n            $d[\'status\'] ?? \'scheduled\'\n        );\n\n        check_contractor_availability(\n            $pdo,\n            $tid,\n            $contractorIds,\n            $startsAt,\n            $endsAt\n        );\n\n        check_contractor_conflicts(\n            $pdo,\n            $tid,\n            $contractorIds,\n            $startsAt,\n            $endsAt\n        );\n\n        $pdo->beginTransaction();\n\n        $q = $pdo->prepare("\n            INSERT INTO appointments(\n                tenant_id,\n                client_id,\n                service_id,\n                title,\n                description,\n                starts_at,\n                ends_at,\n                status,\n                location,\n                is_public,\n                created_by\n            )\n            VALUES(?,?,?,?,?,?,?,?,?,?,?)\n        ");\n\n        $q->execute([\n            $tid,\n            $clientId,\n            $serviceId,\n            trim($d[\'title\'] ?? \'Appointment\'),\n            $d[\'description\'] ?? null,\n            $startsAt,\n            $endsAt,\n            $status,\n            $d[\'location\'] ?? null,\n            !empty($d[\'is_public\']) ? 1 : 0,\n            $u[\'id\']\n        ]);\n\n        $id = (int)$pdo->lastInsertId();\n\n        foreach ($contractorIds as $contractorId) {\n\n            $x = $pdo->prepare("\n                INSERT INTO appointment_contractors(\n                    appointment_id,\n                    contractor_id\n                )\n                VALUES(?,?)\n            ");\n\n            $x->execute([\n                $id,\n                $contractorId\n            ]);\n        }\n\n        $pdo->commit();\n\n        json_response([\n            \'ok\' => true,\n            \'id\' => $id\n        ], 201);\n\n    } catch (Throwable $e) {\n\n        if ($pdo->inTransaction()) {\n            $pdo->rollBack();\n        }\n\n        json_response([\n            \'error\' => $e->getMessage()\n        ], 422);\n    }\n}\n\n/*\n|--------------------------------------------------------------------------\n| PUT\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'PUT\') {\n\n    $id = (int)($d[\'id\'] ?? 0);\n\n    try {\n\n        $owned = $pdo->prepare("\n            SELECT id\n            FROM appointments\n            WHERE id=? AND tenant_id=?\n        ");\n\n        $owned->execute([$id, $tid]);\n\n        if (!$owned->fetchColumn()) {\n            throw new RuntimeException(\n                \'Appointment not found\'\n            );\n        }\n\n        $clientId = tenant_reference(\n            $pdo,\n            \'clients\',\n            $tid,\n            $d[\'client_id\'] ?? null,\n            \'client\'\n        );\n\n        $serviceId = tenant_reference(\n            $pdo,\n            \'services\',\n            $tid,\n            $d[\'service_id\'] ?? null,\n            \'service\'\n        );\n\n        $contractorIds = validate_contractors(\n            $pdo,\n            $tid,\n            $d[\'contractor_ids\'] ?? []\n        );\n\n        $startsAt = appointment_datetime(\n            $d[\'starts_at\'] ?? \'\',\n            \'start time\'\n        );\n\n        $endsAt = appointment_datetime(\n            $d[\'ends_at\'] ?? \'\',\n            \'end time\'\n        );\n\n        validate_times($startsAt, $endsAt);\n\n        $status = validate_appointment_status(\n            $d[\'status\'] ?? \'scheduled\'\n        );\n\n        check_contractor_availability(\n            $pdo,\n            $tid,\n            $contractorIds,\n            $startsAt,\n            $endsAt\n        );\n\n        check_contractor_conflicts(\n            $pdo,\n            $tid,\n            $contractorIds,\n            $startsAt,\n            $endsAt,\n            $id\n        );\n\n        $pdo->beginTransaction();\n\n        $q = $pdo->prepare("\n            UPDATE appointments\n            SET\n                client_id=?,\n                service_id=?,\n                title=?,\n                description=?,\n                starts_at=?,\n                ends_at=?,\n                status=?,\n                location=?,\n                is_public=?\n            WHERE id=? AND tenant_id=?\n        ");\n\n        $q->execute([\n            $clientId,\n            $serviceId,\n            $d[\'title\'] ?? \'Appointment\',\n            $d[\'description\'] ?? null,\n            $startsAt,\n            $endsAt,\n            $status,\n            $d[\'location\'] ?? null,\n            !empty($d[\'is_public\']) ? 1 : 0,\n            $id,\n            $tid\n        ]);\n\n        $pdo->prepare("\n            DELETE FROM appointment_contractors\n            WHERE appointment_id=?\n        ")->execute([$id]);\n\n        foreach ($contractorIds as $contractorId) {\n\n            $x = $pdo->prepare("\n                INSERT INTO appointment_contractors(\n                    appointment_id,\n                    contractor_id\n                )\n                VALUES(?,?)\n            ");\n\n            $x->execute([\n                $id,\n                $contractorId\n            ]);\n        }\n\n        $pdo->commit();\n\n        json_response([\'ok\' => true]);\n\n    } catch (Throwable $e) {\n\n        if ($pdo->inTransaction()) {\n            $pdo->rollBack();\n        }\n\n        json_response([\n            \'error\' => $e->getMessage()\n        ], 422);\n    }\n}\n\n/*\n|--------------------------------------------------------------------------\n| DELETE\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'DELETE\') {\n\n    $id = (int)($d[\'id\'] ?? 0);\n\n    $q = $pdo->prepare("\n        DELETE FROM appointments\n        WHERE id=? AND tenant_id=?\n    ");\n\n    $q->execute([$id, $tid]);\n\n    if (!$q->rowCount()) {\n        json_response([\n            \'error\' => \'Appointment not found\'\n        ], 404);\n    }\n\n    json_response([\'ok\' => true]);\n}\n\njson_response([\n    \'error\' => \'Method not allowed\'\n], 405);\n'

FILES['api/contractor_availability.php'] = '<?php\n\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\n\n$user = Auth::requireUser();\n$tenant = Auth::tenant();\n\n$tid  = (int)$tenant[\'tenant_id\'];\n$role = (string)$tenant[\'role\'];\n\n$pdo = Database::connection();\n\n$managementRoles = [\n    \'owner\',\n    \'admin\',\n    \'scheduler\'\n];\n\n/*\n|--------------------------------------------------------------------------\n| Resolve contractor\n|--------------------------------------------------------------------------\n|\n| Management users may specify contractor_id.\n|\n| Contractors NEVER control contractor_id. Their contractor record is\n| derived from the authenticated users.id -> contractors.user_id link.\n|\n*/\n\nfunction resolve_availability_contractor(\n    PDO $pdo,\n    int $tid,\n    array $user,\n    string $role,\n    array $managementRoles,\n    int $requestedId = 0\n): int {\n\n    if ($role === \'contractor\') {\n\n        $q=$pdo->prepare("\n            SELECT id\n            FROM contractors\n            WHERE tenant_id=?\n              AND user_id=?\n              AND status=\'active\'\n            LIMIT 1\n        ");\n\n        $q->execute([\n            $tid,\n            (int)$user[\'id\']\n        ]);\n\n        $id=(int)($q->fetchColumn() ?: 0);\n\n        if ($id <= 0) {\n            json_response([\n                \'error\'=>\'Contractor profile not found\'\n            ],403);\n        }\n\n        return $id;\n    }\n\n    if (!in_array($role,$managementRoles,true)) {\n        json_response([\'error\'=>\'Forbidden\'],403);\n    }\n\n    if ($requestedId <= 0) {\n        json_response([\n            \'error\'=>\'Contractor required\'\n        ],422);\n    }\n\n    $q=$pdo->prepare("\n        SELECT id\n        FROM contractors\n        WHERE id=?\n          AND tenant_id=?\n    ");\n\n    $q->execute([\n        $requestedId,\n        $tid\n    ]);\n\n    if (!$q->fetchColumn()) {\n        json_response([\n            \'error\'=>\'Contractor not found\'\n        ],404);\n    }\n\n    return $requestedId;\n}\n\n\n/*\n|--------------------------------------------------------------------------\n| GET\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'GET\') {\n\n    $requestedId =\n        (int)($_GET[\'contractor_id\'] ?? 0);\n\n    $contractorId =\n        resolve_availability_contractor(\n            $pdo,\n            $tid,\n            $user,\n            $role,\n            $managementRoles,\n            $requestedId\n        );\n\n    $q=$pdo->prepare("\n        SELECT\n            id,\n            contractor_id,\n            weekday,\n            available_date,\n            starts_at,\n            ends_at,\n            is_available\n        FROM contractor_availability\n        WHERE tenant_id=?\n          AND contractor_id=?\n        ORDER BY\n            CASE\n                WHEN available_date IS NULL\n                THEN 0 ELSE 1\n            END,\n            weekday,\n            available_date,\n            starts_at\n    ");\n\n    $q->execute([\n        $tid,\n        $contractorId\n    ]);\n\n    json_response([\n        \'contractor_id\'=>$contractorId,\n        \'availability\'=>$q->fetchAll()\n    ]);\n}\n\n\n/*\n|--------------------------------------------------------------------------\n| Mutations\n|--------------------------------------------------------------------------\n*/\n\nverify_csrf();\n\nif (\n    $role !== \'contractor\' &&\n    !in_array($role,$managementRoles,true)\n) {\n    json_response([\'error\'=>\'Forbidden\'],403);\n}\n\n$d=request_data();\n\n\n/*\n|--------------------------------------------------------------------------\n| POST\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {\n\n    try {\n\n        $requestedId =\n            (int)($d[\'contractor_id\'] ?? 0);\n\n        $contractorId =\n            resolve_availability_contractor(\n                $pdo,\n                $tid,\n                $user,\n                $role,\n                $managementRoles,\n                $requestedId\n            );\n\n        $weekday =\n            ($d[\'weekday\'] ?? \'\') === \'\'\n                ? null\n                : (int)$d[\'weekday\'];\n\n        $availableDate =\n            trim($d[\'available_date\'] ?? \'\')\n                ?: null;\n\n        if (\n            $weekday === null &&\n            $availableDate === null\n        ) {\n            throw new RuntimeException(\n                \'Choose a weekday or a specific date\'\n            );\n        }\n\n        if (\n            $weekday !== null &&\n            ($weekday < 0 || $weekday > 6)\n        ) {\n            throw new RuntimeException(\n                \'Invalid weekday\'\n            );\n        }\n\n        if ($availableDate !== null) {\n\n            $weekday=null;\n\n            $dt=DateTime::createFromFormat(\n                \'Y-m-d\',\n                $availableDate\n            );\n\n            if (\n                !$dt ||\n                $dt->format(\'Y-m-d\')\n                    !== $availableDate\n            ) {\n                throw new RuntimeException(\n                    \'Invalid date\'\n                );\n            }\n        }\n\n        $startsAt=\n            trim($d[\'starts_at\'] ?? \'\');\n\n        $endsAt=\n            trim($d[\'ends_at\'] ?? \'\');\n\n        if (\n            !preg_match(\n                \'/^\\d{2}:\\d{2}(:\\d{2})?$/\',\n                $startsAt\n            ) ||\n            !preg_match(\n                \'/^\\d{2}:\\d{2}(:\\d{2})?$/\',\n                $endsAt\n            )\n        ) {\n            throw new RuntimeException(\n                \'Invalid time\'\n            );\n        }\n\n        if (strlen($startsAt)===5) {\n            $startsAt .= \':00\';\n        }\n\n        if (strlen($endsAt)===5) {\n            $endsAt .= \':00\';\n        }\n\n        if ($endsAt <= $startsAt) {\n            throw new RuntimeException(\n                \'End time must be after start time\'\n            );\n        }\n\n        $isAvailable =\n            !empty($d[\'is_available\'])\n                ? 1 : 0;\n\n        $q=$pdo->prepare("\n            INSERT INTO contractor_availability(\n                tenant_id,\n                contractor_id,\n                weekday,\n                available_date,\n                starts_at,\n                ends_at,\n                is_available\n            )\n            VALUES(?,?,?,?,?,?,?)\n        ");\n\n        $q->execute([\n            $tid,\n            $contractorId,\n            $weekday,\n            $availableDate,\n            $startsAt,\n            $endsAt,\n            $isAvailable\n        ]);\n\n        json_response([\n            \'ok\'=>true,\n            \'id\'=>(int)$pdo->lastInsertId()\n        ],201);\n\n    } catch(Throwable $e) {\n\n        json_response([\n            \'error\'=>$e->getMessage()\n        ],422);\n    }\n}\n\n\n/*\n|--------------------------------------------------------------------------\n| DELETE\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'DELETE\') {\n\n    $id=(int)($d[\'id\'] ?? 0);\n\n    if ($id <= 0) {\n        json_response([\n            \'error\'=>\'Availability rule required\'\n        ],422);\n    }\n\n    /*\n     * Contractor deletion is restricted to rules\n     * belonging to their own contractor profile.\n     */\n\n    if ($role === \'contractor\') {\n\n        $contractorId =\n            resolve_availability_contractor(\n                $pdo,\n                $tid,\n                $user,\n                $role,\n                $managementRoles\n            );\n\n        $q=$pdo->prepare("\n            DELETE FROM contractor_availability\n            WHERE id=?\n              AND tenant_id=?\n              AND contractor_id=?\n        ");\n\n        $q->execute([\n            $id,\n            $tid,\n            $contractorId\n        ]);\n\n    } else {\n\n        $q=$pdo->prepare("\n            DELETE FROM contractor_availability\n            WHERE id=?\n              AND tenant_id=?\n        ");\n\n        $q->execute([\n            $id,\n            $tid\n        ]);\n    }\n\n    if (!$q->rowCount()) {\n        json_response([\n            \'error\'=>\'Availability rule not found\'\n        ],404);\n    }\n\n    json_response([\'ok\'=>true]);\n}\n\n\njson_response([\n    \'error\'=>\'Method not allowed\'\n],405);\n'

FILES['api/invoices.php'] = '<?php\n\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\n\n$u      = Auth::requireUser();\n$tenant = Auth::tenant();\n\n$tid  = (int)$tenant[\'tenant_id\'];\n$role = (string)$tenant[\'role\'];\n\n$pdo = Database::connection();\n\n\n/*\n|--------------------------------------------------------------------------\n| Resolve client identity for client portal\n|--------------------------------------------------------------------------\n*/\n\n$authenticatedClientId=null;\n\nif ($role === \'client\') {\n\n    $cq=$pdo->prepare("\n        SELECT id\n        FROM clients\n        WHERE tenant_id=?\n          AND user_id=?\n          AND status=\'active\'\n        LIMIT 1\n    ");\n\n    $cq->execute([\n        $tid,\n        (int)$u[\'id\']\n    ]);\n\n    $authenticatedClientId=\n        (int)($cq->fetchColumn() ?: 0);\n\n    if ($authenticatedClientId <= 0) {\n        json_response([\n            \'error\'=>\'Client profile not found\'\n        ],403);\n    }\n}\n\n\n/*\n|--------------------------------------------------------------------------\n| GET\n|--------------------------------------------------------------------------\n*/\n\n/*\n|--------------------------------------------------------------------------\n| GET — Single invoice detail\n|--------------------------------------------------------------------------\n*/\n\nif (\n    $_SERVER[\'REQUEST_METHOD\'] === \'GET\' &&\n    isset($_GET[\'id\'])\n) {\n\n    $invoiceId=(int)$_GET[\'id\'];\n\n    if ($invoiceId <= 0) {\n        json_response([\n            \'error\'=>\'Invalid invoice\'\n        ],422);\n    }\n\n    $sql="\n        SELECT\n            i.*,\n\n            CONCAT(\n                c.first_name,\n                \' \',\n                c.last_name\n            ) AS client_name,\n\n            c.company_name,\n            c.email AS client_email,\n            c.phone AS client_phone,\n            c.address1,\n            c.address2,\n            c.city,\n            c.state,\n            c.postal_code,\n\n            a.title AS appointment_title,\n            a.starts_at AS appointment_starts_at,\n            a.ends_at AS appointment_ends_at,\n\n            t.name AS tenant_name,\n            t.currency AS tenant_currency,\n\n            t.business_email AS tenant_email,\n            t.business_phone AS tenant_phone,\n            t.website AS tenant_website,\n\n            t.address1 AS tenant_address1,\n            t.address2 AS tenant_address2,\n            t.city AS tenant_city,\n            t.state AS tenant_state,\n            t.postal_code AS tenant_postal_code,\n\n            t.invoice_footer,\n            t.payment_instructions,\n\n            COALESCE((\n                SELECT SUM(p.amount)\n                FROM payments p\n                WHERE p.invoice_id=i.id\n                  AND p.tenant_id=i.tenant_id\n                  AND p.status=\'succeeded\'\n            ),0) AS amount_paid\n\n        FROM invoices i\n\n        JOIN clients c\n          ON c.id=i.client_id\n         AND c.tenant_id=i.tenant_id\n\n        JOIN tenants t\n          ON t.id=i.tenant_id\n\n        LEFT JOIN appointments a\n          ON a.id=i.appointment_id\n         AND a.tenant_id=i.tenant_id\n\n        WHERE i.id=?\n          AND i.tenant_id=?\n    ";\n\n    $invoiceParams=[\n        $invoiceId,\n        $tid\n    ];\n\n    /*\n     * Clients may only retrieve invoices\n     * belonging to their linked client record.\n     */\n    if ($role === \'client\') {\n\n        $sql .= "\n          AND i.client_id=?\n        ";\n\n        $invoiceParams[]=\n            $authenticatedClientId;\n    }\n\n    /*\n     * Contractors have no invoice access.\n     */\n    elseif ($role === \'contractor\') {\n\n        json_response([\n            \'error\'=>\'Forbidden\'\n        ],403);\n    }\n\n    elseif (\n        !in_array(\n            $role,\n            [\n                \'owner\',\n                \'admin\',\n                \'accounting\'\n            ],\n            true\n        )\n    ) {\n\n        json_response([\n            \'error\'=>\'Forbidden\'\n        ],403);\n    }\n\n    $sql .= " LIMIT 1";\n\n    $q=$pdo->prepare($sql);\n\n    $q->execute($invoiceParams);\n\n    $invoice=$q->fetch();\n\n    if (!$invoice) {\n        json_response([\n            \'error\'=>\'Invoice not found\'\n        ],404);\n    }\n\n    /*\n     * Overdue is derived for display.\n     * Paid, partial and void states remain authoritative.\n     */\n    if (\n        in_array(\n            $invoice[\'status\'],\n            [\'draft\',\'sent\',\'overdue\'],\n            true\n        ) &&\n        (float)$invoice[\'balance_due\'] > 0 &&\n        !empty($invoice[\'due_at\']) &&\n        strtotime($invoice[\'due_at\']) < time()\n    ) {\n        $invoice[\'status\']=\'overdue\';\n    }\n\n    $items=$pdo->prepare("\n        SELECT\n            id,\n            description,\n            quantity,\n            unit_price,\n            amount\n        FROM invoice_items\n        WHERE invoice_id=?\n        ORDER BY id\n    ");\n\n    $items->execute([\n        $invoiceId\n    ]);\n\n    $payments=$pdo->prepare("\n        SELECT\n            id,\n            amount,\n            currency,\n            status,\n            method,\n            provider,\n            provider_reference,\n            paid_at,\n            notes,\n            created_at\n        FROM payments\n        WHERE tenant_id=?\n          AND invoice_id=?\n        ORDER BY\n            COALESCE(paid_at,created_at) DESC,\n            id DESC\n    ");\n\n    $payments->execute([\n        $tid,\n        $invoiceId\n    ]);\n\n    json_response([\n        \'invoice\'=>$invoice,\n        \'items\'=>$items->fetchAll(),\n        \'payments\'=>$payments->fetchAll()\n    ]);\n}\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'GET\') {\n\n    /*\n     * Contractors do not have billing access.\n     */\n    if ($role === \'contractor\') {\n\n        json_response([\n            \'error\'=>\'Forbidden\'\n        ],403);\n    }\n\n\n    if (\n        $role !== \'client\' &&\n        !in_array(\n            $role,\n            [\n                \'owner\',\n                \'admin\',\n                \'accounting\'\n            ],\n            true\n        )\n    ) {\n\n        json_response([\n            \'error\'=>\'Forbidden\'\n        ],403);\n    }\n\n\n    $where="i.tenant_id=?";\n\n    $params=[$tid];\n\n\n    /*\n     * Client invoice list is locked to\n     * authenticated clients.id.\n     */\n    if ($role === \'client\') {\n\n        $where .= "\n            AND i.client_id=?\n        ";\n\n        $params[]=\n            $authenticatedClientId;\n    }\n\n\n    $q=$pdo->prepare("\n        SELECT\n            i.*,\n\n            CONCAT(\n                c.first_name,\n                \' \',\n                c.last_name\n            ) AS client_name,\n\n            a.title AS appointment_title,\n\n            a.starts_at\n                AS appointment_starts_at,\n\n            COALESCE((\n                SELECT SUM(p.amount)\n                FROM payments p\n                WHERE p.invoice_id=i.id\n                  AND p.tenant_id=i.tenant_id\n                  AND p.status=\'succeeded\'\n            ),0) AS amount_paid\n\n        FROM invoices i\n\n        JOIN clients c\n          ON c.id=i.client_id\n         AND c.tenant_id=i.tenant_id\n\n        LEFT JOIN appointments a\n          ON a.id=i.appointment_id\n         AND a.tenant_id=i.tenant_id\n\n        WHERE {$where}\n\n        ORDER BY i.created_at DESC\n    ");\n\n    $q->execute($params);\n\n    json_response([\n        \'invoices\'=>$q->fetchAll()\n    ]);\n}\n\n/*\n|--------------------------------------------------------------------------\n| Mutations\n|--------------------------------------------------------------------------\n*/\n\nverify_csrf();\n\nAuth::requireRole(\n    \'owner\',\n    \'admin\',\n    \'accounting\'\n);\n\n$d = request_data();\n\n/*\n|--------------------------------------------------------------------------\n| POST — Generate invoice from appointment\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {\n\n    $appointmentId =\n        (int)($d[\'appointment_id\'] ?? 0);\n\n    if ($appointmentId <= 0) {\n        json_response([\n            \'error\'=>\'Appointment required\'\n        ],422);\n    }\n\n    try {\n\n        /*\n         * Fetch everything needed from trusted database data.\n         * We do NOT trust the browser for service price,\n         * client ownership, or appointment status.\n         */\n        $q = $pdo->prepare("\n            SELECT\n                a.id,\n                a.client_id,\n                a.service_id,\n                a.title,\n                a.starts_at,\n                a.ends_at,\n                a.status,\n                s.name AS service_name,\n                s.price AS service_price\n            FROM appointments a\n\n            LEFT JOIN services s\n              ON s.id=a.service_id\n             AND s.tenant_id=a.tenant_id\n\n            WHERE a.id=?\n              AND a.tenant_id=?\n            LIMIT 1\n        ");\n\n        $q->execute([\n            $appointmentId,\n            $tid\n        ]);\n\n        $appointment=$q->fetch();\n\n        if (!$appointment) {\n            throw new RuntimeException(\n                \'Appointment not found\'\n            );\n        }\n\n        if (!$appointment[\'client_id\']) {\n            throw new RuntimeException(\n                \'Appointment must have a client before invoicing\'\n            );\n        }\n\n        if (!$appointment[\'service_id\']) {\n            throw new RuntimeException(\n                \'Appointment must have a service before invoicing\'\n            );\n        }\n\n        if ($appointment[\'status\'] !== \'completed\') {\n            throw new RuntimeException(\n                \'Only completed appointments can be invoiced\'\n            );\n        }\n\n        /*\n         * Application-level idempotency check.\n         * The UNIQUE database constraint provides the\n         * final concurrency-safe protection.\n         */\n        $existing=$pdo->prepare("\n            SELECT\n                id,\n                invoice_number\n            FROM invoices\n            WHERE tenant_id=?\n              AND appointment_id=?\n            LIMIT 1\n        ");\n\n        $existing->execute([\n            $tid,\n            $appointmentId\n        ]);\n\n        if ($row=$existing->fetch()) {\n            throw new RuntimeException(\n                \'Appointment already has invoice \' .\n                $row[\'invoice_number\']\n            );\n        }\n\n        $price=round(\n            (float)$appointment[\'service_price\'],\n            2\n        );\n\n        if ($price < 0) {\n            throw new RuntimeException(\n                \'Service price is invalid\'\n            );\n        }\n\n        /*\n         * Generate a tenant-scoped human-readable number.\n         * Timestamp + appointment ID makes accidental\n         * collisions extremely unlikely; the database\n         * unique constraint is authoritative.\n         */\n        $invoiceNumber =\n            \'INV-\' .\n            date(\'Ymd-His\') .\n            \'-\' .\n            $appointmentId;\n\n        $dueAt = !empty($d[\'due_at\'])\n            ? $d[\'due_at\']\n            : date(\n                \'Y-m-d H:i:s\',\n                strtotime(\'+14 days\')\n            );\n\n        $notes =\n            trim($d[\'notes\'] ?? \'\') ?: null;\n\n        $pdo->beginTransaction();\n\n        $insert=$pdo->prepare("\n            INSERT INTO invoices(\n                tenant_id,\n                client_id,\n                appointment_id,\n                invoice_number,\n                status,\n                subtotal,\n                tax_amount,\n                total,\n                balance_due,\n                due_at,\n                notes\n            )\n            VALUES(\n                ?,?,?,?,\n                \'draft\',\n                ?,\n                0,\n                ?,\n                ?,\n                ?,\n                ?\n            )\n        ");\n\n        $insert->execute([\n            $tid,\n            (int)$appointment[\'client_id\'],\n            $appointmentId,\n            $invoiceNumber,\n            $price,\n            $price,\n            $price,\n            $dueAt,\n            $notes\n        ]);\n\n        $invoiceId =\n            (int)$pdo->lastInsertId();\n\n        $description =\n            trim(\n                ($appointment[\'service_name\'] ?: $appointment[\'title\']) .\n                \' - \' .\n                date(\n                    \'M j, Y\',\n                    strtotime($appointment[\'starts_at\'])\n                )\n            );\n\n        $item=$pdo->prepare("\n            INSERT INTO invoice_items(\n                invoice_id,\n                description,\n                quantity,\n                unit_price,\n                amount\n            )\n            VALUES(?,?,?,?,?)\n        ");\n\n        $item->execute([\n            $invoiceId,\n            $description,\n            1,\n            $price,\n            $price\n        ]);\n\n        $pdo->commit();\n\n        json_response([\n            \'ok\'=>true,\n            \'id\'=>$invoiceId,\n            \'invoice_number\'=>$invoiceNumber,\n            \'total\'=>$price\n        ],201);\n\n    } catch(Throwable $e) {\n\n        if ($pdo->inTransaction()) {\n            $pdo->rollBack();\n        }\n\n        json_response([\n            \'error\'=>$e->getMessage()\n        ],422);\n    }\n}\n\n\n/*\n|--------------------------------------------------------------------------\n| PUT — Invoice lifecycle\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'PUT\') {\n\n    $invoiceId=(int)($d[\'id\'] ?? 0);\n\n    if ($invoiceId <= 0) {\n        json_response([\n            \'error\'=>\'Invoice required\'\n        ],422);\n    }\n\n    try {\n\n        $pdo->beginTransaction();\n\n        $q=$pdo->prepare("\n            SELECT\n                id,\n                status,\n                total,\n                balance_due,\n                due_at\n            FROM invoices\n            WHERE id=?\n              AND tenant_id=?\n            FOR UPDATE\n        ");\n\n        $q->execute([\n            $invoiceId,\n            $tid\n        ]);\n\n        $invoice=$q->fetch();\n\n        if (!$invoice) {\n            throw new RuntimeException(\n                \'Invoice not found\'\n            );\n        }\n\n        $newStatus=\n            $d[\'status\'] ?? $invoice[\'status\'];\n\n        /*\n         * paid and partial are controlled by payments.\n         * overdue is derived from due_at.\n         */\n        if (!in_array(\n            $newStatus,\n            [\'draft\',\'sent\',\'void\'],\n            true\n        )) {\n            throw new RuntimeException(\n                \'Invalid manual invoice status\'\n            );\n        }\n\n        if ($invoice[\'status\']===\'paid\') {\n            throw new RuntimeException(\n                \'Paid invoices cannot be manually changed\'\n            );\n        }\n\n        if (\n            $invoice[\'status\']===\'partial\' &&\n            $newStatus===\'draft\'\n        ) {\n            throw new RuntimeException(\n                \'Partially paid invoices cannot return to draft\'\n            );\n        }\n\n        if ($newStatus===\'void\') {\n\n            $p=$pdo->prepare("\n                SELECT COUNT(*)\n                FROM payments\n                WHERE tenant_id=?\n                  AND invoice_id=?\n                  AND status=\'succeeded\'\n            ");\n\n            $p->execute([\n                $tid,\n                $invoiceId\n            ]);\n\n            if ((int)$p->fetchColumn() > 0) {\n                throw new RuntimeException(\n                    \'Invoice with successful payments cannot be voided\'\n                );\n            }\n        }\n\n        $dueAt=$invoice[\'due_at\'];\n\n        if (array_key_exists(\'due_at\',$d)) {\n\n            if (!$d[\'due_at\']) {\n\n                $dueAt=null;\n\n            } else {\n\n                try {\n\n                    $dueAt=(new DateTime(\n                        $d[\'due_at\']\n                    ))->format(\n                        \'Y-m-d H:i:s\'\n                    );\n\n                } catch(Throwable $e) {\n\n                    throw new RuntimeException(\n                        \'Invalid due date\'\n                    );\n                }\n            }\n        }\n\n        if (array_key_exists(\'notes\',$d)) {\n\n            $notes=\n                trim((string)$d[\'notes\']) ?: null;\n\n            $u=$pdo->prepare("\n                UPDATE invoices\n                SET\n                    status=?,\n                    due_at=?,\n                    notes=?\n                WHERE id=?\n                  AND tenant_id=?\n            ");\n\n            $u->execute([\n                $newStatus,\n                $dueAt,\n                $notes,\n                $invoiceId,\n                $tid\n            ]);\n\n        } else {\n\n            $u=$pdo->prepare("\n                UPDATE invoices\n                SET\n                    status=?,\n                    due_at=?\n                WHERE id=?\n                  AND tenant_id=?\n            ");\n\n            $u->execute([\n                $newStatus,\n                $dueAt,\n                $invoiceId,\n                $tid\n            ]);\n        }\n\n        $pdo->commit();\n\n        json_response([\n            \'ok\'=>true,\n            \'id\'=>$invoiceId,\n            \'status\'=>$newStatus,\n            \'due_at\'=>$dueAt\n        ]);\n\n    } catch(Throwable $e) {\n\n        if ($pdo->inTransaction()) {\n            $pdo->rollBack();\n        }\n\n        json_response([\n            \'error\'=>$e->getMessage()\n        ],422);\n    }\n}\n\njson_response([\n    \'error\'=>\'Method not allowed\'\n],405);\n'

FILES['api/payments.php'] = '<?php\n\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\n\nAuth::requireUser();\n\n$tid = Auth::tenantId();\n$pdo = Database::connection();\n\n/*\n|--------------------------------------------------------------------------\n| Recalculate invoice financial state from payment ledger\n|--------------------------------------------------------------------------\n*/\n\nfunction recalculate_invoice(PDO $pdo, int $tenantId, int $invoiceId): array\n{\n    $q=$pdo->prepare("\n        SELECT\n            id,\n            total,\n            status\n        FROM invoices\n        WHERE id=?\n          AND tenant_id=?\n        FOR UPDATE\n    ");\n\n    $q->execute([\n        $invoiceId,\n        $tenantId\n    ]);\n\n    $invoice=$q->fetch();\n\n    if (!$invoice) {\n        throw new RuntimeException(\'Invoice not found\');\n    }\n\n    $p=$pdo->prepare("\n        SELECT COALESCE(SUM(amount),0)\n        FROM payments\n        WHERE tenant_id=?\n          AND invoice_id=?\n          AND status=\'succeeded\'\n    ");\n\n    $p->execute([\n        $tenantId,\n        $invoiceId\n    ]);\n\n    $paid=round((float)$p->fetchColumn(),2);\n    $total=round((float)$invoice[\'total\'],2);\n    $balance=max(0,round($total-$paid,2));\n\n    /*\n     * Preserve void invoices.\n     * Otherwise payment state determines paid/partial.\n     * An unpaid invoice remains draft/sent/overdue.\n     */\n    if ($invoice[\'status\'] === \'void\') {\n        $newStatus=\'void\';\n    } elseif ($balance <= 0) {\n        $newStatus=\'paid\';\n    } elseif ($paid > 0) {\n        $newStatus=\'partial\';\n    } else {\n        $newStatus=$invoice[\'status\'];\n    }\n\n    $u=$pdo->prepare("\n        UPDATE invoices\n        SET balance_due=?,\n            status=?\n        WHERE id=?\n          AND tenant_id=?\n    ");\n\n    $u->execute([\n        $balance,\n        $newStatus,\n        $invoiceId,\n        $tenantId\n    ]);\n\n    return [\n        \'total\'=>$total,\n        \'paid\'=>$paid,\n        \'balance_due\'=>$balance,\n        \'status\'=>$newStatus\n    ];\n}\n\n/*\n|--------------------------------------------------------------------------\n| GET\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\']===\'GET\') {\n\n    $q=$pdo->prepare("\n        SELECT\n            p.*,\n            CONCAT(c.first_name,\' \',c.last_name) AS client_name,\n            i.invoice_number\n        FROM payments p\n\n        JOIN clients c\n          ON c.id=p.client_id\n         AND c.tenant_id=p.tenant_id\n\n        LEFT JOIN invoices i\n          ON i.id=p.invoice_id\n         AND i.tenant_id=p.tenant_id\n\n        WHERE p.tenant_id=?\n\n        ORDER BY\n            COALESCE(p.paid_at,p.created_at) DESC,\n            p.id DESC\n    ");\n\n    $q->execute([$tid]);\n\n    json_response([\n        \'payments\'=>$q->fetchAll()\n    ]);\n}\n\n/*\n|--------------------------------------------------------------------------\n| Mutations\n|--------------------------------------------------------------------------\n*/\n\nverify_csrf();\n\nAuth::requireRole(\n    \'owner\',\n    \'admin\',\n    \'accounting\'\n);\n\n$d=request_data();\n\n/*\n|--------------------------------------------------------------------------\n| POST — Record invoice payment\n|--------------------------------------------------------------------------\n*/\n\nif ($_SERVER[\'REQUEST_METHOD\']===\'POST\') {\n\n    $invoiceId=(int)($d[\'invoice_id\'] ?? 0);\n\n    if ($invoiceId <= 0) {\n        json_response([\n            \'error\'=>\'Invoice required\'\n        ],422);\n    }\n\n    $amount=round(\n        (float)($d[\'amount\'] ?? 0),\n        2\n    );\n\n    if ($amount <= 0) {\n        json_response([\n            \'error\'=>\'Payment amount must be greater than zero\'\n        ],422);\n    }\n\n    $status=$d[\'status\'] ?? \'succeeded\';\n\n    $allowedStatuses=[\n        \'pending\',\n        \'succeeded\',\n        \'failed\',\n        \'refunded\',\n        \'void\'\n    ];\n\n    if (!in_array($status,$allowedStatuses,true)) {\n        json_response([\n            \'error\'=>\'Invalid payment status\'\n        ],422);\n    }\n\n    $currency=strtoupper(\n        trim($d[\'currency\'] ?? \'USD\')\n    );\n\n    if (!preg_match(\'/^[A-Z]{3}$/\',$currency)) {\n        json_response([\n            \'error\'=>\'Invalid currency\'\n        ],422);\n    }\n\n    try {\n\n        $pdo->beginTransaction();\n\n        /*\n         * Lock and validate invoice.\n         * Client comes from the invoice — never from browser input.\n         */\n        $q=$pdo->prepare("\n            SELECT\n                id,\n                client_id,\n                invoice_number,\n                status,\n                total,\n                balance_due\n            FROM invoices\n            WHERE id=?\n              AND tenant_id=?\n            FOR UPDATE\n        ");\n\n        $q->execute([\n            $invoiceId,\n            $tid\n        ]);\n\n        $invoice=$q->fetch();\n\n        if (!$invoice) {\n            throw new RuntimeException(\n                \'Invoice not found\'\n            );\n        }\n\n        if ($invoice[\'status\']===\'void\') {\n            throw new RuntimeException(\n                \'Cannot record payment against a void invoice\'\n            );\n        }\n\n        /*\n         * Determine authoritative balance from the ledger,\n         * not the potentially stale balance_due field.\n         */\n        $sum=$pdo->prepare("\n            SELECT COALESCE(SUM(amount),0)\n            FROM payments\n            WHERE tenant_id=?\n              AND invoice_id=?\n              AND status=\'succeeded\'\n        ");\n\n        $sum->execute([\n            $tid,\n            $invoiceId\n        ]);\n\n        $alreadyPaid=\n            round((float)$sum->fetchColumn(),2);\n\n        $total=\n            round((float)$invoice[\'total\'],2);\n\n        $currentBalance=\n            max(0,round($total-$alreadyPaid,2));\n\n        if ($status===\'succeeded\') {\n\n            if ($currentBalance <= 0) {\n                throw new RuntimeException(\n                    \'Invoice is already paid\'\n                );\n            }\n\n            if ($amount > $currentBalance) {\n                throw new RuntimeException(\n                    \'Payment exceeds remaining balance of $\' .\n                    number_format($currentBalance,2)\n                );\n            }\n        }\n\n        $method=\n            trim($d[\'method\'] ?? \'\') ?: null;\n\n        $provider=\n            trim($d[\'provider\'] ?? \'\') ?: null;\n\n        $providerReference=\n            trim($d[\'provider_reference\'] ?? \'\') ?: null;\n\n        $notes=\n            trim($d[\'notes\'] ?? \'\') ?: null;\n\n        $paidAt=\n            trim($d[\'paid_at\'] ?? \'\');\n\n        if ($paidAt===\'\') {\n            $paidAt=date(\'Y-m-d H:i:s\');\n        } else {\n            try {\n                $paidAt=(new DateTime($paidAt))\n                    ->format(\'Y-m-d H:i:s\');\n            } catch(Throwable $e) {\n                throw new RuntimeException(\n                    \'Invalid payment date\'\n                );\n            }\n        }\n\n        $insert=$pdo->prepare("\n            INSERT INTO payments(\n                tenant_id,\n                invoice_id,\n                client_id,\n                amount,\n                currency,\n                status,\n                method,\n                provider,\n                provider_reference,\n                paid_at,\n                notes\n            )\n            VALUES(?,?,?,?,?,?,?,?,?,?,?)\n        ");\n\n        $insert->execute([\n            $tid,\n            $invoiceId,\n            (int)$invoice[\'client_id\'],\n            $amount,\n            $currency,\n            $status,\n            $method,\n            $provider,\n            $providerReference,\n            $paidAt,\n            $notes\n        ]);\n\n        $paymentId=\n            (int)$pdo->lastInsertId();\n\n        $financials=\n            recalculate_invoice(\n                $pdo,\n                $tid,\n                $invoiceId\n            );\n\n        $pdo->commit();\n\n        json_response([\n            \'ok\'=>true,\n            \'id\'=>$paymentId,\n            \'invoice_id\'=>$invoiceId,\n            \'invoice_number\'=>$invoice[\'invoice_number\'],\n            \'amount\'=>$amount,\n            \'invoice\'=>$financials\n        ],201);\n\n    } catch(Throwable $e) {\n\n        if ($pdo->inTransaction()) {\n            $pdo->rollBack();\n        }\n\n        json_response([\n            \'error\'=>$e->getMessage()\n        ],422);\n    }\n}\n\njson_response([\n    \'error\'=>\'Method not allowed\'\n],405);\n'

FILES['api/dashboard.php'] = '<?php\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\nAuth::requireUser();Auth::requireRole(\'owner\',\'admin\',\'scheduler\',\'accounting\');$tid=Auth::tenantId();$pdo=Database::connection();\nfunction scalar(PDO $pdo,string $sql,array $p){$q=$pdo->prepare($sql);$q->execute($p);return $q->fetchColumn();}\njson_response([\n \'clients\'=>(int)scalar($pdo,"SELECT COUNT(*) FROM clients WHERE tenant_id=? AND status=\'active\'",[$tid]),\n \'contractors\'=>(int)scalar($pdo,"SELECT COUNT(*) FROM contractors WHERE tenant_id=? AND status=\'active\'",[$tid]),\n \'appointments_today\'=>(int)scalar($pdo,"SELECT COUNT(*) FROM appointments WHERE tenant_id=? AND DATE(starts_at)=CURDATE() AND status NOT IN(\'cancelled\',\'no_show\')",[$tid]),\n \'appointments_week\'=>(int)scalar($pdo,"SELECT COUNT(*) FROM appointments WHERE tenant_id=? AND starts_at>=CURDATE() AND starts_at<DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND status NOT IN(\'cancelled\',\'no_show\')",[$tid]),\n \'receivables\'=>(float)scalar($pdo,"SELECT COALESCE(SUM(balance_due),0) FROM invoices WHERE tenant_id=? AND status IN(\'sent\',\'partial\',\'overdue\')",[$tid]),\n \'payments_month\'=>(float)scalar($pdo,"SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id=? AND status=\'succeeded\' AND paid_at>=DATE_FORMAT(CURDATE(),\'%Y-%m-01\')",[$tid]),\n]);\n'

FILES['api/business_profile.php'] = '<?php\n\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\n\nAuth::requireUser();\n\n$tid = Auth::tenantId();\n$pdo = Database::connection();\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'GET\') {\n\n    $q=$pdo->prepare("\n        SELECT\n            id,\n            name,\n            slug,\n            timezone,\n            currency,\n            business_email,\n            business_phone,\n            website,\n            address1,\n            address2,\n            city,\n            state,\n            postal_code,\n            invoice_footer,\n            payment_instructions\n        FROM tenants\n        WHERE id=?\n        LIMIT 1\n    ");\n\n    $q->execute([$tid]);\n\n    $tenant=$q->fetch();\n\n    if (!$tenant) {\n        json_response([\n            \'error\'=>\'Tenant not found\'\n        ],404);\n    }\n\n    json_response([\n        \'business\'=>$tenant\n    ]);\n}\n\nverify_csrf();\n\nAuth::requireRole(\n    \'owner\',\n    \'admin\'\n);\n\n$d=request_data();\n\nif ($_SERVER[\'REQUEST_METHOD\'] === \'PUT\') {\n\n    $name=trim((string)($d[\'name\'] ?? \'\'));\n\n    if ($name===\'\') {\n        json_response([\n            \'error\'=>\'Business name is required\'\n        ],422);\n    }\n\n    $currency=strtoupper(\n        trim((string)($d[\'currency\'] ?? \'USD\'))\n    );\n\n    if (!preg_match(\'/^[A-Z]{3}$/\',$currency)) {\n        json_response([\n            \'error\'=>\'Currency must be a 3-letter code\'\n        ],422);\n    }\n\n    $timezone=trim(\n        (string)($d[\'timezone\'] ?? \'America/New_York\')\n    );\n\n    try {\n        new DateTimeZone($timezone);\n    } catch(Throwable $e) {\n        json_response([\n            \'error\'=>\'Invalid timezone\'\n        ],422);\n    }\n\n    $email=trim(\n        (string)($d[\'business_email\'] ?? \'\')\n    );\n\n    if (\n        $email !== \'\' &&\n        !filter_var($email,FILTER_VALIDATE_EMAIL)\n    ) {\n        json_response([\n            \'error\'=>\'Invalid business email\'\n        ],422);\n    }\n\n    $website=trim(\n        (string)($d[\'website\'] ?? \'\')\n    );\n\n    if (\n        $website !== \'\' &&\n        !preg_match(\n            \'~^https?://~i\',\n            $website\n        )\n    ) {\n        $website=\'https://\'.$website;\n    }\n\n    if (\n        $website !== \'\' &&\n        !filter_var($website,FILTER_VALIDATE_URL)\n    ) {\n        json_response([\n            \'error\'=>\'Invalid website\'\n        ],422);\n    }\n\n    $nullable=function($value) {\n        $value=trim((string)$value);\n        return $value===\'\' ? null : $value;\n    };\n\n    $q=$pdo->prepare("\n        UPDATE tenants\n        SET\n            name=?,\n            timezone=?,\n            currency=?,\n            business_email=?,\n            business_phone=?,\n            website=?,\n            address1=?,\n            address2=?,\n            city=?,\n            state=?,\n            postal_code=?,\n            invoice_footer=?,\n            payment_instructions=?\n        WHERE id=?\n    ");\n\n    $q->execute([\n        $name,\n        $timezone,\n        $currency,\n        $nullable($email),\n        $nullable($d[\'business_phone\'] ?? \'\'),\n        $nullable($website),\n        $nullable($d[\'address1\'] ?? \'\'),\n        $nullable($d[\'address2\'] ?? \'\'),\n        $nullable($d[\'city\'] ?? \'\'),\n        $nullable($d[\'state\'] ?? \'\'),\n        $nullable($d[\'postal_code\'] ?? \'\'),\n        $nullable($d[\'invoice_footer\'] ?? \'\'),\n        $nullable($d[\'payment_instructions\'] ?? \'\'),\n        $tid\n    ]);\n\n    json_response([\n        \'ok\'=>true\n    ]);\n}\n\njson_response([\n    \'error\'=>\'Method not allowed\'\n],405);\n'

FILES['partials/app_header.php'] = '<?php\nrequire_once dirname(__DIR__).\'/app/bootstrap.php\';\n$user=Auth::requireUser();$tenant=Auth::tenant();$csrf=csrf_token();\n?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">\n<title><?=htmlspecialchars($pageTitle??\'BriteScheduler\')?></title>\n<style>\nbody{font-family:system-ui,sans-serif;margin:0;background:#f5f7fb;color:#1f2937}.top{background:#172033;color:#fff;padding:14px 24px;display:flex;justify-content:space-between}.nav{background:#fff;padding:10px 24px;border-bottom:1px solid #ddd}.nav a{margin-right:18px;color:#334155;text-decoration:none}.wrap{max-width:1250px;margin:24px auto;padding:0 18px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;box-shadow:0 2px 8px #00000008}.metric{font-size:28px;font-weight:700}table{width:100%;border-collapse:collapse;background:#fff}th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}input,select,textarea,button{padding:9px;border:1px solid #cbd5e1;border-radius:6px}button{cursor:pointer}.row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}.row>*{flex:1;min-width:140px}.muted{color:#64748b}.danger{color:#b91c1c}\n</style>\n<script>\nwindow.BRITE_CSRF=<?=json_encode($csrf)?>;\nwindow.BRITE_BASE=<?=json_encode(rtrim($_ENV[\'APP_URL\'] ?? \'http://localhost/dashboard\',\'/\'))?>;\n\nasync function api(url,opt={}){\n    opt.headers={\n        ...(opt.headers||{}),\n        \'X-CSRF-Token\':window.BRITE_CSRF\n    };\n\n    if(url.startsWith(\'/\')){\n        url=window.BRITE_BASE+url;\n    }\n\n    let r=await fetch(url,opt);\n    let j=await r.json();\n\n    if(!r.ok)throw new Error(j.error||\'Request failed\');\n    return j;\n}\n\n\nasync function briteSignOut(){\n\n    try{\n\n        await api(\'/api/auth/logout.php\',{\n            method:\'POST\'\n        });\n\n        location.href =\n            window.BRITE_BASE +\n            \'/sign-in.v2.php\';\n\n    }catch(err){\n\n        alert(\n            err.message ||\n            \'Unable to sign out\'\n        );\n    }\n}\n\n</script>\n</head><body><div class="top"><b>BriteScheduler</b><span><?=htmlspecialchars($tenant[\'name\'])?> · <?=htmlspecialchars($user[\'first_name\'].\' \'.$user[\'last_name\'])?> (<?=htmlspecialchars($tenant[\'role\'])?>) &nbsp; <button type="button" onclick="briteSignOut()" style="padding:5px 10px;background:#fff;color:#172033;border:0;border-radius:5px;font-weight:600">Sign Out</button></span></div>\n<div class="nav">\n\n<?php if(in_array($tenant[\'role\'],[\'owner\',\'admin\',\'scheduler\',\'accounting\'],true)): ?>\n\n<a href="<?=htmlspecialchars(app_url(\'/dashboard.v2.php\'))?>">\nDashboard\n</a>\n\n<?php endif; ?>\n\n<?php if(in_array($tenant[\'role\'],[\'owner\',\'admin\',\'scheduler\',\'accounting\'],true)): ?>\n<a href="<?=htmlspecialchars(app_url(\'/clients.v2.php\'))?>">\nClients\n</a>\n<?php endif; ?>\n\n<?php if(in_array($tenant[\'role\'],[\'owner\',\'admin\',\'scheduler\'],true)): ?>\n<a href="<?=htmlspecialchars(app_url(\'/contractors.v2.php\'))?>">\nContractors\n</a>\n\n<a href="<?=htmlspecialchars(app_url(\'/contractor-availability.php\'))?>">\nAvailability\n</a>\n\n<a href="<?=htmlspecialchars(app_url(\'/services.v2.php\'))?>">\nServices\n</a>\n\n<a href="<?=htmlspecialchars(app_url(\'/scheduling.php\'))?>">\nSchedule\n</a>\n<?php endif; ?>\n\n<?php if(in_array($tenant[\'role\'],[\'owner\',\'admin\',\'accounting\'],true)): ?>\n<a href="<?=htmlspecialchars(app_url(\'/billing.php\'))?>">\nBilling\n</a>\n<?php endif; ?>\n\n<?php if(in_array($tenant[\'role\'],[\'owner\',\'admin\'],true)): ?>\n<a href="<?=htmlspecialchars(app_url(\'/settings.php\'))?>">\nSettings\n</a>\n<?php endif; ?>\n\n\n<?php if($tenant[\'role\']===\'contractor\'): ?>\n\n<a href="<?=htmlspecialchars(app_url(\'/contractor-dashboard.php\'))?>">\nMy Dashboard\n</a>\n\n<a href="<?=htmlspecialchars(app_url(\'/my-availability.php\'))?>">\nMy Availability\n</a>\n\n<?php endif; ?>\n\n\n<?php if($tenant[\'role\']===\'client\'): ?>\n\n<a href="<?=htmlspecialchars(app_url(\'/client-dashboard.php\'))?>">\nMy Dashboard\n</a>\n\n<?php endif; ?>\n\n</div><main class="wrap">\n'

FILES['partials/app_footer.php'] = '</main></body></html>'

FILES['dashboard.v2.php'] = '<?php\nrequire_once __DIR__.\'/app/bootstrap.php\';\nAuth::requireRole(\'owner\',\'admin\',\'scheduler\',\'accounting\');\n $pageTitle=\'Dashboard\';require __DIR__.\'/partials/app_header.php\';?>\n<h1>Operations Dashboard</h1><div id="metrics" class="grid"></div>\n<div class="card" style="margin-top:18px"><h2>System scope</h2><p class="muted">Tenant-isolated clients, contractors, scheduling, invoices and payments are active. Payment records are ledger entries; connect a PCI-compliant payment provider for card processing.</p></div>\n<script>\napi(\'/api/dashboard.php\').then(d=>{let m=[[\'Active clients\',d.clients],[\'Active contractors\',d.contractors],[\'Appointments today\',d.appointments_today],[\'Next 7 days\',d.appointments_week],[\'Receivables\',\'$\'+Number(d.receivables).toFixed(2)],[\'Payments this month\',\'$\'+Number(d.payments_month).toFixed(2)]];document.querySelector(\'#metrics\').innerHTML=m.map(x=>`<div class="card"><div class="muted">${x[0]}</div><div class="metric">${x[1]}</div></div>`).join(\'\')});\n</script><?php require __DIR__.\'/partials/app_footer.php\';?>\n'

FILES['clients.v2.php'] = '<?php\nrequire_once __DIR__.\'/app/bootstrap.php\';\nAuth::requireRole(\'owner\',\'admin\',\'scheduler\',\'accounting\');\n $pageTitle=\'Clients\';require __DIR__.\'/partials/app_header.php\';?>\n<h1>Clients</h1><div class="card"><form id="f"><div class="row"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="company_name" placeholder="Company"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"><button>Add client</button></div></form></div>\n<div class="card" style="margin-top:18px"><table><thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Status</th></tr></thead><tbody id="rows"></tbody></table></div>\n<script>\nasync function load(){let d=await api(\'/api/clients.php\');rows.innerHTML=d.clients.map(c=>`<tr><td>${esc(c.first_name)} ${esc(c.last_name)}</td><td>${esc(c.company_name||\'\')}</td><td>${esc(c.email||\'\')}</td><td>${esc(c.phone||\'\')}</td><td>${esc(c.status)}</td></tr>`).join(\'\')}\nfunction esc(s){return String(s).replace(/[&<>"\']/g,x=>({\'&\':\'&amp;\',\'<\':\'&lt;\',\'>\':\'&gt;\',\'"\':\'&quot;\',"\'":\'&#039;\'}[x]))}\nf.onsubmit=async e=>{e.preventDefault();let d=Object.fromEntries(new FormData(f));await api(\'/api/clients.php\',{method:\'POST\',headers:{\'Content-Type\':\'application/json\'},body:JSON.stringify(d)});f.reset();load()};load();\n</script><?php require __DIR__.\'/partials/app_footer.php\';?>\n'

FILES['contractors.v2.php'] = '<?php\nrequire_once __DIR__.\'/app/bootstrap.php\';\nAuth::requireRole(\'owner\',\'admin\',\'scheduler\');\n $pageTitle=\'Contractors\';require __DIR__.\'/partials/app_header.php\';?>\n<h1>Contractors</h1><div class="card"><form id="f"><div class="row"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="business_name" placeholder="Business"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"><input name="hourly_rate" type="number" step=".01" placeholder="Hourly rate"><button>Add contractor</button></div></form></div>\n<div class="card" style="margin-top:18px"><table><thead><tr><th>Name</th><th>Business</th><th>Email</th><th>Phone</th><th>Rate</th></tr></thead><tbody id="rows"></tbody></table></div>\n<script>\nfunction esc(s){return String(s??\'\').replace(/[&<>"\']/g,x=>({\'&\':\'&amp;\',\'<\':\'&lt;\',\'>\':\'&gt;\',\'"\':\'&quot;\',"\'":\'&#039;\'}[x]))}\nasync function load(){let d=await api(\'/api/contractors.php\');rows.innerHTML=d.contractors.map(c=>`<tr><td>${esc(c.first_name)} ${esc(c.last_name)}</td><td>${esc(c.business_name)}</td><td>${esc(c.email)}</td><td>${esc(c.phone)}</td><td>${c.hourly_rate?\'$\'+Number(c.hourly_rate).toFixed(2):\'\'}</td></tr>`).join(\'\')}\nf.onsubmit=async e=>{e.preventDefault();let d=Object.fromEntries(new FormData(f));await api(\'/api/contractors.php\',{method:\'POST\',headers:{\'Content-Type\':\'application/json\'},body:JSON.stringify(d)});f.reset();load()};load();\n</script><?php require __DIR__.\'/partials/app_footer.php\';?>\n'

FILES['services.v2.php'] = '<?php\nrequire_once __DIR__.\'/app/bootstrap.php\';\nAuth::requireRole(\'owner\',\'admin\',\'scheduler\');\n\n$pageTitle=\'Services\';\nrequire __DIR__.\'/partials/app_header.php\';\n?>\n\n<h1>Services</h1>\n\n<div class="card">\n    <h2>Add Service</h2>\n\n    <form id="serviceForm">\n        <div class="row">\n            <input name="name"\n                   placeholder="Service name"\n                   required>\n\n            <input name="duration_minutes"\n                   type="number"\n                   min="1"\n                   value="60"\n                   placeholder="Duration">\n\n            <input name="price"\n                   type="number"\n                   min="0"\n                   step=".01"\n                   value="0.00"\n                   placeholder="Price">\n\n            <select name="active">\n                <option value="1">Active</option>\n                <option value="">Inactive</option>\n            </select>\n\n            <button>Add Service</button>\n        </div>\n\n        <div class="row">\n            <textarea name="description"\n                      placeholder="Service description"></textarea>\n        </div>\n\n        <p id="serviceError" class="danger"></p>\n    </form>\n</div>\n\n<div class="card" style="margin-top:18px">\n    <table>\n        <thead>\n        <tr>\n            <th>Service</th>\n            <th>Duration</th>\n            <th>Price</th>\n            <th>Status</th>\n        </tr>\n        </thead>\n        <tbody id="serviceRows"></tbody>\n    </table>\n</div>\n\n<script>\nfunction esc(s){\n    return String(s ?? \'\').replace(/[&<>"\']/g,x=>({\n        \'&\':\'&amp;\',\n        \'<\':\'&lt;\',\n        \'>\':\'&gt;\',\n        \'"\':\'&quot;\',\n        "\'":\'&#039;\'\n    }[x]));\n}\n\nasync function loadServices(){\n    try {\n        const d=await api(\'/api/services.php\');\n\n        serviceRows.innerHTML=d.services.map(s=>`\n            <tr>\n                <td>\n                    <strong>${esc(s.name)}</strong>\n                    <div class="muted">${esc(s.description)}</div>\n                </td>\n                <td>${Number(s.duration_minutes)} min</td>\n                <td>$${Number(s.price).toFixed(2)}</td>\n                <td>${Number(s.active) ? \'Active\' : \'Inactive\'}</td>\n            </tr>\n        `).join(\'\');\n    } catch(e){\n        serviceError.textContent=e.message;\n    }\n}\n\nserviceForm.onsubmit=async e=>{\n    e.preventDefault();\n    serviceError.textContent=\'\';\n\n    try {\n        const d=Object.fromEntries(new FormData(serviceForm));\n\n        await api(\'/api/services.php\',{\n            method:\'POST\',\n            headers:{\'Content-Type\':\'application/json\'},\n            body:JSON.stringify(d)\n        });\n\n        serviceForm.reset();\n        serviceForm.duration_minutes.value=60;\n        serviceForm.price.value=\'0.00\';\n        serviceForm.active.value=\'1\';\n\n        await loadServices();\n\n    } catch(e){\n        serviceError.textContent=e.message;\n    }\n};\n\nloadServices();\n</script>\n\n<?php require __DIR__.\'/partials/app_footer.php\'; ?>\n'

FILES['contractor-availability.php'] = '<?php\nrequire_once __DIR__.\'/app/bootstrap.php\';\nAuth::requireRole(\'owner\',\'admin\',\'scheduler\');\n\n$pageTitle=\'Contractor Availability\';\nrequire __DIR__.\'/partials/app_header.php\';\n?>\n\n<style>\n.availability-layout{\n    display:grid;\n    grid-template-columns:340px minmax(0,1fr);\n    gap:18px;\n}\n.availability-form label{\n    display:block;\n    font-weight:600;\n    font-size:13px;\n    margin-top:10px;\n}\n.availability-form input,\n.availability-form select{\n    width:100%;\n    box-sizing:border-box;\n    margin-top:4px;\n}\n.rule-available{\n    font-weight:600;\n}\n.rule-unavailable{\n    font-weight:600;\n}\n@media(max-width:900px){\n    .availability-layout{\n        grid-template-columns:1fr;\n    }\n}\n</style>\n\n<h1>Contractor Availability</h1>\n\n<div class="availability-layout">\n\n<div class="card">\n\n    <h2>Add Availability Rule</h2>\n\n    <form id="availabilityForm"\n          class="availability-form">\n\n        <label>Contractor</label>\n        <select id="contractorId"\n                name="contractor_id"\n                required>\n            <option value="">Select contractor</option>\n        </select>\n\n        <label>Rule Type</label>\n        <select id="ruleType">\n            <option value="weekly">\n                Weekly recurring hours\n            </option>\n            <option value="date">\n                Specific date\n            </option>\n        </select>\n\n        <div id="weekdayContainer">\n            <label>Weekday</label>\n            <select id="weekday"\n                    name="weekday">\n                <option value="0">Sunday</option>\n                <option value="1">Monday</option>\n                <option value="2">Tuesday</option>\n                <option value="3">Wednesday</option>\n                <option value="4">Thursday</option>\n                <option value="5">Friday</option>\n                <option value="6">Saturday</option>\n            </select>\n        </div>\n\n        <div id="dateContainer"\n             style="display:none">\n            <label>Specific Date</label>\n            <input id="availableDate"\n                   type="date"\n                   name="available_date">\n        </div>\n\n        <label>Start</label>\n        <input id="startsAt"\n               name="starts_at"\n               type="time"\n               value="08:00"\n               required>\n\n        <label>End</label>\n        <input id="endsAt"\n               name="ends_at"\n               type="time"\n               value="17:00"\n               required>\n\n        <label>Status</label>\n        <select id="isAvailable"\n                name="is_available">\n            <option value="1">Available</option>\n            <option value="">\n                Unavailable / Time Off\n            </option>\n        </select>\n\n        <div style="margin-top:15px">\n            <button type="submit">\n                Add Rule\n            </button>\n        </div>\n\n        <p id="availabilityError"\n           class="danger"></p>\n\n    </form>\n\n</div>\n\n<div class="card">\n\n    <h2>Availability Rules</h2>\n\n    <p class="muted">\n        Specific-date rules will later override\n        recurring weekly hours.\n    </p>\n\n    <table>\n        <thead>\n        <tr>\n            <th>When</th>\n            <th>Hours</th>\n            <th>Status</th>\n            <th></th>\n        </tr>\n        </thead>\n\n        <tbody id="availabilityRows">\n        </tbody>\n    </table>\n\n</div>\n\n</div>\n\n<script>\n\nconst contractorId =\n    document.getElementById(\'contractorId\');\n\nconst availabilityForm =\n    document.getElementById(\'availabilityForm\');\n\nconst ruleType =\n    document.getElementById(\'ruleType\');\n\nconst weekday =\n    document.getElementById(\'weekday\');\n\nconst availableDate =\n    document.getElementById(\'availableDate\');\n\nconst weekdayContainer =\n    document.getElementById(\'weekdayContainer\');\n\nconst dateContainer =\n    document.getElementById(\'dateContainer\');\n\nconst startsAt =\n    document.getElementById(\'startsAt\');\n\nconst endsAt =\n    document.getElementById(\'endsAt\');\n\nconst isAvailable =\n    document.getElementById(\'isAvailable\');\n\nconst availabilityRows =\n    document.getElementById(\'availabilityRows\');\n\nconst availabilityError =\n    document.getElementById(\'availabilityError\');\n\nconst weekdays=[\n    \'Sunday\',\n    \'Monday\',\n    \'Tuesday\',\n    \'Wednesday\',\n    \'Thursday\',\n    \'Friday\',\n    \'Saturday\'\n];\n\nfunction esc(s){\n    return String(s ?? \'\').replace(\n        /[&<>"\']/g,\n        x=>({\n            \'&\':\'&amp;\',\n            \'<\':\'&lt;\',\n            \'>\':\'&gt;\',\n            \'"\':\'&quot;\',\n            "\'":\'&#039;\'\n        }[x])\n    );\n}\n\nfunction shortTime(value){\n\n    if(!value) return \'\';\n\n    const parts=value.split(\':\');\n\n    let hour=Number(parts[0]);\n    const minute=parts[1];\n\n    const suffix=hour >= 12 ? \'PM\' : \'AM\';\n\n    hour=hour % 12;\n\n    if(hour===0) hour=12;\n\n    return `${hour}:${minute} ${suffix}`;\n}\n\nasync function loadContractors(){\n\n    const d=await api(\'/api/contractors.php\');\n\n    contractorId.innerHTML=\n        \'<option value="">Select contractor</option>\'+\n        d.contractors.map(c=>\n            `<option value="${c.id}">\n                ${esc(c.first_name)} ${esc(c.last_name)}\n            </option>`\n        ).join(\'\');\n}\n\nasync function loadAvailability(){\n\n    availabilityRows.innerHTML=\'\';\n\n    if(!contractorId.value){\n        return;\n    }\n\n    const d=await api(\n        \'/api/contractor_availability.php?contractor_id=\'+\n        encodeURIComponent(contractorId.value)\n    );\n\n    if(!d.availability.length){\n\n        availabilityRows.innerHTML=`\n            <tr>\n                <td colspan="4"\n                    class="muted">\n                    No availability rules configured.\n                </td>\n            </tr>\n        `;\n\n        return;\n    }\n\n    availabilityRows.innerHTML=\n        d.availability.map(r=>{\n\n            const when=r.available_date\n                ? esc(r.available_date)\n                : weekdays[Number(r.weekday)];\n\n            const status=Number(r.is_available)\n                ? \'Available\'\n                : \'Unavailable\';\n\n            const css=Number(r.is_available)\n                ? \'rule-available\'\n                : \'rule-unavailable\';\n\n            return `\n                <tr>\n                    <td>${when}</td>\n\n                    <td>\n                        ${shortTime(r.starts_at)}\n                        –\n                        ${shortTime(r.ends_at)}\n                    </td>\n\n                    <td class="${css}">\n                        ${status}\n                    </td>\n\n                    <td>\n                        <button\n                            type="button"\n                            onclick="deleteRule(${Number(r.id)})">\n                            Delete\n                        </button>\n                    </td>\n                </tr>\n            `;\n        }).join(\'\');\n}\n\nruleType.onchange=()=>{\n\n    const specific=\n        ruleType.value===\'date\';\n\n    weekdayContainer.style.display=\n        specific ? \'none\' : \'\';\n\n    dateContainer.style.display=\n        specific ? \'\' : \'none\';\n\n    weekday.disabled=specific;\n    availableDate.disabled=!specific;\n\n    if(specific){\n        weekday.value=\'1\';\n    }else{\n        availableDate.value=\'\';\n    }\n};\n\ncontractorId.onchange=loadAvailability;\n\navailabilityForm.onsubmit=async e=>{\n\n    e.preventDefault();\n\n    availabilityError.textContent=\'\';\n\n    try{\n\n        if(!contractorId.value){\n            throw new Error(\n                \'Select a contractor\'\n            );\n        }\n\n        const specific=\n            ruleType.value===\'date\';\n\n        if(specific && !availableDate.value){\n            throw new Error(\n                \'Choose a specific date\'\n            );\n        }\n\n        const payload={\n            contractor_id:\n                Number(contractorId.value),\n\n            weekday:\n                specific\n                    ? null\n                    : Number(weekday.value),\n\n            available_date:\n                specific\n                    ? availableDate.value\n                    : null,\n\n            starts_at:\n                startsAt.value,\n\n            ends_at:\n                endsAt.value,\n\n            is_available:\n                isAvailable.value===\'1\'\n        };\n\n        await api(\n            \'/api/contractor_availability.php\',\n            {\n                method:\'POST\',\n                headers:{\n                    \'Content-Type\':\n                        \'application/json\'\n                },\n                body:JSON.stringify(payload)\n            }\n        );\n\n        await loadAvailability();\n\n    }catch(e){\n\n        availabilityError.textContent=\n            e.message;\n    }\n};\n\nasync function deleteRule(id){\n\n    if(!confirm(\n        \'Delete this availability rule?\'\n    )){\n        return;\n    }\n\n    try{\n\n        await api(\n            \'/api/contractor_availability.php\',\n            {\n                method:\'DELETE\',\n                headers:{\n                    \'Content-Type\':\n                        \'application/json\'\n                },\n                body:JSON.stringify({id})\n            }\n        );\n\n        await loadAvailability();\n\n    }catch(e){\n\n        availabilityError.textContent=\n            e.message;\n    }\n}\n\ndocument.addEventListener(\n    \'DOMContentLoaded\',\n    async ()=>{\n\n        try{\n\n            ruleType.dispatchEvent(\n                new Event(\'change\')\n            );\n\n            await loadContractors();\n\n        }catch(e){\n\n            availabilityError.textContent=\n                e.message;\n        }\n    }\n);\n\n</script>\n\n<?php require __DIR__.\'/partials/app_footer.php\'; ?>\n'

FILES['my-availability.php'] = '<?php\n\nrequire_once __DIR__.\'/app/bootstrap.php\';\n\nAuth::requireRole(\'contractor\');\n\n$pageTitle=\'My Availability\';\n\nrequire __DIR__.\'/partials/app_header.php\';\n\n?>\n\n<style>\n\n.availability-layout{\n    display:grid;\n    grid-template-columns:340px minmax(0,1fr);\n    gap:18px;\n}\n\n.availability-form label{\n    display:block;\n    font-weight:600;\n    font-size:13px;\n    margin-top:10px;\n}\n\n.availability-form input,\n.availability-form select{\n    width:100%;\n    box-sizing:border-box;\n    margin-top:4px;\n}\n\n.rule-available{\n    font-weight:600;\n}\n\n.rule-unavailable{\n    font-weight:600;\n}\n\n@media(max-width:900px){\n    .availability-layout{\n        grid-template-columns:1fr;\n    }\n}\n\n</style>\n\n\n<h1>My Availability</h1>\n\n<p class="muted">\nSet your normal weekly working hours and add\nspecific dates when you are available or unavailable.\n</p>\n\n\n<div class="availability-layout">\n\n<div class="card">\n\n<h2>Add Availability Rule</h2>\n\n<form id="availabilityForm"\n      class="availability-form">\n\n<label>Rule Type</label>\n\n<select id="ruleType">\n\n<option value="weekly">\nWeekly recurring hours\n</option>\n\n<option value="date">\nSpecific date\n</option>\n\n</select>\n\n\n<div id="weekdayContainer">\n\n<label>Weekday</label>\n\n<select id="weekday">\n\n<option value="0">Sunday</option>\n<option value="1">Monday</option>\n<option value="2">Tuesday</option>\n<option value="3">Wednesday</option>\n<option value="4">Thursday</option>\n<option value="5">Friday</option>\n<option value="6">Saturday</option>\n\n</select>\n\n</div>\n\n\n<div id="dateContainer"\n     style="display:none">\n\n<label>Specific Date</label>\n\n<input id="availableDate"\n       type="date">\n\n</div>\n\n\n<label>Start</label>\n\n<input id="startsAt"\n       type="time"\n       value="08:00"\n       required>\n\n\n<label>End</label>\n\n<input id="endsAt"\n       type="time"\n       value="17:00"\n       required>\n\n\n<label>Status</label>\n\n<select id="isAvailable">\n\n<option value="1">\nAvailable\n</option>\n\n<option value="0">\nUnavailable / Time Off\n</option>\n\n</select>\n\n\n<div style="margin-top:15px">\n\n<button type="submit">\nAdd Rule\n</button>\n\n</div>\n\n<p id="availabilityError"\n   class="danger"></p>\n\n</form>\n\n</div>\n\n\n<div class="card">\n\n<h2>My Availability Rules</h2>\n\n<p class="muted">\nSpecific-date rules override your normal\nrecurring schedule for that date.\n</p>\n\n<table>\n\n<thead>\n<tr>\n<th>When</th>\n<th>Hours</th>\n<th>Status</th>\n<th></th>\n</tr>\n</thead>\n\n<tbody id="availabilityRows">\n</tbody>\n\n</table>\n\n</div>\n\n</div>\n\n\n<script>\n\nconst availabilityForm =\n    document.getElementById(\'availabilityForm\');\n\nconst ruleType =\n    document.getElementById(\'ruleType\');\n\nconst weekday =\n    document.getElementById(\'weekday\');\n\nconst availableDate =\n    document.getElementById(\'availableDate\');\n\nconst weekdayContainer =\n    document.getElementById(\n        \'weekdayContainer\'\n    );\n\nconst dateContainer =\n    document.getElementById(\n        \'dateContainer\'\n    );\n\nconst startsAt =\n    document.getElementById(\'startsAt\');\n\nconst endsAt =\n    document.getElementById(\'endsAt\');\n\nconst isAvailable =\n    document.getElementById(\'isAvailable\');\n\nconst availabilityRows =\n    document.getElementById(\n        \'availabilityRows\'\n    );\n\nconst availabilityError =\n    document.getElementById(\n        \'availabilityError\'\n    );\n\nconst weekdays=[\n    \'Sunday\',\n    \'Monday\',\n    \'Tuesday\',\n    \'Wednesday\',\n    \'Thursday\',\n    \'Friday\',\n    \'Saturday\'\n];\n\n\nfunction esc(s){\n\n    return String(s ?? \'\').replace(\n        /[&<>"\']/g,\n        x=>({\n            \'&\':\'&amp;\',\n            \'<\':\'&lt;\',\n            \'>\':\'&gt;\',\n            \'"\':\'&quot;\',\n            "\'":\'&#039;\'\n        }[x])\n    );\n}\n\n\nfunction shortTime(value){\n\n    if(!value) return \'\';\n\n    const parts=value.split(\':\');\n\n    let hour=Number(parts[0]);\n    const minute=parts[1];\n\n    const suffix=\n        hour >= 12 ? \'PM\' : \'AM\';\n\n    hour=hour % 12;\n\n    if(hour===0) hour=12;\n\n    return `${hour}:${minute} ${suffix}`;\n}\n\n\nasync function loadAvailability(){\n\n    availabilityRows.innerHTML=\'\';\n\n    /*\n     * No contractor_id is sent.\n     *\n     * The server derives the contractor from\n     * the authenticated user\'s account.\n     */\n\n    const d=await api(\n        \'/api/contractor_availability.php\'\n    );\n\n    if(!d.availability.length){\n\n        availabilityRows.innerHTML=`\n            <tr>\n                <td colspan="4"\n                    class="muted">\n                    No availability rules configured.\n                </td>\n            </tr>\n        `;\n\n        return;\n    }\n\n    availabilityRows.innerHTML=\n        d.availability.map(r=>{\n\n            const when=r.available_date\n                ? esc(r.available_date)\n                : weekdays[\n                    Number(r.weekday)\n                ];\n\n            const status=\n                Number(r.is_available)\n                    ? \'Available\'\n                    : \'Unavailable\';\n\n            const css=\n                Number(r.is_available)\n                    ? \'rule-available\'\n                    : \'rule-unavailable\';\n\n            return `\n                <tr>\n\n                    <td>\n                        ${when}\n                    </td>\n\n                    <td>\n                        ${shortTime(r.starts_at)}\n                        –\n                        ${shortTime(r.ends_at)}\n                    </td>\n\n                    <td class="${css}">\n                        ${status}\n                    </td>\n\n                    <td>\n                        <button\n                            type="button"\n                            onclick="deleteRule(\n                                ${Number(r.id)}\n                            )">\n                            Delete\n                        </button>\n                    </td>\n\n                </tr>\n            `;\n        }).join(\'\');\n}\n\n\nruleType.onchange=()=>{\n\n    const specific=\n        ruleType.value===\'date\';\n\n    weekdayContainer.style.display=\n        specific ? \'none\' : \'\';\n\n    dateContainer.style.display=\n        specific ? \'\' : \'none\';\n\n    weekday.disabled=specific;\n\n    availableDate.disabled=\n        !specific;\n\n    if(specific){\n        weekday.value=\'1\';\n    }else{\n        availableDate.value=\'\';\n    }\n};\n\n\navailabilityForm.onsubmit=async e=>{\n\n    e.preventDefault();\n\n    availabilityError.textContent=\'\';\n\n    try{\n\n        const specific=\n            ruleType.value===\'date\';\n\n        if(\n            specific &&\n            !availableDate.value\n        ){\n            throw new Error(\n                \'Choose a specific date\'\n            );\n        }\n\n        /*\n         * contractor_id intentionally omitted.\n         */\n\n        const payload={\n\n            weekday:\n                specific\n                    ? null\n                    : Number(\n                        weekday.value\n                    ),\n\n            available_date:\n                specific\n                    ? availableDate.value\n                    : null,\n\n            starts_at:\n                startsAt.value,\n\n            ends_at:\n                endsAt.value,\n\n            is_available:\n                isAvailable.value===\'1\'\n        };\n\n        await api(\n            \'/api/contractor_availability.php\',\n            {\n                method:\'POST\',\n\n                headers:{\n                    \'Content-Type\':\n                        \'application/json\'\n                },\n\n                body:\n                    JSON.stringify(payload)\n            }\n        );\n\n        await loadAvailability();\n\n    }catch(e){\n\n        availabilityError.textContent=\n            e.message;\n    }\n};\n\n\nasync function deleteRule(id){\n\n    if(!confirm(\n        \'Delete this availability rule?\'\n    )){\n        return;\n    }\n\n    availabilityError.textContent=\'\';\n\n    try{\n\n        await api(\n            \'/api/contractor_availability.php\',\n            {\n                method:\'DELETE\',\n\n                headers:{\n                    \'Content-Type\':\n                        \'application/json\'\n                },\n\n                body:\n                    JSON.stringify({\n                        id:Number(id)\n                    })\n            }\n        );\n\n        await loadAvailability();\n\n    }catch(e){\n\n        availabilityError.textContent=\n            e.message;\n    }\n}\n\n\ndocument.addEventListener(\n    \'DOMContentLoaded\',\n    async ()=>{\n\n        try{\n\n            ruleType.dispatchEvent(\n                new Event(\'change\')\n            );\n\n            await loadAvailability();\n\n        }catch(e){\n\n            availabilityError.textContent=\n                e.message;\n        }\n    }\n);\n\n</script>\n\n<?php\nrequire __DIR__.\'/partials/app_footer.php\';\n?>\n'

FILES['scheduling.php'] = '<?php\nrequire_once __DIR__.\'/app/bootstrap.php\';\nAuth::requireRole(\'owner\',\'admin\',\'scheduler\');\n\n$pageTitle=\'Schedule\';\nrequire __DIR__.\'/partials/app_header.php\';\n?>\n\n<script src="<?=htmlspecialchars(app_url(\'/events/dist/index.global.min.js\'))?>"></script>\n\n<style>\n.scheduler-layout{\n    display:grid;\n    grid-template-columns:320px minmax(0,1fr);\n    gap:18px;\n}\n.scheduler-form label{\n    display:block;\n    font-size:13px;\n    font-weight:600;\n    margin-top:10px;\n}\n.scheduler-form input,\n.scheduler-form select,\n.scheduler-form textarea{\n    width:100%;\n    box-sizing:border-box;\n    margin-top:4px;\n}\n.scheduler-form textarea{\n    min-height:70px;\n}\n#calendar{\n    min-height:700px;\n}\n@media(max-width:900px){\n    .scheduler-layout{\n        grid-template-columns:1fr;\n    }\n}\n</style>\n\n<h1>Schedule</h1>\n\n<div class="scheduler-layout">\n\n<div class="card">\n    <h2 id="formTitle">New Appointment</h2>\n\n    <form id="appointmentForm" class="scheduler-form">\n\n        <input type="hidden" name="id" id="appointmentId">\n\n        <label>Client</label>\n        <select name="client_id" id="clientId">\n            <option value="">No client</option>\n        </select>\n\n        <label>Service</label>\n        <select name="service_id" id="serviceId">\n            <option value="">No service</option>\n        </select>\n\n        <label>Contractor</label>\n        <select id="contractorId">\n            <option value="">Unassigned</option>\n        </select>\n\n        <label>Title</label>\n        <input name="title"\n               id="appointmentTitle"\n               required\n               placeholder="Appointment">\n\n        <label>Start</label>\n        <input name="starts_at"\n               id="startsAt"\n               type="datetime-local"\n               required>\n\n        <label>End</label>\n        <input name="ends_at"\n               id="endsAt"\n               type="datetime-local"\n               required>\n\n        <label>Status</label>\n        <select name="status" id="appointmentStatus">\n            <option value="tentative">Tentative</option>\n            <option value="scheduled" selected>Scheduled</option>\n            <option value="confirmed">Confirmed</option>\n            <option value="in_progress">In Progress</option>\n            <option value="completed">Completed</option>\n            <option value="cancelled">Cancelled</option>\n            <option value="no_show">No Show</option>\n        </select>\n\n        <label>Location</label>\n        <input name="location"\n               id="appointmentLocation"\n               placeholder="Location">\n\n        <label>Description</label>\n        <textarea name="description"\n                  id="appointmentDescription"\n                  placeholder="Notes"></textarea>\n\n        <div style="margin-top:15px">\n            <button type="submit" id="saveButton">Create Appointment</button>\n            <button type="button" id="newButton">Clear</button>\n            <button type="button" id="deleteButton"\n                    style="display:none">Delete</button>\n<button type="button"\n        id="invoiceButton"\n        style="display:none">\n    Generate Invoice\n</button>\n        </div>\n\n        <p id="appointmentError" class="danger"></p>\n    </form>\n</div>\n\n<div class="card">\n    <div id="calendar"></div>\n</div>\n\n</div>\n\n<script>\nlet calendar;\nlet services=[];\n\nconst appointmentForm = document.getElementById(\'appointmentForm\');\nconst appointmentId = document.getElementById(\'appointmentId\');\nconst invoiceButton = document.getElementById(\'invoiceButton\');\nconst clientId = document.getElementById(\'clientId\');\nconst serviceId = document.getElementById(\'serviceId\');\nconst contractorId = document.getElementById(\'contractorId\');\nconst appointmentTitle = document.getElementById(\'appointmentTitle\');\nconst startsAt = document.getElementById(\'startsAt\');\nconst endsAt = document.getElementById(\'endsAt\');\nconst appointmentStatus = document.getElementById(\'appointmentStatus\');\nconst appointmentLocation = document.getElementById(\'appointmentLocation\');\nconst appointmentDescription = document.getElementById(\'appointmentDescription\');\nconst appointmentError = document.getElementById(\'appointmentError\');\nconst formTitle = document.getElementById(\'formTitle\');\nconst saveButton = document.getElementById(\'saveButton\');\nconst newButton = document.getElementById(\'newButton\');\nconst deleteButton = document.getElementById(\'deleteButton\');\n\nfunction esc(s){\n    return String(s ?? \'\').replace(/[&<>"\']/g,x=>({\n        \'&\':\'&amp;\',\n        \'<\':\'&lt;\',\n        \'>\':\'&gt;\',\n        \'"\':\'&quot;\',\n        "\'":\'&#039;\'\n    }[x]));\n}\n\nfunction localDateTime(value){\n    if(!value) return \'\';\n\n    const d=new Date(value);\n\n    const pad=n=>String(n).padStart(2,\'0\');\n\n    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;\n}\n\nfunction sqlDateTime(value){\n    if(!value) return null;\n    return value.replace(\'T\',\' \') + \':00\';\n}\n\nasync function loadLookups(){\n\n    const [clientsData,contractorsData,servicesData]=await Promise.all([\n        api(\'/api/clients.php\'),\n        api(\'/api/contractors.php\'),\n        api(\'/api/services.php\')\n    ]);\n\n    clientId.innerHTML=\n        \'<option value="">No client</option>\'+\n        clientsData.clients.map(c=>\n            `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name)}</option>`\n        ).join(\'\');\n\n    contractorId.innerHTML=\n        \'<option value="">Unassigned</option>\'+\n        contractorsData.contractors.map(c=>\n            `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name)}</option>`\n        ).join(\'\');\n\n    services=servicesData.services.filter(s=>Number(s.active));\n\n    serviceId.innerHTML=\n        \'<option value="">No service</option>\'+\n        services.map(s=>\n            `<option value="${s.id}">${esc(s.name)} — ${Number(s.duration_minutes)} min</option>`\n        ).join(\'\');\n}\n\nfunction resetForm(){\n    invoiceButton.style.display = \'none\';\n\n\n    appointmentForm.reset();\n\n    appointmentId.value=\'\';\n    appointmentStatus.value=\'scheduled\';\n\n    formTitle.textContent=\'New Appointment\';\n    saveButton.textContent=\'Create Appointment\';\n    deleteButton.style.display=\'none\';\n    appointmentError.textContent=\'\';\n}\n\nserviceId.onchange=()=>{\n\n    const service=services.find(\n        s=>String(s.id)===String(serviceId.value)\n    );\n\n    if(!service || !startsAt.value) return;\n\n    const start=new Date(startsAt.value);\n    start.setMinutes(start.getMinutes()+Number(service.duration_minutes));\n\n    endsAt.value=localDateTime(start);\n};\n\nasync function loadEvents(info,success,failure){\n\n    try {\n\n        const d=await api(\n            \'/api/appointments.php?start=\'+\n            encodeURIComponent(info.startStr)+\n            \'&end=\'+\n            encodeURIComponent(info.endStr)\n        );\n\n        success(d.appointments.map(a=>({\n\n            id:String(a.id),\n\n            title:\n                (a.client_first\n                    ? `${a.client_first} ${a.client_last} — `\n                    : \'\') +\n                (a.service_name || a.title),\n\n            start:a.starts_at,\n            end:a.ends_at,\n\n            backgroundColor:\n                a.contractor_colors\n                    ? a.contractor_colors.split(\',\')[0]\n                    : undefined,\n\n            borderColor:\n                a.contractor_colors\n                    ? a.contractor_colors.split(\',\')[0]\n                    : undefined,\n\n            extendedProps:a\n\n        })));\n\n    } catch(e){\n        failure(e);\n    }\n}\n\nasync function persistCalendarMove(info){\n\n    const event=info.event;\n    const a=event.extendedProps;\n\n    try{\n\n        const contractorIds=String(a.contractor_ids || \'\')\n            .split(\',\')\n            .filter(Boolean)\n            .map(Number);\n\n        await api(\'/api/appointments.php\',{\n            method:\'PUT\',\n            headers:{\n                \'Content-Type\':\'application/json\'\n            },\n            body:JSON.stringify({\n                id:Number(event.id),\n                client_id:a.client_id || null,\n                service_id:a.service_id || null,\n                title:a.title || \'Appointment\',\n                description:a.description || null,\n                starts_at:sqlDateTime(\n                    localDateTime(event.start)\n                ),\n                ends_at:sqlDateTime(\n                    localDateTime(event.end)\n                ),\n                status:a.status || \'scheduled\',\n                location:a.location || null,\n                is_public:Number(a.is_public || 0) === 1,\n                contractor_ids:contractorIds\n            })\n        });\n\n        calendar.refetchEvents();\n\n    }catch(e){\n\n        info.revert();\n\n        alert(e.message);\n    }\n}\n\nfunction editAppointment(event){\n\n    const a=event.extendedProps;\n\n    appointmentId.value=event.id;\n\n    clientId.value=a.client_id || \'\';\n    serviceId.value=a.service_id || \'\';\n\n    const contractors=String(a.contractor_ids || \'\')\n        .split(\',\')\n        .filter(Boolean);\n\n    contractorId.value=contractors[0] || \'\';\n\n    appointmentTitle.value=a.title || \'Appointment\';\n    startsAt.value=localDateTime(a.starts_at);\n    endsAt.value=localDateTime(a.ends_at);\n    appointmentStatus.value=a.status || \'scheduled\';\n    appointmentLocation.value=a.location || \'\';\n    appointmentDescription.value=a.description || \'\';\n\n    formTitle.textContent=\'Edit Appointment\';\n    saveButton.textContent=\'Save Changes\';\n    deleteButton.style.display=\'inline-block\';\n    invoiceButton.style.display =\n        a.status === \'completed\'\n            ? \'inline-block\'\n            : \'none\';\n\n\n    window.scrollTo({top:0,behavior:\'smooth\'});\n}\n\nappointmentForm.onsubmit=async e=>{\n\n    e.preventDefault();\n    appointmentError.textContent=\'\';\n\n    try {\n\n        const d=Object.fromEntries(\n            new FormData(appointmentForm)\n        );\n\n        // Explicitly capture relationship selections.\n        d.client_id = clientId.value || null;\n        d.service_id = serviceId.value || null;\n\n        d.contractor_ids =\n            contractorId.value\n                ? [Number(contractorId.value)]\n                : [];\n\n        d.starts_at=sqlDateTime(d.starts_at);\n        d.ends_at=sqlDateTime(d.ends_at);\n\n        const editing=Boolean(d.id);\n\n        if(editing){\n            d.id=Number(d.id);\n        } else {\n            delete d.id;\n        }\n\n        await api(\'/api/appointments.php\',{\n            method:editing ? \'PUT\' : \'POST\',\n            headers:{\'Content-Type\':\'application/json\'},\n            body:JSON.stringify(d)\n        });\n\n        resetForm();\n        calendar.refetchEvents();\n\n    } catch(e){\n        appointmentError.textContent=e.message;\n    }\n};\n\nnewButton.onclick=resetForm;\n\ndeleteButton.onclick=async ()=>{\n\n    if(!appointmentId.value) return;\n\n    if(!confirm(\'Delete this appointment?\')) return;\n\n    try {\n\n        await api(\'/api/appointments.php\',{\n            method:\'DELETE\',\n            headers:{\'Content-Type\':\'application/json\'},\n            body:JSON.stringify({\n                id:Number(appointmentId.value)\n            })\n        });\n\n        resetForm();\n        calendar.refetchEvents();\n\n    } catch(e){\n        appointmentError.textContent=e.message;\n    }\n};\n\n\ninvoiceButton.onclick = async () => {\n\n    if (!appointmentId.value) {\n        alert(\'Select an appointment first.\');\n        return;\n    }\n\n    if (!confirm(\n        \'Generate an invoice for this completed appointment?\'\n    )) {\n        return;\n    }\n\n    if (typeof appointmentError !== \'undefined\') {\n        appointmentError.textContent = \'\';\n    }\n\n    try {\n\n        const result = await api(\n            \'/api/invoices.php\',\n            {\n                method: \'POST\',\n                headers: {\n                    \'Content-Type\': \'application/json\'\n                },\n                body: JSON.stringify({\n                    appointment_id:\n                        Number(appointmentId.value)\n                })\n            }\n        );\n\n        alert(\n            `Invoice ${result.invoice_number} created for $${Number(result.total).toFixed(2)}`\n        );\n\n        window.location.href =\n            window.BRITE_BASE + \'/billing.php\';\n\n    } catch (e) {\n\n        if (typeof appointmentError !== \'undefined\') {\n            appointmentError.textContent = e.message;\n        } else {\n            alert(e.message);\n        }\n    }\n};\n\ndocument.addEventListener(\'DOMContentLoaded\',async ()=>{\n\n    try {\n\n        await loadLookups();\n\n        calendar=new FullCalendar.Calendar(\n            document.getElementById(\'calendar\'),\n            {\n                initialView:\'dayGridMonth\',\n\n                headerToolbar:{\n                    left:\'prev,next today\',\n                    center:\'title\',\n                    right:\'dayGridMonth,timeGridWeek,timeGridDay\'\n                },\n\n                selectable:true,\n                editable:true,\n                eventDurationEditable:true,\n                eventStartEditable:true,\n\n                events:loadEvents,\n\n                eventDrop:async info=>{\n                    await persistCalendarMove(info);\n                },\n\n                eventResize:async info=>{\n                    await persistCalendarMove(info);\n                },\n\n                select:info=>{\n                    resetForm();\n\n                    startsAt.value=localDateTime(info.start);\n\n                    let end=new Date(info.start);\n                    end.setHours(end.getHours()+1);\n\n                    endsAt.value=localDateTime(end);\n                },\n\n                eventClick:info=>{\n                    editAppointment(info.event);\n                }\n            }\n        );\n\n        calendar.render();\n\n    } catch(e){\n        appointmentError.textContent=e.message;\n    }\n});\n</script>\n\n<?php require __DIR__.\'/partials/app_footer.php\'; ?>\n'

FILES['billing.php'] = '<?php\nrequire_once __DIR__.\'/app/bootstrap.php\';\nAuth::requireRole(\'owner\',\'admin\',\'accounting\');\n\n\n$pageTitle=\'Billing\';\n\nrequire __DIR__.\'/partials/app_header.php\';\n\n?>\n\n<h1>Billing & Payments</h1>\n\n<div id="billingError"\n     class="danger"\n     style="margin-bottom:12px"></div>\n\n<div class="grid">\n\n    <div class="card">\n        <div class="muted">\n            Outstanding Receivables\n        </div>\n        <div class="metric"\n             id="receivablesMetric">\n            $0.00\n        </div>\n    </div>\n\n    <div class="card">\n        <div class="muted">\n            Total Invoiced\n        </div>\n        <div class="metric"\n             id="invoicedMetric">\n            $0.00\n        </div>\n    </div>\n\n    <div class="card">\n        <div class="muted">\n            Successful Payments\n        </div>\n        <div class="metric"\n             id="paymentsMetric">\n            $0.00\n        </div>\n    </div>\n\n</div>\n\n<div class="card"\n     style="margin-top:18px">\n\n    <h2>Invoices</h2>\n\n    <div style="overflow-x:auto">\n\n        <table>\n\n            <thead>\n                <tr>\n                    <th>#</th>\n                    <th>Client</th>\n                    <th>Appointment</th>\n                    <th>Status</th>\n                    <th>Total</th>\n                    <th>Paid</th>\n                    <th>Balance</th>\n                    <th>Due Date</th>\n                    <th>Action</th>\n                </tr>\n            </thead>\n\n            <tbody id="invoiceRows"></tbody>\n\n        </table>\n\n    </div>\n\n</div>\n\n<div class="card"\n     id="paymentCard"\n     style="margin-top:18px;display:none">\n\n    <h2>Record Payment</h2>\n\n    <div id="paymentInvoice"\n         style="margin-bottom:14px"></div>\n\n    <form id="paymentForm">\n\n        <input type="hidden"\n               id="paymentInvoiceId">\n\n        <div class="row">\n\n            <label>\n                Amount\n                <input type="number"\n                       id="paymentAmount"\n                       min="0.01"\n                       step="0.01"\n                       required>\n            </label>\n\n            <label>\n                Method\n                <select id="paymentMethod">\n                    <option value="cash">Cash</option>\n                    <option value="check">Check</option>\n                    <option value="card">Card / External Processor</option>\n                    <option value="ach">ACH</option>\n                    <option value="zelle">Zelle</option>\n                    <option value="other">Other</option>\n                </select>\n            </label>\n\n            <label>\n                Payment Date\n                <input type="datetime-local"\n                       id="paymentDate">\n            </label>\n\n        </div>\n\n        <div class="row">\n\n            <label>\n                Provider\n                <input type="text"\n                       id="paymentProvider"\n                       placeholder="Optional">\n            </label>\n\n            <label>\n                Provider / Transaction Reference\n                <input type="text"\n                       id="paymentReference"\n                       placeholder="Optional">\n            </label>\n\n        </div>\n\n        <div class="row">\n\n            <label>\n                Notes\n                <textarea id="paymentNotes"\n                          rows="3"\n                          placeholder="Optional"></textarea>\n            </label>\n\n        </div>\n\n        <button type="submit">\n            Record Payment\n        </button>\n\n        <button type="button"\n                id="cancelPayment">\n            Cancel\n        </button>\n\n    </form>\n\n</div>\n\n<div class="card"\n     style="margin-top:18px">\n\n    <h2>Payment History</h2>\n\n    <div style="overflow-x:auto">\n\n        <table>\n\n            <thead>\n                <tr>\n                    <th>Invoice</th>\n                    <th>Client</th>\n                    <th>Amount</th>\n                    <th>Method</th>\n                    <th>Status</th>\n                    <th>Date</th>\n                    <th>Reference</th>\n                </tr>\n            </thead>\n\n            <tbody id="paymentRows"></tbody>\n\n        </table>\n\n    </div>\n\n</div>\n\n<script>\n\nconst billingError =\n    document.getElementById(\'billingError\');\n\nconst invoiceRows =\n    document.getElementById(\'invoiceRows\');\n\nconst paymentRows =\n    document.getElementById(\'paymentRows\');\n\nconst paymentCard =\n    document.getElementById(\'paymentCard\');\n\nconst paymentForm =\n    document.getElementById(\'paymentForm\');\n\nconst paymentInvoiceId =\n    document.getElementById(\'paymentInvoiceId\');\n\nconst paymentInvoice =\n    document.getElementById(\'paymentInvoice\');\n\nconst paymentAmount =\n    document.getElementById(\'paymentAmount\');\n\nconst paymentMethod =\n    document.getElementById(\'paymentMethod\');\n\nconst paymentDate =\n    document.getElementById(\'paymentDate\');\n\nconst paymentProvider =\n    document.getElementById(\'paymentProvider\');\n\nconst paymentReference =\n    document.getElementById(\'paymentReference\');\n\nconst paymentNotes =\n    document.getElementById(\'paymentNotes\');\n\nconst cancelPayment =\n    document.getElementById(\'cancelPayment\');\n\nlet invoices = [];\nlet payments = [];\n\nfunction money(value){\n    return \'$\' + Number(value || 0).toFixed(2);\n}\n\nfunction escapeHtml(value){\n\n    return String(value ?? \'\')\n        .replaceAll(\'&\',\'&amp;\')\n        .replaceAll(\'<\',\'&lt;\')\n        .replaceAll(\'>\',\'&gt;\')\n        .replaceAll(\'"\',\'&quot;\')\n        .replaceAll("\'","&#039;");\n}\n\nfunction displayDate(value){\n\n    if(!value){\n        return \'\';\n    }\n\n    const normalized =\n        value.replace(\' \',\'T\');\n\n    const d =\n        new Date(normalized);\n\n    if(Number.isNaN(d.getTime())){\n        return value;\n    }\n\n    return d.toLocaleString();\n}\n\nfunction statusLabel(status){\n\n    const labels={\n        draft:\'Draft\',\n        sent:\'Sent\',\n        partial:\'Partial\',\n        paid:\'Paid\',\n        void:\'Void\',\n        overdue:\'Overdue\'\n    };\n\n    return labels[status] || status;\n}\n\n\nwindow.viewInvoice=function(id){\n\n    location.href =\n        window.BRITE_BASE +\n        \'/invoice.php?id=\' +\n        encodeURIComponent(id);\n};\n\nfunction renderInvoices(){\n\n    if(!invoices.length){\n\n        invoiceRows.innerHTML=\n            \'<tr><td colspan="9" class="muted">No invoices yet.</td></tr>\';\n\n        return;\n    }\n\n    invoiceRows.innerHTML =\n        invoices.map(x=>{\n\n            const balance =\n                Number(x.balance_due || 0);\n\n            const paid =\n                Number(x.amount_paid || 0);\n\n            /*\n             * Every invoice can be viewed regardless of\n             * payment state.\n             */\n            let action=`\n                <button type="button"\n                        onclick="viewInvoice(${Number(x.id)})">\n                    View Invoice\n                </button>\n            `;\n\n            /*\n             * Only invoices with an outstanding balance\n             * can accept another payment.\n             */\n            if(\n                balance > 0 &&\n                x.status !== \'void\'\n            ){\n                action += `\n                    <button type="button"\n                            onclick="openPayment(${Number(x.id)})"\n                            style="margin-left:6px">\n                        Record Payment\n                    </button>\n                `;\n            }\n\n            return `\n                <tr>\n                    <td>${escapeHtml(x.invoice_number)}</td>\n                    <td>${escapeHtml(x.client_name)}</td>\n                    <td>${escapeHtml(x.appointment_title || \'\')}</td>\n                    <td>${escapeHtml(statusLabel(x.status))}</td>\n                    <td>${money(x.total)}</td>\n                    <td>${money(paid)}</td>\n                    <td>${money(balance)}</td>\n                    <td>${escapeHtml(displayDate(x.due_at))}</td>\n                    <td>${action}</td>\n                </tr>\n            `;\n        }).join(\'\');\n}\n\nfunction renderPayments(){\n\n    if(!payments.length){\n\n        paymentRows.innerHTML=\n            \'<tr><td colspan="7" class="muted">No payments yet.</td></tr>\';\n\n        return;\n    }\n\n    paymentRows.innerHTML =\n        payments.map(x=>`\n            <tr>\n                <td>${escapeHtml(x.invoice_number || \'\')}</td>\n                <td>${escapeHtml(x.client_name)}</td>\n                <td>${money(x.amount)}</td>\n                <td>${escapeHtml(x.method || \'\')}</td>\n                <td>${escapeHtml(x.status)}</td>\n                <td>${escapeHtml(displayDate(x.paid_at))}</td>\n                <td>${escapeHtml(x.provider_reference || \'\')}</td>\n            </tr>\n        `).join(\'\');\n}\n\nfunction renderMetrics(){\n\n    const totalInvoiced =\n        invoices\n            .filter(x=>x.status !== \'void\')\n            .reduce(\n                (sum,x)=>sum+Number(x.total || 0),\n                0\n            );\n\n    const receivables =\n        invoices\n            .filter(x=>x.status !== \'void\')\n            .reduce(\n                (sum,x)=>sum+Number(x.balance_due || 0),\n                0\n            );\n\n    const successfulPayments =\n        payments\n            .filter(x=>x.status === \'succeeded\')\n            .reduce(\n                (sum,x)=>sum+Number(x.amount || 0),\n                0\n            );\n\n    document.getElementById(\n        \'invoicedMetric\'\n    ).textContent=money(totalInvoiced);\n\n    document.getElementById(\n        \'receivablesMetric\'\n    ).textContent=money(receivables);\n\n    document.getElementById(\n        \'paymentsMetric\'\n    ).textContent=money(successfulPayments);\n}\n\nasync function loadBilling(){\n\n    billingError.textContent=\'\';\n\n    try{\n\n        const [invoiceData,paymentData] =\n            await Promise.all([\n                api(\'/api/invoices.php\'),\n                api(\'/api/payments.php\')\n            ]);\n\n        invoices =\n            invoiceData.invoices || [];\n\n        payments =\n            paymentData.payments || [];\n\n        renderInvoices();\n        renderPayments();\n        renderMetrics();\n\n    }catch(e){\n\n        billingError.textContent=e.message;\n    }\n}\n\nwindow.openPayment=function(id){\n\n    const invoice =\n        invoices.find(\n            x=>Number(x.id)===Number(id)\n        );\n\n    if(!invoice){\n        return;\n    }\n\n    paymentInvoiceId.value=\n        invoice.id;\n\n    paymentAmount.value=\n        Number(invoice.balance_due).toFixed(2);\n\n    paymentInvoice.innerHTML=`\n        <strong>${escapeHtml(invoice.invoice_number)}</strong>\n        · ${escapeHtml(invoice.client_name)}\n        · Balance ${money(invoice.balance_due)}\n    `;\n\n    paymentProvider.value=\'\';\n    paymentReference.value=\'\';\n    paymentNotes.value=\'\';\n\n    /*\n     * Leave date blank so the server records its\n     * authoritative current time.\n     */\n    paymentDate.value=\'\';\n\n    paymentCard.style.display=\'block\';\n\n    paymentCard.scrollIntoView({\n        behavior:\'smooth\',\n        block:\'start\'\n    });\n};\n\ncancelPayment.onclick=()=>{\n\n    paymentCard.style.display=\'none\';\n    paymentForm.reset();\n    paymentInvoiceId.value=\'\';\n};\n\npaymentForm.addEventListener(\n    \'submit\',\n    async e=>{\n\n        e.preventDefault();\n\n        billingError.textContent=\'\';\n\n        const invoiceId =\n            Number(paymentInvoiceId.value);\n\n        const amount =\n            Number(paymentAmount.value);\n\n        if(!invoiceId){\n            billingError.textContent=\n                \'Invoice required\';\n            return;\n        }\n\n        if(!amount || amount <= 0){\n            billingError.textContent=\n                \'Enter a valid payment amount\';\n            return;\n        }\n\n        const payload={\n            invoice_id:invoiceId,\n            amount:amount,\n            currency:\'USD\',\n            status:\'succeeded\',\n            method:paymentMethod.value,\n            provider:\n                paymentProvider.value || null,\n            provider_reference:\n                paymentReference.value || null,\n            notes:\n                paymentNotes.value || null\n        };\n\n        if(paymentDate.value){\n            payload.paid_at=\n                paymentDate.value;\n        }\n\n        try{\n\n            const result =\n                await api(\n                    \'/api/payments.php\',\n                    {\n                        method:\'POST\',\n                        headers:{\n                            \'Content-Type\':\n                                \'application/json\'\n                        },\n                        body:JSON.stringify(payload)\n                    }\n                );\n\n            alert(\n                `${money(result.amount)} payment recorded. ` +\n                `Remaining balance: ${money(result.invoice.balance_due)}`\n            );\n\n            paymentCard.style.display=\'none\';\n            paymentForm.reset();\n            paymentInvoiceId.value=\'\';\n\n            await loadBilling();\n\n        }catch(e){\n\n            billingError.textContent=\n                e.message;\n        }\n    }\n);\n\nloadBilling();\n\n</script>\n\n<?php require __DIR__.\'/partials/app_footer.php\'; ?>\n'

FILES['invoice.php'] = '<?php\n\n$pageTitle=\'Invoice\';\n\nrequire __DIR__.\'/partials/app_header.php\';\n\n?>\n\n<style>\n\n.invoice-toolbar{\n    max-width:920px;\n    margin:0 auto 16px;\n    display:flex;\n    gap:10px;\n    flex-wrap:wrap;\n}\n\n.invoice-paper{\n    max-width:920px;\n    margin:0 auto;\n    background:#fff;\n    border:1px solid #e2e8f0;\n    border-radius:12px;\n    padding:46px 52px;\n    box-shadow:0 3px 14px #0000000d;\n}\n\n.invoice-top{\n    display:flex;\n    justify-content:space-between;\n    gap:40px;\n    padding-bottom:28px;\n    border-bottom:2px solid #172033;\n}\n\n.business-name{\n    font-size:26px;\n    font-weight:750;\n    margin-bottom:8px;\n}\n\n.business-contact{\n    line-height:1.55;\n    color:#475569;\n}\n\n.invoice-title{\n    text-align:right;\n}\n\n.invoice-title h1{\n    margin:0 0 8px;\n    font-size:34px;\n    letter-spacing:2px;\n}\n\n.invoice-number{\n    font-weight:650;\n}\n\n.status{\n    display:inline-block;\n    margin-top:12px;\n    padding:6px 13px;\n    border-radius:999px;\n    background:#eef2ff;\n    font-weight:700;\n    text-transform:uppercase;\n    letter-spacing:.5px;\n    font-size:12px;\n}\n\n.invoice-info{\n    display:grid;\n    grid-template-columns:1fr 1fr;\n    gap:60px;\n    margin:32px 0;\n}\n\n.section-label{\n    color:#64748b;\n    text-transform:uppercase;\n    letter-spacing:1px;\n    font-size:12px;\n    font-weight:700;\n    margin-bottom:9px;\n}\n\n.bill-to{\n    line-height:1.6;\n}\n\n.meta-table{\n    width:100%;\n    background:transparent;\n}\n\n.meta-table td{\n    padding:4px 0;\n    border:0;\n}\n\n.meta-table td:last-child{\n    text-align:right;\n    font-weight:600;\n}\n\n.appointment-box{\n    margin-bottom:30px;\n    padding:16px 18px;\n    background:#f8fafc;\n    border-radius:8px;\n}\n\n.items-table{\n    margin-top:10px;\n}\n\n.items-table th{\n    background:#172033;\n    color:#fff;\n    border:0;\n}\n\n.items-table th:nth-child(n+2),\n.items-table td:nth-child(n+2){\n    text-align:right;\n}\n\n.summary{\n    width:360px;\n    margin:24px 0 0 auto;\n}\n\n.summary td{\n    border:0;\n    padding:6px 8px;\n}\n\n.summary td:last-child{\n    text-align:right;\n}\n\n.summary .total-row td{\n    border-top:1px solid #cbd5e1;\n    padding-top:10px;\n    font-weight:700;\n}\n\n.balance-box{\n    margin-top:8px;\n    padding:14px 12px;\n    background:#172033;\n    color:#fff;\n    border-radius:7px;\n    display:flex;\n    justify-content:space-between;\n    align-items:center;\n}\n\n.balance-box strong{\n    font-size:22px;\n}\n\n.paid-stamp{\n    margin:30px 0 0 auto;\n    width:max-content;\n    border:3px solid #334155;\n    padding:8px 18px;\n    font-size:18px;\n    font-weight:800;\n    letter-spacing:2px;\n    transform:rotate(-2deg);\n}\n\n.invoice-notes{\n    margin-top:36px;\n    padding-top:22px;\n    border-top:1px solid #e2e8f0;\n}\n\n.payment-history{\n    margin-top:34px;\n}\n\n.payment-history th:nth-child(3),\n.payment-history td:nth-child(3),\n.payment-history th:nth-child(4),\n.payment-history td:nth-child(4){\n    text-align:right;\n}\n\n.invoice-footer{\n    margin-top:42px;\n    padding-top:20px;\n    border-top:1px solid #e2e8f0;\n    text-align:center;\n    color:#64748b;\n    font-size:13px;\n}\n\n#error{\n    max-width:920px;\n    margin:0 auto 12px;\n}\n\n@media(max-width:700px){\n\n    .invoice-paper{\n        padding:25px;\n    }\n\n    .invoice-top,\n    .invoice-info{\n        display:block;\n    }\n\n    .invoice-title{\n        text-align:left;\n        margin-top:25px;\n    }\n\n    .invoice-info > div{\n        margin-bottom:25px;\n    }\n\n    .summary{\n        width:100%;\n    }\n}\n\n@page{\n    size:Letter;\n    margin:0.45in;\n}\n\n@media print{\n\n    body{\n        background:#fff;\n    }\n\n    .top,\n    .nav,\n    .invoice-toolbar,\n    #error{\n        display:none !important;\n    }\n\n    .wrap{\n        max-width:none;\n        margin:0;\n        padding:0;\n    }\n\n    .invoice-paper{\n        max-width:none;\n        margin:0;\n        padding:0;\n        border:0;\n        border-radius:0;\n        box-shadow:none;\n    }\n\n    table,\n    tr,\n    .appointment-box,\n    .summary,\n    .payment-history{\n        break-inside:avoid;\n        page-break-inside:avoid;\n    }\n\n    .items-table th{\n        background:#172033 !important;\n        color:#fff !important;\n        -webkit-print-color-adjust:exact;\n        print-color-adjust:exact;\n    }\n\n    .balance-box{\n        background:#172033 !important;\n        color:#fff !important;\n        -webkit-print-color-adjust:exact;\n        print-color-adjust:exact;\n    }\n}\n\n</style>\n\n<div id="error" class="danger"></div>\n\n<div class="invoice-toolbar">\n\n    <button type="button"\n            onclick="location.href=window.BRITE_BASE+\'/billing.php\'">\n        Back to Billing\n    </button>\n\n    <button type="button"\n            onclick="window.print()">\n        Print / Save PDF\n    </button>\n\n    <button id="markSent"\n            type="button"\n            style="display:none">\n        Mark Sent\n    </button>\n\n    <button id="voidInvoice"\n            type="button"\n            style="display:none">\n        Void Invoice\n    </button>\n\n</div>\n\n<div class="invoice-paper">\n\n    <div id="invoice">\n        Loading invoice...\n    </div>\n\n</div>\n\n<script>\n\nconst invoiceId =\n    Number(\n        new URLSearchParams(location.search).get(\'id\')\n    );\n\nconst output =\n    document.getElementById(\'invoice\');\n\nconst errorBox =\n    document.getElementById(\'error\');\n\nconst markSent =\n    document.getElementById(\'markSent\');\n\nconst voidInvoice =\n    document.getElementById(\'voidInvoice\');\n\nfunction esc(value){\n\n    return String(value ?? \'\')\n        .replaceAll(\'&\',\'&amp;\')\n        .replaceAll(\'<\',\'&lt;\')\n        .replaceAll(\'>\',\'&gt;\')\n        .replaceAll(\'"\',\'&quot;\')\n        .replaceAll("\'","&#039;");\n}\n\nfunction money(value,currency=\'USD\'){\n\n    try{\n        return new Intl.NumberFormat(\n            \'en-US\',\n            {\n                style:\'currency\',\n                currency\n            }\n        ).format(Number(value || 0));\n    }catch(e){\n        return \'$\'+Number(value || 0).toFixed(2);\n    }\n}\n\nfunction parseDate(value){\n\n    if(!value){\n        return null;\n    }\n\n    const d=new Date(\n        String(value).replace(\' \',\'T\')\n    );\n\n    return Number.isNaN(d.getTime())\n        ? null\n        : d;\n}\n\nfunction dateOnly(value){\n\n    const d=parseDate(value);\n\n    return d\n        ? d.toLocaleDateString(\n            \'en-US\',\n            {\n                month:\'short\',\n                day:\'numeric\',\n                year:\'numeric\'\n            }\n        )\n        : \'\';\n}\n\nfunction dateTime(value){\n\n    const d=parseDate(value);\n\n    return d\n        ? d.toLocaleString(\n            \'en-US\',\n            {\n                month:\'short\',\n                day:\'numeric\',\n                year:\'numeric\',\n                hour:\'numeric\',\n                minute:\'2-digit\'\n            }\n        )\n        : \'\';\n}\n\nfunction phone(value){\n\n    const raw=String(value || \'\').trim();\n    const digits=raw.replace(/\\D/g,\'\');\n\n    if(digits.length===10){\n        return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`;\n    }\n\n    if(digits.length===11 && digits[0]===\'1\'){\n        return `+1 (${digits.slice(1,4)}) ${digits.slice(4,7)}-${digits.slice(7)}`;\n    }\n\n    return raw;\n}\n\nfunction lines(values){\n\n    return values\n        .filter(v=>String(v || \'\').trim()!==\'\')\n        .map(v=>esc(v))\n        .join(\'<br>\');\n}\n\nfunction cityStateZip(city,state,zip){\n\n    let left=[city,state]\n        .filter(Boolean)\n        .join(\', \');\n\n    return [left,zip]\n        .filter(Boolean)\n        .join(\' \');\n}\n\nfunction statusLabel(status){\n\n    return {\n        draft:\'Draft\',\n        sent:\'Sent\',\n        partial:\'Partial\',\n        paid:\'Paid\',\n        overdue:\'Overdue\',\n        void:\'Void\'\n    }[status] || status;\n}\n\nfunction render(data){\n\n    const i=data.invoice;\n    const currency=i.tenant_currency || \'USD\';\n\n    const businessAddress=lines([\n        i.tenant_address1,\n        i.tenant_address2,\n        cityStateZip(\n            i.tenant_city,\n            i.tenant_state,\n            i.tenant_postal_code\n        )\n    ]);\n\n    const businessContact=lines([\n        i.tenant_phone ? phone(i.tenant_phone) : \'\',\n        i.tenant_email,\n        i.tenant_website\n    ]);\n\n    const clientAddress=lines([\n        i.company_name,\n        i.client_name,\n        i.address1,\n        i.address2,\n        cityStateZip(\n            i.city,\n            i.state,\n            i.postal_code\n        ),\n        i.client_email,\n        i.client_phone ? phone(i.client_phone) : \'\'\n    ]);\n\n    const itemRows=(data.items || [])\n        .map(item=>`\n            <tr>\n                <td>${esc(item.description)}</td>\n                <td>${Number(item.quantity).toFixed(2)}</td>\n                <td>${money(item.unit_price,currency)}</td>\n                <td>${money(item.amount,currency)}</td>\n            </tr>\n        `)\n        .join(\'\');\n\n    const paymentRows=(data.payments || [])\n        .map(p=>`\n            <tr>\n                <td>${esc(dateTime(p.paid_at || p.created_at))}</td>\n                <td>${esc(p.method || \'\')}</td>\n                <td>${esc(p.status)}</td>\n                <td>${money(p.amount,p.currency || currency)}</td>\n            </tr>\n        `)\n        .join(\'\');\n\n    output.innerHTML=`\n\n        <div class="invoice-top">\n\n            <div>\n\n                <div class="business-name">\n                    ${esc(i.tenant_name)}\n                </div>\n\n                ${\n                    businessAddress\n                    ? `<div class="business-contact">${businessAddress}</div>`\n                    : \'\'\n                }\n\n                ${\n                    businessContact\n                    ? `<div class="business-contact"\n                            style="margin-top:5px">\n                           ${businessContact}\n                       </div>`\n                    : \'\'\n                }\n\n            </div>\n\n            <div class="invoice-title">\n\n                <h1>INVOICE</h1>\n\n                <div class="invoice-number">\n                    ${esc(i.invoice_number)}\n                </div>\n\n                <div class="status">\n                    ${esc(statusLabel(i.status))}\n                </div>\n\n            </div>\n\n        </div>\n\n        <div class="invoice-info">\n\n            <div>\n\n                <div class="section-label">\n                    Bill To\n                </div>\n\n                <div class="bill-to">\n                    ${clientAddress}\n                </div>\n\n            </div>\n\n            <div>\n\n                <div class="section-label">\n                    Invoice Details\n                </div>\n\n                <table class="meta-table">\n\n                    <tr>\n                        <td>Invoice Date</td>\n                        <td>${esc(dateOnly(i.created_at))}</td>\n                    </tr>\n\n                    <tr>\n                        <td>Due Date</td>\n                        <td>${esc(dateOnly(i.due_at))}</td>\n                    </tr>\n\n                    <tr>\n                        <td>Status</td>\n                        <td>${esc(statusLabel(i.status))}</td>\n                    </tr>\n\n                </table>\n\n            </div>\n\n        </div>\n\n        ${\n            i.appointment_title\n            ? `\n                <div class="appointment-box">\n\n                    <div class="section-label">\n                        Service Appointment\n                    </div>\n\n                    <strong>\n                        ${esc(i.appointment_title)}\n                    </strong>\n\n                    ${\n                        i.appointment_starts_at\n                        ? `<div>${esc(dateTime(i.appointment_starts_at))}</div>`\n                        : \'\'\n                    }\n\n                </div>\n              `\n            : \'\'\n        }\n\n        <table class="items-table">\n\n            <thead>\n                <tr>\n                    <th>Description</th>\n                    <th>Qty</th>\n                    <th>Rate</th>\n                    <th>Amount</th>\n                </tr>\n            </thead>\n\n            <tbody>\n                ${itemRows}\n            </tbody>\n\n        </table>\n\n        <table class="summary">\n\n            <tr>\n                <td>Subtotal</td>\n                <td>${money(i.subtotal,currency)}</td>\n            </tr>\n\n            <tr>\n                <td>Tax</td>\n                <td>${money(i.tax_amount,currency)}</td>\n            </tr>\n\n            <tr class="total-row">\n                <td>Total</td>\n                <td>${money(i.total,currency)}</td>\n            </tr>\n\n            <tr>\n                <td>Payments</td>\n                <td>-${money(i.amount_paid,currency)}</td>\n            </tr>\n\n        </table>\n\n        <div class="summary">\n\n            <div class="balance-box">\n\n                <span>\n                    BALANCE DUE\n                </span>\n\n                <strong>\n                    ${money(i.balance_due,currency)}\n                </strong>\n\n            </div>\n\n        </div>\n\n        ${\n            i.status===\'paid\' ||\n            Number(i.balance_due) <= 0\n            ? `<div class="paid-stamp">PAID IN FULL</div>`\n            : \'\'\n        }\n\n        ${\n            i.payment_instructions\n            ? `\n                <div class="invoice-notes">\n\n                    <div class="section-label">\n                        Payment Instructions\n                    </div>\n\n                    <div>\n                        ${esc(i.payment_instructions)\n                            .replaceAll(\'\\n\',\'<br>\')}\n                    </div>\n\n                </div>\n              `\n            : \'\'\n        }\n\n        ${\n            i.notes\n            ? `\n                <div class="invoice-notes">\n\n                    <div class="section-label">\n                        Notes\n                    </div>\n\n                    <div>\n                        ${esc(i.notes)\n                            .replaceAll(\'\\n\',\'<br>\')}\n                    </div>\n\n                </div>\n              `\n            : \'\'\n        }\n\n        ${\n            data.payments && data.payments.length\n            ? `\n                <div class="payment-history">\n\n                    <div class="section-label">\n                        Payment History\n                    </div>\n\n                    <table>\n\n                        <thead>\n                            <tr>\n                                <th>Date</th>\n                                <th>Method</th>\n                                <th>Status</th>\n                                <th>Amount</th>\n                            </tr>\n                        </thead>\n\n                        <tbody>\n                            ${paymentRows}\n                        </tbody>\n\n                    </table>\n\n                </div>\n              `\n            : \'\'\n        }\n\n        ${\n            i.invoice_footer\n            ? `\n                <div class="invoice-footer">\n                    ${esc(i.invoice_footer)\n                        .replaceAll(\'\\n\',\'<br>\')}\n                </div>\n              `\n            : \'\'\n        }\n    `;\n\n    markSent.style.display =\n        i.status===\'draft\'\n            ? \'inline-block\'\n            : \'none\';\n\n    voidInvoice.style.display =\n        [\'draft\',\'sent\',\'overdue\'].includes(i.status) &&\n        Number(i.amount_paid || 0)===0\n            ? \'inline-block\'\n            : \'none\';\n}\n\nasync function loadInvoice(){\n\n    if(!invoiceId){\n\n        errorBox.textContent=\'Invalid invoice.\';\n        return;\n    }\n\n    try{\n\n        errorBox.textContent=\'\';\n\n        const data=await api(\n            \'/api/invoices.php?id=\'+\n            encodeURIComponent(invoiceId)\n        );\n\n        render(data);\n\n    }catch(e){\n\n        errorBox.textContent=e.message;\n    }\n}\n\nasync function changeStatus(status){\n\n    try{\n\n        errorBox.textContent=\'\';\n\n        await api(\n            \'/api/invoices.php\',\n            {\n                method:\'PUT\',\n                headers:{\n                    \'Content-Type\':\'application/json\'\n                },\n                body:JSON.stringify({\n                    id:invoiceId,\n                    status\n                })\n            }\n        );\n\n        await loadInvoice();\n\n    }catch(e){\n\n        errorBox.textContent=e.message;\n    }\n}\n\nmarkSent.onclick=async ()=>{\n\n    if(confirm(\'Mark this invoice as sent?\')){\n        await changeStatus(\'sent\');\n    }\n};\n\nvoidInvoice.onclick=async ()=>{\n\n    if(confirm(\'Void this invoice?\')){\n        await changeStatus(\'void\');\n    }\n};\n\nloadInvoice();\n\n</script>\n\n<?php require __DIR__.\'/partials/app_footer.php\'; ?>\n'

FILES['settings.php'] = '<?php\nrequire_once __DIR__.\'/app/bootstrap.php\';\nAuth::requireRole(\'owner\',\'admin\');\n\n\n$pageTitle=\'Settings\';\n\nrequire __DIR__.\'/partials/app_header.php\';\n\n?>\n\n<h1>Business Settings</h1>\n\n<p class="muted">\n    Configure the business information displayed on invoices\n    and other customer-facing documents.\n</p>\n\n<div class="card">\n\n    <h2>Business Profile</h2>\n\n    <form id="businessForm">\n\n        <div class="row">\n\n            <div>\n                <label>Business Name</label><br>\n                <input id="businessName"\n                       required>\n            </div>\n\n            <div>\n                <label>Business Email</label><br>\n                <input id="businessEmail"\n                       type="email">\n            </div>\n\n            <div>\n                <label>Business Phone</label><br>\n                <input id="businessPhone">\n            </div>\n\n        </div>\n\n        <div class="row">\n\n            <div>\n                <label>Website</label><br>\n                <input id="website"\n                       placeholder="https://example.com">\n            </div>\n\n            <div>\n                <label>Currency</label><br>\n                <input id="currency"\n                       maxlength="3"\n                       value="USD">\n            </div>\n\n            <div>\n                <label>Timezone</label><br>\n                <input id="timezone"\n                       value="America/New_York">\n            </div>\n\n        </div>\n\n        <h3>Business Address</h3>\n\n        <div class="row">\n\n            <div>\n                <label>Address</label><br>\n                <input id="address1">\n            </div>\n\n            <div>\n                <label>Address 2</label><br>\n                <input id="address2">\n            </div>\n\n        </div>\n\n        <div class="row">\n\n            <div>\n                <label>City</label><br>\n                <input id="city">\n            </div>\n\n            <div>\n                <label>State</label><br>\n                <input id="state">\n            </div>\n\n            <div>\n                <label>ZIP / Postal Code</label><br>\n                <input id="postalCode">\n            </div>\n\n        </div>\n\n        <h3>Invoice Configuration</h3>\n\n        <div style="margin-bottom:16px">\n\n            <label>Payment Instructions</label><br>\n\n            <textarea id="paymentInstructions"\n                      rows="4"\n                      style="width:100%"\n                      placeholder="Payment methods, remittance instructions, or other customer payment information."></textarea>\n\n        </div>\n\n        <div style="margin-bottom:16px">\n\n            <label>Invoice Footer</label><br>\n\n            <textarea id="invoiceFooter"\n                      rows="3"\n                      style="width:100%"\n                      placeholder="Thank you for your business."></textarea>\n\n        </div>\n\n        <button type="submit">\n            Save Business Profile\n        </button>\n\n        <span id="saveStatus"\n              style="margin-left:12px"></span>\n\n    </form>\n\n</div>\n\n<script>\n\nconst businessForm =\n    document.getElementById(\'businessForm\');\n\nconst saveStatus =\n    document.getElementById(\'saveStatus\');\n\nconst fields={\n    name:document.getElementById(\'businessName\'),\n    business_email:document.getElementById(\'businessEmail\'),\n    business_phone:document.getElementById(\'businessPhone\'),\n    website:document.getElementById(\'website\'),\n    currency:document.getElementById(\'currency\'),\n    timezone:document.getElementById(\'timezone\'),\n    address1:document.getElementById(\'address1\'),\n    address2:document.getElementById(\'address2\'),\n    city:document.getElementById(\'city\'),\n    state:document.getElementById(\'state\'),\n    postal_code:document.getElementById(\'postalCode\'),\n    payment_instructions:document.getElementById(\'paymentInstructions\'),\n    invoice_footer:document.getElementById(\'invoiceFooter\')\n};\n\nasync function loadBusiness(){\n\n    try{\n\n        const data =\n            await api(\'/api/business_profile.php\');\n\n        const b=data.business;\n\n        Object.entries(fields).forEach(\n            ([key,element])=>{\n                element.value=b[key] ?? \'\';\n            }\n        );\n\n    }catch(e){\n\n        saveStatus.textContent=e.message;\n        saveStatus.className=\'danger\';\n    }\n}\n\nbusinessForm.addEventListener(\n    \'submit\',\n    async event=>{\n\n        event.preventDefault();\n\n        saveStatus.textContent=\'Saving...\';\n        saveStatus.className=\'muted\';\n\n        const payload={};\n\n        Object.entries(fields).forEach(\n            ([key,element])=>{\n                payload[key]=element.value;\n            }\n        );\n\n        try{\n\n            await api(\n                \'/api/business_profile.php\',\n                {\n                    method:\'PUT\',\n                    headers:{\n                        \'Content-Type\':\'application/json\'\n                    },\n                    body:JSON.stringify(payload)\n                }\n            );\n\n            saveStatus.textContent=\'Saved\';\n            saveStatus.className=\'\';\n\n        }catch(e){\n\n            saveStatus.textContent=e.message;\n            saveStatus.className=\'danger\';\n        }\n    }\n);\n\nloadBusiness();\n\n</script>\n\n<?php require __DIR__.\'/partials/app_footer.php\'; ?>\n'

FILES['sign-in.v2.php'] = '<?php require_once __DIR__.\'/app/bootstrap.php\';$csrf=csrf_token();?><!doctype html><html><head><meta charset="utf-8"><title>BriteScheduler Sign In</title><style>body{font-family:system-ui;background:#f5f7fb}.box{max-width:360px;margin:10vh auto;background:white;padding:30px;border-radius:12px}input,button{box-sizing:border-box;width:100%;padding:11px;margin:7px 0}</style></head><body><div class="box"><h1>BriteScheduler</h1><form id="f"><input name="email" type="email" placeholder="Email" required><input name="password" type="password" placeholder="Password" required><button>Sign in</button><p id="e"></p></form>\n\n<div style="\n    margin-top:24px;\n    padding-top:20px;\n    border-top:1px solid #e5e7eb;\n    text-align:center;\n">\n\n    <div style="\n        margin-bottom:12px;\n        color:#64748b;\n    ">\n        Don\'t have an account?\n    </div>\n\n    <div style="\n        display:flex;\n        gap:10px;\n        flex-wrap:wrap;\n    ">\n\n        <a\n            href="<?=htmlspecialchars(app_url(\'/sign-up.v2.php?type=client\'))?>"\n            style="\n                flex:1;\n                min-width:150px;\n                box-sizing:border-box;\n                padding:10px 12px;\n                border:1px solid #cbd5e1;\n                border-radius:7px;\n                text-decoration:none;\n                color:#334155;\n                font-weight:600;\n            ">\n            Sign Up as Client\n        </a>\n\n        <a\n            href="<?=htmlspecialchars(app_url(\'/sign-up.v2.php?type=contractor\'))?>"\n            style="\n                flex:1;\n                min-width:150px;\n                box-sizing:border-box;\n                padding:10px 12px;\n                border:1px solid #cbd5e1;\n                border-radius:7px;\n                text-decoration:none;\n                color:#334155;\n                font-weight:600;\n            ">\n            Sign Up as Contractor\n        </a>\n\n    </div>\n\n</div>\n</div><script>f.onsubmit=async x=>{x.preventDefault();let d=Object.fromEntries(new FormData(f));let r=await fetch(<?=json_encode(app_url(\'/api/auth/login.php\'))?>,{method:\'POST\',headers:{\'Content-Type\':\'application/json\',\'X-CSRF-Token\':<?=json_encode($csrf)?>},body:JSON.stringify(d)}),j=await r.json();if(r.ok)location=j.redirect;else e.textContent=j.error}</script></body></html>'

FILES['sign-up.v2.php'] = '<?php\n\nrequire_once __DIR__.\'/app/bootstrap.php\';\n\nif (Auth::user()) {\n    header(\'Location: \'.app_url(\'/dashboard.v2.php\'));\n    exit;\n}\n\n$csrf = csrf_token();\n\n$type = strtolower(trim($_GET[\'type\'] ?? \'client\'));\n\nif (!in_array($type, [\'client\',\'contractor\'], true)) {\n    $type = \'client\';\n}\n\n$isContractor = $type === \'contractor\';\n\n?><!doctype html>\n<html>\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width,initial-scale=1">\n\n<title>Create Account - BriteScheduler</title>\n\n<style>\nbody{\n    margin:0;\n    font-family:system-ui,sans-serif;\n    background:#f5f7fb;\n    color:#1f2937;\n}\n.auth-wrap{\n    min-height:100vh;\n    display:flex;\n    align-items:center;\n    justify-content:center;\n    padding:30px 18px;\n}\n.auth-card{\n    width:100%;\n    max-width:520px;\n    background:#fff;\n    border:1px solid #e5e7eb;\n    border-radius:16px;\n    padding:30px;\n    box-shadow:0 8px 30px #00000012;\n}\nh1{\n    margin:0 0 6px;\n}\n.subtitle{\n    color:#64748b;\n    margin-bottom:24px;\n}\n.row{\n    display:flex;\n    gap:12px;\n}\n.field{\n    flex:1;\n    margin-bottom:15px;\n}\nlabel{\n    display:block;\n    font-weight:600;\n    margin-bottom:6px;\n}\ninput{\n    width:100%;\n    box-sizing:border-box;\n    padding:11px;\n    border:1px solid #cbd5e1;\n    border-radius:7px;\n}\nbutton{\n    width:100%;\n    padding:12px;\n    border:0;\n    border-radius:7px;\n    background:#172033;\n    color:white;\n    font-weight:700;\n    cursor:pointer;\n}\n.error{\n    display:none;\n    background:#fee2e2;\n    color:#991b1b;\n    padding:10px;\n    border-radius:7px;\n    margin-bottom:15px;\n}\n.links{\n    text-align:center;\n    margin-top:20px;\n}\n.links a{\n    color:#334155;\n}\n.account-type{\n    display:flex;\n    gap:8px;\n    margin-bottom:22px;\n}\n.account-type a{\n    flex:1;\n    text-align:center;\n    padding:10px;\n    border:1px solid #cbd5e1;\n    border-radius:7px;\n    text-decoration:none;\n    color:#334155;\n}\n.account-type a.active{\n    background:#172033;\n    color:#fff;\n    border-color:#172033;\n}\n@media(max-width:600px){\n    .row{display:block}\n}\n</style>\n</head>\n\n<body>\n\n<div class="auth-wrap">\n\n<div class="auth-card">\n\n<h1>Create Account</h1>\n\n<div class="subtitle">\n    Sign up as a <?= $isContractor ? \'contractor\' : \'client\' ?>.\n</div>\n\n<div class="account-type">\n\n<a\n href="<?=htmlspecialchars(app_url(\'/sign-up.v2.php?type=client\'))?>"\n class="<?=$type===\'client\'?\'active\':\'\'?>">\nClient\n</a>\n\n<a\n href="<?=htmlspecialchars(app_url(\'/sign-up.v2.php?type=contractor\'))?>"\n class="<?=$type===\'contractor\'?\'active\':\'\'?>">\nContractor\n</a>\n\n</div>\n\n<div id="error" class="error"></div>\n\n<form id="signupForm">\n\n<input type="hidden"\n       id="type"\n       value="<?=htmlspecialchars($type)?>">\n\n<div class="row">\n\n<div class="field">\n<label>First Name</label>\n<input id="firstName"\n       autocomplete="given-name"\n       required>\n</div>\n\n<div class="field">\n<label>Last Name</label>\n<input id="lastName"\n       autocomplete="family-name"\n       required>\n</div>\n\n</div>\n\n<?php if($isContractor): ?>\n\n<div class="field">\n<label>Business Name</label>\n<input id="businessName"\n       autocomplete="organization">\n</div>\n\n<?php else: ?>\n\n<div class="field">\n<label>Company</label>\n<input id="companyName"\n       autocomplete="organization">\n</div>\n\n<?php endif; ?>\n\n<div class="field">\n<label>Email</label>\n<input type="email"\n       id="email"\n       autocomplete="email"\n       required>\n</div>\n\n<div class="field">\n<label>Phone</label>\n<input type="tel"\n       id="phone"\n       autocomplete="tel">\n</div>\n\n<div class="field">\n<label>Password</label>\n<input type="password"\n       id="password"\n       autocomplete="new-password"\n       minlength="8"\n       required>\n</div>\n\n<div class="field">\n<label>Confirm Password</label>\n<input type="password"\n       id="passwordConfirm"\n       autocomplete="new-password"\n       minlength="8"\n       required>\n</div>\n\n<button type="submit">\nCreate <?= $isContractor ? \'Contractor\' : \'Client\' ?> Account\n</button>\n\n</form>\n\n<div class="links">\nAlready have an account?\n<a href="<?=htmlspecialchars(app_url(\'/sign-in.v2.php\'))?>">\nSign In\n</a>\n</div>\n\n</div>\n</div>\n\n<script>\n\nconst BASE =\n    <?=json_encode(rtrim($_ENV[\'APP_URL\'] ?? \'http://localhost/dashboard\',\'/\'))?>;\n\nconst CSRF =\n    <?=json_encode($csrf)?>;\n\nconst form =\n    document.getElementById(\'signupForm\');\n\nconst errorBox =\n    document.getElementById(\'error\');\n\nform.addEventListener(\'submit\', async e=>{\n\n    e.preventDefault();\n\n    errorBox.style.display=\'none\';\n\n    const payload={\n        type:document.getElementById(\'type\').value,\n        first_name:document.getElementById(\'firstName\').value.trim(),\n        last_name:document.getElementById(\'lastName\').value.trim(),\n        email:document.getElementById(\'email\').value.trim(),\n        phone:document.getElementById(\'phone\').value.trim(),\n        password:document.getElementById(\'password\').value,\n        password_confirm:document.getElementById(\'passwordConfirm\').value,\n        company_name:\n            document.getElementById(\'companyName\')?.value.trim() || \'\',\n        business_name:\n            document.getElementById(\'businessName\')?.value.trim() || \'\'\n    };\n\n    try{\n\n        const r=await fetch(\n            BASE+\'/api/auth/register.php\',\n            {\n                method:\'POST\',\n                headers:{\n                    \'Content-Type\':\'application/json\',\n                    \'X-CSRF-Token\':CSRF\n                },\n                body:JSON.stringify(payload)\n            }\n        );\n\n        const j=await r.json();\n\n        if(!r.ok){\n            throw new Error(j.error || \'Registration failed\');\n        }\n\n        location.href=j.redirect;\n\n    }catch(err){\n\n        errorBox.textContent=err.message;\n        errorBox.style.display=\'block\';\n    }\n\n});\n\n</script>\n\n</body>\n</html>\n'

FILES['client-dashboard.php'] = '<?php\n\nrequire_once __DIR__.\'/app/bootstrap.php\';\n\n$user = Auth::requireUser();\n$tenant = Auth::requireRole(\'client\');\n$tid = (int)$tenant[\'tenant_id\'];\n\n$pdo = Database::connection();\n\n$q=$pdo->prepare(\n    "SELECT *\n       FROM clients\n      WHERE tenant_id=?\n        AND user_id=?\n      LIMIT 1"\n);\n\n$q->execute([\n    $tid,\n    (int)$user[\'id\']\n]);\n\n$client=$q->fetch();\n\nif(!$client){\n    http_response_code(403);\n    exit(\'Client profile not found.\');\n}\n\n$pageTitle=\'My Dashboard\';\n\nrequire __DIR__.\'/partials/app_header.php\';\n\n$q=$pdo->prepare(\n    "SELECT\n        a.id,\n        a.title,\n        a.starts_at,\n        a.ends_at,\n        a.status,\n        s.name AS service_name\n     FROM appointments a\n     LEFT JOIN services s\n       ON s.id=a.service_id\n      AND s.tenant_id=a.tenant_id\n     WHERE a.tenant_id=?\n       AND a.client_id=?\n     ORDER BY a.starts_at DESC\n     LIMIT 20"\n);\n\n$q->execute([$tid,(int)$client[\'id\']]);\n\n$appointments=$q->fetchAll();\n\n$q=$pdo->prepare(\n    "SELECT\n        id,\n        invoice_number,\n        status,\n        total,\n        balance_due,\n        due_at\n     FROM invoices\n     WHERE tenant_id=?\n       AND client_id=?\n     ORDER BY created_at DESC\n     LIMIT 20"\n);\n\n$q->execute([$tid,(int)$client[\'id\']]);\n\n$invoices=$q->fetchAll();\n\n?>\n\n<h1>Client Dashboard</h1>\n\n<p class="muted">\nWelcome,\n<?=htmlspecialchars($user[\'first_name\'])?>.\n</p>\n\n<div class="grid">\n\n<div class="card">\n<div class="muted">Appointments</div>\n<div class="metric"><?=count($appointments)?></div>\n</div>\n\n<div class="card">\n<div class="muted">Invoices</div>\n<div class="metric"><?=count($invoices)?></div>\n</div>\n\n<div class="card">\n<div class="muted">Outstanding Balance</div>\n<div class="metric">\n$<?=number_format(array_sum(array_map(\n    fn($x)=>(float)$x[\'balance_due\'],\n    $invoices\n)),2)?>\n</div>\n</div>\n\n</div>\n\n<div class="card" style="margin-top:18px">\n\n<h2>My Appointments</h2>\n\n<?php if(!$appointments): ?>\n\n<p class="muted">No appointments yet.</p>\n\n<?php else: ?>\n\n<div style="overflow-x:auto">\n\n<table>\n<thead>\n<tr>\n<th>Service</th>\n<th>Appointment</th>\n<th>Start</th>\n<th>Status</th>\n</tr>\n</thead>\n\n<tbody>\n\n<?php foreach($appointments as $a): ?>\n\n<tr>\n<td><?=htmlspecialchars($a[\'service_name\'] ?? \'\')?></td>\n<td><?=htmlspecialchars($a[\'title\'] ?? \'\')?></td>\n<td><?=htmlspecialchars($a[\'starts_at\'])?></td>\n<td><?=htmlspecialchars($a[\'status\'])?></td>\n</tr>\n\n<?php endforeach; ?>\n\n</tbody>\n</table>\n\n</div>\n\n<?php endif; ?>\n\n</div>\n\n\n<div class="card" style="margin-top:18px">\n\n<h2>My Invoices</h2>\n\n<?php if(!$invoices): ?>\n\n<p class="muted">No invoices yet.</p>\n\n<?php else: ?>\n\n<div style="overflow-x:auto">\n\n<table>\n<thead>\n<tr>\n<th>Invoice</th>\n<th>Status</th>\n<th>Total</th>\n<th>Balance</th>\n<th>Due</th>\n</tr>\n</thead>\n\n<tbody>\n\n<?php foreach($invoices as $i): ?>\n\n<tr>\n<td><?=htmlspecialchars($i[\'invoice_number\'])?></td>\n<td><?=htmlspecialchars($i[\'status\'])?></td>\n<td>$<?=number_format((float)$i[\'total\'],2)?></td>\n<td>$<?=number_format((float)$i[\'balance_due\'],2)?></td>\n<td><?=htmlspecialchars($i[\'due_at\'] ?? \'\')?></td>\n</tr>\n\n<?php endforeach; ?>\n\n</tbody>\n</table>\n\n</div>\n\n<?php endif; ?>\n\n</div>\n\n<?php require __DIR__.\'/partials/app_footer.php\'; ?>\n'

FILES['contractor-dashboard.php'] = '<?php\n\nrequire_once __DIR__.\'/app/bootstrap.php\';\n\n$user = Auth::requireUser();\n$tenant = Auth::requireRole(\'contractor\');\n$tid = (int)$tenant[\'tenant_id\'];\n\n$pdo = Database::connection();\n\n$q=$pdo->prepare(\n    "SELECT *\n       FROM contractors\n      WHERE tenant_id=?\n        AND user_id=?\n      LIMIT 1"\n);\n\n$q->execute([\n    $tid,\n    (int)$user[\'id\']\n]);\n\n$contractor=$q->fetch();\n\nif(!$contractor){\n    http_response_code(403);\n    exit(\'Contractor profile not found.\');\n}\n\n$pageTitle=\'Contractor Dashboard\';\n\nrequire __DIR__.\'/partials/app_header.php\';\n\n$q=$pdo->prepare(\n    "SELECT\n        a.id,\n        a.title,\n        a.starts_at,\n        a.ends_at,\n        a.status,\n        ac.status AS assignment_status,\n        c.first_name AS client_first_name,\n        c.last_name AS client_last_name,\n        s.name AS service_name\n     FROM appointment_contractors ac\n     JOIN appointments a\n       ON a.id=ac.appointment_id\n      AND a.tenant_id=?\n     LEFT JOIN clients c\n       ON c.id=a.client_id\n      AND c.tenant_id=a.tenant_id\n     LEFT JOIN services s\n       ON s.id=a.service_id\n      AND s.tenant_id=a.tenant_id\n     WHERE ac.contractor_id=?\n     ORDER BY a.starts_at DESC\n     LIMIT 30"\n);\n\n$q->execute([\n    $tid,\n    (int)$contractor[\'id\']\n]);\n\n$appointments=$q->fetchAll();\n\n?>\n\n<h1>Contractor Dashboard</h1>\n\n<p class="muted">\nWelcome,\n<?=htmlspecialchars($user[\'first_name\'])?>.\n</p>\n\n<div class="grid">\n\n<div class="card">\n<div class="muted">Assigned Appointments</div>\n<div class="metric"><?=count($appointments)?></div>\n</div>\n\n<div class="card">\n<div class="muted">Business</div>\n<div style="font-size:20px;font-weight:700">\n<?=htmlspecialchars(\n    $contractor[\'business_name\']\n    ?: $contractor[\'first_name\'].\' \'.$contractor[\'last_name\']\n)?>\n</div>\n</div>\n\n</div>\n\n<div class="card" style="margin-top:18px">\n\n<h2>My Schedule</h2>\n\n<?php if(!$appointments): ?>\n\n<p class="muted">No appointments assigned yet.</p>\n\n<?php else: ?>\n\n<div style="overflow-x:auto">\n\n<table>\n\n<thead>\n<tr>\n<th>Service</th>\n<th>Appointment</th>\n<th>Client</th>\n<th>Start</th>\n<th>End</th>\n<th>Assignment</th>\n</tr>\n</thead>\n\n<tbody>\n\n<?php foreach($appointments as $a): ?>\n\n<tr>\n\n<td>\n<?=htmlspecialchars($a[\'service_name\'] ?? \'\')?>\n</td>\n\n<td>\n<?=htmlspecialchars($a[\'title\'] ?? \'\')?>\n</td>\n\n<td>\n<?=htmlspecialchars(trim(\n    ($a[\'client_first_name\'] ?? \'\') .\n    \' \' .\n    ($a[\'client_last_name\'] ?? \'\')\n))?>\n</td>\n\n<td>\n<?=htmlspecialchars($a[\'starts_at\'])?>\n</td>\n\n<td>\n<?=htmlspecialchars($a[\'ends_at\'])?>\n</td>\n\n<td>\n<?=htmlspecialchars($a[\'assignment_status\'])?>\n</td>\n\n</tr>\n\n<?php endforeach; ?>\n\n</tbody>\n\n</table>\n\n</div>\n\n<?php endif; ?>\n\n</div>\n\n<?php require __DIR__.\'/partials/app_footer.php\'; ?>\n'

FILES['migrations/003_invoice_appointment_unique.sql'] = 'ALTER TABLE invoices\nADD UNIQUE KEY uq_invoice_appointment (\n    tenant_id,\n    appointment_id\n);\n'

FILES['migrations/004_tenant_business_profile.sql'] = 'ALTER TABLE tenants\n    ADD COLUMN business_email VARCHAR(190) NULL AFTER currency,\n    ADD COLUMN business_phone VARCHAR(40) NULL AFTER business_email,\n    ADD COLUMN website VARCHAR(190) NULL AFTER business_phone,\n    ADD COLUMN address1 VARCHAR(190) NULL AFTER website,\n    ADD COLUMN address2 VARCHAR(190) NULL AFTER address1,\n    ADD COLUMN city VARCHAR(100) NULL AFTER address2,\n    ADD COLUMN state VARCHAR(100) NULL AFTER city,\n    ADD COLUMN postal_code VARCHAR(30) NULL AFTER state,\n    ADD COLUMN invoice_footer TEXT NULL AFTER postal_code,\n    ADD COLUMN payment_instructions TEXT NULL AFTER invoice_footer;\n'

# END LIVE-SYNC OVERRIDES

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
            if not migrate(env):
                sys.exit("Migration failed.")

    print("\nDONE.")
    print("1) Review: git diff --stat && git diff")
    print("2) Configure .env (never commit it)")
    print("3) Run migrations: python3 brite_complete_wizard.py --migrate --yes")
    print("4) Create owner with setup/create_admin.php")
    print("5) Activate v2 pages after review: python3 brite_complete_wizard.py --activate --yes")
    print("6) Test locally, then git add/commit/push.")

if __name__=="__main__":
    main()
