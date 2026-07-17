<?php
if (!defined('ABSPATH')) {
    exit;
}

class Stripe_Payment_Module {
    private static $instance = null;
    private $option_prefix = 'spm_';

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_spm_create_checkout', array($this, 'handle_checkout'));
        add_action('admin_notices', array($this, 'show_notice'));
        add_action('rest_api_init', array($this, 'register_webhook_route'));
    }

    public function register_menu() {
        add_submenu_page(
            'chps-settings',
            'Stripe Payment Module',
            'Stripe Payments',
            'manage_options',
            'chps-spm-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('spm_settings_group', $this->option_prefix . 'enabled');
        register_setting('spm_settings_group', $this->option_prefix . 'secret_key');
        register_setting('spm_settings_group', $this->option_prefix . 'publishable_key');
        register_setting('spm_settings_group', $this->option_prefix . 'webhook_secret');
        register_setting('spm_settings_group', $this->option_prefix . 'pro_price_id');
        register_setting('spm_settings_group', $this->option_prefix . 'corporate_price_id');
        register_setting('spm_settings_group', $this->option_prefix . 'success_url');
        register_setting('spm_settings_group', $this->option_prefix . 'cancel_url');
    }

    public function render_settings_page() {
        $enabled = get_option($this->option_prefix . 'enabled', 0);
        $secret_key = get_option($this->option_prefix . 'secret_key', '');
        $publishable_key = get_option($this->option_prefix . 'publishable_key', '');
        $webhook_secret = get_option($this->option_prefix . 'webhook_secret', '');
        $pro_price_id = get_option($this->option_prefix . 'pro_price_id', '');
        $corporate_price_id = get_option($this->option_prefix . 'corporate_price_id', '');
        $success_url = get_option($this->option_prefix . 'success_url', home_url('/'));
        $cancel_url = get_option($this->option_prefix . 'cancel_url', home_url('/'));
        $webhook_url = rest_url('spm/v1/stripe-webhook');
        ?>
        <div class="wrap">
            <h1>Stripe Payment Module</h1>
            <p>This module is ready to accept Stripe Checkout links for Pro and Corporate access. Add your Stripe credentials later and it will work.</p>
            <form method="post" action="options.php">
                <?php settings_fields('spm_settings_group'); ?>
                <?php do_settings_sections('spm_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Stripe Checkout</th>
                        <td><label><input type="checkbox" name="spm_enabled" value="1" <?php checked($enabled, 1); ?> /> Enable this module</label></td>
                    </tr>
                    <tr>
                        <th scope="row">Stripe Secret Key</th>
                        <td><input type="password" name="spm_secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Stripe Publishable Key</th>
                        <td><input type="text" name="spm_publishable_key" value="<?php echo esc_attr($publishable_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook Secret</th>
                        <td><input type="password" name="spm_webhook_secret" value="<?php echo esc_attr($webhook_secret); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Pro Price ID</th>
                        <td><input type="text" name="spm_pro_price_id" value="<?php echo esc_attr($pro_price_id); ?>" class="regular-text" placeholder="price_..." /></td>
                    </tr>
                    <tr>
                        <th scope="row">Corporate Price ID</th>
                        <td><input type="text" name="spm_corporate_price_id" value="<?php echo esc_attr($corporate_price_id); ?>" class="regular-text" placeholder="price_..." /></td>
                    </tr>
                    <tr>
                        <th scope="row">Success URL</th>
                        <td><input type="text" name="spm_success_url" value="<?php echo esc_attr($success_url); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Cancel URL</th>
                        <td><input type="text" name="spm_cancel_url" value="<?php echo esc_attr($cancel_url); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button('Save Stripe Settings'); ?>
            </form>

            <div class="card" style="max-width:900px;padding:16px;border:1px solid #ddd;background:#fff;margin-top:20px;">
                <h2>Quick Checkout</h2>
                <p>Webhook endpoint: <code><?php echo esc_url($webhook_url); ?></code></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:10px;">
                    <input type="hidden" name="action" value="spm_create_checkout" />
                    <input type="hidden" name="spm_checkout_tier" value="pro" />
                    <?php wp_nonce_field('spm_create_checkout'); ?>
                    <button type="submit" class="button button-primary">Create Pro Checkout</button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                    <input type="hidden" name="action" value="spm_create_checkout" />
                    <input type="hidden" name="spm_checkout_tier" value="corporate" />
                    <?php wp_nonce_field('spm_create_checkout'); ?>
                    <button type="submit" class="button button-secondary">Create Corporate Checkout</button>
                </form>
            </div>
        </div>
        <?php
    }

    public function show_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page !== 'chps-spm-settings') {
            return;
        }

        if (!get_option($this->option_prefix . 'enabled', 0)) {
            echo '<div class="notice notice-info"><p>Stripe Payment Module is installed. Add your Stripe keys and price IDs later to activate checkout.</p></div>';
        }
    }

    public function handle_checkout() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('spm_create_checkout');
        $tier = sanitize_text_field(wp_unslash($_POST['spm_checkout_tier'] ?? 'pro'));

        if (!get_option($this->option_prefix . 'enabled', 0)) {
            wp_redirect(admin_url('admin.php?page=chps-spm-settings&notice=disabled'));
            exit;
        }

        $price_id = $tier === 'corporate'
            ? get_option($this->option_prefix . 'corporate_price_id', '')
            : get_option($this->option_prefix . 'pro_price_id', '');

        if (empty($price_id) || empty(get_option($this->option_prefix . 'secret_key', ''))) {
            wp_redirect(admin_url('admin.php?page=chps-spm-settings&notice=missing-config'));
            exit;
        }

        $checkout_url = $this->create_checkout_session($tier, $price_id);
        if ($checkout_url) {
            wp_redirect($checkout_url);
            exit;
        }

        wp_redirect(admin_url('admin.php?page=chps-spm-settings&notice=checkout-failed'));
        exit;
    }

    public function register_webhook_route() {
        register_rest_route('spm/v1', '/stripe-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_webhook($request) {
        $payload = $request->get_body();
        $signature = $request->get_header('stripe-signature');
        $webhook_secret = get_option($this->option_prefix . 'webhook_secret', '');

        if (empty($webhook_secret) || empty($signature)) {
            return new WP_REST_Response(array('error' => 'Missing webhook configuration'), 400);
        }

        if (!$this->verify_signature($payload, $signature, $webhook_secret)) {
            return new WP_REST_Response(array('error' => 'Invalid signature'), 401);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            return new WP_REST_Response(array('error' => 'Invalid payload'), 400);
        }

        update_option($this->option_prefix . 'last_event', sanitize_text_field($event['type']));
        return new WP_REST_Response(array('received' => true), 200);
    }

    private function verify_signature($payload, $signature, $secret) {
        $elements = explode(',', $signature);
        $timestamp = '';
        $signed = '';

        foreach ($elements as $item) {
            $parts = explode('=', $item, 2);
            if (count($parts) !== 2) {
                continue;
            }

            if ($parts[0] === 't') {
                $timestamp = $parts[1];
            } elseif ($parts[0] === 'v1') {
                $signed = $parts[1];
            }
        }

        if ($timestamp === '' || $signed === '') {
            return false;
        }

        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        return hash_equals($expected, $signed);
    }

    private function create_checkout_session($tier, $price_id) {
        $secret_key = get_option($this->option_prefix . 'secret_key', '');
        $success_url = get_option($this->option_prefix . 'success_url', home_url('/'));
        $cancel_url = get_option($this->option_prefix . 'cancel_url', home_url('/'));

        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'mode' => 'subscription',
                'line_items[0][price]' => $price_id,
                'line_items[0][quantity]' => 1,
                'success_url' => $success_url . '?spm=success',
                'cancel_url' => $cancel_url . '?spm=cancel',
                'metadata[tier]' => $tier,
                'subscription_data[metadata][tier]' => $tier,
                'allow_promotion_codes' => 'true',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return !empty($data['url']) ? $data['url'] : false;
    }
}
