<?php
if (!defined('ABSPATH')) {
    exit;
}

class CHPS_Settings {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 1);  // Run VERY early so modules can register submenus
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_chps_toggle_module', array($this, 'handle_toggle_module'));
        add_action('admin_post_chps_clear_error_log', array($this, 'handle_clear_error_log'));
        add_action('admin_menu', array($this, 'register_license_submenu'), 99);
        add_action('admin_menu', array($this, 'register_error_log_submenu'), 100);
        // License activation is handled by CHPS_License to avoid duplicate admin_post callbacks.
    }

    public function register_menu() {
        add_menu_page(
            'CuredHosting Plugin Suite',
            'CuredHosting Suite',
            'manage_options',
            'chps-settings',
            array($this, 'render_page'),
            'dashicons-admin-plugins',
            81
        );

        add_submenu_page(
            'chps-settings',
            'Modules',
            'Modules',
            'manage_options',
            'chps-modules',
            array($this, 'render_modules_page')
        );
    }

    public function register_settings() {
        register_setting('chps_options_group', 'chps_license_key');
        register_setting('chps_options_group', 'chps_tier');
        register_setting('chps_options_group', 'chps_debug_enabled');
    }

    public function render_page() {
        $license_key = get_option('chps_license_key', '');
        $tier = get_option('chps_tier', 'free');
        $license_status = get_option('chps_license_status', 'inactive');
        $debug_enabled = get_option('chps_debug_enabled', 0);
        ?>
        <div class="wrap">
            <h1>CuredHosting Plugin Suite</h1>
            <p>Manage your plugin access in one place. Use the quick forms below to activate a license or issue one for a customer.</p>
            <form method="post" action="options.php">
                <?php settings_fields('chps_options_group'); ?>
                <?php do_settings_sections('chps_options_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Current Tier</th>
                        <td>
                            <select name="chps_tier">
                                <option value="free" <?php selected($tier, 'free'); ?>>Free</option>
                                <option value="pro" <?php selected($tier, 'pro'); ?>>Pro ($49/mo)</option>
                                <option value="corporate" <?php selected($tier, 'corporate'); ?>>Corporate ($149/mo)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Current License Key</th>
                        <td><input type="text" name="chps_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Current Status</th>
                        <td>
                            <strong><?php echo esc_html(ucfirst($license_status)); ?></strong>
                            <p class="description">Use the quick activation box below or issue a new key for a customer.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Debug Logging</th>
                        <td>
                            <label><input type="checkbox" name="chps_debug_enabled" value="1" <?php checked($debug_enabled, 1); ?> /> Enable plugin debug logging</label>
                            <p class="description">When enabled, log entries will be written to <code>wp-content/uploads/chps-error.log</code>.</p>
                        </td>
                    </tr>
                </table>
                <p><strong>Tip:</strong> Click Save All Settings to persist your current license, tier, and debug mode before making any further changes.</p>
                <?php submit_button('Save All Settings', 'primary', 'submit', false, array('onclick' => "return confirm('Saving changes can overwrite your current configuration. Are you sure you want to continue? Make sure you have saved any important settings before proceeding.');")); ?>
            </form>

            <div class="card" style="max-width:900px;padding:16px;border:1px solid #ddd;background:#fff;margin-top:20px;">
                <h2>Quick Activate</h2>
                <p>Paste a known license key and click activate to unlock the chosen tier instantly.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:10px;">
                    <input type="hidden" name="action" value="chps_activate_license" />
                    <input type="hidden" name="chps_license_tier" value="<?php echo esc_attr($tier); ?>" />
                    <?php wp_nonce_field('chps_activate_license'); ?>
                    <label for="chps-license-key">License key</label>
                    <input type="text" id="chps-license-key" name="chps_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" />
                    <input type="email" name="chps_customer_email" placeholder="customer@example.com" class="regular-text" />
                    <button type="submit" class="button button-primary" onclick="return confirm('Activating a license will change your current tier and settings. Be sure you have saved any current configuration before continuing.');">Activate</button>
                </form>

                <?php if ($license_status === 'active') : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                        <input type="hidden" name="action" value="chps_deactivate_license" />
                        <?php wp_nonce_field('chps_deactivate_license'); ?>
                        <button type="submit" class="button" onclick="return confirm('Deactivating the license will switch the site back to the free tier. Make sure you have saved your current settings before continuing.');">Deactivate</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width:900px;padding:16px;border:1px solid #ddd;background:#fff;margin-top:20px;">
                <h2>Issue a License</h2>
                <p>Enter the customer email and choose the tier. One click generates a license key for that customer.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="chps_issue_license" />
                    <?php wp_nonce_field('chps_issue_license'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Customer Email</th>
                            <td><input type="email" name="chps_customer_email" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row">Tier</th>
                            <td>
                                <select name="chps_issue_tier">
                                    <option value="pro">Pro</option>
                                    <option value="corporate">Corporate</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Also activate here</th>
                            <td><label><input type="checkbox" name="chps_activate_immediately" value="1" /> Turn it on for this site right away</label></td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary" onclick="return confirm('Issuing a license can change a customer site tier and configuration. Please confirm you have saved your current settings before proceeding.');">Issue License</button>
                </form>
            </div>

            <div class="card" style="max-width:900px;padding:16px;border:1px solid #ddd;background:#fff;margin-top:20px;">
                <h2>Installed Modules</h2>
                <?php $this->render_modules_list(); ?>
            </div>
        </div>
        <?php
    }

    public function render_modules_page() {
        ?>
        <div class="wrap">
            <h1>CuredHosting Suite Modules</h1>
            <p>These modules are registered with the CuredHosting Plugin Suite and loaded from the suite's modules folder.</p>
            <div class="card" style="max-width:900px;padding:16px;border:1px solid #ddd;background:#fff;margin-top:20px;">
                <?php $this->render_modules_list(); ?>
            </div>
        </div>
        <?php
    }

    public function handle_toggle_module() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $slug = sanitize_key(wp_unslash($_POST['module_slug'] ?? ''));
        check_admin_referer('chps_toggle_module_' . $slug);

        if (empty($slug)) {
            wp_safe_redirect(admin_url('admin.php?page=chps-modules'));
            exit;
        }

        $current = get_option('chps_module_status_' . $slug, 'active');
        $new_status = $current === 'active' ? 'disabled' : 'active';
        update_option('chps_module_status_' . $slug, $new_status);

        wp_safe_redirect(admin_url('admin.php?page=chps-modules&module_status=' . rawurlencode($new_status) . '&module=' . rawurlencode($slug)));
        exit;
    }

    private function get_module_status($module) {
        $slug = $module['slug'] ?? '';
        if (empty($slug)) {
            return 'unknown';
        }

        return get_option('chps_module_status_' . sanitize_key($slug), $module['status'] ?? 'active');
    }

    private function render_modules_list() {
        $modules = chps_get_registered_modules();
        if (empty($modules)) {
            echo '<p>No modules registered yet.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Module</th><th>Slug</th><th>Version</th><th>Status</th><th>Admin Page</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        foreach ($modules as $module) {
            $name = esc_html($module['name'] ?? 'Unnamed');
            $slug = esc_html($module['slug'] ?? 'unknown');
            $version = esc_html($module['version'] ?? '1.0.1');
            $status = esc_html($this->get_module_status($module));
            $admin_slug = isset($module['admin_slug']) ? esc_attr($module['admin_slug']) : '';
            $admin_link = $admin_slug ? admin_url('admin.php?page=' . rawurlencode($admin_slug)) : '';
            $enabled = $status === 'active';

            echo '<tr>';
            echo '<td>' . $name . '</td>';
            echo '<td>' . $slug . '</td>';
            echo '<td>' . $version . '</td>';
            echo '<td>' . ucfirst($status) . '</td>';
            echo '<td>';
            if ($admin_link && $enabled) {
                echo '<a href="' . esc_url($admin_link) . '">View</a>';
            } else {
                echo 'None';
            }
            echo '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('chps_toggle_module_' . $slug);
            echo '<input type="hidden" name="action" value="chps_toggle_module" />';
            echo '<input type="hidden" name="module_slug" value="' . esc_attr($slug) . '" />';
            echo '<button type="submit" class="button" onclick="return confirm(\'Changing module status can alter your current configuration. Please make sure you have saved your settings before continuing.\')">' . ($enabled ? 'Disable' : 'Enable') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    public function register_license_submenu() {
        add_submenu_page(
            'options-general.php',
            'CuredHosting License',
            'CuredHosting License',
            'manage_options',
            'chps-license-key',
            array($this, 'render_license_key_page')
        );
    }

    public function register_error_log_submenu() {
        add_submenu_page(
            'chps-settings',
            'Error Log',
            'Error Log',
            'manage_options',
            'chps-error-log',
            array($this, 'render_error_log_page')
        );
    }

    public function render_error_log_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $logger = CHPS_Logger::instance();
        $entries = $logger->get_recent_entries(100);
        $log_url = $logger->get_log_file_url();
        $cleared = isset($_GET['cleared']);
        ?>
        <div class="wrap">
            <h1>CuredHosting Error Log</h1>
            <p>Recent plugin log entries are shown below. The log file is stored at <code><?php echo esc_html($logger->get_log_file_path()); ?></code>.</p>
            <?php if ($cleared) : ?>
                <div class="notice notice-success"><p>Log cleared successfully.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:20px;">
                <?php wp_nonce_field('chps_clear_error_log'); ?>
                <input type="hidden" name="action" value="chps_clear_error_log" />
                <button type="submit" class="button button-secondary">Clear Log</button>
                <a href="<?php echo esc_url($log_url); ?>" class="button">Download Raw Log</a>
            </form>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Level</th>
                        <th>Message</th>
                        <th>Context</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)) : ?>
                        <tr><td colspan="4">No log entries found.</td></tr>
                    <?php else : ?>
                        <?php foreach ($entries as $entry) : ?>
                            <tr>
                                <td><?php echo esc_html($entry['timestamp'] ?? ''); ?></td>
                                <td><?php echo esc_html(ucfirst($entry['level'] ?? '')); ?></td>
                                <td><?php echo esc_html($entry['message'] ?? ''); ?></td>
                                <td><pre style="margin:0; white-space:pre-wrap;"><?php echo esc_html(wp_json_encode($entry['context'] ?? array(), JSON_PRETTY_PRINT)); ?></pre></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_clear_error_log() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('chps_clear_error_log');
        CHPS_Logger::instance()->clear_log();

        wp_safe_redirect(admin_url('admin.php?page=chps-error-log&cleared=1'));
        exit;
    }

    public function render_license_key_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $license_key = get_option('chps_license_key', '');
        $tier = get_option('chps_tier', 'free');
        $license_status = get_option('chps_license_status', 'inactive');
        ?>
        <div class="wrap" style="max-width:600px;margin-top:20px;">
            <h1>🔑 CuredHosting License Key</h1>
            <p>Enter or manage your license key below.</p>

            <div class="card" style="padding:20px;border:1px solid #ddd;background:#fff;margin:20px 0;">
                <h2 style="margin-top:0;">License Status</h2>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th scope="row">Current Status:</th>
                        <td>
                            <strong style="color: <?php echo $license_status === 'active' ? '#008000' : '#d32f2f'; ?>;">
                                <?php echo esc_html(ucfirst($license_status)); ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Current Tier:</th>
                        <td><strong><?php echo esc_html(ucfirst($tier)); ?></strong></td>
                    </tr>
                </table>
            </div>

            <div class="card" style="padding:20px;border:1px solid #ddd;background:#fff;margin:20px 0;">
                <h2 style="margin-top:0;">Enter License Key</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="chps_activate_license" />
                    <?php wp_nonce_field('chps_activate_license'); ?>
                    
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th scope="row">
                                <label for="chps-license-key">License Key:</label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="chps-license-key" 
                                    name="chps_license_key" 
                                    value="<?php echo esc_attr($license_key); ?>" 
                                    class="regular-text" 
                                    placeholder="e.g., CHPS-xxxx-xxxx-xxxx"
                                    required
                                />
                                <p class="description">Paste your license key here to activate your tier.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="chps-tier">Tier to Unlock:</label>
                            </th>
                            <td>
                                <select id="chps-tier" name="chps_license_tier">
                                    <option value="pro">Pro ($49/mo)</option>
                                    <option value="corporate">Corporate ($149/mo)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary button-large">Activate License</button>
                    </p>
                </form>
            </div>

            <div class="card" style="padding:20px;border:1px solid #ccc;background:#f9f9f9;">
                <h3>Need a license?</h3>
                <p>Contact: <strong>siteguardian@plaguedr.online</strong></p>
                <p style="font-size:12px;color:#666;">Pro plan: $49/mo • Corporate plan: $149/mo</p>
            </div>
        </div>
        <?php
    }

    public function handle_activate_license() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('chps_activate_license');

        $license_key = sanitize_text_field(wp_unslash($_POST['chps_license_key'] ?? ''));
        $tier = sanitize_text_field(wp_unslash($_POST['chps_license_tier'] ?? 'pro'));

        if (empty($license_key)) {
            wp_safe_redirect(admin_url('options-general.php?page=chps-license-key&error=empty_key'));
            exit;
        }

        update_option('chps_license_key', $license_key);
        update_option('chps_tier', $tier);
        update_option('chps_license_status', 'active');

        wp_safe_redirect(admin_url('options-general.php?page=chps-license-key&success=1'));
        exit;
    }

    public function reduce_notices() {
        // Only show notices on CuredHosting pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'chps') === false) {
            // Suppress plugin notices on non-CuredHosting pages to reduce clutter
            remove_action('admin_notices', array(CHPS_Admin::instance(), 'show_tier_notice'));
        }
    }

    public function hide_notices_css() {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (!empty($page) && (strpos($page, 'chps') === 0 || $page === 'chps-license-key')) {
            echo '<style>
                .notice { display: none !important; }
                .update-nag { display: none !important; }
            </style>';
        }
    }

    public function ensure_module_pages_accessible() {
        global $pagenow;
        
        // If we're on admin.php with a CuredHosting module page, add a top-level menu so the page is properly registered
        if ($pagenow === 'admin.php' && isset($_GET['page'])) {
            $page = sanitize_text_field(wp_unslash($_GET['page']));
            // Check if it's a CHPS module page
            if (strpos($page, 'chps-') === 0) {
                // Register as a temporary top-level menu so WordPress recognizes it as a valid page
                add_menu_page(
                    'CHPS Module',
                    'CHPS Module',
                    'manage_options',
                    $page,
                    '__return_null',
                    'dashicons-admin-generic',
                    999
                );
            }
        }
    }
}
