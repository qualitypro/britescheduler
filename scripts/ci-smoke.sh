#!/usr/bin/env bash
# Brite Scheduler: dependency-free source smoke checks.
# Run from any directory: bash scripts/ci-smoke.sh
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
PHP_BIN="${PHP_BIN:-php}"
NODE_BIN="${NODE_BIN:-node}"
failures=0
checked=0
check() {
  local kind="$1" path="$2"
  checked=$((checked+1))
  if [[ "$kind" == php ]]; then
    if "$PHP_BIN" -l "$path"; then :; else failures=$((failures+1)); fi
  else
    if "$NODE_BIN" --check "$path"; then echo "PASS JS: $path"; else failures=$((failures+1)); fi
  fi
}
command -v "$PHP_BIN" >/dev/null || { echo "ERROR: PHP CLI unavailable: $PHP_BIN"; exit 2; }
command -v "$NODE_BIN" >/dev/null || { echo "ERROR: Node.js unavailable: $NODE_BIN"; exit 2; }
# Limit to maintained entry points and shared frontend scripts; this does not
# execute endpoints, connect to a database, or imply security test coverage.
php_files=(
  app/bootstrap.php app/DispatchRequest.php
  contractor-profile.php api/my_contractor_profile.php api/marketplace_contractor_profile.php tests/dispatch_request_test.php
  partials/app_header.php partials/app_footer.php
  scheduling.php contractors.v2.php contractor-service-areas.php
  trends.php activity-hub.php
  api/trends.php api/user_notifications.php api/user_notification_read.php
  api/contractor_recommendations.php api/contractor_marketplace.php
  api/marketplace_client_listings.php api/client_locations.php
  app/ServerCalendar.php app/ServerPagination.php api/calendar_range.php
  api/clients.php api/contractors.php api/appointments.php
  api/client_quote_requests.php api/contractor_quote_inbox.php
  modules/crm/page.php modules/crm/list_pager.php bidding.php
  client-quote-lookup.php contractor-quote-inbox.php
  tests/cycle90_foundation_test.php tests/cycle90_pagination_boundaries.php
  tests/cycle90_integration_guards.php
)
js_files=(
  assets/js/contractor-profile.js assets/js/brite-app-shell.js assets/js/brite-activity-hub.js
  assets/js/brite-tooltips.js assets/js/brite-value-pills.js
  assets/js/brite-unified-lists.js assets/js/brite-pagination.js
  assets/js/brite-directory-accordion.js assets/js/client-quote-lookup.js
  assets/js/contractor-quote-inbox.js
)
echo "=== Brite Scheduler source smoke checks ==="
for path in "${php_files[@]}"; do
  if [[ -f "$path" ]]; then check php "$path"; else echo "FAIL missing PHP: $path"; failures=$((failures+1)); fi
done
for path in "${js_files[@]}"; do
  if [[ -f "$path" ]]; then check js "$path"; else echo "FAIL missing JS: $path"; failures=$((failures+1)); fi
done
echo "=== Smart Dispatch unit tests ==="
if "$PHP_BIN" tests/dispatch_request_test.php; then echo "PASS: dispatch request tests"; else failures=$((failures+1)); fi
echo "=== Cycle 90 regression suites ==="
for test in tests/cycle90_foundation_test.php tests/cycle90_pagination_boundaries.php tests/cycle90_integration_guards.php; do
  if "$PHP_BIN" "$test"; then echo "PASS: $test"; else echo "FAIL: $test"; failures=$((failures+1)); fi
done
echo "=== Result: $checked source files checked; $failures failures ==="
[[ "$failures" -eq 0 ]]
