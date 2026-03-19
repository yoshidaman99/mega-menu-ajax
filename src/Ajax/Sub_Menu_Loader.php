<?php

namespace Mega_Menu_Ajax\Ajax;

defined('ABSPATH') || exit;

class Sub_Menu_Loader
{
    private static $instance = null;
    private $cache_group = 'mega_menu_ajax_submenus';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_ajax_mega_menu_ajax_load_submenu', [$this, 'ajax_load_submenu']);
        add_action('wp_ajax_nopriv_mega_menu_ajax_load_submenu', [$this, 'ajax_load_submenu']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        add_action('wp_update_nav_menu', [$this, 'clear_related_caches'], 10, 1);
        add_action('wp_update_nav_menu_item', [$this, 'clear_item_cache'], 10, 3);
    }

    public function ajax_load_submenu()
    {
        check_ajax_referer('mega_menu_ajax_nonce', 'nonce');
        
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        
        if (!$item_id) {
            wp_send_json_error(['message' => __('Invalid menu item ID.', 'mega-menu-ajax')]);
        }
        
        $items = self::get_submenu($item_id, $location);
        
        if (empty($items)) {
            wp_send_json_success(['items' => [], 'html' => '']);
        }
        
        $html = self::render_submenu_html($items, $location);
        
        wp_send_json_success([
            'items' => $items,
            'html' => $html,
            'cached' => false,
        ]);
    }

