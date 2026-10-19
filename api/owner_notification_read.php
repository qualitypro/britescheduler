<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('owner');

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

try {
    $pdo = Database::connection();

    $pdo->beginTransaction();

    $query = $pdo->prepare(
        'SELECT id, read_at
         FROM notifications
         WHERE id = ?
           AND tenant_id = ?
           AND user_id = ?
         FOR UPDATE'
    );

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
        $update = $pdo->prepare(
            'UPDATE notifications
             SET read_at = UTC_TIMESTAMP()
             WHERE id = ?
               AND tenant_id = ?
               AND user_id = ?
               AND read_at IS NULL'
        );

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
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Owner notification read API: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Unable to update notification'
    ], 500);
}
