Admin Verification Checklist — 1.0.2

Follow these steps to verify the update and confirm secrets are protected.

- [ ] Backup DB and files
- [ ] Install plugin update (activate)
- [ ] Open CuredHosting → Stripe Billing
  - [ ] Secret and webhook fields show empty inputs with "Leave blank to keep existing" description if previously set
  - [ ] If constants are used, confirm in `wp-config.php` the `CHPS_STRIPE_SECRET_KEY` or `CHPS_STRIPE_WEBHOOK_SECRET` values exist
- [ ] Run setup wizard once if not run already (it will migrate sensitive options to `autoload = 'no'`)
- [ ] Check `wp_options` for these keys and confirm `autoload` is `no`:
  - `chps_stripe_secret_key`
  - `chps_stripe_webhook_secret`
  - `chps_license_secret`
  - `spm_secret_key`
  - `spm_webhook_secret`
- [ ] Enable debug logging temporarily, trigger any webhook or test actions, then review `wp-content/uploads/chps-error.log` to confirm payloads/signatures/response bodies are not present
- [ ] If any secret-like strings are found in logs, rotate secrets immediately
- [ ] Optional: define secrets in `wp-config.php` and remove DB-stored secret values

Notes
- The plugin intentionally preserves existing DB secret values when a settings form is saved with empty secret fields. To replace a secret, enter a new value in the settings page and save.
- For maximum security, prefer `wp-config.php` constants or external secret management systems.
