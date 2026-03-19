<?php

namespace Mega_Menu_Ajax\Ajax;

defined('ABSPATH') || exit;

class Page_Preload
{
    public function __construct()
    {
        add_action('wp_ajax_mega_menu_ajax_preload_page', [$this, 'ajax_preload_page']);
        add_action('wp_ajax_nopriv_mega_menu_ajax_preload_page', [$this, 'ajax_preload_page']);
    }

    public function ajax_preload_page()
    {
        check_ajax_referer('mega_menu_ajax_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;

        if (!$item_id) {
            wp_send_json_error(['message' => __('Invalid menu item ID.', 'mega-menu-ajax')]);
        }

        $data = self::get_page_data($item_id);

        if (is_wp_error($data)) {
            wp_send_json_error(['message' => $data->get_error_message()]);
        }

        wp_send_json_success($data);
    }

    public static function get_page_data($item_id)
    {
        $transient_key = "mega_menu_ajax_preload_{$item_id}";
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            return $cached;
        }

        $menu_item = get_post($item_id);

        if (!$menu_item || $menu_item->post_type !== 'nav_menu_item') {
            return new \WP_Error('invalid_item', __('Menu item not found.', 'mega-menu-ajax'));
        }

        $url = $menu_item->url;

        if (empty($url)) {
            $url = get_permalink($menu_item->object_id);
        }

        if (empty($url)) {
            return new \WP_Error('no_url', __('No URL found for this menu item.', 'mega-menu-ajax'));
        }

        $response = wp_remote_get($url, [
            'timeout' => 1,
            'sslverify' => false,
            'user-agent' => 'Mega-Menu-Ajax-Preload/' . MEGA_MENU_AJAX_VERSION,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('fetch_failed', __('Failed to fetch page content.', 'mega-menu-ajax'));
        }

        $html = wp_remote_retrieve_body($response);
        $assets = self::extract_assets($html, $url);

        $data = [
            'url' => $url,
            'title' => self::extract_title($html),
            'assets' => $assets,
            'cached_at' => time(),
        ];

        $data = apply_filters('mega_menu_ajax_preload_data', $data, $item_id, $html);

        set_transient($transient_key, $data, 6 * HOUR_IN_SECONDS);

        return $data;
    }

    private static function extract_assets($html, $base_url)
    {
        $assets = [
            'css' => [],
            'js' => [],
            'images' => [],
        ];

        if (empty($html)) {
            return $assets;
        }

        preg_match_all('/<link[^>]+rel=["\']?stylesheet["\']?[^>]+href=["\']([^"\']+)["\']?/i', $html, $css_matches);
        if (!empty($css_matches[1])) {
            foreach ($css_matches[1] as $url) {
                $url = self::resolve_url($url, $base_url);
                if ($url && !self::is_external($url)) {
                    $assets['css'][] = $url;
                }
            }
            $assets['css'] = array_unique($assets['css']);
        }

        $assets['js'] = [];
        $assets['images'] = [];

        return apply_filters('mega_menu_ajax_extracted_assets', $assets, $html, $base_url);
    }

    private static function extract_title($html)
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private static function resolve_url($url, $base_url)
    {
        if (empty($url)) {
            return false;
        }

        if (strpos($url, '//') === 0) {
            $parsed_base = parse_url($base_url);
            $scheme = isset($parsed_base['scheme']) ? $parsed_base['scheme'] : 'https';
            return $scheme . ':' . $url;
        }

        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }

        if (strpos($url, '/') === 0) {
            $parsed_base = parse_url($base_url);
            $scheme = isset($parsed_base['scheme']) ? $parsed_base['scheme'] : 'https';
            $host = isset($parsed_base['host']) ? $parsed_base['host'] : '';
            return $scheme . '://' . $host . $url;
        }

        return rtrim($base_url, '/') . '/' . ltrim($url, '/');
    }

    private static function is_external($url)
    {
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        $url_host = parse_url($url, PHP_URL_HOST);
        return $url_host && $url_host !== $site_host;
    }

    private static function is_data_url($url)
    {
        return strpos($url, 'data:') === 0;
    }
}
