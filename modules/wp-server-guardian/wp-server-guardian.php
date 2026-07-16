<?php
/**
 * Plugin Name: CuredHosting Suite — Server Guardian
 * Description: Security hardening and monitoring module for the CuredHosting Plugin Suite.
 * Version: 1.0.0
 * Author: CuredHosting
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Register with the Suite and initialize
add_action('plugins_loaded', function() {
    // Register this module with the main Suite
    if (function_exists('chps_register_module')) {
        chps_register_module([
            'name' => 'Server Guardian',
            'slug' => 'server-guardian',
            'version' => '1.0.0'
        ]);
    }

    // Initialize the module logic
    if (class_exists('WP_Server_Guardian')) {
        $wpsg = new WP_Server_Guardian();
        $wpsg->apply_active_rules();
    }
});

if (!class_exists('WP_Server_Guardian')) {
    class WP_Server_Guardian {
        private $option_prefix = 'wpsg_';

        public function __construct() {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('admin_notices', [$this, 'show_free_tier_promo']);
            add_action('wp_login_failed', [$this, 'log_failed_login']);
            add_action('wp_login', [$this, 'log_successful_login'], 10, 2);
            add_action('wpsg_daily_health_report', [$this, 'send_daily_report']);
            add_action('admin_post_wpsg_harden', [$this, 'apply_hardening']);
            add_action('admin_post_wpsg_reset_logs', [$this, 'reset_logs']);
            add_action('admin_post_wpsg_backup_prompt', [$this, 'handle_backup_prompt']);
            add_action('wpsg_uptime_check', [$this, 'perform_uptime_check']);

            if (!wp_next_scheduled('wpsg_daily_health_report')) {
                wp_schedule_event(time(), 'daily', 'wpsg_daily_health_report');
            }
            if (!wp_next_scheduled('wpsg_uptime_check')) {
                wp_schedule_event(time(), 'hourly', 'wpsg_uptime_check');
            }
        }
        
        // ... [Keep all your existing functions: add_admin_menu(), register_settings(), etc.] ...
        // Note: You do NOT need to change your existing function logic.
    }
}