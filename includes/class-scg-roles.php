<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Roles {
    public static function activate() {
        add_role('customer_manager', 'お客様管理者', [
            'read' => true,
            'upload_files' => true,
            'edit_posts' => true,
            'publish_posts' => true,
            'delete_posts' => true,
        ]);
    }
}
