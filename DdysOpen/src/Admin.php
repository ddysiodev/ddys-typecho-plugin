<?php

namespace TypechoPlugin\DdysOpen;

use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Number;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Url;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Admin
{
    public static function config(Form $form): void
    {
        $defaults = Settings::defaults();

        $form->addInput(new Url('api_base_url', null, $defaults['api_base_url'], 'API Base URL', 'Official API or your own Worker Proxy endpoint.'));
        $form->addInput(new Url('site_base_url', null, $defaults['site_base_url'], 'Site Base URL', 'Used to build source links.'));
        $form->addInput(new Number('timeout', null, $defaults['timeout'], 'Request timeout', 'Seconds, from 1 to 30.'));
        $form->addInput(new Number('default_cache_ttl', null, $defaults['default_cache_ttl'], 'Default cache TTL', 'Seconds, 0 disables cache for unmatched endpoints.'));
        $form->addInput(new Number('dictionary_cache_ttl', null, $defaults['dictionary_cache_ttl'], 'Dictionary cache TTL', 'Used by types, genres, regions, and calendar.'));
        $form->addInput(new Number('fresh_cache_ttl', null, $defaults['fresh_cache_ttl'], 'Latest and hot cache TTL', 'Used by latest and hot lists.'));
        $form->addInput(new Number('list_cache_ttl', null, $defaults['list_cache_ttl'], 'List cache TTL', 'Used by movies, search, and collections.'));
        $form->addInput(new Number('detail_cache_ttl', null, $defaults['detail_cache_ttl'], 'Detail cache TTL', 'Used by movie, sources, related, collection, and share details.'));
        $form->addInput(new Number('community_cache_ttl', null, $defaults['community_cache_ttl'], 'Community cache TTL', 'Used by comments, shares, requests, activities, and users.'));
        $form->addInput(new Select('theme', ['auto' => 'Auto', 'light' => 'Light', 'dark' => 'Dark'], $defaults['theme'], 'Theme'));
        $form->addInput(new Select('layout', ['grid' => 'Grid', 'list' => 'List', 'compact' => 'Compact'], $defaults['layout'], 'Default layout'));
        $form->addInput(new Number('columns', null, $defaults['columns'], 'Default columns', 'From 1 to 6.'));
        $form->addInput(new Select('target', ['_blank' => '_blank', '_self' => '_self'], $defaults['target'], 'Link target'));
        $form->addInput(new Select('show_source_link', ['1' => 'Enabled', '0' => 'Disabled'], '1', 'Show source link'));
        $form->addInput(new Select('enable_styles', ['1' => 'Enabled', '0' => 'Disabled'], '1', 'Load frontend styles'));
        $form->addInput(new Select('enable_auth_features', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Enable authenticated features'));
        $form->addInput(new Select('enable_request_form', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Enable request form shortcode'));
        $form->addInput(new Password('api_key', null, '', 'DDYS API Key', 'Used only when authenticated features are enabled.'));
        $form->addInput(new Select('debug', ['0' => 'Disabled', '1' => 'Enabled'], '0', 'Debug mode'));
    }

    public static function renderPanel(): void
    {
        $settings = new Settings();
        $cache = new Cache();
        $options = $settings->all();
        $status = isset($_GET['ddys_status']) ? (string) $_GET['ddys_status'] : '';
        $count = isset($_GET['ddys_count']) ? abs((int) $_GET['ddys_count']) : 0;

        echo '<div class="ddys-typecho-admin">';
        echo '<div class="ddys-typecho-admin-head"><img src="' . Helpers::attr(Helpers::pluginUrl('assets/images/icon-32.png')) . '" width="32" height="32" alt=""><h2>DDYS Open</h2></div>';

        if ($status) {
            $message = self::statusMessage($status, $count);
            $class = in_array($status, ['api_ok', 'cache_flushed'], true) ? 'success' : 'error';
            echo '<div class="message ' . Helpers::attr($class) . '"><p>' . Helpers::e($message) . '</p></div>';
        }

        echo '<div class="ddys-typecho-admin-grid">';
        self::renderShortcodeGenerator();
        self::renderCachePanel($cache);
        self::renderDiagnosticsPanel($options, $cache);
        echo '</div></div>';
    }

    private static function renderShortcodeGenerator(): void
    {
        echo '<section class="ddys-typecho-panel"><h3>Shortcode Generator</h3>';
        echo '<label>Shortcode <select id="ddys-typecho-shortcode-kind">';
        foreach (Shortcodes::definitions() as $tag => $definition) {
            echo '<option value="' . Helpers::attr($tag) . '">' . Helpers::e($tag . ' - ' . $definition['label']) . '</option>';
        }
        echo '</select></label>';
        echo '<label>slug <input id="ddys-typecho-shortcode-slug" type="text" placeholder="interstellar"></label>';
        echo '<label>id <input id="ddys-typecho-shortcode-id" type="number" min="1"></label>';
        echo '<label>type <input id="ddys-typecho-shortcode-type" type="text" placeholder="movie"></label>';
        echo '<label>q <input id="ddys-typecho-shortcode-q" type="text" placeholder="keyword"></label>';
        echo '<label>year <input id="ddys-typecho-shortcode-year" type="number" min="1900" max="2099"></label>';
        echo '<label>month <input id="ddys-typecho-shortcode-month" type="number" min="1" max="12"></label>';
        echo '<label>limit <input id="ddys-typecho-shortcode-limit" type="number" min="1" max="50" value="12"></label>';
        echo '<label>per_page <input id="ddys-typecho-shortcode-per-page" type="number" min="1" max="50" value="10"></label>';
        echo '<label>layout <select id="ddys-typecho-shortcode-layout"><option value="grid">grid</option><option value="list">list</option><option value="compact">compact</option></select></label>';
        echo '<p><button type="button" class="btn primary" id="ddys-typecho-shortcode-build">Build</button> <button type="button" class="btn" id="ddys-typecho-shortcode-copy">Copy</button></p>';
        echo '<textarea id="ddys-typecho-shortcode-output" rows="5" readonly>[ddys_latest limit="12"]</textarea>';
        echo '<h4>Common examples</h4><pre>[ddys_latest type="movie" limit="12"]
[ddys_hot limit="10"]
[ddys_search]
[ddys_calendar year="2026" month="7"]
[ddys_movie slug="interstellar"]
[ddys_sources slug="interstellar"]
[ddys_collection slug="best-sci-fi" per_page="12"]</pre>';
        echo '</section>';
    }

    private static function renderCachePanel(Cache $cache): void
    {
        echo '<section class="ddys-typecho-panel"><h3>Cache</h3>';
        echo '<p>Cache directory: <code>' . Helpers::e($cache->dir()) . '</code></p>';
        echo '<p>Writable: <strong>' . ($cache->isWritable() ? 'Yes' : 'No') . '</strong></p>';
        echo '<p>Entries: <strong>' . $cache->count() . '</strong></p>';
        echo '<form method="post" action="' . Helpers::attr(Helpers::adminActionUrl(Plugin::ACTION_FLUSH)) . '">';
        echo '<button type="submit" class="btn">Flush DDYS cache</button>';
        echo '</form></section>';
    }

    private static function renderDiagnosticsPanel(array $options, Cache $cache): void
    {
        $typechoVersion = defined('__TYPECHO_VERSION__') ? __TYPECHO_VERSION__ : 'unknown';
        echo '<section class="ddys-typecho-panel"><h3>Diagnostics</h3>';
        echo '<table class="typecho-list-table"><tbody>';
        echo '<tr><th>Plugin</th><td>0.1.0</td></tr>';
        echo '<tr><th>Typecho</th><td>' . Helpers::e($typechoVersion) . '</td></tr>';
        echo '<tr><th>PHP</th><td>' . Helpers::e(PHP_VERSION) . '</td></tr>';
        echo '<tr><th>API Base</th><td>' . Helpers::e($options['api_base_url']) . '</td></tr>';
        echo '<tr><th>Site Base</th><td>' . Helpers::e($options['site_base_url']) . '</td></tr>';
        echo '<tr><th>Cache writable</th><td>' . ($cache->isWritable() ? 'Yes' : 'No') . '</td></tr>';
        echo '<tr><th>Authenticated features</th><td>' . ($options['enable_auth_features'] ? 'Enabled' : 'Disabled') . '</td></tr>';
        echo '<tr><th>Request form</th><td>' . ($options['enable_request_form'] ? 'Enabled' : 'Disabled') . '</td></tr>';
        echo '</tbody></table>';
        echo '<form method="post" action="' . Helpers::attr(Helpers::adminActionUrl(Plugin::ACTION_TEST)) . '">';
        echo '<button type="submit" class="btn primary">Test DDYS API</button>';
        echo '</form></section>';
    }

    private static function statusMessage(string $status, int $count): string
    {
        $messages = [
            'api_ok' => 'DDYS API connection succeeded.',
            'api_failed' => 'DDYS API connection failed. Check API Base URL and network access.',
            'cache_flushed' => 'DDYS cache flushed. Removed ' . $count . ' entries.',
            'permission_denied' => 'Permission denied.',
            'invalid_token' => 'Request verification failed.',
        ];

        return $messages[$status] ?? 'Action completed.';
    }
}
