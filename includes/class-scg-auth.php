<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Auth {
    const CUSTOMER_LOGIN_SLUG = 'login';
    const ADMIN_LOGIN_SLUG = 'admin-login';
    const CUSTOMER_HOME_SLUG = 'scg-dashboard';

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'render_custom_login_page']);
        add_action('login_init', [__CLASS__, 'block_direct_wp_login']);
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 10, 3);
        add_action('admin_init', [__CLASS__, 'restrict_admin_pages']);
        add_action('after_setup_theme', [__CLASS__, 'hide_admin_bar']);
    }

    private static function request_path() {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        return trim($path, '/');
    }

    public static function render_custom_login_page() {
        $path = self::request_path();

        if ($path !== self::CUSTOMER_LOGIN_SLUG && $path !== self::ADMIN_LOGIN_SLUG) {
            return;
        }

        if (is_user_logged_in()) {
            wp_safe_redirect(current_user_can('manage_options') ? admin_url() : admin_url('admin.php?page=' . self::CUSTOMER_HOME_SLUG));
            exit;
        }

        $login_type = ($path === self::ADMIN_LOGIN_SLUG) ? 'admin' : 'customer';

        setcookie('scg_login_access', '1', time() + 300, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE['scg_login_access'] = '1';

        $redirect_to = ($login_type === 'admin') ? admin_url() : admin_url('admin.php?page=' . self::CUSTOMER_HOME_SLUG);
        $action_url = add_query_arg('scg_login_access', '1', wp_login_url($redirect_to));

        status_header(200);
        nocache_headers();

        self::render_login_html($login_type, $action_url, $redirect_to);
        exit;
    }

    private static function render_login_html($login_type, $action_url, $redirect_to) {
        $site_name = get_bloginfo('name');
        $logo_text = ($login_type === 'admin') ? $site_name . ' 管理者ログイン' : $site_name;
        $lost_password_url = wp_lostpassword_url(home_url('/' . self::CUSTOMER_LOGIN_SLUG . '/'));
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($logo_text); ?></title>
            <?php wp_head(); ?>
            <style>
                body.scg-login-page {
                    margin: 0;
                    min-height: 100vh;
                    background: #f7f7f7;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    color: #222;
                }
                .scg-login-box { width: 360px; max-width: calc(100% - 40px); }
                .scg-login-logo { text-align: center; font-size: 24px; font-weight: 700; margin-bottom: 28px; }
                .scg-login-card { background: #fff; border-radius: 18px; box-shadow: 0 10px 32px rgba(0,0,0,.08); padding: 28px; }
                .scg-login-field { margin-bottom: 18px; }
                .scg-login-field label { display: block; font-size: 13px; margin-bottom: 7px; }
                .scg-login-field input[type="text"], .scg-login-field input[type="password"] {
                    width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #c8c8c8; border-radius: 8px; font-size: 16px;
                }
                .scg-login-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
                .scg-login-submit { background: #222; color: #fff; border: none; border-radius: 999px; padding: 10px 24px; cursor: pointer; font-weight: 700; }
                .scg-login-submit:hover { background: #444; }
                .scg-login-remember { font-size: 13px; }
                .scg-login-lost { text-align: center; margin-top: 18px; font-size: 13px; }
                .scg-login-lost a { color: #333; }
            </style>
        </head>
        <body class="scg-login-page">
            <main class="scg-login-box">
                <div class="scg-login-logo"><?php echo esc_html($logo_text); ?></div>
                <form class="scg-login-card" name="loginform" method="post" action="<?php echo esc_url($action_url); ?>">
                    <div class="scg-login-field">
                        <label for="user_login">メールアドレス</label>
                        <input type="text" name="log" id="user_login" autocomplete="username" required>
                    </div>
                    <div class="scg-login-field">
                        <label for="user_pass">パスワード</label>
                        <input type="password" name="pwd" id="user_pass" autocomplete="current-password" required>
                    </div>
                    <div class="scg-login-row">
                        <label class="scg-login-remember"><input name="rememberme" type="checkbox" value="forever"> ログイン状態を保存</label>
                        <button class="scg-login-submit" type="submit">ログイン</button>
                    </div>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                    <input type="hidden" name="testcookie" value="1">
                </form>
                <div class="scg-login-lost"><a href="<?php echo esc_url($lost_password_url); ?>">パスワードを忘れた場合はこちら</a></div>
            </main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    public static function block_direct_wp_login() {
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
        $allowed_actions = ['lostpassword', 'retrievepassword', 'rp', 'resetpass', 'logout'];

        if (in_array($action, $allowed_actions, true)) {
            return;
        }

        if (!empty($_COOKIE['scg_login_access']) || isset($_GET['scg_login_access'])) {
            return;
        }

        wp_safe_redirect(home_url('/' . self::CUSTOMER_LOGIN_SLUG . '/'));
        exit;
    }

    public static function login_redirect($redirect_to, $request, $user) {
        if (!isset($user->roles) || !is_array($user->roles)) {
            return $redirect_to;
        }

        if (in_array('customer_manager', $user->roles, true)) {
            return admin_url('admin.php?page=' . self::CUSTOMER_HOME_SLUG);
        }

        if (in_array('administrator', $user->roles, true)) {
            return admin_url();
        }

        return $redirect_to;
    }

    public static function restrict_admin_pages() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (current_user_can('manage_options') || !is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        if (!in_array('customer_manager', (array) $user->roles, true)) {
            return;
        }

        global $pagenow;

        $allowed_pages = [
            self::CUSTOMER_HOME_SLUG,
            'scg-photo-manage',
            'scg-blog-add',
            'scg-blog-list',
            'scg-news-add',
            'scg-news-list',
        ];

        if ($pagenow === 'profile.php') {
            return;
        }

        if ($pagenow === 'admin.php') {
            $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
            if (in_array($page, $allowed_pages, true)) {
                return;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::CUSTOMER_HOME_SLUG));
        exit;
    }

    public static function hide_admin_bar() {
        if (!current_user_can('manage_options')) {
            show_admin_bar(false);
        }
    }
}
