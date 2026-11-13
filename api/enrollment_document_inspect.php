<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Registration/DocumentVault.php';
require_once dirname(__DIR__) . '/app/Registration/DocumentInspectionService.php';

use BriteScheduler\Registration\DocumentInspectionService;

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
require_method('POST');
$user = Auth::requireUser();
$tenant = Auth::requireRole('owner', 'admin');
verify_csrf();
$id = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) json_response(['error' => 'Invalid document'], 422);
try {
    $result = DocumentInspectionService::inspect(Database::connection(), (int)$tenant['tenant_id'], (int)$user['id'], (int)$id);
    header('Content-Type: ' . $result['mime']);
    header('Content-Disposition: attachment; filename="enrollment-document.' .
        match ($result['mime']) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        } . '"');
    header('Content-Length: ' . strlen($result['content']));
    echo $result['content'];
} catch (DomainException $error) {
    json_response(['error' => 'Document unavailable'], 404);
} catch (Throwable $error) {
    error_log('Enrollment document inspection unavailable: ' . get_class($error));
    json_response(['error' => 'Document inspection unavailable'], 503);
}
