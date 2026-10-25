<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::tenant();

require_method('GET');

$tenantId = (int)$tenant['tenant_id'];
$userId = (int)$user['id'];

$pdo = Database::connection();

try {
    /*
     * Require active tenant membership and active account.
     */
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
        json_response([
            'error' => 'Active tenant membership required'
        ], 403);
    }

    $count = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE tenant_id = ?
          AND user_id = ?
          AND read_at IS NULL
    ");

    $count->execute([$tenantId, $userId]);

    $query = $pdo->prepare("
        SELECT
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
        LIMIT 50
    ");

    $query->execute([$tenantId, $userId]);

    json_response([
        'ok' => true,
        'unread_count' => (int)$count->fetchColumn(),
        'notifications' => $query->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {
    error_log(
        'User notifications API: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Unable to load notifications'
    ], 500);
}
