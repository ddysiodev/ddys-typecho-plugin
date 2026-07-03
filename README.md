# DDYS Typecho Plugin

[English](README.md) | [简体中文](README.zh-CN.md)

Official Typecho plugin for the [DDYS](https://ddys.io/) API.

It lets Typecho site owners embed DDYS content in posts, pages, and themes with shortcodes, caching, diagnostics, and a configurable API base URL.

## Features

- Typecho 1.3 plugin structure with `TypechoPlugin\DdysOpen`.
- Admin configuration for API Base URL, Site Base URL, cache TTLs, layout, theme, and optional authenticated features.
- 21 shortcodes covering all public DDYS display endpoints.
- File cache under `usr/cache/ddys-open`.
- Admin shortcode generator.
- Cache management panel.
- Diagnostics panel with API connection test.
- Responsive frontend CSS.
- Optional request form, disabled by default.
- Theme helper for direct template rendering.

## Install

1. Copy `DdysOpen` to `usr/plugins/DdysOpen`.
2. Open Typecho admin, then activate `DdysOpen`.
3. Open the plugin settings and confirm the API Base URL.
4. Use the DDYS panel to generate shortcodes.
5. Add shortcodes to posts or pages.

## Shortcodes

```text
[ddys_movies type="movie" per_page="24"]
[ddys_latest type="movie" limit="12" layout="grid"]
[ddys_hot limit="10" layout="list"]
[ddys_search]
[ddys_suggest q="interstellar"]
[ddys_calendar year="2026" month="7"]
[ddys_movie slug="interstellar"]
[ddys_sources slug="interstellar"]
[ddys_related slug="interstellar"]
[ddys_comments slug="interstellar"]
[ddys_collections per_page="10"]
[ddys_collection slug="best-sci-fi" per_page="12"]
[ddys_shares per_page="10"]
[ddys_share id="1081"]
[ddys_requests per_page="10"]
[ddys_activities type="share" per_page="10"]
[ddys_user username="diduan"]
[ddys_types]
[ddys_genres]
[ddys_regions]
[ddys_request_form]
```

## Theme Usage

```php
echo \TypechoPlugin\DdysOpen\Plugin::render('latest', [
    'limit' => 12,
    'layout' => 'grid',
]);
```

## Requirements

- Typecho 1.3+
- PHP 7.4+
- PHP cURL extension

## Cache

The plugin uses file cache in:

```text
usr/cache/ddys-open
```

If the cache directory is not writable, pages still render by requesting the API directly. The diagnostics panel shows cache status.

## License

GPL-2.0-or-later
