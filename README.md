# Mega Menu Ajax

A fast, AJAX-powered mega menu plugin for WordPress with Elementor integration.

## Features

- **Native WordPress Menu Integration** - Works with your existing WordPress menus
- **AJAX Sub-Menu Loading** - Load sub-menus on demand for faster initial page loads
- **Lazy Loading** - Optionally load entire menus after page content
- **Real-Time Search** - Filter menu items with AJAX-powered search
- **Elementor Widget** - Drag and drop mega menus in Elementor
- **Responsive Design** - Mobile-friendly with customizable breakpoints
- **Custom Animations** - Fade, slide, or combined effects
- **RTL Support** - Right-to-left language support built-in
- **Accessibility** - ARIA attributes and keyboard navigation

## Installation

1. Upload the plugin files to `/wp-content/plugins/mega-menu-ajax/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Mega Menu** in the admin menu to configure settings
4. Enable mega menu for your menu locations

## Configuration

### Menu Locations

For each registered menu location, you can configure:

- **Enable Mega Menu** - Turn mega menu on/off for this location
- **Load sub-menus via AJAX** - Load sub-menu content only when needed
- **Lazy load entire menu** - Load menu after page content
- **Enable search** - Add real-time menu search
- **Animation Effect** - Choose fade, slide, or combined effect
- **Mobile Breakpoint** - Set responsive breakpoint in pixels

### Elementor Integration

1. Edit a page with Elementor
2. Find "Mega Menu Ajax" in the widgets panel
3. Drag to your page and select a menu location
4. Style using Elementor's built-in controls

## Usage with Theme

Add to your theme's `header.php` or use in templates:

```php
<?php
wp_nav_menu([
    'theme_location' => 'primary',
    'container_class' => 'mega-menu-ajax-wrap',
]);
?>
```

## Filters and Hooks

### Disable for specific location

```php
add_filter('mega_menu_ajax_enabled', function($enabled, $location) {
    if ($location === 'secondary') {
        return false;
    }
    return $enabled;
}, 10, 2);
```

### Customize CSS classes

```php
add_filter('mega_menu_ajax_css_class', function($classes, $item, $args, $depth) {
    $classes[] = 'my-custom-class';
    return $classes;
}, 10, 4);
```

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher

## Changelog

### 1.0.0
- Initial release

## License

GPL v2 or later

## Author

[yoshidaman99](https://github.com/yoshidaman99)
