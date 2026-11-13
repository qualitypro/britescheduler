<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use RuntimeException;

/** Secure local filesystem lock for serializing installation operations. */
final class InstallationMutex
{
    /** @var resource|null */
    private $handle = null;

    public function acquire(string $root): void
    {
        $path = rtrim($root, '/') . '/.brite-install.mutex';
        if (is_link($path)) throw new RuntimeException('Unsafe installer lock path');
        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) throw new RuntimeException('Installer lock unavailable');
        @chmod($path, 0600);
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Another installer operation is running');
        }
        $this->handle = $handle;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
