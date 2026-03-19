<?php

namespace Mega_Menu_Ajax\Elementor;

defined('ABSPATH') || exit;

class Integration
{
    public function __construct()
    {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
    }

    public function register_category($elements_manager)
    {
        $elements_manager->add_category('mega-menu-ajax', [
            'title' => __('Mega Menu Ajax', 'mega-menu-ajax'),
            'icon' => 'fa fa-menu',
        ]);
    }

    public function register_widgets($widgets_manager)
    {
        $widgets_manager->register(new Widgets\Menu_Widget());
    }
}