    public function register_rest_routes()
    {
        register_rest_route('mega-menu-ajax/v1', '/submenu/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_submenu'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
                'location' => [
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                ],
            ],
        ]);
    }

    public function rest_get_submenu($request)
    {
        $item_id = (int) $request['id'];
        $location = sanitize_text_field($request->get_param('location') ?? '');
        
        $items = self::get_submenu($item_id, $location);
        
        $response = rest_ensure_response([
            'items' => $items,
            'html' => self::render_submenu_html($items, $location),
        ]);
        
        $response->header('Cache-Control', 'public, max-age=3600');
        $response->header('X-WP-Cache', 'HIT');
        
        return $response;
    }

    public static function get_submenu($parent_id, $location = '')
    {
        $cache = \Mega_Menu_Ajax\Performance\Menu_Cache::get_instance();
        $cached_items = $cache->get_submenu_data($parent_id);
        
        if ($cached_items !== null) {
            return $cached_items;
        }
        
        $items = [];
        $menu_id = self::get_menu_id_from_item($parent_id);
        
        if (!$menu_id) {
            return $items;
        }
        
        $all_items = wp_get_nav_menu_items($menu_id);
        
        if (empty($all_items)) {
            return $items;
        }
        
        $child_map = self::build_child_map($all_items);
        
        if (!isset($child_map[$parent_id])) {
            return $items;
        }
        
        foreach ($child_map[$parent_id] as $child_id) {
            $child_item = self::find_item_by_id($all_items, $child_id);
            
            if (!$child_item) {
                continue;
            }
            
            $items[] = [
                'id' => $child_item->ID,
                'title' => $child_item->title,
                'url' => $child_item->url,
                'attr_title' => $child_item->attr_title,
                'target' => $child_item->target,
                'xfn' => $child_item->xfn,
                'classes' => array_filter((array) $child_item->classes),
                'has_children' => isset($child_map[$child_item->ID]),
                'depth' => self::calculate_depth($child_item, $all_items),
                'current' => $child_item->current ?? false,
                'current_item_parent' => $child_item->current_item_parent ?? false,
                'current_item_ancestor' => $child_item->current_item_ancestor ?? false,
                'description' => $child_item->description ?? '',
                'object_id' => $child_item->object_id ?? 0,
                'object' => $child_item->object ?? '',
                'type' => $child_item->type ?? '',
            ];
        }
        
        $cache->set_submenu_data($parent_id, $items);
        
        return $items;
    }

    private static function build_child_map($menu_items)
    {
        $map = [];
        
        foreach ($menu_items as $item) {
            $parent = (int) ($item->menu_item_parent ?? 0);
            
            if (!isset($map[$parent])) {
                $map[$parent] = [];
            }
            
            $map[$parent][] = (int) $item->ID;
        }
        
        return $map;
    }

    private static function find_item_by_id($items, $id)
    {
        foreach ($items as $item) {
            if ((int) $item->ID === (int) $id) {
                return $item;
            }
        }
        return null;
    }

    private static function calculate_depth($item, $all_items)
    {
        $depth = 0;
        $parent_id = (int) ($item->menu_item_parent ?? 0);
        $visited = [$item->ID];
        
        while ($parent_id > 0 && $depth < 10) {
            if (in_array($parent_id, $visited)) {
                break;
            }
            
            $visited[] = $parent_id;
            $depth++;
            
            $parent = self::find_item_by_id($all_items, $parent_id);
            
            if (!$parent) {
                break;
            }
            
            $parent_id = (int) ($parent->menu_item_parent ?? 0);
        }
        
        return $depth;
    }

    private static function get_menu_id_from_item($item_id)
    {
        $menus = wp_get_object_terms($item_id, 'nav_menu');
        
        if (!empty($menus) && !is_wp_error($menus)) {
            return (int) $menus[0]->term_id;
        }
        
        $item = get_post($item_id);
        
        if (!$item || $item->post_type !== 'nav_menu_item') {
            $locations = get_nav_menu_locations();
            return reset($locations) ?: 0;
        }
        
        $menu_term_id = get_post_meta($item_id, '_menu_item_menu_item_parent', true);
        
        if ($menu_term_id) {
            return (int) $menu_term_id;
        }
        
        $locations = get_nav_menu_locations();
        return reset($locations) ?: 0;
    }

    public static function render_submenu_html($items, $location = '')
    {
        if (empty($items)) {
            return '';
        }
        
        $settings = get_option('mega_menu_ajax_settings', []);
        $location_settings = $settings[$location] ?? [];
        $max_depth = $location_settings['max_depth'] ?? 10;
        
        $html = '';
        
        foreach ($items as $item) {
            $classes = ['mega-menu-ajax-item', 'menu-item-' . $item['id']];
            
            if (!empty($item['has_children'])) {
                $classes[] = 'mega-menu-ajax-has-children';
            }
            
            if (!empty($item['current'])) {
                $classes[] = 'current-menu-item';
            }
            
            if (!empty($item['current_item_parent'])) {
                $classes[] = 'current-menu-parent';
            }
            
            if (!empty($item['current_item_ancestor'])) {
                $classes[] = 'current-menu-ancestor';
            }
            
            if (!empty($item['classes'])) {
                $classes = array_merge($classes, $item['classes']);
            }
            
            $class_str = esc_attr(implode(' ', array_filter(array_unique($classes))));
            
            $html .= '<li class="' . $class_str . '" data-menu-item-id="' . esc_attr($item['id']) . '">';
            
            $atts = [
                'href' => esc_url($item['url']),
                'title' => esc_attr($item['attr_title'] ?? ''),
            ];
            
            if (!empty($item['target'])) {
                $atts['target'] = esc_attr($item['target']);
            }
            
            if (!empty($item['xfn'])) {
                $atts['rel'] = esc_attr($item['xfn']);
            }
            
            if (!empty($item['has_children'])) {
                $atts['aria-expanded'] = 'false';
                $atts['aria-haspopup'] = 'true';
            }
            
            $attr_str = '';
            foreach ($atts as $key => $value) {
                if (!empty($value)) {
                    $attr_str .= ' ' . $key . '="' . $value . '"';
                }
            }
            
            $html .= '<a' . $attr_str . '>';
            $html .= esc_html($item['title']);
            
            if (!empty($item['has_children'])) {
                $html .= '<span class="mega-menu-ajax-indicator" aria-hidden="true"></span>';
            }
            
            $html .= '</a>';
            
            if (!empty($item['has_children']) && $item['depth'] < $max_depth) {
                $html .= '<ul class="mega-menu-ajax-submenu mega-menu-ajax-lazy" data-loaded="false" data-parent-id="' . esc_attr($item['id']) . '"></ul>';
            }
            
            $html .= '</li>';
        }
        
        return $html;
    }

    public function clear_related_caches($menu_id)
    {
        $menu_items = wp_get_nav_menu_items($menu_id);
        
        if (empty($menu_items)) {
            return;
        }
        
        $cache = \Mega_Menu_Ajax\Performance\Menu_Cache::get_instance();
        
        foreach ($menu_items as $item) {
            if (!empty($item->menu_item_parent)) {
                $cache->clear_submenu_cache((int) $item->menu_item_parent);
            }
        }
    }

    public function clear_item_cache($menu_id, $menu_item_db_id, $args)
    {
        $cache = \Mega_Menu_Ajax\Performance\Menu_Cache::get_instance();
        
        $item = get_post($menu_item_db_id);
        
        if ($item && !empty($item->post_parent)) {
            $cache->clear_submenu_cache((int) $item->post_parent);
        }
        
        $this->clear_related_caches($menu_id);
    }
}
