<?php

namespace TypechoPlugin\DdysOpen;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Renderer
{
    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?: new Settings();
    }

    public function wrap(string $html, array $args = []): string
    {
        $theme = Helpers::choice(Helpers::getArrayValue($args, 'theme', $this->settings->get('theme', 'auto')), ['auto', 'light', 'dark'], 'auto');
        $layout = Helpers::choice(Helpers::getArrayValue($args, 'layout', $this->settings->get('layout', 'grid')), ['grid', 'list', 'compact'], 'grid');
        $columns = Helpers::intRange(Helpers::getArrayValue($args, 'columns', $this->settings->get('columns', 4)), 4, 1, 6);
        $classes = ['ddys-typecho', 'ddys-typecho-theme-' . $theme, 'ddys-typecho-layout-' . $layout];

        if (!empty($args['class'])) {
            $classes[] = preg_replace('/[^a-zA-Z0-9_\\-]/', '', (string) $args['class']);
        }

        return '<div class="' . Helpers::attr(implode(' ', $classes)) . '" style="--ddys-columns:' . $columns . '">' . $html . '</div>';
    }

    public function error($error): string
    {
        $message = ApiClient::isError($error) ? ($error['message'] ?? 'DDYS API request failed.') : (string) $error;
        return $this->wrap('<div class="ddys-typecho-error">' . Helpers::e($message) . '</div>', ['class' => 'notice']);
    }

    public function emptyState(string $message = ''): string
    {
        $message = $message ?: 'No DDYS items found.';
        return '<div class="ddys-typecho-empty">' . Helpers::e($message) . '</div>';
    }

    public function listItems($payload, array $args = []): string
    {
        $data = Helpers::payloadData($payload);
        if (!is_array($data) || empty($data)) {
            return $this->wrap($this->emptyState(), $args);
        }

        $items = $this->normalizeListItems($data);
        if (empty($items)) {
            return $this->wrap($this->emptyState(), $args);
        }

        $html = '<div class="ddys-typecho-items">';
        foreach ($items as $item) {
            if (is_array($item)) {
                $html .= $this->card($item, $args);
            }
        }
        $html .= '</div>';
        $html .= $this->paginationMeta(Helpers::payloadMeta($payload));
        $html .= $this->sourceLink();

        return $this->wrap($html, $args);
    }

    public function movieDetail($payload, array $args = []): string
    {
        $data = Helpers::payloadData($payload);
        if (!is_array($data)) {
            return $this->wrap($this->emptyState(), $args);
        }

        $html = '<article class="ddys-typecho-detail">';
        $html .= $this->card($data, array_merge($args, ['detail' => true]));

        $intro = Helpers::getArrayValue($data, 'description', Helpers::getArrayValue($data, 'intro', ''));
        if ($intro) {
            $html .= '<div class="ddys-typecho-description">' . nl2br(Helpers::e($intro)) . '</div>';
        }

        $html .= '</article>';
        $html .= $this->sourceLink((string) Helpers::getArrayValue($data, 'url', ''));

        return $this->wrap($html, array_merge($args, ['class' => 'detail-wrap']));
    }

    public function sources($payload, array $args = []): string
    {
        $data = Helpers::payloadData($payload);
        if (!is_array($data) || empty($data)) {
            return $this->wrap($this->emptyState('No sources found.'), $args);
        }

        $groups = $this->normalizeSourceGroups($data);
        if (empty($groups)) {
            return $this->wrap($this->emptyState('No sources found.'), $args);
        }

        $html = '<div class="ddys-typecho-sources">';
        foreach ($groups as $name => $resources) {
            $html .= '<section class="ddys-typecho-source-group"><h3>' . Helpers::e($name) . '</h3><ul>';
            if (is_array($resources)) {
                foreach ($resources as $resource) {
                    if (!is_array($resource)) {
                        continue;
                    }

                    $title = Helpers::getArrayValue($resource, 'title', Helpers::getArrayValue($resource, 'name', Helpers::getArrayValue($resource, 'download_type', 'Resource')));
                    $url = Helpers::getArrayValue($resource, 'url', Helpers::getArrayValue($resource, 'link', ''));
                    $meta = array_filter([
                        Helpers::getArrayValue($resource, 'quality', ''),
                        Helpers::getArrayValue($resource, 'format', ''),
                        Helpers::getArrayValue($resource, 'size', ''),
                    ]);
                    $html .= '<li>' . $this->resourceLinks((string) $title, (string) $url);
                    if (!empty($meta)) {
                        $html .= ' <span class="ddys-typecho-card-meta">' . Helpers::e(implode(' / ', array_map('strval', $meta))) . '</span>';
                    }
                    $html .= '</li>';
                }
            }
            $html .= '</ul></section>';
        }

        $html .= '</div>';
        return $this->wrap($html, $args);
    }

    public function collectionDetail($payload, array $args = []): string
    {
        $data = Helpers::payloadData($payload);
        if (!is_array($data)) {
            return $this->wrap($this->emptyState(), $args);
        }

        $movies = isset($data['movies']) && is_array($data['movies']) ? $data['movies'] : [];
        $html = '<article class="ddys-typecho-detail">';
        $html .= '<h2>' . Helpers::e(Helpers::getArrayValue($data, 'title', 'Collection')) . '</h2>';

        if (!empty($data['description'])) {
            $html .= '<div class="ddys-typecho-description">' . nl2br(Helpers::e($data['description'])) . '</div>';
        }

        $html .= '</article>';
        if (!empty($movies)) {
            $html .= '<div class="ddys-typecho-items">';
            foreach ($movies as $movie) {
                if (is_array($movie)) {
                    $html .= $this->card($movie, $args);
                }
            }
            $html .= '</div>';
        } else {
            $html .= $this->emptyState('No movies found in this collection.');
        }

        $html .= $this->paginationMeta(Helpers::payloadMeta($payload));
        $html .= $this->sourceLink((string) Helpers::getArrayValue($data, 'url', ''));

        return $this->wrap($html, array_merge($args, ['class' => 'collection-detail']));
    }

    public function shareDetail($payload, array $args = []): string
    {
        $data = Helpers::payloadData($payload);
        if (!is_array($data)) {
            return $this->wrap($this->emptyState(), $args);
        }

        $html = '<article class="ddys-typecho-detail">';
        $html .= '<h2>' . Helpers::e(Helpers::getArrayValue($data, 'title', 'Share')) . '</h2>';
        $meta = array_filter([
            Helpers::getArrayValue($data, 'resource_type', ''),
            Helpers::getArrayValue($data, 'quality', ''),
            Helpers::getArrayValue($data, 'username', ''),
        ]);
        if (!empty($meta)) {
            $html .= '<div class="ddys-typecho-card-meta">' . Helpers::e(implode(' / ', array_map('strval', $meta))) . '</div>';
        }
        if (!empty($data['note'])) {
            $html .= '<div class="ddys-typecho-description">' . nl2br(Helpers::e($data['note'])) . '</div>';
        }

        if (!empty($data['resources']) && is_array($data['resources'])) {
            $html .= '<h3>Resources</h3><ul class="ddys-typecho-resource-list">';
            foreach ($data['resources'] as $resource) {
                if (!is_array($resource)) {
                    continue;
                }
                $title = Helpers::getArrayValue($resource, 'type', 'Resource');
                $url = Helpers::getArrayValue($resource, 'url', '');
                $html .= '<li>' . $this->resourceLinks((string) $title, (string) $url) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</article>';
        $html .= $this->sourceLink((string) Helpers::getArrayValue($data, 'url', ''));

        return $this->wrap($html, array_merge($args, ['class' => 'share-detail']));
    }

    public function calendar($payload, array $args = []): string
    {
        $data = Helpers::payloadData($payload);
        if (!is_array($data) || empty($data)) {
            return $this->wrap($this->emptyState('No calendar data found.'), $args);
        }

        $days = $this->extractCalendarDays($data);
        if (empty($days)) {
            return $this->wrap($this->emptyState('No calendar data found.'), $args);
        }

        $html = '<div class="ddys-typecho-calendar">';
        foreach ($days as $day => $items) {
            $html .= '<section class="ddys-typecho-calendar-day"><h3>' . Helpers::e($day) . '</h3><div class="ddys-typecho-items">';
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $html .= $this->card($item, $args);
                    }
                }
            }
            $html .= '</div></section>';
        }
        $html .= '</div>';

        return $this->wrap($html, array_merge($args, ['class' => 'calendar-wrap']));
    }

    public function dictionaries($payload, array $args = []): string
    {
        $data = Helpers::payloadData($payload);
        if (!is_array($data) || empty($data)) {
            return $this->wrap($this->emptyState(), $args);
        }

        $html = '<div class="ddys-typecho-taxonomy-list">';
        foreach ($data as $key => $item) {
            if (is_array($item)) {
                $name = Helpers::getArrayValue($item, 'name', '');
                $code = Helpers::getArrayValue($item, 'code', '');
            } else {
                $name = (string) $item;
                $code = is_string($key) ? $key : '';
            }
            if ('' === $name) {
                continue;
            }

            $html .= '<span class="ddys-typecho-pill"><span>' . Helpers::e($name) . '</span>';
            if ($code) {
                $html .= '<code>' . Helpers::e($code) . '</code>';
            }
            $html .= '</span>';
        }
        $html .= '</div>';

        return $this->wrap($html, $args);
    }

    public function searchForm(array $atts = []): string
    {
        $q = isset($_GET['ddys_q']) ? trim((string) $_GET['ddys_q']) : (string) Helpers::getArrayValue($atts, 'q', '');
        $type = isset($_GET['ddys_type']) ? (string) $_GET['ddys_type'] : (string) Helpers::getArrayValue($atts, 'type', 'movie');
        $type = Helpers::choice($type, ['movie', 'share', 'request'], 'movie');

        $html = '<form class="ddys-typecho-search-form" method="get">';
        $html .= '<label><span class="ddys-typecho-sr">Search DDYS</span><input type="search" name="ddys_q" value="' . Helpers::attr($q) . '" placeholder="Search movies, shares, or requests"></label>';
        $html .= '<select name="ddys_type">';
        foreach (['movie' => 'Movie', 'share' => 'Share', 'request' => 'Request'] as $value => $label) {
            $html .= '<option value="' . Helpers::attr($value) . '"' . ($type === $value ? ' selected' : '') . '>' . Helpers::e($label) . '</option>';
        }
        $html .= '</select><button type="submit">Search</button></form>';

        return $html;
    }

    public function requestForm(): string
    {
        if (!$this->settings->get('enable_auth_features', false) || !$this->settings->get('enable_request_form', false)) {
            return $this->wrap('<div class="ddys-typecho-empty">DDYS request form is disabled.</div>');
        }

        $html = '';
        if (isset($_GET['ddys_request_status'])) {
            $status = Helpers::choice($_GET['ddys_request_status'], ['ok', 'failed', 'rate_limited', 'missing_title', 'invalid_token'], 'failed');
            $messages = [
                'ok' => 'Request submitted.',
                'failed' => 'Request submission failed.',
                'rate_limited' => 'Please wait before submitting again.',
                'missing_title' => 'Please enter a title.',
                'invalid_token' => 'Request verification failed.',
            ];
            $class = 'ok' === $status ? 'empty' : 'error';
            $html .= '<div class="ddys-typecho-' . $class . '">' . Helpers::e($messages[$status]) . '</div>';
        }

        $action = Helpers::siteActionUrl(Plugin::ACTION_REQUEST);
        $html .= '<form class="ddys-typecho-request-form" method="post" action="' . Helpers::attr($action) . '">';
        $html .= '<input type="hidden" name="ddys_ref" value="' . Helpers::attr(Helpers::currentUrl()) . '">';
        $html .= '<label>Title<input type="text" name="ddys_title" required maxlength="255"></label>';
        $html .= '<label>Year<input type="number" name="ddys_year" min="1900" max="2099"></label>';
        $html .= '<label>Type<select name="ddys_type"><option value=""></option><option value="movie">Movie</option><option value="series">Series</option><option value="variety">Variety</option><option value="anime">Anime</option></select></label>';
        $html .= '<label>Description<textarea name="ddys_description" maxlength="1000"></textarea></label>';
        $html .= '<button type="submit" name="ddys_request_submit" value="1">Submit request</button>';
        $html .= '</form>';

        return $this->wrap($html);
    }

    private function card(array $item, array $args = []): string
    {
        $siteBase = rtrim((string) $this->settings->get('site_base_url', 'https://ddys.io'), '/');
        $title = Helpers::getArrayValue($item, 'title', Helpers::getArrayValue($item, 'name', Helpers::getArrayValue($item, 'username', 'Untitled')));
        $url = (string) Helpers::getArrayValue($item, 'url', '');
        $poster = (string) Helpers::getArrayValue($item, 'poster', Helpers::getArrayValue($item, 'avatar', ''));
        $rating = Helpers::getArrayValue($item, 'rating', '');
        $year = Helpers::getArrayValue($item, 'year', '');
        $type = Helpers::getArrayValue($item, 'type', Helpers::getArrayValue($item, 'type_code', ''));
        $target = Helpers::getArrayValue($args, 'target', $this->settings->get('target', '_blank'));
        $showPoster = Helpers::bool(Helpers::getArrayValue($args, 'show_poster', true));
        $showRating = Helpers::bool(Helpers::getArrayValue($args, 'show_rating', true));
        $href = $url ? $this->absoluteSiteUrl($siteBase, $url) : '';

        $html = '<article class="ddys-typecho-card">';
        if ($showPoster && $poster) {
            $html .= '<div class="ddys-typecho-card-poster"><img src="' . Helpers::url($poster) . '" alt="' . Helpers::attr($title) . '" loading="lazy"></div>';
        }
        $html .= '<div class="ddys-typecho-card-body"><h3 class="ddys-typecho-card-title">';
        if ($href) {
            $html .= '<a href="' . Helpers::url($href) . '" target="' . Helpers::attr($target) . '" rel="noopener">' . Helpers::e($title) . '</a>';
        } else {
            $html .= Helpers::e($title);
        }
        $html .= '</h3>';

        $meta = array_filter([$year, $type, $showRating ? $rating : '']);
        if (!empty($meta)) {
            $html .= '<div class="ddys-typecho-card-meta">' . Helpers::e(implode(' / ', array_map('strval', $meta))) . '</div>';
        }

        $summary = Helpers::getArrayValue($item, 'description', Helpers::getArrayValue($item, 'content', ''));
        if ($summary) {
            $html .= '<div class="ddys-typecho-card-summary">' . Helpers::e($this->trimWords(strip_tags((string) $summary), 120)) . '</div>';
        }

        $html .= '</div></article>';

        return $html;
    }

    private function normalizeListItems(array $data): array
    {
        if ($this->looksLikeSingleItem($data)) {
            return [$data];
        }

        if (Helpers::arrayIsList($data)) {
            return $data;
        }

        $items = [];
        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            if ($this->looksLikeSingleItem($value)) {
                $items[] = $value;
                continue;
            }

            if (Helpers::arrayIsList($value)) {
                foreach ($value as $nested) {
                    if (is_array($nested)) {
                        $items[] = $nested;
                    }
                }
            }
        }

        return $items;
    }

    private function normalizeSourceGroups(array $data): array
    {
        if (isset($data['online']) || isset($data['download'])) {
            return array_filter([
                'Online' => isset($data['online']) && is_array($data['online']) ? $data['online'] : [],
                'Download' => isset($data['download']) && is_array($data['download']) ? $data['download'] : [],
            ]);
        }

        if (Helpers::arrayIsList($data)) {
            foreach ($data as $item) {
                if (is_array($item) && (isset($item['url']) || isset($item['link']) || isset($item['resources']))) {
                    return ['Resources' => $data];
                }
            }
        }

        $groups = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['resources']) && is_array($value['resources'])) {
                $groups[(string) Helpers::getArrayValue($value, 'name', (string) $key)] = $value['resources'];
            } elseif (is_array($value)) {
                $groups[(string) $key] = $value;
            }
        }

        return $groups;
    }

    private function resourceLinks(string $title, string $url): string
    {
        if (!$url) {
            return Helpers::e($title);
        }

        $parts = array_filter(explode('#', $url));
        $links = [];
        foreach ($parts as $index => $part) {
            $label = $title;
            $href = $part;
            if (false !== strpos($part, '$')) {
                [$label, $href] = array_pad(explode('$', $part, 2), 2, '');
            } elseif (count($parts) > 1) {
                $label = $title . ' ' . ($index + 1);
            }

            $safeUrl = Helpers::url($href, Helpers::allowedResourceProtocols());
            if ($safeUrl) {
                $links[] = '<a href="' . $safeUrl . '" target="' . Helpers::attr($this->settings->get('target', '_blank')) . '" rel="noopener">' . Helpers::e($label ?: $title) . '</a>';
            }
        }

        return empty($links) ? Helpers::e($title) : implode(' ', $links);
    }

    private function paginationMeta(array $meta): string
    {
        if (empty($meta['total'])) {
            return '';
        }

        $page = isset($meta['page']) ? abs((int) $meta['page']) : 1;
        $total = abs((int) $meta['total']);
        return '<div class="ddys-typecho-meta">Page ' . $page . ' / ' . $total . ' total items</div>';
    }

    private function sourceLink(string $path = ''): string
    {
        if (!$this->settings->get('show_source_link', true)) {
            return '';
        }

        $href = $this->absoluteSiteUrl(rtrim((string) $this->settings->get('site_base_url', 'https://ddys.io'), '/'), $path ?: '/');
        return '<div class="ddys-typecho-source-link"><a href="' . Helpers::url($href) . '" target="_blank" rel="noopener">View on DDYS</a></div>';
    }

    private function absoluteSiteUrl(string $siteBase, string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return $siteBase . '/' . ltrim($url, '/');
    }

    private function looksLikeSingleItem(array $data): bool
    {
        return isset($data['id']) || isset($data['slug']) || isset($data['title']) || isset($data['username']);
    }

    private function extractCalendarDays(array $data): array
    {
        if (isset($data['days']) && is_array($data['days'])) {
            return $data['days'];
        }

        $days = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$|^\\d{1,2}$/', (string) $key)) {
                $days[(string) $key] = $value;
            }
        }

        return $days;
    }

    private function trimWords(string $value, int $length): string
    {
        $value = trim(preg_replace('/\\s+/', ' ', $value));
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > $length ? mb_substr($value, 0, $length, 'UTF-8') . '...' : $value;
        }

        return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
    }
}
