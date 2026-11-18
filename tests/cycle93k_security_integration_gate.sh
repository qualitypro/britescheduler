#!/usr/bin/env bash
# Cycle 93K: enrollment security and integration gate.
# Uses ONLY an existing, explicitly named disposable Cycle 93E database.
set -euo pipefail
cd "$(dirname "$0")/.."
[[ "${DB_NAME:-}" =~ ^brite_cycle93e_test_[a-z0-9_]+$ ]] || { echo 'REFUSED: explicit Cycle 93E disposable DB_NAME required'; exit 2; }
[[ "${DB_USER:-}" == root ]] || { echo 'REFUSED: DB_USER=root required'; exit 2; }
[[ "${DB_HOST:-127.0.0.1}" == 127.0.0.1 || "${DB_HOST:-}" == localhost ]] || { echo 'REFUSED: local DB_HOST required'; exit 2; }
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
ARGS=(-u root -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}")
[[ -z "${DB_PASS:-}" ]] || ARGS+=("-p$DB_PASS")
sql(){ "$MYSQL_BIN" "${ARGS[@]}" "$DB_NAME" -N -B -e "$1"; }
echo '=== CYCLE 93K ENROLLMENT SECURITY GATE ==='
[[ "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='enrollment_invitations'")" == 1 ]] || { echo 'FAIL enrollment schema missing'; exit 1; }
for f in enrollment.php enrollment-activate.php tests/cycle93g_enrollment_request_driver.php; do
  "$PHP_BIN" -l "$f"
done
for f in tests/cycle93f_http_activation_smoke.sh tests/cycle93g_admin_workflow_smoke.sh tests/cycle93h_enrollment_negative_paths.sh tests/cycle93i_contractor_http_smoke.sh tests/cycle93ib_authentication_http_smoke.sh; do
  bash -n "$f"
done
echo '=== STATIC SECURITY CONTRACTS ==='
bash tests/run_cycle93e.sh
echo '=== TENANT ISOLATION, ROLE AND CSRF ==='
bash tests/cycle93g_admin_workflow_smoke.sh
echo '=== NEGATIVE PATHS AND INVITATION LIFECYCLE ==='
bash tests/cycle93h_enrollment_negative_paths.sh
echo '=== AUTHENTICATION AND BOTH DASHBOARDS ==='
bash tests/cycle93ib_authentication_http_smoke.sh
echo 'PASS Cycle 93K security integration gate'
