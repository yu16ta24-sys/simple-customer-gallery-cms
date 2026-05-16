<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Content_Post_Type {
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_types']);
    }

    public static function register_post_types() {
        self::register_single_post_type('scg_blog', 'ブログ', 'ブログ');
        self::register_single_post_type('scg_news', 'お知らせ', 'お知らせ');
    }

    private static function register_single_post_type($post_type, $singular, $plural) {
        register_post_type($post_type, [
            'labels' => [
                'name' => $plural,
                'singular_name' => $singular,
                'add_new_item' => $singular . 'を追加',
                'edit_item' => $singular . 'を編集',
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }
}
