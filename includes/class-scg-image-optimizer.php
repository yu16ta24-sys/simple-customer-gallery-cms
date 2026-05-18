<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared image optimization for Simple Customer Gallery CMS.
 *
 * Upload endpoints should accept large source files and call this class after
 * WordPress creates the attachment. The stored original is normalized to a
 * practical portfolio size, and a WebP sidecar is generated when supported.
 */
class SCG_Image_Optimizer {
    const MAX_DIMENSION = 2560;
    const JPEG_QUALITY = 85;
    const WEBP_QUALITY = 80;
    const META_WEBP_PATH = '_scg_webp_path';
    const META_WEBP_URL = '_scg_webp_url';

    public static function init() {
        add_action('delete_attachment', [__CLASS__, 'delete_webp_sidecar']);
    }

    public static function optimize_attachment($attachment_id) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id) {
            return false;
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }

        if (!wp_attachment_is_image($attachment_id)) {
            return false;
        }

        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $optimized = self::resize_original_if_needed($file_path);
        self::generate_webp($attachment_id, $file_path);

        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return $optimized;
    }

    private static function resize_original_if_needed($file_path) {
        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return false;
        }

        if (method_exists($editor, 'maybe_exif_rotate')) {
            $editor->maybe_exif_rotate();
        }

        $size = $editor->get_size();
        $width = isset($size['width']) ? (int) $size['width'] : 0;
        $height = isset($size['height']) ? (int) $size['height'] : 0;

        if (!$width || !$height) {
            return false;
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            $editor->resize(self::MAX_DIMENSION, self::MAX_DIMENSION, false);
        }

        $editor->set_quality(self::JPEG_QUALITY);
        $saved = $editor->save($file_path);

        return !is_wp_error($saved);
    }

    private static function generate_webp($attachment_id, $file_path) {
        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return false;
        }

        $webp_path = preg_replace('/\.[^.]+$/', '.webp', $file_path);
        if (!$webp_path || $webp_path === $file_path) {
            return false;
        }

        $editor->set_quality(self::WEBP_QUALITY);
        $saved = $editor->save($webp_path, 'image/webp');
        if (is_wp_error($saved) || empty($saved['path']) || !file_exists($saved['path'])) {
            delete_post_meta($attachment_id, self::META_WEBP_PATH);
            delete_post_meta($attachment_id, self::META_WEBP_URL);
            return false;
        }

        update_post_meta($attachment_id, self::META_WEBP_PATH, $saved['path']);
        update_post_meta($attachment_id, self::META_WEBP_URL, self::path_to_url($saved['path']));

        return true;
    }

    public static function get_webp_url($attachment_id) {
        $url = get_post_meta(absint($attachment_id), self::META_WEBP_URL, true);
        if ($url) {
            return esc_url_raw($url);
        }

        $path = get_post_meta(absint($attachment_id), self::META_WEBP_PATH, true);
        if ($path && file_exists($path)) {
            return esc_url_raw(self::path_to_url($path));
        }

        return '';
    }

    public static function get_best_url($attachment_id, $size = 'full') {
        $webp = self::get_webp_url($attachment_id);
        if ($webp) {
            return $webp;
        }

        return wp_get_attachment_image_url($attachment_id, $size);
    }

    private static function path_to_url($path) {
        $uploads = wp_get_upload_dir();
        if (empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            return '';
        }

        $basedir = wp_normalize_path($uploads['basedir']);
        $path = wp_normalize_path($path);

        if (strpos($path, $basedir) !== 0) {
            return '';
        }

        $relative = ltrim(substr($path, strlen($basedir)), '/');
        return trailingslashit($uploads['baseurl']) . str_replace('%2F', '/', rawurlencode($relative));
    }

    public static function delete_webp_sidecar($attachment_id) {
        $path = get_post_meta(absint($attachment_id), self::META_WEBP_PATH, true);
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }
}
