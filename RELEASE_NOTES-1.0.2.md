CuredHosting Plugin Suite — Release Notes 1.0.2

Release date: 2026-07-17

Summary
- Security and stability release focused on secrets handling, logging safety, and license enforcement for paid tiers.

Key changes
- Link audit: enforce effective paid tier checks before performing deeper/corporate scans; prevents unpaid access to corporate scans.
- Stripe integration:
  - Admin forms no longer echo secret or webhook keys back to the UI.
  - Admins can set `CHPS_STRIPE_SECRET_KEY` / `CHPS_STRIPE_WEBHOOK_SECRET` in `wp-config.php` as a constant fallback.
  - Webhook and API-response logging reduced to avoid recording payloads, signatures, or full response bodies.
- Settings & Setup:
  - Setup wizard now sets a transient guard to prevent rapid repeated runs and migrates sensitive options to `autoload = 'no'` to avoid preloading secrets.
  - Settings save handlers preserve existing secret values when the form field is left empty.
- Logging & UX:
  - Error log test button added (safe write example).
  - Admin notices and confirmations added to reduce accidental operations.

Why this matters
- These changes reduce the risk that secret API keys or sensitive webhook payloads are stored in logs or autoloaded in the DB, improving security for customers who run the plugin on shared or production servers.

Files changed (high level)
- includes/class-chps-stripe.php — secret/ui/sanitization, logging changes, constant fallbacks
- modules/stripe-payment-module/includes/class-stripe-payment-module.php — same changes for module
- includes/class-chps-settings.php — preserve license key handling
- includes/class-chps-setup-wizard.php — transient guard + autoload migration helper
- includes/class-chps-license.php — reduced logging of remote responses
- modules/link-audit-module/link-audit-module.php — enforce tier for scans
- build.ps1, CHANGELOG.md, release-notes-template.html — build & packaging updates

Upgrade notes
- Please follow the `UPGRADE_GUIDE.md` for required actions and verification steps.

Contact
- For questions or help upgrading, contact support@yourdomain.example
