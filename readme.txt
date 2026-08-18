=== Advanced Passkeys for Secure Login ===
Contributors: wppasskey, mbuiux
Tags: passkeys, webauthn, passwordless, login, security
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.1.13
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add secure passwordless passkey login with Face ID, Touch ID, Windows Hello, and hardware keys.

== Description ==

Passwords are the single biggest security liability for your WordPress site. They get leaked, reused, and exploited by automated brute-force attacks every single day. While standard Two-Factor Authentication (2FA) adds a layer of safety, typing in temporary codes from SMS or authenticator apps introduces annoying workflow friction that hurts user adoption and slows down your day.

**Advanced Passkeys for Secure Login** brings the future of un-phishable, lightning-fast authentication directly to WordPress using the official FIDO2 / WebAuthn standard.

Give your users a modern login experience. Users register a secure passkey just once using their device's built-in biometric sensor (Face ID, Touch ID, Windows Hello), device PIN, or a hardware security key (like a YubiKey). Future sign-ins can bypass traditional password fields and reduce credential-theft risk.

=== Out-of-the-Box WooCommerce & Membership Integrations ===
Don't settle for basic alternatives that only support the default backend login page. Advanced Passkeys features intelligent, dependency-aware integration modules that automatically inject secure passkey entry points into your existing platform ecosystem. Experience seamless, zero-configuration support for:
* **WooCommerce** (Secure checkout & customer account login)
* **MemberPress** & **Paid Memberships Pro (PMPro)** (Frictionless member portal protection)
* **LearnDash** (Instant student sign-in to prevent account sharing)
* **Ultimate Member** & **BuddyBoss** (Biometric profiles for modern communities)
* **Gravity Forms** (Protected frontend user-registration flows)

=== Why Smart Site Owners Choose Passkeys ===
* **Strong Phishing Resistance:** Passkeys are cryptographically bound to your specific domain name. A fake login page or copycat site cannot reuse a passkey issued for your real site.
* **Reduce Brute-Force Risk:** Passkey login does not rely on a static password being typed into the login form, reducing exposure to automated password-guessing attacks.
* **Painless Cross-Device Sync:** Works natively with iCloud Keychain, Google Password Manager, and 1Password for reliable, frictionless cross-device access across desktop, mobile, and tablet.

== Features ==

* **One-Click Passwordless Authentication:** Adds a native, highly visible "Sign in with Passkey" button directly to your core WordPress login screens.
* **Conditional UI Autofill Support:** Offers optional browser-native passkey autofill prompts when a user focuses on the username field for an incredibly polished user experience.
* **Ecosystem-Aware Gutenberg Blocks & Shortcodes:** Automatically registers matching frontend login cards and components specifically tailored to your active third-party plugins.
* **High-Yield Admin Nudge Notices:** Turn on built-in dashboard reminders that gently guide eligible users to secure their accounts with a passkey without administrator intervention.
* **Granular Role-Based Security Policies:** Configure exactly which user roles are permitted to use biometric authentication (Default: Administrators-only out of the box).
* **Developer-Managed Overrides:** Manage passkey settings in PHP using the centralized `advapafo_local_configuration` filter or the `ADVAPAFO_SETTINGS` constant.
* **Theme Template Customization:** Seamlessly match your active brand by overriding the login button template layout via `/advanced-passkeys/login/button.php` inside your child theme.
* **Advanced Analytics Dashboard:** Track credential performance over time with a live Authenticator Overview breakdown card and a Last Login audit trail log.
* **Hardened Brute-Force Rate Limiting:** Enforce strict local connection limits to log and block malicious behavior, backed by automated daily cleanup crons to keep your database lean.
* **Multisite Network Provisioning:** Network-aware architecture instantly partitions tables dynamically and inherits security guardrails across newly deployed network sites.
* **Clean Housekeeping Routine:** Implements a strict, responsible uninstall function that leaves behind absolutely zero orphaned database tables or leftover configuration choices.

== Installation ==

= Automatic installation =

1. In your WordPress dashboard, navigate to **Plugins > Add New**.
2. Search for **Advanced Passkeys for Secure Login**.
3. Click **Install Now** and then click **Activate**.
4. Navigate to **Settings > Advanced Passkeys** to fine-tune your security configuration.

= Manual installation =

1. Download the plugin ZIP archive file from WordPress.org.
2. In your dashboard, go to **Plugins > Add New > Upload Plugin** and choose the downloaded file.
3. Click **Activate**.
4. Navigate to **Settings > Advanced Passkeys** to get started.

= Quick Start Onboarding Guide =

1. Head to **Settings > Advanced Passkeys** — confirm passkeys are enabled and review your allowed user roles.
2. Navigate directly to **Users > Your Profile** and register your first passkey.
3. Sign out of your dashboard to confirm the secure **Sign in with Passkey** button appears below the password form.
4. *Pro Tip:* Register a secondary backup passkey (such as a mobile device or physical hardware key) to ensure you have a fallback access path.

= Local Development & Staging Environments =

The official WebAuthn protocol strictly mandates a secure connection context (HTTPS) in production. To protect your site, the plugin blocks live passkey validation over unsecured HTTP web connections.

