<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('owner');

$tenantId = (int)$tenant['tenant_id'];
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed'], 405);
}

$pdo = Database::connection();

try {
    $countQuery = $pdo->prepare(
        'SELECT COUNT(*)
         FROM notifications
         WHERE tenant_id = ?
           AND user_id = ?
           AND read_at IS NULL'
    );

    $countQuery->execute([$tenantId, $userId]);

    $unreadCount = (int)$countQuery->fetchColumn();

    $notificationsQuery = $pdo->prepare(
        'SELECT
            id,
            appointment_id,
            type,
            title,
            body,
            read_at,
            created_at
         FROM notifications
         WHERE tenant_id = ?
           AND user_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 50'
    );

    $notificationsQuery->execute([$tenantId, $userId]);

    json_response([
        'ok' => true,
        'unread_count' => $unreadCount,
        'notifications' => $notificationsQuery->fetchAll(
            PDO::FETCH_ASSOC
        )
    ]);

} catch (Throwable $e) {
    error_log('Owner notifications API: ' . $e->getMessage());

    json_response([
        'error' => 'Unable to load notifications'
    ], 500);
}
