<?php
if (!defined('ABSPATH')) {
    exit;
}

class CHPS_Stripe {
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
        add_action('admin_post_chps_create_checkout', array($this, 'handle_checkout_redirect'));
        add_action('admin_notices', array($this, 'maybe_show_notice'));
        add_action('rest_api_init', array($this, 'register_webhook_route'));
    }

    public function register_menu() {
        add_submenu_page(
            'chps-settings',
            'Stripe Billing',
            'Stripe Billing',
            'manage_options',
            'chps-stripe',
            array($this, 'render_page')
        );
    }

    public function register_settings() {
        register_setting('chps_stripe_group', 'chps_stripe_enabled');
        register_setting('chps_stripe_group', 'chps_stripe_secret_key');
        register_setting('chps_stripe_group', 'chps_stripe_publishable_key');
        register_setting('chps_stripe_group', 'chps_stripe_webhook_secret');
        register_setting('chps_stripe_group', 'chps_stripe_pro_price_id');
        register_setting('chps_stripe_group', 'chps_stripe_corporate_price_id');
        register_setting('chps_stripe_group', 'chps_stripe_success_url');
        register_setting('chps_stripe_group', 'chps_stripe_cancel_url');
    }

    public function render_page() {
        $enabled = get_option('chps_stripe_enabled', 0);
        $secret_key = get_option('chps_stripe_secret_key', '');
        $publishable_key = get_option('chps_stripe_publishable_key', '');
        $webhook_secret = get_option('chps_stripe_webhook_secret', '');
        $pro_price_id = get_option('chps_stripe_pro_price_id', '');
        $corporate_price_id = get_option('chps_stripe_corporate_price_id', '');
        $success_url = get_option('chps_stripe_success_url', home_url('/'));
        $cancel_url = get_option('chps_stripe_cancel_url', home_url('/'));
        $webhook_url = rest_url('chps/v1/stripe-webhook');
        ?>
        <div class="wrap">
            <h1>Stripe Billing</h1>
            <p>Sell Pro and Corporate access with Stripe Checkout and unlock the feature set without relying on EDD.</p>
            <form method="post" action="options.php">
                <?php settings_fields('chps_stripe_group'); ?>
                <?php do_settings_sections('chps_stripe_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Stripe</th>
                        <td><label><input type="checkbox" name="chps_stripe_enabled" value="1" <?php checked($enabled, 1); ?> /> Enable checkout and webhooks</label></td>
                    </tr>
                    <tr>
                        <th scope="row">Secret Key</th>
                        <td><input type="password" name="chps_stripe_secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Publishable Key</th>
                        <td><input type="text" name="chps_stripe_publishable_key" value="<?php echo esc_attr($publishable_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook Secret</th>
                        <td><input type="password" name="chps_stripe_webhook_secret" value="<?php echo esc_attr($webhook_secret); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Pro Price ID</th>
                        <td><input type="text" name="chps_stripe_pro_price_id" value="<?php echo esc_attr($pro_price_id); ?>" class="regular-text" placeholder="price_..." /></td>
                    </tr>
                    <tr>
                        <th scope="row">Corporate Price ID</th>
                        <td><input type="text" name="chps_stripe_corporate_price_id" value="<?php echo esc_attr($corporate_price_id); ?>" class="regular-text" placeholder="price_..." /></td>
                    </tr>
                    <tr>
                        <th scope="row">Success URL</th>
                        <td><input type="text" name="chps_stripe_success_url" value="<?php echo esc_attr($success_url); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Cancel URL</th>
                        <td><input type="text" name="chps_stripe_cancel_url" value="<?php echo esc_attr($cancel_url); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button('Save Stripe Settings'); ?>
            </form>

            <div class="card" style="max-width:900px;padding:16px;border:1px solid #ddd;background:#fff;margin-top:20px;">
                <h2>Quick Checkout</h2>
                <p>Webhook endpoint: <code><?php echo esc_url($webhook_url); ?></code></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:10px;">
                    <input type="hidden" name="action" value="chps_create_checkout" />
                    <input type="hidden" name="chps_checkout_tier" value="pro" />
                    <?php wp_nonce_field('chps_create_checkout'); ?>
                    <button type="submit" class="button button-primary">Create Pro Checkout</button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                    <input type="hidden" name="action" value="chps_create_checkout" />
                    <input type="hidden" name="chps_checkout_tier" value="corporate" />
                    <?php wp_nonce_field('chps_create_checkout'); ?>
                    <button type="submit" class="button button-secondary">Create Corporate Checkout</button>
                </form>
            </div>
        </div>
        <?php
    }

    public function maybe_show_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['chps_stripe']) && sanitize_text_field(wp_unslash($_GET['chps_stripe'])) === 'success') {
            echo '<div class="notice notice-success"><p>Checkout completed. The suite will activate your requested tier automatically once Stripe confirms the purchase.</p></div>';
            return;
        }

        if (isset($_GET['chps_stripe']) && sanitize_text_field(wp_unslash($_GET['chps_stripe'])) === 'cancel') {
            echo '<div class="notice notice-warning"><p>Checkout was canceled. No changes were made to your license.</p></div>';
            return;
        }

        if (!get_option('chps_stripe_enabled', 0)) {
            echo '<div class="notice notice-info"><p>Stripe billing is ready. Enable it and add your keys to start selling Pro and Corporate access.</p></div>';
        }
    }

    public function handle_checkout_redirect() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('chps_create_checkout');

        $tier = sanitize_text_field(wp_unslash($_POST['chps_checkout_tier'] ?? 'pro'));
        if (!in_array($tier, array('pro', 'corporate'), true)) {
            wp_die('Invalid tier.');
        }

        if (!get_option('chps_stripe_enabled', 0)) {
            wp_redirect(admin_url('admin.php?page=chps-stripe&notice=stripe-disabled'));
            exit;
        }

        $price_id = $tier === 'corporate'
            ? get_option('chps_stripe_corporate_price_id', '')
            : get_option('chps_stripe_pro_price_id', '');

        if (empty($price_id) || empty(get_option('chps_stripe_secret_key', ''))) {
            wp_redirect(admin_url('admin.php?page=chps-stripe&notice=missing-config'));
            exit;
        }

        $checkout_url = $this->create_checkout_session($tier, $price_id);
        if ($checkout_url) {
            wp_redirect($checkout_url);
            exit;
        }

        wp_redirect(admin_url('admin.php?page=chps-stripe&notice=checkout-failed'));
        exit;
    }

    public function register_webhook_route() {
        register_rest_route('chps/v1', '/stripe-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_webhook($request) {
        $payload = $request->get_body();
        $signature = $request->get_header('stripe-signature');
        $webhook_secret = get_option('chps_stripe_webhook_secret', '');

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

        $this->process_event($event);
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

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        return hash_equals($expected, $signed);
    }

    private function create_checkout_session($tier, $price_id) {
        $secret_key = get_option('chps_stripe_secret_key', '');
        $success_url = get_option('chps_stripe_success_url', home_url('/'));
        $cancel_url = get_option('chps_stripe_cancel_url', home_url('/'));
        $admin_email = get_option('admin_email', '');

        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'mode' => 'subscription',
                'line_items[0][price]' => $price_id,
                'line_items[0][quantity]' => 1,
                'success_url' => $success_url . '?chps_stripe=success',
                'cancel_url' => $cancel_url . '?chps_stripe=cancel',
                'customer_email' => $admin_email,
                'metadata[tier]' => $tier,
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

    private function process_event($event) {
        switch ($event['type']) {
            case 'checkout.session.completed':
                $object = $event['data']['object'] ?? array();
                $tier = $object['metadata']['tier'] ?? 'pro';
                $this->activate_license($tier, 'active', $object['id'] ?? '');
                break;

            case 'invoice.paid':
                $object = $event['data']['object'] ?? array();
                $tier = $object['metadata']['tier'] ?? 'pro';
                $this->activate_license($tier, 'active', $object['id'] ?? '');
                break;

            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $object = $event['data']['object'] ?? array();
                $status = $object['status'] ?? 'inactive';
                if (in_array($status, array('active', 'trialing'), true)) {
                    $tier = $object['metadata']['tier'] ?? 'pro';
                    $this->activate_license($tier, $status, $object['id'] ?? '');
                } else {
                    $this->deactivate_license($object['id'] ?? '');
                }
                break;

            default:
                break;
        }
    }

    private function activate_license($tier, $status, $event_id) {
        if (!in_array($tier, array('pro', 'corporate'), true)) {
            $tier = 'pro';
        }

        $license = CHPS_License::instance();
        $license->activate_license($tier, 'stripe-' . substr(md5($event_id . time()), 0, 16), $status);

        update_option('chps_stripe_status', sanitize_text_field($status));
        update_option('chps_stripe_last_event', sanitize_text_field($event_id));
    }

    private function deactivate_license($event_id) {
        $license = CHPS_License::instance();
        $license->deactivate_license();

        update_option('chps_stripe_status', 'canceled');
        update_option('chps_stripe_last_event', sanitize_text_field($event_id));
    }
}
