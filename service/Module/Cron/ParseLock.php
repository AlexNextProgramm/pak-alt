<?php

namespace Module\Cron;

use Module\Ai\ParseConfig;

class ParseLock
{
    private string $lockPath;

    /** @var resource|null */
    private $handle = null;

    public function __construct(?string $lockPath = null)
    {
        $dir = ParseConfig::repoRoot() . '/var/locks';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->lockPath = $lockPath ?: $dir . '/email-parse.lock';
    }

    public function acquire(): bool
    {
        $this->handle = fopen($this->lockPath, 'c+');
        if ($this->handle === false) {
            return false;
        }

        return flock($this->handle, LOCK_EX | LOCK_NB);
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }
}
