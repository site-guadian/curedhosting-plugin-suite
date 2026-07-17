<?php
if (!defined('ABSPATH')) {
    exit;
}

class CHPS_License {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('chps_can_access', array($this, 'check_access'), 10, 2);
        add_action('init', array($this, 'ensure_license_table'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_post_chps_activate_license', array($this, 'handle_activate'));
        add_action('admin_post_chps_deactivate_license', array($this, 'handle_deactivate'));
        add_action('admin_post_chps_issue_license', array($this, 'handle_issue'));
        add_action('admin_notices', array($this, 'show_license_notice'));
    }

    public function check_access($default, $feature) {
        $tier = get_option('chps_tier', 'free');
        $license_status = get_option('chps_license_status', 'inactive');

        $pro_features = array('bulk_tools', 'advanced_reports');
        $corporate_features = array('bulk_tools', 'advanced_reports', 'unlimited_everything');

        if ($tier === 'corporate' || $license_status === 'active' && $tier === 'corporate') {
            return true;
        }

        if ($tier === 'pro' && in_array($feature, $pro_features, true)) {
            return true;
        }

        if ($license_status === 'active' && $feature === 'bulk_tools') {
            return true;
        }

        return $default;
    }

    public function show_license_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only show on CuredHosting pages or settings pages
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (empty($page) || (strpos($page, 'chps') === false && strpos($page, 'options-general') === false)) {
            return;
        }

        $notice = isset($_GET['chps_notice']) ? sanitize_text_field(wp_unslash($_GET['chps_notice'])) : '';

        if ($notice === 'activated') {
            echo '<div class="notice notice-success"><p>License activated. Pro and Corporate features are now available.</p></div>';
            return;
        }

        if ($notice === 'deactivated') {
            echo '<div class="notice notice-info"><p>License deactivated. The suite has been moved back to the free tier.</p></div>';
            return;
        }

        if ($notice === 'invalid') {
            echo '<div class="notice notice-error"><p>The license key could not be validated. Try a key such as PRO-DEMO-2026 or CORP-DEMO-2026.</p></div>';
            return;
        }

        if ($notice === 'issued') {
            echo '<div class="notice notice-success"><p>License issued successfully. The customer can activate it using the generated key.</p></div>';
        }
    }

    public function register_rest_routes() {
        register_rest_route('chps/v1', '/license-check', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_rest_license_check'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_rest_license_check($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }

        $shared_secret = sanitize_text_field($body['secret'] ?? '');
        $expected_secret = get_option('chps_license_secret', '');
        if ($shared_secret === '' || !hash_equals($expected_secret, $shared_secret)) {
            return new WP_REST_Response(array('valid' => false, 'error' => 'invalid_secret'), 403);
        }

        $license_key = sanitize_text_field($body['license_key'] ?? '');
        $customer_email = sanitize_email($body['customer_email'] ?? '');
        $requested_tier = sanitize_text_field($body['requested_tier'] ?? 'free');

        $db_validation = $this->validate_license_against_database($customer_email, $license_key, $requested_tier);
        if ($db_validation['valid']) {
            return new WP_REST_Response(array('valid' => true, 'tier' => $db_validation['tier']), 200);
        }

        $fallback = $this->validate_license_key($license_key, $requested_tier);
        if ($fallback['valid']) {
            return new WP_REST_Response(array('valid' => true, 'tier' => $fallback['tier']), 200);
        }

        return new WP_REST_Response(array('valid' => false), 200);
    }

    public function handle_activate() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('chps_activate_license');

        $license_key = sanitize_text_field(wp_unslash($_POST['chps_license_key'] ?? ''));
        $customer_email = sanitize_email(wp_unslash($_POST['chps_customer_email'] ?? ''));
        $requested_tier = sanitize_text_field(wp_unslash($_POST['chps_license_tier'] ?? 'free'));

        if ($customer_email !== '' && $license_key !== '') {
            $db_validation = $this->validate_license_against_database($customer_email, $license_key, $requested_tier);
            if ($db_validation['valid']) {
                $this->activate_license($db_validation['tier'], $license_key, 'active');
                wp_redirect(admin_url('admin.php?page=chps-settings&chps_notice=activated'));
                exit;
            }
        }

        $validation = $this->validate_license_key($license_key, $requested_tier);

        if ($validation['valid']) {
            $this->activate_license($validation['tier'], $license_key, 'active');
            wp_redirect(admin_url('admin.php?page=chps-settings&chps_notice=activated'));
            exit;
        }

        update_option('chps_license_key', $license_key);
        update_option('chps_tier', 'free');
        update_option('chps_license_status', 'invalid');
        wp_redirect(admin_url('admin.php?page=chps-settings&chps_notice=invalid'));
        exit;
    }

    public function handle_deactivate() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('chps_deactivate_license');

