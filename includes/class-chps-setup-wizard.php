<?php
if (!defined('ABSPATH')) {
    exit;
}

class CHPS_Setup_Wizard {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function migrate_sensitive_options_to_noautoload() {
        global $wpdb;
        $sensitive_keys = array(
            'chps_stripe_secret_key',
            'chps_stripe_webhook_secret',
            'chps_license_secret',
            'spm_secret_key',
            'spm_webhook_secret',
        );

        foreach ($sensitive_keys as $key) {
            $option = $wpdb->get_row($wpdb->prepare("SELECT option_id, autoload FROM {$wpdb->options} WHERE option_name = %s", $key));
            if ($option && $option->autoload === 'yes') {
                $wpdb->update($wpdb->options, array('autoload' => 'no'), array('option_id' => $option->option_id));
            }
        }
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_post_chps_run_setup_wizard', array($this, 'handle_setup'));
    }

    public function register_menu() {
        add_submenu_page(
            'chps-settings',
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'chps-setup-wizard',
            array($this, 'render_page')
        );
    }

    public function render_page() {
        $setup_done = isset($_GET['setup']) && sanitize_text_field(wp_unslash($_GET['setup'])) === 'done';
        $setup_recent = isset($_GET['setup']) && sanitize_text_field(wp_unslash($_GET['setup'])) === 'recent';
        ?>
        <div class="wrap">
            <h1>License Setup Wizard</h1>
            <?php if ($setup_done) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>Setup completed.</strong> Your license settings have been saved and the license table was created.</p></div>
            <?php elseif ($setup_recent) : ?>
                <div class="notice notice-warning is-dismissible"><p><strong>Setup already ran recently.</strong> If you already clicked <em>Run Setup</em>, wait a minute before re-running to avoid duplicate operations.</p></div>
            <?php endif; ?>
            <p>This wizard creates the license table and configures the basic server-side license settings.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="chps_run_setup_wizard" />
                <?php wp_nonce_field('chps_run_setup_wizard'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">License Endpoint</th>
                        <td>
                            <input type="text" name="chps_license_endpoint" value="<?php echo esc_attr(get_option('chps_license_endpoint', home_url('/wp-json/chps/v1/license-check'))); ?>" class="regular-text" />
                            <p class="description">This is the URL the plugin will use to validate a license key.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Shared Secret</th>
                        <td>
                            <input type="password" name="chps_license_secret" value="" class="regular-text" placeholder="Leave empty to keep existing" />
                            <?php if (!empty(get_option('chps_license_secret', ''))) : ?>
                                <p class="description">A shared secret exists (hidden). Leave blank to keep it, or enter a new one to rotate.</p>
                            <?php else : ?>
                                <p class="description">Enter a shared secret used to validate license requests.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Create Table</th>
                        <td><label><input type="checkbox" name="chps_create_table" value="1" checked="checked" /> Create the MySQL license table</label></td>
                    </tr>
                </table>
                <button type="submit" id="chps-run-setup-btn" class="button button-primary" onclick="this.disabled=true;this.innerText='Running...';return true;">Run Setup</button>
            </form>
        </div>
        <?php
    }

    public function handle_setup() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('chps_run_setup_wizard');
        
        // Prevent rapid repeated runs: if setup ran recently, redirect with notice
        $last = get_transient('chps_setup_last_run');
        if (!empty($last)) {
            wp_redirect(admin_url('admin.php?page=chps-setup-wizard&setup=recent'));
            exit;
        }

        update_option('chps_license_endpoint', sanitize_url(wp_unslash($_POST['chps_license_endpoint'] ?? '')));
        $submitted_secret = isset($_POST['chps_license_secret']) ? sanitize_text_field(wp_unslash($_POST['chps_license_secret'])) : '';
        if (trim($submitted_secret) !== '') {
            update_option('chps_license_secret', $submitted_secret);
        }

        if (!empty($_POST['chps_create_table'])) {
            $this->create_license_table();
        }

        // Mark the setup as run to avoid accidental rapid re-runs (ttl: 60s)
        set_transient('chps_setup_last_run', time(), 60);

            // ensure any secrets written during setup are not autoloaded
            $this->migrate_sensitive_options_to_noautoload();
            wp_redirect(admin_url('admin.php?page=chps-setup-wizard&setup=done'));
        exit;
    }

    private function create_license_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'chps_licenses';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            license_key varchar(255) NOT NULL,
            tier varchar(32) NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'active',
            issued_at datetime NOT NULL,
            expires_at datetime NULL,
            site_hash varchar(64) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY license_key (license_key),
            KEY email (email),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
