#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
if [[ ! -x "$PHP_BIN" ]]; then PHP_BIN="$(command -v php)"; fi
cd "$ROOT"
for file in enrollment.php enrollment-activate.php partials/app_header.php tests/cycle93_enrollment_contract_test.php tests/cycle93c_enrollment_progress_test.php tests/cycle93d_enrollment_reliability_test.php tests/cycle93e_disposable_db_smoke.php; do
  "$PHP_BIN" -l "$file"
done
for file in tests/cycle93_enrollment_contract_test.php tests/cycle93c_enrollment_progress_test.php tests/cycle93d_enrollment_reliability_test.php; do
  "$PHP_BIN" "$file"
done
if [[ "${RUN_DISPOSABLE_DB_TEST:-0}" == 1 ]]; then
  "$PHP_BIN" tests/cycle93e_disposable_db_smoke.php
else
  echo "SKIP disposable MariaDB test: set RUN_DISPOSABLE_DB_TEST=1 and DB_NAME=brite_cycle93e_test_<suffix> for a NEW EMPTY database."
fi
