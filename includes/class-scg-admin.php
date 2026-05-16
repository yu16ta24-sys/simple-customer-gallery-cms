<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_menu', [__CLASS__, 'restrict_menu_for_customer'], 999);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    public static function register_menu() {
        add_menu_page(
            '専用CMS',
            '専用CMS',
            'read',
            'scg-dashboard',
            ['SCG_Dashboard', 'render'],
            'dashicons-screenoptions',
            3
        );

        
        add_submenu_page('scg-dashboard', 'ギャラリー管理', 'ギャラリー管理', 'upload_files', 'scg-photo-manage', ['SCG_Gallery_Manage', 'render_manage_page']);
        add_submenu_page('scg-dashboard', 'ブログを書く', 'ブログを書く', 'edit_posts', 'scg-blog-add', [__CLASS__, 'render_placeholder']);
        add_submenu_page('scg-dashboard', 'ブログ一覧', 'ブログ一覧', 'edit_posts', 'scg-blog-list', [__CLASS__, 'render_placeholder']);
        add_submenu_page('scg-dashboard', 'お知らせを書く', 'お知らせを書く', 'edit_posts', 'scg-news-add', [__CLASS__, 'render_placeholder']);
        add_submenu_page('scg-dashboard', 'お知らせ一覧', 'お知らせ一覧', 'edit_posts', 'scg-news-list', [__CLASS__, 'render_placeholder']);

        add_submenu_page(
            'scg-dashboard',
            'ギャラリーカテゴリ',
            'ギャラリーカテゴリ',
            'manage_options',
            'edit-tags.php?taxonomy=scg_gallery_category&post_type=scg_photo'
        );
    }

    public static function restrict_menu_for_customer() {
        if (current_user_can('manage_options')) {
            return;
        }

        $user = wp_get_current_user();
        if (!in_array('customer_manager', (array) $user->roles, true)) {
            return;
        }

        remove_menu_page('index.php');
        remove_menu_page('edit.php');
        remove_menu_page('upload.php');
        remove_menu_page('edit.php?post_type=page');
        remove_menu_page('edit.php?post_type=scg_photo');
        remove_menu_page('edit-comments.php');
        remove_menu_page('themes.php');
        remove_menu_page('plugins.php');
        remove_menu_page('users.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
    }

    public static function enqueue_admin_assets($hook) {
        wp_enqueue_style('scg-admin', SCG_CMS_URL . 'assets/css/admin.css', [], SCG_CMS_VERSION);

        if (strpos($hook, 'scg-photo-manage') !== false) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('scg-manage', SCG_CMS_URL . 'assets/js/admin-manage.js', ['jquery', 'jquery-ui-sortable'], SCG_CMS_VERSION, true);
            wp_localize_script('scg-manage', 'SCG_MANAGE', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scg_manage_photos'),
                'upload_nonce' => wp_create_nonce('scg_upload_photos'),
                'max_files' => 10,
                'max_file_size' => 20 * 1024 * 1024,
                'max_file_size_label' => '20MB',
            ]);
        }
    }

    public static function render_placeholder() {
        $title = get_admin_page_title();
        ?>
        <div class="wrap scg-wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <div class="scg-card">
                <p>この画面は次フェーズで実装します。</p>
            </div>
        </div>
        <?php
    }
}
