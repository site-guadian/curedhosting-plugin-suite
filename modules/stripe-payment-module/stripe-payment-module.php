<?php
/**
 * Plugin Name: Stripe Payment Module
 * Description: Lightweight Stripe Checkout module for selling Pro and Corporate access. Add your Stripe keys later and it will be ready.
 * Version: 1.0.1
 * Author: CuredHosting
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Stripe_Payment_Module')) {
    define('SPM_PLUGIN_FILE', __FILE__);
    define('SPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('SPM_PLUGIN_URL', plugin_dir_url(__FILE__));

    require_once SPM_PLUGIN_DIR . 'includes/class-stripe-payment-module.php';

    add_action('plugins_loaded', function () {
        if (function_exists('chps_register_module')) {
            chps_register_module([
                'name' => 'Stripe Payment',
                'slug' => 'stripe-payment',
                'version' => '1.0.1',
                'admin_slug' => 'chps-spm-settings',
                'status' => 'active'
            ]);
        }
        Stripe_Payment_Module::instance();
    });
}
