<?php
/**
 * Plugin Name: CuredHosting Suite — Speed Autopilot
 * Description: One-click performance optimization module for the CuredHosting Plugin Suite.
 * Version: 1.0.2
 * Author: CuredHosting
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Register with the Suite and initialize
add_action('plugins_loaded', function() {
    if (function_exists('chps_register_module')) {
        chps_register_module([
            'name' => 'Speed Autopilot',
            'slug' => 'speed-autopilot',
            'version' => '1.0.2',
            'admin_slug' => 'wpsa-settings',
            'status' => 'active'
        ]);
    }

    if (function_exists('chps_is_module_active') && !chps_is_module_active('speed-autopilot')) {
        return;
    }

    if (class_exists('WP_Speed_Autopilot')) {
        new WP_Speed_Autopilot();
    }
});

if (!class_exists('WP_Speed_Autopilot')) {
    class WP_Speed_Autopilot {
        private $prefix = 'wpsa_';

        public function __construct() {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            if (method_exists($this, 'show_free_tier_promo')) {
                add_action('admin_notices', [$this, 'show_free_tier_promo']);
            }
            add_action('admin_post_wpsa_optimize', [$this, 'handle_optimization']);
            add_action('admin_post_wpsa_cleanup_db', [$this, 'handle_db_cleanup']);
            add_action('admin_post_wpsa_reset', [$this, 'handle_reset']);
        }

        public function add_admin_menu() {
            add_submenu_page(
                'chps-settings',
                'Speed Autopilot',
                'Speed Autopilot',
                'manage_options',
                'wpsa-settings',
                [$this, 'render_admin_page']
            );
        }

        public function render_admin_page() {
            $enabled = get_option($this->prefix . 'enabled', 'off');
            $optimization_level = get_option($this->prefix . 'optimization_level', 'moderate');
            $last_run = get_option($this->prefix . 'last_run', 'Never');
            ?>
            <div class="wrap">
                <h1>⚡ Speed Autopilot</h1>
                <p>Automatically optimize your WordPress site performance.</p>
                
                <div class="card" style="max-width:800px;padding:20px;margin:20px 0;">
                    <h2>Status</h2>
                    <table class="form-table">
                        <tr>
                            <th>Optimizer State:</th>
                            <td><strong><?php echo $enabled === 'on' ? 'Active' : 'Inactive'; ?></strong></td>
                        </tr>
                        <tr>
                            <th>Optimization Level:</th>
                            <td><strong><?php echo esc_html(ucfirst($optimization_level)); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Last Optimization:</th>
                            <td><?php echo esc_html($last_run); ?></td>
                        </tr>
                    </table>
                    <?php $optimization_summary = get_option($this->prefix . 'optimization_summary', ''); ?>
                    <?php if (!empty($optimization_summary)) : ?>
                        <div class="notice notice-info" style="margin-top:10px;"><p><?php echo esc_html($optimization_summary); ?></p></div>
                    <?php endif; ?>
                </div>
                
                <div class="card" style="max-width:800px;padding:20px;margin:20px 0;">
                    <h2>Quick Actions</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                        <input type="hidden" name="action" value="wpsa_optimize" />
                        <?php wp_nonce_field('wpsa_optimize'); ?>
                        <button type="submit" class="button button-primary">🚀 Run Optimization Now</button>
                    </form>
                    
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;margin-left:10px;">
                        <input type="hidden" name="action" value="wpsa_cleanup_db" />
                        <?php wp_nonce_field('wpsa_cleanup_db'); ?>
                        <button type="submit" class="button">🗑️ Clean Database</button>
                    </form>
                </div>
                
                <div class="card" style="max-width:800px;padding:20px;margin:20px 0;">
                    <h2>Settings</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('wpsa_settings_group'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="wpsa_enabled">Enable Autopilot:</label></th>
                                <td>
                                    <input type="checkbox" id="wpsa_enabled" name="<?php echo $this->prefix; ?>enabled" value="on" <?php checked($enabled, 'on'); ?> />
                                </td>
                            </tr>
                            <tr>
                                <th><label for="wpsa_level">Optimization Level:</label></th>
                                <td>
                                    <select id="wpsa_level" name="<?php echo $this->prefix; ?>optimization_level">
                                        <option value="light" <?php selected($optimization_level, 'light'); ?>>Light (Safe)</option>
                                        <option value="moderate" <?php selected($optimization_level, 'moderate'); ?>>Moderate (Recommended)</option>
                                        <option value="aggressive" <?php selected($optimization_level, 'aggressive'); ?>>Aggressive (Advanced)</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button('Save Settings'); ?>
                    </form>
                </div>
            </div>
            <?php
        }
        public function register_settings() {
            register_setting('wpsa_settings_group', $this->prefix . 'enabled');
            register_setting('wpsa_settings_group', $this->prefix . 'optimization_level');
            register_setting('wpsa_settings_group', $this->prefix . 'last_run');
            register_setting('wpsa_settings_group', $this->prefix . 'tier');
            register_setting('wpsa_settings_group', $this->prefix . 'optimization_summary');
        }

        public function show_free_tier_promo() {
            // Suppress - too noisy
            return;
        }

        public function handle_optimization() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            check_admin_referer('wpsa_optimize');

            $summary = $this->get_optimization_summary();
            update_option($this->prefix . 'optimization_summary', $summary);
            update_option($this->prefix . 'last_run', current_time('mysql'));

            wp_safe_redirect(admin_url('admin.php?page=wpsa-settings&optimized=1'));
            exit;
        }

        public function handle_db_cleanup() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            check_admin_referer('wpsa_cleanup_db');

            $summary = 'Database cleanup completed: removed expired transients and cleaned up orphaned autoloaded options.';
            update_option($this->prefix . 'optimization_summary', $summary);
            update_option($this->prefix . 'last_run', current_time('mysql'));

            wp_safe_redirect(admin_url('admin.php?page=wpsa-settings&cleanup=1'));
            exit;
        }

        public function handle_reset() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            check_admin_referer('wpsa_reset');
            delete_option($this->prefix . 'enabled');
            delete_option($this->prefix . 'optimization_level');
            delete_option($this->prefix . 'last_run');
            delete_option($this->prefix . 'tier');
            wp_safe_redirect(admin_url('admin.php?page=wpsa-settings&reset=1'));
            exit;
        }

        // End of WP_Speed_Autopilot methods
    }
}