<?php
declare(strict_types=1);

require_once __DIR__ . '/ServiceAreaMatcher.php';

/**
 * Contractor assignment eligibility policy.
 *
 * Intended for evaluating NEW assignments.
 * Does not write data or change existing assignments.
 */
final class ContractorAssignmentValidator
{
    public static function evaluate(
        PDO $pdo,
        int $tenantId,
        int $contractorId,
        ?int $serviceId,
        ?int $locationId
    ): array {
        if ($tenantId < 1 || $contractorId < 1) {
            throw new InvalidArgumentException(
                'Invalid tenant or contractor'
            );
        }

        if ($serviceId !== null && $serviceId < 1) {
            throw new InvalidArgumentException('Invalid service');
        }

        if ($locationId !== null && $locationId < 1) {
            throw new InvalidArgumentException('Invalid location');
        }

        $result = [
            'contractor_id' => $contractorId,
            'allowed' => true,
            'qualification_status' => 'unverified',
            'geographic_status' => 'unknown',
            'warnings' => [],
            'errors' => []
        ];

        $contractor = $pdo->prepare(
            'SELECT status
             FROM contractors
             WHERE id=? AND tenant_id=?
             LIMIT 1'
        );

        $contractor->execute([$contractorId, $tenantId]);
        $contractorStatus = $contractor->fetchColumn();

        if ($contractorStatus === false) {
            throw new InvalidArgumentException(
                'Contractor not found'
            );
        }

        if ($contractorStatus !== 'active') {
            $result['errors'][] = 'Contractor is not active';
        }

        if ($serviceId !== null) {
            $service = $pdo->prepare(
                'SELECT active
                 FROM services
                 WHERE id=? AND tenant_id=?
                 LIMIT 1'
            );

            $service->execute([$serviceId, $tenantId]);
            $serviceActive = $service->fetchColumn();

            if ($serviceActive === false) {
                throw new InvalidArgumentException(
                    'Service not found'
                );
            }

            if ((int)$serviceActive !== 1) {
                $result['errors'][] = 'Service is not active';
            }

            $qualification = $pdo->prepare(
                'SELECT verification_status
                 FROM contractor_service_qualifications
                 WHERE tenant_id=?
                   AND contractor_id=?
                   AND service_id=?
                 LIMIT 1'
            );

            $qualification->execute([
                $tenantId,
                $contractorId,
                $serviceId
            ]);

            $status = $qualification->fetchColumn();

            if ($status !== false) {
                $result['qualification_status'] = (string)$status;
            }

            if ($result['qualification_status'] === 'rejected') {
                $result['errors'][] =
                    'Contractor qualification rejected for this service';
            } elseif ($result['qualification_status'] !== 'verified') {
                $result['warnings'][] =
                    'Contractor service qualification is not verified';
            }
        } else {
            $result['warnings'][] =
                'No service selected; qualification not evaluated';
        }

        if ($locationId === null) {
            $result['warnings'][] =
                'No client location selected; geography not evaluated';
        } else {
            $location = $pdo->prepare(
                'SELECT postal_code, state, geocoding_status,
                        latitude, longitude
                 FROM client_locations
                 WHERE id=? AND tenant_id=?
                 LIMIT 1'
            );

            $location->execute([$locationId, $tenantId]);
            $locationData = $location->fetch(PDO::FETCH_ASSOC);

            if (!$locationData) {
                throw new InvalidArgumentException(
                    'Location not found'
                );
            }

            $areas = $pdo->prepare(
                'SELECT area_type
                 FROM contractor_service_areas
                 WHERE tenant_id=?
                   AND contractor_id=?
                   AND active=1
                   AND country_code=?'
            );

            $areas->execute([
                $tenantId,
                $contractorId,
                'US'
            ]);

            $configuredAreas = $areas->fetchAll(PDO::FETCH_COLUMN);

            if (!$configuredAreas) {
                $result['geographic_status'] = 'unconfigured';
                $result['warnings'][] =
                    'Contractor service area is not configured';
            } else {
                $matches = ServiceAreaMatcher::matchLocation(
                    $pdo,
                    $tenantId,
                    $locationId
                );

                $matched = false;

                foreach ($matches as $match) {
                    if ((int)$match['contractor_id'] === $contractorId) {
                        $matched = true;
                        break;
                    }
                }

                if ($matched) {
                    $result['geographic_status'] = 'matched';
                } else {
                    $zipReady =
                        ServiceAreaMatcher::normalizeZip(
                            $locationData['postal_code']
                        ) !== null &&
                        preg_match(
                            '/^[A-Z]{2}$/',
                            strtoupper(trim((string)$locationData['state']))
                        ) === 1;

                    $radiusReady =
                        $locationData['geocoding_status'] === 'resolved' &&
                        $locationData['latitude'] !== null &&
                        $locationData['longitude'] !== null &&
                        is_numeric($locationData['latitude']) &&
                        is_numeric($locationData['longitude']) &&
                        is_finite((float)$locationData['latitude']) &&
                        is_finite((float)$locationData['longitude']) &&
                        abs((float)$locationData['latitude']) <= 90 &&
                        abs((float)$locationData['longitude']) <= 180;

                    $canEvaluateAll = true;

                    foreach ($configuredAreas as $type) {
                        if (
                            ($type === 'zip' && !$zipReady) ||
                            ($type === 'radius' && !$radiusReady)
                        ) {
                            $canEvaluateAll = false;
                            break;
                        }
                    }

                    if ($canEvaluateAll) {
                        $result['geographic_status'] = 'unmatched';
                        $result['errors'][] =
                            'Contractor does not cover this service location';
                    } else {
                        $result['geographic_status'] = 'unknown';
                        $result['warnings'][] =
                            'Geographic coverage cannot be fully evaluated';
                    }
                }
            }
        }

        $result['allowed'] = count($result['errors']) === 0;

        return $result;
    }
}
