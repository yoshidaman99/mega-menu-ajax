<?php

namespace Mega_Menu_Ajax\Ajax;

defined('ABSPATH') || exit;

class Sub_Menu_Loader
{
    public function __construct()
    {
        add_action('wp_ajax_mega_menu_ajax_load_submenu', [$this, 'ajax_load_submenu']);
        add_action('wp_ajax_nopriv_mega_menu_ajax_load_submenu', [$this, 'ajax_load_submenu']);
    }

    public function ajax_load_submenu()
    {
        check_ajax_referer('mega_menu_ajax_nonce', 'nonce');
        
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        
        if (!$item_id) {
            wp_send_json_error(['message' => __('Invalid menu item ID.', 'mega-menu-ajax')]);
        }
        
        $items = self::get_submenu($item_id);
        
        wp_send_json_success($items);
    }

    public static function get_submenu($parent_id)
    {
        $transient_key = "mega_menu_ajax_submenu_{$parent_id}";
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $items = [];
        $menu_id = self::get_menu_id_from_item($parent_id);
        
        if (!$menu_id) {
            return $items;
        }
        
        $menu_items = wp_get_nav_menu_items($menu_id);
        
        if (!empty($menu_items)) {
            foreach ($menu_items as $item) {
                if ((int) $item->menu_item_parent === (int) $parent_id) {
                    $items[] = [
                        'id' => $item->ID,
                        'title' => $item->title,
                        'url' => $item->url,
                        'attr_title' => $item->attr_title,
                        'target' => $item->target,
                        'classes' => $item->classes,
                        'has_children' => self::has_children($item->ID, $menu_items),
                    ];
                }
            }
        }
        
        set_transient($transient_key, $items, HOUR_IN_SECONDS);
        
        return $items;
    }

    private static function get_menu_id_from_item($item_id)
    {
        $item = get_post($item_id);
        
        if (!$item || $item->post_type !== 'nav_menu_item') {
            return 0;
        }
        
        $term_id = get_post_meta($item_id, '_menu_item_menu_item_parent', true);
        
        $menus = wp_get_object_terms($item_id, 'nav_menu');
        
        if (!empty($menus) && !is_wp_error($menus)) {
            return $menus[0]->term_id;
        }
        
        return 0;
    }

    private static function has_children($item_id, $all_items = null)
    {
        if ($all_items === null) {
            $args = [
                'meta_key' => '_menu_item_menu_item_parent',
                'meta_value' => $item_id,
                'post_type' => 'nav_menu_item',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'suppress_filters' => false,
            ];
            $children = get_posts($args);
            return !empty($children);
        }
        
        foreach ($all_items as $item) {
            if ((int) $item->menu_item_parent === (int) $item_id) {
                return true;
            }
        }
        
        return false;
    }
}
