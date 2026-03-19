<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class Font_Preload
{
    private $fonts;

    public function __construct()
    {
        $this->fonts = get_option('mega_menu_ajax_preload_fonts', '');
        
        if (!empty($this->fonts)) {
            add_action('wp_head', [$this, 'output_preload'], 1);
        }
    }

    public function output_preload()
    {
        if (empty($this->fonts)) {
            return;
        }

        $font_urls = array_filter(array_map('trim', explode("\n", $this->fonts)));
        
        foreach ($font_urls as $font_url) {
            $font_url = esc_url($font_url);
            if (empty($font_url)) {
                continue;
            }
            
            printf(
                '<link rel="preload" as="font" href="%s" crossorigin>%s',
                $font_url,
                "\n"
            );
        }
    }

    private function get_font_type($url)
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        $types = [
            'woff2' => 'font/woff2',
            'woff'  => 'font/woff',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
            'eot'   => 'application/vnd.ms-fontobject',
        ];
        
        return $types[$extension] ?? 'font/woff2';
    }
}
