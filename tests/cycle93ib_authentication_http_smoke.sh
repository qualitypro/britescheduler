#!/usr/bin/env bash
# Isolated HTTP authentication checks for previously activated enrollment accounts.
set -euo pipefail
cd "$(dirname "$0")/.."
[[ "${DB_NAME:-}" =~ ^brite_cycle93e_test_[a-z0-9_]+$ ]] || { echo 'REFUSED: disposable DB_NAME required'; exit 2; }
[[ "${DB_USER:-}" == root ]] || { echo 'REFUSED: DB_USER=root required'; exit 2; }
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
PORT="${PORT:-18795}"
[[ "$PORT" =~ ^[0-9]+$ ]] || exit 2
ARGS=(-u root -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}")
[[ -z "${DB_PASS:-}" ]] || ARGS+=("-p$DB_PASS")
sql(){ "$MYSQL_BIN" "${ARGS[@]}" "$DB_NAME" -N -B -e "$1"; }
export DB_NAME DB_USER DB_PASS="${DB_PASS:-}" DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-3306}"
TMP="$(mktemp -d)"
cleanup(){ [[ -z "${SERVER_PID:-}" ]] || kill "$SERVER_PID" 2>/dev/null || true; rm -rf "$TMP"; }
trap cleanup EXIT
trap 'echo "FAIL 93I-B line $LINENO" >&2; tail -30 "$TMP/server.log" >&2' ERR
sql 'SELECT id FROM enrollment_applications LIMIT 1' >/dev/null
# Credentials are fixed, disposable-test-only passwords from the prior HTTP activation runs.
CONTRACTOR="$(sql "SELECT u.email FROM users u JOIN tenant_memberships m ON m.user_id=u.id WHERE m.role='contractor' AND m.tenant_id=1 AND u.email LIKE 'http93i_%@example.invalid' ORDER BY u.id DESC LIMIT 1")"
CLIENT="$(sql "SELECT u.email FROM users u JOIN tenant_memberships m ON m.user_id=u.id WHERE m.role='client' AND m.tenant_id=1 AND u.email LIKE 'http93f_%@example.invalid' ORDER BY u.id DESC LIMIT 1")"
[[ -n "$CONTRACTOR" && -n "$CLIENT" ]] || { echo 'FAIL missing previously activated 93F client or 93I contractor; run those smoke tests first'; exit 1; }
"$PHP_BIN" -S "127.0.0.1:$PORT" -t . >"$TMP/server.log" 2>&1 &
SERVER_PID=$!
BASE="http://127.0.0.1:$PORT"
for attempt in {1..15}; do
  if curl -fsS "$BASE/sign-in.v2.php" -c "$TMP/probe" -o "$TMP/page" 2>/dev/null; then break; fi
  sleep 1
done
grep -q 'X-CSRF-Token' "$TMP/page"
csrf(){ sed -n 's/.*X-CSRF-Token.*:"\([a-f0-9]\{64\}\)".*/\1/p' "$1" | head -1; }
login(){
  local role="$1" email="$2" password="$3" expected="$4"
  local cookie="$TMP/$role.cookie" token
  curl -fsS "$BASE/sign-in.v2.php" -c "$cookie" -o "$TMP/$role.page"
  token="$(csrf "$TMP/$role.page")"
  [[ "$token" =~ ^[a-f0-9]{64}$ ]] || { echo "FAIL $role CSRF extraction"; exit 1; }
  local status
  status="$(curl -sS -o "$TMP/$role.login" -w '%{http_code}' -b "$cookie" -c "$cookie" \
    -H 'Content-Type: application/json' -H "X-CSRF-Token: $token" \
    --data "{\"email\":\"$email\",\"password\":\"$password\"}" "$BASE/api/auth/login.php")"
  [[ "$status" == "$expected" ]] || { echo "FAIL $role login HTTP $status expected $expected"; cat "$TMP/$role.login"; exit 1; }
}
login contractor "$CONTRACTOR" 'incorrect-password' 422
grep -q 'Invalid email or password' "$TMP/contractor.login"
echo 'PASS incorrect contractor password rejected'
login contractor "$CONTRACTOR" 'cycle93i-local-test-password' 200
grep -q 'contractor-dashboard.php' "$TMP/contractor.login"
echo 'PASS contractor login and dashboard redirect'
status="$(curl -sS -o "$TMP/contractor.denied" -w '%{http_code}' -b "$TMP/contractor.cookie" "$BASE/enrollment.php")"
[[ "$status" == 403 ]] || { echo "FAIL contractor administration restriction HTTP $status"; exit 1; }
echo 'PASS authenticated contractor denied enrollment administration'
login client "$CLIENT" 'cycle93f-local-test-password' 200
grep -q 'client-dashboard.php' "$TMP/client.login"
echo 'PASS client login and dashboard redirect'
status="$(curl -sS -o "$TMP/client.denied" -w '%{http_code}' -b "$TMP/client.cookie" "$BASE/enrollment.php")"
[[ "$status" == 403 ]] || { echo "FAIL client administration restriction HTTP $status"; exit 1; }
echo 'PASS authenticated client denied enrollment administration'
echo '=== DASHBOARD RENDERING ==='
for role in contractor client; do
  status="$(curl -sS -o "$TMP/$role.dashboard" -w '%{http_code}' -b "$TMP/$role.cookie" "$BASE/$role-dashboard.php")"
  [[ "$status" == 200 ]] || { echo "FAIL $role dashboard HTTP $status"; tail -30 "$TMP/server.log"; exit 1; }
  grep -qi "$role Dashboard" "$TMP/$role.dashboard" || { echo "FAIL $role dashboard heading missing"; exit 1; }
  if grep -Eqi 'Fatal error|Uncaught PDOException|Warning:.*(include|require)' "$TMP/$role.dashboard"; then
    echo "FAIL $role dashboard PHP runtime error"; exit 1
  fi
  echo "PASS authenticated $role dashboard renders"
done
echo 'PASS 93I-B HTTP authentication and dashboard smoke'
