<?php
declare(strict_types=1);

final class Geocoding
{
    private const PROVIDER = 'nominatim';
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /**
     * Resolve an address using the persistent cache and Nominatim.
     *
     * Returns:
     *   ['status' => 'resolved', 'latitude' => ..., 'longitude' => ...]
     *   ['status' => 'rejected']
     *   ['status' => 'not_found']
     *   ['status' => 'unavailable']
     *
     * Never interpret unavailable as a permanent address failure.
     */
    public static function resolve(PDO $pdo, array $address): array
    {
        $parts = [];

        foreach (['address1', 'city', 'state', 'postal_code'] as $field) {
            $value = trim((string)($address[$field] ?? ''));

            if ($value === '') {
                return ['status' => 'rejected'];
            }

            $parts[$field] = $value;
        }

        // Street-level verification requires an explicit house number.
        if (!preg_match('/^\s*(\d+[A-Za-z]?)\b/u', $parts['address1'], $match)) {
            return ['status' => 'rejected'];
        }

        $requestedNumber = strtoupper($match[1]);

        $normalized = implode(', ', [
            $parts['address1'],
            $parts['city'],
            $parts['state'],
            $parts['postal_code'],
            'USA'
        ]);

        $hash = hash('sha256', mb_strtolower(
            preg_replace('/\s+/u', ' ', $normalized)
        ));

        $cached = self::cached($pdo, $hash);
        if ($cached !== null) {
            return $cached;
        }

        if (getenv('BRITE_GEOCODING_PROVIDER') !== self::PROVIDER) {
            return ['status' => 'unavailable'];
        }

        $contact = getenv('BRITE_GEOCODING_CONTACT') ?: '';
        $agent = getenv('BRITE_GEOCODING_USER_AGENT') ?: '';

        if (!filter_var($contact, FILTER_VALIDATE_EMAIL) || $agent === '') {
            return ['status' => 'unavailable'];
        }

        $lockName = 'brite_geocoding_nominatim';

        try {
            $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
            $lock->execute([$lockName]);

            if ((int)$lock->fetchColumn() !== 1) {
                return ['status' => 'unavailable'];
            }
        } catch (Throwable $e) {
            error_log('Geocoding lock acquisition failed: ' . get_class($e));
            return ['status' => 'unavailable'];
        }

        try {
            // Another process may have populated the cache while we waited.
            $cached = self::cached($pdo, $hash);
            if ($cached !== null) {
                return $cached;
            }

            // Reserve the next request slot before making the HTTP call.
            $q = $pdo->prepare(
                'SELECT TIMESTAMPDIFF(MICROSECOND, UTC_TIMESTAMP(6), next_allowed_at)
                 FROM geocoding_rate_limit WHERE provider=?'
            );
            $q->execute([self::PROVIDER]);
            $waitMicroseconds = max(0, (int)$q->fetchColumn());

            if ($waitMicroseconds > 0) {
                usleep($waitMicroseconds);
            }

            $q = $pdo->prepare(
                'UPDATE geocoding_rate_limit
                 SET next_allowed_at=UTC_TIMESTAMP(6) + INTERVAL 2 SECOND
                 WHERE provider=?'
            );
            $q->execute([self::PROVIDER]);

            if ($q->rowCount() !== 1) {
                return ['status' => 'unavailable'];
            }

            $result = self::request($parts, $requestedNumber, $contact, $agent);

            if (in_array($result['status'], ['resolved', 'rejected', 'not_found'], true)) {
                $q = $pdo->prepare(
                    'INSERT INTO geocoding_cache
                     (provider,address_hash,normalized_address,latitude,longitude,
                      precision_type,display_name,status)
                     VALUES (?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                      latitude=VALUES(latitude),
                      longitude=VALUES(longitude),
                      precision_type=VALUES(precision_type),
                      display_name=VALUES(display_name),
                      status=VALUES(status),
                      updated_at=CURRENT_TIMESTAMP()'
                );

                $q->execute([
                    self::PROVIDER,
                    $hash,
                    $normalized,
                    $result['latitude'] ?? null,
                    $result['longitude'] ?? null,
                    $result['precision_type'] ?? null,
                    $result['display_name'] ?? null,
                    $result['status']
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            error_log('Geocoding service unavailable: ' . get_class($e));
            return ['status' => 'unavailable'];
        } finally {
            try {
                $q = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $q->execute([$lockName]);
            } catch (Throwable $e) {
                error_log('Geocoding lock release failed');
            }
        }
    }

    private static function cached(PDO $pdo, string $hash): ?array
    {
        $q = $pdo->prepare(
            'SELECT status,latitude,longitude,precision_type,display_name
             FROM geocoding_cache
             WHERE provider=? AND address_hash=?
               AND (
                   status=\'resolved\'
                   OR updated_at > NOW() - INTERVAL 24 HOUR
               )
             LIMIT 1'
        );
        $q->execute([self::PROVIDER, $hash]);

        $row = $q->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'status' => $row['status'],
            'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
            'precision_type' => $row['precision_type'],
            'display_name' => $row['display_name']
        ];
    }

    private static function normalizeStreet(string $street): string
    {
        $street = mb_strtolower(trim($street));
        $street = preg_replace('/[^\\p{L}\\p{N}\\s]/u', ' ', $street);
        $street = preg_replace('/\\s+/u', ' ', $street);

        $street = trim($street);

        $suffixes = [
            'st' => 'street',
            'rd' => 'road',
            'ave' => 'avenue',
            'av' => 'avenue',
            'blvd' => 'boulevard',
            'dr' => 'drive',
            'ln' => 'lane',
            'ct' => 'court',
            'cir' => 'circle',
            'pl' => 'place',
            'pkwy' => 'parkway',
            'ter' => 'terrace',
            'trl' => 'trail',
            'hwy' => 'highway'
        ];

        $words = explode(' ', $street);

        $directions = [
            'n' => 'north',
            's' => 'south',
            'e' => 'east',
            'w' => 'west',
            'ne' => 'northeast',
            'nw' => 'northwest',
            'se' => 'southeast',
            'sw' => 'southwest'
        ];

        foreach ($words as &$word) {
            if (isset($directions[$word])) {
                $word = $directions[$word];
            }
        }
        unset($word);

        $last = count($words) - 1;

        if (isset($suffixes[$words[$last]])) {
            $words[$last] = $suffixes[$words[$last]];
        }

        return implode(' ', $words);
    }

    private static function request(
        array $parts,
        string $requestedNumber,
        string $contact,
        string $agent
    ): array {
        $query = http_build_query([
            'street' => $parts['address1'],
            'city' => $parts['city'],
            'state' => $parts['state'],
            'postalcode' => $parts['postal_code'],
            'country' => 'United States',
            'countrycodes' => 'us',
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 5,
            'email' => $contact
        ], '', '&', PHP_QUERY_RFC3986);

        $ch = curl_init(self::ENDPOINT . '?' . $query);

        if ($ch === false) {
            return ['status' => 'unavailable'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => $agent,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'From: ' . $contact
            ]
        ]);

        try {
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } finally {
            curl_close($ch);
        }

        if ($body === false || $status !== 200) {
            return ['status' => 'unavailable'];
        }

        $items = json_decode($body, true);

        if (!is_array($items)) {
            return ['status' => 'unavailable'];
        }

        if ($items === []) {
            return ['status' => 'not_found'];
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $details = $item['address'] ?? [];
            if (!is_array($details)) {
                continue;
            }

            $actualNumber = strtoupper(trim(
                (string)($details['house_number'] ?? '')
            ));

            $requestedStreet = self::normalizeStreet(
                preg_replace('/^\\s*\\d+[A-Za-z]?\\s+/u', '', $parts['address1'])
            );

            $actualStreet = self::normalizeStreet(
                (string)($details['road'] ?? '')
            );

            if ($requestedStreet === '' ||
                $actualStreet === '' ||
                $requestedStreet !== $actualStreet) {
                continue;
            }

            // Reject city centers, streets, and buildings without
            // an explicitly matching house number.
            if ($actualNumber !== $requestedNumber) {
                continue;
            }

            $actualPostcode = preg_replace(
                '/\D/',
                '',
                (string)($details['postcode'] ?? '')
            );
            $requestedPostcode = preg_replace(
                '/\D/',
                '',
                $parts['postal_code']
            );

            if (
                strlen($actualPostcode) < 5 ||
                strlen($requestedPostcode) < 5 ||
                substr($actualPostcode, 0, 5) !== substr($requestedPostcode, 0, 5)
            ) {
                continue;
            }

            if (($details['country_code'] ?? '') !== 'us') {
                continue;
            }

            $lat = filter_var($item['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $lon = filter_var($item['lon'] ?? null, FILTER_VALIDATE_FLOAT);

            if (
                $lat === false || $lon === false ||
                $lat < -90 || $lat > 90 ||
                $lon < -180 || $lon > 180
            ) {
                continue;
            }

            return [
                'status' => 'resolved',
                'latitude' => (float)$lat,
                'longitude' => (float)$lon,
                'precision_type' => 'house_number',
                'display_name' => (string)($item['display_name'] ?? '')
            ];
        }

        return ['status' => 'rejected'];
    }
}
