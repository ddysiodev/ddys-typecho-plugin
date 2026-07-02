<?php

namespace TypechoPlugin\DdysOpen;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Helpers
{
    public static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function attr($value): string
    {
        return self::e($value);
    }

    public static function url($value, array $protocols = ['http', 'https']): string
    {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        if (0 === strpos($value, '//')) {
            return '';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if ($scheme && !in_array($scheme, $protocols, true)) {
            return '';
        }

        return self::attr($value);
    }

    public static function bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function intRange($value, int $default, int $min, int $max): int
    {
        $number = abs((int) $value);
        if ($number < $min) {
            return $default;
        }
        if ($number > $max) {
            return $max;
        }

        return $number;
    }

    public static function choice($value, array $allowed, string $default): string
    {
        $value = strtolower(preg_replace('/[^a-z0-9_\\-]/i', '', (string) $value));
        return in_array($value, $allowed, true) ? $value : $default;
    }

    public static function normalizeBaseUrl($value, string $default): string
    {
        $url = rtrim(trim((string) $value), '/');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $default;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ('https' !== $scheme) {
            return $default;
        }

        return $url;
    }

    public static function getArrayValue(array $array, string $key, $default = null)
    {
        return array_key_exists($key, $array) ? $array[$key] : $default;
    }

    public static function arrayIsList(array $array): bool
    {
        $index = 0;
        foreach (array_keys($array) as $key) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }

        return true;
    }

    public static function allowedResourceProtocols(): array
    {
        return ['http', 'https', 'magnet', 'ed2k', 'thunder'];
    }

    public static function allowedTypes(): array
    {
        return ['movie', 'series', 'variety', 'anime'];
    }

    public static function isSuccessResponse($payload): bool
    {
        return is_array($payload) && (!isset($payload['success']) || true === (bool) $payload['success']);
    }

    public static function payloadData($payload)
    {
        if (is_array($payload) && array_key_exists('data', $payload)) {
            return $payload['data'];
        }

        return $payload;
    }

    public static function payloadMeta($payload): array
    {
        return is_array($payload) && isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];
    }

    public static function buildQuery(array $params): array
    {
        $output = [];
        foreach ($params as $key => $value) {
            if (null === $value || '' === $value || [] === $value) {
                continue;
            }
            $output[$key] = $value;
        }

        ksort($output);
        return $output;
    }

    public static function queryString(array $params): string
    {
        $params = self::buildQuery($params);
        return empty($params) ? '' : http_build_query($params);
    }

    public static function pluginUrl(string $path = ''): string
    {
        try {
            $options = \Widget\Options::alloc();
            $base = rtrim((string) $options->pluginUrl, '/') . '/DdysOpen/';
        } catch (\Throwable $e) {
            $base = '/usr/plugins/DdysOpen/';
        }

        return $base . ltrim($path, '/');
    }

    public static function siteActionUrl(string $action): string
    {
        $path = '/action/' . preg_replace('/[^a-zA-Z0-9_]/', '', $action);

        try {
            return \Widget\Security::alloc()->getIndex($path);
        } catch (\Throwable $e) {
            try {
                return rtrim((string) \Widget\Options::alloc()->index, '/') . $path;
            } catch (\Throwable $inner) {
                return $path;
            }
        }
    }

    public static function adminActionUrl(string $action): string
    {
        $path = 'action.php?action=' . rawurlencode($action);

        try {
            return \Widget\Security::alloc()->getAdminUrl($path);
        } catch (\Throwable $e) {
            try {
                return \Widget\Options::alloc()->adminUrl($path, true);
            } catch (\Throwable $inner) {
                return $path;
            }
        }
    }

    public static function currentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && 'off' !== strtolower((string) $_SERVER['HTTPS'])) ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        return $host ? $scheme . '://' . $host . $uri : $uri;
    }

    public static function safeReturnUrl(string $url, string $fallback = '/'): string
    {
        $url = trim($url);
        if ('' === $url || 0 === strpos($url, '//')) {
            return $fallback;
        }

        $parts = parse_url($url);
        if (false === $parts) {
            return $fallback;
        }

        if (empty($parts['host'])) {
            return '/' . ltrim($url, '/');
        }

        $currentHost = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
        $targetHost = strtolower((string) $parts['host']);
        return $currentHost && $targetHost === $currentHost ? $url : $fallback;
    }

    public static function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
            exit;
        }

        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        exit;
    }
}
