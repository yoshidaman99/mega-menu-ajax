<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class CSS_Extractor
{
    private $html;
    private $settings;
    private $critical_selectors;

    public function __construct($html, $settings = [])
    {
        $this->html = $html;
        $this->settings = $settings;
        $this->critical_selectors = $this->get_critical_selectors();
    }

    public function extract_critical()
    {
        $css_urls = $this->extract_css_urls();
        if (empty($css_urls)) {
            return '';
        }

        $all_css = $this->fetch_all_css($css_urls);
        if (empty($all_css)) {
            return '';
        }

        $critical_css = $this->filter_critical_rules($all_css);
        
        $critical_css = $this->minify_css($critical_css);

        return $critical_css;
    }

    private function extract_css_urls()
    {
        preg_match_all('/<link[^>]+href=["\']([^"\']+\.css[^"\']*)["\'][^>]*>/i', $this->html, $matches);
        
        $urls = [];
        foreach ($matches[1] as $url) {
            if (strpos($url, '//') === 0) {
                $url = (is_ssl() ? 'https:' : 'http:') . $url;
            }
            $urls[] = $url;
        }

        return array_unique($urls);
    }

    private function fetch_all_css($urls)
    {
        $css = '';
        
        foreach ($urls as $url) {
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'sslverify' => false,
            ]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $css .= wp_remote_retrieve_body($response) . "\n";
            }
        }

        return $css;
    }

    private function filter_critical_rules($css)
    {
        $critical_css = '';
        
        $parsed = $this->parse_css($css);
        
        foreach ($parsed as $rule) {
            if ($this->is_rule_critical($rule)) {
                $critical_css .= $rule['raw'] . "\n";
            }
        }

        return $critical_css;
    }

    private function parse_css($css)
    {
        $rules = [];
        $pattern = '/([^{]+)\{([^}]*)\}/s';
        
        preg_match_all($pattern, $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $selectors = trim($match[1]);
            $declarations = trim($match[2]);
            
            if (empty($selectors) || empty($declarations)) {
                continue;
            }

            $rules[] = [
                'selectors' => array_map('trim', explode(',', $selectors)),
                'declarations' => $declarations,
                'raw' => $selectors . '{' . $declarations . '}',
            ];
        }

        return $rules;
    }

    private function is_rule_critical($rule)
    {
        $above_fold_selectors = $this->get_above_fold_selectors();
        
        foreach ($rule['selectors'] as $selector) {
            $selector = trim($selector);
            
            if (empty($selector)) {
                continue;
            }

            if ($this->selector_matches_critical($selector, $above_fold_selectors)) {
                return true;
            }

            if ($this->has_above_fold_properties($rule['declarations'])) {
                return true;
            }
        }

        return false;
    }

    private function selector_matches_critical($selector, $critical_selectors)
    {
        $selector_lower = strtolower($selector);
        
        foreach ($critical_selectors as $critical) {
            if (strpos($selector_lower, strtolower($critical)) !== false) {
                return true;
            }
        }

        if (preg_match('/^(html|body|:root)([,\s\[{:].*)?$/i', $selector)) {
            return true;
        }

        if (preg_match('/^(\.|#|\[)[a-zA-Z0-9_-]*([\s,>]+[a-zA-Z0-9_-]*)*$/', $selector)) {
            return true;
        }

        return false;
    }

    private function has_above_fold_properties($declarations)
    {
        $critical_properties = [
            'display',
            'visibility',
            'position',
            'top',
            'left',
            'width',
            'height',
            'min-height',
            'margin',
            'margin-top',
            'padding',
            'padding-top',
            'font-size',
            'line-height',
            'background',
            'background-color',
            'background-image',
            'color',
            'z-index',
            'overflow',
            'flex',
            'grid',
            'transform',
        ];

        $declarations_lower = strtolower($declarations);
        
        foreach ($critical_properties as $prop) {
            if (strpos($declarations_lower, $prop . ':') !== false) {
                return true;
            }
        }

        return false;
    }

    private function get_above_fold_selectors()
    {
        $defaults = [
            'header',
            '.header',
            '#header',
            'nav',
            '.nav',
            '#nav',
            'menu',
            '.menu',
            '#menu',
            '.mega-menu',
            '.mega-menu-ajax',
            '.elementor-element',
            '.elementor-widget',
            '.elementor-container',
            '.elementor-row',
            '.elementor-column',
            'h1',
            'h2',
            '.hero',
            '.banner',
            '.title',
            '.logo',
            '.site-logo',
            '.site-branding',
            '.top-bar',
            '.navbar',
            '.navigation',
            '.main-navigation',
            '.page-header',
            '.entry-header',
            '.site-header',
            '.wp-block',
        ];

        return array_merge($defaults, $this->critical_selectors);
    }

    private function get_critical_selectors()
    {
        $selectors = [
            'mega-menu-ajax',
            'mega-menu-ajax-wrap',
            'mega-menu-ajax-menu',
        ];

        return $selectors;
    }

    private function minify_css($css)
    {
        $css = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        $css = str_replace(';}', '}', $css);
        $css = trim($css);

        return $css;
    }
}