If you are a developer testing locally on a development server environment without a local SSL certificate installed, you can easily bypass this security restriction by defining this helper flag inside your local `wp-config.php` file:

`define( 'ADVAPAFO_ALLOW_HTTP', true );` *(Warning: Never define this configuration constant on a live production environment!)*

== Frequently Asked Questions ==

= Does this replace standard WordPress passwords entirely? =

No. Passkeys function as a high-security alternative login method. Users retain their standard WordPress password as a fallback authentication method if their passkey device is missing or unavailable.

= Does this plugin require an active SSL Certificate (HTTPS)? =

Yes, in live production environments. Secure biometric authentication requires an encrypted HTTPS context to communicate safely with authenticators. To preview features locally via standard HTTP, please refer to the local development instructions outlined in the Installation section.

= Can developers manage passkey policies directly in code? =

Yes, seamlessly. Agency teams and developers can manage the plugin's complete option array using the centralized `advapafo_local_configuration` filter or the global `ADVAPAFO_SETTINGS` PHP array constant. Setting your policy in code locks the matching WordPress admin dashboard control, displaying a clear **Managed via code** status flag to prevent configuration drift.

= Which major browsers, platforms, and biometric devices are supported? =

Any operating system and browser that supports modern WebAuthn standards (supported by all primary vendors since 2022). This covers Apple Safari, Google Chrome, Mozilla Firefox, and Microsoft Edge across macOS, iOS, Windows Hello biometrics, Android fingerprint scanners, and physical FIDO2/U2F keys like YubiKeys.

= Can I restrict passkey creation to specific user roles? =

Yes. Navigate to **Settings > Advanced Passkeys > Eligible Roles** to limit access. The plugin defaults to Administrators only, and you can enable passkey access for Subscribers, Customers, or custom membership roles as needed.

= Which dynamic shortcodes are packaged with the plugin? =

**Global Core Shortcodes:**
* `[advapafo_login_button]` — Renders the standalone passkey login button.
* `[advapafo_register_button]` — Places a passkey enrollment trigger on any frontend layout.
* `[advapafo_passkey_profile]` — Displays a full credential management table for logged-in profiles.
* `[advapafo_passkey_prompt]` — Embeds an interactive onboarding registration area.

**Ecosystem Integration Shortcodes:** (Automatically responsive when companion plugins are active)
* `[advapafo_woocommerce_login]`
* `[advapafo_edd_login]`
* `[advapafo_memberpress_login]`
* `[advapafo_ultimate_member_login]`
* `[advapafo_learndash_login]`
* `[advapafo_buddyboss_login]`
* `[advapafo_gravityforms_login]`
* `[advapafo_pmp_login]`

= Which integrated Gutenberg Blocks are available? =

When an ecosystem dependency is active on your site, the plugin registers optimized, native core editor blocks:
* `advanced-passkey-login/woocommerce-login-card`
* `advanced-passkey-login/edd-login-card`
* `advanced-passkey-login/memberpress-login-card`
* `advanced-passkey-login/ultimate-member-login-card`
* `advanced-passkey-login/learndash-login-card`
* `advanced-passkey-login/buddyboss-login-card`
* `advanced-passkey-login/gravityforms-login-card`
* `advanced-passkey-login/pmp-login-card`

= What system PHP extensions must be active on my server hosting? =

The plugin runs on standard, lightweight modern server components requiring the `openssl`, `mbstring`, and `json` extensions. These packages are pre-compiled and running out of the box on nearly all managed WordPress web hosts.

= Is the plugin fully WordPress Multisite network compatible? =

Yes. Database tables partition dynamically across your network using `$wpdb->prefix`. Network activation provisions the plugin tables for existing sites and newly created network sites.

= Can I use a custom Relying Party ID (RP ID) for complex subdomain setups? =

Yes. For compatible subdomain setups, declare your root domain within your site's `wp-config.php` file:
`define( 'ADVAPAFO_RP_ID', 'yourdomain.com' );`

= Can I override the passkey login button HTML templates in my theme files? =

Yes. Copy the plugin's internal markup template directly into your theme's active folder structure:
`/wp-content/themes/your-active-theme/advanced-passkeys/login/button.php`

= What happens to my database data if I delete the plugin? =

Deactivating the plugin preserves passkey records and settings. Running the default WordPress delete routine removes the plugin's credential, rate-limit, and log tables alongside matching `advapafo_*` configuration rows.

== Developer Configuration ==

Advanced Passkeys supports code-managed configuration for developers, agencies, and infrastructure teams that deploy settings through version control. This helps apply the same passkey policy across many sites without manual option changes.

= Configuration evaluation priority order =

When resolving a configuration key, the plugin respects this strict top-down hierarchy:

1. Centralized `advapafo_local_configuration` hook filter
2. Global `ADVAPAFO_SETTINGS` array constant definition
3. Site-level `advapafo_settings` option array map
4. Single historical legacy `advapafo_*` options
5. Core plugin out-of-the-box system defaults

Values resolve individually by index key. Overriding a key like `login_challenge_ttl` via code leaves your remaining settings completely manageable via the dashboard UI or database choices.

