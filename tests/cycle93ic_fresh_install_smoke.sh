#!/usr/bin/env bash
# Cycle 93I-C: focused enrollment clean-install schema smoke on a NEW disposable DB.
set -euo pipefail
cd "$(dirname "$0")/.."
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
[[ "${DB_USER:-}" == root ]] || { echo 'REFUSED: DB_USER=root required for disposable local verification'; exit 2; }
[[ "${DB_HOST:-127.0.0.1}" == 127.0.0.1 || "${DB_HOST:-}" == localhost ]] || { echo 'REFUSED: local database only'; exit 2; }
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
[[ "$DB_PORT" =~ ^[0-9]+$ ]] || exit 2
[[ "${DB_NAME:-}" != britescheduler ]] || { echo 'REFUSED: production DB_NAME'; exit 2; }
DB_NAME="brite_cycle93ic_test_$(date +%Y%m%d_%H%M%S)_$RANDOM"
[[ "$DB_NAME" =~ ^brite_cycle93ic_test_[0-9_]+$ ]] || exit 2
ARGS=(-u root -h "$DB_HOST" -P "$DB_PORT")
[[ -z "${DB_PASS:-}" ]] || ARGS+=("-p$DB_PASS")
mysql(){ "$MYSQL_BIN" "${ARGS[@]}" "$@"; }
created=0
cleanup(){
  if [[ "$created" == 1 ]]; then
    if [[ "${KEEP_DISPOSABLE_DB:-0}" == 1 ]]; then
      echo "Retained disposable database: $DB_NAME"
    else
      mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`" || echo 'WARNING: failed to remove disposable database' >&2
    fi
  fi
}
trap cleanup EXIT
echo "=== CYCLE 93I-C FRESH ENROLLMENT SCHEMA ==="
echo "Creating disposable database: $DB_NAME"
mysql -e "CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
created=1
for migration in 001_core 014_user_preferences 026_tenant_modules 027_module_role_permissions 039_enrollment_management 040_enrollment_invitations; do
  echo "Applying $migration"
  mysql "$DB_NAME" < "migrations/$migration.sql"
done
for table in tenants users tenant_memberships clients contractors audit_log user_preferences tenant_modules tenant_module_roles enrollment_applications enrollment_invitations; do
  count="$(mysql "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='$table'")"
  [[ "$count" == 1 ]] || { echo "FAIL missing $table"; exit 1; }
  echo "PASS table $table"
done
mysql "$DB_NAME" <<'SQL'
INSERT INTO tenants (name,slug) VALUES ('Enrollment Clean Install','enrollment-clean-install');
INSERT INTO users (email,password_hash,first_name,last_name) VALUES ('owner93ic@example.invalid','test-not-for-login','Clean','Owner');
INSERT INTO tenant_memberships (tenant_id,user_id,role,status) VALUES (1,1,'owner','active');
INSERT INTO enrollment_applications (tenant_id,applicant_type,full_name,email,submitted_by)
VALUES (1,'contractor','Test Contractor','contractor93ic@example.invalid',1);
UPDATE enrollment_applications SET status='approved',reviewed_by=1,reviewed_at=UTC_TIMESTAMP() WHERE id=1;
INSERT INTO enrollment_invitations (tenant_id,application_id,token_hash,expires_at,created_by)
VALUES (1,1,REPEAT('a',64),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 48 HOUR),1);
SQL
[[ "$(mysql "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM enrollment_invitations i JOIN enrollment_applications a ON a.id=i.application_id AND a.tenant_id=i.tenant_id WHERE a.status='approved'")" == 1 ]]
echo 'PASS application approval and invitation foreign keys'
[[ "$(mysql "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM information_schema.key_column_usage WHERE table_schema='$DB_NAME' AND table_name IN ('enrollment_applications','enrollment_invitations') AND referenced_table_name IS NOT NULL")" -ge 5 ]]
echo 'PASS enrollment foreign keys present'
echo 'PASS focused fresh enrollment schema smoke'
