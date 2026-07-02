<?php

namespace TypechoPlugin\DdysOpen;

use Typecho\Http\Client;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class ApiClient
{
    private Settings $settings;
    private Cache $cache;
    private ?array $lastError = null;

    public function __construct(?Settings $settings = null, ?Cache $cache = null)
    {
        $this->settings = $settings ?: new Settings();
        $this->cache = $cache ?: new Cache();
    }

    public function get(string $path, array $params = [], array $options = [])
    {
        return $this->request('GET', $path, $params, null, $options);
    }

    public function post(string $path, array $body = [], array $options = [])
    {
        return $this->request('POST', $path, [], $body, $options);
    }

    public function request(string $method, string $path, array $params = [], ?array $body = null, array $options = [])
    {
        $method = strtoupper($method);
        $settings = $this->settings->all();
        $baseUrl = rtrim($settings['api_base_url'], '/');
        $path = '/' . ltrim($path, '/');
        $params = Helpers::buildQuery($params);
        $ttl = isset($options['cache_ttl']) ? abs((int) $options['cache_ttl']) : $this->ttlForPath($path, $settings);
        $useCache = 'GET' === $method && empty($options['no_cache']);
        $cacheKey = $this->cache->key($method, $baseUrl, $path, $params);

        if ($useCache) {
            $cached = $this->cache->get($cacheKey);
            if (false !== $cached) {
                return $cached;
            }
        }

        $client = Client::get();
        if (!$client) {
            return $this->error('ddys_http_unavailable', 'PHP cURL extension is required for DDYS API requests.', 0);
        }

        $url = $baseUrl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        try {
            $client->setMethod($method)
                ->setTimeout((int) $settings['timeout'])
                ->setHeader('Accept', 'application/json')
                ->setHeader('User-Agent', 'ddys-typecho-plugin/0.1.0; Typecho');

            if (!empty($options['auth']) && !empty($settings['api_key'])) {
                $client->setHeader('Authorization', 'Bearer ' . $settings['api_key']);
            }

            if (null !== $body) {
                $client->setJson($body, $method);
            }

            $client->send($url);
            $status = $client->getResponseStatus();
            $raw = $client->getResponseBody();
        } catch (\Throwable $e) {
            return $this->error('ddys_http_error', $e->getMessage(), 0);
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            return $this->error('ddys_invalid_json', 'DDYS API returned invalid JSON.', $status);
        }

        if ($status < 200 || $status >= 300 || !Helpers::isSuccessResponse($json)) {
            $message = isset($json['message']) ? (string) $json['message'] : 'DDYS API request failed with HTTP ' . $status . '.';
            return $this->error('ddys_api_error', $message, $status, $json);
        }

        if ($useCache && $ttl > 0) {
            $this->cache->set($cacheKey, $json, $ttl);
        }

        return $json;
    }

    public function lastError(): ?array
    {
        return $this->lastError;
    }

    public static function isError($value): bool
    {
        return is_array($value) && isset($value['ddys_error']) && true === $value['ddys_error'];
    }

    private function error(string $code, string $message, int $status = 0, ?array $payload = null): array
    {
        $this->lastError = [
            'ddys_error' => true,
            'code' => $code,
            'message' => $message,
            'status' => $status,
            'payload' => $payload,
        ];

        return $this->lastError;
    }

    private function ttlForPath(string $path, array $settings): int
    {
        if (preg_match('#^/(types|genres|regions|calendar)$#', $path)) {
            return (int) $settings['dictionary_cache_ttl'];
        }

        if (preg_match('#^/(latest|hot)$#', $path)) {
            return (int) $settings['fresh_cache_ttl'];
        }

        if (preg_match('#^/(movies/[^/]+|movies/[^/]+/sources|movies/[^/]+/related|collections/[^/]+|shares/[0-9]+)$#', $path)) {
            return (int) $settings['detail_cache_ttl'];
        }

        if (preg_match('#^/(movies/[^/]+/comments|suggest|shares|requests|activities|user/)#', $path)) {
            return (int) $settings['community_cache_ttl'];
        }

        if (preg_match('#^/(movies|search|collections)#', $path)) {
            return (int) $settings['list_cache_ttl'];
        }

        return (int) $settings['default_cache_ttl'];
    }
}
