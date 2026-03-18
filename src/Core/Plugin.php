<?php

namespace Mega_Menu_Ajax\Core;

defined('ABSPATH') || exit;

class Plugin
{
    private static $instance = null;
    private $version = MEGA_MENU_AJAX_VERSION;
    private $debug_logger;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->debug_logger = new Debug_Logger();
        $this->init_hooks();
        $this->load_components();
    }

    private function init_hooks()
    {
        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('wp_nav_menu_args', [$this, 'filter_nav_menu_args'], 100);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    private function load_components()
    {
        new \Mega_Menu_Ajax\Menu\Menu_Manager();
        new \Mega_Menu_Ajax\Menu\Style_Manager();
        new \Mega_Menu_Ajax\Ajax\Sub_Menu_Loader();
        new \Mega_Menu_Ajax\Ajax\Menu_Lazy_Load();
        new \Mega_Menu_Ajax\Ajax\Search_Handler();
        new \Mega_Menu_Ajax\Ajax\Page_Preload();
        
        if (did_action('elementor/loaded')) {
            new \Mega_Menu_Ajax\Elementor\Integration();
        }
    }

    public function load_textdomain()
    {
        load_plugin_textdomain(
            'mega-menu-ajax',
            false,
            dirname(MEGA_MENU_AJAX_BASENAME) . '/languages'
        );
    }

    public function enqueue_frontend_assets()
    {
        wp_enqueue_style(
            'mega-menu-ajax-frontend',
            MEGA_MENU_AJAX_URL . 'assets/css/frontend.css',
            [],
            MEGA_MENU_AJAX_VERSION
        );

        wp_enqueue_script(
            'mega-menu-ajax-frontend',
            MEGA_MENU_AJAX_URL . 'assets/js/frontend.js',
            ['jquery'],
            MEGA_MENU_AJAX_VERSION,
            true
        );

        $settings = get_option('mega_menu_ajax_settings', []);
        $preload_settings = [];
        $enabled_locations = [];
        
        foreach ($settings as $location => $location_settings) {
            if (!empty($location_settings['enabled'])) {
                $enabled_locations[] = $location;
            }
            if (!empty($location_settings['preload_enabled'])) {
                $preload_settings[$location] = [
                    'enabled' => true,
                    'delay' => absint($location_settings['preload_delay'] ?? 30),
                    'preload_css' => !empty($location_settings['preload_css']),
                    'preload_js' => !empty($location_settings['preload_js']),
                    'preload_images' => !empty($location_settings['preload_images']),
                ];
            }
        }

        wp_localize_script('mega-menu-ajax-frontend', 'megaMenuAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('mega-menu-ajax/v1/'),
            'nonce' => wp_create_nonce('mega_menu_ajax_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'enabledLocations' => $enabled_locations,
            'registeredLocations' => array_keys(get_registered_nav_menus()),
            'preload' => $preload_settings,
            'i18n' => [
                'searchPlaceholder' => __('Search menu...', 'mega-menu-ajax'),
                'loading' => __('Loading...', 'mega-menu-ajax'),
                'noResults' => __('No results found', 'mega-menu-ajax'),
                'menu' => __('Menu', 'mega-menu-ajax'),
            ],
        ]);
    }

    public function enqueue_admin_assets($hook)
    {
        if (!in_array($hook, ['nav-menus.php', 'toplevel_page_mega-menu-ajax'], true)) {
            return;
        }

        wp_enqueue_style(
            'mega-menu-ajax-admin',
            MEGA_MENU_AJAX_URL . 'assets/css/admin.css',
            [],
            MEGA_MENU_AJAX_VERSION
        );

        wp_enqueue_script(
            'mega-menu-ajax-admin',
            MEGA_MENU_AJAX_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            MEGA_MENU_AJAX_VERSION,
            true
        );

        wp_localize_script('mega-menu-ajax-admin', 'megaMenuAjaxAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mega_menu_ajax_admin_nonce'),
        ]);
    }

    public function filter_nav_menu_args($args)
    {
        $settings = get_option('mega_menu_ajax_settings', []);
        
        if (empty($settings)) {
            return $args;
        }

        $location = $args['theme_location'] ?? '';
        
        if (empty($location)) {
            if (!empty($args['menu']) && is_object($args['menu'])) {
                $location = $args['menu']->slug ?? '';
            } elseif (!empty($args['menu']) && is_string($args['menu'])) {
                $location = $args['menu'];
            }
        }

        if (empty($location)) {
            $locations = get_nav_menu_locations();
            if (!empty($args['menu'])) {
                $menu_id = is_object($args['menu']) ? $args['menu']->term_id : $args['menu'];
                foreach ($locations as $loc => $id) {
                    if ($id == $menu_id) {
                        $location = $loc;
                        break;
                    }
                }
            }
        }

        if (empty($location) || empty($settings[$location]['enabled'])) {
            return $args;
        }

        $args['walker'] = new \Mega_Menu_Ajax\Menu\Walker();
        $args['menu_class'] = 'mega-menu-ajax-menu ' . ($args['menu_class'] ?? '');
        $args['container_class'] = trim('mega-menu-ajax-wrap mega-menu-ajax-wrap-' . esc_attr($location) . ' ' . ($args['container_class'] ?? ''));

        return $args;
    }

    public function register_rest_routes()
    {
        register_rest_route('mega-menu-ajax/v1', '/submenu/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_submenu_items'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        register_rest_route('mega-menu-ajax/v1', '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_menu_items'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('mega-menu-ajax/v1', '/menu/(?P<location>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_menu_by_location'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_submenu_items($request)
    {
        $item_id = (int) $request['id'];
        $items = \Mega_Menu_Ajax\Ajax\Sub_Menu_Loader::get_submenu($item_id);
        
        return rest_ensure_response($items);
    }

    public function search_menu_items($request)
    {
        $query = sanitize_text_field($request->get_param('q'));
        $location = sanitize_text_field($request->get_param('location'));
        
        $results = \Mega_Menu_Ajax\Ajax\Search_Handler::search($query, $location);
        
        return rest_ensure_response($results);
    }

    public function get_menu_by_location($request)
    {
        $location = sanitize_text_field($request['location']);
        $menu = \Mega_Menu_Ajax\Ajax\Menu_Lazy_Load::get_menu($location);
        
        return rest_ensure_response($menu);
    }

    public function get_version()
    {
        return $this->version;
    }

    public function get_logger()
    {
        return $this->debug_logger;
    }
}
