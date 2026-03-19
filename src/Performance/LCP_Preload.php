<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class LCP_Preload
{
    private $image_url;

    public function __construct()
    {
        $this->image_url = get_option('mega_menu_ajax_lcp_image_url', '');
        
        if (!empty($this->image_url)) {
            add_action('wp_head', [$this, 'output_preload'], 1);
        }
    }

    public function output_preload()
    {
        if (empty($this->image_url)) {
            return;
        }

        printf(
            '<link rel="preload" as="image" href="%s">%s',
            esc_url($this->image_url),
            "\n"
        );
    }
}
