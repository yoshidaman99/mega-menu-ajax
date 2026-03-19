<?php

namespace Mega_Menu_Ajax\Integration;

defined('ABSPATH') || exit;

class Max_Mega_Menu
{
    private static $instance = null;
    private $lazy_enabled = false;
    private $location_settings = [];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        if (!$this->is_max_mega_menu_active()) {
            return;
        }

        add_filter('megamenu_nav_menu_args', [$this, 'filter_nav_menu_args'], 999, 2);
        add_filter('megamenu_nav_menu_css_class', [$this, 'add_lazy_classes'], 10, 4);
        add_action('megamenu_before_menu', [$this, 'inject_lazy_skeleton'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_compat_scripts'], 99);
        add_filter('mega_menu_ajax_compat_mode', '__return_true');
        
        add_action('wp_ajax_mega_menu_ajax_load_megamenu_submenu', [$this, 'ajax_load_submenu']);
        add_action('wp_ajax_nopriv_mega_menu_ajax_load_megamenu_submenu', [$this, 'ajax_load_submenu']);
        
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    private function is_max_mega_menu_active()
    {
        return class_exists('Mega_Menu') || 
               class_exists('Max_Mega_Menu') || 
               defined('MEGAMENU_VERSION') ||
               function_exists('max_mega_menu_enabled');
    }

    public function filter_nav_menu_args($args, $menu_id)
    {
        $settings = get_option('mega_menu_ajax_settings', []);
        $location = $args['theme_location'] ?? '';
        
        if (empty($location) || empty($settings[$location]['enabled'])) {
            return $args;
        }

        $this->lazy_enabled = !empty($settings[$location]['ajax_submenu']);
        $this->location_settings = $settings[$location] ?? [];
        
        if ($this->lazy_enabled) {
            add_filter('megamenu_walk_nav_menu_tree', [$this, 'enable_lazy_walking'], 10, 3);
        }

        return $args;
    }

    public function enable_lazy_walking($output, $items, $args)
    {
        $location = $args->theme_location ?? '';
        $settings = get_option('mega_menu_ajax_settings', []);
        
        if (empty($settings[$location]['ajax_submenu'])) {
            return $output;
        }

        $cached = \Mega_Menu_Ajax\Performance\Menu_Cache::get_instance()
            ->get_toplevel_html($location, $args->menu->term_id ?? 0);
        
        if ($cached !== null) {
            return $cached;
        }

        $output = $this->render_toplevel_only($items, $args);
        
        \Mega_Menu_Ajax\Performance\Menu_Cache::get_instance()
            ->set_toplevel_html($location, $args->menu->term_id ?? 0, $output);

        return $output;
    }

    private function render_toplevel_only($items, $args)
    {
        $output = '';
        
        $toplevel_items = array_filter($items, function($item) {
            return empty($item->menu_item_parent) || $item->menu_item_parent == 0;
        });

        foreach ($toplevel_items as $item) {
            $output .= $this->render_lazy_item($item, $args, $items);
        }

        return $output;
    }

    private function render_lazy_item($item, $args, $all_items)
    {
        $has_children = $this->item_has_children($item->ID, $all_items);
        
        $classes = [
            'mega-menu-item',
            'mega-menu-item-type-' . ($item->type ?? 'post_type'),
            'mega-menu-item-object-' . ($item->object ?? 'page'),
            'menu-item-' . $item->ID,
        ];
        
        if ($has_children) {
            $classes[] = 'mega-menu-item-has-children';
            $classes[] = 'mega-menu-ajax-lazy-parent';
        }
        
        if (!empty($item->current)) {
            $classes[] = 'mega-current-menu-item';
        }
        if (!empty($item->current_item_parent)) {
            $classes[] = 'mega-current-menu-parent';
        }
        if (!empty($item->current_item_ancestor)) {
            $classes[] = 'mega-current-menu-ancestor';
        }
        
        $classes = array_merge($classes, (array) ($item->classes ?? []));
        $classes = array_filter($classes);
        
        $output = '<li class="' . esc_attr(implode(' ', $classes)) . '" id="mega-menu-item-' . esc_attr($item->ID) . '">';
        
        $atts = [
            'href' => $item->url ?? '#',
            'class' => 'mega-menu-link',
            'aria-expanded' => 'false',
        ];
        
        if (!empty($item->attr_title)) {
            $atts['title'] = $item->attr_title;
        }
        if (!empty($item->target)) {
            $atts['target'] = $item->target;
        }
        
        $attributes = '';
        foreach ($atts as $attr => $value) {
            $attributes .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        
        $output .= '<a' . $attributes . '>';
        $output .= '<span class="mega-menu-title">' . esc_html($item->title) . '</span>';
        
        if ($has_children) {
            $output .= '<span class="mega-indicator" aria-hidden="true"></span>';
        }
        
        $output .= '</a>';
        
        if ($has_children) {
            $output .= '<ul class="mega-sub-menu mega-menu-ajax-lazy" data-loaded="false" data-parent-id="' . esc_attr($item->ID) . '">';
            $output .= '</ul>';
        }
        
        $output .= '</li>';
        
        return $output;
    }

    private function item_has_children($item_id, $all_items)
    {
        foreach ($all_items as $item) {
            if ((int) ($item->menu_item_parent ?? 0) === (int) $item_id) {
                return true;
            }
        }
        return false;
    }

    public function add_lazy_classes($classes, $item, $args, $depth = 0)
    {
        $settings = get_option('mega_menu_ajax_settings', []);
        $location = $args->theme_location ?? '';
        
        if (empty($settings[$location]['ajax_submenu'])) {
            return $classes;
        }
        
        if ($depth === 0 && in_array('mega-menu-item-has-children', $classes, true)) {
            $classes[] = 'mega-menu-ajax-lazy-parent';
        }
        
        return $classes;
    }

    public function inject_lazy_skeleton($menu_id, $args)
    {
        $settings = get_option('mega_menu_ajax_settings', []);
        $location = $args->theme_location ?? '';
        
        if (empty($settings[$location]['ajax_submenu'])) {
            return;
        }
        ?>
        <style id="mega-menu-ajax-skeleton-css">
        .mega-menu-ajax-lazy:not(.mega-menu-ajax-loaded) {
            min-height: 60px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: mega-menu-ajax-shimmer 1.5s infinite;
        }
        @keyframes mega-menu-ajax-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .mega-menu-ajax-lazy-parent > .mega-sub-menu.mega-menu-ajax-lazy:not([data-loaded="true"]) {
            min-height: 80px;
        }
        .mega-menu-ajax-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .mega-menu-ajax-spinner {
            width: 24px;
            height: 24px;
            border: 3px solid #e0e0e0;
            border-top-color: #666;
            border-radius: 50%;
            animation: mega-menu-ajax-spin 0.8s linear infinite;
        }
        @keyframes mega-menu-ajax-spin {
            to { transform: rotate(360deg); }
        }
        </style>
        <?php
    }

    public function enqueue_compat_scripts()
    {
        $inline_js = $this->get_compat_js();
        wp_add_inline_script('mega-menu-ajax-frontend', $inline_js, 'after');
    }

    private function get_compat_js()
    {
        return '
(function($) {
    if (typeof megaMenuAjax === "undefined") return;
    
    var initialized = false;
    var hoverTimers = {};
    var loadingStates = {};
    var submenuCache = {};
    
    function init() {
        if (initialized) return;
        initialized = true;
        
        $(".mega-menu-ajax-lazy-parent").each(function() {
            var $item = $(this);
            var $submenu = $item.children(".mega-sub-menu").first();
            var itemId = extractItemId($item);
            
            if (itemId && $submenu.length) {
                $submenu.addClass("mega-menu-ajax-lazy").attr("data-loaded", "false").attr("data-parent-id", itemId);
            }
        });
        
        bindHoverEvents();
    }
    
    function extractItemId($item) {
        var id = $item.attr("id");
        if (id && id.indexOf("mega-menu-item-") === 0) {
            return id.replace("mega-menu-item-", "");
        }
        return $item.data("menu-item-id");
    }
    
    function bindHoverEvents() {
        $(document).on("mouseenter", ".mega-menu-ajax-lazy-parent", function(e) {
            var $item = $(this);
            var itemId = extractItemId($item);
            var $submenu = $item.children(".mega-sub-menu").first();
            
            if (!itemId || !$submenu.length) return;
            
            if (hoverTimers[itemId]) {
                clearTimeout(hoverTimers[itemId]);
            }
            
            if (loadingStates[itemId]) return;
            
            if ($submenu.attr("data-loaded") === "true") {
                return;
            }
            
            hoverTimers[itemId] = setTimeout(function() {
                loadSubmenu($item, $submenu, itemId);
            }, 30);
        });
        
        $(document).on("mouseleave", ".mega-menu-ajax-lazy-parent", function(e) {
            var itemId = extractItemId($(this));
            if (hoverTimers[itemId]) {
                clearTimeout(hoverTimers[itemId]);
                delete hoverTimers[itemId];
            }
        });
    }
    
    function loadSubmenu($item, $submenu, itemId) {
        if (submenuCache[itemId]) {
            injectSubmenu($submenu, submenuCache[itemId]);
            return;
        }
        
        loadingStates[itemId] = true;
        $submenu.addClass("mega-menu-ajax-loading");
        $submenu.append("<li class=\"mega-menu-ajax-loading\"><div class=\"mega-menu-ajax-spinner\"></div></li>");
        
        $.ajax({
            url: megaMenuAjax.restUrl + "megamenu-submenu/" + itemId,
            method: "GET",
            success: function(response) {
                if (response && response.length) {
                    submenuCache[itemId] = response;
                    injectSubmenu($submenu, response);
                } else {
                    $submenu.attr("data-loaded", "true").removeClass("mega-menu-ajax-lazy mega-menu-ajax-loading");
                    $submenu.find(".mega-menu-ajax-loading").remove();
                }
            },
            error: function() {
                $submenu.removeClass("mega-menu-ajax-loading");
                $submenu.find(".mega-menu-ajax-loading").remove();
            },
            complete: function() {
                delete loadingStates[itemId];
            }
        });
    }
    
    function injectSubmenu($submenu, items) {
        var html = renderSubmenuItems(items);
        $submenu.find(".mega-menu-ajax-loading").remove();
        $submenu.html(html);
        $submenu.attr("data-loaded", "true");
        $submenu.addClass("mega-menu-ajax-loaded");
        $submenu.removeClass("mega-menu-ajax-lazy mega-menu-ajax-loading");
    }
    
    function renderSubmenuItems(items) {
        var html = "";
        items.forEach(function(item) {
            var classes = ["mega-menu-item", "mega-menu-item-type-post_type", "mega-menu-item-object-page"];
            
            if (item.has_children) {
                classes.push("mega-menu-item-has-children", "mega-menu-ajax-lazy-parent");
            }
            if (item.current) classes.push("mega-current-menu-item");
            if (item.current_item_parent) classes.push("mega-current-menu-parent");
            if (item.current_item_ancestor) classes.push("mega-current-menu-ancestor");
            
            if (item.classes && item.classes.length) {
                classes = classes.concat(item.classes.filter(function(c) { return c && c !== ""; }));
            }
            
            html += "<li class=\"" + classes.join(" ") + "\" id=\"mega-menu-item-" + item.id + "\">";
            html += "<a class=\"mega-menu-link\" href=\"" + item.url + "\"";
            if (item.target) html += " target=\"" + item.target + "\"";
            html += "><span class=\"mega-menu-title\">" + item.title + "</span>";
            if (item.has_children) {
                html += "<span class=\"mega-indicator\" aria-hidden=\"true\"></span>";
            }
            html += "</a>";
            
            if (item.has_children) {
                html += "<ul class=\"mega-sub-menu mega-menu-ajax-lazy\" data-loaded=\"false\" data-parent-id=\"" + item.id + "\"></ul>";
            }
            
            html += "</li>";
        });
        return html;
    }
    
    $(document).ready(function() {
        setTimeout(init, 50);
    });
})(jQuery);
        ';
    }

    public function ajax_load_submenu()
    {
        check_ajax_referer('mega_menu_ajax_nonce', 'nonce');
        
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        
        if (!$item_id) {
            wp_send_json_error(['message' => __('Invalid menu item ID.', 'mega-menu-ajax')]);
        }
        
        $items = $this->get_submenu_items($item_id);
        
        wp_send_json_success($items);
    }

    public function register_rest_routes()
    {
        register_rest_route('mega-menu-ajax/v1', '/megamenu-submenu/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_submenu'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_get_submenu($request)
    {
        $parent_id = (int) $request['id'];
        $items = $this->get_submenu_items($parent_id);
        
        $response = rest_ensure_response($items);
        $response->header('Cache-Control', 'public, max-age=3600');
        
        return $response;
    }

    private function get_submenu_items($parent_id)
    {
        $cache = \Mega_Menu_Ajax\Performance\Menu_Cache::get_instance();
        $cached = $cache->get_submenu_data($parent_id);
        
        if ($cached !== null) {
            return $cached;
        }

        $menu_id = $this->get_menu_id_from_item($parent_id);
        
        if (!$menu_id) {
            return [];
        }
        
        $menu_items = wp_get_nav_menu_items($menu_id);
        $items = [];
        
        if (!empty($menu_items)) {
            foreach ($menu_items as $item) {
                if ((int) ($item->menu_item_parent ?? 0) === (int) $parent_id) {
                    $items[] = [
                        'id' => $item->ID,
                        'title' => $item->title,
                        'url' => $item->url,
                        'attr_title' => $item->attr_title ?? '',
                        'target' => $item->target ?? '',
                        'classes' => array_filter((array) ($item->classes ?? [])),
                        'has_children' => $this->item_has_children_in_list($item->ID, $menu_items),
                        'current' => !empty($item->current),
                        'current_item_parent' => !empty($item->current_item_parent),
                        'current_item_ancestor' => !empty($item->current_item_ancestor),
                    ];
                }
            }
        }
        
        $cache->set_submenu_data($parent_id, $items);
        
        return $items;
    }

    private function item_has_children_in_list($item_id, $all_items)
    {
        foreach ($all_items as $item) {
            if ((int) ($item->menu_item_parent ?? 0) === (int) $item_id) {
                return true;
            }
        }
        return false;
    }

    private function get_menu_id_from_item($item_id)
    {
        $menus = wp_get_object_terms($item_id, 'nav_menu');
        
        if (!empty($menus) && !is_wp_error($menus)) {
            return (int) $menus[0]->term_id;
        }
        
        $locations = get_nav_menu_locations();
        return reset($locations) ?: 0;
    }

    public function get_location_settings($location)
    {
        return $this->location_settings[$location] ?? [];
    }

    public function is_lazy_enabled()
    {
        return $this->lazy_enabled;
    }
}
