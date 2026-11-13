<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use PDO;

/** Provision a reviewed applicant in one transaction; caller must authorize reviewer. */
final class ApprovalService
{
    public static function approve(PDO $pdo, int $tenantId, int $reviewerId, int $applicationId): int
    {
        if ($tenantId < 1 || $reviewerId < 1 || $applicationId < 1) {
            throw new DomainException('Invalid approval request');
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT * FROM registration_applications WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $q->execute([$applicationId, $tenantId]);
            $a = $q->fetch(PDO::FETCH_ASSOC);
            if (!$a || $a['status'] !== 'pending_review' || $a['email_verified_at'] === null ||
                !in_array($a['applicant_type'], ['client', 'contractor'], true) ||
                empty($a['password_hash']) || !password_get_info((string)$a['password_hash'])['algo']) {
                throw new DomainException('Application is not eligible for approval');
            }
            $q = $pdo->prepare("SELECT verification_type FROM registration_verifications WHERE application_id = ? AND status = 'verified' AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())");
            $q->execute([$applicationId]);
            $verified = $q->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('email', $verified, true)) {
                throw new DomainException('Email verification record is missing');
            }
            if ($a['applicant_type'] === 'contractor' && empty($a['phone'])) {
                throw new DomainException('Contractor phone is required');
            }
            // Only verified records count. Never infer verification from a supplied phone or uploaded document.
            ApplicationLifecycle::assertApproval($a['applicant_type'], $verified, true, true);
            $q = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $q->execute([$a['email']]);
            if ($q->fetchColumn()) {
                throw new DomainException('Email already belongs to an existing account');
            }
            $q = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $q->execute([$a['email'], $a['password_hash'], $a['first_name'], $a['last_name'], $a['phone']]);
            $userId = (int)$pdo->lastInsertId();
            $q = $pdo->prepare("INSERT INTO tenant_memberships (tenant_id, user_id, role, status) VALUES (?, ?, ?, 'active')");
            $q->execute([$tenantId, $userId, $a['applicant_type']]);
            if ($a['applicant_type'] === 'client') {
                $q = $pdo->prepare("INSERT INTO clients (tenant_id, user_id, company_name, first_name, last_name, email, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                $q->execute([$tenantId, $userId, $a['organization_name'], $a['first_name'], $a['last_name'], $a['email'], $a['phone']]);
            } else {
                $q = $pdo->prepare("INSERT INTO contractors (tenant_id, user_id, business_name, first_name, last_name, email, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                $q->execute([$tenantId, $userId, $a['organization_name'], $a['first_name'], $a['last_name'], $a['email'], $a['phone']]);
            }
            $q = $pdo->prepare("UPDATE registration_applications SET status = 'approved', reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(), provisioned_user_id = ?, password_hash = NULL WHERE id = ? AND tenant_id = ? AND status = 'pending_review'");
            $q->execute([$reviewerId, $userId, $applicationId, $tenantId]);
            if ($q->rowCount() !== 1) {
                throw new DomainException('Application changed during approval');
            }
            $q = $pdo->prepare("INSERT INTO registration_reviews (application_id, reviewer_id, action, reason) VALUES (?, ?, 'approved', 'Requirements checked and account provisioned')");
            $q->execute([$applicationId, $reviewerId]);
            $pdo->commit();
            return $userId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
}
