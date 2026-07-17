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

    public function sanitize_chps_stripe_secret_key($value) {
        $val = sanitize_text_field(wp_unslash($value));
        if (trim($val) === '') {
            // preserve existing secret if user left the field empty
            return get_option('chps_stripe_secret_key', '');
        }
        return $val;
    }

    public function sanitize_chps_stripe_webhook_secret($value) {
        $val = sanitize_text_field(wp_unslash($value));
        if (trim($val) === '') {
            return get_option('chps_stripe_webhook_secret', '');
        }
        return $val;
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
        register_setting('chps_stripe_group', 'chps_stripe_secret_key', array($this, 'sanitize_chps_stripe_secret_key'));
        register_setting('chps_stripe_group', 'chps_stripe_publishable_key');
        register_setting('chps_stripe_group', 'chps_stripe_webhook_secret', array($this, 'sanitize_chps_stripe_webhook_secret'));
        register_setting('chps_stripe_group', 'chps_stripe_pro_price_id');
        register_setting('chps_stripe_group', 'chps_stripe_corporate_price_id');
        register_setting('chps_stripe_group', 'chps_stripe_success_url');
        register_setting('chps_stripe_group', 'chps_stripe_cancel_url');
    }

    public function render_page() {
        $enabled = get_option('chps_stripe_enabled', 0);
        // Do not echo secrets back into the form. Show empty inputs and only update when a non-empty value is submitted.
        $secret_key = '';
        $publishable_key = get_option('chps_stripe_publishable_key', '');
        $webhook_secret = '';
        $has_secret = !empty(get_option('chps_stripe_secret_key', ''));
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
                        <td>
                            <input type="password" name="chps_stripe_secret_key" value="" class="regular-text" placeholder="Leave empty to keep existing" />
                            <?php if ($has_secret) : ?>
                                <p class="description">A secret key is saved (hidden). Leave blank to keep.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Publishable Key</th>
                        <td><input type="text" name="chps_stripe_publishable_key" value="<?php echo esc_attr($publishable_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook Secret</th>
                        <td>
                            <input type="password" name="chps_stripe_webhook_secret" value="" class="regular-text" placeholder="Leave empty to keep existing" />
                            <?php if (!empty(get_option('chps_stripe_webhook_secret', ''))) : ?>
                                <p class="description">A webhook secret is saved (hidden). Leave blank to keep.</p>
                            <?php endif; ?>
                        </td>
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

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page !== 'chps-stripe') {
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
        $webhook_secret = defined('CHPS_STRIPE_WEBHOOK_SECRET') ? CHPS_STRIPE_WEBHOOK_SECRET : get_option('chps_stripe_webhook_secret', '');

        if (empty($webhook_secret) || empty($signature)) {
            chps_log_error('Stripe webhook missing configuration or signature', array('webhook_secret_present' => !empty($webhook_secret), 'signature_present' => !empty($signature)));
            return new WP_REST_Response(array('error' => 'Missing webhook configuration'), 400);
        }

        if (!$this->verify_signature($payload, $signature, $webhook_secret)) {
            chps_log_error('Stripe webhook signature verification failed', array('signature_present' => !empty($signature)));
            return new WP_REST_Response(array('error' => 'Invalid signature'), 401);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            chps_log_error('Stripe webhook payload invalid JSON or missing type', array());
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

        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        return hash_equals($expected, $signed);
    }

    private function create_checkout_session($tier, $price_id) {
        $secret_key = defined('CHPS_STRIPE_SECRET_KEY') ? CHPS_STRIPE_SECRET_KEY : get_option('chps_stripe_secret_key', '');
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
                'subscription_data[metadata][tier]' => $tier,
                'allow_promotion_codes' => 'true',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            chps_log_error('Stripe create_checkout_session: wp_remote_post error', array('error' => $response->get_error_message()));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['url'])) {
            chps_log_error('Stripe create_checkout_session: missing url in response', array('response_code' => wp_remote_retrieve_response_code($response)));
            return false;
        }

        return $data['url'];
    }

    private function process_event($event) {
        switch ($event['type']) {
            case 'checkout.session.completed':
            case 'invoice.paid':
                $object = $event['data']['object'] ?? array();
                $tier = $this->get_tier_from_event($event);
                $this->activate_license($tier, 'active', $object['id'] ?? '');
                break;

            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $object = $event['data']['object'] ?? array();
                $status = $object['status'] ?? 'inactive';
                if (in_array($status, array('active', 'trialing'), true)) {
                    $tier = $this->get_tier_from_event($event);
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

    private function get_tier_from_event($event) {
        $object = $event['data']['object'] ?? array();

        if (!empty($object['metadata']['tier'])) {
            $tier = sanitize_text_field($object['metadata']['tier']);
            if (in_array($tier, array('pro', 'corporate'), true)) {
                return $tier;
            }
        }

        if (!empty($object['subscription']) && is_array($object['subscription']) && !empty($object['subscription']['metadata']['tier'])) {
            $tier = sanitize_text_field($object['subscription']['metadata']['tier']);
            if (in_array($tier, array('pro', 'corporate'), true)) {
                return $tier;
            }
        }

        if (!empty($object['lines']['data'][0]['price']['id'])) {
            return $this->get_tier_from_price_id($object['lines']['data'][0]['price']['id']);
        }

        if (!empty($object['price']['id'])) {
            return $this->get_tier_from_price_id($object['price']['id']);
        }

        if (!empty($event['data']['object']['subscription']) && is_string($event['data']['object']['subscription'])) {
            return $this->get_tier_from_price_id($event['data']['object']['subscription']);
        }

        return 'pro';
    }

    private function get_tier_from_price_id($price_id) {
        $pro_price_id = get_option('chps_stripe_pro_price_id', '');
        $corporate_price_id = get_option('chps_stripe_corporate_price_id', '');

        if ($price_id === $corporate_price_id) {
            return 'corporate';
        }

        if ($price_id === $pro_price_id) {
            return 'pro';
        }

        if (strpos($price_id, 'corp') !== false || strpos($price_id, 'CORP') !== false) {
            return 'corporate';
        }

        return 'pro';
    }

    private function deactivate_license($event_id) {
        $license = CHPS_License::instance();
        $license->deactivate_license();

        update_option('chps_stripe_status', 'canceled');
        update_option('chps_stripe_last_event', sanitize_text_field($event_id));
    }
}
