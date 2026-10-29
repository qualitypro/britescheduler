#!/usr/bin/env bash
# Cycle 70 non-destructive source verification. Captures complete output and clipboard.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LOG="/tmp/brite-cycle70-$(date +%Y%m%d-%H%M%S).log"
(
  set -e
  cd "$ROOT"
  PHP_BIN="/opt/lampp/bin/php"
  if [[ ! -x "$PHP_BIN" ]]; then PHP_BIN="$(command -v php)"; fi
  echo '=== Cycle 70 PHP syntax ==='
  "$PHP_BIN" -l scheduling.php
  "$PHP_BIN" -l api/contractor_recommendations.php
  "$PHP_BIN" -l api/appointments.php
  echo '=== Cycle 70 source assertions ==='
  grep -Fq 'function cycle70DispatchSnapshot()' scheduling.php
  grep -Fq 'function cycle70DispatchUnchanged(snapshot)' scheduling.php
  grep -Fq 'cycle70DispatchUnchanged(cycle70PreReview)' scheduling.php
  grep -Fq 'confirmed && cycle70DispatchUnchanged(snapshot)' scheduling.php
  grep -Fq "'ok' => true" api/contractor_recommendations.php
  grep -Fq "'automatic_assignment' => false" api/contractor_recommendations.php
  grep -Fq 'lock_assignment_contractors(' api/appointments.php
  echo '=== Diff hygiene ==='
  git diff --check HEAD
  git status --short
  echo 'PASS: Cycle 70 source checks (not browser or API runtime QA)'
) > "$LOG" 2>&1
RESULT=$?
cat "$LOG"
if command -v wl-copy >/dev/null 2>&1; then
  wl-copy < "$LOG"
  echo 'Copied complete report to Wayland clipboard.'
elif command -v xclip >/dev/null 2>&1; then
  xclip -selection clipboard < "$LOG"
  echo 'Copied complete report to X11 clipboard.'
else
  echo 'Clipboard unavailable; report saved on disk.'
fi
echo "Report: $LOG"
echo "Exit status: $RESULT"
exit "$RESULT"
