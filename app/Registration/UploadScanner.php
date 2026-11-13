<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use RuntimeException;

/** Fail-closed ClamAV scan of verified PHP uploads before vault encryption. */
final class UploadScanner
{
    public static function scan(string $path): void
    {
        if (!is_uploaded_file($path)) throw new DomainException('Invalid upload source');
        $binary = getenv('ENROLLMENT_CLAMSCAN_BIN') ?: '';
        if (!str_starts_with($binary, '/') || !is_file($binary) ||
            !is_executable($binary) || is_link($binary)) {
            throw new RuntimeException('Document malware scanner is not configured');
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('Document malware scanner cannot be executed');
        }
        // Array command bypasses shell interpolation. Never log scanner output or document paths.
        $pipes = [];
        $process = proc_open([$binary, '--no-summary', '--', $path],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes, null, ['PATH' => '/usr/bin:/bin']);
        if (!is_resource($process)) throw new RuntimeException('Document malware scanner unavailable');
        fclose($pipes[0]);
        $status = proc_close($process);
        if ($status === 1) throw new DomainException('Document rejected by security scan');
        if ($status !== 0) throw new RuntimeException('Document malware scan could not complete');
    }
}
