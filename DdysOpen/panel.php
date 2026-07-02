<?php

use TypechoPlugin\DdysOpen\Admin;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

require_once __DIR__ . '/Plugin.php';

echo '<link rel="stylesheet" href="' . \TypechoPlugin\DdysOpen\Helpers::attr(\TypechoPlugin\DdysOpen\Helpers::pluginUrl('assets/css/admin.css')) . '?v=' . \TypechoPlugin\DdysOpen\Plugin::VERSION . '">';
echo '<script src="' . \TypechoPlugin\DdysOpen\Helpers::attr(\TypechoPlugin\DdysOpen\Helpers::pluginUrl('assets/js/admin.js')) . '?v=' . \TypechoPlugin\DdysOpen\Plugin::VERSION . '" defer></script>';

Admin::renderPanel();
