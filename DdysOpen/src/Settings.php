<?php

namespace TypechoPlugin\DdysOpen;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Settings
{
    public const PLUGIN_NAME = 'DdysOpen';

    public static function defaults(): array
    {
        return [
            'api_base_url' => 'https://ddys.io/api/v1',
            'site_base_url' => 'https://ddys.io',
            'timeout' => 12,
            'default_cache_ttl' => 300,
            'dictionary_cache_ttl' => 86400,
            'fresh_cache_ttl' => 300,
            'list_cache_ttl' => 600,
            'detail_cache_ttl' => 1800,
            'community_cache_ttl' => 120,
            'theme' => 'auto',
            'layout' => 'grid',
            'columns' => 4,
            'target' => '_blank',
            'show_source_link' => true,
            'enable_styles' => true,
            'enable_auth_features' => false,
            'enable_request_form' => false,
            'api_key' => '',
            'debug' => false,
        ];
    }

    public function all(): array
    {
        $defaults = self::defaults();
        $values = [];

        try {
            $config = \Widget\Options::alloc()->plugin(self::PLUGIN_NAME);
            foreach ($defaults as $key => $default) {
                if (isset($config->{$key})) {
                    $values[$key] = $config->{$key};
                }
            }
        } catch (\Throwable $e) {
            $values = [];
        }

        return $this->normalize(array_merge($defaults, $values));
    }

    public function get(string $key, $default = null)
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function normalize(array $input): array
    {
        $defaults = self::defaults();

        return [
            'api_base_url' => Helpers::normalizeBaseUrl(Helpers::getArrayValue($input, 'api_base_url', ''), $defaults['api_base_url']),
            'site_base_url' => Helpers::normalizeBaseUrl(Helpers::getArrayValue($input, 'site_base_url', ''), $defaults['site_base_url']),
            'timeout' => Helpers::intRange(Helpers::getArrayValue($input, 'timeout', 12), 12, 1, 30),
            'default_cache_ttl' => Helpers::intRange(Helpers::getArrayValue($input, 'default_cache_ttl', 300), 300, 0, 604800),
            'dictionary_cache_ttl' => Helpers::intRange(Helpers::getArrayValue($input, 'dictionary_cache_ttl', 86400), 86400, 0, 604800),
            'fresh_cache_ttl' => Helpers::intRange(Helpers::getArrayValue($input, 'fresh_cache_ttl', 300), 300, 0, 604800),
            'list_cache_ttl' => Helpers::intRange(Helpers::getArrayValue($input, 'list_cache_ttl', 600), 600, 0, 604800),
            'detail_cache_ttl' => Helpers::intRange(Helpers::getArrayValue($input, 'detail_cache_ttl', 1800), 1800, 0, 604800),
            'community_cache_ttl' => Helpers::intRange(Helpers::getArrayValue($input, 'community_cache_ttl', 120), 120, 0, 604800),
            'theme' => Helpers::choice(Helpers::getArrayValue($input, 'theme', 'auto'), ['auto', 'light', 'dark'], 'auto'),
            'layout' => Helpers::choice(Helpers::getArrayValue($input, 'layout', 'grid'), ['grid', 'list', 'compact'], 'grid'),
            'columns' => Helpers::intRange(Helpers::getArrayValue($input, 'columns', 4), 4, 1, 6),
            'target' => in_array(Helpers::getArrayValue($input, 'target', '_blank'), ['_blank', '_self'], true) ? Helpers::getArrayValue($input, 'target', '_blank') : '_blank',
            'show_source_link' => Helpers::bool(Helpers::getArrayValue($input, 'show_source_link', false)),
            'enable_styles' => Helpers::bool(Helpers::getArrayValue($input, 'enable_styles', false)),
            'enable_auth_features' => Helpers::bool(Helpers::getArrayValue($input, 'enable_auth_features', false)),
            'enable_request_form' => Helpers::bool(Helpers::getArrayValue($input, 'enable_request_form', false)),
            'api_key' => trim((string) Helpers::getArrayValue($input, 'api_key', '')),
            'debug' => Helpers::bool(Helpers::getArrayValue($input, 'debug', false)),
        ];
    }
}
