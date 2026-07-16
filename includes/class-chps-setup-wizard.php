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
        ?>
        <div class="wrap">
            <h1>License Setup Wizard</h1>
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
                            <input type="text" name="chps_license_secret" value="<?php echo esc_attr(get_option('chps_license_secret', 'chps-' . wp_generate_uuid4())); ?>" class="regular-text" />
                            <p class="description">Keep this secret on the server and never expose it publicly.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Create Table</th>
                        <td><label><input type="checkbox" name="chps_create_table" value="1" checked="checked" /> Create the MySQL license table</label></td>
                    </tr>
                </table>
                <button type="submit" class="button button-primary">Run Setup</button>
            </form>
        </div>
        <?php
    }

    public function handle_setup() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('chps_run_setup_wizard');

        update_option('chps_license_endpoint', sanitize_url(wp_unslash($_POST['chps_license_endpoint'] ?? '')));
        update_option('chps_license_secret', sanitize_text_field(wp_unslash($_POST['chps_license_secret'] ?? '')));

        if (!empty($_POST['chps_create_table'])) {
            $this->create_license_table();
        }

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
