<?php

namespace Mega_Menu_Ajax\Menu;

defined('ABSPATH') || exit;

class Walker extends \Walker_Nav_Menu
{
    private $ajax_enabled = false;
    private $location_settings = [];
    private $current_depth = 0;
    private $toplevel_only = false;
    private $location = '';
    private $menu_id = 0;

    public function start_lvl(&$output, $depth = 0, $args = null)
    {
        $this->current_depth = $depth + 1;
        
        $classes = ['mega-menu-ajax-submenu', 'mega-menu-ajax-depth-' . ($depth + 1)];
        
        $this->location = $args->theme_location ?? '';
        $settings = get_option('mega_menu_ajax_settings', []);
        $this->location_settings = $settings[$this->location] ?? [];
        $this->ajax_enabled = !empty($this->location_settings['ajax_submenu']);
        $this->toplevel_only = !empty($this->location_settings['toplevel_only']);
        
        if ($this->ajax_enabled && $depth === 0) {
            $classes[] = 'mega-menu-ajax-lazy';
            $classes[] = 'mega-menu-ajax-submenu-placeholder';
            $output .= '<ul class="' . esc_attr(implode(' ', $classes)) . '" data-loaded="false" data-depth="' . ($depth + 1) . '">';
            return;
        }
        
        if ($this->toplevel_only && $depth > 0) {
            return;
        }
        
        $output .= '<ul class="' . esc_attr(implode(' ', $classes)) . '">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null)
    {
        $this->current_depth = $depth;
        
        if ($this->ajax_enabled && $depth === 0) {
            $output .= '</ul>';
            return;
        }
        
        if ($this->toplevel_only && $depth > 0) {
            return;
        }
        
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        if ($this->ajax_enabled && $depth > 0) {
            return;
        }
        
        if ($this->toplevel_only && $depth > 0) {
            return;
        }
        
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $classes[] = 'mega-menu-ajax-item';
        $classes[] = 'menu-item-' . $item->ID;
        
        $has_children = $this->item_has_children($item->ID);
        
        if ($has_children) {
            $classes[] = 'mega-menu-ajax-has-children';
        }
        
        $class_names = join(' ', apply_filters('mega_menu_ajax_css_class', array_filter($classes), $item, $args, $depth));
        $class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';
        
        $data_attr = ' data-menu-item-id="' . esc_attr($item->ID) . '"';
        
        if ($has_children && $this->ajax_enabled && $depth === 0) {
            $data_attr .= ' data-ajax-submenu="true"';
        }
        
        $output .= '<li' . $class_names . $data_attr . '>';
        
        $atts = [
            'title' => $item->attr_title ?? '',
            'target' => $item->target ?? '',
            'rel' => $item->xfn ?? '',
            'href' => $item->url ?? '',
        ];
        
        if ($has_children) {
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
        
        if ($has_children) {
            $item_output .= '<span class="mega-menu-ajax-indicator" aria-hidden="true"></span>';
        }
        
        $item_output .= '</a>';
        $item_output .= $args->after ?? '';
        
        $output .= apply_filters('mega_menu_ajax_walker_start_el', $item_output, $item, $depth, $args);
    }

    public function end_el(&$output, $item, $depth = 0, $args = null)
    {
        if ($this->ajax_enabled && $depth > 0) {
            return;
        }
        
        if ($this->toplevel_only && $depth > 0) {
            return;
        }
        
        $output .= '</li>';
    }

    private function item_has_children($item_id)
    {
        $args = [
            'meta_key' => '_menu_item_menu_item_parent',
            'meta_value' => $item_id,
            'post_type' => 'nav_menu_item',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'suppress_filters' => false,
        ];
        $children = get_posts($args);
        return !empty($children);
    }
}
