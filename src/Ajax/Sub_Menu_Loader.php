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
        $menu_items = wp_get_nav_menu_items(get_the_ID(), ['post_parent' => $parent_id]);
        
        if (!empty($menu_items)) {
            foreach ($menu_items as $item) {
                if ($item->menu_item_parent == $parent_id) {
                    $items[] = [
                        'id' => $item->ID,
                        'title' => $item->title,
                        'url' => $item->url,
                        'attr_title' => $item->attr_title,
                        'target' => $item->target,
                        'classes' => $item->classes,
                        'has_children' => self::has_children($item->ID),
                    ];
                }
            }
        }
        
        set_transient($transient_key, $items, HOUR_IN_SECONDS);
        
        return $items;
    }

    private static function has_children($item_id)
    {
        $children = get_posts([
            'post_type' => 'nav_menu_item',
            'post_parent' => $item_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);
        
        return !empty($children);
    }
}
