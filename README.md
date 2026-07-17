
# Cured Hosting Plugin Suite

The Cured Hosting Plugin Suite provides a set of WordPress plugins and modules for hosting businesses: an admin settings UI, license management and validation, a guided setup wizard, and Stripe payment integration. It's designed to be drop-in for WordPress sites and extendable by developers.

Short description: Cured Hosting Plugin Suite — WordPress admin tools, license management, setup wizard, and Stripe payments.

Features
-
- Admin UI and settings pages
- License activation, validation, and auto-renew hooks
- Setup wizard for first-time install and configuration
- Stripe integration for one-time and subscription payments
- Modular structure for adding new modules (cookie consent, link audit, server tools, etc.)

Quick start
-
1. Copy the `curedhosting-plugin-suite` folder into your WordPress `wp-content/plugins/` directory.
2. Activate the plugin from the WordPress admin Plugins screen.
3. Visit the plugin's settings panel to run the setup wizard and configure license and Stripe settings.

Development notes
-
- Core classes live in `includes/` (admin, license, settings, setup wizard, Stripe integration).
- Additional modules are placed under `othwer/completed-plugins/` and other feature folders.

Suggested repository topics: WordPress, plugin, PHP, Stripe, licensing, hosting, onboarding, admin

See also: `othwer/README.html` for historical notes and module-specific docs.

## Standalone Key Maker

A standalone key maker app lives in `key-maker/`.
It can generate license keys outside WordPress using a simple CLI:

```bash
php key-maker/generate-key.php --email="customer@example.com" --tier=pro
```

Use `--secret` to provide an optional shared secret for reproducible key generation.

## Plugin Suite File Structure

```
curedhosting-plugin-suite/
├── .git/
├── .gitignore
├── README.md
├── curedhosting-plugin-suite.php
├── includes/
│   ├── class-chps-admin.php
│   ├── class-chps-license.php
│   ├── class-chps-settings.php
│   ├── class-chps-setup-wizard.php
│   └── class-chps-stripe.php
└── modules/
    ├── cookie-consent-module/
    │   ├── assets/
    │   │   └── cookie-consent.js
    │   └── cookie-consent-module.php
    ├── link-audit-module/
    │   └── link-audit-module.php
    ├── stripe-payment-module/
    │   ├── includes/
    │   │   └── class-stripe-payment-module.php
    │   ├── README.txt
    │   └── stripe-payment-module.php
    ├── wp-server-guardian/
    │   └── wp-server-guardian.php
    └── wp-speed-autopilot/
        └── wp-speed-autopilot.php
```
