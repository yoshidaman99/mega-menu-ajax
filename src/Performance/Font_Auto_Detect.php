<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class Font_Auto_Detect
{
    private $cache_key = 'mega_menu_ajax_detected_fonts';
    private $external_css_cache_key = 'mega_menu_ajax_external_css_fonts';
    private $cache_ttl = DAY_IN_SECONDS;
    private $max_preload_fonts = 2;

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'detect_and_cache_fonts'], 999);
        add_action('wp_head', [$this, 'output_font_preloads'], 1);
        add_action('wp_head', [$this, 'output_preconnect_hints'], 1);
        add_filter('wp_resource_hints', [$this, 'add_resource_hints'], 5, 2);
        add_action('update_option_mega_menu_ajax_settings', [$this, 'clear_cache']);
        add_action('customize_save_after', [$this, 'clear_cache']);
    }

    public function detect_and_cache_fonts()
    {
        $cached = get_transient($this->cache_key);
        if (false !== $cached) {
            return;
        }

        $fonts = $this->scan_registered_styles();

        if (!empty($fonts)) {
            set_transient($this->cache_key, $fonts, $this->cache_ttl);
        } else {
            set_transient($this->cache_key, [], HOUR_IN_SECONDS);
        }
    }

    public function output_font_preloads()
    {
        $fonts = get_transient($this->cache_key);
        if (!is_array($fonts)) {
            return;
        }

        $max = get_option('mega_menu_ajax_max_preload_fonts', 2);
        $max = apply_filters('mega_menu_ajax_max_preload_fonts', absint($max));
        $fonts = array_slice($fonts, 0, $max);

        foreach ($fonts as $font) {
            if (empty($font['url'])) {
                continue;
            }

            $crossorigin = '';
            if (!$this->is_same_origin($font['url'], site_url())) {
                $crossorigin = ' crossorigin';
            }

            printf(
                "<link rel='preload' as='font' href='%s' fetchpriority='high'%s>\n",
                esc_url($font['url']),
                $crossorigin
            );
        }
    }

    public function output_preconnect_hints()
    {
        $domains = $this->get_cdn_domains();
        if (empty($domains)) {
            return;
        }

        foreach ($domains as $domain) {
            $crossorigin = '';
            if (strpos($domain, 'gstatic') !== false) {
                $crossorigin = ' crossorigin';
            }
            printf(
                "<link rel='preconnect' href='%s'%s>\n",
                esc_url($domain),
                $crossorigin
            );
        }
    }

    public function add_resource_hints($urls, $relation_type)
    {
        if ($relation_type !== 'preconnect') {
            return $urls;
        }

        $domains = $this->get_cdn_domains();
        foreach ($domains as $domain) {
            $url = ['href' => $domain];
            if (strpos($domain, 'gstatic') !== false) {
                $url['crossorigin'] = 'anonymous';
            }
            $urls[] = $url;
        }

        return $urls;
    }

    public function clear_cache()
    {
        delete_transient($this->cache_key);
        delete_transient($this->external_css_cache_key);
    }

    private function get_cdn_domains()
    {
        $fonts = get_transient($this->cache_key);
        if (!is_array($fonts)) {
            return [];
        }

        $domains = [];
        $site_url = site_url();

        foreach ($fonts as $font) {
            $font_host = wp_parse_url($font['url'], PHP_URL_HOST);
            if (empty($font_host)) {
                continue;
            }
            if ($this->is_same_origin($font['url'], $site_url)) {
                continue;
            }
            $scheme = wp_parse_url($font['url'], PHP_URL_SCHEME) ?? 'https';
            $domain = $scheme . '://' . $font_host;
            if (!in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    private function scan_registered_styles()
    {
        global $wp_styles;

        if (empty($wp_styles) || empty($wp_styles->queue)) {
            return [];
        }

        $content_url = content_url();
        $content_dir = trailingslashit(WP_CONTENT_DIR);
        $fonts = [];
        $seen = [];
        $external_css_handles = [];

        foreach ($wp_styles->queue as $handle) {
            $style = $wp_styles->registered[$handle] ?? null;
            if (!$style || empty($style->src)) {
                continue;
            }

            $src = $style->src;
            if (strpos($src, '//') === 0) {
                $src = 'https:' . $src;
            } elseif (strpos($src, 'http') !== 0) {
                $src = site_url($src);
            }

            $is_external = $this->is_external_url($src, $content_url);

            if ($is_external) {
                $external_css_handles[] = [
                    'src' => $src,
                    'handle' => $handle,
                ];
                continue;
            }

            $path = $this->url_to_path($src, $content_url, $content_dir);
            if (!$path || !file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);
            if (empty($content)) {
                continue;
            }

            $this->extract_fonts_from_css($content, $src, $fonts, $seen);

            preg_match_all('/@import\s+(?:url\(\s*)?[\'"]([^\'"]+)[\'"]\s*(?:\))?\s*;/i', $content, $imports);
            foreach ($imports[1] as $import_url) {
                if ($this->is_font_url($import_url)) {
                    continue;
                }
                $resolved = $this->resolve_url($import_url, $src);
                if ($resolved && $this->is_external_url($resolved, $content_url)) {
                    $external_css_handles[] = [
                        'src' => $resolved,
                        'handle' => $handle . '_import',
                    ];
                }
            }
        }

        if (!empty($wp_styles->print_inline) && is_array($wp_styles->print_inline)) {
            foreach ($wp_styles->print_inline as $handle => $inline_data) {
                if (!is_array($inline_data)) {
                    continue;
                }
                foreach ($inline_data as $inline) {
                    if (!is_string($inline)) {
                        continue;
                    }
                    $this->extract_fonts_from_css($inline, site_url(), $fonts, $seen);
                }
            }
        }

        $external_fonts = $this->scan_external_css($external_css_handles);
        foreach ($external_fonts as $font) {
            if (!isset($seen[$font['url']])) {
                $seen[$font['url']] = true;
                $fonts[] = $font;
            }
        }

        return $fonts;
    }

    private function scan_external_css($handles)
    {
        if (empty($handles)) {
            return [];
        }

        $external_cache = get_transient($this->external_css_cache_key);
        if (is_array($external_cache)) {
            $all_urls = array_column($handles, 'src');
            $cached_urls = array_column($external_cache, 'source_url');
            if (count(array_intersect($all_urls, $cached_urls)) === count($all_urls)) {
                $result = [];
                foreach ($external_cache as $entry) {
                    if (in_array($entry['source_url'], $all_urls, true)) {
                        $result[] = ['url' => $entry['url'], 'type' => $entry['type']];
                    }
                }
                return $result;
            }
        }

        $fonts = [];
        $seen = [];

        foreach ($handles as $item) {
            $response = wp_remote_get($item['src'], [
                'timeout' => 5,
                'sslverify' => true,
                'headers' => ['Accept' => 'text/css,*/*;q=0.1'],
            ]);

            if (is_wp_error($response)) {
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                continue;
            }

            $this->extract_fonts_from_css($body, $item['src'], $fonts, $seen, true);
        }

        if (!empty($fonts)) {
            $cache_entries = [];
            foreach ($fonts as $font) {
                $cache_entries[] = [
                    'url' => $font['url'],
                    'type' => $font['type'],
                    'source_url' => $font['source_url'] ?? '',
                ];
            }
            set_transient($this->external_css_cache_key, $cache_entries, $this->cache_ttl);
        }

        return $fonts;
    }

    private function extract_fonts_from_css($css, $base_url, &$fonts, &$seen, $track_source = false)
    {
        preg_match_all('/@font-face\s*\{([^}]*)\}/si', $css, $font_faces);
        foreach ($font_faces[1] as $face_content) {
            preg_match('/font-family\s*:\s*[\'"]?([^\'"};]+)/i', $face_content, $family_match);
            $family = strtolower(trim($family_match[1] ?? ''));

            if ($this->is_skip_font($family, $base_url)) {
                continue;
            }

            preg_match_all('/url\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/i', $face_content, $urls);
            $woff2_url = null;

            foreach ($urls[1] as $font_url) {
                if (strpos($font_url, 'data:') === 0) {
                    continue;
                }

                $absolute_url = $this->resolve_url($font_url, $base_url);
                if (!$absolute_url || isset($seen[$absolute_url])) {
                    continue;
                }

                $ext = strtolower(pathinfo(
                    wp_parse_url($absolute_url, PHP_URL_PATH),
                    PATHINFO_EXTENSION
                ));

                if ($ext === 'woff2' && $woff2_url === null) {
                    $woff2_url = $absolute_url;
                }
            }

            if (!$woff2_url) {
                continue;
            }

            $seen[$woff2_url] = true;
            $entry = [
                'url' => $woff2_url,
                'type' => 'font/woff2',
            ];
            if ($track_source) {
                $entry['source_url'] = $base_url;
            }
            $fonts[] = $entry;
        }
    }

    private function is_skip_font($family, $source_url)
    {
        static $skip_families = [
            'font awesome',
            'fa-brands',
            'fa-regular',
            'fa-solid',
            'eicons',
            'woocommerce',
            'dashicons',
            'genericons',
            'star',
            'woocommerce-icons',
            'fontawesome',
            'el-icon',
            'elementor-icons',
        ];

        $family_lower = strtolower($family);
        foreach ($skip_families as $skip) {
            if ($family_lower === $skip || strpos($family_lower, $skip) !== false) {
                return true;
            }
        }

        $skip_paths = [
            '/font-awesome/',
            '/fonts/fontawesome',
            '/eicons/',
            '/woocommerce/assets/fonts/',
            '/woocommerce/fonts/',
            '/dashicons',
            '/genericons/',
            '/elementor/assets/lib/',
        ];

        $url_lower = strtolower($source_url);
        foreach ($skip_paths as $skip) {
            if (strpos($url_lower, $skip) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_external_url($url, $content_url)
    {
        $url_host = strtolower(wp_parse_url($url, PHP_URL_HOST));
        $content_host = strtolower(wp_parse_url($content_url, PHP_URL_HOST));
        return $url_host && $content_host && $url_host !== $content_host;
    }

    private function is_font_url($url)
    {
        $ext = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($ext, ['woff2', 'woff', 'ttf', 'otf', 'eot'], true);
    }

    private function url_to_path($url, $content_url, $content_dir)
    {
        $parsed = wp_parse_url($url);
        if (empty($parsed['path'])) {
            return null;
        }

        $content_parsed = wp_parse_url($content_url);
        $content_path = $content_parsed['path'] ?? '';
        $host = $parsed['host'] ?? '';
        $content_host = $content_parsed['host'] ?? '';

        if ($host && $content_host && strtolower($host) !== strtolower($content_host)) {
            return null;
        }

        $relative = substr($parsed['path'], strlen($content_path));
        if ($relative === $parsed['path'] && strlen($content_path) > 0) {
            return null;
        }

        $path = wp_normalize_path($content_dir . $relative);
        return file_exists($path) ? $path : null;
    }

    private function resolve_url($relative, $base)
    {
        if (preg_match('#^(https?:)?//#i', $relative) || strpos($relative, 'data:') === 0) {
            return $relative;
        }

        return rtrim(dirname($base), '/') . '/' . ltrim($relative, '/');
    }

    private function get_font_mime($ext)
    {
        $types = [
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject',
        ];

        return $types[$ext] ?? null;
    }

    private function normalize_host($host)
    {
        $host = strtolower($host);
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        return $host;
    }

    private function is_same_origin($url1, $url2)
    {
        $host1 = $this->normalize_host(wp_parse_url($url1, PHP_URL_HOST) ?? '');
        $host2 = $this->normalize_host(wp_parse_url($url2, PHP_URL_HOST) ?? '');
        return $host1 !== '' && $host1 === $host2;
    }
}
