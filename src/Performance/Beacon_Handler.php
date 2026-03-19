<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class Beacon_Handler
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        register_rest_route('mega-menu-ajax/v1', '/performance/lcp', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_lcp_beacon'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_lcp_beacon($request)
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'mega_menu_ajax_performance_nonce')) {
            return new \WP_Error('invalid_nonce', __('Invalid nonce', 'mega-menu-ajax'), ['status' => 403]);
        }

        $url_path = sanitize_text_field($request->get_param('url'));
        $lcp_entry = $request->get_param('lcp');

        if (empty($url_path) || empty($lcp_entry)) {
            return new \WP_Error('missing_data', __('Missing required data', 'mega-menu-ajax'), ['status' => 400]);
        }

        $url_path = $this->normalize_url($url_path);
        $transient_key = 'mega_menu_ajax_lcp_' . md5($url_path);
        
        $module = Module::get_instance();
        $settings = $module->get_settings();
        $ttl = $settings['lcp_cache_ttl'] ?? 7 * DAY_IN_SECONDS;

        $lcp_data = [
            'url' => $url_path,
            'selector' => sanitize_text_field($lcp_entry['selector'] ?? ''),
            'elementType' => sanitize_text_field($lcp_entry['elementType'] ?? ''),
            'imageUrl' => esc_url_raw($lcp_entry['imageUrl'] ?? ''),
            'elementId' => sanitize_text_field($lcp_entry['elementId'] ?? ''),
            'elementClass' => sanitize_text_field($lcp_entry['elementClass'] ?? ''),
            'backgroundImage' => esc_url_raw($lcp_entry['backgroundImage'] ?? ''),
            'size' => sanitize_text_field($lcp_entry['size'] ?? ''),
            'timestamp' => time(),
        ];

        $result = set_transient($transient_key, $lcp_data, $ttl);

        if ($result) {
            return rest_ensure_response([
                'success' => true,
                'message' => 'LCP data stored successfully',
                'data' => [
                    'url' => $url_path,
                    'cached' => true,
                ],
            ]);
        }

        return new \WP_Error('storage_failed', __('Failed to store LCP data', 'mega-menu-ajax'), ['status' => 500]);
    }

    private function normalize_url($url)
    {
        $parsed = wp_parse_url($url);
        $path = $parsed['path'] ?? '/';
        return strtok($path, '?');
    }

    public static function get_lcp_data($url_path = null)
    {
        if ($url_path === null) {
            $url_path = $_SERVER['REQUEST_URI'] ?? '/';
            $url_path = strtok($url_path, '?');
        }

        $transient_key = 'mega_menu_ajax_lcp_' . md5($url_path);
        return get_transient($transient_key);
    }
}
