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
        
        register_setting('mega_menu_ajax_settings', 'mega_menu_ajax_lcp_image_url', [
            'sanitize_callback' => 'esc_url_raw',
        ]);

        add_settings_section(
            'mega_menu_ajax_global',
            __('Global Settings', 'mega-menu-ajax'),
            [$this, 'render_global_section'],
            'mega-menu-ajax'
        );

        add_settings_field(
            'prefetch_timeout',
            __('Prefetch Timeout', 'mega-menu-ajax'),
            [$this, 'render_prefetch_timeout_field'],
            'mega-menu-ajax',
            'mega_menu_ajax_global'
        );
        
        add_settings_field(
            'lcp_image_url',
            __('LCP Preload Image', 'mega-menu-ajax'),
            [$this, 'render_lcp_image_url_field'],
            'mega-menu-ajax',
            'mega_menu_ajax_global'
        );

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
        
        $sanitized['prefetch_timeout'] = isset($input['prefetch_timeout']) 
            ? absint($input['prefetch_timeout']) 
            : 300;
        $sanitized['prefetch_timeout'] = max(100, min(2000, $sanitized['prefetch_timeout']));
        
        if (is_array($input)) {
            foreach ($input as $location => $settings) {
                if ($location === 'prefetch_timeout') {
                    continue;
                }
                $sanitized[$location] = [
                    'enabled' => !empty($settings['enabled']),
                    'ajax_submenu' => !empty($settings['ajax_submenu']),
                    'lazy_load' => !empty($settings['lazy_load']),
                    'search_enabled' => !empty($settings['search_enabled']),
                    'effect' => sanitize_text_field($settings['effect'] ?? 'fade'),
                    'mobile_breakpoint' => absint($settings['mobile_breakpoint'] ?? 768),
                    'preload_enabled' => !empty($settings['preload_enabled']),
                    'preload_delay' => absint($settings['preload_delay'] ?? 30),
                    'preload_css' => !empty($settings['preload_css']),
                    'prerender_enabled' => !empty($settings['prerender_enabled']),
                    'background_preload_enabled' => !empty($settings['background_preload_enabled']),
                    'background_preload_limit' => absint($settings['background_preload_limit'] ?? 5),
                    'background_preload_delay' => absint($settings['background_preload_delay'] ?? 2000),
                    'background_preload_priority' => sanitize_text_field($settings['background_preload_priority'] ?? 'balanced'),
                    'background_preload_on_wifi_only' => !empty($settings['background_preload_on_wifi_only']),
                    'background_preload_when_idle_only' => !empty($settings['background_preload_when_idle_only']),
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

    public function render_global_section()
    {
        echo '<p>' . esc_html__('Global performance settings for the mega menu.', 'mega-menu-ajax') . '</p>';
    }

    public function render_prefetch_timeout_field()
    {
        $timeout = $this->settings['prefetch_timeout'] ?? 300;
        ?>
        <input type="number" 
               name="mega_menu_ajax_settings[prefetch_timeout]" 
               value="<?php echo esc_attr($timeout); ?>" 
               min="100" 
               max="2000" 
               step="50"
               class="small-text"> 
        <span><?php esc_html_e('ms', 'mega-menu-ajax'); ?></span>
        <p class="description">
            <?php esc_html_e('Abort prefetch if it takes longer than this. Lower = faster fallback navigation. Default: 300ms', 'mega-menu-ajax'); ?>
        </p>
        <?php
    }

    public function render_lcp_image_url_field()
    {
        $lcp_url = get_option('mega_menu_ajax_lcp_image_url', '');
        ?>
        <input type="url" 
               name="mega_menu_ajax_lcp_image_url" 
               value="<?php echo esc_attr($lcp_url); ?>" 
               class="regular-text"
               placeholder="https://example.com/image.jpg">
        <p class="description">
            <?php esc_html_e('Enter the LCP image URL to preload. Find this via PageSpeed Insights. Leave empty to disable.', 'mega-menu-ajax'); ?>
        </p>
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
            <p class="description" style="margin-left: 22px; margin-top: 2px; color: #d63638;">
                <?php esc_html_e('Warning: Not recommended for menus visible on initial page load (impacts LCP/Core Web Vitals).', 'mega-menu-ajax'); ?>
            </p>
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
        <fieldset class="mega-menu-ajax-preload-settings">
            <h4 style="margin: 15px 0 10px; font-weight: 600;"><?php esc_html_e('Page Preload on Hover', 'mega-menu-ajax'); ?></h4>
            <label>
                <input type="checkbox" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][preload_enabled]" 
                       value="1" 
                       <?php checked(!empty($settings['preload_enabled'])); ?>>
                <?php esc_html_e('Enable page preload on hover', 'mega-menu-ajax'); ?>
            </label>
            <br>
            <label>
                <?php esc_html_e('Preload delay (ms):', 'mega-menu-ajax'); ?>
                <input type="number" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][preload_delay]" 
                       value="<?php echo esc_attr($settings['preload_delay'] ?? 30); ?>" 
                       min="0" 
                       max="2000"
                       class="small-text">
            </label>
            <br>
             <label>
                 <input type="checkbox" 
                        name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][preload_css]" 
                        value="1" 
                        <?php checked(!empty($settings['preload_css'])); ?>>
                 <?php esc_html_e('Preload CSS assets', 'mega-menu-ajax'); ?>
             </label>
             <br>
             <label>
                 <input type="checkbox" 
                        name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][prerender_enabled]" 
                        value="1" 
                        <?php checked(!empty($settings['prerender_enabled'])); ?>>
                 <?php esc_html_e('Enable full page prerender', 'mega-menu-ajax'); ?>
             </label>
            <p class="description" style="margin-left: 22px; margin-top: 2px; color: #d63638;">
                <?php esc_html_e('Warning: Prerender loads entire pages in background (images, JS, CSS) for instant navigation. Uses more bandwidth.', 'mega-menu-ajax'); ?>
            </p>
        </fieldset>
        <fieldset class="mega-menu-ajax-background-preload-settings">
            <h4 style="margin: 15px 0 10px; font-weight: 600;"><?php esc_html_e('Background Preloading', 'mega-menu-ajax'); ?></h4>
            <p class="description" style="margin-bottom: 10px;">
                <?php esc_html_e('Preload pages in the background when the network is idle.', 'mega-menu-ajax'); ?>
            </p>
            <label>
                <input type="checkbox" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][background_preload_enabled]" 
                       value="1" 
                       <?php checked(!empty($settings['background_preload_enabled'])); ?>>
                <?php esc_html_e('Enable background preloading', 'mega-menu-ajax'); ?>
            </label>
            <br>
            <label>
                <?php esc_html_e('Concurrent preload limit:', 'mega-menu-ajax'); ?>
                <input type="number" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][background_preload_limit]" 
                       value="<?php echo esc_attr($settings['background_preload_limit'] ?? 5); ?>" 
                       min="1" 
                       max="10"
                       class="small-text">
            </label>
            <br>
            <label>
                <?php esc_html_e('Idle delay (ms):', 'mega-menu-ajax'); ?>
                <input type="number" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][background_preload_delay]" 
                       value="<?php echo esc_attr($settings['background_preload_delay'] ?? 2000); ?>" 
                       min="500" 
                       max="10000"
                       class="small-text">
            </label>
            <br>
            <label>
                <?php esc_html_e('Preload priority:', 'mega-menu-ajax'); ?>
                <select name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][background_preload_priority]">
                    <option value="conservative" <?php selected($settings['background_preload_priority'] ?? '', 'conservative'); ?>><?php esc_html_e('Conservative (low data usage)', 'mega-menu-ajax'); ?></option>
                    <option value="balanced" <?php selected($settings['background_preload_priority'] ?? '', 'balanced'); ?>><?php esc_html_e('Balanced', 'mega-menu-ajax'); ?></option>
                    <option value="aggressive" <?php selected($settings['background_preload_priority'] ?? '', 'aggressive'); ?>><?php esc_html_e('Aggressive (better performance)', 'mega-menu-ajax'); ?></option>
                </select>
            </label>
            <br>
            <label>
                <input type="checkbox" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][background_preload_on_wifi_only]" 
                       value="1" 
                       <?php checked(!empty($settings['background_preload_on_wifi_only'])); ?>>
                <?php esc_html_e('Only preload on fast connections', 'mega-menu-ajax'); ?>
            </label>
            <br>
            <label>
                <input type="checkbox" 
                       name="mega_menu_ajax_settings[<?php echo esc_attr($location); ?>][background_preload_when_idle_only]" 
                       value="1" 
                       <?php checked(!empty($settings['background_preload_when_idle_only'])); ?>>
                <?php esc_html_e('Only preload when network is idle', 'mega-menu-ajax'); ?>
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
