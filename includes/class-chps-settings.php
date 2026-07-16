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
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
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
    }

    public function register_settings() {
        register_setting('chps_options_group', 'chps_license_key');
        register_setting('chps_options_group', 'chps_tier');
    }

    public function render_page() {
        $license_key = get_option('chps_license_key', '');
        $tier = get_option('chps_tier', 'free');
        $license_status = get_option('chps_license_status', 'inactive');
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
                </table>
                <?php submit_button('Save Tier Settings'); ?>
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
                    <button type="submit" class="button button-primary">Activate</button>
                </form>

                <?php if ($license_status === 'active') : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                        <input type="hidden" name="action" value="chps_deactivate_license" />
                        <?php wp_nonce_field('chps_deactivate_license'); ?>
                        <button type="submit" class="button">Deactivate</button>
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
                    <button type="submit" class="button button-primary">Issue License</button>
                </form>
            </div>
        </div>
        <?php
    }
}
