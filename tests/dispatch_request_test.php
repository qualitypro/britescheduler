<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/DispatchRequest.php';

$failures = 0;
$checks = 0;
function expectDispatch(bool $condition, string $description): void {
    global $failures, $checks;
    ++$checks;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $description . PHP_EOL;
    if (!$condition) ++$failures;
}
foreach ([1, '12', '999'] as $id) {
    expectDispatch(DispatchRequest::positiveId($id) !== null, 'valid positive ID ' . $id);
}
foreach ([0, -1, '0', '-4', '1.5', 'abc', '', [], null, 1.5] as $id) {
    expectDispatch(DispatchRequest::positiveId($id) === null, 'reject invalid ID ' . get_debug_type($id));
}
expectDispatch(DispatchRequest::localDatetime('2026-11-03T09:30') === '2026-11-03 09:30:00', 'HTML datetime-local input');
expectDispatch(DispatchRequest::localDatetime('2026-11-03T09:30:45') === '2026-11-03 09:30:45', 'ISO local datetime with seconds');
expectDispatch(DispatchRequest::localDatetime('2026-11-03 09:30:00') === '2026-11-03 09:30:00', 'SQL datetime');
foreach (['2026-02-30T09:30', '2026-11-03T25:00', '2026-11-03T09:30Z', '2026-11-03', 'tomorrow'] as $bad) {
    expectDispatch(DispatchRequest::localDatetime($bad) === null, 'reject malformed datetime ' . $bad);
}
expectDispatch(DispatchRequest::validInterval('2026-11-03 09:00:00', '2026-11-03 10:00:00'), 'valid same-day interval');
expectDispatch(!DispatchRequest::validInterval('2026-11-03 10:00:00', '2026-11-03 10:00:00'), 'reject zero duration');
expectDispatch(!DispatchRequest::validInterval('2026-11-03 11:00:00', '2026-11-03 10:00:00'), 'reject reversed interval');
expectDispatch(!DispatchRequest::validInterval('2026-11-03 23:30:00', '2026-11-04 00:30:00'), 'reject cross-midnight interval');
expectDispatch(!DispatchRequest::validInterval(null, '2026-11-03 10:00:00'), 'reject missing start');
echo "Dispatch validation: $checks checks, $failures failures" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
