<?php

namespace TypechoPlugin\DdysOpen;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Shortcodes
{
    private Settings $settings;
    private ApiClient $client;
    private Renderer $renderer;

    public function __construct(?Settings $settings = null, ?ApiClient $client = null, ?Renderer $renderer = null)
    {
        $this->settings = $settings ?: new Settings();
        $this->client = $client ?: new ApiClient($this->settings, new Cache());
        $this->renderer = $renderer ?: new Renderer($this->settings);
    }

    public static function definitions(): array
    {
        return [
            'ddys_movies' => ['method' => 'movies', 'label' => 'Movies'],
            'ddys_latest' => ['method' => 'latest', 'label' => 'Latest movies'],
            'ddys_hot' => ['method' => 'hot', 'label' => 'Hot movies'],
            'ddys_search' => ['method' => 'search', 'label' => 'Search'],
            'ddys_suggest' => ['method' => 'suggest', 'label' => 'Suggestions'],
            'ddys_calendar' => ['method' => 'calendar', 'label' => 'Calendar'],
            'ddys_movie' => ['method' => 'movie', 'label' => 'Movie detail'],
            'ddys_sources' => ['method' => 'sources', 'label' => 'Movie sources'],
            'ddys_related' => ['method' => 'related', 'label' => 'Related movies'],
            'ddys_comments' => ['method' => 'comments', 'label' => 'Comments'],
            'ddys_collections' => ['method' => 'collections', 'label' => 'Collections'],
            'ddys_collection' => ['method' => 'collection', 'label' => 'Collection detail'],
            'ddys_shares' => ['method' => 'shares', 'label' => 'Shares'],
            'ddys_share' => ['method' => 'share', 'label' => 'Share detail'],
            'ddys_requests' => ['method' => 'requests', 'label' => 'Requests'],
            'ddys_activities' => ['method' => 'activities', 'label' => 'Activities'],
            'ddys_user' => ['method' => 'user', 'label' => 'User profile'],
            'ddys_types' => ['method' => 'types', 'label' => 'Types'],
            'ddys_genres' => ['method' => 'genres', 'label' => 'Genres'],
            'ddys_regions' => ['method' => 'regions', 'label' => 'Regions'],
            'ddys_request_form' => ['method' => 'requestForm', 'label' => 'Request form'],
        ];
    }

    public function parse(string $content): string
    {
        return preg_replace_callback('/\\[(ddys_[a-z_]+)([^\\]]*)\\]/', function (array $matches) {
            $tag = $matches[1];
            $atts = $this->parseAttributes($matches[2] ?? '');
            return $this->render($tag, $atts);
        }, $content);
    }

    public function render(string $tag, array $atts = []): string
    {
        $definitions = self::definitions();
        if (!isset($definitions[$tag])) {
            return '';
        }

        $method = $definitions[$tag]['method'];
        return $this->{$method}($atts);
    }

    public function movies(array $atts): string
    {
        $atts = $this->atts($atts, ['type' => '', 'genre' => '', 'region' => '', 'year' => '', 'sort' => 'latest', 'page' => 1, 'per_page' => 24]);
        return $this->renderGet('/movies', $this->query($atts, ['type', 'genre', 'region', 'year', 'sort', 'page', 'per_page']), $atts);
    }

    public function latest(array $atts): string
    {
        $atts = $this->atts($atts, ['type' => '', 'genre' => '', 'region' => '', 'year' => '', 'limit' => 12]);
        return $this->renderGet('/latest', $this->query($atts, ['type', 'genre', 'region', 'year', 'limit']), $atts);
    }

    public function hot(array $atts): string
    {
        $atts = $this->atts($atts, ['limit' => 10, 'type' => '', 'genre' => '', 'region' => '']);
        return $this->renderGet('/hot', $this->query($atts, ['limit', 'type', 'genre', 'region']), $atts);
    }

