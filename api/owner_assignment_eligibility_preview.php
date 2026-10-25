<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ContractorAssignmentValidator.php';

Auth::requireUser();
$tenant = Auth::requireRole('owner');
require_method('GET');

$tenantId = (int)$tenant['tenant_id'];
$positive = static function ($value): int {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int)$id;
};
$appointmentId = $positive($_GET['appointment_id'] ?? null);
$declinedId = $positive($_GET['declined_contractor_id'] ?? null);
$replacementId = $positive($_GET['replacement_contractor_id'] ?? null);

if (!$appointmentId || !$declinedId || !$replacementId || $declinedId === $replacementId) {
    json_response(['error' => 'Invalid reassignment preview request'], 422);
}

$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $q = $pdo->prepare("
        SELECT id, status, starts_at, service_id, client_location_id
        FROM appointments WHERE id = ? AND tenant_id = ? LIMIT 1
    ");
    $q->execute([$appointmentId, $tenantId]);
    $appointment = $q->fetch(PDO::FETCH_ASSOC);
    if (!$appointment) {
        json_response(['error' => 'Appointment not found'], 404);
    }

    if (!in_array($appointment['status'], ['tentative', 'scheduled', 'confirmed'], true)
        || (string)$appointment['starts_at'] <= date('Y-m-d H:i:s')) {
        json_response(['error' => 'Appointment is not eligible for reassignment'], 409);
    }

    $q = $pdo->prepare("SELECT id FROM appointment_bid_listings WHERE tenant_id = ? AND appointment_id = ? LIMIT 1");
    $q->execute([$tenantId, $appointmentId]);
    if ($q->fetchColumn()) {
        json_response(['error' => 'Marketplace appointments cannot be reassigned here'], 409);
    }

    $q = $pdo->prepare("SELECT contractor_id, status FROM appointment_contractors WHERE appointment_id = ?");
    $q->execute([$appointmentId]);
    $declined = false;
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $assignment) {
        if ((int)$assignment['contractor_id'] === $replacementId) {
            json_response(['error' => 'Replacement contractor is already assigned'], 409);
        }
        if ((int)$assignment['contractor_id'] === $declinedId && $assignment['status'] === 'declined') {
            $declined = true;
        }
    }
    if (!$declined) {
        json_response(['error' => 'Declined assignment not found'], 409);
    }

    $q = $pdo->prepare("
        SELECT id FROM audit_log
        WHERE tenant_id = ? AND entity_type = 'appointment_assignment'
          AND entity_id = ? AND action = 'assignment.reassigned'
          AND JSON_VALID(metadata)
          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.declined_contractor_id')) = ?
        LIMIT 1
    ");
    $q->execute([$tenantId, $appointmentId, (string)$declinedId]);
    if ($q->fetchColumn()) {
        json_response(['error' => 'This declined assignment has already been reassigned'], 409);
    }

    $q = $pdo->prepare("
        SELECT c.id FROM contractors c
        JOIN users u ON u.id = c.user_id AND u.status = 'active'
        JOIN tenant_memberships tm ON tm.tenant_id = c.tenant_id
            AND tm.user_id = c.user_id AND tm.role = 'contractor' AND tm.status = 'active'
        WHERE c.tenant_id = ? AND c.id = ? AND c.status = 'active'
        LIMIT 1
    ");
    $q->execute([$tenantId, $replacementId]);
    if (!$q->fetchColumn()) {
        json_response(['error' => 'Active replacement contractor required'], 422);
    }

    $eligibility = ContractorAssignmentValidator::evaluate(
        $pdo, $tenantId, $replacementId,
        $appointment['service_id'] === null ? null : (int)$appointment['service_id'],
        $appointment['client_location_id'] === null ? null : (int)$appointment['client_location_id']
    );

    json_response([
        'ok' => true,
        'allowed' => (bool)$eligibility['allowed'],
        'qualification_status' => $eligibility['qualification_status'],
        'geographic_status' => $eligibility['geographic_status'],
        'warnings' => $eligibility['warnings'],
        'errors' => $eligibility['errors'],
        'advisory_only' => true
    ]);
} catch (Throwable $e) {
    error_log('Owner reassignment preview: ' . $e->getMessage());
    json_response(['error' => 'Unable to evaluate replacement eligibility'], 500);
}
