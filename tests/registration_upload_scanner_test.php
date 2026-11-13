<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Registration/UploadScanner.php';

use BriteScheduler\Registration\UploadScanner;

$rejected = false;
try {
    UploadScanner::scan(__FILE__);
} catch (DomainException $expected) {
    $rejected = true;
}
if (!$rejected) throw new RuntimeException('Local non-upload file was accepted by malware scanner');
echo "PASS: malware scanner rejects non-HTTP-upload files\n";