    public function search(array $atts): string
    {
        $atts = $this->atts($atts, ['q' => '', 'type' => 'movie', 'page' => 1, 'per_page' => 10, 'show_form' => true]);
        $q = isset($_GET['ddys_q']) ? trim((string) $_GET['ddys_q']) : (string) $atts['q'];
        $type = isset($_GET['ddys_type']) ? (string) $_GET['ddys_type'] : (string) $atts['type'];
        $type = Helpers::choice($type, ['movie', 'share', 'request'], 'movie');
        $html = Helpers::bool($atts['show_form']) ? $this->renderer->searchForm(['q' => $q, 'type' => $type]) : '';

        if (!$q) {
            return $this->renderer->wrap($html, $atts);
        }

        $payload = $this->client->get('/search', $this->query(array_merge($atts, ['q' => $q, 'type' => $type]), ['q', 'type', 'page', 'per_page']), $this->cacheOptions($atts));
        return ApiClient::isError($payload) ? $html . $this->renderer->error($payload) : $html . $this->renderer->listItems($payload, $atts);
    }

    public function suggest(array $atts): string
    {
        $atts = $this->atts($atts, ['q' => '', 'limit' => 8]);
        return $this->renderGet('/suggest', $this->query($atts, ['q', 'limit']), $atts);
    }

    public function calendar(array $atts): string
    {
        $atts = $this->atts($atts, ['year' => '', 'month' => '']);
        $payload = $this->client->get('/calendar', $this->query($atts, ['year', 'month']), $this->cacheOptions($atts));
        return ApiClient::isError($payload) ? $this->renderer->error($payload) : $this->renderer->calendar($payload, $atts);
    }

    public function movie(array $atts): string
    {
        $atts = $this->atts($atts, ['slug' => '']);
        if (!$atts['slug']) {
            return $this->renderer->error('Missing movie slug.');
        }
        $payload = $this->client->get('/movies/' . rawurlencode($atts['slug']), [], $this->cacheOptions($atts));
        return ApiClient::isError($payload) ? $this->renderer->error($payload) : $this->renderer->movieDetail($payload, $atts);
    }

    public function sources(array $atts): string
    {
        $atts = $this->atts($atts, ['slug' => '']);
        if (!$atts['slug']) {
            return $this->renderer->error('Missing movie slug.');
        }
        $payload = $this->client->get('/movies/' . rawurlencode($atts['slug']) . '/sources', [], $this->cacheOptions($atts));
        return ApiClient::isError($payload) ? $this->renderer->error($payload) : $this->renderer->sources($payload, $atts);
    }

    public function related(array $atts): string
    {
        $atts = $this->atts($atts, ['slug' => '']);
        if (!$atts['slug']) {
            return $this->renderer->error('Missing movie slug.');
        }
        return $this->renderGet('/movies/' . rawurlencode($atts['slug']) . '/related', [], $atts);
    }

    public function comments(array $atts): string
    {
        $atts = $this->atts($atts, ['slug' => '', 'page' => 1, 'per_page' => 20]);
        if (!$atts['slug']) {
            return $this->renderer->error('Missing movie slug.');
        }
        return $this->renderGet('/movies/' . rawurlencode($atts['slug']) . '/comments', $this->query($atts, ['page', 'per_page']), $atts);
    }

    public function collections(array $atts): string
    {
        $atts = $this->atts($atts, ['page' => 1, 'per_page' => 10]);
        return $this->renderGet('/collections', $this->query($atts, ['page', 'per_page']), $atts);
    }

    public function collection(array $atts): string
    {
        $atts = $this->atts($atts, ['slug' => '', 'page' => 1, 'per_page' => 12]);
        if (!$atts['slug']) {
            return $this->renderer->error('Missing collection slug.');
        }
        $payload = $this->client->get('/collections/' . rawurlencode($atts['slug']), $this->query($atts, ['page', 'per_page']), $this->cacheOptions($atts));
        return ApiClient::isError($payload) ? $this->renderer->error($payload) : $this->renderer->collectionDetail($payload, $atts);
    }

    public function shares(array $atts): string
    {
        $atts = $this->atts($atts, ['page' => 1, 'per_page' => 10]);
        return $this->renderGet('/shares', $this->query($atts, ['page', 'per_page']), $atts);
    }

    public function share(array $atts): string
    {
        $atts = $this->atts($atts, ['id' => 0]);
        $id = abs((int) $atts['id']);
        if (!$id) {
            return $this->renderer->error('Missing share ID.');
        }
        $payload = $this->client->get('/shares/' . $id, [], $this->cacheOptions($atts));
        return ApiClient::isError($payload) ? $this->renderer->error($payload) : $this->renderer->shareDetail($payload, $atts);
    }

