<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Gallery_Post_Type {
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function register_post_type() {
        register_post_type('scg_photo', [
            'labels' => [
                'name' => '写真',
                'singular_name' => '写真',
                'add_new_item' => '写真を追加',
                'edit_item' => '写真を編集',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-format-image',
            'capability_type' => 'post',
        ]);
    }
}
