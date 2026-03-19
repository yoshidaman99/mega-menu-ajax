<?php

namespace Mega_Menu_Ajax\Menu;

defined('ABSPATH') || exit;

class Style_Manager
{
    private $css_transient_key = 'mega_menu_ajax_css';

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_inline_css'], 99);
        add_action('wp_head', [$this, 'output_critical_css'], 1);
        add_action('wp_head', [$this, 'output_critical_js'], 1);
        add_action('customize_save_after', [$this, 'clear_css_cache']);
        add_action('update_option_mega_menu_ajax_settings', [$this, 'clear_css_cache']);
    }

    public function output_critical_css()
    {
        if (!wp_is_mobile()) {
            return;
        }
        
        $settings = get_option('mega_menu_ajax_settings', []);
        $has_enabled = false;
        
        foreach ($settings as $location => $location_settings) {
            if (!empty($location_settings['enabled'])) {
                $has_enabled = true;
                break;
            }
        }
        
        if (!$has_enabled) {
            return;
        }
        
        $critical_css = $this->get_critical_css();
        echo "<style id=\"mega-menu-ajax-critical-css\">\n{$critical_css}\n</style>\n";
    }

    private function get_critical_css()
    {
        $base_css = '.mega-menu-ajax-wrap{position:relative;display:block;width:100%}.mega-menu-ajax-menu{display:flex;flex-wrap:wrap;list-style:none;margin:0;padding:0}.mega-menu-ajax-item{position:relative;margin:0;padding:0}.mega-menu-ajax-item>a{display:flex;align-items:center;padding:.75rem 1rem;text-decoration:none;transition:all .3s ease}.mega-menu-ajax-item:hover>a,.mega-menu-ajax-item.mega-menu-ajax-active>a{text-decoration:none}.mega-menu-ajax-indicator{display:inline-flex;align-items:center;justify-content:center;margin-left:.25rem;font-size:.625em;transition:transform .3s ease}.mega-menu-ajax-indicator::after{content:"";border-style:solid;border-width:.25em .25em 0;border-color:currentColor transparent transparent}.mega-menu-ajax-item:hover>.mega-menu-ajax-indicator,.mega-menu-ajax-item.mega-menu-ajax-active>.mega-menu-ajax-indicator{transform:rotate(180deg)}.mega-menu-ajax-submenu{position:absolute;left:0;top:100%;min-width:200px;opacity:0;visibility:hidden;list-style:none;margin:0;padding:0;z-index:1000;transition:opacity .3s ease,visibility .3s ease;box-shadow:0 4px 12px rgba(0,0,0,.1)}.mega-menu-ajax-item:hover>.mega-menu-ajax-submenu,.mega-menu-ajax-item.mega-menu-ajax-active>.mega-menu-ajax-submenu{opacity:1;visibility:visible}.mega-menu-ajax-submenu .mega-menu-ajax-item{width:100%}.mega-menu-ajax-submenu .mega-menu-ajax-item>a{padding:.5rem 1rem}.mega-menu-ajax-submenu .mega-menu-ajax-submenu{left:100%;top:0}.mega-menu-ajax-submenu .mega-menu-ajax-item:hover>.mega-menu-ajax-submenu,.mega-menu-ajax-submenu .mega-menu-ajax-item.mega-menu-ajax-active>.mega-menu-ajax-submenu{opacity:1;visibility:visible}.mega-menu-ajax-lazy{position:relative}.mega-menu-ajax-loading{display:flex;align-items:center;justify-content:center;padding:1rem}.mega-menu-ajax-spinner{width:20px;height:20px;border:2px solid rgba(0,0,0,.1);border-top-color:currentColor;border-radius:50%;animation:megaMenuAjaxSpin .8s linear infinite}@keyframes megaMenuAjaxSpin{to{transform:rotate(360deg)}}.mega-menu-ajax-search{position:relative;margin:.5rem}.mega-menu-ajax-search-input{width:100%;padding:.5rem;border:1px solid #ddd;border-radius:4px;font-size:.875rem}.mega-menu-ajax-search-results{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;max-height:300px;overflow-y:auto;z-index:1001}.mega-menu-ajax-search-result{display:block;padding:.5rem 1rem;text-decoration:none;color:inherit}.mega-menu-ajax-search-result:hover{background:#f5f5f5}.mega-menu-ajax-placeholder{display:flex;align-items:center;justify-content:center;min-height:50px;padding:1rem;background:#f9f9f9;border:1px dashed #ddd}.mega-menu-ajax-placeholder-content{display:flex;align-items:center;gap:.5rem}.mega-menu-ajax-toggle{display:none;padding:.75rem 1rem;background:none;border:none;cursor:pointer}.mega-menu-ajax-toggle-icon{display:block;width:24px;height:2px;background:currentColor;position:relative}.mega-menu-ajax-toggle-icon::before,.mega-menu-ajax-toggle-icon::after{content:"";position:absolute;width:100%;height:100%;background:currentColor;left:0}.mega-menu-ajax-toggle-icon::before{top:-8px}.mega-menu-ajax-toggle-icon::after{top:8px}';
        
        $mobile_css = '@media screen and (max-width:768px){.mega-menu-ajax-toggle{display:block}.mega-menu-ajax-menu{display:none;flex-direction:column;width:100%}.mega-menu-ajax-wrap.mega-menu-ajax-open .mega-menu-ajax-menu{display:flex}.mega-menu-ajax-item>a{justify-content:space-between}.mega-menu-ajax-submenu{position:static;opacity:1;visibility:visible;display:none;box-shadow:none;padding-left:1rem}.mega-menu-ajax-item.mega-menu-ajax-active>.mega-menu-ajax-submenu{display:block}}';
        
        $rtl_css = '.rtl .mega-menu-ajax-submenu .mega-menu-ajax-submenu{left:auto;right:100%}.rtl .mega-menu-ajax-indicator{margin-left:0;margin-right:.25rem}.mega-menu-ajax-preloading>a::after{content:"";display:inline-block;width:12px;height:12px;margin-left:6px;border:2px solid rgba(0,0,0,.1);border-top-color:currentColor;border-radius:50%;animation:megaMenuAjaxSpin .8s linear infinite;vertical-align:middle}.rtl .mega-menu-ajax-preloading>a::after{margin-left:0;margin-right:6px}';
        
        return $base_css . $mobile_css . $rtl_css;
    }

    public function output_critical_js()
    {
        $settings = get_option('mega_menu_ajax_settings', []);
        $has_enabled = false;
        
        foreach ($settings as $location => $location_settings) {
            if (!empty($location_settings['enabled'])) {
                $has_enabled = true;
                break;
            }
        }
        
        if (!$has_enabled) {
            return;
        }
        
        $js = '(function(){var d=document;function q(s){return d.querySelectorAll(s)};function h(e,c){e.classList.toggle(c)};d.addEventListener("click",function(e){var t=e.target;if(t.closest(".mega-menu-ajax-toggle")){h(t.closest(".mega-menu-ajax-wrap"),"mega-menu-ajax-open");e.preventDefault()}else if(t.closest(".mega-menu-ajax-item>a")){var p=t.closest(".mega-menu-ajax-item");if(p&&p.querySelector(".mega-menu-ajax-submenu")&&window.innerWidth<=768){h(p,"mega-menu-ajax-active");e.preventDefault()}}if(!e.target.closest(".mega-menu-ajax-wrap")){q(".mega-menu-ajax-wrap").forEach(function(w){w.classList.remove("mega-menu-ajax-open")})}})})();';
        
        echo "<script id=\"mega-menu-ajax-critical-js\">\n{$js}\n</script>\n";
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
