=== Mega Menu Ajax ===
Contributors: yoshidaman99
Tags: menu, mega menu, ajax menu, responsive menu, navigation, elementor
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.0.36
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A fast, AJAX-powered mega menu plugin with Elementor integration and real-time search.

== Description ==

Mega Menu Ajax transforms your WordPress navigation menus into fast, responsive mega menus with AJAX-powered loading.

= Key Features =

* **AJAX Sub-Menu Loading** - Load sub-menu content on demand for faster page loads
* **Lazy Load** - Optionally load entire menus after page content
* **Real-Time Search** - Filter menu items instantly with AJAX search
* **Elementor Widget** - Drag and drop mega menus in Elementor builder
* **Responsive Design** - Mobile-friendly with customizable breakpoints
* **Custom Animations** - Fade, slide, or combined animation effects
* **Native Integration** - Works with existing WordPress menus
* **RTL Support** - Built-in right-to-left language support
* **Accessibility** - ARIA attributes and keyboard navigation

= Usage =

1. Install and activate the plugin
2. Go to **Mega Menu** in WordPress admin
3. Configure settings for each menu location
4. Enable AJAX loading, lazy load, or search as needed
5. Customize animations and mobile breakpoint

= Elementor Integration =

The plugin includes a dedicated Elementor widget with full styling controls for seamless integration with your Elementor-built pages.

== Installation ==

= From WordPress Dashboard =

1. Go to Plugins > Add New
2. Search for "Mega Menu Ajax"
3. Click "Install Now"
4. Activate the plugin

= Manual Installation =

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Configure under Mega Menu settings

== Frequently Asked Questions ==

= Does this work with any theme? =

Yes! The plugin integrates with the native WordPress menu system and works with any theme that uses `wp_nav_menu()`.

= Can I use this without Elementor? =

Absolutely! The plugin works with standard WordPress themes and menus. Elementor integration is optional.

= Will this slow down my site? =

No! The AJAX loading features actually improve performance by loading menu content only when needed.

== Screenshots ==

1. Admin settings page showing menu location configuration
2. Elementor widget with styling options
3. Frontend mega menu with hover animation
4. Mobile responsive menu with toggle button
5. Real-time search filtering menu items

== Changelog ==

= 1.0.35 =
* Fix render-blocking CSS by switching preload strategy to media="print" onload pattern
* Remove unsupported `type` attribute from font preload links to fix Lighthouse warnings
* Add defensive filters to strip `type` from preload hints and tags site-wide

= 1.0.0 =
* Initial release
* AJAX sub-menu loading
* Lazy load functionality
* Real-time menu search
* Elementor widget integration
* Responsive design with mobile toggle
* Custom animation effects (fade, slide, combined)
* RTL support
* Accessibility features (ARIA, keyboard navigation)

== Upgrade Notice ==

= 1.0.0 =
Initial release. Welcome to Mega Menu Ajax!
