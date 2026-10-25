<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::tenant();

require_method('POST');
verify_csrf();

$data = request_data();

$notificationId = filter_var(
    $data['notification_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($notificationId === false || $notificationId === null) {
    json_response([
        'error' => 'Invalid notification ID'
    ], 422);
}

$tenantId = (int)$tenant['tenant_id'];
$userId = (int)$user['id'];

$pdo = Database::connection();

try {
    $pdo->beginTransaction();

    $membership = $pdo->prepare("
        SELECT tm.role
        FROM tenant_memberships tm
        JOIN users u ON u.id = tm.user_id
        WHERE tm.tenant_id = ?
          AND tm.user_id = ?
          AND tm.status = 'active'
          AND u.status = 'active'
        LIMIT 1
    ");

    $membership->execute([$tenantId, $userId]);

    if (!$membership->fetchColumn()) {
        $pdo->rollBack();

        json_response([
            'error' => 'Active tenant membership required'
        ], 403);
    }

    $query = $pdo->prepare("
        SELECT id, read_at
        FROM notifications
        WHERE id = ?
          AND tenant_id = ?
          AND user_id = ?
        FOR UPDATE
    ");

    $query->execute([
        $notificationId,
        $tenantId,
        $userId
    ]);

    $notification = $query->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        $pdo->rollBack();

        json_response([
            'error' => 'Notification not found'
        ], 404);
    }

    $alreadyRead = $notification['read_at'] !== null;

    if (!$alreadyRead) {
        $update = $pdo->prepare("
            UPDATE notifications
            SET read_at = UTC_TIMESTAMP()
            WHERE id = ?
              AND tenant_id = ?
              AND user_id = ?
              AND read_at IS NULL
        ");

        $update->execute([
            $notificationId,
            $tenantId,
            $userId
        ]);
    }

    $pdo->commit();

    json_response([
        'ok' => true,
        'notification_id' => (int)$notificationId,
        'already_read' => $alreadyRead
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'User notification read API: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Unable to update notification'
    ], 500);
}
