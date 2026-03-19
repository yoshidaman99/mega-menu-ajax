<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class LCP_Optimizer
{
    private $settings;
    private $lcp_data;
    private $current_url;

    public function __construct($settings)
    {
        $this->settings = $settings;
        $this->current_url = $this->get_current_url();
        $this->lcp_data = $this->get_lcp_data();
        
        $this->init_hooks();
    }

    private function get_current_url()
    {
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        return strtok($url, '?');
    }

    private function get_lcp_data()
    {
        $transient_key = 'mega_menu_ajax_lcp_' . md5($this->current_url);
        return get_transient($transient_key);
    }

    private function init_hooks()
    {
        add_action('wp_head', [$this, 'preload_lcp_resources'], 1);
        add_filter('wp_get_attachment_image_attributes', [$this, 'optimize_image_attributes'], 10, 3);
        add_filter('img_lazy_load_filters', [$this, 'disable_lazy_load_for_lcp'], 10, 2);
        add_action('init', [$this, 'disable_native_lazy_load_for_lcp']);
        add_filter('the_content', [$this, 'optimize_content_images'], 999);
        add_action('wp_footer', [$this, 'output_debug_info'], 999);
    }

    public function preload_lcp_resources()
    {
        if (empty($this->lcp_data)) {
            return;
        }

        $exclude_selectors = $this->settings['exclude_selectors'] ?? [];
        if ($this->is_excluded($this->lcp_data['selector'] ?? '', $exclude_selectors)) {
            return;
        }

        if (!empty($this->lcp_data['imageUrl'])) {
            $this->output_preload_tag($this->lcp_data['imageUrl'], 'image');
        }

        if (!empty($this->lcp_data['backgroundImage'])) {
            $bg_url = $this->extract_url_from_css($this->lcp_data['backgroundImage']);
            if ($bg_url) {
                $this->output_preload_tag($bg_url, 'image');
            }
        }

        if (!empty($this->lcp_data['elementId'])) {
            $this->output_elementor_background_preload($this->lcp_data['elementId']);
        }
    }

    private function output_preload_tag($url, $as_type)
    {
        if (empty($url)) {
            return;
        }

        printf(
            '<link rel="preload" as="%s" href="%s" fetchpriority="high">%s',
            esc_attr($as_type),
            esc_url($url),
            "\n"
        );
    }

    private function output_elementor_background_preload($element_id)
    {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        $document = \Elementor\Plugin::$instance->documents->get($post_id);
        if (!$document) {
            return;
        }

        $elements = $document->get_elements_data();
        $element = $this->find_element_by_id($elements, $element_id);

        if ($element && !empty($element['settings']['background_image']['url'])) {
            $this->output_preload_tag($element['settings']['background_image']['url'], 'image');
        }
    }

    private function find_element_by_id($elements, $target_id)
    {
        foreach ($elements as $element) {
            if (isset($element['id']) && $element['id'] === $target_id) {
                return $element;
            }
            if (!empty($element['elements'])) {
                $found = $this->find_element_by_id($element['elements'], $target_id);
                if ($found) {
                    return $found;
                }
            }
        }
        return null;
    }

    public function optimize_image_attributes($attr, $attachment, $size)
    {
        if (empty($this->lcp_data)) {
            return $attr;
        }

        $exclude_selectors = $this->settings['exclude_selectors'] ?? [];
        
        if (!empty($this->lcp_data['imageUrl'])) {
            $attachment_url = wp_get_attachment_url($attachment->ID);
            if ($attachment_url && strpos($this->lcp_data['imageUrl'], basename($attachment_url)) !== false) {
                if (!$this->is_excluded($this->lcp_data['selector'], $exclude_selectors)) {
                    $attr['fetchpriority'] = 'high';
                    unset($attr['loading']);
                }
            }
        }

        return $attr;
    }

    public function disable_lazy_load_for_lcp($lazy_load, $image_html)
    {
        if (empty($this->lcp_data) || empty($this->lcp_data['imageUrl'])) {
            return $lazy_load;
        }

        if (strpos($image_html, $this->lcp_data['imageUrl']) !== false) {
            return false;
        }

        return $lazy_load;
    }

    public function disable_native_lazy_load_for_lcp()
    {
        add_filter('wp_lazy_loading_enabled', function($enabled, $tag_name, $context) {
            if ($tag_name !== 'img' || empty($this->lcp_data)) {
                return $enabled;
            }

            $exclude_selectors = $this->settings['exclude_selectors'] ?? [];
            if ($this->is_excluded($this->lcp_data['selector'] ?? '', $exclude_selectors)) {
                return $enabled;
            }

            if (!empty($this->lcp_data['imageUrl']) && $context) {
                if (strpos($context, $this->lcp_data['imageUrl']) !== false) {
                    return false;
                }
            }

            return $enabled;
        }, 10, 3);
    }

    public function optimize_content_images($content)
    {
        if (empty($this->lcp_data) || empty($this->lcp_data['imageUrl'])) {
            return $content;
        }

        $exclude_selectors = $this->settings['exclude_selectors'] ?? [];
        if ($this->is_excluded($this->lcp_data['selector'], $exclude_selectors)) {
            return $content;
        }

        $image_url = $this->lcp_data['imageUrl'];
        $escaped_url = preg_quote($image_url, '/');

        $content = preg_replace_callback(
            '/<img([^>]*?)src=["\']' . $escaped_url . '["\']([^>]*)>/i',
            function($matches) {
                $before = $matches[1];
                $after = $matches[2];
                
                $before = preg_replace('/\s*loading=["\'][^"\']*["\']/i', '', $before);
                $after = preg_replace('/\s*loading=["\'][^"\']*["\']/i', '', $after);
                
                if (strpos($before, 'fetchpriority') === false && strpos($after, 'fetchpriority') === false) {
                    $before .= ' fetchpriority="high"';
                }
                
                return '<img' . $before . 'src="' . $this->lcp_data['imageUrl'] . '"' . $after . '>';
            },
            $content
        );

        return $content;
    }

    private function extract_url_from_css($css_value)
    {
        if (preg_match('/url\(["\']?([^)"\']+)["\']?\)/i', $css_value, $matches)) {
            return $matches[1];
        }
        return $css_value;
    }

    private function is_excluded($selector, $exclude_selectors)
    {
        if (empty($exclude_selectors) || empty($selector)) {
            return false;
        }

        foreach ($exclude_selectors as $exclude) {
            $exclude = trim($exclude);
            if (empty($exclude)) {
                continue;
            }
            if (strpos($selector, $exclude) !== false) {
                return true;
            }
        }

        return false;
    }

    public function output_debug_info()
    {
        if (empty($this->settings['debug_mode'])) {
            return;
        }

        $debug_data = [
            'currentUrl' => $this->current_url,
            'hasLcpData' => !empty($this->lcp_data),
            'lcpData' => $this->lcp_data,
            'settings' => $this->settings,
        ];

        printf(
            '<script>console.log("Mega Menu Ajax LCP Debug:", %s);</script>',
            wp_json_encode($debug_data, JSON_PRETTY_PRINT)
        );
    }
}
