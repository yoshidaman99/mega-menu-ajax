<?php

namespace Mega_Menu_Ajax\Menu;

defined('ABSPATH') || exit;

class Menu_Manager
{
    private $settings;

    public function __construct()
    {
        $this->settings = get_option('mega_menu_ajax_settings', []);
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_update_nav_menu', [$this, 'clear_cache']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('Mega Menu Ajax', 'mega-menu-ajax'),
            __('Mega Menu', 'mega-menu-ajax'),
            'manage_options',
            'mega-menu-ajax',
            [$this, 'render_settings_page'],
            'dashicons-menu-alt3',
            30
        );
    }

    public function register_settings()
    {
        register_setting('mega_menu_ajax_settings', 'mega_menu_ajax_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        add_settings_section(
            'mega_menu_ajax_locations',
            __('Menu Locations', 'mega-menu-ajax'),
            [$this, 'render_locations_section'],
            'mega-menu-ajax'
        );

        $locations = get_registered_nav_menus();
        
        foreach ($locations as $location => $description) {
            add_settings_field(
                "location_{$location}",
                $description,
                [$this, 'render_location_field'],
                'mega-menu-ajax',
                'mega_menu_ajax_locations',
                ['location' => $location]
            );
        }
    }

    public function sanitize_settings($input)
    {
        $sanitized = [];
        
        if (is_array($input)) {
            foreach ($input as $location => $settings) {
                $sanitized[$location] = [
                    'enabled' => !empty($settings['enabled']),
                    'ajax_submenu' => !empty($settings['ajax_submenu']),
                    'lazy_load' => !empty($settings['lazy_load']),
                    'search_enabled' => !empty($settings['search_enabled']),
                    'effect' => sanitize_text_field($settings['effect'] ?? 'fade'),
                    'mobile_breakpoint' => absint($settings['mobile_breakpoint'] ?? 768),
                ];
            }
        }
        
        return $sanitized;
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('mega_menu_ajax_settings');
                do_settings_sections('mega-menu-ajax');
                submit_button(__('Save Settings', 'mega-menu-ajax'));
                ?>
            </form>
        </div>
        <?php
    }

    public function render_locations_section()
    {
        echo '<p>' . esc_html__('Configure mega menu settings for each menu location.', 'mega-menu-ajax') . '</p>';
    }

    public function render_location_field($args)
    {
        $location = $args['location'];
        $settings = $this->settings[$location] ?? [];
        ?>
        <fieldset>
            <label>
                <input type="checkbox" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][enabled]" 
                       value="1" 
                       <?php checked(!empty($settings['enabled'])); ?>>
                <?php esc_html_e('Enable Mega Menu', 'mega-menu-ajax'); ?>
            </label>
            <br>
            <label>
                <input type="checkbox" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][ajax_submenu]" 
                       value="1" 
                       <?php checked(!empty($settings['ajax_submenu'])); ?>>
                <?php esc_html_e('Load sub-menus via AJAX', 'mega-menu-ajax'); ?>
            </label>
            <br>
            <label>
                <input type="checkbox" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][lazy_load]" 
                       value="1" 
                       <?php checked(!empty($settings['lazy_load'])); ?>>
                <?php esc_html_e('Lazy load entire menu', 'mega-menu-ajax'); ?>
            </label>
            <br>
            <label>
                <input type="checkbox" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][search_enabled]" 
                       value="1" 
                       <?php checked(!empty($settings['search_enabled'])); ?>>
                <?php esc_html_e('Enable menu search', 'mega-menu-ajax'); ?>
            </label>
            <br>
            <label>
                <?php esc_html_e('Animation Effect:', 'mega-menu-ajax'); ?>
                <select name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][effect]">
                    <option value="fade" <?php selected($settings['effect'] ?? '', 'fade'); ?>><?php esc_html_e('Fade', 'mega-menu-ajax'); ?></option>
                    <option value="slide" <?php selected($settings['effect'] ?? '', 'slide'); ?>><?php esc_html_e('Slide', 'mega-menu-ajax'); ?></option>
                    <option value="fade_slide" <?php selected($settings['effect'] ?? '', 'fade_slide'); ?>><?php esc_html_e('Fade & Slide', 'mega-menu-ajax'); ?></option>
                </select>
            </label>
            <br>
            <label>
                <?php esc_html_e('Mobile Breakpoint (px):', 'mega-menu-ajax'); ?>
                <input type="number" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][mobile_breakpoint]" 
                       value="<?php echo esc_attr($settings['mobile_breakpoint'] ?? 768); ?>" 
                       min="0" 
                       max="2000">
            </label>
        </fieldset>
        <?php
    }

    public function clear_cache($menu_id)
    {
        $transient_key = "mega_menu_ajax_menu_{$menu_id}";
        delete_transient($transient_key);
    }

    public function get_settings($location = null)
    {
        if ($location) {
            return $this->settings[$location] ?? [];
        }
        return $this->settings;
    }
}