    public function requests(array $atts): string
    {
        $atts = $this->atts($atts, ['page' => 1, 'per_page' => 10]);
        return $this->renderGet('/requests', $this->query($atts, ['page', 'per_page']), $atts);
    }

    public function activities(array $atts): string
    {
        $atts = $this->atts($atts, ['type' => '', 'page' => 1, 'per_page' => 10]);
        return $this->renderGet('/activities', $this->query($atts, ['type', 'page', 'per_page']), $atts);
    }

    public function user(array $atts): string
    {
        $atts = $this->atts($atts, ['username' => '']);
        if (!$atts['username']) {
            return $this->renderer->error('Missing username.');
        }
        return $this->renderGet('/user/' . rawurlencode($atts['username']), [], $atts);
    }

    public function types(array $atts): string
    {
        return $this->dictionary('/types', $atts);
    }

    public function genres(array $atts): string
    {
        return $this->dictionary('/genres', $atts);
    }

    public function regions(array $atts): string
    {
        return $this->dictionary('/regions', $atts);
    }

    public function requestForm(array $atts): string
    {
        return $this->renderer->requestForm();
    }

    private function dictionary(string $path, array $atts): string
    {
        $atts = $this->atts($atts, []);
        $payload = $this->client->get($path, [], $this->cacheOptions($atts));
        return ApiClient::isError($payload) ? $this->renderer->error($payload) : $this->renderer->dictionaries($payload, $atts);
    }

    private function renderGet(string $path, array $params, array $atts): string
    {
        $payload = $this->client->get($path, $params, $this->cacheOptions($atts));
        return ApiClient::isError($payload) ? $this->renderer->error($payload) : $this->renderer->listItems($payload, $atts);
    }

    private function atts(array $atts, array $defaults): array
    {
        $defaults = array_merge([
            'layout' => $this->settings->get('layout', 'grid'),
            'theme' => $this->settings->get('theme', 'auto'),
            'columns' => $this->settings->get('columns', 4),
            'target' => $this->settings->get('target', '_blank'),
            'show_poster' => true,
            'show_rating' => true,
            'cache_ttl' => '',
        ], $defaults);

        $atts = array_merge($defaults, $atts);
        foreach ($atts as $key => $value) {
            if (is_string($value)) {
                $atts[$key] = trim(strip_tags($value));
            }
        }

        $atts['columns'] = Helpers::intRange($atts['columns'], (int) $this->settings->get('columns', 4), 1, 6);
        return $atts;
    }

    private function query(array $atts, array $keys): array
    {
        $query = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $atts) || '' === $atts[$key]) {
                continue;
            }
            if (in_array($key, ['page', 'per_page', 'limit', 'year', 'month'], true)) {
                $number = $this->numericQueryValue($key, $atts[$key]);
                if (null !== $number) {
                    $query[$key] = $number;
                }
            } elseif ('type' === $key && in_array($atts[$key], Helpers::allowedTypes(), true)) {
                $query[$key] = $atts[$key];
            } elseif ('type' !== $key) {
                $query[$key] = trim((string) $atts[$key]);
            }
        }

        return $query;
    }

    private function numericQueryValue(string $key, $value): ?int
    {
        $number = abs((int) $value);
        if ('month' === $key) {
            return $number >= 1 && $number <= 12 ? $number : null;
        }
        if ('year' === $key) {
            return $number >= 1900 && $number <= 2099 ? $number : null;
        }
        if ('page' === $key) {
            return max(1, $number);
        }
        if (in_array($key, ['per_page', 'limit'], true)) {
            return Helpers::intRange($number, 12, 1, 50);
        }

        return $number;
    }

    private function cacheOptions(array $atts): array
    {
        return isset($atts['cache_ttl']) && '' !== $atts['cache_ttl'] ? ['cache_ttl' => abs((int) $atts['cache_ttl'])] : [];
    }

    private function parseAttributes(string $raw): array
    {
        $atts = [];
        preg_match_all("/([a-zA-Z0-9_\\-]+)\\s*=\\s*(?:\"([^\"]*)\"|'([^']*)'|([^\\s\\]]+))/", $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = strtolower($match[1]);
            $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);
            $atts[$key] = $value;
        }

        return $atts;
    }
}
