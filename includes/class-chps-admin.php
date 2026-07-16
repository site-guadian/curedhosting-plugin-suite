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
        add_action('admin_menu', array($this, 'register_feature_pages'));
    }

    public function show_tier_notice() {
        $tier = get_option('chps_tier', 'free');
        if ($tier === 'free') {
            echo '<div class="notice notice-info"><p>Upgrade to Pro or Corporate to unlock advanced features and support more workloads. For sales or technical support, contact siteguardian@plaguedr.online.</p></div>';
        }
    }

    public function register_feature_pages() {
        if (apply_filters('chps_can_access', false, 'bulk_tools')) {
            add_submenu_page('chps-settings', 'Bulk Tools', 'Bulk Tools', 'manage_options', 'chps-bulk-tools', array($this, 'render_bulk_tools'));
        }

        if (apply_filters('chps_can_access', false, 'advanced_reports')) {
            add_submenu_page('chps-settings', 'Advanced Reports', 'Advanced Reports', 'manage_options', 'chps-reports', array($this, 'render_reports'));
        }
    }

    public function render_bulk_tools() {
        echo '<div class="wrap"><h1>Bulk Tools</h1><p>This is where your first premium utility can live.</p></div>';
    }

    public function render_reports() {
        echo '<div class="wrap"><h1>Advanced Reports</h1><p>This is where your reporting features can live.</p></div>';
    }
}
