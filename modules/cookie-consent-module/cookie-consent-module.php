<?php
/**
 * Plugin Name: CuredHosting Cookie Consent
 * Description: Lightweight cookie consent banner for WordPress sites with free, pro, and corporate tier support.
 * Version: 1.0.0
 * Author: CuredHosting
 */

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('chps_register_module')) {
    chps_register_module([
        'name' => 'Cookie Consent',
        'slug' => 'cookie-consent',
        'version' => '1.0.0',
        'admin_slug' => 'chps-cookie-consent'
    ]);
}

if (!class_exists('CH_Cookie_Consent')) {
    class CH_Cookie_Consent {
        private $option_prefix = 'chcc_';

        public function __construct() {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
            add_action('wp_footer', [$this, 'render_banner']);
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_notices', [$this, 'show_free_tier_promo']);
            add_action('admin_post_chcc_save', [$this, 'handle_save']);
            add_action('init', [$this, 'handle_consent_action']);
        }

        public function enqueue_frontend_assets() {
            wp_enqueue_style('chcc-style', false);
            wp_add_inline_style('chcc-style', $this->get_css());
            wp_enqueue_script('chcc-script', plugin_dir_url(__FILE__) . 'assets/cookie-consent.js', [], '1.0.0', true);
            wp_localize_script('chcc-script', 'chccData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'consentKey' => $this->option_prefix . 'consent',
                'message' => get_option($this->option_prefix . 'message', 'We use cookies to improve your experience on this site.'),
                'acceptLabel' => get_option($this->option_prefix . 'accept_label', 'Accept'),
                'declineLabel' => get_option($this->option_prefix . 'decline_label', 'Decline'),
            ]);
        }

        public function add_admin_menu() {
            add_submenu_page(
                'chps-settings',
                'Cookie Consent',
                'Cookie Consent',
                'manage_options',
                'chps-cookie-consent',
                [$this, 'render_admin_page']
            );
        }

        public function register_settings() {
            register_setting('chcc_settings_group', $this->option_prefix . 'message');
            register_setting('chcc_settings_group', $this->option_prefix . 'accept_label');
            register_setting('chcc_settings_group', $this->option_prefix . 'decline_label');
            register_setting('chcc_settings_group', $this->option_prefix . 'tier');
        }

        public function render_admin_page() {
            $message = get_option($this->option_prefix . 'message', 'We use cookies to improve your experience on this site.');
            $acceptLabel = get_option($this->option_prefix . 'accept_label', 'Accept');
            $declineLabel = get_option($this->option_prefix . 'decline_label', 'Decline');
            $tier = get_option($this->option_prefix . 'tier', 'free');
            ?>
            <div class="wrap">
                <h1>Cookie Consent Settings</h1>
                <p>Basic consent banner for free users, with upgrade paths for Pro and Corporate.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="chcc_save">
                    <?php wp_nonce_field('chcc_save'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="chcc_message">Banner Message</label></th>
                            <td><input type="text" id="chcc_message" name="chcc_message" value="<?php echo esc_attr($message); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="chcc_accept_label">Accept Label</label></th>
                            <td><input type="text" id="chcc_accept_label" name="chcc_accept_label" value="<?php echo esc_attr($acceptLabel); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="chcc_decline_label">Decline Label</label></th>
                            <td><input type="text" id="chcc_decline_label" name="chcc_decline_label" value="<?php echo esc_attr($declineLabel); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="chcc_tier">Tier</label></th>
                            <td>
                                <select id="chcc_tier" name="chcc_tier">
                                    <option value="free" <?php selected($tier, 'free'); ?>>Free</option>
                                    <option value="pro" <?php selected($tier, 'pro'); ?>>Pro</option>
                                    <option value="corporate" <?php selected($tier, 'corporate'); ?>>Corporate</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>
            <?php
        }

        public function handle_save() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            check_admin_referer('chcc_save');
            update_option($this->option_prefix . 'message', sanitize_text_field(wp_unslash($_POST['chcc_message'] ?? '')));
            update_option($this->option_prefix . 'accept_label', sanitize_text_field(wp_unslash($_POST['chcc_accept_label'] ?? '')));
            update_option($this->option_prefix . 'decline_label', sanitize_text_field(wp_unslash($_POST['chcc_decline_label'] ?? '')));
            update_option($this->option_prefix . 'tier', sanitize_text_field(wp_unslash($_POST['chcc_tier'] ?? 'free')));
            wp_redirect(admin_url('admin.php?page=chps-cookie-consent&updated=1'));
            exit;
        }

        public function handle_consent_action() {
            if (!isset($_GET['chcc_action'])) {
                return;
            }

            $action = sanitize_text_field(wp_unslash($_GET['chcc_action']));
            if ($action === 'accept') {
                setcookie($this->option_prefix . 'consent', 'accepted', time() + YEAR_IN_SECONDS, '/');
            } elseif ($action === 'decline') {
                setcookie($this->option_prefix . 'consent', 'declined', time() + YEAR_IN_SECONDS, '/');
            }

            wp_safe_redirect(wp_get_referer() ?: home_url('/'));
            exit;
        }

        public function render_banner() {
            if (isset($_COOKIE[$this->option_prefix . 'consent'])) {
                return;
            }

            $message = get_option($this->option_prefix . 'message', 'We use cookies to improve your experience on this site.');
            $accept = get_option($this->option_prefix . 'accept_label', 'Accept');
            $decline = get_option($this->option_prefix . 'decline_label', 'Decline');
            $tier = get_option($this->option_prefix . 'tier', 'free');
            $promo = $tier === 'free' ? ' Upgrade to Pro for $49/month or use CuredHosting plans starting at $49/month with these tools built in.' : '';
            ?>
            <div id="chcc-banner" class="chcc-banner" role="dialog" aria-live="polite" aria-label="Cookie consent">
                <div class="chcc-content">
                    <p><?php echo esc_html($message . $promo); ?></p>
                    <div class="chcc-actions">
                        <a href="<?php echo esc_url(add_query_arg('chcc_action', 'accept', home_url('/'))); ?>" class="chcc-btn chcc-btn-accept"><?php echo esc_html($accept); ?></a>
                        <a href="<?php echo esc_url(add_query_arg('chcc_action', 'decline', home_url('/'))); ?>" class="chcc-btn chcc-btn-decline"><?php echo esc_html($decline); ?></a>
                    </div>
                </div>
            </div>
            <?php
        }

        private function get_css() {
            return '.chcc-banner{position:fixed;left:0;right:0;bottom:0;background:#0f172a;color:#fff;padding:16px 20px;z-index:99999;box-shadow:0 -4px 20px rgba(0,0,0,.15)}.chcc-content{max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;gap:16px}.chcc-content p{margin:0;font-size:14px}.chcc-actions{display:flex;gap:10px}.chcc-btn{display:inline-block;padding:8px 14px;border-radius:6px;text-decoration:none;font-weight:600}.chcc-btn-accept{background:#00a32a;color:#fff}.chcc-btn-decline{background:#fff;color:#111}@media (max-width:768px){.chcc-content{flex-direction:column;align-items:flex-start}.chcc-actions{width:100%}}';
        }
    }

    new CH_Cookie_Consent();
}
