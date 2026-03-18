<?php

namespace Mega_Menu_Ajax\Menu;

defined('ABSPATH') || exit;

class Walker extends \Walker_Nav_Menu
{
    public function start_lvl(&$output, $depth = 0, $args = null)
    {
        $classes = ['mega-menu-ajax-submenu', 'mega-menu-ajax-depth-' . ($depth + 1)];
        
        $location = $args->theme_location ?? '';
        $settings = get_option('mega_menu_ajax_settings', []);
        $location_settings = $settings[$location] ?? [];
        
        if (!empty($location_settings['ajax_submenu'])) {
            $classes[] = 'mega-menu-ajax-lazy';
        }
        
        $output .= '<ul class="' . esc_attr(implode(' ', $classes)) . '">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null)
    {
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $classes[] = 'mega-menu-ajax-item';
        $classes[] = 'menu-item-' . $item->ID;
        
        if (in_array('menu-item-has-children', $classes, true)) {
            $classes[] = 'mega-menu-ajax-has-children';
        }
        
        $class_names = join(' ', apply_filters('mega_menu_ajax_css_class', array_filter($classes), $item, $args, $depth));
        $class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';
        
        $output .= '<li' . $class_names . '>';
        
        $atts = [
            'title' => $item->attr_title ?? '',
            'target' => $item->target ?? '',
            'rel' => $item->xfn ?? '',
            'href' => $item->url ?? '',
        ];
        
        if (in_array('menu-item-has-children', $classes, true)) {
            $atts['aria-expanded'] = 'false';
            $atts['aria-haspopup'] = 'true';
        }
        
        $atts = apply_filters('mega_menu_ajax_link_attributes', $atts, $item, $args, $depth);
        
        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (!empty($value)) {
                $value = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }
        
        $title = apply_filters('the_title', $item->title, $item->ID);
        $title = apply_filters('mega_menu_ajax_title', $title, $item, $args, $depth);
        
        $item_output = $args->before ?? '';
        $item_output .= '<a' . $attributes . '>';
        $item_output .= ($args->link_before ?? '') . $title . ($args->link_after ?? '');
        
        if (in_array('menu-item-has-children', $classes, true)) {
            $item_output .= '<span class="mega-menu-ajax-indicator" aria-hidden="true"></span>';
        }
        
        $item_output .= '</a>';
        $item_output .= $args->after ?? '';
        
        $output .= apply_filters('mega_menu_ajax_walker_start_el', $item_output, $item, $depth, $args);
    }

    public function end_el(&$output, $item, $depth = 0, $args = null)
    {
        $output .= '</li>';
    }
}
