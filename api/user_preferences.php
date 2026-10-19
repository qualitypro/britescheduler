<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireUser();
$userId = (int)$user['id'];

$pdo = Database::connection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$defaults = [
    'color_palette' => 'ocean',
    'calendar_view' => 'month',
    'week_starts_on' => 'sunday',
    'time_format' => '12h',
    'reduced_motion' => 0,
    'high_contrast' => 0,
    'in_app_notifications' => 1,
];

$allowed = [
    'color_palette' => [
        'ocean', 'forest', 'midnight', 'slate'
    ],
    'calendar_view' => [
        'month', 'week', 'day'
    ],
    'week_starts_on' => [
        'sunday', 'monday'
    ],
    'time_format' => [
        '12h', '24h'
    ],
];

$booleanFields = [
    'reduced_motion',
    'high_contrast',
    'in_app_notifications',
];

$loadPreferences = static function () use (
    $pdo,
    $userId,
    $defaults
): array {
    $query = $pdo->prepare(
        'SELECT
            color_palette,
            calendar_view,
            week_starts_on,
            time_format,
            reduced_motion,
            high_contrast,
            in_app_notifications
         FROM user_preferences
         WHERE user_id = ?
         LIMIT 1'
    );

    $query->execute([$userId]);

    $stored = $query->fetch(PDO::FETCH_ASSOC);

    return array_merge($defaults, $stored ?: []);
};

if ($method === 'GET') {
    json_response([
        'preferences' => $loadPreferences()
    ]);
}

if ($method !== 'PUT') {
    header('Allow: GET, PUT');
    json_response([
        'error' => 'Method not allowed'
    ], 405);
}

verify_csrf();

$data = request_data();

if (!is_array($data) || array_is_list($data)) {
    json_response([
        'error' => 'Expected a settings object'
    ], 422);
}

$unknown = array_diff(
    array_keys($data),
    array_keys($defaults)
);

if ($unknown) {
    json_response([
        'error' => 'Unknown preference field'
    ], 422);
}

if (!$data) {
    json_response([
        'error' => 'No preferences supplied'
    ], 422);
}

$validated = [];

foreach ($data as $field => $value) {
    if (isset($allowed[$field])) {
        if (
            !is_string($value) ||
            !in_array($value, $allowed[$field], true)
        ) {
            json_response([
                'error' => 'Invalid value for ' . $field
            ], 422);
        }

        $validated[$field] = $value;
        continue;
    }

    if (in_array($field, $booleanFields, true)) {
        if (
            !is_bool($value) &&
            $value !== 0 &&
            $value !== 1
        ) {
            json_response([
                'error' => 'Invalid value for ' . $field
            ], 422);
        }

        $validated[$field] = (int)$value;
    }
}

$columns = array_keys($validated);

$insertColumns = array_merge(['user_id'], $columns);
$insertPlaceholders = implode(
    ', ',
    array_fill(0, count($insertColumns), '?')
);

$updates = implode(
    ', ',
    array_map(
        static fn(string $column): string =>
            "`$column` = VALUES(`$column`)",
        $columns
    )
);

$sql = 'INSERT INTO user_preferences (' .
    implode(', ', array_map(
        static fn(string $column): string =>
            "`$column`",
        $insertColumns
    )) .
    ') VALUES (' . $insertPlaceholders . ')' .
    ' ON DUPLICATE KEY UPDATE ' . $updates;

$statement = $pdo->prepare($sql);

$statement->execute(
    array_merge([$userId], array_values($validated))
);

json_response([
    'ok' => true,
    'preferences' => $loadPreferences()
]);
