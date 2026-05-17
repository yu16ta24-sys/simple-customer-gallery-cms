<?php
/**
 * Plugin Name: Simple Customer Gallery CMS
 * Description: 専用CMS土台、ギャラリー管理、ブログ管理、お知らせ管理。
 * Version: 1.6.9
 * Author: TRUSTEPS
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCG_CMS_VERSION', '1.6.9');
define('SCG_CMS_FILE', __FILE__);
define('SCG_CMS_DIR', plugin_dir_path(__FILE__));
define('SCG_CMS_URL', plugin_dir_url(__FILE__));

require_once SCG_CMS_DIR . 'includes/class-scg-roles.php';
require_once SCG_CMS_DIR . 'includes/class-scg-auth.php';
require_once SCG_CMS_DIR . 'includes/class-scg-gallery-post-type.php';
require_once SCG_CMS_DIR . 'includes/class-scg-gallery-taxonomy.php';
require_once SCG_CMS_DIR . 'includes/class-scg-content-post-type.php';
require_once SCG_CMS_DIR . 'includes/class-scg-content-manage.php';
require_once SCG_CMS_DIR . 'includes/class-scg-front-gallery.php';
require_once SCG_CMS_DIR . 'includes/class-scg-front-content.php';
require_once SCG_CMS_DIR . 'includes/class-scg-top-slider.php';
require_once SCG_CMS_DIR . 'includes/class-scg-gallery-upload.php';
require_once SCG_CMS_DIR . 'includes/class-scg-gallery-manage.php';
require_once SCG_CMS_DIR . 'includes/class-scg-dashboard.php';
require_once SCG_CMS_DIR . 'includes/class-scg-admin.php';

register_activation_hook(__FILE__, function () {
    SCG_Roles::activate();
    SCG_Gallery_Post_Type::register_post_type();
    SCG_Gallery_Taxonomy::register_taxonomy();
    SCG_Content_Post_Type::register_post_types();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

add_action('plugins_loaded', function () {
    SCG_Auth::init();
    SCG_Gallery_Post_Type::init();
    SCG_Gallery_Taxonomy::init();
    SCG_Content_Post_Type::init();
    SCG_Gallery_Upload::init();
    SCG_Content_Manage::init();
    SCG_Front_Gallery::init();
    SCG_Front_Content::init();
    SCG_Top_Slider::init();
    SCG_Gallery_Manage::init();
    SCG_Dashboard::init();
    SCG_Admin::init();
});
