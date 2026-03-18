<?php

namespace Mega_Menu_Ajax\Ajax;

defined('ABSPATH') || exit;

class Menu_Lazy_Load
{
    public function __construct()
    {
        add_action('wp_ajax_mega_menu_ajax_load_menu', [$this, 'ajax_load_menu']);
        add_action('wp_ajax_nopriv_mega_menu_ajax_load_menu', [$this, 'ajax_load_menu']);
        add_filter('mega_menu_ajax_lazy_load_placeholder', [$this, 'get_placeholder'], 10, 2);
    }

    public function ajax_load_menu()
    {
        check_ajax_referer('mega_menu_ajax_nonce', 'nonce');
        
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        
        if (!$location) {
            wp_send_json_error(['message' => __('Invalid menu location.', 'mega-menu-ajax')]);
        }
        
        $menu = self::get_menu($location);
        
        wp_send_json_success($menu);
    }

    public static function get_menu($location)
    {
        $transient_key = "mega_menu_ajax_menu_{$location}";
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $locations = get_nav_menu_locations();
        $menu_id = $locations[$location] ?? 0;
        
        if (!$menu_id) {
            return [];
        }
        
        $menu_items = wp_get_nav_menu_items($menu_id);
        $menu = [];
        
        if (!empty($menu_items)) {
            foreach ($menu_items as $item) {
                $menu[$item->ID] = [
                    'id' => $item->ID,
                    'title' => $item->title,
                    'url' => $item->url,
                    'parent' => $item->menu_item_parent,
                    'attr_title' => $item->attr_title,
                    'target' => $item->target,
                    'classes' => $item->classes,
                    'depth' => self::get_depth($item, $menu_items),
                ];
            }
        }
        
        set_transient($transient_key, $menu, HOUR_IN_SECONDS);
        
        return $menu;
    }

    private static function get_depth($item, $all_items)
    {
        $depth = 0;
        $parent_id = $item->menu_item_parent;
        
        while ($parent_id != 0 && $depth < 10) {
            $depth++;
            foreach ($all_items as $parent_item) {
                if ($parent_item->ID == $parent_id) {
                    $parent_id = $parent_item->menu_item_parent;
                    break;
                }
            }
        }
        
        return $depth;
    }

    public function get_placeholder($location, $settings)
    {
        $placeholder = '<div class="mega-menu-ajax-placeholder" data-location="' . esc_attr($location) . '">';
        $placeholder .= '<div class="mega-menu-ajax-placeholder-content">';
        $placeholder .= '<span class="mega-menu-ajax-spinner"></span>';
        $placeholder .= '<span class="mega-menu-ajax-loading-text">' . esc_html__('Loading menu...', 'mega-menu-ajax') . '</span>';
        $placeholder .= '</div>';
        $placeholder .= '</div>';
        
        return $placeholder;
    }
}
