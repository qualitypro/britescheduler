#!/usr/bin/env bash
# Cycle 95B: tenant-scoped search/filter behavior against an existing disposable Cycle 93E fixture.
set -euo pipefail
cd "$(dirname "$0")/.."
[[ "${DB_NAME:-}" =~ ^brite_cycle93e_test_[a-z0-9_]+$ ]] || { echo 'REFUSED: disposable DB_NAME required'; exit 2; }
[[ "${DB_USER:-}" == root ]] || { echo 'REFUSED: DB_USER=root required'; exit 2; }
[[ "${DB_HOST:-127.0.0.1}" == 127.0.0.1 || "${DB_HOST:-}" == localhost ]] || { echo 'REFUSED: local DB_HOST required'; exit 2; }
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
ARGS=(-u root -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}")
[[ -z "${DB_PASS:-}" ]] || ARGS+=("-p$DB_PASS")
sql(){ "$MYSQL_BIN" "${ARGS[@]}" "$DB_NAME" -N -B -e "$1"; }
OWNER="$(sql "SELECT user_id FROM tenant_memberships WHERE tenant_id=1 AND role='owner' AND status='active' LIMIT 1")"
[[ "$OWNER" =~ ^[0-9]+$ ]] || { echo 'FAIL missing disposable tenant owner'; exit 1; }
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
STAMP="cycle95b_$$_$(date +%s)"
EMAIL="$STAMP@example.invalid"
sql "INSERT INTO enrollment_applications (tenant_id,applicant_type,full_name,email,submitted_by) VALUES (1,'client','Cycle95 Unique Search','$EMAIL',$OWNER);"
APP="$(sql "SELECT id FROM enrollment_applications WHERE tenant_id=1 AND email='$EMAIL' ORDER BY id DESC LIMIT 1")"
[[ "$APP" =~ ^[0-9]+$ ]] || exit 1
request(){ CYCLE95_QUERY="$1" "$PHP_BIN" tests/cycle93g_enrollment_request_driver.php "$2" "$3" GET none > "$TMP/out"; }
assert_has(){ grep -Fq "$1" "$TMP/out" || { echo "FAIL $2"; exit 1; }; echo "PASS $2"; }
assert_lacks(){ if grep -Fq "$1" "$TMP/out"; then echo "FAIL $2"; exit 1; fi; echo "PASS $2"; }
request "q=$STAMP" "$OWNER" 1
assert_has "$EMAIL" 'name/email search returns tenant record'
request "q=cycle95b_no_match_$$_" "$OWNER" 1
assert_lacks "$EMAIL" 'nonmatching search hides record'
request "q=$STAMP&invitation=not_sent" "$OWNER" 1
assert_has "$EMAIL" 'not-sent invitation filter includes uninvited application'
request "q=$STAMP&invitation=invited" "$OWNER" 1
assert_lacks "$EMAIL" 'invited filter excludes uninvited application'
request "q=$STAMP&type=contractor" "$OWNER" 1
assert_lacks "$EMAIL" 'type filter excludes client'
request "q=$STAMP&status=approved" "$OWNER" 1
assert_lacks "$EMAIL" 'status filter excludes pending application'
request "q=$STAMP" "$OWNER" 2
assert_lacks "$EMAIL" 'cross-tenant search does not leak application'
echo 'PASS Cycle 95B search and invitation filter smoke'
