<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Admin {
    public static function init() {
        self::ensure_default_options();
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_menu', [__CLASS__, 'restrict_menu_for_customer'], 999);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_filter('admin_body_class', [__CLASS__, 'admin_body_class']);
        add_action('admin_head', [__CLASS__, 'customer_admin_chrome_css']);
        add_action('in_admin_header', [__CLASS__, 'render_customer_cms_nav']);
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
        add_submenu_page('scg-dashboard', 'ブログを書く', 'ブログを書く', 'edit_posts', 'scg-blog-add', ['SCG_Content_Manage', 'render_blog_edit_page']);
        add_submenu_page('scg-dashboard', 'ブログ一覧', 'ブログ一覧', 'edit_posts', 'scg-blog-list', ['SCG_Content_Manage', 'render_blog_list_page']);
        add_submenu_page('scg-dashboard', 'お知らせを書く', 'お知らせを書く', 'edit_posts', 'scg-news-add', ['SCG_Content_Manage', 'render_news_edit_page']);
        add_submenu_page('scg-dashboard', 'お知らせ一覧', 'お知らせ一覧', 'edit_posts', 'scg-news-list', ['SCG_Content_Manage', 'render_news_list_page']);
        add_submenu_page('scg-dashboard', 'トップスライダー管理', 'トップスライダー管理', 'upload_files', 'scg-top-slider', ['SCG_Top_Slider', 'render_admin_page']);

        add_submenu_page(
            'scg-dashboard',
            'ギャラリーカテゴリ',
            'ギャラリーカテゴリ',
            'manage_options',
            'edit-tags.php?taxonomy=scg_gallery_category&post_type=scg_photo'
        );

        add_submenu_page(
            'scg-dashboard',
            'ギャラリー表示設定',
            'ギャラリー表示設定',
            'manage_options',
            'scg-gallery-display-settings',
            [__CLASS__, 'render_gallery_display_settings']
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
        remove_menu_page('edit.php?post_type=scg_blog');
        remove_menu_page('edit.php?post_type=scg_news');
        remove_menu_page('edit-comments.php');
        remove_menu_page('themes.php');
        remove_menu_page('plugins.php');
        remove_menu_page('users.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
    }



    private static function is_customer_manager() {
        if (!is_user_logged_in() || current_user_can('manage_options')) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array('customer_manager', (array) $user->roles, true);
    }

    public static function admin_body_class($classes) {
        if (self::is_customer_manager()) {
            $classes .= ' scg-customer-shell';
        }
        return $classes;
    }

    public static function customer_admin_chrome_css() {
        if (!self::is_customer_manager()) {
            return;
        }
        ?>
        <style>
            body.scg-customer-shell #wpadminbar,
            body.scg-customer-shell #adminmenuback,
            body.scg-customer-shell #adminmenuwrap,
            body.scg-customer-shell #wpfooter,
            body.scg-customer-shell .update-nag,
            body.scg-customer-shell .notice:not(.scg-keep-notice) {
                display: none !important;
            }
            body.scg-customer-shell.wp-admin {
                padding-top: 0 !important;
                background: #f5f5f7;
            }
            body.scg-customer-shell #wpcontent,
            body.scg-customer-shell #wpfooter {
                margin-left: 0 !important;
            }
            body.scg-customer-shell #wpcontent {
                padding-left: 32px !important;
                padding-right: 32px !important;
                box-sizing: border-box;
            }
            body.scg-customer-shell #wpbody-content {
                padding-bottom: 48px;
            }
            body.scg-customer-shell .wrap.scg-wrap {
                max-width: 1180px;
                margin: 18px auto 0;
            }
            @media (max-width: 782px) {
                body.scg-customer-shell #wpcontent {
                    padding-left: 16px !important;
                    padding-right: 16px !important;
                }
            }
        </style>
        <?php
    }

    public static function render_customer_cms_nav() {
        if (!self::is_customer_manager()) {
            return;
        }

        $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $items = [
            'scg-dashboard' => ['label' => '専用CMSトップ', 'url' => admin_url('admin.php?page=scg-dashboard')],
            'scg-photo-manage' => ['label' => 'ギャラリー管理', 'url' => admin_url('admin.php?page=scg-photo-manage')],
            'scg-blog-add' => ['label' => 'ブログを書く', 'url' => admin_url('admin.php?page=scg-blog-add')],
            'scg-blog-list' => ['label' => 'ブログ一覧', 'url' => admin_url('admin.php?page=scg-blog-list')],
            'scg-news-add' => ['label' => 'お知らせを書く', 'url' => admin_url('admin.php?page=scg-news-add')],
            'scg-news-list' => ['label' => 'お知らせ一覧', 'url' => admin_url('admin.php?page=scg-news-list')],
            'scg-top-slider' => ['label' => 'スライダー管理', 'url' => admin_url('admin.php?page=scg-top-slider')],
        ];
        $logout_url = wp_logout_url(home_url('/login/'));
        ?>
        <div class="scg-customer-nav" role="navigation" aria-label="専用CMSメニュー">
            <div class="scg-customer-nav-inner">
                <div class="scg-customer-nav-brand">専用CMS</div>
                <div class="scg-customer-nav-links">
                    <?php foreach ($items as $page => $item): ?>
                        <a class="scg-customer-nav-link <?php echo $current_page === $page ? 'is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                    <?php endforeach; ?>
                </div>
                <a class="scg-customer-nav-logout" href="<?php echo esc_url($logout_url); ?>">ログアウト</a>
            </div>
        </div>
        <?php
    }

    public static function ensure_default_options() {
        add_option('scg_gallery_columns_desktop', 5);
        add_option('scg_gallery_columns_tablet', 4);
        add_option('scg_gallery_columns_mobile', 3);
    }

    public static function get_gallery_column_settings() {
        return [
            'desktop' => max(1, min(8, (int) get_option('scg_gallery_columns_desktop', 5))),
            'tablet' => max(1, min(6, (int) get_option('scg_gallery_columns_tablet', 4))),
            'mobile' => max(1, min(4, (int) get_option('scg_gallery_columns_mobile', 3))),
        ];
    }

    public static function render_gallery_display_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('この設定を変更する権限がありません。', 'simple-customer-gallery-cms'));
        }

        if (isset($_POST['scg_gallery_display_settings_submit'])) {
            check_admin_referer('scg_save_gallery_display_settings');

            $desktop = isset($_POST['scg_gallery_columns_desktop']) ? (int) $_POST['scg_gallery_columns_desktop'] : 5;
            $tablet = isset($_POST['scg_gallery_columns_tablet']) ? (int) $_POST['scg_gallery_columns_tablet'] : 4;
            $mobile = isset($_POST['scg_gallery_columns_mobile']) ? (int) $_POST['scg_gallery_columns_mobile'] : 3;

            update_option('scg_gallery_columns_desktop', max(1, min(8, $desktop)));
            update_option('scg_gallery_columns_tablet', max(1, min(6, $tablet)));
            update_option('scg_gallery_columns_mobile', max(1, min(4, $mobile)));

            echo '<div class="notice notice-success is-dismissible"><p>ギャラリー表示設定を保存しました。</p></div>';
        }

        $settings = self::get_gallery_column_settings();
        ?>
        <div class="wrap scg-wrap scg-settings-wrap">
            <h1>ギャラリー表示設定</h1>
            <p class="scg-lead">フロントギャラリーの画像グリッド列数を、表示端末ごとに調整できます。</p>

            <form method="post" class="scg-settings-form">
                <?php wp_nonce_field('scg_save_gallery_display_settings'); ?>

                <section class="scg-panel scg-settings-panel">
                    <h2>表示列数</h2>
                    <p>設定値はショートコード <code>[scg_gallery]</code> の表示に反映されます。</p>

                    <?php self::render_column_slider('PC表示', 'scg_gallery_columns_desktop', $settings['desktop'], 1, 8, '1024px以上の画面で使う列数です。'); ?>
                    <?php self::render_column_slider('タブレット表示', 'scg_gallery_columns_tablet', $settings['tablet'], 1, 6, '768px〜1023pxの画面で使う列数です。'); ?>
                    <?php self::render_column_slider('スマホ表示', 'scg_gallery_columns_mobile', $settings['mobile'], 1, 4, '767px以下の画面で使う列数です。'); ?>

                    <div class="scg-settings-footer">
                        <button type="submit" name="scg_gallery_display_settings_submit" value="1" class="button button-primary button-large">設定を保存</button>
                    </div>
                </section>
            </form>
        </div>

        <script>
        (function() {
            const sliders = document.querySelectorAll('.scg-range-input');
            sliders.forEach(function(slider) {
                const output = document.querySelector('[data-scg-range-output="' + slider.name + '"]');
                if (!output) return;
                const sync = function() { output.textContent = slider.value + '列'; };
                slider.addEventListener('input', sync);
                sync();
            });
        })();
        </script>
        <?php
    }

    private static function render_column_slider($label, $name, $value, $min, $max, $description) {
        ?>
        <div class="scg-setting-row">
            <div class="scg-setting-label">
                <strong><?php echo esc_html($label); ?></strong>
                <span><?php echo esc_html($description); ?></span>
            </div>
            <div class="scg-setting-control">
                <input class="scg-range-input" type="range" name="<?php echo esc_attr($name); ?>" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" value="<?php echo esc_attr($value); ?>">
                <output class="scg-range-output" data-scg-range-output="<?php echo esc_attr($name); ?>"><?php echo esc_html($value); ?>列</output>
            </div>
        </div>
        <?php
    }

    public static function enqueue_admin_assets($hook) {
        wp_enqueue_style('scg-admin', SCG_CMS_URL . 'assets/css/admin.css', [], SCG_CMS_VERSION);

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $is_gallery_manage = ($page === 'scg-photo-manage') || (strpos((string) $hook, 'scg-photo-manage') !== false);
        $is_top_slider = ($page === 'scg-top-slider') || (strpos((string) $hook, 'scg-top-slider') !== false);

        if ($is_gallery_manage) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('scg-manage', SCG_CMS_URL . 'assets/js/admin-manage.js', ['jquery', 'jquery-ui-sortable'], SCG_CMS_VERSION, true);
            wp_localize_script('scg-manage', 'SCG_MANAGE', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scg_manage_photos'),
                'upload_nonce' => wp_create_nonce('scg_upload_photos'),
                'max_files' => 10,
                'max_file_size' => 0,
                'max_file_size_label' => 'サーバー上限',
            ]);
        }

        if ($is_top_slider) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('scg-admin-slider', SCG_CMS_URL . 'assets/js/admin-slider.js', ['jquery', 'jquery-ui-sortable'], SCG_CMS_VERSION, true);
            wp_localize_script('scg-admin-slider', 'SCG_ADMIN_SLIDER', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scg_top_slider_ajax'),
                'max_items' => SCG_Top_Slider::MAX_ITEMS,
                'max_file_size' => 0,
                'messages' => [
                    'delete_confirm' => 'この画像を削除します。よろしいですか？',
                    'uploading' => 'アップロード中...',
                    'processing' => '画像を処理中...',
                    'complete' => 'アップロード完了',
                    'error' => '処理に失敗しました。',
                    'too_large' => 'サーバーのアップロード上限を超えています。PHP設定を確認してください。',
                    'max_reached' => '登録できる画像は最大12枚までです。',
                ],
            ]);
        }
    }

}