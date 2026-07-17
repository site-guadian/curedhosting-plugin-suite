Upgrade Guide — 1.0.2

Purpose
These steps document how to upgrade to 1.0.2 and verify the secrets hardening changes so site admins and buyers can be confident.

Before you begin
- Backup your site and database.
- Ensure you have access to `wp-config.php` and a database admin account.

1) Install the plugin update
- Upload and activate the new plugin zip as usual.

2) Verify secret handling behavior
- Go to the Stripe settings page (CuredHosting → Stripe Billing).
- If you previously had a secret key saved, the secret input will appear empty but a short note will say "A secret key is saved (hidden). Leave blank to keep.". Do NOT paste keys into the field if you want to keep the current stored secret.
- To rotate a secret, paste the new key into the field and click Save.

3) Prefer constants for production
- For higher security, set the following in `wp-config.php` (do not commit these to source control):

```php
// Example (add above the "That's all, stop editing!" line)
define('CHPS_STRIPE_SECRET_KEY', 'sk_live_xxx');
define('CHPS_STRIPE_WEBHOOK_SECRET', 'whsec_xxx');
```

- When these constants are present, the plugin uses them instead of reading the secret from the DB.

4) Verify autoload migration
- After setup or saving settings, the setup wizard runs a helper that attempts to set `autoload='no'` for known sensitive options.
- To confirm manually (DB):
  - Inspect `wp_options` and check `autoload` column for keys: `chps_stripe_secret_key`, `chps_stripe_webhook_secret`, `chps_license_secret`, `spm_secret_key`, `spm_webhook_secret`.
  - They should be set to `no`.

5) Verify logging does not contain secrets
- If debug logging is enabled (`CuredHosting → Settings`), open the Error Log admin page and confirm there are no webhook payloads, signatures, or full remote response bodies recorded for Stripe or license checks.
- The log will still contain short, non-sensitive messages indicating failures or states (intentionally limited).

6) Rotate keys if you suspect exposure
- If you previously logged keys or response bodies, rotate the secrets at the provider (Stripe) and update the plugin settings or define constants in `wp-config.php`.

7) Admin verification checklist
- Use `ADMIN_VERIFICATION_CHECKLIST.md` for a step-by-step checklist.

Need help?
- Contact support@yourdomain.example for guided upgrade assistance.
