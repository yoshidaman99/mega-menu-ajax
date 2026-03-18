<?php

namespace Mega_Menu_Ajax\Core;

defined('ABSPATH') || exit;

class Debug_Logger
{
    private $enabled = false;
    private $log_file;

    public function __construct()
    {
        $this->enabled = defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $this->log_file = WP_CONTENT_DIR . '/debug.log';
    }

    public function log($message, $context = [])
    {
        if (!$this->enabled) {
            return;
        }

        $entry = sprintf(
            '[%s] [Mega Menu Ajax] %s %s',
            current_time('mysql'),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        error_log($entry);
    }

    public function info($message, $context = [])
    {
        $this->log('[INFO] ' . $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->log('[WARNING] ' . $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('[ERROR] ' . $message, $context);
    }

    public function debug($message, $context = [])
    {
        if (defined('MEGA_MENU_AJAX_DEBUG') && MEGA_MENU_AJAX_DEBUG) {
            $this->log('[DEBUG] ' . $message, $context);
        }
    }
}
