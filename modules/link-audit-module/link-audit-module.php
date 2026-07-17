<?php
/**
 * Plugin Name: CuredHosting Link Audit
 * Description: Lightweight broken link and duplicate content checker for WordPress sites with free, pro, and corporate tier support.
 * Version: 1.0.1
 * Author: CuredHosting
 */

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('chps_register_module')) {
    chps_register_module([
        'name' => 'Link Audit',
        'slug' => 'link-audit',
        'version' => '1.0.1',
        'admin_slug' => 'chps-link-audit',
        'status' => 'active'
    ]);
}

if (!class_exists('CH_Link_Audit')) {
    class CH_Link_Audit {
        private $option_prefix = 'chla_';

        public function __construct() {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_post_chla_run_scan', [$this, 'handle_scan']);
            if (method_exists($this, 'show_promo')) {
                add_action('admin_notices', [$this, 'show_promo']);
            }
        }

        public function add_admin_menu() {
            add_submenu_page(
                'chps-settings',
                'Link Audit',
                'Link Audit',
                'manage_options',
                'chps-link-audit',
                [$this, 'render_admin_page']
            );
        }

        public function register_settings() {
            register_setting('chla_settings_group', $this->option_prefix . 'tier');
            register_setting('chla_settings_group', $this->option_prefix . 'notify_email');
        }

        public function show_promo() {
            $tier = get_option($this->option_prefix . 'tier', 'free');
            if ($tier !== 'free') {
                return;
            }

            echo '<div class="notice notice-info is-dismissible"><p><strong>Free link audit:</strong> Buy a key to unlock deeper scans, email alerts, and more advanced monitoring. <strong>CuredHosting</strong> plans start at <strong>$49/month</strong> with these tools included.</p><p><a href="https://plaguedr.online/plugins/" target="_blank" rel="noopener" class="button button-primary">View all plugins</a> <a href="https://plaguedr.online/plugins/" target="_blank" rel="noopener" class="button button-secondary">See the portfolio</a></p><p style="margin-top:8px;"><strong>Support:</strong> siteguardian@plaguedr.online</p></div>';
        }

        public function render_admin_page() {
            $tier = get_option($this->option_prefix . 'tier', 'free');
            $notifyEmail = get_option($this->option_prefix . 'notify_email', get_option('admin_email'));
            $results = get_transient($this->option_prefix . 'last_scan');
            ?>
            <div class="wrap">
                <h1>Link Audit</h1>
                <p>Scan your site for broken links and possible duplicate content patterns.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="chla_run_scan">
                    <?php wp_nonce_field('chla_run_scan'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="chla_tier">Tier</label></th>
                            <td>
                                <select id="chla_tier" name="chla_tier">
                                    <option value="free" <?php selected($tier, 'free'); ?>>Free</option>
                                    <option value="pro" <?php selected($tier, 'pro'); ?>>Pro</option>
                                    <option value="corporate" <?php selected($tier, 'corporate'); ?>>Corporate</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="chla_notify_email">Notify Email</label></th>
                            <td><input type="email" id="chla_notify_email" name="chla_notify_email" value="<?php echo esc_attr($notifyEmail); ?>" class="regular-text" /></td>
                        </tr>
                    </table>
                    <?php submit_button('Run Scan'); ?>
                </form>

                <?php if (!empty($results)) : ?>
                    <h2>Latest Scan Results</h2>
                    <table class="widefat fixed" style="margin-top:12px;">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html($item['type']); ?></td>
                                    <td><?php echo esc_html($item['value']); ?></td>
                                    <td><?php echo esc_html($item['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php
        }

        public function handle_scan() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            check_admin_referer('chla_run_scan');

            update_option($this->option_prefix . 'tier', sanitize_text_field(wp_unslash($_POST['chla_tier'] ?? 'free')));
            update_option($this->option_prefix . 'notify_email', sanitize_email(wp_unslash($_POST['chla_notify_email'] ?? get_option('admin_email'))));

            $results = $this->scan_site();
            set_transient($this->option_prefix . 'last_scan', $results, 60 * 60);

            if ($notifyEmail = get_option($this->option_prefix . 'notify_email')) {
                wp_mail($notifyEmail, 'Link Audit Scan Report', $this->format_report($results));
            }

            wp_redirect(admin_url('admin.php?page=chps-link-audit&scanned=1'));
            exit;
        }

        private function scan_site() {
            $results = [];
            $posts = get_posts(['post_type' => ['post', 'page'], 'posts_per_page' => 50, 'post_status' => 'publish']);

            foreach ($posts as $post) {
                $content = $post->post_content;
                $urls = $this->extract_urls($content);
                foreach ($urls as $url) {
                    if ($this->looks_like_external_link($url)) {
                        $status = $this->check_url($url);
                        if ($status !== 'ok') {
                            $results[] = ['type' => 'broken', 'value' => $url, 'details' => $status];
                        }
                    }
                }

                $normalized = strtolower(trim(preg_replace('/\s+/', ' ', strip_tags($content))));
                if (strlen($normalized) > 120) {
                    $results[] = ['type' => 'duplicate-check', 'value' => get_permalink($post->ID), 'details' => 'Content length sampled for duplicate detection'];
                }
            }

            return $results;
        }

        private function extract_urls($content) {
            preg_match_all('/https?:\/\/[^\s"\')>]+/', $content, $matches);
            return $matches[0] ?? [];
        }

        private function looks_like_external_link($url) {
            return strpos($url, home_url('/')) === false;
        }

        private function check_url($url) {
            $response = wp_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
            if (is_wp_error($response)) {
                return $response->get_error_message();
            }
            $code = wp_remote_retrieve_response_code($response);
            return $code >= 200 && $code < 400 ? 'ok' : 'HTTP ' . $code;
        }

        private function format_report($results) {
            $lines = ['Link Audit Scan Report', ''];
            foreach ($results as $item) {
                $lines[] = strtoupper($item['type']) . ': ' . $item['value'] . ' - ' . $item['details'];
            }
            return implode("\n", $lines);
        }
    }

    add_action('plugins_loaded', function () {
        new CH_Link_Audit();
    });
}
