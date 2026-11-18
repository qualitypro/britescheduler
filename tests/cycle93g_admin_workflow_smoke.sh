#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
DB_NAME="${DB_NAME:-}"
[[ "$DB_NAME" =~ ^brite_cycle93e_test_[a-z0-9_]+$ ]] || { echo "REFUSED: disposable DB required" >&2; exit 2; }
[[ "${DB_USER:-}" == root ]] || { echo "REFUSED: test requires DB_USER=root" >&2; exit 2; }
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
ARGS=(-u root -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}")
[[ -z "${DB_PASS:-}" ]] || ARGS+=("-p$DB_PASS")
sql(){ "$MYSQL_BIN" "${ARGS[@]}" "$DB_NAME" -N -B -e "$1"; }
export DB_NAME DB_USER DB_PASS="${DB_PASS:-}" DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-3306}"
sql 'SELECT id FROM enrollment_applications LIMIT 1' >/dev/null
if [[ "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='user_preferences'")" == 0 ]]; then
  echo "Preparing disposable UI dependency: migration 014"
  "$MYSQL_BIN" "${ARGS[@]}" "$DB_NAME" < migrations/014_user_preferences.sql
fi
STAMP="$(date +%s)_$$"
sql "INSERT INTO users (email,password_hash,first_name,last_name) VALUES
('scheduler_$STAMP@example.invalid','test','Test','Scheduler'),
('client_$STAMP@example.invalid','test','Test','Client'),
('other_$STAMP@example.invalid','test','Test','Other');
INSERT INTO tenant_memberships (tenant_id,user_id,role) SELECT 1,id,'scheduler' FROM users WHERE email='scheduler_$STAMP@example.invalid';
INSERT INTO tenant_memberships (tenant_id,user_id,role) SELECT 1,id,'client' FROM users WHERE email='client_$STAMP@example.invalid';
INSERT INTO tenant_memberships (tenant_id,user_id,role) SELECT 2,id,'owner' FROM users WHERE email='other_$STAMP@example.invalid';"
SCHED="$(sql "SELECT id FROM users WHERE email='scheduler_$STAMP@example.invalid'")"
CLIENT="$(sql "SELECT id FROM users WHERE email='client_$STAMP@example.invalid'")"
OTHER="$(sql "SELECT id FROM users WHERE email='other_$STAMP@example.invalid'")"
OWNER="$(sql "SELECT user_id FROM tenant_memberships WHERE tenant_id=1 AND role='owner' LIMIT 1")"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
request(){
  if ! "$PHP_BIN" tests/cycle93g_enrollment_request_driver.php "$@" >"$TMP/response" 2>"$TMP/error"; then
    echo "FAIL request: method=$3 action=$4" >&2
    tail -35 "$TMP/error" >&2
    exit 1
  fi
}
assert(){ grep -q "$1" "$TMP/response" || { echo "FAIL $2"; tail -25 "$TMP/error"; exit 1; }; echo "PASS $2"; }
request "$CLIENT" 1 GET none
assert 'Access denied' 'client blocked from enrollment administration'
request "$SCHED" 1 GET none
assert 'Enrollment Management' 'scheduler can view enrollment'
request "$SCHED" 1 POST submit
assert 'Application submitted for review' 'scheduler can submit application'
APP="$(sql "SELECT id FROM enrollment_applications WHERE submitted_by=$SCHED ORDER BY id DESC LIMIT 1")"
[[ "$APP" =~ ^[0-9]+$ ]] || { echo 'FAIL application missing'; exit 1; }
request "$SCHED" 1 POST review "$APP" approved
assert 'Action not permitted' 'scheduler cannot approve'
request "$OTHER" 2 POST review "$APP" approved
assert 'Application already reviewed or unavailable' 'other tenant cannot approve'
request "$OWNER" 1 POST review "$APP" approved
assert 'Application marked approved' 'owner can approve'
[[ "$(sql "SELECT status FROM enrollment_applications WHERE id=$APP")" == approved ]] || exit 1
request "$OWNER" 1 POST invite "$APP"
assert 'One-time invitation link' 'owner can issue invitation'
request "$OWNER" 1 POST revoke "$APP"
assert 'Active invitation revoked' 'owner can revoke invitation'
[[ "$(sql "SELECT COUNT(*) FROM audit_log WHERE entity_id=$APP AND action IN ('enrollment.review','enrollment.invite','enrollment.revoke')")" == 3 ]] || { echo 'FAIL audit actions'; exit 1; }
echo 'PASS audit trail recorded'
echo 'PASS 93G authenticated CLI request smoke'
