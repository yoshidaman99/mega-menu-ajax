<?php
/**
 * Plugin Name: Mega Menu Ajax
 * Plugin URI:  https://github.com/yoshidaman99/mega-menu-ajax
 * Description: A fast, AJAX-powered mega menu plugin with Elementor integration, lazy loading, and real-time search.
 * Version:     1.0.5
 * Author:      Jerel Yoshida
 * Author URI:  https://github.com/yoshidaman99
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: mega-menu-ajax
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('MEGA_MENU_AJAX_VERSION', '1.0.5');
define('MEGA_MENU_AJAX_PATH', plugin_dir_path(__FILE__));
define('MEGA_MENU_AJAX_URL', plugin_dir_url(__FILE__));
define('MEGA_MENU_AJAX_BASENAME', plugin_basename(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'Mega_Menu_Ajax\\';
    $base_dir = MEGA_MENU_AJAX_PATH . 'src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

add_action('plugins_loaded', function () {
    \Mega_Menu_Ajax\Core\Plugin::get_instance();
});