= Example: Enforce HTTPS and restrict passkey enrollment to administrators =

Drop this configuration array inside your active theme's functions file or a custom functionality plugin:

    <?php
    add_filter(
        'advapafo_local_configuration',
        static function ( array $configuration ): array {
            return array_replace(
                $configuration,
                array(
                    'enforce_https'  => true,
                    'eligible_roles' => array( 'administrator' ),
                )
            );
        }
    );

= Example: Global array constant configuration within wp-config.php =

For automated environments that favor infrastructure-level array maps, define this constant:

    define(
        'ADVAPAFO_SETTINGS',
        array(
            'conditional_ui_enabled'     => true,
            'login_challenge_ttl'        => 300,
            'registration_challenge_ttl' => 300,
            'max_passkeys_per_user'      => 0,
        )
    );

== Screenshots ==

1. The unified site security dashboard panel tracking passkey performance metrics, active provider charts, and live login logs.
2. The core configuration settings view showing role management selections, biometric behaviors, and integrated platform switches.
3. The advanced developer configuration panel exposing parameters for WebAuthn RP IDs, token lifetimes, and security connection locks.
4. The helper copy dashboard offering copy-ready Gutenberg module paths and complete shortcode templates for clean site layout assembly.
5. The frontend User Profile responsive administration panel where site users manage, name, and revoke credentials.
6. The standard core WordPress login layout showing the passkey sign-in button below the password form.
7. The returning user login state demonstrating the Last used device indicator pill for returning passkey users.

== Changelog ==

= 1.1.13 =
* Confirmed compatibility with WordPress 7.1.
* Fixed: Gutenberg blocks now declare `api_version` 3 so they render without deprecation warnings in the persistent iframe post editor introduced in WordPress 7.1.

= 1.1.11 =
* Improved: username-free passkey sign-in now shows clearer fallback guidance when a browser or authenticator does not support discoverable credentials.
* Improved: passkey sign-in button now uses a refreshed user/key icon.
* Fixed: admin switch checkbox rendering avoids native checkbox bleed-through in custom toggle controls.

= 1.1.10 =
* Added: code-managed local configuration via `advapafo_local_configuration` and `ADVAPAFO_SETTINGS`.
* Added: admin UI indicators and locked controls for settings managed by code.
* Improved: developer configuration filters now resolve through the centralized settings getter for cleaner policy management.
* Fixed: conditional UI login elements now remain hidden consistently when conditional UI is enabled.
* Improved: authenticator provider reporting resolves legacy credential/log payload variants more reliably.
* Improved: registration errors now show friendlier passkey guidance for browser invalid-state failures.

= 1.1.9 =
* Improved: admin footer link styling and rating stars visibility for the settings screen.

= 1.1.8 =
* Fixed: passkey revoke reliability across compatibility credential tables.
* Improved: authenticator provider normalization and typo tolerance for brand labeling.
* Improved: reporting metadata normalization for authenticator detection.

= 1.1.7 =
* Improved: aligned plugin header author metadata with readme contributors.

= 1.1.6 =
* Improved: include WordPress.org assets directory in release source so banners, icon, and screenshots deploy via SVN automation.

= 1.1.5 =
* Improved: sanitize-early handling for transports and credential request inputs.
* Improved: stricter nonce-gated debug query handling in settings.
* Improved: output escaping hardening for integration-rendered markup and dynamic admin classes.
* Improved: release and quality workflow validation guardrails (actionlint gate).

= 1.1.4 =
* Added: Dashboard tab with an Authenticator Overview card.
* Added: Last Login activity card in the Dashboard tab.
* Improved: nonce and capability enforcement across sensitive request handlers.
* Improved: release packaging workflow with strict file allowlist validation.
* Fixed: release automation now publishes real GitHub releases with attached installable ZIP.

= 1.1.2 =
* Added: integration manager for popular ecosystem plugins with dependency-aware module loading.
* Added: integration-specific shortcodes and Gutenberg blocks.
* Added: integration controls in settings with installed/not installed indicators.
* Added: shortcode quick-start helper and improved shortcode documentation in admin UI.
* Changed: removed legacy shortcode alias registrations and related legacy copy.
* Changed: refreshed docs and translation template strings for current shortcode names.

= 1.1.1 =
* Updated: plugin name and user-facing references to "Advanced Passkeys for Secure Login".
* Updated: settings/UI copy to use the full plugin name.

= 1.1.0 =
* Added: dismissible "set up your passkey" nudge notice for eligible users.
* Added: Passkeys column in the admin Users list showing count per user.
* Added: Scheduled daily cleanup of expired rate-limit rows and old log entries.
* Added: Challenge timeout setting in Settings > Advanced Passkeys for Secure Login > Advanced.
* Added: Login redirect URL field in settings (fallback after passkey login).
* Added: `[advapafo_login_button]` and `[advapafo_register_button]` shortcodes.
* Added: Log retention period setting (days).
* Improved: `get_challenge_ttl()` now reads from the settings UI.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.11 =
Recommended update: improves passkey login guidance, refreshes the sign-in button icon, and fixes admin toggle rendering.