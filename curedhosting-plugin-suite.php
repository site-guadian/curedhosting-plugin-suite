<?php
/**
 * Plugin Name: CuredHosting Plugin Suite
 * Description: A freemium WordPress plugin suite with Free, Pro, and Corporate tiers for hosting-focused utilities.
 * Version: 1.0.2
 * Author: CuredHosting
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CHPS_VERSION', '1.0.2');
define('CHPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHPS_PLUGIN_URL', plugin_dir_url(__FILE__));

define('CHPS_MODULES', 'chps_modules');

function chps_register_module($module) {
    do_action('chps_register_module', $module);
}

function chps_get_registered_modules() {
    return get_option(CHPS_MODULES, array());
}

function chps_is_module_active($slug) {
    return get_option('chps_module_status_' . sanitize_key($slug), 'active') === 'active';
}

add_action('chps_register_module', function ($module) {
    if (!is_array($module)) {
        return;
    }

    // Normalize slug and ensure we don't register duplicates across versions.
    $slug = isset($module['slug']) ? sanitize_key($module['slug']) : '';
    $existing = get_option(CHPS_MODULES, array());

    // Build map by slug so new registration replaces older entries with same slug.
    $map = array();
    foreach ($existing as $m) {
        if (!is_array($m)) {
            continue;
        }
        $s = isset($m['slug']) ? sanitize_key($m['slug']) : '';
        if ($s === '') {
            // keep entries without slug (unexpected) by giving them a unique key
            $map[] = $m;
            continue;
        }
        $map[$s] = $m;
    }

    if ($slug !== '') {
        $map[$slug] = $module; // replace or set
    } else {
        // fallback: append if no slug provided
        $map[] = $module;
    }

    // Save as numeric array
    update_option(CHPS_MODULES, array_values($map));
});

require_once CHPS_PLUGIN_DIR . 'includes/class-chps-settings.php';
require_once CHPS_PLUGIN_DIR . 'includes/class-chps-license.php';
require_once CHPS_PLUGIN_DIR . 'includes/class-chps-admin.php';
require_once CHPS_PLUGIN_DIR . 'includes/class-chps-logger.php';
require_once CHPS_PLUGIN_DIR . 'includes/class-chps-stripe.php';
require_once CHPS_PLUGIN_DIR . 'includes/class-chps-setup-wizard.php';

// Instantiate settings early so parent menu is registered before modules try to use it
CHPS_Settings::instance();

foreach (glob(CHPS_PLUGIN_DIR . 'modules/*/*.php') as $module_file) {
    require_once $module_file;
}

add_action('plugins_loaded', function () {
    CHPS_License::instance();
    CHPS_Admin::instance();
    CHPS_Stripe::instance();
    CHPS_Setup_Wizard::instance();
});
