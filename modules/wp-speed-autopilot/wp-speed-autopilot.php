<?php
/**
 * Plugin Name: CuredHosting Suite — Speed Autopilot
 * Description: One-click performance optimization module for the CuredHosting Plugin Suite.
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
            'name' => 'Speed Autopilot',
            'slug' => 'speed-autopilot',
            'version' => '1.0.0'
        ]);
    }

    // Initialize the module logic
    if (class_exists('WP_Speed_Autopilot')) {
        $wpsa = new WP_Speed_Autopilot();
    }
});

if (!class_exists('WP_Speed_Autopilot')) {
    class WP_Speed_Autopilot {
        private $prefix = 'wpsa_';

        public function __construct() {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('admin_notices', [$this, 'show_free_tier_promo']);
            add_action('admin_post_wpsa_optimize', [$this, 'handle_optimization']);
            add_action('admin_post_wpsa_cleanup_db', [$this, 'handle_db_cleanup']);
            add_action('admin_post_wpsa_reset', [$this, 'handle_reset']);
            add_action('wpsa_scheduled_cleanup', [$this, 'run_scheduled_cleanup']);

            $this->apply_optimizations();
        }

        // ... [Keep ALL of your original class methods exactly as they are] ...
        // ... add_admin_menu(), register_settings(), get_optimization_keys(), etc ...
        
        // Ensure you remove the 'new WP_Speed_Autopilot();' call at the very bottom
    }
}