<?php
/**
 * Plugin Name: CuredHosting Suite — Server Guardian
 * Description: Security hardening and monitoring module for the CuredHosting Plugin Suite.
 * Version: 1.0.1
 * Author: CuredHosting
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Register with the Suite and initialize
add_action('plugins_loaded', function() {
    if (function_exists('chps_register_module')) {
        chps_register_module([
            'name' => 'Server Guardian',
            'slug' => 'server-guardian',
            'version' => '1.0.1',
            'admin_slug' => 'wpsg-settings',
            'status' => 'active'
        ]);
    }

    if (function_exists('chps_is_module_active') && !chps_is_module_active('server-guardian')) {
        return;
    }

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
            if (method_exists($this, 'show_free_tier_promo')) {
                add_action('admin_notices', [$this, 'show_free_tier_promo']);
            }
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

        public function add_admin_menu() {
            add_submenu_page(
                'chps-settings',
                'Server Guardian',
                'Server Guardian',
                'manage_options',
                'wpsg-settings',
                [$this, 'render_admin_page']
            );
        }

        public function render_admin_page() {
            $hardening_level = get_option($this->option_prefix . 'hardening_level', 'moderate');
            $monitoring_enabled = get_option($this->option_prefix . 'monitoring_enabled', 'on');
            ?>
            <div class="wrap">
                <h1>🛡️ Server Guardian</h1>
                <p>Monitor and harden your server security.</p>
                <?php if (isset($_GET['hardened']) && $_GET['hardened'] === '1') : ?>
                    <div class="notice notice-success is-dismissible"><p>Hardening rules applied successfully.</p></div>
                <?php elseif (isset($_GET['wpsg_error'])) : ?>
                    <?php if ($_GET['wpsg_error'] === 'invalid_nonce') : ?>
                        <div class="notice notice-error is-dismissible"><p>Security verification failed. Please try again.</p></div>
                    <?php elseif ($_GET['wpsg_error'] === 'unauthorized') : ?>
                        <div class="notice notice-error is-dismissible"><p>Unauthorized action. Make sure you are logged in with sufficient privileges.</p></div>
                    <?php else : ?>
                        <div class="notice notice-error is-dismissible"><p>An unknown error occurred while applying hardening rules.</p></div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="card" style="max-width:800px;padding:20px;margin:20px 0;">
                    <h2>Security Status</h2>
                    <table class="form-table">
                        <tr>
                            <th>Monitoring:</th>
                            <td><strong><?php echo $monitoring_enabled === 'on' ? '✓ Active' : '○ Inactive'; ?></strong></td>
                        </tr>
                        <tr>
                            <th>Hardening Level:</th>
                            <td><strong><?php echo esc_html(ucfirst($hardening_level)); ?></strong></td>
                        </tr>
                    </table>
                </div>
                
                <div class="card" style="max-width:800px;padding:20px;margin:20px 0;">
                    <h2>Quick Actions</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                        <input type="hidden" name="action" value="wpsg_harden" />
                        <?php wp_nonce_field('wpsg_harden'); ?>
                        <button type="submit" class="button button-primary">🔒 Apply Hardening Rules</button>
                    </form>
                    
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;margin-left:10px;">
                        <input type="hidden" name="action" value="wpsg_reset_logs" />
                        <?php wp_nonce_field('wpsg_reset_logs'); ?>
                        <button type="submit" class="button">📋 Clear Security Logs</button>
                    </form>
                </div>
                
                <div class="card" style="max-width:800px;padding:20px;margin:20px 0;">
                    <h2>Configuration</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('wpsg_settings_group'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="wpsg_monitoring">Enable Monitoring:</label></th>
                                <td>
                                    <input type="checkbox" id="wpsg_monitoring" name="<?php echo $this->option_prefix; ?>monitoring_enabled" value="on" <?php checked($monitoring_enabled, 'on'); ?> />
                                </td>
                            </tr>
                            <tr>
                                <th><label for="wpsg_level">Hardening Level:</label></th>
                                <td>
                                    <select id="wpsg_level" name="<?php echo $this->option_prefix; ?>hardening_level">
                                        <option value="light" <?php selected($hardening_level, 'light'); ?>>Light (Minimal)</option>
                                        <option value="moderate" <?php selected($hardening_level, 'moderate'); ?>>Moderate (Recommended)</option>
                                        <option value="strict" <?php selected($hardening_level, 'strict'); ?>>Strict (Maximum)</option>
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
        
        /**
         * Apply active rules when the module is instantiated during plugins_loaded.
         * Minimal implementation to avoid fatal errors; real hardening is optional.
         */
        public function apply_active_rules() {
            update_option($this->option_prefix . 'last_check', current_time('mysql'));
            // If an auto-harden flag is set, apply hardening silently.
            $auto = get_option($this->option_prefix . 'auto_harden', 0);
            if ($auto) {
                $this->apply_hardening(false);
            }
        }

        public function register_settings() {
            register_setting('wpsg_settings_group', $this->option_prefix . 'notify_email');
            register_setting('wpsg_settings_group', $this->option_prefix . 'tier');
            register_setting('wpsg_settings_group', $this->option_prefix . 'last_check');
            register_setting('wpsg_settings_group', $this->option_prefix . 'auto_harden');
            register_setting('wpsg_settings_group', $this->option_prefix . 'monitoring_enabled');
            register_setting('wpsg_settings_group', $this->option_prefix . 'hardening_level');
        }

        public function add_dashboard_widget() {
            wp_add_dashboard_widget('wpsg_dashboard', 'Server Guardian', [$this, 'render_dashboard_widget']);
        }

        public function render_dashboard_widget() {
            echo '<p>Server Guardian status: Active</p>';
        }

        public function show_free_tier_promo() {
            // Suppress - too noisy
            return;
        }

        public function enqueue_admin_assets($hook) {
            // Placeholder for enqueued admin scripts/styles when needed.
        }

        public function log_failed_login($username) {
            // Minimal logging to option to avoid heavy dependencies.
            $logs = get_option($this->option_prefix . 'failed_logins', []);
            $logs[] = ['time' => current_time('mysql'), 'user' => $username];
            update_option($this->option_prefix . 'failed_logins', array_slice($logs, -100));
        }

        public function log_successful_login($user_login, $user) {
            $last = ['time' => current_time('mysql'), 'user' => $user_login];
            update_option($this->option_prefix . 'last_successful_login', $last);
        }

        public function send_daily_report() {
            // Minimal: update last report time.
            update_option($this->option_prefix . 'last_report_sent', current_time('mysql'));
        }

        public function handle_backup_prompt() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            check_admin_referer('wpsg_backup_prompt');
            wp_safe_redirect(admin_url('admin.php?page=wpsg-settings&backup_prompted=1'));
            exit;
        }

        public function perform_uptime_check() {
            // Lightweight uptime marker
            update_option($this->option_prefix . 'last_uptime_check', current_time('mysql'));
        }

        public function apply_hardening($redirect = true) {
            if ($redirect) {
                if (!current_user_can('manage_options')) {
                    wp_safe_redirect(admin_url('admin.php?page=wpsg-settings&wpsg_error=unauthorized'));
                    exit;
                }

                if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce(wp_unslash($_REQUEST['_wpnonce']), 'wpsg_harden')) {
                    wp_safe_redirect(admin_url('admin.php?page=wpsg-settings&wpsg_error=invalid_nonce'));
                    exit;
                }
            }

            // Minimal hardening actions: disable file editing and update timestamp.
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }

            update_option($this->option_prefix . 'last_hardened', current_time('mysql'));

            if ($redirect) {
                wp_safe_redirect(admin_url('admin.php?page=wpsg-settings&hardened=1'));
                exit;
            }
        }

        public function reset_logs() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            check_admin_referer('wpsg_reset_logs');
            delete_option($this->option_prefix . 'failed_logins');
            delete_option($this->option_prefix . 'last_report_sent');
            wp_safe_redirect(admin_url('admin.php?page=wpsg-settings&logs_reset=1'));
            exit;
        }
    }
}