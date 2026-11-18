#!/usr/bin/env bash
# Cycle 93H: isolated negative-path checks, no production writes.
set -euo pipefail
cd "$(dirname "$0")/.."
[[ "${DB_NAME:-}" =~ ^brite_cycle93e_test_[a-z0-9_]+$ ]] || { echo 'REFUSED: disposable DB required'; exit 2; }
[[ "${DB_USER:-}" == root ]] || { echo 'REFUSED: DB_USER=root required'; exit 2; }
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
ARGS=(-u root -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}")
[[ -z "${DB_PASS:-}" ]] || ARGS+=("-p$DB_PASS")
sql(){ "$MYSQL_BIN" "${ARGS[@]}" "$DB_NAME" -N -B -e "$1"; }
export DB_NAME DB_USER DB_PASS="${DB_PASS:-}" DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-3306}"
sql 'SELECT id FROM enrollment_invitations LIMIT 1' >/dev/null
OWNER="$(sql "SELECT user_id FROM tenant_memberships WHERE tenant_id=1 AND role='owner' AND status='active' LIMIT 1")"
[[ "$OWNER" =~ ^[0-9]+$ ]] || { echo 'FAIL missing test owner'; exit 1; }
STAMP="$(date +%s)_$$"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
request(){
  if ! "$PHP_BIN" tests/cycle93g_enrollment_request_driver.php "$@" >"$TMP/out" 2>"$TMP/err"; then
    echo "FAIL request method=$3 action=$4" >&2
    tail -35 "$TMP/err" >&2
    exit 1
  fi
}
check(){ grep -q "$1" "$TMP/out" || { echo "FAIL $2"; tail -25 "$TMP/err"; exit 1; }; echo "PASS $2"; }
sql "INSERT INTO enrollment_applications (tenant_id,applicant_type,full_name,email,status,submitted_by)
VALUES (1,'contractor','93H Rejection','reject_$STAMP@example.invalid','pending',$OWNER),
(1,'contractor','93H Expired','expired_$STAMP@example.invalid','approved',$OWNER);"
REJECT="$(sql "SELECT id FROM enrollment_applications WHERE email='reject_$STAMP@example.invalid'")"
EXPIRED="$(sql "SELECT id FROM enrollment_applications WHERE email='expired_$STAMP@example.invalid'")"
request "$OWNER" 1 POST review "$REJECT" rejected
check 'Application marked rejected' 'owner can reject contractor application'
request "$OWNER" 1 POST review "$REJECT" approved
check 'Application already reviewed or unavailable' 'rejected application cannot be reapproved'
request "$OWNER" 1 POST invite "$REJECT"
check 'Only approved applications can be invited' 'rejected application cannot be invited'
CYCLE93_BAD_CSRF=1 request "$OWNER" 1 POST review "$EXPIRED" rejected
check 'Invalid request token' 'invalid CSRF rejected'
[[ "$(sql "SELECT status FROM enrollment_applications WHERE id=$EXPIRED")" == approved ]] || { echo 'FAIL CSRF mutated status'; exit 1; }
echo 'PASS invalid CSRF left application unchanged'
sql "INSERT INTO enrollment_invitations (tenant_id,application_id,token_hash,expires_at,created_by)
VALUES (1,$EXPIRED,SHA2('expired_$STAMP',256),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),$OWNER);"
request "$OWNER" 1 POST revoke "$EXPIRED"
check 'No active invitation to revoke' 'expired invitation cannot be revoked as active'
request "$OWNER" 1 POST invite "$EXPIRED"
check 'One-time invitation link' 'expired invitation can be replaced'
[[ "$(sql "SELECT COUNT(*) FROM enrollment_invitations WHERE application_id=$EXPIRED AND revoked_at IS NULL AND accepted_at IS NULL AND expires_at>UTC_TIMESTAMP()")" == 1 ]] || { echo 'FAIL active invitation count'; exit 1; }
echo 'PASS exactly one active invitation after replacement'
request "$OWNER" 1 POST invite "$EXPIRED"
check 'One-time invitation link' 'reissuing invitation succeeds'
[[ "$(sql "SELECT COUNT(*) FROM enrollment_invitations WHERE application_id=$EXPIRED AND revoked_at IS NULL AND accepted_at IS NULL AND expires_at>UTC_TIMESTAMP()")" == 1 ]] || { echo 'FAIL duplicate active invitations'; exit 1; }
echo 'PASS reissue revokes prior invitation'
echo 'PASS 93H negative-path regression suite'
