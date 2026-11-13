<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$id = (int)($_SESSION['enrollment_document_application_id'] ?? 0);
$expires = (int)($_SESSION['enrollment_document_expires_at'] ?? 0);
if ($id < 1 || $expires <= time()) {
    http_response_code(403);
    exit('This upload session has expired. Contact the business for further instructions.');
}
$q = Database::connection()->prepare("SELECT id FROM registration_applications
    WHERE id = ? AND applicant_type = 'contractor' AND email_verified_at IS NOT NULL
    AND status IN ('pending_review', 'action_required') LIMIT 1");
$q->execute([$id]);
if (!$q->fetchColumn()) {
    http_response_code(403);
    exit('Document submission is unavailable for this enrollment.');
}
$escape = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contractor documents | Brite Scheduler</title>
<style>body{font:16px system-ui,sans-serif;max-width:560px;margin:7vh auto;padding:24px;color:#172554;background:#f8fafc}main{background:white;padding:28px;border-radius:14px}label{display:block;margin-top:16px}input,select,button{font:inherit;padding:10px;width:100%;box-sizing:border-box;margin-top:8px}</style></head>
<body><main><h1>Contractor documents</h1>
<p>Upload a government-issued ID or supporting contractor credential for review. Accepted formats: PDF, JPEG or PNG, up to 10 MB. Uploading does not verify your identity or approve your account.</p>
<form action="<?= $escape(app_url('/api/enrollment_document_upload.php')) ?>" method="post" enctype="multipart/form-data">
<input type="hidden" name="_csrf" value="<?= $escape(csrf_token()) ?>">
<label for="document_type">Document type</label>
<select name="document_type" id="document_type" required>
<option value="government_id">Government-issued ID</option><option value="license">License</option>
<option value="insurance">Insurance</option><option value="certification">Certification</option></select>
<label for="document">Choose document</label><input type="file" name="document" id="document" accept=".pdf,.jpg,.jpeg,.png" required>
<button type="submit">Submit document securely</button>
</form></main></body></html>
