# Installing the Freemium Suite and Adding Paid Modules

This file explains how to install the freemium package, enable modules, and install paid modules when purchased.

1) What this package contains
- The core plugin `curedhosting-plugin-suite` and the free modules (including `wp-server-guardian` and `wp-speed-autopilot`).
- Paid modules (for example `key-maker` and `modules/stripe-payment-module`) are not included in this archive.

2) Install the freemium plugin
- From WordPress admin: Plugins → Add New → Upload Plugin → choose `curedhosting-plugin-suite-freemium-<version>.zip` and click Install Now.
- After install: Plugins → Activate the `CuredHosting Plugin Suite` plugin.

> **Warning:** Before making any changes or installing new modules, save your current plugin configuration and license settings. This helps avoid losing custom settings if you switch packages or update modules.

3) Configure the plugin
- Visit the plugin settings page (left admin menu `CuredHosting` or similar) to run the setup wizard and configure license and Stripe settings.
- Enable/disable individual modules from the plugin settings. Modules map to folders under `modules/`.

4) Installing paid modules (after purchase)
- You will receive a zip containing the paid module(s) and any purchase/license information.
- Option A — Upload: Plugins → Add New → Upload Plugin → choose the paid module zip → Install → Activate.
- Option B — Manual: Copy the paid module folder into `wp-content/plugins/` then activate it in WordPress Plugins screen.
- After activation, enter your license code at `CuredHosting → License` if required.

5) Debugging & Error Log
- Debug logging is off by default. To enable: open plugin settings and enable `chps_debug_enabled` (Debug Mode).
- Error log path: `wp-content/uploads/chps-error.log` (viewable from the plugin's Error Log admin page).

6) Support
- If you encounter problems installing or activating paid modules, provide:
  - Plugin version (from `curedhosting-plugin-suite.php` `CHPS_VERSION`)
  - A copy of recent lines from `wp-content/uploads/chps-error.log` (if debug enabled)
- Send support requests to support@yourdomain.example (replace with your contact email).

Thanks — enjoy the freemium suite. If you'd like, I can also generate a separate `paid-only` zip with all paid modules for distribution to customers.
