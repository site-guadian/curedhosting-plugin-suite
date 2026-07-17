<?php
if (!defined('ABSPATH')) {
    exit;
}

class CHPS_Admin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_notices', array($this, 'show_tier_notice'));
        add_action('admin_notices', array($this, 'show_module_duplicate_notice'));
        add_action('admin_post_chps_cleanup_modules', array($this, 'handle_cleanup_modules'));
        add_action('admin_menu', array($this, 'register_feature_pages'));
    }

    public function show_tier_notice() {
        // Don't show tier notice on any admin page - it's too noisy
        return;
    }

    public function register_feature_pages() {
        if (apply_filters('chps_can_access', false, 'bulk_tools')) {
            add_submenu_page('chps-settings', 'Bulk Tools', 'Bulk Tools', 'manage_options', 'chps-bulk-tools', array($this, 'render_bulk_tools'));
        }

        if (apply_filters('chps_can_access', false, 'advanced_reports')) {
            add_submenu_page('chps-settings', 'Advanced Reports', 'Advanced Reports', 'manage_options', 'chps-reports', array($this, 'render_reports'));
        }
    }

    public function show_module_duplicate_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $modules = chps_get_registered_modules();
        if (empty($modules) || !is_array($modules)) {
            return;
        }

        $counts = array();
        foreach ($modules as $m) {
            if (!is_array($m)) continue;
            $s = isset($m['slug']) ? sanitize_key($m['slug']) : '';
            if ($s === '') continue;
            if (!isset($counts[$s])) $counts[$s] = 0;
            $counts[$s]++;
        }

        $duplicates = array_filter($counts, function($c) { return $c > 1; });
        if (empty($duplicates)) {
            return;
        }

        $count = array_sum($duplicates);
        $url = wp_nonce_url(admin_url('admin-post.php?action=chps_cleanup_modules'), 'chps_cleanup_modules');
        echo '<div class="notice notice-warning"><p><strong>CuredHosting:</strong> Detected ' . esc_html($count) . ' duplicated module registration(s). <a href="' . esc_url($url) . '">Click to cleanup module registrations</a> (requires manage_options).</p></div>';
    }

    public function handle_cleanup_modules() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('chps_cleanup_modules');

        $modules = chps_get_registered_modules();
        if (!is_array($modules)) {
            wp_safe_redirect(admin_url('admin.php?page=chps-modules'));
            exit;
        }

        $map = array();
        foreach ($modules as $m) {
            if (!is_array($m)) continue;
            $s = isset($m['slug']) ? sanitize_key($m['slug']) : '';
            if ($s === '') {
                $map[] = $m; // preserve entries without a slug
                continue;
            }
            // prefer the most recently-registered entry (last wins)
            $map[$s] = $m;
        }

        update_option(CHPS_MODULES, array_values($map));
        wp_safe_redirect(admin_url('admin.php?page=chps-modules&chps_notice=modules-cleaned'));
        exit;
    }

    public function render_bulk_tools() {
        echo '<div class="wrap"><h1>Bulk Tools</h1><p>This is where your first premium utility can live.</p></div>';
    }

    public function render_reports() {
        echo '<div class="wrap"><h1>Advanced Reports</h1><p>This is where your reporting features can live.</p></div>';
    }
}
