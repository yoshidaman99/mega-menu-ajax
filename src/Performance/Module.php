<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class Module
{
    private static $instance = null;
    private $settings;
    private $lcp_optimizer;
    private $css_optimizer;
    private $beacon_handler;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->settings = get_option('mega_menu_ajax_performance', $this->get_default_settings());
        
        if ($this->is_enabled('lcp_optimizer')) {
            $this->lcp_optimizer = new LCP_Optimizer($this->settings);
            $this->beacon_handler = new Beacon_Handler();
        }
        
        if ($this->is_enabled('css_optimizer')) {
            $this->css_optimizer = new CSS_Optimizer($this->settings);
        }
        
        $this->init_hooks();
    }

    private function get_default_settings()
    {
        return [
            'lcp_optimizer_enabled' => false,
            'css_optimizer_enabled' => false,
            'lcp_cache_ttl' => 7 * DAY_IN_SECONDS,
            'css_cache_ttl' => 7 * DAY_IN_SECONDS,
            'exclude_selectors' => [],
            'exclude_css_handles' => [],
            'debug_mode' => false,
        ];
    }

    private function init_hooks()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_mega_menu_ajax_clear_performance_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_beacon_script'], 999);
    }

    public function is_enabled($module)
    {
        return !empty($this->settings[$module . '_enabled']);
    }

    public function get_settings()
    {
        return $this->settings;
    }

    public function register_settings()
    {
        register_setting('mega_menu_ajax_performance', 'mega_menu_ajax_performance', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    public function sanitize_settings($input)
    {
        $sanitized = $this->get_default_settings();
        
        $sanitized['lcp_optimizer_enabled'] = !empty($input['lcp_optimizer_enabled']);
        $sanitized['css_optimizer_enabled'] = !empty($input['css_optimizer_enabled']);
        
        $sanitized['lcp_cache_ttl'] = isset($input['lcp_cache_ttl']) 
            ? absint($input['lcp_cache_ttl']) 
            : 7 * DAY_IN_SECONDS;
        $sanitized['lcp_cache_ttl'] = max(DAY_IN_SECONDS, min(30 * DAY_IN_SECONDS, $sanitized['lcp_cache_ttl']));
        
        $sanitized['css_cache_ttl'] = isset($input['css_cache_ttl']) 
            ? absint($input['css_cache_ttl']) 
            : 7 * DAY_IN_SECONDS;
        $sanitized['css_cache_ttl'] = max(DAY_IN_SECONDS, min(30 * DAY_IN_SECONDS, $sanitized['css_cache_ttl']));
        
        if (!empty($input['exclude_selectors']) && is_string($input['exclude_selectors'])) {
            $selectors = preg_split('/\r\n|\r|\n/', $input['exclude_selectors']);
            $sanitized['exclude_selectors'] = array_filter(array_map('sanitize_text_field', $selectors));
        }
        
        if (!empty($input['exclude_css_handles']) && is_string($input['exclude_css_handles'])) {
            $handles = preg_split('/\r\n|\r|\n/', $input['exclude_css_handles']);
            $sanitized['exclude_css_handles'] = array_filter(array_map('sanitize_text_field', $handles));
        }
        
        $sanitized['debug_mode'] = !empty($input['debug_mode']);
        
        return $sanitized;
    }

    public function enqueue_beacon_script()
    {
        if (!$this->is_enabled('lcp_optimizer')) {
            return;
        }
        
        if (is_admin()) {
            return;
        }

        wp_enqueue_script(
            'mega-menu-ajax-performance-beacon',
            MEGA_MENU_AJAX_URL . 'assets/js/performance-beacon.js',
            [],
            MEGA_MENU_AJAX_VERSION,
            true
        );

        $current_url = $this->get_current_url_path();
        $lcp_data = get_transient('mega_menu_ajax_lcp_' . md5($current_url));

        wp_localize_script('mega-menu-ajax-performance-beacon', 'megaMenuPerformance', [
            'restUrl' => rest_url('mega-menu-ajax/v1/'),
            'nonce' => wp_create_nonce('mega_menu_ajax_performance_nonce'),
            'currentUrl' => $current_url,
            'hasLcpData' => !empty($lcp_data),
            'debug' => !empty($this->settings['debug_mode']),
        ]);
    }

    private function get_current_url_path()
    {
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        return strtok($url, '?');
    }

    public function ajax_clear_cache()
    {
        check_ajax_referer('mega_menu_ajax_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'mega-menu-ajax')]);
        }
        
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_mega_menu_ajax_lcp_%' 
             OR option_name LIKE '_transient_timeout_mega_menu_ajax_lcp_%'
             OR option_name LIKE '_transient_mega_menu_ajax_css_%'
             OR option_name LIKE '_transient_timeout_mega_menu_ajax_css_%'"
        );
        
        wp_send_json_success(['message' => __('Performance cache cleared successfully', 'mega-menu-ajax')]);
    }

    public function render_settings_section()
    {
        ?>
        <div class="mega-menu-ajax-performance-settings">
            <h3><?php esc_html_e('Performance Optimization', 'mega-menu-ajax'); ?></h3>
            <p class="description">
                <?php esc_html_e('Site-wide performance optimizations for LCP and CSS loading.', 'mega-menu-ajax'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('LCP Optimizer', 'mega-menu-ajax'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="mega_menu_ajax_performance[lcp_optimizer_enabled]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['lcp_optimizer_enabled'])); ?>>
                            <?php esc_html_e('Enable LCP optimization', 'mega-menu-ajax'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Auto-detects LCP elements and adds fetchpriority="high", preloads background images, and disables lazy-loading for LCP content.', 'mega-menu-ajax'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('CSS Optimizer', 'mega-menu-ajax'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="mega_menu_ajax_performance[css_optimizer_enabled]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['css_optimizer_enabled'])); ?>>
                            <?php esc_html_e('Enable CSS optimization', 'mega-menu-ajax'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Extracts critical CSS inline and loads remaining CSS asynchronously to eliminate render-blocking.', 'mega-menu-ajax'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Cache Duration', 'mega-menu-ajax'); ?></th>
                    <td>
                        <select name="mega_menu_ajax_performance[lcp_cache_ttl]">
                            <option value="<?php echo DAY_IN_SECONDS; ?>" <?php selected($this->settings['lcp_cache_ttl'], DAY_IN_SECONDS); ?>>
                                <?php esc_html_e('1 day', 'mega-menu-ajax'); ?>
                            </option>
                            <option value="<?php echo 3 * DAY_IN_SECONDS; ?>" <?php selected($this->settings['lcp_cache_ttl'], 3 * DAY_IN_SECONDS); ?>>
                                <?php esc_html_e('3 days', 'mega-menu-ajax'); ?>
                            </option>
                            <option value="<?php echo 7 * DAY_IN_SECONDS; ?>" <?php selected($this->settings['lcp_cache_ttl'], 7 * DAY_IN_SECONDS); ?>>
                                <?php esc_html_e('7 days (recommended)', 'mega-menu-ajax'); ?>
                            </option>
                            <option value="<?php echo 14 * DAY_IN_SECONDS; ?>" <?php selected($this->settings['lcp_cache_ttl'], 14 * DAY_IN_SECONDS); ?>>
                                <?php esc_html_e('14 days', 'mega-menu-ajax'); ?>
                            </option>
                            <option value="<?php echo 30 * DAY_IN_SECONDS; ?>" <?php selected($this->settings['lcp_cache_ttl'], 30 * DAY_IN_SECONDS); ?>>
                                <?php esc_html_e('30 days', 'mega-menu-ajax'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('How long to cache LCP and CSS optimization data.', 'mega-menu-ajax'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Exclude Selectors', 'mega-menu-ajax'); ?></th>
                    <td>
                        <textarea name="mega_menu_ajax_performance[exclude_selectors]" 
                                  rows="4" 
                                  cols="50"
                                  class="large-text"><?php echo esc_textarea(implode("\n", $this->settings['exclude_selectors'] ?? [])); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('CSS selectors to exclude from optimization (one per line). E.g., .no-optimize, #specific-element', 'mega-menu-ajax'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Exclude CSS Handles', 'mega-menu-ajax'); ?></th>
                    <td>
                        <textarea name="mega_menu_ajax_performance[exclude_css_handles]" 
                                  rows="4" 
                                  cols="50"
                                  class="large-text"><?php echo esc_textarea(implode("\n", $this->settings['exclude_css_handles'] ?? [])); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('CSS handles to exclude from async loading (one per line). E.g., admin-bar, dashicons', 'mega-menu-ajax'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Debug Mode', 'mega-menu-ajax'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="mega_menu_ajax_performance[debug_mode]" 
                                   value="1" 
                                   <?php checked(!empty($this->settings['debug_mode'])); ?>>
                            <?php esc_html_e('Enable debug logging', 'mega-menu-ajax'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Log optimization details to browser console.', 'mega-menu-ajax'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Clear Cache', 'mega-menu-ajax'); ?></th>
                    <td>
                        <button type="button" 
                                class="button" 
                                id="mega-menu-ajax-clear-performance-cache"
                                data-nonce="<?php echo esc_attr(wp_create_nonce('mega_menu_ajax_admin_nonce')); ?>">
                            <?php esc_html_e('Clear Performance Cache', 'mega-menu-ajax'); ?>
                        </button>
                        <span id="mega-menu-ajax-cache-clear-status"></span>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}
