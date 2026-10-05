<?php
declare(strict_types=1);

function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
function request_data(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $v = json_decode(file_get_contents('php://input'), true);
        return is_array($v) ? $v : [];
    }
    return $_POST;
}
function require_method(string ...$allowed): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $allowed, true)) {
        json_response(['error' => 'Method not allowed'], 405);
    }
}
function int_or_null(mixed $v): ?int {
    return ($v === null || $v === '') ? null : (int)$v;
}
