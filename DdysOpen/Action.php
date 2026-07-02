<?php

namespace TypechoPlugin\DdysOpen;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Action extends \Typecho\Widget implements \Widget\ActionInterface
{
    public function action()
    {
        $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

        if (Plugin::ACTION_TEST === $action) {
            $this->adminTestApi();
            return;
        }

        if (Plugin::ACTION_FLUSH === $action) {
            $this->adminFlushCache();
            return;
        }

        if (Plugin::ACTION_REQUEST === $action) {
            $this->submitRequest();
            return;
        }

        $this->response->setStatus(404);
        echo 'Not found';
    }

    private function adminTestApi(): void
    {
        if (!$this->isAdmin()) {
            $this->redirectAdmin('permission_denied');
        }

        $this->protect(true);
        $client = new ApiClient(new Settings(), new Cache());
        $result = $client->get('/types', [], ['no_cache' => true]);
        $this->redirectAdmin(ApiClient::isError($result) ? 'api_failed' : 'api_ok');
    }

    private function adminFlushCache(): void
    {
        if (!$this->isAdmin()) {
            $this->redirectAdmin('permission_denied');
        }

        $this->protect(true);
        $count = (new Cache())->flush();
        $this->redirectAdmin('cache_flushed', ['ddys_count' => $count]);
    }

    private function submitRequest(): void
    {
        $settings = new Settings();
        if (!$settings->get('enable_auth_features', false) || !$settings->get('enable_request_form', false)) {
            $this->redirectRequest('failed');
        }

        $this->protect(false);
        if (empty($_POST['ddys_request_submit'])) {
            $this->redirectRequest('failed');
        }

        $cache = new Cache();
        $cache->pruneLocks();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^a-fA-F0-9:\\.]/', '', (string) $_SERVER['REMOTE_ADDR']) : 'unknown';
        $lockFile = rtrim($cache->dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'request_' . md5($ip) . '.lock';
        if (is_file($lockFile) && filemtime($lockFile) > time() - 30) {
            $this->redirectRequest('rate_limited');
        }

        $title = isset($_POST['ddys_title']) ? trim(strip_tags((string) $_POST['ddys_title'])) : '';
        $year = isset($_POST['ddys_year']) ? abs((int) $_POST['ddys_year']) : 0;
        $type = isset($_POST['ddys_type']) ? Helpers::choice($_POST['ddys_type'], Helpers::allowedTypes(), '') : '';
        $description = isset($_POST['ddys_description']) ? trim(strip_tags((string) $_POST['ddys_description'])) : '';

        if ('' === $title) {
            $this->redirectRequest('missing_title');
        }

        $body = ['title' => $title];
        if ($year >= 1900 && $year <= 2099) {
            $body['year'] = $year;
        }
        if ($type) {
            $body['type'] = $type;
        }
        if ($description) {
            $body['description'] = function_exists('mb_substr') ? mb_substr($description, 0, 1000, 'UTF-8') : substr($description, 0, 1000);
        }

        if (!is_dir($cache->dir())) {
            @mkdir($cache->dir(), 0755, true);
        }
        @touch($lockFile);

        $result = (new ApiClient($settings, $cache))->post('/requests', $body, ['auth' => true]);
        $this->redirectRequest(ApiClient::isError($result) ? 'failed' : 'ok');
    }

    private function isAdmin(): bool
    {
        try {
            return \Widget\User::alloc()->pass('administrator', true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function protect(bool $admin = false): void
    {
        try {
            \Widget\Security::alloc()->protect();
        } catch (\Throwable $e) {
            if ($admin) {
                $this->redirectAdmin('invalid_token');
            }
            $this->redirectRequest('invalid_token');
        }
    }

    private function redirectAdmin(string $status, array $extra = []): void
    {
        $params = array_merge(['panel' => 'DdysOpen/panel.php', 'ddys_status' => $status], $extra);
        $url = 'extending.php?' . http_build_query($params);
        try {
            $this->response->redirect(\Widget\Options::alloc()->adminUrl($url, true));
        } catch (\Throwable $e) {
            Helpers::redirect($url);
        }
    }

    private function redirectRequest(string $status): void
    {
        $ref = isset($_POST['ddys_ref']) ? (string) $_POST['ddys_ref'] : '';
        if (!$ref) {
            $ref = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '/';
        }

        $ref = Helpers::safeReturnUrl($ref, '/');
        $sep = false === strpos($ref, '?') ? '?' : '&';
        Helpers::redirect($ref . $sep . 'ddys_request_status=' . rawurlencode($status));
    }
}
