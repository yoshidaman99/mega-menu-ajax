<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class CSS_Optimizer
{
    private $settings;
    private $buffer_started = false;
    private $current_url;

    public function __construct($settings)
    {
        $this->settings = $settings;
        $this->current_url = $this->get_current_url();
        
        $this->init_hooks();
    }

    private function get_current_url()
    {
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        return strtok($url, '?');
    }

    private function init_hooks()
    {
        if (is_admin() || $this->is_amp_endpoint()) {
            return;
        }

        add_action('template_redirect', [$this, 'start_buffer'], 1);
        add_action('shutdown', [$this, 'process_buffer'], 0);
    }

    private function is_amp_endpoint()
    {
        return function_exists('is_amp_endpoint') && is_amp_endpoint();
    }

    public function start_buffer()
    {
        if ($this->buffer_started) {
            return;
        }

        $cached_css = $this->get_cached_critical_css();
        if ($cached_css) {
            add_action('wp_head', function() use ($cached_css) {
                echo '<style id="mega-menu-ajax-critical-css">' . $cached_css . "</style>\n";
            }, 1);
        }

        $this->buffer_started = true;
        ob_start([$this, 'process_html_output']);
    }

    public function process_buffer()
    {
        if ($this->buffer_started) {
            ob_end_flush();
        }
    }

    public function process_html_output($html)
    {
        if (empty($html)) {
            return $html;
        }

        $cached_css = $this->get_cached_critical_css();
        
        if (!$cached_css) {
            $css_extractor = new CSS_Extractor($html, $this->settings);
            $critical_css = $css_extractor->extract_critical();
            
            if (!empty($critical_css)) {
                $this->cache_critical_css($critical_css);
            }
        }

        $html = $this->make_css_async($html);

        return $html;
    }

    private function make_css_async($html)
    {
        $exclude_handles = $this->settings['exclude_css_handles'] ?? [];
        
        preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $matches);
        
        if (empty($matches[0])) {
            return $html;
        }

        $replacements = [];

        foreach ($matches[0] as $link_tag) {
            if ($this->should_exclude_link($link_tag, $exclude_handles)) {
                continue;
            }

            if (strpos($link_tag, 'id="mega-menu-ajax-critical-css"') !== false) {
                continue;
            }

            if (strpos($link_tag, 'media=') !== false) {
                if (preg_match('/media=["\']print["\']/i', $link_tag)) {
                    continue;
                }
            }

            $async_tag = $this->convert_to_async($link_tag);
            if ($async_tag !== $link_tag) {
                $replacements[$link_tag] = $async_tag;
            }
        }

        foreach ($replacements as $original => $async) {
            $html = str_replace($original, $async, $html);
        }

        return $html;
    }

    private function should_exclude_link($link_tag, $exclude_handles)
    {
        $link_tag_lower = strtolower($link_tag);

        foreach ($exclude_handles as $handle) {
            $handle = trim($handle);
            if (empty($handle)) {
                continue;
            }
            if (strpos($link_tag_lower, strtolower($handle)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function convert_to_async($link_tag)
    {
        if (preg_match('/media=["\']([^"\']*)["\']/i', $link_tag, $matches)) {
            $original_media = $matches[1];
            $link_tag = str_replace(
                'media="' . $original_media . '"',
                'media="print" onload="this.media=\'' . $original_media . '\'"',
                $link_tag
            );
            $link_tag = str_replace(
                "media='" . $original_media . "'",
                "media='print' onload=\"this.media='" . $original_media . "'\"",
                $link_tag
            );
        } else {
            $link_tag = preg_replace(
                '/<link/',
                '<link media="print" onload="this.media=\'all\'"',
                $link_tag,
                1
            );
        }

        $noscript = '<noscript>' . htmlspecialchars_decode(htmlspecialchars($link_tag)) . '</noscript>';
        $noscript = preg_replace('/\s*onload=["\'][^"\']*["\']/', '', $noscript);
        $noscript = str_replace('media="print"', 'media="all"', $noscript);

        return $link_tag . $noscript;
    }

    private function get_cached_critical_css()
    {
        $transient_key = 'mega_menu_ajax_css_' . md5($this->current_url);
        return get_transient($transient_key);
    }

    private function cache_critical_css($css)
    {
        $transient_key = 'mega_menu_ajax_css_' . md5($this->current_url);
        $ttl = $this->settings['css_cache_ttl'] ?? 7 * DAY_IN_SECONDS;
        set_transient($transient_key, $css, $ttl);
    }

    public function clear_cache($url = null)
    {
        if ($url) {
            $transient_key = 'mega_menu_ajax_css_' . md5($url);
            delete_transient($transient_key);
        } else {
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_mega_menu_ajax_css_%' 
                 OR option_name LIKE '_transient_timeout_mega_menu_ajax_css_%'"
            );
        }
    }
}
