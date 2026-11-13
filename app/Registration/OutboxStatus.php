<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use PDO;

/** Operational read-only visibility; no plaintext email bodies or addresses. */
final class OutboxStatus
{
    public static function inspect(PDO $pdo): array
    {
        $counts = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
        $q = $pdo->query('SELECT status, COUNT(*) AS total FROM registration_email_outbox GROUP BY status');
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string)$row['status']] = (int)$row['total'];
        }
        $q = $pdo->query("SELECT COUNT(*) FROM registration_email_outbox
            WHERE status = 'sending' AND claimed_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE");
        $stale = (int)$q->fetchColumn();
        $q = $pdo->query("SELECT id FROM registration_email_outbox
            WHERE status = 'sending' AND claimed_at < UTC_TIMESTAMP() - INTERVAL 15 MINUTE
            ORDER BY id ASC LIMIT 50");
        return ['counts' => $counts, 'stale_claims' => $stale,
            'stale_ids' => array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN))];
    }
}
