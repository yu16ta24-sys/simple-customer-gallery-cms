<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Gallery_Taxonomy {
    public static function init() {
        add_action('init', [__CLASS__, 'register_taxonomy']);
    }

    public static function register_taxonomy() {
        register_taxonomy('scg_gallery_category', ['scg_photo'], [
            'labels' => [
                'name' => 'ギャラリーカテゴリ',
                'singular_name' => 'ギャラリーカテゴリ',
                'search_items' => 'カテゴリを検索',
                'all_items' => 'すべてのカテゴリ',
                'parent_item' => '親カテゴリ',
                'parent_item_colon' => '親カテゴリ:',
                'edit_item' => 'カテゴリを編集',
                'update_item' => 'カテゴリを更新',
                'add_new_item' => '新規カテゴリを追加',
                'new_item_name' => '新規カテゴリ名',
                'menu_name' => 'ギャラリーカテゴリ',
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_quick_edit' => false,
            'rewrite' => false,
            'public' => false,
        ]);
    }
}
