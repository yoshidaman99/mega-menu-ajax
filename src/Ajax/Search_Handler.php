<?php

namespace Mega_Menu_Ajax\Ajax;

defined('ABSPATH') || exit;

class Search_Handler
{
    public function __construct()
    {
        add_action('wp_ajax_mega_menu_ajax_search', [$this, 'ajax_search']);
        add_action('wp_ajax_nopriv_mega_menu_ajax_search', [$this, 'ajax_search']);
    }

    public function ajax_search()
    {
        check_ajax_referer('mega_menu_ajax_nonce', 'nonce');
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        
        if (strlen($query) < 2) {
            wp_send_json_success([]);
        }
        
        $results = self::search($query, $location);
        
        wp_send_json_success($results);
    }

    public static function search($query, $location = null)
    {
        $results = [];
        $query = strtolower($query);
        
        $menu_id = null;
        if ($location) {
            $locations = get_nav_menu_locations();
            $menu_id = $locations[$location] ?? null;
        }
        
        $args = [
            'post_type' => 'nav_menu_item',
            'posts_per_page' => 20,
            's' => $query,
        ];
        
        if ($menu_id) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'nav_menu',
                    'field' => 'term_id',
                    'terms' => $menu_id,
                ],
            ];
        }
        
        $items = get_posts($args);
        
        if (!empty($items)) {
            foreach ($items as $item) {
                $menu_item = wp_setup_nav_menu_item($item);
                
                $results[] = [
                    'id' => $menu_item->ID,
                    'title' => $menu_item->title,
                    'url' => $menu_item->url,
                    'type' => $menu_item->type,
                    'object' => $menu_item->object,
                    'relevance' => self::calculate_relevance($query, $menu_item->title),
                ];
            }
        }
        
        usort($results, function ($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });
        
        return array_slice($results, 0, 10);
    }

    private static function calculate_relevance($query, $title)
    {
        $title = strtolower($title);
        $relevance = 0;
        
        if ($title === $query) {
            $relevance = 100;
        } elseif (strpos($title, $query) === 0) {
            $relevance = 80;
        } elseif (strpos($title, $query) !== false) {
            $relevance = 50;
        }
        
        return $relevance;
    }
}
