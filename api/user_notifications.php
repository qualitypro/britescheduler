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

    $all = isset($_GET['all']) && $_GET['all'] === '1';
    $limit = $all ? 500 : 50;
    $offset = $all ? max(0, min(1000000, (int)($_GET['offset'] ?? 0))) : 0;

    $query = $pdo->prepare("
        SELECT
            n.id,
            n.appointment_id,
            n.type,
            n.title,
            n.body,
            n.read_at,
            n.created_at,
            NULLIF(TRIM(CONCAT_WS(' ', cl.first_name, cl.last_name)), '') AS client_name,
            (
                SELECT GROUP_CONCAT(
                    DISTINCT NULLIF(TRIM(CONCAT_WS(' ', co.first_name, co.last_name)), '')
                    ORDER BY co.last_name, co.first_name SEPARATOR ', '
                )
                FROM appointment_contractors ac
                JOIN contractors co
                  ON co.id = ac.contractor_id
                 AND co.tenant_id = n.tenant_id
                WHERE ac.appointment_id = n.appointment_id
            ) AS contractor_name
        FROM notifications n
        LEFT JOIN appointments a
          ON a.id = n.appointment_id
         AND a.tenant_id = n.tenant_id
        LEFT JOIN clients cl
          ON cl.id = a.client_id
         AND cl.tenant_id = n.tenant_id
        WHERE n.tenant_id = ?
          AND n.user_id = ?
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT {$limit} OFFSET {$offset}
    ");

    $query->execute([$tenantId, $userId]);

    json_response([
        'ok' => true,
        'unread_count' => (int)$count->fetchColumn(),
        'notifications' => $query->fetchAll(PDO::FETCH_ASSOC),
        'limit' => $limit,
        'offset' => $offset
    ]);

} catch (Throwable $e) {
    error_log(
        'User notifications API: ' . $e->getMessage()
    );

    json_response([
        'error' => 'Unable to load notifications'
    ], 500);
}
