<?php

namespace Mega_Menu_Ajax\Performance;

defined('ABSPATH') || exit;

class Menu_Cache
{
    private static $instance = null;
    private $cache_group = 'mega_menu_ajax';
    private $default_ttl = DAY_IN_SECONDS;
    private $use_object_cache = false;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->use_object_cache = $this->detect_object_cache();
        
        add_action('wp_update_nav_menu', [$this, 'clear_menu_cache'], 10, 1);
        add_action('wp_update_nav_menu_item', [$this, 'clear_menu_cache_by_item'], 10, 3);
        add_action('customize_save_after', [$this, 'clear_all_caches']);
        add_action('switch_theme', [$this, 'clear_all_caches']);
    }

    private function detect_object_cache()
    {
        global $wp_object_cache;
        return !empty($wp_object_cache) && 
               (isset($wp_object_cache->redis) || isset($wp_object_cache->memcache) || 
                (defined('WP_REDIS_DISABLED') && !WP_REDIS_DISABLED) ||
                class_exists('Redis'));
    }

    public function get_menu_html($location, $menu_id)
    {
        $cache_key = $this->get_cache_key('menu_html', $location, $menu_id);
        
        if ($this->use_object_cache) {
            $cached = wp_cache_get($cache_key, $this->cache_group);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $cached = get_transient($cache_key);
        return $cached !== false ? $cached : null;
    }

    public function set_menu_html($location, $menu_id, $html, $ttl = null)
    {
        $cache_key = $this->get_cache_key('menu_html', $location, $menu_id);
        $ttl = $ttl ?? $this->default_ttl;
        
        if ($this->use_object_cache) {
            wp_cache_set($cache_key, $html, $this->cache_group, $ttl);
        }
        
        set_transient($cache_key, $html, $ttl);
        
        $locations_key = $this->get_cache_key('cached_locations', '', '');
        $locations = get_transient($locations_key) ?: [];
        if (!in_array($location, $locations)) {
            $locations[] = $location;
            set_transient($locations_key, $locations, $ttl);
        }
    }

    public function get_toplevel_html($location, $menu_id)
    {
        $cache_key = $this->get_cache_key('toplevel_html', $location, $menu_id);
        
        if ($this->use_object_cache) {
            $cached = wp_cache_get($cache_key, $this->cache_group);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $cached = get_transient($cache_key);
        return $cached !== false ? $cached : null;
    }

    public function set_toplevel_html($location, $menu_id, $html, $ttl = null)
    {
        $cache_key = $this->get_cache_key('toplevel_html', $location, $menu_id);
        $ttl = $ttl ?? $this->default_ttl;
        
        if ($this->use_object_cache) {
            wp_cache_set($cache_key, $html, $this->cache_group, $ttl);
        }
        
        set_transient($cache_key, $html, $ttl);
    }

    public function get_submenu_html($parent_id)
    {
        $cache_key = $this->get_cache_key('submenu_html', $parent_id, '');
        
        if ($this->use_object_cache) {
            $cached = wp_cache_get($cache_key, $this->cache_group);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $cached = get_transient($cache_key);
        return $cached !== false ? $cached : null;
    }

    public function set_submenu_html($parent_id, $html, $ttl = null)
    {
        $cache_key = $this->get_cache_key('submenu_html', $parent_id, '');
        $ttl = $ttl ?? $this->default_ttl;
        
        if ($this->use_object_cache) {
            wp_cache_set($cache_key, $html, $this->cache_group, $ttl);
        }
        
        set_transient($cache_key, $html, $ttl);
        
        $index_key = $this->get_cache_key('submenu_index', '', '');
        $index = get_transient($index_key) ?: [];
        if (!in_array($parent_id, $index)) {
            $index[] = $parent_id;
            set_transient($index_key, $index, $ttl);
        }
    }

    public function get_menu_data($location)
    {
        $cache_key = $this->get_cache_key('menu_data', $location, '');
        
        if ($this->use_object_cache) {
            $cached = wp_cache_get($cache_key, $this->cache_group);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $cached = get_transient($cache_key);
        return $cached !== false ? $cached : null;
    }

    public function set_menu_data($location, $data, $ttl = null)
    {
        $cache_key = $this->get_cache_key('menu_data', $location, '');
        $ttl = $ttl ?? $this->default_ttl;
        
        if ($this->use_object_cache) {
            wp_cache_set($cache_key, $data, $this->cache_group, $ttl);
        }
        
        set_transient($cache_key, $data, $ttl);
    }

    public function get_submenu_data($parent_id)
    {
        $cache_key = $this->get_cache_key('submenu_data', $parent_id, '');
        
        if ($this->use_object_cache) {
            $cached = wp_cache_get($cache_key, $this->cache_group);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $cached = get_transient($cache_key);
        return $cached !== false ? $cached : null;
    }

    public function set_submenu_data($parent_id, $data, $ttl = null)
    {
        $cache_key = $this->get_cache_key('submenu_data', $parent_id, '');
        $ttl = $ttl ?? $this->default_ttl;
        
        if ($this->use_object_cache) {
            wp_cache_set($cache_key, $data, $this->cache_group, $ttl);
        }
        
        set_transient($cache_key, $data, $ttl);
        
        $index_key = $this->get_cache_key('submenu_data_index', '', '');
        $index = get_transient($index_key) ?: [];
        if (!in_array($parent_id, $index)) {
            $index[] = $parent_id;
            set_transient($index_key, $index, $ttl);
        }
    }

    public function clear_menu_cache($menu_id)
    {
        $locations = get_nav_menu_locations();
        $location = array_search($menu_id, $locations);
        
        if ($location) {
            $this->clear_location_cache($location);
        }
        
        $this->clear_menu_transients($menu_id);
    }

    public function clear_menu_cache_by_item($menu_id, $menu_item_db_id, $args)
    {
        $this->clear_menu_cache($menu_id);
        $this->clear_submenu_cache($menu_item_db_id);
    }

    public function clear_location_cache($location)
    {
        $locations = get_nav_menu_locations();
        $menu_id = $locations[$location] ?? 0;
        
        if ($menu_id) {
            $keys = [
                $this->get_cache_key('menu_html', $location, $menu_id),
                $this->get_cache_key('toplevel_html', $location, $menu_id),
                $this->get_cache_key('menu_data', $location, ''),
            ];
            
            foreach ($keys as $key) {
                if ($this->use_object_cache) {
                    wp_cache_delete($key, $this->cache_group);
                }
                delete_transient($key);
            }
        }
    }

    public function clear_submenu_cache($parent_id)
    {
        $cache_key = $this->get_cache_key('submenu_html', $parent_id, '');
        
        if ($this->use_object_cache) {
            wp_cache_delete($cache_key, $this->cache_group);
        }
        delete_transient($cache_key);
    }

    public function clear_all_caches()
    {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_mega_menu_ajax_%' 
            OR option_name LIKE '_site_transient_mega_menu_ajax_%'"
        );
        
        if ($this->use_object_cache) {
            wp_cache_flush();
        }
    }

    private function clear_menu_transients($menu_id)
    {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            OR option_name LIKE %s",
            '_transient_mega_menu_ajax_submenu_%',
            '_transient_mega_menu_ajax_menu_' . $menu_id . '%'
        ));
    }

    private function get_cache_key($type, $id1, $id2)
    {
        $version = get_option('mega_menu_ajax_cache_version', '1.0');
        return "mega_menu_ajax_{$type}_{$id1}_{$id2}_{$version}";
    }

    public function bump_cache_version()
    {
        update_option('mega_menu_ajax_cache_version', time());
    }

    public function get_stats()
    {
        return [
            'object_cache' => $this->use_object_cache,
            'cache_group' => $this->cache_group,
            'default_ttl' => $this->default_ttl,
        ];
    }
}
