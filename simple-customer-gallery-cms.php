<?php
/**
 * Plugin Name: Simple Customer Gallery CMS - v1.0.2
 * Description: 専用CMS土台、ログイン制御、ギャラリー管理、画像アップロード、安全化整理版。
 * Version: 1.0.2
 * Author: TRUSTEPS
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCG_CMS_VERSION', '1.0.2');
define('SCG_CMS_FILE', __FILE__);
define('SCG_CMS_DIR', plugin_dir_path(__FILE__));
define('SCG_CMS_URL', plugin_dir_url(__FILE__));

require_once SCG_CMS_DIR . 'includes/class-scg-roles.php';
require_once SCG_CMS_DIR . 'includes/class-scg-auth.php';
require_once SCG_CMS_DIR . 'includes/class-scg-gallery-post-type.php';
require_once SCG_CMS_DIR . 'includes/class-scg-gallery-taxonomy.php';
require_once SCG_CMS_DIR . 'includes/class-scg-gallery-upload.php';
require_once SCG_CMS_DIR . 'includes/class-scg-gallery-manage.php';
require_once SCG_CMS_DIR . 'includes/class-scg-dashboard.php';
require_once SCG_CMS_DIR . 'includes/class-scg-admin.php';

register_activation_hook(__FILE__, function () {
    SCG_Roles::activate();
    SCG_Gallery_Post_Type::register_post_type();
    SCG_Gallery_Taxonomy::register_taxonomy();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

add_action('plugins_loaded', function () {
    SCG_Auth::init();
    SCG_Gallery_Post_Type::init();
    SCG_Gallery_Taxonomy::init();
    SCG_Gallery_Upload::init();
    SCG_Gallery_Manage::init();
    SCG_Dashboard::init();
    SCG_Admin::init();
});
