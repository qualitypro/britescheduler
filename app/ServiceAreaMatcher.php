<?php
declare(strict_types=1);

/**
 * Brite Scheduler V2 — Contractor Service Area Matcher
 *
 * Read-only geographic eligibility engine.
 * Does not assign appointments or perform geocoding.
 */
final class ServiceAreaMatcher
{
    public static function normalizeZip(?string $postalCode): ?string
    {
        if ($postalCode === null) {
            return null;
        }

        $postalCode = trim($postalCode);

        if (!preg_match('/^([0-9]{5})(?:-[0-9]{4})?$/D', $postalCode, $matches)) {
            return null;
        }

        return $matches[1] === '00000' ? null : $matches[1];
    }

    public static function haversineMiles(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        foreach ([$latitude1, $latitude2] as $latitude) {
            if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) {
                throw new InvalidArgumentException('Invalid latitude');
            }
        }

        foreach ([$longitude1, $longitude2] as $longitude) {
            if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) {
                throw new InvalidArgumentException('Invalid longitude');
            }
        }

        $earthRadiusMiles = 3958.7613;

        $deltaLatitude = deg2rad($latitude2 - $latitude1);
        $deltaLongitude = deg2rad($longitude2 - $longitude1);

        $a =
            sin($deltaLatitude / 2) ** 2 +
            cos(deg2rad($latitude1)) *
            cos(deg2rad($latitude2)) *
            sin($deltaLongitude / 2) ** 2;

        $a = min(1.0, max(0.0, $a));

        return $earthRadiusMiles * 2 * asin(sqrt($a));
    }

    /**
     * Return eligible contractors for a tenant-owned client location.
     *
     * A contractor qualifies when at least one active ZIP or radius
     * service area matches the location.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function matchLocation(
        PDO $pdo,
        int $tenantId,
        int $locationId
    ): array {
        if ($tenantId < 1 || $locationId < 1) {
            throw new InvalidArgumentException('Invalid tenant or location ID');
        }

        $locationQuery = $pdo->prepare(
            'SELECT id, tenant_id, state, postal_code,
                    latitude, longitude, geocoding_status
             FROM client_locations
             WHERE tenant_id = ? AND id = ?
             LIMIT 1'
        );

        $locationQuery->execute([$tenantId, $locationId]);
        $location = $locationQuery->fetch(PDO::FETCH_ASSOC);

        if (!$location) {
            return [];
        }

        $zip = self::normalizeZip((string)$location['postal_code']);
        $state = strtoupper(trim((string)$location['state']));

        $hasCoordinates =
            $location['geocoding_status'] === 'resolved' &&
            $location['latitude'] !== null &&
            $location['longitude'] !== null;

        $latitude = null;
        $longitude = null;

        if ($hasCoordinates) {
            $latitude = (float)$location['latitude'];
            $longitude = (float)$location['longitude'];

            if (
                !is_finite($latitude) ||
                !is_finite($longitude) ||
                $latitude < -90 ||
                $latitude > 90 ||
                $longitude < -180 ||
                $longitude > 180
            ) {
                $hasCoordinates = false;
            }
        }

        $areaQuery = $pdo->prepare(
            "SELECT
                c.id AS contractor_id,
                c.first_name,
                c.last_name,
                c.business_name,
                sa.id AS service_area_id,
                sa.area_type,
                sa.label,
                sa.state_code,
                sa.postal_code,
                sa.center_latitude,
                sa.center_longitude,
                sa.radius_miles
             FROM contractors c
             INNER JOIN contractor_service_areas sa
                 ON sa.tenant_id = c.tenant_id
                AND sa.contractor_id = c.id
             WHERE c.tenant_id = ?
               AND c.status = 'active'
               AND sa.active = 1
               AND sa.country_code = 'US'
             ORDER BY c.id, sa.id"
        );

        $areaQuery->execute([$tenantId]);

        $matches = [];

        while ($area = $areaQuery->fetch(PDO::FETCH_ASSOC)) {
            $matched = false;
            $distance = null;

            if ($area['area_type'] === 'zip') {
                $matched =
                    $zip !== null &&
                    strlen($state) === 2 &&
                    $state === (string)$area['state_code'] &&
                    $zip === (string)$area['postal_code'];
            }

            if (
                $area['area_type'] === 'radius' &&
                $hasCoordinates &&
                $area['center_latitude'] !== null &&
                $area['center_longitude'] !== null &&
                $area['radius_miles'] !== null
            ) {
                $distance = self::haversineMiles(
                    $latitude,
                    $longitude,
                    (float)$area['center_latitude'],
                    (float)$area['center_longitude']
                );

                $matched =
                    $distance <= (float)$area['radius_miles'];
            }

            if (!$matched) {
                continue;
            }

            $contractorId = (int)$area['contractor_id'];

            if (!isset($matches[$contractorId])) {
                $matches[$contractorId] = [
                    'contractor_id' => $contractorId,
                    'first_name' => $area['first_name'],
                    'last_name' => $area['last_name'],
                    'business_name' => $area['business_name'],
                    'matching_areas' => []
                ];
            }

            $matches[$contractorId]['matching_areas'][] = [
                'service_area_id' => (int)$area['service_area_id'],
                'area_type' => $area['area_type'],
                'label' => $area['label'],
                'distance_miles' => $distance
            ];
        }

        return array_values($matches);
    }
}
