<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class Early_Hints
{
    private static $instance = null;
    private $preload_links = [];
    private $preconnect_links = [];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'init_early_hints'], 1);
        add_action('send_headers', [$this, 'send_link_headers'], 1);
        add_filter('wp_resource_hints', [$this, 'add_resource_hints'], 10, 2);
        add_action('mega_menu_ajax_preload_submenu', [$this, 'queue_preload'], 10, 2);
    }

    public function init_early_hints()
    {
        $this->collect_menu_assets();
    }

    private function collect_menu_assets()
    {
        $settings = get_option('mega_menu_ajax_settings', []);
        
        if (empty($settings)) {
            return;
        }
        
        $plugin_url = MEGA_MENU_AJAX_URL;
        $version = MEGA_MENU_AJAX_VERSION;
        
        $this->add_preload('style', $plugin_url . 'assets/css/frontend.css?v=' . $version, 'high');
        $this->add_preconnect(set_url_scheme(admin_url('admin-ajax.php'), 'https'));
        
        $lcp_image = get_option('mega_menu_ajax_lcp_image_url', '');
        if (!empty($lcp_image)) {
            $this->add_preload('image', $lcp_image, 'high');
        }
        
        $fonts = get_option('mega_menu_ajax_preload_fonts', '');
        if (!empty($fonts)) {
            $font_urls = array_filter(array_map('trim', explode("\n", $fonts)));
            foreach ($font_urls as $font_url) {
                $font_type = $this->get_font_type($font_url);
                if ($font_type) {
                    $this->add_preload('font', $font_url, 'high', [
                        'crossorigin' => 'anonymous',
                        'type' => $font_type,
                    ]);
                }
            }
        }
        
        $rest_url = rest_url('mega-menu-ajax/v1/');
        $this->add_preconnect(preg_replace('#^https?://([^/]+)/.*$#', 'https://$1', $rest_url));
    }

    private function get_font_type($url)
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $types = [
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject',
        ];
        
        return $types[$ext] ?? null;
    }

    public function add_preload($type, $href, $priority = 'low', $attrs = [])
    {
        $this->preload_links[] = [
            'type' => $type,
            'href' => $href,
            'priority' => $priority,
            'attrs' => $attrs,
        ];
    }

    public function add_preconnect($href)
    {
        if (!in_array($href, $this->preconnect_links)) {
            $this->preconnect_links[] = $href;
        }
    }

    public function send_link_headers()
    {
        if (headers_sent()) {
            return;
        }
        
        $links = [];
        
        foreach ($this->preconnect_links as $href) {
            $links[] = '<' . $href . '>; rel=preconnect';
        }
        
        usort($this->preload_links, function($a, $b) {
            $priorities = ['high' => 0, 'medium' => 1, 'low' => 2];
            return ($priorities[$a['priority']] ?? 2) <=> ($priorities[$b['priority']] ?? 2);
        });
        
        foreach ($this->preload_links as $preload) {
            $link = '<' . $preload['href'] . '>; rel=preload; as=' . $preload['type'];
            
            if (!empty($preload['attrs'])) {
                foreach ($preload['attrs'] as $key => $value) {
                    if ($value === true) {
                        $link .= '; ' . $key;
                    } else {
                        $link .= '; ' . $key . '="' . $value . '"';
                    }
                }
            }
            
            $links[] = $link;
        }
        
        if (!empty($links)) {
            @header('Link: ' . implode(', ', $links), false);
        }
        
        @header('X-Mega-Menu-Ajax-Cache: HIT');
        @header('Cache-Control: public, max-age=3600, stale-while-revalidate=86400');
    }

    public function add_resource_hints($urls, $relation_type)
    {
        if ($relation_type === 'preconnect') {
            foreach ($this->preconnect_links as $href) {
                if (!in_array($href, $urls, true)) {
                    $urls[] = $href;
                }
            }
        }
        
        if ($relation_type === 'preload') {
            foreach ($this->preload_links as $preload) {
                if ($preload['priority'] === 'high') {
                    $url = [
                        'href' => $preload['href'],
                        'as' => $preload['type'],
                    ];
                    
                    if (!empty($preload['attrs']['crossorigin'])) {
                        $url['crossorigin'] = $preload['attrs']['crossorigin'];
                    }
                    
                    $urls[] = $url;
                }
            }
        }
        
        return $urls;
    }

    public function queue_preload($item_id, $location)
    {
        $rest_url = rest_url('mega-menu-ajax/v1/submenu/' . $item_id);
        $this->add_preload('fetch', $rest_url, 'low', ['crossorigin' => true]);
    }

    public function send_103_early_hints()
    {
        if (!headers_sent() && php_sapi_name() !== 'cli') {
            $links = [];
            
            foreach ($this->preconnect_links as $href) {
                $links[] = '<' . $href . '>; rel=preconnect';
            }
            
            if (!empty($links)) {
                if (function_exists('http_response_code')) {
                    http_response_code(103);
                    header('Link: ' . implode(', ', $links));
                    flush();
                }
            }
        }
    }
}
