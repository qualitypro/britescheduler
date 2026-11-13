<?php
declare(strict_types=1);

namespace BriteScheduler\Registration;

use DomainException;
use RuntimeException;

/**
 * Private, encrypted document vault primitive. Not a verification provider.
 * Configure ENROLLMENT_DOCUMENT_ROOT outside the webroot and ENROLLMENT_DOCUMENT_KEY.
 */
final class DocumentVault
{
    private static function key(): string
    {
        $key = base64_decode(getenv('ENROLLMENT_DOCUMENT_KEY') ?: '', true);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('Document encryption key is not configured');
        }
        return $key;
    }

    private static function root(): string
    {
        $configured = getenv('ENROLLMENT_DOCUMENT_ROOT') ?: '';
        $root = realpath($configured);
        $webroot = realpath(dirname(__DIR__, 2));
        $serverDocroot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : false;
        $configuredDocroot = getenv('ENROLLMENT_WEB_ROOT') ? realpath((string)getenv('ENROLLMENT_WEB_ROOT')) : false;
        if (!$configuredDocroot) throw new RuntimeException('Explicit web document root is required for vault isolation');
        if (!$root || !is_dir($root) || is_link($configured) ||
            !str_starts_with($configured, '/') || !$webroot ||
            ($root === $webroot || str_starts_with($root, $webroot . DIRECTORY_SEPARATOR)) ||
            ($root === $configuredDocroot || str_starts_with($root, $configuredDocroot . DIRECTORY_SEPARATOR)) ||
            ($serverDocroot && ($root === $serverDocroot || str_starts_with($root, $serverDocroot . DIRECTORY_SEPARATOR)))) {
            throw new RuntimeException('Document vault must be an existing directory outside the webroot');
        }
        if ((fileperms($root) & 0007) !== 0) {
            throw new RuntimeException('Document vault must not be accessible to other users');
        }
        return $root;
    }

    /**
     * Accept only recognized formats, enforce a 10 MiB ceiling, and encrypt before storage.
     * The caller must authenticate the applicant and authorize the application.
     */
    public static function store(string $uploadedPath, string $originalName): string
    {
        $root = self::root();
        $key = self::key();
        if (!is_uploaded_file($uploadedPath)) {
            throw new DomainException('Document must be a verified HTTP upload');
        }
        $length = filesize($uploadedPath);
        if ($length === false || $length < 1 || $length > 10 * 1024 * 1024) {
            throw new DomainException('Document exceeds the allowed size');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($uploadedPath);
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw new DomainException('Unsupported document format');
        }
        $data = file_get_contents($uploadedPath);
        if ($data === false || strlen($data) !== $length) {
            throw new RuntimeException('Unable to read uploaded document');
        }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt document');
        }
        $id = bin2hex(random_bytes(24));
        $path = $root . DIRECTORY_SEPARATOR . $id . '.vault';
        $handle = @fopen($path, 'x+b');
        if ($handle === false) throw new RuntimeException('Unable to allocate private document');
        try {
            @chmod($path, 0600);
            $payload = "BRITEDOC1" . $nonce . $tag . $ciphertext;
            $written = 0;
            while ($written < strlen($payload)) {
                $count = fwrite($handle, substr($payload, $written));
                if ($count === false || $count === 0) throw new RuntimeException('Document write failed');
                $written += $count;
            }
            if (!fflush($handle)) throw new RuntimeException('Document flush failed');
        } catch (\Throwable $error) {
            fclose($handle);
            @unlink($path);
            throw $error;
        }
        fclose($handle);
        return $id;
    }

    /** Caller must enforce moderator authorization and record access in audit history. */
    public static function read(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/D', $id)) {
            throw new DomainException('Invalid document reference');
        }
        $path = self::root() . DIRECTORY_SEPARATOR . $id . '.vault';
        if (is_link($path) || !is_file($path)) {
            throw new DomainException('Document unavailable');
        }
        $payload = file_get_contents($path);
        if ($payload === false || strlen($payload) < 38 || substr($payload, 0, 9) !== 'BRITEDOC1') {
            throw new DomainException('Document is corrupt');
        }
        $plaintext = openssl_decrypt(substr($payload, 37), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($payload, 9, 12), substr($payload, 21, 16));
        if ($plaintext === false) throw new DomainException('Document authentication failed');
        return $plaintext;
    }
}
