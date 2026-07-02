# DDYS Typecho Plugin

[English](README.md) | [简体中文](README.zh-CN.md)

[低端影视](https://ddys.io/) Open API 的官方 Typecho 插件。

站长安装后，可以在 Typecho 文章、页面和主题模板里直接嵌入 DDYS 内容，并且支持缓存、诊断、短代码生成器和自定义 API Base URL。

## 功能

- 使用 Typecho 1.3 插件结构：`TypechoPlugin\DdysOpen`。
- 后台配置 API Base URL、Site Base URL、缓存时间、布局、主题和可选认证功能。
- 21 个短代码，覆盖 DDYS 公开展示接口。
- 文件缓存目录：`usr/cache/ddys-open`。
- 后台短代码生成器。
- 缓存管理面板。
- 诊断面板和 API 连通性测试。
- 响应式前台 CSS。
- 可选求片表单，默认关闭。
- 支持主题模板直接调用。

## 安装

1. 将 `DdysOpen` 目录复制到 `usr/plugins/DdysOpen`。
2. 进入 Typecho 后台，启用 `DdysOpen` 插件。
3. 打开插件设置，确认 API Base URL。
4. 在 DDYS Open 面板生成短代码。
5. 将短代码加入文章或页面。

## 短代码

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

## 主题调用

```php
echo \TypechoPlugin\DdysOpen\Plugin::render('latest', [
    'limit' => 12,
    'layout' => 'grid',
]);
```

## 环境要求

- Typecho 1.3+
- PHP 7.4+
- PHP cURL 扩展

## 缓存

插件使用文件缓存：

```text
usr/cache/ddys-open
```

如果缓存目录不可写，页面仍会直接请求 API 渲染内容。诊断面板会显示缓存状态。

## License

GPL-2.0-or-later