        $this->deactivate_license();
        wp_redirect(admin_url('admin.php?page=chps-settings&chps_notice=deactivated'));
        exit;
    }

    public function handle_issue() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('chps_issue_license');

        $email = sanitize_email(wp_unslash($_POST['chps_customer_email'] ?? ''));
        $tier = sanitize_text_field(wp_unslash($_POST['chps_issue_tier'] ?? 'pro'));
        $activate = isset($_POST['chps_activate_immediately']) ? 1 : 0;

        if (!is_email($email)) {
            wp_redirect(admin_url('admin.php?page=chps-settings&chps_notice=invalid'));
            exit;
        }

        $license_key = $this->generate_license_key($email, $tier);
        $this->store_license($email, $license_key, $tier);

        if ($activate) {
            $this->activate_license($tier, $license_key, 'active');
        }

        update_option('chps_last_issued_license', $license_key);
        update_option('chps_last_issued_email', $email);
        wp_redirect(admin_url('admin.php?page=chps-settings&chps_notice=issued'));
        exit;
    }

    public function activate_license($tier, $license_key = '', $status = 'active') {
        $safe_tier = in_array($tier, array('pro', 'corporate'), true) ? $tier : 'free';
        $safe_key = $license_key !== '' ? sanitize_text_field($license_key) : 'stripe-' . substr(md5($safe_tier . current_time('timestamp')), 0, 16);

        update_option('chps_tier', $safe_tier);
        update_option('chps_license_status', sanitize_text_field($status));
        update_option('chps_license_key', $safe_key);
        update_option('chps_license_last_checked', current_time('mysql'));
    }

    public function deactivate_license() {
        update_option('chps_tier', 'free');
        update_option('chps_license_status', 'inactive');
        update_option('chps_license_key', '');
        update_option('chps_license_last_checked', current_time('mysql'));
    }

    public function ensure_license_table() {
        global $wpdb;

        $table_name = $this->get_licenses_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
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

    private function get_licenses_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'chps_licenses';
    }

    private function generate_license_key($email, $tier) {
        $prefix = $tier === 'corporate' ? 'CORP' : 'PRO';
        $hash = substr(hash('sha256', $email . ':' . $prefix . ':' . wp_salt('auth')), 0, 16);
        return strtoupper($prefix . '-' . $hash);
    }

    private function store_license($email, $license_key, $tier) {
        global $wpdb;

        $this->ensure_license_table();
        $table_name = $this->get_licenses_table_name();

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'email' => sanitize_email($email),
                'license_key' => sanitize_text_field($license_key),
                'tier' => sanitize_text_field($tier),
                'status' => 'active',
                'issued_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted !== false) {
            $licenses = get_option('chps_issued_licenses', array());
            $licenses[$license_key] = array(
                'email' => sanitize_email($email),
                'tier' => sanitize_text_field($tier),
                'status' => 'active',
                'issued_at' => current_time('mysql'),
            );
            update_option('chps_issued_licenses', $licenses);
        }
    }

    private function validate_license_against_database($email, $license_key, $requested_tier) {
        global $wpdb;

        $this->ensure_license_table();
        $table_name = $this->get_licenses_table_name();
        $email = sanitize_email($email);
        $license_key = sanitize_text_field($license_key);

        if ($email === '' || $license_key === '') {
            return array('valid' => false);
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE email = %s AND license_key = %s LIMIT 1",
            $email,
            $license_key
        ), ARRAY_A);

        if (empty($row)) {
            return array('valid' => false);
        }

        if (($row['status'] ?? 'inactive') !== 'active') {
            return array('valid' => false);
        }

        if ($requested_tier !== 'free' && !in_array($row['tier'] ?? 'free', array($requested_tier, 'corporate'), true)) {
            return array('valid' => false);
        }

        return array('valid' => true, 'tier' => $row['tier']);
    }

    private function validate_license_key($license_key, $requested_tier) {
        $normalized = strtoupper(trim($license_key));

        if ($normalized === '') {
            return array('valid' => false);
        }

        $remote_response = $this->validate_against_remote_endpoint($normalized, $requested_tier);
        if ($remote_response['valid']) {
            return array('valid' => true, 'tier' => $remote_response['tier']);
        }

        $detected_tier = $this->detect_license_tier($normalized);
        if ($detected_tier === false) {
            return array('valid' => false);
        }

        if ($requested_tier !== 'free' && $requested_tier !== $detected_tier) {
            return array('valid' => false);
        }

        if ($requested_tier === 'free' && $detected_tier !== 'free') {
            return array('valid' => false);
        }

        return array('valid' => true, 'tier' => $detected_tier);
    }

    private function validate_against_remote_endpoint($license_key, $requested_tier) {
        $endpoint = get_option('chps_license_endpoint', '');
        $secret = get_option('chps_license_secret', '');
        if ($endpoint === '' || $secret === '') {
            return array('valid' => false);
        }

        $payload = array(
            'secret' => $secret,
            'license_key' => $license_key,
            'requested_tier' => $requested_tier,
            'customer_email' => $email,
        );

        $response = wp_remote_post($endpoint, array(
            'timeout' => 10,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            chps_log_error('License remote validation request failed', array('endpoint' => $endpoint, 'error' => $response->get_error_message()));
            return array('valid' => false);
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            chps_log_error('License remote validation returned HTTP error', array('endpoint' => $endpoint, 'code' => $code, 'body' => substr(wp_remote_retrieve_body($response), 0, 1000)));
            return array('valid' => false);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['valid'])) {
            chps_log_error('License remote validation returned invalid payload', array('endpoint' => $endpoint, 'body' => substr($body, 0, 1000)));
            return array('valid' => false);
        }

        return array('valid' => true, 'tier' => sanitize_text_field($data['tier'] ?? 'free'));
    }

    private function detect_license_tier($license_key) {
        if (strpos($license_key, 'CORP') !== false || strpos($license_key, 'ENTERPRISE') !== false) {
            return 'corporate';
        }

        if (strpos($license_key, 'PRO') !== false || strpos($license_key, 'PLUS') !== false) {
            return 'pro';
        }

        if (strpos($license_key, 'FREE') !== false) {
            return 'free';
        }

        return false;
    }
}
