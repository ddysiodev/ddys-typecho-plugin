<?php

namespace TypechoPlugin\DdysOpen;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Cache
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?: rtrim(__TYPECHO_ROOT_DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'usr' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'ddys-open';
    }

    public function key(string $method, string $baseUrl, string $path, array $params = []): string
    {
        $normalized = [
            'method' => strtoupper($method),
            'base' => rtrim($baseUrl, '/'),
            'path' => '/' . ltrim($path, '/'),
            'params' => Helpers::buildQuery($params),
        ];

        return 'ddys_open_' . md5(json_encode($normalized));
    }

    public function get(string $key)
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return false;
        }

        $raw = file_get_contents($file);
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload) || !isset($payload['expires'])) {
            @unlink($file);
            return false;
        }

        if ((int) $payload['expires'] < time()) {
            @unlink($file);
            return false;
        }

        return $payload['value'] ?? false;
    }

    public function set(string $key, $value, int $ttl): void
    {
        if ($ttl <= 0 || !$this->ensureDir()) {
            return;
        }

        $payload = [
            'expires' => time() + $ttl,
            'created' => time(),
            'value' => $value,
        ];

        @file_put_contents($this->file($key), json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function flush(): int
    {
        if (!is_dir($this->dir)) {
            return 0;
        }

        $count = 0;
        $patterns = [
            $this->dir . DIRECTORY_SEPARATOR . 'ddys_open_*.json',
            $this->dir . DIRECTORY_SEPARATOR . 'request_*.lock',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function pruneLocks(int $maxAge = 3600): int
    {
        if (!is_dir($this->dir)) {
            return 0;
        }

        $count = 0;
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . 'request_*.lock') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - $maxAge && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    public function count(): int
    {
        if (!is_dir($this->dir)) {
            return 0;
        }

        return count(glob($this->dir . DIRECTORY_SEPARATOR . 'ddys_open_*.json') ?: []);
    }

    public function isWritable(): bool
    {
        if (is_dir($this->dir)) {
            return is_writable($this->dir);
        }

        $dir = dirname($this->dir);
        while ($dir && !is_dir($dir)) {
            $parent = dirname($dir);
            if ($parent === $dir) {
                return false;
            }
            $dir = $parent;
        }

        return is_dir($dir) && is_writable($dir);
    }

    public function dir(): string
    {
        return $this->dir;
    }

    private function ensureDir(): bool
    {
        if (is_dir($this->dir)) {
            return is_writable($this->dir);
        }

        return @mkdir($this->dir, 0755, true) || is_dir($this->dir);
    }

    private function file(string $key): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_\\-]/', '', $key) . '.json';
    }
}
