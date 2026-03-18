<?php

namespace Mega_Menu_Ajax\Ajax;

defined('ABSPATH') || exit;

class Background_Preload
{
    public function __construct()
    {
        add_action('wp_ajax_mega_menu_ajax_background_preload_urls', [$this, 'ajax_get_preload_urls']);
        add_action('wp_ajax_nopriv_mega_menu_ajax_background_preload_urls', [$this, 'ajax_get_preload_urls']);
    }

    public function ajax_get_preload_urls()
    {
        check_ajax_referer('mega_menu_ajax_nonce', 'nonce');
        
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        
        if (!$location) {
            wp_send_json_error(['message' => __('Invalid menu location.', 'mega-menu-ajax')]);
        }
        
        $settings = get_option('mega_menu_ajax_settings', []);
        $location_settings = $settings[$location] ?? [];
        
        if (empty($location_settings['background_preload_enabled'])) {
            wp_send_json_error(['message' => __('Background preloading is disabled.', 'mega-menu-ajax')]);
        }
        
        $urls = $this->get_preload_urls($location, $location_settings);
        
        wp_send_json_success($urls);
    }
    
    public function get_preload_urls($location, $settings)
    {
        $transient_key = "mega_menu_ajax_bg_preload_{$location}";
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $urls = [];
        $locations = get_nav_menu_locations();
        $menu_id = $locations[$location] ?? 0;
        
        if (!$menu_id) {
            return $urls;
        }
        
        $menu_items = wp_get_nav_menu_items($menu_id);
        
        if (empty($menu_items)) {
            return $urls;
        }
        
        $limit = (int) ($settings['background_preload_limit'] ?? 5);
        $priority_mode = $settings['background_preload_priority'] ?? 'balanced';
        
        if ($priority_mode === 'conservative') {
            $limit = min($limit, 3);
        } elseif ($priority_mode === 'aggressive') {
            $limit = min($limit * 2, 10);
        }
        
        $top_level_items = array_filter($menu_items, function($item) {
            return empty($item->menu_item_parent) || $item->menu_item_parent == 0;
        });
        
        usort($top_level_items, function($a, $b) {
            return $a->menu_order - $b->menu_order;
        });
        
        foreach ($top_level_items as $item) {
            if (count($urls) >= $limit) {
                break;
            }
            
            $url = $item->url;
            
            if (empty($url) || $url === '#' || strpos($url, 'javascript:') === 0) {
                continue;
            }
            
            if (strpos($url, 'http') !== 0) {
                $url = home_url($url);
            }
            
            if ($this->is_external_url($url)) {
                continue;
            }
            
            $urls[] = [
                'url' => $url,
                'title' => $item->title,
                'priority' => 10,
            ];
        }
        
        usort($urls, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        set_transient($transient_key, $urls, HOUR_IN_SECONDS);
        
        return $urls;
    }
    
    private function is_external_url($url)
    {
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        $url_host = parse_url($url, PHP_URL_HOST);
        return $url_host && $url_host !== $site_host;
    }
}
