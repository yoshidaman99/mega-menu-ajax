<?php

namespace Mega_Menu_Ajax\Elementor\Widgets;

defined('ABSPATH') || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class Menu_Widget extends Widget_Base
{
    public function get_name()
    {
        return 'mega-menu-ajax';
    }

    public function get_title()
    {
        return __('Mega Menu Ajax', 'mega-menu-ajax');
    }

    public function get_icon()
    {
        return 'eicon-nav-menu';
    }

    public function get_categories()
    {
        return ['mega-menu-ajax'];
    }

    public function get_script_depends()
    {
        return ['mega-menu-ajax-frontend'];
    }

    public function get_style_depends()
    {
        return ['mega-menu-ajax-frontend'];
    }

    protected function register_controls()
    {
        $this->start_controls_section('content_section', [
            'label' => __('Menu Settings', 'mega-menu-ajax'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $menus = $this->get_menus();
        
        $this->add_control('menu_location', [
            'label' => __('Menu Location', 'mega-menu-ajax'),
            'type' => Controls_Manager::SELECT,
            'options' => $menus,
            'default' => array_key_first($menus) ?: '',
        ]);

        $this->add_control('enable_ajax_submenu', [
            'label' => __('Load Sub-menus via AJAX', 'mega-menu-ajax'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __('Yes', 'mega-menu-ajax'),
            'label_off' => __('No', 'mega-menu-ajax'),
            'default' => 'yes',
        ]);

        $this->add_control('enable_lazy_load', [
            'label' => __('Lazy Load Menu', 'mega-menu-ajax'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __('Yes', 'mega-menu-ajax'),
            'label_off' => __('No', 'mega-menu-ajax'),
            'default' => 'no',
        ]);

        $this->add_control('enable_search', [
            'label' => __('Enable Search', 'mega-menu-ajax'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __('Yes', 'mega-menu-ajax'),
            'label_off' => __('No', 'mega-menu-ajax'),
            'default' => 'no',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('style_section', [
            'label' => __('Menu Style', 'mega-menu-ajax'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('menu_orientation', [
            'label' => __('Orientation', 'mega-menu-ajax'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'horizontal' => __('Horizontal', 'mega-menu-ajax'),
                'vertical' => __('Vertical', 'mega-menu-ajax'),
            ],
            'default' => 'horizontal',
            'prefix_class' => 'mega-menu-ajax-orientation-',
        ]);

        $this->add_control('menu_align', [
            'label' => __('Alignment', 'mega-menu-ajax'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'flex-start' => [
                    'title' => __('Left', 'mega-menu-ajax'),
                    'icon' => 'eicon-h-align-left',
                ],
                'center' => [
                    'title' => __('Center', 'mega-menu-ajax'),
                    'icon' => 'eicon-h-align-center',
                ],
                'flex-end' => [
                    'title' => __('Right', 'mega-menu-ajax'),
                    'icon' => 'eicon-h-align-right',
                ],
            ],
            'default' => 'flex-start',
            'selectors' => [
                '{{WRAPPER}} .mega-menu-ajax-menu' => 'justify-content: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'menu_typography',
            'label' => __('Typography', 'mega-menu-ajax'),
            'selector' => '{{WRAPPER}} .mega-menu-ajax-item a',
        ]);

        $this->add_control('menu_item_color', [
            'label' => __('Text Color', 'mega-menu-ajax'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .mega-menu-ajax-item a' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('menu_item_hover_color', [
            'label' => __('Hover Color', 'mega-menu-ajax'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .mega-menu-ajax-item a:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('menu_item_bg', [
            'label' => __('Background Color', 'mega-menu-ajax'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .mega-menu-ajax-menu' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('menu_item_hover_bg', [
            'label' => __('Hover Background', 'mega-menu-ajax'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .mega-menu-ajax-item:hover > a' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'menu_border',
            'selector' => '{{WRAPPER}} .mega-menu-ajax-menu',
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'menu_shadow',
            'selector' => '{{WRAPPER}} .mega-menu-ajax-submenu',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('submenu_style_section', [
            'label' => __('Sub-menu Style', 'mega-menu-ajax'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('submenu_bg', [
            'label' => __('Background Color', 'mega-menu-ajax'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .mega-menu-ajax-submenu' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'submenu_typography',
            'label' => __('Typography', 'mega-menu-ajax'),
            'selector' => '{{WRAPPER}} .mega-menu-ajax-submenu a',
        ]);

        $this->add_control('submenu_item_color', [
            'label' => __('Text Color', 'mega-menu-ajax'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .mega-menu-ajax-submenu a' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('submenu_item_hover_color', [
            'label' => __('Hover Color', 'mega-menu-ajax'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .mega-menu-ajax-submenu a:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('mobile_section', [
            'label' => __('Mobile Settings', 'mega-menu-ajax'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('mobile_breakpoint', [
            'label' => __('Mobile Breakpoint (px)', 'mega-menu-ajax'),
            'type' => Controls_Manager::NUMBER,
            'default' => 768,
            'min' => 0,
            'max' => 2000,
        ]);

        $this->add_control('mobile_toggle_text', [
            'label' => __('Toggle Button Text', 'mega-menu-ajax'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Menu', 'mega-menu-ajax'),
        ]);

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        
        if (!is_array($settings)) {
            return;
        }
        
        $location = $settings['menu_location'] ?? '';

        if (empty($location)) {
            echo '<p>' . esc_html__('Please select a menu location.', 'mega-menu-ajax') . '</p>';
            return;
        }

        $classes = ['mega-menu-ajax-widget'];
        
        if ($settings['enable_ajax_submenu'] === 'yes') {
            $classes[] = 'mega-menu-ajax-ajax-submenu';
        }
        
        if ($settings['enable_lazy_load'] === 'yes') {
            $classes[] = 'mega-menu-ajax-lazy';
        }
        
        if ($settings['enable_search'] === 'yes') {
            $classes[] = 'mega-menu-ajax-search-enabled';
        }

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '" data-location="' . esc_attr($location) . '" data-breakpoint="' . esc_attr($settings['mobile_breakpoint']) . '">';
        
        if ($settings['enable_search'] === 'yes') {
            echo '<div class="mega-menu-ajax-search-wrapper">';
            echo '<input type="search" class="mega-menu-ajax-search-input" placeholder="' . esc_attr__('Search menu...', 'mega-menu-ajax') . '">';
            echo '</div>';
        }

        if ($settings['enable_lazy_load'] === 'yes') {
            echo '<div class="mega-menu-ajax-placeholder">';
            echo '<span class="mega-menu-ajax-spinner"></span>';
            echo '<span class="mega-menu-ajax-loading-text">' . esc_html__('Loading menu...', 'mega-menu-ajax') . '</span>';
            echo '</div>';
        } else {
            wp_nav_menu([
                'theme_location' => $location,
                'menu_class' => 'mega-menu-ajax-menu',
                'container' => 'nav',
                'container_class' => 'mega-menu-ajax-nav',
                'fallback_cb' => false,
            ]);
        }

        echo '</div>';
    }

    private function get_menus()
    {
        $menus = [];
        $locations = get_registered_nav_menus();
        
        foreach ($locations as $location => $description) {
            $menus[$location] = $description;
        }
        
        return $menus;
    }
}
