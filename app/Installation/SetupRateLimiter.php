<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use RuntimeException;

/** Origin-IP keyed, filesystem-backed setup throttle; ignores spoofable forwarded headers. */
final class SetupRateLimiter
{
    public static function attempt(string $root, string $address, bool $successful): bool
    {
        if (!filter_var($address, FILTER_VALIDATE_IP)) return false;
        $directory = rtrim($root, '/') . '/.brite-setup-throttle';
        if (is_link($directory)) throw new RuntimeException('Unsafe setup throttle directory');
        if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
            throw new RuntimeException('Unable to protect installer attempts');
        }
        @chmod($directory, 0700);
        $path = $directory . '/' . hash('sha256', $address) . '.state';
        if (is_link($path)) throw new RuntimeException('Unsafe throttle state');
        $file = @fopen($path, 'c+');
        if (!is_resource($file)) throw new RuntimeException('Unable to protect installer attempts');
        @chmod($path, 0600);
        try {
            if (!flock($file, LOCK_EX)) throw new RuntimeException('Unable to lock setup throttle');
            $raw = stream_get_contents($file);
            $state = json_decode(is_string($raw) ? $raw : '', true);
            $now = time();
            $start = is_array($state) ? (int)($state['start'] ?? 0) : 0;
            $count = is_array($state) ? (int)($state['count'] ?? 0) : 0;
            if ($start < $now - 900 || $start > $now) { $start = $now; $count = 0; }
            $allowed = $count < 5;
            if ($allowed && !$successful) $count++;
            if ($successful) { $count = 0; $start = $now; }
            rewind($file);
            ftruncate($file, 0);
            $data = json_encode(['start'=>$start,'count'=>$count], JSON_THROW_ON_ERROR);
            if (fwrite($file, $data) !== strlen($data) || !fflush($file)) {
                throw new RuntimeException('Unable to persist setup throttle');
            }
            return $allowed;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
