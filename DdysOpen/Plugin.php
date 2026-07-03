<?php

namespace TypechoPlugin\DdysOpen;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/Cache.php';
require_once __DIR__ . '/src/ApiClient.php';
require_once __DIR__ . '/src/Renderer.php';
require_once __DIR__ . '/src/Shortcodes.php';
require_once __DIR__ . '/src/Admin.php';
require_once __DIR__ . '/Action.php';

/**
 * DDYS API integration for Typecho.
 *
 * @package DdysOpen
 * @author DDYS
 * @version 0.1.0
 * @link https://ddys.io/
 */
class Plugin implements PluginInterface
{
    public const VERSION = '0.1.0';
    public const ACTION_TEST = 'ddysopentest';
    public const ACTION_FLUSH = 'ddysopenflush';
    public const ACTION_REQUEST = 'ddysopenrequest';
    private const PANEL = 'DdysOpen/panel.php';
    private const PANEL_INDEX = 3;

    public static function activate()
    {
        \Typecho\Plugin::factory('Widget\Base\Contents')->contentEx = __CLASS__ . '::parseContent';
        \Typecho\Plugin::factory('Widget\Archive')->header = __CLASS__ . '::header';
        \Utils\Helper::addPanel(self::PANEL_INDEX, self::PANEL, 'DDYS Open', 'DDYS', 'administrator');
        \Utils\Helper::addAction(self::ACTION_TEST, __NAMESPACE__ . '\Action');
        \Utils\Helper::addAction(self::ACTION_FLUSH, __NAMESPACE__ . '\Action');
        \Utils\Helper::addAction(self::ACTION_REQUEST, __NAMESPACE__ . '\Action');
    }

    public static function deactivate()
    {
        \Utils\Helper::removePanel(self::PANEL_INDEX, self::PANEL);
        \Utils\Helper::removeAction(self::ACTION_TEST);
        \Utils\Helper::removeAction(self::ACTION_FLUSH);
        \Utils\Helper::removeAction(self::ACTION_REQUEST);
    }

    public static function config(Form $form)
    {
        Admin::config($form);
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function parseContent($content)
    {
        return (new Shortcodes())->parse((string) $content);
    }

    public static function render(string $tag, array $atts = []): string
    {
        $tag = 0 === strpos($tag, 'ddys_') ? $tag : 'ddys_' . ltrim($tag, '_');
        return (new Shortcodes())->render($tag, $atts);
    }

    public static function header(): void
    {
        if (!(new Settings())->get('enable_styles', true)) {
            return;
        }

        echo '<link rel="stylesheet" href="' . Helpers::attr(Helpers::pluginUrl('assets/css/frontend.css')) . '?v=' . self::VERSION . '">' . "\n";
    }
}
