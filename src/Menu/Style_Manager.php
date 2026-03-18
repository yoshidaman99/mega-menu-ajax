<?php

namespace Mega_Menu_Ajax\Menu;

defined('ABSPATH') || exit;

class Style_Manager
{
    private $css_transient_key = 'mega_menu_ajax_css';

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_inline_css'], 99);
        add_action('customize_save_after', [$this, 'clear_css_cache']);
        add_action('update_option_mega_menu_ajax_settings', [$this, 'clear_css_cache']);
    }

    public function enqueue_inline_css()
    {
        $css = $this->get_generated_css();
        
        if (!empty($css)) {
            wp_add_inline_style('mega-menu-ajax-frontend', $css);
        }
    }

    public function get_generated_css()
    {
        $cached = get_transient($this->css_transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $css = $this->generate_css();
        set_transient($this->css_transient_key, $css, DAY_IN_SECONDS);
        
        return $css;
    }

    private function generate_css()
    {
        $settings = get_option('mega_menu_ajax_settings', []);
        $css = '';
        
        foreach ($settings as $location => $location_settings) {
            if (empty($location_settings['enabled'])) {
                continue;
            }
            
            $breakpoint = $location_settings['mobile_breakpoint'] ?? 768;
            $effect = $location_settings['effect'] ?? 'fade';
            
            $css .= $this->generate_location_css($location, $location_settings);
        }
        
        return $css;
    }

    private function generate_location_css($location, $settings)
    {
        $breakpoint = $settings['mobile_breakpoint'] ?? 768;
        $effect = $settings['effect'] ?? 'fade';
        
        $css = "
/* Mega Menu Ajax - Location: {$location} */
.mega-menu-ajax-wrap-{$location} {
    position: relative;
}

.mega-menu-ajax-wrap-{$location} .mega-menu-ajax-menu {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
}

.mega-menu-ajax-wrap-{$location} .mega-menu-ajax-item {
    position: relative;
    margin: 0;
    padding: 0;
}

.mega-menu-ajax-wrap-{$location} .mega-menu-ajax-submenu {
    position: absolute;
    left: 0;
    top: 100%;
    min-width: 200px;
    opacity: 0;
    visibility: hidden;
    list-style: none;
    margin: 0;
    padding: 0;
    z-index: 1000;
";
        
        if ($effect === 'fade') {
            $css .= "    transition: opacity 0.3s ease;";
        } elseif ($effect === 'slide') {
            $css .= "    transform: translateY(-10px); transition: transform 0.3s ease, visibility 0.3s ease;";
        } elseif ($effect === 'fade_slide') {
            $css .= "    transform: translateY(-10px); transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s ease;";
        }
        
        $css .= "
}

.mega-menu-ajax-wrap-{$location} .mega-menu-ajax-item:hover > .mega-menu-ajax-submenu,
.mega-menu-ajax-wrap-{$location} .mega-menu-ajax-item.mega-menu-ajax-active > .mega-menu-ajax-submenu {
    opacity: 1;
    visibility: visible;
";
        
        if ($effect === 'slide' || $effect === 'fade_slide') {
            $css .= "    transform: translateY(0);";
        }
        
        $css .= "
}

.mega-menu-ajax-wrap-{$location} .mega-menu-ajax-indicator::after {
    content: '';
    display: inline-block;
    border: 4px solid transparent;
    border-top-color: currentColor;
    margin-left: 0.5em;
    vertical-align: middle;
}

/* Mobile Styles */
@media (max-width: {$breakpoint}px) {
    .mega-menu-ajax-wrap-{$location} .mega-menu-ajax-menu {
        flex-direction: column;
    }
    
    .mega-menu-ajax-wrap-{$location} .mega-menu-ajax-submenu {
        position: static;
        opacity: 1;
        visibility: visible;
        display: none;
    }
    
    .mega-menu-ajax-wrap-{$location} .mega-menu-ajax-item.mega-menu-ajax-active > .mega-menu-ajax-submenu {
        display: block;
    }
}
";
        
        return $css;
    }

    public function clear_css_cache()
    {
        delete_transient($this->css_transient_key);
    }
}
