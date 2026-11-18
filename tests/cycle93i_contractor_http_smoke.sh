#!/usr/bin/env bash
# Isolated HTTP activation smoke test. Requires a disposable DB prepared with
# tests/cycle93e_disposable_db_smoke.php. Never point this at the live DB.
set -euo pipefail
cd "$(dirname "$0")/.."
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
DB_NAME="${DB_NAME:-}"
[[ "$DB_NAME" =~ ^brite_cycle93e_test_[a-z0-9_]+$ ]] || { echo "REFUSED: disposable DB_NAME required" >&2; exit 2; }
[[ "${DB_USER:-}" == root ]] || { echo "REFUSED: DB_USER=root required for this local test" >&2; exit 2; }
command -v curl >/dev/null
PORT="${PORT:-18793}"
[[ "$PORT" =~ ^[0-9]+$ ]] || exit 2
TMP="$(mktemp -d)"
cleanup(){ [[ -n "${SERVER_PID:-}" ]] && kill "$SERVER_PID" 2>/dev/null || true; rm -rf "$TMP"; }
trap cleanup EXIT
trap 'echo "FAIL HTTP smoke at line $LINENO (exit $?)" >&2; if [[ -f "$TMP/server.log" ]]; then tail -35 "$TMP/server.log" >&2; fi' ERR
export DB_NAME DB_USER DB_PASS="${DB_PASS:-}" DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-3306}"
MYSQL_ARGS=(-u "$DB_USER" -h "$DB_HOST" -P "$DB_PORT")
if [[ -n "$DB_PASS" ]]; then MYSQL_ARGS+=("-p$DB_PASS"); fi
echo "Using disposable database: $DB_NAME"

"$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" -e "SELECT id FROM enrollment_applications LIMIT 1" >/dev/null
TOKEN="$("$PHP_BIN" -r 'echo bin2hex(random_bytes(32));')"
HASH="$("$PHP_BIN" -r 'echo hash("sha256",$argv[1]);' "$TOKEN")"
EMAIL="http93i_${PORT}_$(date +%s)@example.invalid"
# SQL identifiers and token are constrained to known-safe values.
"$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" <<SQL
INSERT INTO enrollment_applications (tenant_id,applicant_type,full_name,email,status,submitted_by)
VALUES (1,'contractor','HTTP Test','$EMAIL','approved',1);
SET @app=LAST_INSERT_ID();
INSERT INTO enrollment_invitations (tenant_id,application_id,token_hash,expires_at,created_by)
VALUES (1,@app,'$HASH',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 48 HOUR),1);
SQL
"$PHP_BIN" -S "127.0.0.1:$PORT" -t . >"$TMP/server.log" 2>&1 &
SERVER_PID=$!
for _ in 1 2 3 4 5 6 7 8 9 10; do
  if curl -fsS "http://127.0.0.1:$PORT/enrollment-activate.php?token=$TOKEN" -c "$TMP/cookies" -o "$TMP/form"; then break; fi
  sleep 1
done
grep -q 'Activate account' "$TMP/form" || { cat "$TMP/server.log"; exit 1; }
CSRF="$(sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p' "$TMP/form" | head -1)"
[[ "$CSRF" =~ ^[a-f0-9]{64}$ ]] || { echo "FAIL missing CSRF"; exit 1; }
curl -fsS "http://127.0.0.1:$PORT/enrollment-activate.php" -b "$TMP/cookies" \
  --data-urlencode "token=$TOKEN" --data-urlencode "_csrf=$CSRF" \
  --data-urlencode 'first_name=HTTP' --data-urlencode 'last_name=Test' \
  --data-urlencode 'password=cycle93i-local-test-password' -o "$TMP/activated"
grep -q 'Your account is ready' "$TMP/activated" || { cat "$TMP/server.log"; echo "FAIL activation"; exit 1; }
echo "PASS activation over HTTP"
curl -fsS "http://127.0.0.1:$PORT/enrollment-activate.php?token=$TOKEN" -o "$TMP/reuse"
grep -q 'Invitation invalid, expired, or already used' "$TMP/reuse"
echo "PASS consumed invitation rejected"
"$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" -N -e "SELECT COUNT(*) FROM tenant_memberships m JOIN users u ON u.id=m.user_id WHERE u.email='$EMAIL' AND m.tenant_id=1 AND m.role='contractor'" | grep -qx 1
echo "PASS contractor membership created for correct tenant"
"$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" -N -e "SELECT COUNT(*) FROM contractors c JOIN users u ON u.id=c.user_id WHERE u.email='$EMAIL' AND c.tenant_id=1 AND c.status='active'" | grep -qx 1
echo "PASS contractor profile created"
"$PHP_BIN" -r 'require "app/bootstrap.php"; $q=Database::connection()->prepare("SELECT password_hash FROM users WHERE email=?"); $q->execute([$argv[1]]); $hash=$q->fetchColumn(); if(!$hash || !password_verify("cycle93i-local-test-password",$hash)) exit(1); echo "PASS contractor password verified by authentication algorithm\\n";' "$EMAIL"
echo "PASS isolated contractor activation smoke"
