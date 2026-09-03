<?php
/**
 * Plugin Name: Advanced Passkeys for Secure Login
 * Plugin URI:  https://wordpress.org/plugins/advanced-passkey-login/
 * Description: Advanced Passkeys for Secure Login enables passwordless passkey login for WordPress. Supports Face ID, Touch ID, Windows Hello, YubiKey, and more.
 * Version:     1.1.14
 * Author:      wppasskey
 * Author URI:  https://profiles.wordpress.org/mbuiux/
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: advanced-passkey-login
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 8.0
 *
 * @package ADVAPAFO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADVAPAFO_VERSION', '1.1.14' );
define( 'ADVAPAFO_PLUGIN_FILE', __FILE__ );
define( 'ADVAPAFO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADVAPAFO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'advapafo_template_render_scope' ) ) {
	/**
	 * Include a template file in isolated variable scope.
	 *
	 * @param string               $__template_path Absolute template path.
	 * @param array<string, mixed> $__args          Variables to expose to template.
	 */
	function advapafo_template_render_scope( string $__template_path, array $__args = array() ): void {
		if ( ! empty( $__args ) ) {
			extract( $__args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template views intentionally receive arg keys as local variables.
		}

		include $__template_path;
	}
}

if ( ! function_exists( 'advapafo_is_template_name_safe' ) ) {
	/**
	 * Validate relative template name and guard against traversal.
	 *
	 * @param string $template_name Relative template path.
	 * @return bool
	 */
	function advapafo_is_template_name_safe( string $template_name ): bool {
		if ( '' === $template_name ) {
			return false;
		}

		$normalized = wp_normalize_path( trim( $template_name ) );
		if ( '' === $normalized ) {
			return false;
		}

		if ( str_starts_with( $normalized, '/' ) || str_starts_with( $normalized, '\\' ) ) {
			return false;
		}

		if ( preg_match( '#(^|/|\\\\)\.\.($|/|\\\\)#', $normalized ) ) {
			return false;
		}

		if ( str_contains( $normalized, "\0" ) ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'advapafo_is_template_path_allowed' ) ) {
	/**
	 * Ensure a resolved template path stays within known allowed roots.
	 *
	 * @param string             $resolved_path Absolute template path.
	 * @param array<int, string> $allowed_roots Allowed absolute root directories.
	 * @return bool
	 */
	function advapafo_is_template_path_allowed( string $resolved_path, array $allowed_roots ): bool {
		$real_file = realpath( $resolved_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- realpath is required to validate canonical include paths.
		if ( false === $real_file ) {
			return false;
		}

		$real_file = wp_normalize_path( $real_file );

		foreach ( $allowed_roots as $root ) {
			$real_root = realpath( $root ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- canonical path comparison for traversal protection.
			if ( false === $real_root ) {
				continue;
			}

			$real_root = wp_normalize_path( $real_root );
			if ( str_starts_with( $real_file, trailingslashit( $real_root ) ) || $real_file === $real_root ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'advapafo_get_template' ) ) {
	/**
	 * Locate and render a plugin template with child/parent theme overrides.
	 *
	 * Search order:
	 * 1) Child theme: /advanced-passkeys/{template}
	 * 2) Parent theme: /advanced-passkeys/{template}
	 * 3) Plugin fallback: /templates/{template}
	 *
	 * @param string               $template_name Relative template path (e.g. login/button.php).
	 * @param array<string, mixed> $args          Optional variables available in the template scope.
	 * @return string Absolute resolved template path, or empty string when not found/invalid.
	 */
	function advapafo_get_template( $template_name, $args = array() ) {
		$template_name = is_string( $template_name ) ? wp_normalize_path( trim( $template_name ) ) : '';
		$args          = is_array( $args ) ? $args : array();

		/**
		 * Filter template request context before template resolution.
		 *
		 * @param array{template_name:string,args:array<string,mixed>,theme_root:string,plugin_root:string} $context Template context.
		 */
		$context = apply_filters(
			'advapafo_get_template_part',
			array(
				'template_name' => $template_name,
				'args'          => $args,
				'theme_root'    => 'advanced-passkeys',
				'plugin_root'   => trailingslashit( ADVAPAFO_PLUGIN_DIR ) . 'templates',
			)
		);

		if ( ! is_array( $context ) ) {
			return '';
		}

		$template_name = isset( $context['template_name'] ) && is_string( $context['template_name'] )
			? wp_normalize_path( trim( $context['template_name'] ) )
			: '';
		$args          = isset( $context['args'] ) && is_array( $context['args'] ) ? $context['args'] : array();
		$theme_root    = isset( $context['theme_root'] ) && is_string( $context['theme_root'] ) && '' !== trim( $context['theme_root'] )
			? trim( $context['theme_root'], '/\\' )
			: 'advanced-passkeys';
		$plugin_root   = isset( $context['plugin_root'] ) && is_string( $context['plugin_root'] ) && '' !== trim( $context['plugin_root'] )
			? wp_normalize_path( untrailingslashit( trim( $context['plugin_root'] ) ) )
			: wp_normalize_path( untrailingslashit( trailingslashit( ADVAPAFO_PLUGIN_DIR ) . 'templates' ) );

		if ( ! advapafo_is_template_name_safe( $template_name ) ) {
			return '';
		}

		$relative_theme_path = trailingslashit( $theme_root ) . ltrim( $template_name, '/\\' );
		$located_template    = locate_template( array( $relative_theme_path ), false, false );

		if ( ! is_string( $located_template ) || '' === $located_template ) {
			$located_template = wp_normalize_path( trailingslashit( $plugin_root ) . ltrim( $template_name, '/\\' ) );
		}

		if ( ! is_string( $located_template ) || '' === $located_template || ! file_exists( $located_template ) ) {
			return '';
		}

		$allowed_roots = array(
			get_stylesheet_directory() . '/' . $theme_root,
			get_template_directory() . '/' . $theme_root,
			$plugin_root,
		);

		if ( ! advapafo_is_template_path_allowed( $located_template, $allowed_roots ) ) {
			return '';
		}

		advapafo_template_render_scope( $located_template, $args );

		return $located_template;
	}
}

// Allow env-based constant injection (same pattern used in planning-center-sso).
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- dynamic constant names are constrained to ADVAPAFO_* entries in this allowlist.
foreach ( array(
	'ADVAPAFO_ALLOW_HTTP',
	'ADVAPAFO_RP_ID',
	'ADVAPAFO_RP_NAME',
	'ADVAPAFO_CHALLENGE_TTL',
	'ADVAPAFO_USER_VERIFICATION',
	'ADVAPAFO_RATE_WINDOW',
	'ADVAPAFO_RATE_MAX_ATTEMPTS',
	'ADVAPAFO_RATE_LOCKOUT',
	'ADVAPAFO_ENABLE_LOGGING',
	'ADVAPAFO_SETTINGS',
) as $advapafo_env_const ) {
	if ( ! defined( $advapafo_env_const ) ) {
		$advapafo_env_value = getenv( $advapafo_env_const );
		if ( false !== $advapafo_env_value && '' !== $advapafo_env_value ) {
			define( $advapafo_env_const, $advapafo_env_value );
		}
	}
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound
unset( $advapafo_env_const, $advapafo_env_value );

/**
 * Map logical setting keys to stored option names and defaults.
 *
 * @return array<string,array{option:string,default:mixed}>
 */
function advapafo_get_setting_registry(): array {
	return array(
		'enabled'                             => array(
			'option'  => 'advapafo_enabled',
			'default' => true,
		),
		'show_separator'                      => array(
			'option'  => 'advapafo_show_separator',
			'default' => true,
		),
		'conditional_ui_enabled'              => array(
			'option'  => 'advapafo_conditional_ui_enabled',
			'default' => false,
		),
		'show_setup_notice'                   => array(
			'option'  => 'advapafo_show_setup_notice',
			'default' => true,
		),
		'eligible_roles'                      => array(
			'option'  => 'advapafo_eligible_roles',
			'default' => array( 'administrator' ),
		),
		'eligible_user'                       => array(
			'option'  => '',
			'default' => null,
		),
		'max_passkeys_per_user'               => array(
			'option'  => 'advapafo_max_passkeys_per_user',
			'default' => 0,
		),
		'user_verification'                   => array(
			'option'  => 'advapafo_user_verification',
			'default' => 'required',
		),
		'button_style'                        => array(
			'option'  => 'advapafo_button_style',
			'default' => 'black',
		),
		'rp_name'                             => array(
			'option'  => 'advapafo_rp_name',
			'default' => '',
		),
		'rp_id'                               => array(
			'option'  => 'advapafo_rp_id',
			'default' => '',
		),
		'challenge_ttl'                       => array(
			'option'  => 'advapafo_challenge_ttl',
			'default' => 300,
		),
		'login_challenge_ttl'                 => array(
			'option'  => 'advapafo_login_challenge_ttl',
			'default' => 300,
		),
		'registration_challenge_ttl'          => array(
			'option'  => 'advapafo_registration_challenge_ttl',
			'default' => 300,
		),
		'rate_limit_window'                   => array(
			'option'  => 'advapafo_rate_limit_window',
			'default' => 300,
		),
		'rate_window'                         => array(
			'option'  => 'advapafo_rate_window',
			'default' => 300,
		),
		'rate_limit_max_failures'             => array(
			'option'  => 'advapafo_rate_limit_max_failures',
			'default' => 5,
		),
		'rate_max_attempts'                   => array(
			'option'  => 'advapafo_rate_max_attempts',
			'default' => 5,
		),
		'rate_limit_lockout'                  => array(
			'option'  => 'advapafo_rate_limit_lockout',
			'default' => 900,
		),
		'rate_lockout'                        => array(
			'option'  => 'advapafo_rate_lockout',
			'default' => 900,
		),
		'activity_logging_enabled'            => array(
			'option'  => 'advapafo_activity_logging_enabled',
			'default' => true,
		),
		'log_retention_days'                  => array(
			'option'  => 'advapafo_log_retention_days',
			'default' => 90,
		),
		'login_redirect'                      => array(
			'option'  => 'advapafo_login_redirect',
			'default' => '',
		),
		'username_autocomplete_value'         => array(
			'option'  => '',
			'default' => 'username webauthn',
		),
		'passkey_allow_session_establishment' => array(
			'option'  => '',
			'default' => true,
		),
		'passkey_remember_me'                 => array(
			'option'  => '',
			'default' => false,
		),
		'passkey_emit_wp_login_failed'        => array(
			'option'  => '',
			'default' => false,
		),
		'last_used_pill_label'                => array(
			'option'  => '',
			'default' => '',
		),
		'last_used_pill_freshness_days'       => array(
			'option'  => '',
			'default' => 90,
		),
		'last_used_pill_visible'              => array(
			'option'  => '',
			'default' => null,
		),
		'authenticator_provider_label'        => array(
			'option'  => '',
			'default' => '',
		),
		'authenticator_aaguid_map'            => array(
			'option'  => '',
			'default' => array(),
		),
		'enforce_https'                       => array(
			'option'  => 'advapafo_enforce_https',
			'default' => true,
		),
		'enable_woocommerce_support'          => array(
			'option'  => 'advapafo_enable_woocommerce_support',
			'default' => true,
		),
		'enable_edd_support'                  => array(
			'option'  => 'advapafo_enable_edd_support',
			'default' => true,
		),
		'enable_memberpress_support'          => array(
			'option'  => 'advapafo_enable_memberpress_support',
			'default' => true,
		),
		'enable_ultimate_member_support'      => array(
			'option'  => 'advapafo_enable_ultimate_member_support',
			'default' => true,
		),
		'enable_learndash_support'            => array(
			'option'  => 'advapafo_enable_learndash_support',
			'default' => true,
		),
		'enable_buddyboss_support'            => array(
			'option'  => 'advapafo_enable_buddyboss_support',
			'default' => true,
		),
		'enable_gravityforms_support'         => array(
			'option'  => 'advapafo_enable_gravityforms_support',
			'default' => true,
		),
		'enable_pmp_support'                  => array(
			'option'  => 'advapafo_enable_pmp_support',
			'default' => true,
		),
	);
}

/**
 * Normalize a setting key from plugin option names or local configuration keys.
 *
 * @param string $setting_key Setting key.
 * @return string
 */
function advapafo_normalize_setting_key( $setting_key ): string {
	$setting_key = sanitize_key( (string) $setting_key );

	if ( str_starts_with( $setting_key, 'advapafo_' ) ) {
		$setting_key = substr( $setting_key, 9 );
	}

	return $setting_key;
}

/**
 * Normalize local configuration arrays from filters or constants.
 *
 * @param mixed $configuration Raw configuration value.
 * @return array<string,mixed>
 */
function advapafo_normalize_local_configuration( $configuration ): array {
	if ( is_string( $configuration ) ) {
		$maybe_unserialized = maybe_unserialize( $configuration );
		if ( is_array( $maybe_unserialized ) ) {
			$configuration = $maybe_unserialized;
		} else {
			$maybe_json    = json_decode( $configuration, true );
			$configuration = is_array( $maybe_json ) ? $maybe_json : array();
		}
	}

	if ( ! is_array( $configuration ) ) {
		return array();
	}

	$normalized = array();
	foreach ( $configuration as $key => $value ) {
		$normalized_key = advapafo_normalize_setting_key( (string) $key );
		if ( '' !== $normalized_key ) {
			$normalized[ $normalized_key ] = $value;
		}
	}

	return $normalized;
}

/**
 * Get the effective local configuration supplied by PHP code.
 *
 * @return array<string,mixed>
 */
function advapafo_get_local_configuration(): array {
	$filter_configuration = advapafo_normalize_local_configuration(
		apply_filters( 'advapafo_local_configuration', array() )
	);

	$constant_configuration = defined( 'ADVAPAFO_SETTINGS' )
		? advapafo_normalize_local_configuration( ADVAPAFO_SETTINGS )
		: array();

	return array(
		'filter'   => $filter_configuration,
		'constant' => $constant_configuration,
	);
}

/**
 * Check whether a specific setting is dictated by local PHP configuration.
 *
 * @param string $setting_key Setting key.
 * @return bool
 */
function advapafo_is_setting_overridden( $setting_key ): bool {
	$setting_key   = advapafo_normalize_setting_key( $setting_key );
	$configuration = advapafo_get_local_configuration();

	return array_key_exists( $setting_key, $configuration['filter'] )
		|| array_key_exists( $setting_key, $configuration['constant'] );
}

/**
 * Apply legacy configuration filters from a single compatibility layer.
 *
 * Local configuration and constants intentionally bypass these legacy filters.
 * This keeps code-level deployments deterministic while preserving existing
 * extension callbacks for sites that already use them.
 *
 * @param string              $setting_key Setting key.
 * @param mixed               $value Current resolved value.
 * @param array<string,mixed> $context Optional contextual values.
 * @return mixed
 */
function advapafo_apply_legacy_setting_filter( string $setting_key, $value, array $context = array() ) {
	$user            = $context['user'] ?? null;
	$credential_hash = isset( $context['credential_hash'] ) ? (string) $context['credential_hash'] : '';
	$reason          = isset( $context['reason'] ) ? (string) $context['reason'] : '';

	switch ( $setting_key ) {
		case 'conditional_ui_enabled':
			return apply_filters( 'advapafo_enable_conditional_ui', (bool) $value );

		case 'eligible_user':
			return $user instanceof WP_User
				? apply_filters( 'advapafo_is_eligible_user', $value, $user )
				: $value;

		case 'max_passkeys_per_user':
			$filtered = $user instanceof WP_User
				? apply_filters( 'advapafo_max_passkeys_per_user', null, $user )
				: null;

			return null !== $filtered ? $filtered : $value;

		case 'username_autocomplete_value':
			return apply_filters( 'advapafo_username_autocomplete_value', (string) $value );

		case 'passkey_allow_session_establishment':
			return $user instanceof WP_User
				? apply_filters( 'advapafo_passkey_allow_session_establishment', (bool) $value, $user, $credential_hash )
				: $value;

		case 'passkey_remember_me':
			return $user instanceof WP_User
				? apply_filters( 'advapafo_passkey_remember_me', (bool) $value, $user )
				: $value;

		case 'login_redirect':
			return $user instanceof WP_User
				? apply_filters( 'advapafo_login_redirect', (string) $value, $user )
				: $value;

		case 'passkey_emit_wp_login_failed':
			return $user instanceof WP_User
				? apply_filters( 'advapafo_passkey_emit_wp_login_failed', (bool) $value, $user, $credential_hash, $reason )
				: $value;

		case 'last_used_pill_label':
			return apply_filters( 'advapafo_last_used_pill_label', (string) $value, $user instanceof WP_User ? $user : null );

		case 'last_used_pill_freshness_days':
			return apply_filters( 'advapafo_last_used_pill_freshness_days', (int) $value, $user instanceof WP_User ? $user : null );

		case 'last_used_pill_visible':
			return apply_filters(
				'advapafo_last_used_pill_visible',
				(bool) $value,
				isset( $context['timestamp'] ) ? (int) $context['timestamp'] : 0,
				isset( $context['freshness_days'] ) ? (int) $context['freshness_days'] : 0,
				$user instanceof WP_User ? $user : null
			);

		case 'authenticator_provider_label':
			return apply_filters(
				'advapafo_authenticator_provider_label',
				(string) $value,
				isset( $context['provider'] ) ? (string) $context['provider'] : '',
				isset( $context['label'] ) ? (string) $context['label'] : ''
			);

		case 'authenticator_aaguid_map':
			return apply_filters( 'advapafo_authenticator_aaguid_map', is_array( $value ) ? $value : array() );
	}

	return $value;
}

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Public API intentionally uses the documented $default getter argument.
/**
 * Get one setting using local code overrides before database fallback.
 *
 * Evaluation order: advapafo_local_configuration filter, ADVAPAFO_SETTINGS
 * constant, advapafo_settings option array, mapped legacy option, default.
 *
 * @param string              $setting_key Setting key, with or without advapafo_ prefix.
 * @param mixed               $default Default value.
 * @param array<string,mixed> $context Optional contextual values and lookup flags.
 * @return mixed
 */
function advapafo_get_setting( $setting_key, $default = null, array $context = array() ) {
	$setting_key = advapafo_normalize_setting_key( $setting_key );
	if ( '' === $setting_key ) {
		return $default;
	}

	$registry         = advapafo_get_setting_registry();
	$registered       = $registry[ $setting_key ] ?? null;
	$resolved_default = null !== $default ? $default : ( $registered['default'] ?? null );
	$configuration    = advapafo_get_local_configuration();
	$apply_legacy     = ! array_key_exists( 'apply_legacy', $context ) || (bool) $context['apply_legacy'];
	$skip_database    = ! empty( $context['skip_database'] );

	if ( array_key_exists( $setting_key, $configuration['filter'] ) ) {
		return $configuration['filter'][ $setting_key ];
	}

	if ( array_key_exists( $setting_key, $configuration['constant'] ) ) {
		return $configuration['constant'][ $setting_key ];
	}

	$resolved_value = $resolved_default;

	if ( ! $skip_database ) {
		$settings_option = get_option( 'advapafo_settings', array() );
		if ( is_array( $settings_option ) ) {
			$settings_option = advapafo_normalize_local_configuration( $settings_option );
			if ( array_key_exists( $setting_key, $settings_option ) ) {
				$resolved_value = $settings_option[ $setting_key ];
				return $apply_legacy ? advapafo_apply_legacy_setting_filter( $setting_key, $resolved_value, $context ) : $resolved_value;
			}
		}

		if ( ! empty( $registered['option'] ) ) {
			$resolved_value = get_option( $registered['option'], $resolved_default );
		}
	}

	return $apply_legacy ? advapafo_apply_legacy_setting_filter( $setting_key, $resolved_value, $context ) : $resolved_value;
}
// phpcs:enable Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound

/**
 * Render the admin badge used beside code-managed settings.
 *
 * @param string|array<int,string> $setting_key Setting key or related keys.
 */
function advapafo_render_managed_setting_badge( $setting_key ): void {
	$setting_keys = (array) $setting_key;
	$is_managed   = false;

	foreach ( $setting_keys as $key ) {
		if ( advapafo_is_setting_overridden( (string) $key ) ) {
			$is_managed = true;
			break;
		}
	}

	if ( ! $is_managed ) {
		return;
	}

	echo '<span class="advapafo-badge advapafo-badge--managed-code">' . esc_html__( 'Managed via code', 'advanced-passkey-login' ) . '</span>';
}

// ──────────────────────────────────────────────────────────────
// Composer autoload (lbuchs/webauthn)
// ──────────────────────────────────────────────────────────────
$advapafo_autoload = ADVAPAFO_PLUGIN_DIR . 'vendor/autoload.php';
if ( PHP_VERSION_ID >= 80000 && file_exists( $advapafo_autoload ) ) {
	$advapafo_should_load_autoloader = true;

	$advapafo_autoload_real = ADVAPAFO_PLUGIN_DIR . 'vendor/composer/autoload_real.php';
	if ( file_exists( $advapafo_autoload_real ) ) {
		$advapafo_autoload_real_src = file_get_contents( $advapafo_autoload_real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local filesystem read for composer class detection.
		if ( is_string( $advapafo_autoload_real_src ) && preg_match( '/class\s+(ComposerAutoloaderInit[0-9a-fA-F_]+)/', $advapafo_autoload_real_src, $m ) ) {
			if ( ! empty( $m[1] ) && class_exists( (string) $m[1], false ) ) {
				$advapafo_should_load_autoloader = false;
			}
		}
	}

	if ( $advapafo_should_load_autoloader ) {
		require_once $advapafo_autoload;
	}
}
unset( $advapafo_autoload, $advapafo_should_load_autoloader, $advapafo_autoload_real, $advapafo_autoload_real_src );

/**
 * Run one-time option normalization tasks for advapafo_* keys.
 */
function advapafo_migrate_legacy_options_once(): void {
	if ( (int) get_option( 'advapafo_legacy_options_migrated_v1', 0 ) === 1 ) {
		return;
	}

	// Legacy free builds commonly stored a default cap of 5; move to unlimited.
	$legacy_cap = (int) get_option( 'advapafo_max_passkeys_per_user', 0 );
	if ( 5 === $legacy_cap ) {
		update_option( 'advapafo_max_passkeys_per_user', 0 );
	}

	update_option( 'advapafo_legacy_options_migrated_v1', 1, false );
}

/**
 * Ensure passkey cap defaults to unlimited for existing installs migrated earlier.
 */
function advapafo_remove_legacy_passkey_cap_once(): void {
	if ( (int) get_option( 'advapafo_cap_migrated_v1', 0 ) === 1 ) {
		return;
	}

	if ( (int) get_option( 'advapafo_max_passkeys_per_user', 0 ) === 5 ) {
		update_option( 'advapafo_max_passkeys_per_user', 0 );
	}

	update_option( 'advapafo_cap_migrated_v1', 1, false );
}

// ──────────────────────────────────────────────────────────────
// Load classes
// ──────────────────────────────────────────────────────────────
require_once ADVAPAFO_PLUGIN_DIR . 'includes/class-advapafo-passkeys.php';
require_once ADVAPAFO_PLUGIN_DIR . 'includes/class-advapafo-settings.php';
require_once ADVAPAFO_PLUGIN_DIR . 'includes/class-advapafo-login-form.php';
require_once ADVAPAFO_PLUGIN_DIR . 'includes/class-advapafo-shortcodes.php';
require_once ADVAPAFO_PLUGIN_DIR . 'includes/class-advapafo-integration-manager.php';

// ──────────────────────────────────────────────────────────────
// Bootstrap
// ──────────────────────────────────────────────────────────────
/**
 * Initialize plugin services after core/plugin load.
 */
function advapafo_init() {
	advapafo_migrate_legacy_options_once();
	advapafo_remove_legacy_passkey_cap_once();

	new ADVAPAFO_Passkeys();
	new ADVAPAFO_Login_Form();
	new ADVAPAFO_Shortcodes();
	new ADVAPAFO_Integration_Manager();

	if ( is_admin() ) {
		new ADVAPAFO_Settings();
	}
}
add_action( 'plugins_loaded', 'advapafo_init' );

/**
 * Detect whether the plugin is network-activated.
 */
function advapafo_is_network_active(): bool {
	if ( ! is_multisite() ) {
		return false;
	}

	$active = (array) get_site_option( 'active_sitewide_plugins', array() );
	return isset( $active[ plugin_basename( ADVAPAFO_PLUGIN_FILE ) ] );
}

/**
 * Run a callback for current site or across the whole network.
 *
 * @param bool     $network_wide Whether the plugin is network-activated.
 * @param callable $callback     Callback to run for each relevant site.
 */
function advapafo_for_each_site( bool $network_wide, callable $callback ): void {
	if ( ! is_multisite() || ! $network_wide ) {
		$callback();
		return;
	}

	$original_blog_id = get_current_blog_id();

	$page     = 1;
	$per_page = 200;

	do {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => $per_page,
				'paged'  => $page,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			$callback();
			restore_current_blog();
		}

		++$page;
	} while ( ! empty( $site_ids ) );

	if ( get_current_blog_id() !== (int) $original_blog_id ) {
		switch_to_blog( (int) $original_blog_id );
		restore_current_blog();
	}
}

// ──────────────────────────────────────────────────────────────
// Activation / deactivation
// ──────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'advapafo_activate' );
register_deactivation_hook( __FILE__, 'advapafo_deactivate' );

/**
 * Activation routine.
 *
 * @param bool $network_wide Whether plugin is being activated network-wide.
 */
function advapafo_activate( bool $network_wide = false ) {
	if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
		deactivate_plugins( plugin_basename( ADVAPAFO_PLUGIN_FILE ) );
		wp_die( esc_html__( 'Advanced Passkeys for Secure Login requires PHP 8.0 or higher. Please upgrade PHP before activating this plugin.', 'advanced-passkey-login' ) );
	}
	if ( version_compare( $GLOBALS['wp_version'], '6.0', '<' ) ) {
		deactivate_plugins( plugin_basename( ADVAPAFO_PLUGIN_FILE ) );
		wp_die( esc_html__( 'Advanced Passkeys for Secure Login requires WordPress 6.0 or higher. Please update WordPress before activating this plugin.', 'advanced-passkey-login' ) );
	}

	require_once ADVAPAFO_PLUGIN_DIR . 'includes/class-advapafo-passkeys.php';

	advapafo_for_each_site(
		$network_wide,
		static function (): void {
			ADVAPAFO_Passkeys::create_tables();
			ADVAPAFO_Passkeys::schedule_cron();
		}
	);

	flush_rewrite_rules();
}

/**
 * Deactivation routine.
 *
 * @param bool $network_wide Whether plugin is being deactivated network-wide.
 */
function advapafo_deactivate( bool $network_wide = false ) {
	require_once ADVAPAFO_PLUGIN_DIR . 'includes/class-advapafo-passkeys.php';

	advapafo_for_each_site(
		$network_wide,
		static function (): void {
			ADVAPAFO_Passkeys::unschedule_cron();
		}
	);

	// Nothing else to tear down on deactivation; tables are preserved until uninstall.
}

/**
 * Provision plugin tables/cron when a new site is created on multisite.
 *
 * @param WP_Site $new_site The newly provisioned site.
 */
function advapafo_multisite_initialize_site( WP_Site $new_site ): void {
	if ( ! advapafo_is_network_active() ) {
		return;
	}

	require_once ADVAPAFO_PLUGIN_DIR . 'includes/class-advapafo-passkeys.php';

	switch_to_blog( (int) $new_site->blog_id );
	ADVAPAFO_Passkeys::create_tables();
	ADVAPAFO_Passkeys::schedule_cron();
	restore_current_blog();
}
add_action( 'wp_initialize_site', 'advapafo_multisite_initialize_site' );

// ──────────────────────────────────────────────────────────────
// Settings link on Plugins page
// ──────────────────────────────────────────────────────────────
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'options-general.php?page=advanced-passkey-login' );
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'advanced-passkey-login' ) ) );
		return $links;
	}
);

add_filter(
	'plugin_row_meta',
	function ( $links, $file ) {
		if ( plugin_basename( __FILE__ ) !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://wordpress.org/plugins/advanced-passkey-login/' ),
			esc_html__( 'Plugin Page', 'advanced-passkey-login' )
		);

		return $links;
	},
	10,
	2
);

// ──────────────────────────────────────────────────────────────
// Security notice for dev-only flags
// ──────────────────────────────────────────────────────────────
add_action(
	'admin_notices',
	function () {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$warnings = array();
		if ( defined( 'ADVAPAFO_ALLOW_HTTP' ) && ADVAPAFO_ALLOW_HTTP ) {
			$warnings[] = '<strong>ADVAPAFO_ALLOW_HTTP</strong> is enabled — insecure transport is allowed. Disable in production.';
		}
		if ( empty( $warnings ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Advanced Passkeys for Secure Login security warning:</strong></p><ul>';
		foreach ( $warnings as $w ) {
			echo '<li>' . wp_kses( $w, array( 'strong' => array() ) ) . '</li>';
		}
		echo '</ul></div>';
	}
);
