<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$user = Auth::requireUser();
$tenant = Auth::requireRole('contractor');
$tenantId = (int)$tenant['tenant_id'];
$pdo = Database::connection();
$q = $pdo->prepare('SELECT id, first_name, last_name, business_name FROM contractors WHERE tenant_id=? AND user_id=? AND status=? LIMIT 1');
$q->execute([$tenantId, (int)$user['id'], 'active']);
$contractor = $q->fetch(PDO::FETCH_ASSOC);
if (!$contractor) json_response(['error' => 'Active contractor account required'], 403);
$id = (int)$contractor['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $q = $pdo->prepare('SELECT headline, biography, specialties, years_experience, portfolio_url, updated_at FROM contractor_profiles WHERE tenant_id=? AND contractor_id=? LIMIT 1');
    $q->execute([$tenantId, $id]);
    json_response(['ok'=>true,'contractor'=> $contractor, 'profile'=> $q->fetch(PDO::FETCH_ASSOC) ?: [
        'headline'=>'', 'biography'=>'', 'specialties'=>'', 'years_experience'=>null, 'portfolio_url'=>null, 'updated_at'=>null
    ]]);
}
if ($method !== 'PUT') {
    header('Allow: GET, PUT');
    json_response(['error'=>'Method not allowed'], 405);
}
verify_csrf();
$d = request_data();
$fields = ['headline'=>140, 'biography'=>3000, 'specialties'=>500, 'portfolio_url'=>500];
foreach ($fields as $field=>$limit) {
    if (!array_key_exists($field, $d) || !is_string($d[$field]) || mb_strlen(trim($d[$field]), 'UTF-8') > $limit) {
        json_response(['error'=>'Invalid ' . $field], 422);
    }
    $d[$field] = trim($d[$field]);
}
$years = $d['years_experience'] ?? null;
if ($years === '') $years = null;
if ($years !== null) {
    if (!is_int($years) && !is_string($years)) json_response(['error'=>'Invalid years of experience'],422);
    $years = filter_var($years, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>80]]);
    if ($years === false) json_response(['error'=>'Invalid years of experience'],422);
}
if ($d['portfolio_url'] !== '') {
    $url = filter_var($d['portfolio_url'], FILTER_VALIDATE_URL);
    $parts = $url ? parse_url($url) : false;
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
        isset($parts['user']) || isset($parts['pass'])) {
        json_response(['error'=>'Portfolio URL must be a valid HTTPS address'],422);
    }
}
try {
    $q = $pdo->prepare('INSERT INTO contractor_profiles (tenant_id, contractor_id, headline, biography, specialties, years_experience, portfolio_url) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE headline=VALUES(headline), biography=VALUES(biography), specialties=VALUES(specialties), years_experience=VALUES(years_experience), portfolio_url=VALUES(portfolio_url)');
    $q->execute([$tenantId,$id,$d['headline'],$d['biography'],$d['specialties'],$years,$d['portfolio_url'] ?: null]);
    json_response(['ok'=>true]);
} catch (Throwable $e) {
    error_log('Contractor profile update failed: ' . $e->getMessage());
    json_response(['error'=>'Unable to save profile; verify migration 020 is installed'],500);
}
