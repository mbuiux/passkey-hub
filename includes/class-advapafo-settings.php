<?php
/**
 * Advanced Passkeys for Secure Login premium admin settings screen.
 *
 * Drop this in place of your existing settings class file, or copy the markup
 * methods into your current class if your plugin already wires settings elsewhere.
 *
 * @package ADVAPAFO
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- legacy file naming kept for backward compatibility.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page controller and dashboard data presenter.
 */
class ADVAPAFO_Settings {
	/**
	 * Settings API option group key.
	 *
	 * @var string
	 */
	private $option_group = 'advapafo_settings_group';

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'advanced-passkey-login';

	/**
	 * Prefix for per-user notice transients.
	 *
	 * @var string
	 */
	private $notice_transient_prefix = 'advapafo_settings_notice_';

	/**
	 * Build per-user transient key for save notices.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_notice_transient_key( $user_id ) {
		return $this->notice_transient_prefix . absint( $user_id );
	}

	/**
	 * Register settings-page hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_action_update', array( $this, 'flag_settings_save' ), 1 );
		add_action( 'admin_post_advapafo_dismiss_quick_setup', array( $this, 'dismiss_quick_setup' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_footer_text', array( $this, 'filter_admin_footer_text' ), 20 );
		add_filter( 'update_footer', array( $this, 'filter_update_footer' ), 20 );
	}

	/**
	 * Determine whether the quick setup sidebar card has been dismissed.
	 *
	 * @return bool
	 */
	private function is_quick_setup_dismissed() {
		return '1' === (string) get_option( 'advapafo_quick_setup_dismissed', '0' );
	}

	/**
	 * Persist dismissal of the quick setup sidebar card.
	 */
	public function dismiss_quick_setup() {
		check_admin_referer( 'advapafo_dismiss_quick_setup' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'advanced-passkey-login' ) );
		}

		update_option( 'advapafo_quick_setup_dismissed', '1', false );

		$raw_redirect = filter_input( INPUT_POST, 'redirect_to', FILTER_SANITIZE_URL );
		$redirect_to  = is_string( $raw_redirect ) && '' !== $raw_redirect
			? wp_validate_redirect( $raw_redirect, admin_url( 'options-general.php?page=' . $this->page_slug ) )
			: admin_url( 'options-general.php?page=' . $this->page_slug );

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Determine if current admin screen is this plugin settings page.
	 *
	 * @return bool
	 */
	private function is_plugin_settings_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->id ) ) {
			return false;
		}

		return 'settings_page_' . $this->page_slug === $screen->id;
	}

	/**
	 * Replace left admin footer text on plugin settings screen.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public function filter_admin_footer_text( $text ) {
		if ( ! $this->is_plugin_settings_screen() ) {
			return $text;
		}

		return sprintf(
			'%1$s <a class="advapafo-footer-link" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s <span class="advapafo-rating-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span></a>! Thank you in advance! 🙌',
			esc_html__( 'Maintained by wppasskey.', 'advanced-passkey-login' ),
			esc_url( 'https://wordpress.org/support/plugin/advanced-passkey-login/reviews/#new-post' ),
			esc_html__( 'Please rate this plugin', 'advanced-passkey-login' )
		);
	}

	/**
	 * Replace right admin footer text on plugin settings screen.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public function filter_update_footer( $text ) {
		if ( ! $this->is_plugin_settings_screen() ) {
			return $text;
		}

		$wordpress_link = sprintf(
			'<a class="advapafo-footer-link" href="%1$s" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-wordpress advapafo-footer-link__icon" aria-hidden="true"></span><span>%2$s</span></a>',
			esc_url( 'https://wordpress.org/plugins/advanced-passkey-login' ),
			esc_html__( 'WordPress.org', 'advanced-passkey-login' )
		);

		$github_link = sprintf(
			'<a class="advapafo-footer-link" href="%1$s" target="_blank" rel="noopener noreferrer"><span class="advapafo-footer-link__icon advapafo-footer-link__icon--svg" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 .5C5.65.5.5 5.67.5 12.06c0 5.12 3.3 9.46 7.87 10.99.58.11.79-.26.79-.57v-2.02c-3.2.7-3.88-1.56-3.88-1.56-.52-1.34-1.28-1.69-1.28-1.69-1.05-.72.08-.7.08-.7 1.16.08 1.77 1.2 1.77 1.2 1.03 1.78 2.7 1.27 3.36.97.1-.76.4-1.27.73-1.56-2.56-.29-5.25-1.29-5.25-5.73 0-1.26.45-2.29 1.19-3.1-.12-.29-.51-1.46.11-3.04 0 0 .97-.32 3.18 1.18a10.97 10.97 0 0 1 5.8 0c2.2-1.5 3.17-1.18 3.17-1.18.63 1.58.24 2.75.12 3.04.74.81 1.18 1.84 1.18 3.1 0 4.46-2.69 5.44-5.26 5.72.41.36.78 1.08.78 2.18v3.24c0 .32.21.69.8.57A11.6 11.6 0 0 0 23.5 12.06C23.5 5.67 18.35.5 12 .5Z"/></svg></span><span>%2$s</span></a>',
			esc_url( 'https://github.com/mbuiux/advanced-passkey-login' ),
			esc_html__( 'GitHub', 'advanced-passkey-login' )
		);

		return $wordpress_link . ' | ' . $github_link;
	}

	/**
	 * Detect when our settings form is submitted to options.php and store a
	 * per-user flag BEFORE the redirect happens. Avoids relying on the
	 * settings-updated URL param or the settings_errors transient, both of
	 * which can be consumed or missing depending on environment.
	 */
	public function flag_settings_save() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		$request_method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately for method check.

		if ( 'POST' !== strtoupper( $request_method ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['option_page'] ) ) {
			return;
		}

		$option_page = sanitize_text_field( wp_unslash( $_POST['option_page'] ) );
		if ( $this->option_group !== $option_page ) {
			return;
		}

		if ( empty( $_POST['_wpnonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		if ( ! wp_verify_nonce( $nonce, $option_page . '-options' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$notice = array(
			'type'    => 'success',
			'message' => __( 'Settings saved.', 'advanced-passkey-login' ),
		);

		set_transient( $this->get_notice_transient_key( $user_id ), $notice, 180 );
	}

	/**
	 * Retrieve and consume any pending save notice for the user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, string>|null
	 */
	private function consume_save_notice( $user_id ) {
		if ( $user_id <= 0 ) {
			return null;
		}

		$key    = $this->get_notice_transient_key( $user_id );
		$notice = get_transient( $key );

		if ( false === $notice ) {
			return null;
		}

		delete_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return null;
		}

		$type = ! empty( $notice['type'] ) ? sanitize_key( $notice['type'] ) : 'success';
		if ( ! in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ) {
			$type = 'success';
		}

		return array(
			'type'    => $type,
			'message' => wp_kses_post( $notice['message'] ),
		);
	}

	/**
	 * Register the plugin settings page under Settings.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Advanced Passkeys for Secure Login', 'advanced-passkey-login' ),
			__( 'Advanced Passkeys for Secure Login', 'advanced-passkey-login' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin styles/scripts for this plugin settings screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . $this->page_slug !== $hook ) {
			return;
		}

		$version = defined( 'ADVAPAFO_VERSION' ) ? ADVAPAFO_VERSION : '1.0.0';
		$css_url = '';

		/*
		 * Support the common Advanced Passkeys for Secure Login plugin structure first:
		 * /admin/css/advapafo-admin.css. The other paths are fallbacks for simple
		 * copy/paste installs and older generated bundles.
		 */
		if ( file_exists( plugin_dir_path( __DIR__ ) . 'admin/css/advapafo-admin.css' ) ) {
			$css_url = ADVAPAFO_PLUGIN_URL . 'admin/css/advapafo-admin.css';
		} elseif ( file_exists( __DIR__ . '/advapafo-admin.css' ) ) {
			$css_url = ADVAPAFO_PLUGIN_URL . 'includes/advapafo-admin.css';
		} elseif ( file_exists( plugin_dir_path( __DIR__ ) . 'assets/css/advapafo-admin.css' ) ) {
			$css_url = ADVAPAFO_PLUGIN_URL . 'assets/css/advapafo-admin.css';
		} elseif ( file_exists( plugin_dir_path( __DIR__ ) . 'advapafo-admin.css' ) ) {
			$css_url = ADVAPAFO_PLUGIN_URL . 'advapafo-admin.css';
		}

		if ( $css_url ) {
			wp_enqueue_style( 'advapafo-admin', $css_url, array(), $version );
		}

		$active_tab = $this->resolve_active_tab();
		if ( ! in_array( $active_tab, array( 'dashboard', 'audit' ), true ) ) {
			return;
		}

		$dashboard_css = plugin_dir_path( __DIR__ ) . 'admin/css/advapafo-dashboard.css';
		if ( file_exists( $dashboard_css ) ) {
			wp_enqueue_style(
				'advapafo-dashboard',
				ADVAPAFO_PLUGIN_URL . 'admin/css/advapafo-dashboard.css',
				array( 'advapafo-admin' ),
				$version
			);
		}

		if ( 'audit' === $active_tab ) {
			$admin_table_js = plugin_dir_path( __DIR__ ) . 'admin/js/advapafo-admin-table.js';
			if ( file_exists( $admin_table_js ) ) {
				wp_enqueue_script(
					'advapafo-admin-table',
					ADVAPAFO_PLUGIN_URL . 'admin/js/advapafo-admin-table.js',
					array(),
					$version,
					true
				);
			}
			return;
		}

		$apexcharts_js   = plugin_dir_path( __DIR__ ) . 'admin/vendor/apexcharts/apexcharts.min.js';
		$apex_dependency = '';
		if ( file_exists( $apexcharts_js ) ) {
			wp_enqueue_script(
				'advapafo-apexcharts',
				ADVAPAFO_PLUGIN_URL . 'admin/vendor/apexcharts/apexcharts.min.js',
				array(),
				'3.49.1',
				true
			);
			$apex_dependency = 'advapafo-apexcharts';
		} elseif ( wp_script_is( 'apexcharts', 'registered' ) || wp_script_is( 'apexcharts', 'enqueued' ) ) {
			$apex_dependency = 'apexcharts';
		}

		$dashboard_js = plugin_dir_path( __DIR__ ) . 'admin/js/advapafo-dashboard.js';
		if ( file_exists( $dashboard_js ) ) {
			$dashboard_deps = array();
			if ( '' !== $apex_dependency ) {
				$dashboard_deps[] = $apex_dependency;
			}

			wp_enqueue_script(
				'advapafo-dashboard',
				ADVAPAFO_PLUGIN_URL . 'admin/js/advapafo-dashboard.js',
				$dashboard_deps,
				$version,
				true
			);
		}
	}

	/**
	 * Register plugin options and sanitization callbacks.
	 */
	public function register_settings() {
		register_setting(
			$this->option_group,
			'advapafo_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_show_separator',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_show_separator' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_conditional_ui_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_activity_logging_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_show_setup_notice',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_enable_woocommerce_support',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_enable_edd_support',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_enable_memberpress_support',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_enable_ultimate_member_support',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_enable_learndash_support',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_enable_buddyboss_support',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_enable_gravityforms_support',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_enable_pmp_support',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_eligible_roles',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_roles' ),
				'default'           => array( 'administrator' ),
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_max_passkeys_per_user',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_max_passkeys' ),
				'default'           => 0,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_user_verification',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_user_verification' ),
				'default'           => 'required',
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_button_style',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_button_style' ),
				'default'           => 'black',
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_rp_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_rp_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_rp_id' ),
				'default'           => '',
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_login_challenge_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_challenge_ttl' ),
				'default'           => 300,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_registration_challenge_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_challenge_ttl' ),
				'default'           => 300,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_rate_limit_window',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_rate_limit_window' ),
				'default'           => 300,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_rate_limit_max_failures',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_rate_limit_max_failures' ),
				'default'           => 5,
			)
		);

		register_setting(
			$this->option_group,
			'advapafo_rate_limit_lockout',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_rate_limit_lockout' ),
				'default'           => 900,
			)
		);
	}

	/**
	 * Render the full settings page shell and active tab content.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'advanced-passkey-login' ) );
		}

		$active_tab = $this->resolve_active_tab();

		$base_url         = admin_url( 'options-general.php?page=' . $this->page_slug );
		$show_quick_setup = ! $this->is_quick_setup_dismissed();

		$user_id = get_current_user_id();

		$queued_notices = array();
		$notice_source  = 'none';

		$core_settings_errors = get_settings_errors();
		foreach ( $core_settings_errors as $notice ) {
			if ( empty( $notice['message'] ) ) {
				continue;
			}

			$type = ! empty( $notice['type'] ) ? sanitize_key( $notice['type'] ) : 'info';
			if ( 'updated' === $type ) {
				$type = 'success';
			}
			if ( ! in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ) {
				$type = 'info';
			}

			$queued_notices[] = array(
				'type'    => $type,
				'message' => wp_kses_post( $notice['message'] ),
			);
		}

		if ( ! empty( $queued_notices ) ) {
			$notice_source = 'core_settings_errors';
		}

		$transient_present = false;
		if ( $user_id > 0 ) {
			$transient_present = false !== get_transient( $this->get_notice_transient_key( $user_id ) );
		}

		if ( empty( $queued_notices ) ) {
			$save_notice = $this->consume_save_notice( $user_id );
			if ( ! empty( $save_notice ) ) {
				$queued_notices[] = $save_notice;
				$notice_source    = 'transient';
			}
		}

		$notice_debug      = filter_input( INPUT_GET, 'advapafo_notice_debug', FILTER_SANITIZE_NUMBER_INT );
		$raw_debug_nonce   = filter_input( INPUT_GET, 'advapafo_notice_debug_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$debug_nonce_valid = false;

		if ( is_string( $raw_debug_nonce ) && '' !== $raw_debug_nonce ) {
			$debug_nonce       = sanitize_text_field( $raw_debug_nonce );
			$debug_nonce_valid = wp_verify_nonce( $debug_nonce, 'advapafo_notice_debug' );
		}

		$show_debug = current_user_can( 'manage_options' )
			&& is_string( $notice_debug )
			&& '1' === $notice_debug
			&& $debug_nonce_valid;

		$debug_payload = array();
		if ( $show_debug ) {
			$request_method_debug = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

			$debug_payload = array(
				'method'               => $request_method_debug,
				'page'                 => $this->page_slug,
				'tab'                  => $active_tab,
				'settings_updated_get' => '(not read)',
				'core_errors_count'    => count( $core_settings_errors ),
				'transient_present'    => $transient_present ? 'yes' : 'no',
				'queued_notices_count' => count( $queued_notices ),
				'notice_source'        => $notice_source,
				'user_id'              => $user_id,
			);
		}
		?>
		<div class="wrap advapafo-admin-wrap">
			<?php if ( $show_debug ) : ?>
			<div class="advapafo-debug-banner" role="status" aria-live="polite">
				<strong><?php esc_html_e( 'ADVAPAFO Notice Debug', 'advanced-passkey-login' ); ?></strong>
				<pre><?php echo esc_html( wp_json_encode( $debug_payload, JSON_PRETTY_PRINT ) ); ?></pre>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $queued_notices ) ) : ?>
			<div class="advapafo-notices-wrap">
				<?php foreach ( $queued_notices as $notice ) : ?>
				<div class="advapafo-flash advapafo-flash--<?php echo esc_attr( $notice['type'] ); ?>" role="alert">
					<p><?php echo wp_kses_post( $notice['message'] ); ?></p>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<div class="advapafo-premium-shell">
				<header class="advapafo-hero">
					<div class="advapafo-hero__content">
						<div class="advapafo-product-mark">
							<span class="advapafo-product-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"></path><path d="M14 13.12c0 2.38 0 6.38-1 8.88"></path><path d="M17.29 21.02c.12-.6.43-2.3.5-3.02"></path><path d="M2 12a10 10 0 0 1 18-6"></path><path d="M2 16h.01"></path><path d="M21.8 16c.2-2 .131-5.354 0-6"></path><path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"></path><path d="M8.65 22c.21-.66.45-1.32.57-2"></path><path d="M9 6.8a6 6 0 0 1 9 5.2v2"></path></svg></span>
							<div>
								<p class="advapafo-eyebrow"><?php esc_html_e( 'Welcome to', 'advanced-passkey-login' ); ?></p>
								<h1><?php esc_html_e( 'Advanced Passkeys for Secure Login', 'advanced-passkey-login' ); ?></h1>
							</div>
						</div>
						<p class="advapafo-hero__copy">
							<?php esc_html_e( 'A premium passwordless authentication control center built for WordPress.', 'advanced-passkey-login' ); ?>
						</p>
					</div>
					<div class="advapafo-hero__actions">
						<span class="advapafo-status-pill advapafo-status-pill--success">
							<span class="advapafo-status-dot" aria-hidden="true"></span>
							<?php esc_html_e( 'Ready', 'advanced-passkey-login' ); ?>
						</span>

					</div>
				</header>

				<nav class="advapafo-tabs" aria-label="<?php esc_attr_e( 'Advanced Passkeys for Secure Login settings tabs', 'advanced-passkey-login' ); ?>">
					<?php $this->render_tab_link( $base_url, 'dashboard', __( 'Dashboard', 'advanced-passkey-login' ), $active_tab ); ?>
					<?php $this->render_tab_link( $base_url, 'audit', __( 'Audit Log', 'advanced-passkey-login' ), $active_tab ); ?>
					<?php $this->render_tab_link( $base_url, 'settings', __( 'Settings', 'advanced-passkey-login' ), $active_tab ); ?>
					<?php $this->render_tab_link( $base_url, 'advanced', __( 'Advanced', 'advanced-passkey-login' ), $active_tab ); ?>
					<?php $this->render_tab_link( $base_url, 'shortcodes', __( 'Shortcodes', 'advanced-passkey-login' ), $active_tab ); ?>
				</nav>

				<?php $is_full_width_tab = in_array( $active_tab, array( 'dashboard', 'audit' ), true ); ?>
				<div class="advapafo-layout<?php echo esc_attr( $is_full_width_tab ? ' advapafo-layout--dashboard' : ( $show_quick_setup ? '' : ' advapafo-layout--full' ) ); ?>">
					<main class="advapafo-main-panel">
						<?php if ( 'dashboard' === $active_tab ) : ?>
							<?php $this->render_dashboard_tab(); ?>
						<?php elseif ( 'audit' === $active_tab ) : ?>
							<?php $this->render_audit_tab(); ?>
						<?php elseif ( 'shortcodes' === $active_tab ) : ?>
							<?php $this->render_shortcodes_tab(); ?>
						<?php else : ?>
							<form method="post" action="options.php" class="advapafo-settings-form">
								<?php settings_fields( $this->option_group ); ?>
								<?php $this->render_preserved_hidden_fields( $active_tab ); ?>
								<?php 'advanced' === $active_tab ? $this->render_advanced_tab() : $this->render_settings_tab(); ?>
								<footer class="advapafo-form-footer">
									<p><?php esc_html_e( 'Changes apply immediately after saving.', 'advanced-passkey-login' ); ?></p>
									<?php submit_button( __( 'Save Settings', 'advanced-passkey-login' ), 'primary advapafo-save-button', 'submit', false ); ?>
								</footer>
							</form>
						<?php endif; ?>
					</main>

					<?php if ( ! $is_full_width_tab && $show_quick_setup ) : ?>
					<aside class="advapafo-sidebar" aria-label="<?php esc_attr_e( 'Advanced Passkeys for Secure Login quick actions', 'advanced-passkey-login' ); ?>">
						<?php $this->render_sidebar_cards( $active_tab ); ?>
					</aside>
					<?php endif; ?>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Render a single tab link.
	 *
	 * @param string $base_url   Base settings URL.
	 * @param string $tab        Tab key.
	 * @param string $label      Tab label.
	 * @param string $active_tab Currently active tab key.
	 */
	private function render_tab_link( $base_url, $tab, $label, $active_tab ) {
		$classes = 'advapafo-tab';
		if ( $tab === $active_tab ) {
			$classes .= ' is-active';
		}

		$tab_url = add_query_arg( 'tab', $tab, $base_url );
		$tab_url = wp_nonce_url( $tab_url, 'advapafo_tab_' . $tab, 'advapafo_tab_nonce' );

		printf(
			'<a class="%1$s" href="%2$s">%3$s</a>',
			esc_attr( $classes ),
			esc_url( $tab_url ),
			esc_html( $label )
		);
	}

	/**
	 * Resolve active settings tab with nonce verification.
	 *
	 * @return string
	 */
	private function resolve_active_tab() {
		$allowed_tabs = array( 'dashboard', 'audit', 'settings', 'advanced', 'shortcodes' );
		$raw_tab      = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! is_string( $raw_tab ) || '' === $raw_tab ) {
			return 'dashboard';
		}

		$active_tab = sanitize_key( $raw_tab );
		if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
			return 'settings';
		}

		$raw_nonce = filter_input( INPUT_GET, 'advapafo_tab_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $raw_nonce ) || '' === $raw_nonce ) {
			return 'settings';
		}

		$nonce = sanitize_text_field( $raw_nonce );
		if ( ! wp_verify_nonce( $nonce, 'advapafo_tab_' . $active_tab ) ) {
			return 'settings';
		}

		return $active_tab;
	}

	/**
	 * Output hidden fields to preserve values from non-active tabs.
	 *
	 * @param string $active_tab Active tab key.
	 */
	private function render_preserved_hidden_fields( $active_tab ) {
		if ( 'advanced' === $active_tab ) {
			$enabled              = (bool) advapafo_get_setting( 'enabled', true );
			$show_setup_notice    = (bool) advapafo_get_setting( 'show_setup_notice', true );
			$integration_settings = class_exists( 'ADVAPAFO_Integration_Manager' ) && method_exists( 'ADVAPAFO_Integration_Manager', 'get_settings_registry' )
				? ADVAPAFO_Integration_Manager::get_settings_registry()
				: array();
			$roles                = (array) advapafo_get_setting( 'eligible_roles', array( 'administrator' ) );
			$max_passkeys         = absint( advapafo_get_setting( 'max_passkeys_per_user', 0 ) );
			$verification         = advapafo_get_setting( 'user_verification', 'required' );

			echo '<input type="hidden" name="advapafo_enabled" value="' . esc_attr( $enabled ? '1' : '0' ) . '" />';
			echo '<input type="hidden" name="advapafo_show_setup_notice" value="' . esc_attr( $show_setup_notice ? '1' : '0' ) . '" />';

			foreach ( $integration_settings as $integration_setting ) {
				if ( empty( $integration_setting['master_option'] ) ) {
					continue;
				}

				$master_option = sanitize_key( (string) $integration_setting['master_option'] );

				$dependency_active = ! empty( $integration_setting['dependency_active'] );
				$master_value      = $dependency_active
					? (bool) advapafo_get_setting( $master_option, ! empty( $integration_setting['default_master'] ) )
					: false;

				echo '<input type="hidden" name="' . esc_attr( $master_option ) . '" value="' . esc_attr( $master_value ? '1' : '0' ) . '" />';
			}

			foreach ( $roles as $role ) {
				echo '<input type="hidden" name="advapafo_eligible_roles[]" value="' . esc_attr( sanitize_key( $role ) ) . '" />';
			}
			echo '<input type="hidden" name="advapafo_max_passkeys_per_user" value="' . esc_attr( (string) $max_passkeys ) . '" />';
			echo '<input type="hidden" name="advapafo_user_verification" value="' . esc_attr( (string) $verification ) . '" />';
			return;
		}

		if ( 'settings' === $active_tab ) {
			$show_separator             = (bool) advapafo_get_setting( 'show_separator', true );
			$conditional_ui_enabled     = (bool) advapafo_get_setting( 'conditional_ui_enabled', false );
			$button_style               = advapafo_get_setting( 'button_style', 'black' );
			$rp_name                    = advapafo_get_setting( 'rp_name', '' );
			$rp_id                      = advapafo_get_setting( 'rp_id', '' );
			$login_challenge_ttl        = absint( advapafo_get_setting( 'login_challenge_ttl', 300 ) );
			$registration_challenge_ttl = absint( advapafo_get_setting( 'registration_challenge_ttl', 300 ) );
			$window                     = absint( advapafo_get_setting( 'rate_limit_window', 300 ) );
			$max_failures               = absint( advapafo_get_setting( 'rate_limit_max_failures', 5 ) );
			$lockout                    = absint( advapafo_get_setting( 'rate_limit_lockout', 900 ) );

			$activity_logging_enabled = (bool) advapafo_get_setting( 'activity_logging_enabled', true );

			echo '<input type="hidden" name="advapafo_show_separator" value="' . esc_attr( $show_separator ? '1' : '0' ) . '" />';
			echo '<input type="hidden" name="advapafo_conditional_ui_enabled" value="' . esc_attr( $conditional_ui_enabled ? '1' : '0' ) . '" />';
			echo '<input type="hidden" name="advapafo_activity_logging_enabled" value="' . esc_attr( $activity_logging_enabled ? '1' : '0' ) . '" />';
			echo '<input type="hidden" name="advapafo_button_style" value="' . esc_attr( (string) $button_style ) . '" />';
			echo '<input type="hidden" name="advapafo_rp_name" value="' . esc_attr( (string) $rp_name ) . '" />';
			echo '<input type="hidden" name="advapafo_rp_id" value="' . esc_attr( (string) $rp_id ) . '" />';
			echo '<input type="hidden" name="advapafo_login_challenge_ttl" value="' . esc_attr( (string) $login_challenge_ttl ) . '" />';
			echo '<input type="hidden" name="advapafo_registration_challenge_ttl" value="' . esc_attr( (string) $registration_challenge_ttl ) . '" />';
			echo '<input type="hidden" name="advapafo_rate_limit_window" value="' . esc_attr( (string) $window ) . '" />';
			echo '<input type="hidden" name="advapafo_rate_limit_max_failures" value="' . esc_attr( (string) $max_failures ) . '" />';
			echo '<input type="hidden" name="advapafo_rate_limit_lockout" value="' . esc_attr( (string) $lockout ) . '" />';
		}
	}

	/**
	 * Render the everyday settings tab.
	 */
	private function render_settings_tab() {
		$enabled                      = (bool) advapafo_get_setting( 'enabled', true );
		$show_setup_notice            = (bool) advapafo_get_setting( 'show_setup_notice', true );
		$eligible_roles               = (array) advapafo_get_setting( 'eligible_roles', array( 'administrator' ) );
		$max_passkeys                 = absint( advapafo_get_setting( 'max_passkeys_per_user', 0 ) );
		$verification                 = advapafo_get_setting( 'user_verification', 'required' );
		$roles                        = wp_roles()->roles;
		$enabled_overridden           = advapafo_is_setting_overridden( 'enabled' );
		$show_setup_notice_overridden = advapafo_is_setting_overridden( 'show_setup_notice' );
		$eligible_roles_overridden    = advapafo_is_setting_overridden( 'eligible_roles' );
		$max_passkeys_overridden      = advapafo_is_setting_overridden( 'max_passkeys_per_user' );
		$verification_overridden      = advapafo_is_setting_overridden( 'user_verification' );
		?>
		<section class="advapafo-section-header">
			<div>
				<p class="advapafo-eyebrow"><?php esc_html_e( 'Settings', 'advanced-passkey-login' ); ?></p>
				<h2><?php esc_html_e( 'Everyday passkey controls', 'advanced-passkey-login' ); ?></h2>
			</div>
			<span class="advapafo-badge"><?php esc_html_e( 'Recommended defaults', 'advanced-passkey-login' ); ?></span>
		</section>

		<div class="advapafo-card advapafo-card--setting">
			<div class="advapafo-setting-copy">
				<h3><?php esc_html_e( 'Enable passkeys', 'advanced-passkey-login' ); ?><?php advapafo_render_managed_setting_badge( 'enabled' ); ?></h3>
				<p><?php esc_html_e( 'Allow eligible users to register and sign in with secure device passkeys.', 'advanced-passkey-login' ); ?></p>
			</div>
			<label class="advapafo-switch">
				<input type="checkbox" name="advapafo_enabled" value="1" <?php checked( $enabled ); ?> <?php disabled( $enabled_overridden ); ?> />
				<span class="advapafo-switch__track"><span class="advapafo-switch__thumb"></span></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Enable passkeys', 'advanced-passkey-login' ); ?></span>
			</label>
			<?php if ( $enabled_overridden ) : ?>
				<input type="hidden" name="advapafo_enabled" value="<?php echo esc_attr( $enabled ? '1' : '0' ); ?>" />
			<?php endif; ?>
		</div>

		<div class="advapafo-card advapafo-card--setting">
			<div class="advapafo-setting-copy">
				<h3><?php esc_html_e( 'Show setup alert on profile', 'advanced-passkey-login' ); ?><?php advapafo_render_managed_setting_badge( 'show_setup_notice' ); ?></h3>
				<p><?php esc_html_e( 'Show or hide the admin alert that reminds users to set up a passkey on their profile page.', 'advanced-passkey-login' ); ?></p>
			</div>
			<label class="advapafo-switch">
				<input type="checkbox" name="advapafo_show_setup_notice" value="1" <?php checked( $show_setup_notice ); ?> <?php disabled( $show_setup_notice_overridden ); ?> />
				<span class="advapafo-switch__track"><span class="advapafo-switch__thumb"></span></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Show setup alert on profile', 'advanced-passkey-login' ); ?></span>
			</label>
			<?php if ( $show_setup_notice_overridden ) : ?>
				<input type="hidden" name="advapafo_show_setup_notice" value="<?php echo esc_attr( $show_setup_notice ? '1' : '0' ); ?>" />
			<?php endif; ?>
		</div>

		<?php
		if ( class_exists( 'ADVAPAFO_Integration_Manager' ) && method_exists( 'ADVAPAFO_Integration_Manager', 'get_settings_registry' ) ) {
			$integration_settings = ADVAPAFO_Integration_Manager::get_settings_registry();
			if ( ! empty( $integration_settings ) ) {
				?>
				<div class="advapafo-card">
					<div class="advapafo-card__header">
						<div>
							<h3><?php esc_html_e( 'Integration modules', 'advanced-passkey-login' ); ?></h3>
							<p><?php esc_html_e( 'Control each integration independently with master and auto-inject switches.', 'advanced-passkey-login' ); ?></p>
						</div>
					</div>
					<div class="advapafo-integration-settings-grid">
						<?php
						foreach ( $integration_settings as $integration_setting ) :
							$label                = ! empty( $integration_setting['label'] ) ? (string) $integration_setting['label'] : __( 'Integration', 'advanced-passkey-login' );
							$master_option        = ! empty( $integration_setting['master_option'] ) ? sanitize_key( (string) $integration_setting['master_option'] ) : '';
							$dependency_active    = ! empty( $integration_setting['dependency_active'] );
							$default_master       = ! empty( $integration_setting['default_master'] );
							$saved_master_enabled = $master_option ? (bool) advapafo_get_setting( $master_option, $default_master ) : false;
							$master_enabled       = $dependency_active ? $saved_master_enabled : false;
							$master_overridden    = $master_option && advapafo_is_setting_overridden( $master_option );
							?>
							<article class="advapafo-integration-setting-card<?php echo esc_attr( $dependency_active ? ' is-active' : ' is-missing' ); ?>">
								<header>
									<h4><?php echo esc_html( $label ); ?></h4>
									<span class="advapafo-integration-status <?php echo esc_attr( $dependency_active ? 'is-active' : 'is-missing' ); ?>">
										<?php echo $dependency_active ? esc_html__( 'Installed', 'advanced-passkey-login' ) : esc_html__( 'Not installed', 'advanced-passkey-login' ); ?>
									</span>
								</header>
								<p><?php esc_html_e( 'Enable this to add passkey blocks, shortcodes, and auto sign-in prompts.', 'advanced-passkey-login' ); ?></p>
								<?php if ( ! $dependency_active && $master_option ) : ?>
									<input type="hidden" name="<?php echo esc_attr( $master_option ); ?>" value="0" />
								<?php endif; ?>
								<div class="advapafo-integration-toggle-row">
									<label><?php esc_html_e( 'Enable module', 'advanced-passkey-login' ); ?><?php advapafo_render_managed_setting_badge( $master_option ); ?></label>
									<label class="advapafo-switch">
										<input type="checkbox" name="<?php echo esc_attr( $master_option ); ?>" value="1" <?php checked( $master_enabled ); ?> <?php disabled( ! $dependency_active || $master_overridden ); ?> />
										<span class="advapafo-switch__track"><span class="advapafo-switch__thumb"></span></span>
										<span class="screen-reader-text"><?php esc_html_e( 'Enable integration module', 'advanced-passkey-login' ); ?></span>
									</label>
									<?php if ( $master_overridden ) : ?>
										<input type="hidden" name="<?php echo esc_attr( $master_option ); ?>" value="<?php echo esc_attr( $master_enabled ? '1' : '0' ); ?>" />
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			}
		}
		?>

		<div class="advapafo-card">
			<div class="advapafo-card__header">
				<div>
					<h3><?php esc_html_e( 'Eligible user roles', 'advanced-passkey-login' ); ?><?php advapafo_render_managed_setting_badge( 'eligible_roles' ); ?></h3>
					<p><?php esc_html_e( 'Choose which WordPress roles can create and use passkeys.', 'advanced-passkey-login' ); ?></p>
				</div>
			</div>
			<div class="advapafo-role-grid">
				<?php foreach ( $roles as $role_key => $role ) : ?>
					<?php $role_label = translate_user_role( $role['name'] ); ?>
					<div class="advapafo-role-chip">
						<span class="advapafo-role-chip__label"><?php echo esc_html( $role_label ); ?></span>
						<label class="advapafo-switch">
							<input type="checkbox" name="advapafo_eligible_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $eligible_roles, true ) ); ?> <?php disabled( $eligible_roles_overridden ); ?> />
							<span class="advapafo-switch__track"><span class="advapafo-switch__thumb"></span></span>
							<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: WordPress user role name. */ __( 'Allow %s to use passkeys', 'advanced-passkey-login' ), $role_label ) ); ?></span>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $eligible_roles_overridden ) : ?>
				<?php foreach ( $eligible_roles as $eligible_role ) : ?>
					<input type="hidden" name="advapafo_eligible_roles[]" value="<?php echo esc_attr( sanitize_key( $eligible_role ) ); ?>" />
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div class="advapafo-card advapafo-grid-2">
			<div class="advapafo-field">
				<div class="advapafo-label-row">
					<label for="advapafo_max_passkeys_per_user"><?php esc_html_e( 'Passkeys per user', 'advanced-passkey-login' ); ?></label>
					<?php advapafo_render_managed_setting_badge( 'max_passkeys_per_user' ); ?>
				</div>
				<input id="advapafo_max_passkeys_per_user" class="regular-text" type="number" min="0" max="999999" name="advapafo_max_passkeys_per_user" value="<?php echo esc_attr( $max_passkeys ); ?>" <?php wp_readonly( $max_passkeys_overridden ); ?> />
				<p><?php esc_html_e( 'Maximum number of passkeys each user can register. Use 0 for no limit.', 'advanced-passkey-login' ); ?></p>
			</div>

			<div class="advapafo-field">
				<div class="advapafo-label-row">
					<label for="advapafo_user_verification"><?php esc_html_e( 'User verification', 'advanced-passkey-login' ); ?></label>
					<?php if ( $verification_overridden ) : ?>
						<?php advapafo_render_managed_setting_badge( 'user_verification' ); ?>
					<?php else : ?>
						<span class="advapafo-badge advapafo-badge--success"><?php esc_html_e( 'Recommended', 'advanced-passkey-login' ); ?></span>
					<?php endif; ?>
				</div>
				<select id="advapafo_user_verification" name="advapafo_user_verification" <?php disabled( $verification_overridden ); ?>>
					<option value="required" <?php selected( $verification, 'required' ); ?>><?php esc_html_e( 'Required — biometric or device PIN', 'advanced-passkey-login' ); ?></option>
					<option value="preferred" <?php selected( $verification, 'preferred' ); ?>><?php esc_html_e( 'Preferred — use when available', 'advanced-passkey-login' ); ?></option>
					<option value="discouraged" <?php selected( $verification, 'discouraged' ); ?>><?php esc_html_e( 'Discouraged — presence only', 'advanced-passkey-login' ); ?></option>
				</select>
				<?php if ( $verification_overridden ) : ?>
					<input type="hidden" name="advapafo_user_verification" value="<?php echo esc_attr( (string) $verification ); ?>" />
				<?php endif; ?>
				<p><?php esc_html_e( 'Required verification gives the strongest account protection.', 'advanced-passkey-login' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the dashboard overview tab.
	 */
	private function render_dashboard_tab() {
		global $wpdb;

		$credentials_table   = $this->get_credentials_table_for_audit();
		$users_with_passkeys = 0;
		$passkeys_total      = 0;

		if ( '' !== $credentials_table ) {
			$credentials_table_sql = $this->quote_table_name( $credentials_table );
			if ( '' !== $credentials_table_sql ) {
				$users_with_passkeys = (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT user_id) FROM ' . $credentials_table_sql . ' WHERE revoked_at IS NULL' ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
				$passkeys_total      = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $credentials_table_sql . ' WHERE revoked_at IS NULL' ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
			}
		}

		$activities_total = $this->count_combined_audit_login_rows();
		$blocked_attempts = $this->count_combined_login_event( 'login_password_blocked_passkey_only' ) + $this->count_combined_login_event( 'login_rate_limited' );

		$authenticator_totals = array();
		if ( '' !== $credentials_table ) {
			$credentials_table_sql = $this->quote_table_name( $credentials_table );
			if ( '' === $credentials_table_sql ) {
				$rows = array();
			} else {
				$rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
					'SELECT COALESCE(NULLIF(TRIM(credential_label), ""), "") AS credential_label, COALESCE(NULLIF(TRIM(credential_id_hash), ""), "") AS credential_hash, COUNT(*) AS total FROM ' . $credentials_table_sql . ' WHERE revoked_at IS NULL GROUP BY credential_label, credential_id_hash ORDER BY total DESC LIMIT 300',
					ARRAY_A
				);
			}

			foreach ( $rows as $row ) {
				$raw_label       = (string) ( $row['credential_label'] ?? '' );
				$credential_hash = strtolower( trim( (string) ( $row['credential_hash'] ?? '' ) ) );
				$raw_provider    = '';
				$raw_aaguid      = '';

				if ( '' !== $credential_hash ) {
					$raw_provider = $this->lookup_credential_provider_hint_for_hash( $credential_hash );
					$raw_aaguid   = $this->lookup_credential_aaguid_for_hash( $credential_hash );
				}

				$provider_meta = $this->resolve_authenticator_metadata_for_reporting( $raw_provider, $raw_label, $raw_aaguid );
				$provider      = (string) ( $provider_meta['label'] ?? 'Unknown Authenticator' );
				if ( ! isset( $authenticator_totals[ $provider ] ) ) {
					$authenticator_totals[ $provider ] = 0;
				}
				$authenticator_totals[ $provider ] += (int) ( $row['total'] ?? 0 );
			}
		}

		arsort( $authenticator_totals );
		$authenticator_rows = array();
		foreach ( $authenticator_totals as $provider => $total ) {
			$authenticator_rows[] = array(
				'provider'     => (string) $provider,
				'provider_key' => $this->normalize_authenticator_provider_key( (string) $provider ),
				'total'        => (int) $total,
			);
			if ( count( $authenticator_rows ) >= 8 ) {
				break;
			}
		}

		$authenticator_chart_labels      = array();
		$authenticator_chart_series      = array();
		$authenticator_chart_other_total = 0;
		$authenticator_chart_limit       = 5;
		$authenticator_chart_index       = 0;

		foreach ( $authenticator_totals as $provider => $total ) {
			if ( $authenticator_chart_index < $authenticator_chart_limit ) {
				$authenticator_chart_labels[] = (string) $provider;
				$authenticator_chart_series[] = (int) $total;
			} else {
				$authenticator_chart_other_total += (int) $total;
			}
			++$authenticator_chart_index;
		}

		if ( $authenticator_chart_other_total > 0 ) {
			$authenticator_chart_labels[] = __( 'Other', 'advanced-passkey-login' );
			$authenticator_chart_series[] = (int) $authenticator_chart_other_total;
		}

		$authenticator_chart_payload = array(
			'labels' => $authenticator_chart_labels,
			'series' => $authenticator_chart_series,
		);

		$login_rows                 = $this->get_combined_audit_login_rows_for_last_days( 14, 600 );
		$dashboard_allowed_statuses = array( 'success', 'blocked', 'failed' );
		$dashboard_activity_rows    = array();
		foreach ( $login_rows as $login_row ) {
			$event_type  = (string) ( $login_row['event_type'] ?? '' );
			$status_meta = $this->classify_audit_event_for_activity_feed( $event_type );
			$status_key  = (string) ( $status_meta['key'] ?? '' );
			if ( in_array( $status_key, $dashboard_allowed_statuses, true ) ) {
				$dashboard_activity_rows[] = $login_row;
			}
		}
		$dashboard_activity_rows = array_slice( $dashboard_activity_rows, 0, 8 );

		$chart_days           = 14;
		$chart_success_points = array();
		$chart_blocked_points = array();
		$chart_failed_points  = array();
		$chart_labels         = array();
		$chart_timezone       = wp_timezone();
		$day_success_buckets  = array();
		$day_blocked_buckets  = array();
		$day_failed_buckets   = array();

		for ( $offset = $chart_days - 1; $offset >= 0; $offset-- ) {
			$bucket_ts                          = strtotime( '-' . $offset . ' days', time() );
			$bucket_key                         = wp_date( 'Y-m-d', $bucket_ts, $chart_timezone );
			$day_success_buckets[ $bucket_key ] = 0;
			$day_blocked_buckets[ $bucket_key ] = 0;
			$day_failed_buckets[ $bucket_key ]  = 0;
		}

		$chart_source_rows = $this->get_combined_audit_login_rows( 300 );
		foreach ( $chart_source_rows as $row ) {
			$event_type   = (string) ( $row['event_type'] ?? '' );
			$event_bucket = $this->classify_audit_event_for_activity_trend( $event_type );
			if ( '' === $event_bucket ) {
				continue;
			}

			$timestamp_raw = (string) ( $row['log_timestamp'] ?? '' );
			$timestamp_ts  = $this->utc_datetime_to_timestamp( $timestamp_raw );
			if ( $timestamp_ts <= 0 ) {
				continue;
			}

			$day_key = wp_date( 'Y-m-d', $timestamp_ts, $chart_timezone );
			if ( 'success' === $event_bucket && array_key_exists( $day_key, $day_success_buckets ) ) {
				++$day_success_buckets[ $day_key ];
			} elseif ( 'blocked' === $event_bucket && array_key_exists( $day_key, $day_blocked_buckets ) ) {
				++$day_blocked_buckets[ $day_key ];
			} elseif ( 'failed' === $event_bucket && array_key_exists( $day_key, $day_failed_buckets ) ) {
				++$day_failed_buckets[ $day_key ];
			}
		}

		foreach ( $day_success_buckets as $day_key => $count ) {
			$chart_success_points[] = (int) $count;
			$chart_blocked_points[] = (int) ( $day_blocked_buckets[ $day_key ] ?? 0 );
			$chart_failed_points[]  = (int) ( $day_failed_buckets[ $day_key ] ?? 0 );
			$chart_labels[]         = wp_date( 'M j', strtotime( $day_key ), $chart_timezone );
		}

		$activity_chart_payload = array(
			'labels'  => $chart_labels,
			'success' => $chart_success_points,
			'blocked' => $chart_blocked_points,
			'failed'  => $chart_failed_points,
		);
		?>
		<section class="wpkpro-section-header">
			<div>
				<p class="wpkpro-eyebrow"><?php esc_html_e( 'Dashboard', 'advanced-passkey-login' ); ?></p>
				<h2><?php esc_html_e( 'Security activity overview', 'advanced-passkey-login' ); ?></h2>
			</div>
		</section>

		<div class="wpkpro-audit-stats-grid wpkpro-dashboard-kpis">
			<article class="wpkpro-audit-stat wpkpro-audit-stat--users">
				<h3><?php esc_html_e( 'Users with passkeys', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $users_with_passkeys ) ); ?></p>
			</article>
			<article class="wpkpro-audit-stat wpkpro-audit-stat--passkeys">
				<h3><?php esc_html_e( 'Passkeys stored', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $passkeys_total ) ); ?></p>
			</article>
			<article class="wpkpro-audit-stat wpkpro-audit-stat--passkey-logins">
				<h3><?php esc_html_e( 'Activities', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $activities_total ) ); ?></p>
			</article>
			<article class="wpkpro-audit-stat wpkpro-audit-stat--blocked-attempts">
				<h3><?php esc_html_e( 'Blocked attempts', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $blocked_attempts ) ); ?></p>
			</article>
		</div>

		<div class="wpkpro-dashboard-grid">
			<div class="wpkpro-card wpkpro-dashboard-card">
				<div class="wpkpro-card__header">
					<h3><?php esc_html_e( 'Overview of Authenticators', 'advanced-passkey-login' ); ?></h3>
				</div>
				<div class="wpkpro-card__body">
					<div class="wpkpro-dashboard-auth-chart-wrap">
						<div class="wpkpro-dashboard-auth-chart-labels">
							<strong><?php esc_html_e( 'Authenticator Distribution', 'advanced-passkey-login' ); ?></strong>
							<span><?php esc_html_e( 'Top providers', 'advanced-passkey-login' ); ?></span>
						</div>
						<?php if ( empty( $authenticator_chart_series ) ) : ?>
							<p class="wpkpro-dashboard-auth-chart-empty"><?php esc_html_e( 'No authenticator records yet.', 'advanced-passkey-login' ); ?></p>
						<?php else : ?>
							<div class="wpkpro-dashboard-auth-chart" data-auth-chart="<?php echo esc_attr( wp_json_encode( $authenticator_chart_payload ) ); ?>"></div>
						<?php endif; ?>
					</div>

					<div class="wpkpro-dashboard-auth-body">
						<?php if ( empty( $authenticator_rows ) ) : ?>
							<p><?php esc_html_e( 'No authenticator records yet.', 'advanced-passkey-login' ); ?></p>
						<?php else : ?>
							<ul class="wpkpro-dashboard-auth-list">
								<?php foreach ( $authenticator_rows as $row ) : ?>
									<li>
										<div>
											<?php echo $this->render_authenticator_provider_badge( (string) $row['provider'], array( 'provider_key' => (string) ( $row['provider_key'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_authenticator_provider_badge() returns fully escaped, internally-controlled markup. ?>
											<span class="wpkpro-dashboard-auth-total"><?php echo esc_html( number_format_i18n( (int) $row['total'] ) ); ?></span>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="wpkpro-card wpkpro-dashboard-card">
				<div class="wpkpro-card__header">
					<h3><?php esc_html_e( 'Last Login Activity', 'advanced-passkey-login' ); ?></h3>
				</div>
				<div class="wpkpro-card__body">
					<div class="wpkpro-dashboard-activity-chart-wrap">
						<div class="wpkpro-dashboard-activity-chart-labels">
							<strong><?php esc_html_e( 'Login Activity Trend', 'advanced-passkey-login' ); ?></strong>
							<span><?php esc_html_e( 'Last 14 days', 'advanced-passkey-login' ); ?></span>
						</div>
						<div class="wpkpro-dashboard-activity-chart" data-activity-chart="<?php echo esc_attr( wp_json_encode( $activity_chart_payload ) ); ?>"></div>
					</div>

					<div class="wpkpro-dashboard-activity-body">
						<?php if ( empty( $dashboard_activity_rows ) ) : ?>
							<p><?php esc_html_e( 'No login activity recorded yet.', 'advanced-passkey-login' ); ?></p>
						<?php else : ?>
							<table class="wpkpro-dashboard-activity-table">
								<tbody>
									<?php foreach ( $dashboard_activity_rows as $row ) : ?>
										<?php
										$data = json_decode( (string) ( $row['log_data'] ?? '' ), true );
										if ( ! is_array( $data ) ) {
											$data = array();
										}

										$event        = (string) ( $row['event_type'] ?? '' );
										$status_meta  = $this->classify_audit_event_for_activity_feed( $event );
										$status_key   = (string) ( $status_meta['key'] ?? 'info' );
										$status_label = (string) ( $status_meta['label'] ?? __( 'Info', 'advanced-passkey-login' ) );

										$method     = __( 'Other', 'advanced-passkey-login' );
										$method_key = 'other';
										if ( 'login_success' === $event ) {
											$method     = __( 'Passkey', 'advanced-passkey-login' );
											$method_key = 'passkey';
										} elseif ( 'login_password_success' === $event || 'login_password_blocked_passkey_only' === $event || 'login_bypass_cookie_used' === $event ) {
											$method     = __( 'Password', 'advanced-passkey-login' );
											$method_key = 'password';
										} elseif ( 'login_rate_limited' === $event ) {
											$method     = __( 'Passkey', 'advanced-passkey-login' );
											$method_key = 'passkey';
										} elseif ( in_array( $event, array( 'login_failed', 'login_credential_mismatch', 'login_begin_failed' ), true ) ) {
											$method     = __( 'Passkey', 'advanced-passkey-login' );
											$method_key = 'passkey';
										} elseif ( in_array( $event, array( 'attestation_policy_pass', 'trusted_device_match', 'trusted_device_first_seen', 'trusted_device_marked_trusted', 'session_hardening_other_sessions_terminated', 'attestation_policy_dry_run_block', 'attestation_policy_aaguid_dry_run_block', 'trusted_device_mismatch', 'attestation_policy_blocked', 'attestation_policy_aaguid_blocked', 'trusted_device_revoked' ), true ) ) {
											$method     = __( 'Security Policy', 'advanced-passkey-login' );
											$method_key = 'security';
										}

										$authenticator     = '—';
										$authenticator_key = 'unknown';
										if ( ! in_array( $method_key, array( 'password', 'security' ), true ) ) {
											$credential_hash   = isset( $data['credential_hash'] ) ? (string) $data['credential_hash'] : '';
											$raw_authenticator = isset( $data['authenticator'] ) ? (string) $data['authenticator'] : '';
											$raw_label         = '';
											$raw_aaguid        = isset( $data['aaguid'] ) ? (string) $data['aaguid'] : '';
											if ( '' === $raw_authenticator && '' !== $credential_hash ) {
												$raw_authenticator = $this->lookup_credential_provider_hint_for_hash( $credential_hash );
											}
											if ( '' === $raw_authenticator && '' !== $credential_hash ) {
												$raw_authenticator = $this->lookup_credential_label_for_hash( $credential_hash );
											}
											if ( '' === $raw_aaguid && '' !== $credential_hash ) {
												$raw_aaguid = $this->lookup_credential_aaguid_for_hash( $credential_hash );
											}
											if ( isset( $data['authenticator_label'] ) ) {
												$raw_label = (string) $data['authenticator_label'];
											}

											$meta              = $this->resolve_authenticator_metadata_for_reporting( $raw_authenticator, $raw_label, $raw_aaguid );
											$authenticator     = (string) ( $meta['label'] ?? '' );
											$authenticator_key = (string) ( $meta['key'] ?? 'unknown' );
											if ( '' === $authenticator ) {
												$authenticator = __( 'Unknown Authenticator', 'advanced-passkey-login' );
											}
										}

										$user_label = isset( $data['user_login'] ) ? (string) $data['user_login'] : '';
										if ( '' === $user_label && isset( $data['user_id'] ) ) {
											$activity_user = get_userdata( (int) $data['user_id'] );
											if ( $activity_user instanceof WP_User ) {
												$user_label = (string) $activity_user->user_login;
											}
										}
										if ( '' === $user_label ) {
											$user_label = __( 'User activity', 'advanced-passkey-login' );
										}

										$timestamp_raw      = (string) ( $row['log_timestamp'] ?? '' );
										$timestamp_display  = $this->format_utc_datetime_for_display( $timestamp_raw );
										$timestamp_relative = $this->format_utc_relative_for_display( $timestamp_raw );
										?>
										<tr>
											<td>
												<strong><?php echo esc_html( $user_label ); ?></strong>
												<span class="wpkpro-dashboard-activity-meta">
													<span class="wpkpro-dashboard-status-pill wpkpro-dashboard-status-pill--<?php echo esc_attr( $status_key ); ?>">
														<span class="wpkpro-dashboard-status-dot" aria-hidden="true"></span>
														<?php echo esc_html( $status_label ); ?>
													</span>
													<span class="wpkpro-dashboard-method-pill wpkpro-dashboard-method-pill--<?php echo esc_attr( $method_key ); ?>"><?php echo esc_html( $method ); ?></span>
													<?php if ( '—' === $authenticator ) : ?>
														&mdash;
													<?php else : ?>
														<?php echo $this->render_authenticator_provider_badge( $authenticator, array( 'provider_key' => $authenticator_key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_authenticator_provider_badge() returns fully escaped, internally-controlled markup. ?>
													<?php endif; ?>
												</span>
												<span class="wpkpro-dashboard-activity-time"><?php echo esc_html( $timestamp_display ); ?><?php echo '' !== $timestamp_relative ? esc_html( ' | ' . $timestamp_relative ) : ''; ?></span>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the full audit log tab: KPIs, authenticator usage, and paginated login activity.
	 */
	private function render_audit_tab() {
		global $wpdb;

		$credentials_table        = $this->get_credentials_table_for_audit();
		$activity_logging_enabled = $this->is_activity_logging_enabled();

		$users_with_passkeys = 0;
		$passkeys_total      = 0;
		if ( '' !== $credentials_table ) {
			$credentials_table_sql = $this->quote_table_name( $credentials_table );
			if ( '' !== $credentials_table_sql ) {
				$users_with_passkeys = (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT user_id) FROM ' . $credentials_table_sql . ' WHERE revoked_at IS NULL' ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
				$passkeys_total      = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $credentials_table_sql . ' WHERE revoked_at IS NULL' ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
			}
		}

		$passkey_logins   = $this->count_combined_login_event( 'login_success' );
		$password_logins  = $this->count_combined_login_event( 'login_password_success' );
		$blocked_attempts = $this->count_combined_login_event( 'login_password_blocked_passkey_only' );
		$bypassed_logins  = $this->count_combined_login_event( 'login_bypass_cookie_used' );

		$authenticator_rows = array();
		if ( '' !== $credentials_table ) {
			$credentials_table_sql = $this->quote_table_name( $credentials_table );
			$rows                  = array();
			if ( '' !== $credentials_table_sql ) {
				$rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
					'SELECT COALESCE(NULLIF(TRIM(credential_label), ""), "") AS credential_label, COALESCE(NULLIF(TRIM(credential_id_hash), ""), "") AS credential_hash, COUNT(*) AS total FROM ' . $credentials_table_sql . ' WHERE revoked_at IS NULL GROUP BY credential_label, credential_id_hash ORDER BY total DESC LIMIT 300',
					ARRAY_A
				);
			}

			$grouped = array();
			foreach ( $rows as $row ) {
				$raw_label       = (string) ( $row['credential_label'] ?? '' );
				$credential_hash = strtolower( trim( (string) ( $row['credential_hash'] ?? '' ) ) );
				$raw_provider    = '';
				$raw_aaguid      = '';

				if ( '' !== $credential_hash ) {
					$raw_provider = $this->lookup_credential_provider_hint_for_hash( $credential_hash );
					$raw_aaguid   = $this->lookup_credential_aaguid_for_hash( $credential_hash );
				}

				$provider_meta = $this->resolve_authenticator_metadata_for_reporting( $raw_provider, $raw_label, $raw_aaguid );
				$provider      = (string) ( $provider_meta['label'] ?? 'Unknown Authenticator' );
				if ( ! isset( $grouped[ $provider ] ) ) {
					$grouped[ $provider ] = 0;
				}
				$grouped[ $provider ] += (int) ( $row['total'] ?? 0 );
			}

			arsort( $grouped );
			foreach ( $grouped as $provider => $total ) {
				$authenticator_rows[] = array(
					'provider' => (string) $provider,
					'total'    => (int) $total,
				);
			}
		}

		$audit_per_page    = 50;
		$audit_page        = isset( $_GET['advapafo_audit_page'] ) ? max( 1, absint( wp_unslash( $_GET['advapafo_audit_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination cursor on an admin-only screen; tab access itself is nonce-verified by resolve_active_tab().
		$audit_total_rows  = $this->count_combined_audit_login_rows();
		$audit_total_pages = max( 1, (int) ceil( $audit_total_rows / $audit_per_page ) );
		if ( $audit_page > $audit_total_pages ) {
			$audit_page = $audit_total_pages;
		}
		$audit_offset = ( $audit_page - 1 ) * $audit_per_page;
		$login_rows   = $this->get_combined_audit_login_rows( $audit_per_page, $audit_offset );

		$audit_start = 0;
		$audit_end   = 0;
		if ( $audit_total_rows > 0 ) {
			$audit_start = $audit_offset + 1;
			$audit_end   = min( $audit_offset + count( $login_rows ), $audit_total_rows );
		}

		$audit_pagination_links = array();
		if ( $audit_total_pages > 1 ) {
			$audit_page_base = wp_nonce_url(
				add_query_arg(
					array(
						'page'                => $this->page_slug,
						'tab'                 => 'audit',
						'advapafo_audit_page' => '%#%',
					),
					admin_url( 'options-general.php' )
				),
				'advapafo_tab_audit',
				'advapafo_tab_nonce'
			);

			$audit_pagination_links = paginate_links(
				array(
					'base'      => $audit_page_base,
					'format'    => '',
					'current'   => $audit_page,
					'total'     => $audit_total_pages,
					'type'      => 'array',
					'prev_text' => __( 'Previous', 'advanced-passkey-login' ),
					'next_text' => __( 'Next', 'advanced-passkey-login' ),
				)
			);
		}
		?>
		<section class="wpkpro-section-header">
			<div>
				<p class="wpkpro-eyebrow"><?php esc_html_e( 'Audit', 'advanced-passkey-login' ); ?></p>
				<h2><?php esc_html_e( 'Full audit log', 'advanced-passkey-login' ); ?></h2>
			</div>
		</section>

		<div class="wpkpro-audit-stats-grid wpkpro-audit-stats-grid--login-kpis">
			<article class="wpkpro-audit-stat wpkpro-audit-stat--users">
				<h3><?php esc_html_e( 'Users with passkeys', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $users_with_passkeys ) ); ?></p>
			</article>
			<article class="wpkpro-audit-stat wpkpro-audit-stat--passkeys">
				<h3><?php esc_html_e( 'Passkeys stored', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $passkeys_total ) ); ?></p>
			</article>
			<article class="wpkpro-audit-stat wpkpro-audit-stat--passkey-logins">
				<h3><?php esc_html_e( 'Logins with passkey', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $passkey_logins ) ); ?></p>
			</article>
			<article class="wpkpro-audit-stat wpkpro-audit-stat--password-logins">
				<h3><?php esc_html_e( 'Password logins', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $password_logins ) ); ?></p>
			</article>
			<article class="wpkpro-audit-stat wpkpro-audit-stat--blocked-attempts">
				<h3><?php esc_html_e( 'Blocked attempts', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $blocked_attempts ) ); ?></p>
			</article>
			<article class="wpkpro-audit-stat wpkpro-audit-stat--bypassed-logins">
				<h3><?php esc_html_e( 'Bypassed logins', 'advanced-passkey-login' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $bypassed_logins ) ); ?></p>
			</article>
		</div>

		<div class="wpkpro-card">
			<div class="wpkpro-card__header">
				<div>
					<h3><?php esc_html_e( 'Authenticator Usage', 'advanced-passkey-login' ); ?></h3>
					<p><?php esc_html_e( 'Most-used authenticators grouped by normalized provider.', 'advanced-passkey-login' ); ?></p>
				</div>
				<div class="wpkpro-audit-search-wrap">
					<label for="advapafo-authenticator-search" class="screen-reader-text"><?php esc_html_e( 'Search authenticator table', 'advanced-passkey-login' ); ?></label>
					<input id="advapafo-authenticator-search" class="wpkpro-audit-search" type="search" data-table-target="advapafo-authenticator-table" placeholder="<?php esc_attr_e( 'Search authenticators...', 'advanced-passkey-login' ); ?>" />
				</div>
			</div>
			<div class="wpkpro-card__body">
				<div class="wpkpro-audit-table-wrap">
					<table id="advapafo-authenticator-table" class="wpkpro-audit-table widefat striped" data-enhanced-table="1">
						<thead>
							<tr>
								<th scope="col" data-sort="text"><?php esc_html_e( 'Provider', 'advanced-passkey-login' ); ?></th>
								<th scope="col" data-sort="number"><?php esc_html_e( 'Count', 'advanced-passkey-login' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $authenticator_rows ) ) : ?>
								<tr><td colspan="2"><?php esc_html_e( 'No authenticator data available yet.', 'advanced-passkey-login' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $authenticator_rows as $row ) : ?>
									<tr>
										<td><?php echo $this->render_authenticator_provider_badge( (string) $row['provider'], array( 'provider_key' => $this->normalize_authenticator_provider_key( (string) $row['provider'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_authenticator_provider_badge() returns sanitized badge HTML. ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $row['total'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="wpkpro-card">
			<div class="wpkpro-card__header">
				<div>
					<h3><?php esc_html_e( 'Detailed Login Activity', 'advanced-passkey-login' ); ?></h3>
					<p><?php esc_html_e( 'Latest passkey and password login events.', 'advanced-passkey-login' ); ?></p>
				</div>
				<?php if ( $activity_logging_enabled ) : ?>
				<div class="wpkpro-audit-controls">
					<div class="wpkpro-audit-search-wrap">
						<label for="advapafo-login-search" class="screen-reader-text"><?php esc_html_e( 'Search login activity table', 'advanced-passkey-login' ); ?></label>
						<input id="advapafo-login-search" class="wpkpro-audit-search" type="search" data-table-target="advapafo-login-activity-table" placeholder="<?php esc_attr_e( 'Search login activity...', 'advanced-passkey-login' ); ?>" />
					</div>
					<div class="wpkpro-audit-filter-wrap">
						<label for="advapafo-login-method-filter" class="screen-reader-text"><?php esc_html_e( 'Filter login activity by method', 'advanced-passkey-login' ); ?></label>
						<select id="advapafo-login-method-filter" class="wpkpro-audit-filter" data-table-filter-target="advapafo-login-activity-table" data-table-filter-key="methodKey">
							<option value="all"><?php esc_html_e( 'All Methods', 'advanced-passkey-login' ); ?></option>
							<option value="passkey"><?php esc_html_e( 'Passkey', 'advanced-passkey-login' ); ?></option>
							<option value="password"><?php esc_html_e( 'Password', 'advanced-passkey-login' ); ?></option>
							<option value="other"><?php esc_html_e( 'Other', 'advanced-passkey-login' ); ?></option>
						</select>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<div class="wpkpro-card__body">
				<?php if ( ! $activity_logging_enabled ) : ?>
					<div class="wpkpro-flash wpkpro-flash--warning" role="status">
						<p><?php esc_html_e( 'Activity logging has been disabled in Settings > Advanced Passkeys > Advanced. Re-enable it to restore login activity charts and detailed audit rows.', 'advanced-passkey-login' ); ?></p>
					</div>
				<?php else : ?>
				<p class="description">
					<?php
					if ( $audit_total_rows > 0 ) {
						printf(
							/* translators: 1: first visible row number, 2: last visible row number, 3: total rows */
							esc_html__( 'Showing %1$s-%2$s of %3$s events.', 'advanced-passkey-login' ),
							esc_html( number_format_i18n( $audit_start ) ),
							esc_html( number_format_i18n( $audit_end ) ),
							esc_html( number_format_i18n( $audit_total_rows ) )
						);
					} else {
						esc_html_e( 'No events found.', 'advanced-passkey-login' );
					}
					?>
				</p>
				<div class="wpkpro-audit-table-wrap">
					<table id="advapafo-login-activity-table" class="wpkpro-audit-table widefat striped" data-enhanced-table="1">
						<thead>
							<tr>
								<th scope="col" data-sort="number"><?php esc_html_e( 'ID', 'advanced-passkey-login' ); ?></th>
								<th scope="col" data-sort="text"><?php esc_html_e( 'Method', 'advanced-passkey-login' ); ?></th>
								<th scope="col" data-sort="text"><?php esc_html_e( 'Status', 'advanced-passkey-login' ); ?></th>
								<th scope="col" data-sort="date"><?php esc_html_e( 'Timestamp', 'advanced-passkey-login' ); ?></th>
								<th scope="col" data-sort="text"><?php esc_html_e( 'Authenticator', 'advanced-passkey-login' ); ?></th>
								<th scope="col" data-sort="text"><?php esc_html_e( 'User Ref', 'advanced-passkey-login' ); ?></th>
								<th scope="col" data-sort="text"><?php esc_html_e( 'IP Address', 'advanced-passkey-login' ); ?></th>
								<th scope="col" data-sort="text"><?php esc_html_e( 'Event', 'advanced-passkey-login' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $login_rows ) ) : ?>
								<tr><td colspan="8"><?php esc_html_e( 'No login activity has been logged yet.', 'advanced-passkey-login' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $login_rows as $row ) : ?>
									<?php
									$data = json_decode( (string) ( $row['log_data'] ?? '' ), true );
									if ( ! is_array( $data ) ) {
										$data = array();
									}

									$event      = (string) ( $row['event_type'] ?? '' );
									$method     = __( 'Other', 'advanced-passkey-login' );
									$method_key = 'other';
									$status     = __( 'Info', 'advanced-passkey-login' );
									$status_key = 'info';

									if ( 'login_success' === $event ) {
										$method     = __( 'Passkey', 'advanced-passkey-login' );
										$method_key = 'passkey';
										$status     = __( 'Success', 'advanced-passkey-login' );
										$status_key = 'success';
									} elseif ( 'login_password_success' === $event ) {
										$method     = __( 'Password', 'advanced-passkey-login' );
										$method_key = 'password';
										$status     = __( 'Success', 'advanced-passkey-login' );
										$status_key = 'success';
									} elseif ( 'login_password_blocked_passkey_only' === $event ) {
										$method     = __( 'Password', 'advanced-passkey-login' );
										$method_key = 'password';
										$status     = __( 'Blocked', 'advanced-passkey-login' );
										$status_key = 'blocked';
									} elseif ( 'login_bypass_cookie_used' === $event ) {
										$method     = __( 'Password', 'advanced-passkey-login' );
										$method_key = 'password';
										$status     = __( 'Bypassed', 'advanced-passkey-login' );
										$status_key = 'bypassed';
									} elseif ( in_array( $event, array( 'login_failed', 'login_credential_mismatch', 'login_begin_failed' ), true ) ) {
										$method     = __( 'Passkey', 'advanced-passkey-login' );
										$method_key = 'passkey';
										$status     = __( 'Failed', 'advanced-passkey-login' );
										$status_key = 'failed';
									}

									$authenticator     = '—';
									$authenticator_key = 'unknown';
									if ( in_array( $method_key, array( 'passkey', 'other' ), true ) ) {
										$credential_hash   = $this->extract_credential_hash_from_log_payload( $data );
										$raw_authenticator = isset( $data['authenticator'] ) ? (string) $data['authenticator'] : '';
										$raw_label         = isset( $data['authenticator_label'] ) ? (string) $data['authenticator_label'] : '';
										$raw_aaguid        = isset( $data['aaguid'] ) ? (string) $data['aaguid'] : '';
										if ( '' === $raw_authenticator && '' !== $credential_hash ) {
											$raw_authenticator = $this->lookup_credential_provider_hint_for_hash( $credential_hash );
										}
										if ( '' === $raw_label && '' !== $credential_hash ) {
											$raw_label = $this->lookup_credential_label_for_hash( $credential_hash );
										}
										if ( '' === $raw_aaguid && '' !== $credential_hash ) {
											$raw_aaguid = $this->lookup_credential_aaguid_for_hash( $credential_hash );
										}
										$meta              = $this->resolve_authenticator_metadata_for_reporting( $raw_authenticator, $raw_label, $raw_aaguid );
										$authenticator     = (string) ( $meta['label'] ?? '' );
										$authenticator_key = (string) ( $meta['key'] ?? 'unknown' );

										if ( '' === $authenticator ) {
											$authenticator = __( 'Unknown Authenticator', 'advanced-passkey-login' );
										}
									}

									$user_ref    = isset( $data['user_ref'] ) ? (string) $data['user_ref'] : '';
									$user_ref_ui = '—';
									if ( '' !== $user_ref ) {
										$user_ref_ui = strlen( $user_ref ) > 12 ? substr( $user_ref, 0, 12 ) . '...' : $user_ref;
									}
									$ip_ui = $this->resolve_audit_ip_for_display( $data );
									?>
									<tr data-method-key="<?php echo esc_attr( $method_key ); ?>">
										<td><?php echo esc_html( (string) ( $row['id'] ?? '' ) ); ?></td>
										<td><?php echo esc_html( $method ); ?></td>
										<td>
											<span class="wpkpro-audit-status-pill wpkpro-audit-status-pill--<?php echo esc_attr( $status_key ); ?>">
												<?php echo esc_html( $status ); ?>
											</span>
										</td>
										<td><?php echo esc_html( $this->format_utc_datetime_for_display( (string) ( $row['log_timestamp'] ?? '' ) ) ); ?></td>
										<td>
											<?php if ( '—' === $authenticator ) : ?>
												&mdash;
											<?php else : ?>
												<?php echo $this->render_authenticator_provider_badge( $authenticator, array( 'provider_key' => $authenticator_key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_authenticator_provider_badge() returns sanitized badge HTML. ?>
											<?php endif; ?>
										</td>
										<td><code><?php echo esc_html( $user_ref_ui ); ?></code></td>
										<td><code><?php echo esc_html( $ip_ui ); ?></code></td>
										<td><?php echo esc_html( $event ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
					<?php if ( ! empty( $audit_pagination_links ) ) : ?>
					<nav class="tablenav-pages" aria-label="<?php esc_attr_e( 'Audit log pagination', 'advanced-passkey-login' ); ?>">
						<ul class="page-numbers">
							<?php foreach ( $audit_pagination_links as $audit_link ) : ?>
								<li><?php echo wp_kses_post( $audit_link ); ?></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="wpkpro-card wpkpro-metric-info-card">
			<div class="wpkpro-card__header">
				<div>
					<h3><?php esc_html_e( 'Metric definitions', 'advanced-passkey-login' ); ?></h3>
					<p><?php esc_html_e( 'Quick reference for the summary metrics shown above.', 'advanced-passkey-login' ); ?></p>
				</div>
			</div>
			<div class="wpkpro-card__body">
				<ul class="wpkpro-metric-info-list">
					<li><strong><?php esc_html_e( 'Users with passkeys:', 'advanced-passkey-login' ); ?></strong> <?php esc_html_e( 'Unique users who currently have at least one active (non-revoked) passkey registered.', 'advanced-passkey-login' ); ?></li>
					<li><strong><?php esc_html_e( 'Passkeys stored:', 'advanced-passkey-login' ); ?></strong> <?php esc_html_e( 'Total number of active passkeys stored across all users.', 'advanced-passkey-login' ); ?></li>
					<li><strong><?php esc_html_e( 'Logins with passkey:', 'advanced-passkey-login' ); ?></strong> <?php esc_html_e( 'Count of successful passkey login events recorded in the audit log.', 'advanced-passkey-login' ); ?></li>
					<li><strong><?php esc_html_e( 'Password logins:', 'advanced-passkey-login' ); ?></strong> <?php esc_html_e( 'Count of successful password login events recorded in the audit log.', 'advanced-passkey-login' ); ?></li>
					<li><strong><?php esc_html_e( 'Blocked attempts:', 'advanced-passkey-login' ); ?></strong> <?php esc_html_e( 'Password attempts blocked by passkey-only enforcement policies.', 'advanced-passkey-login' ); ?></li>
					<li><strong><?php esc_html_e( 'Bypassed logins:', 'advanced-passkey-login' ); ?></strong> <?php esc_html_e( 'Logins where the bypass cookie flow was used, as recorded in the audit log.', 'advanced-passkey-login' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Resolve credential label from a stored credential hash.
	 *
	 * @param string $credential_hash Credential SHA-256 hash.
	 * @return string
	 */
	private function lookup_credential_label_for_hash( string $credential_hash ): string {
		static $cache    = array();
		$credential_hash = strtolower( trim( $credential_hash ) );
		if ( '' === $credential_hash ) {
			return '';
		}

		if ( isset( $cache[ $credential_hash ] ) ) {
			return (string) $cache[ $credential_hash ];
		}

		global $wpdb;
		$table_name = $this->get_credentials_table_for_audit();
		$table_sql  = $this->quote_table_name( $table_name );
		if ( '' === $table_sql ) {
			$cache[ $credential_hash ] = '';
			return '';
		}

		$label = (string) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off label lookup on plugin-owned custom table.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
				'SELECT COALESCE(NULLIF(TRIM(credential_label), ""), "") FROM ' . $table_sql . ' WHERE credential_id_hash = %s LIMIT 1',
				$credential_hash
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().

		$cache[ $credential_hash ] = $label;
		return $label;
	}

	/**
	 * Resolve credential AAGUID from registration audit logs by credential hash.
	 *
	 * @param string $credential_hash Credential SHA-256 hash.
	 * @return string
	 */
	private function lookup_credential_aaguid_for_hash( string $credential_hash ): string {
		static $cache    = array();
		$credential_hash = strtolower( trim( $credential_hash ) );
		if ( '' === $credential_hash ) {
			return '';
		}

		if ( isset( $cache[ $credential_hash ] ) ) {
			return (string) $cache[ $credential_hash ];
		}

		global $wpdb;
		$resolved   = '';
		$log_tables = array_values(
			array_unique(
				array(
					$wpdb->prefix . 'advapafo_logs',
					$wpdb->prefix . 'wpk_logs',
				)
			)
		);

		$event_types = array(
			'registered',
			'registration_success',
			'passkey_registered',
		);

		foreach ( $log_tables as $logs_table ) {
			if ( ! $this->table_exists( $logs_table ) ) {
				continue;
			}

			$table_sql = $this->quote_table_name( $logs_table );
			if ( '' === $table_sql ) {
				continue;
			}

			$rows = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded lookup from plugin-owned logs tables.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
					'SELECT log_data FROM ' . $table_sql . ' WHERE event_type IN (%s, %s, %s) ORDER BY id DESC LIMIT 400',
					$event_types[0],
					$event_types[1],
					$event_types[2]
				)
			);

			foreach ( $rows as $row_json ) {
				$payload = json_decode( (string) $row_json, true );
				if ( ! is_array( $payload ) ) {
					continue;
				}

				$row_hash = $this->extract_credential_hash_from_log_payload( $payload );

				if ( '' === $row_hash || ! hash_equals( $credential_hash, $row_hash ) ) {
					continue;
				}

				$raw_aaguid = '';
				if ( isset( $payload['aaguid'] ) ) {
					$raw_aaguid = (string) $payload['aaguid'];
				} elseif ( isset( $payload['AAGUID'] ) ) {
					$raw_aaguid = (string) $payload['AAGUID'];
				}

				$resolved = $this->normalize_authenticator_aaguid( $raw_aaguid );
				if ( '' !== $resolved ) {
					break 2;
				}
			}
		}

		$cache[ $credential_hash ] = $resolved;
		return $resolved;
	}

	/**
	 * Resolve credential provider hint from recent audit logs by credential hash.
	 *
	 * @param string $credential_hash Credential SHA-256 hash.
	 * @return string
	 */
	private function lookup_credential_provider_hint_for_hash( string $credential_hash ): string {
		static $cache    = array();
		$credential_hash = strtolower( trim( $credential_hash ) );
		if ( '' === $credential_hash ) {
			return '';
		}

		if ( isset( $cache[ $credential_hash ] ) ) {
			return (string) $cache[ $credential_hash ];
		}

		global $wpdb;
		$resolved   = '';
		$log_tables = array_values(
			array_unique(
				array(
					$wpdb->prefix . 'advapafo_logs',
					$wpdb->prefix . 'wpk_logs',
				)
			)
		);

		$event_types = array(
			'login_success',
			'registered',
			'registration_success',
			'passkey_registered',
			'login_credential_mismatch',
		);

		$provider_fields = array(
			'authenticator',
			'authenticator_label',
			'provider',
			'provider_label',
			'device_name',
			'device',
		);

		foreach ( $log_tables as $logs_table ) {
			if ( ! $this->table_exists( $logs_table ) ) {
				continue;
			}

			$table_sql = $this->quote_table_name( $logs_table );
			if ( '' === $table_sql ) {
				continue;
			}

			$rows = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded lookup from plugin-owned logs tables.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
					'SELECT log_data FROM ' . $table_sql . ' WHERE event_type IN (%s, %s, %s, %s, %s) ORDER BY id DESC LIMIT 600',
					$event_types[0],
					$event_types[1],
					$event_types[2],
					$event_types[3],
					$event_types[4]
				)
			);

			foreach ( $rows as $row_json ) {
				$payload = json_decode( (string) $row_json, true );
				if ( ! is_array( $payload ) ) {
					continue;
				}

				$row_hash = $this->extract_credential_hash_from_log_payload( $payload );
				if ( '' === $row_hash || ! hash_equals( $credential_hash, $row_hash ) ) {
					continue;
				}

				$raw_provider = '';
				foreach ( $provider_fields as $field ) {
					if ( ! isset( $payload[ $field ] ) ) {
						continue;
					}

					$candidate = trim( sanitize_text_field( (string) $payload[ $field ] ) );
					if ( '' !== $candidate ) {
						$raw_provider = $candidate;
						break;
					}
				}

				$raw_aaguid = '';
				if ( isset( $payload['aaguid'] ) ) {
					$raw_aaguid = (string) $payload['aaguid'];
				} elseif ( isset( $payload['AAGUID'] ) ) {
					$raw_aaguid = (string) $payload['AAGUID'];
				}

				$resolved = $this->resolve_authenticator_provider_label_from_signals( $raw_provider, $raw_provider, $raw_aaguid );
				if ( '' === $resolved && '' !== $raw_provider && ! $this->is_generic_authenticator_placeholder( $raw_provider ) ) {
					$resolved = $raw_provider;
				}

				if ( '' !== $resolved ) {
					break 2;
				}
			}
		}

		$cache[ $credential_hash ] = $resolved;

		return $resolved;
	}

	/**
	 * Extract a credential hash from a log payload using known key variants.
	 *
	 * @param array<string,mixed> $payload Parsed log payload.
	 * @return string
	 */
	private function extract_credential_hash_from_log_payload( array $payload ): string {
		foreach ( array( 'credential_hash', 'credential_id_hash', 'credentialIdHash', 'hash' ) as $key ) {
			if ( ! isset( $payload[ $key ] ) ) {
				continue;
			}

			$value = strtolower( trim( (string) $payload[ $key ] ) );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Normalize AAGUID input into canonical lowercase UUID format.
	 *
	 * @param string $aaguid Raw AAGUID value.
	 * @return string
	 */
	private function normalize_authenticator_aaguid( string $aaguid ): string {
		$aaguid = strtolower( trim( $aaguid ) );
		if ( '' === $aaguid ) {
			return '';
		}

		$hex = str_replace( '-', '', $aaguid );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $hex ) ) {
			return '';
		}

		if ( str_repeat( '0', 32 ) === $hex ) {
			return '';
		}

		return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20, 12 );
	}

	/**
	 * Locate the active credentials table used for reporting.
	 *
	 * @return string
	 */
	private function get_credentials_table_for_audit(): string {
		global $wpdb;
		$candidates = array(
			$wpdb->prefix . 'advapafo_credentials',
			$wpdb->prefix . 'wpk_credentials',
		);

		$fallback = '';

		foreach ( $candidates as $candidate ) {
			if ( ! $this->table_exists( $candidate ) ) {
				continue;
			}

			if ( '' === $fallback ) {
				$fallback = $candidate;
			}

			$table_sql = $this->quote_table_name( $candidate );
			if ( '' === $table_sql ) {
				continue;
			}

			$active_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table_sql . ' WHERE revoked_at IS NULL' ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
			if ( $active_count > 0 ) {
				return $candidate;
			}
		}

		return $fallback;
	}

	/**
	 * Check whether a database table exists.
	 *
	 * @param string $table_name Table name.
	 * @return bool
	 */
	private function table_exists( string $table_name ): bool {
		global $wpdb;
		$table_name = trim( $table_name );
		if ( '' === $table_name ) {
			return false;
		}

		static $exists_cache = array();
		if ( array_key_exists( $table_name, $exists_cache ) ) {
			return (bool) $exists_cache[ $table_name ];
		}

		$found                       = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists_cache[ $table_name ] = ( $found === $table_name );
		return (bool) $exists_cache[ $table_name ];
	}

	/**
	 * Quote and validate a table name for SQL usage.
	 *
	 * @param string $table_name Table name.
	 * @return string
	 */
	private function quote_table_name( string $table_name ): string {
		$table_name = trim( $table_name );
		if ( '' === $table_name ) {
			return '';
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			return '';
		}
		return '`' . $table_name . '`';
	}

	/**
	 * Convert a UTC datetime string to a Unix timestamp.
	 *
	 * @param string $raw_datetime Datetime value.
	 * @return int
	 */
	private function utc_datetime_to_timestamp( string $raw_datetime ): int {
		$raw_datetime = trim( $raw_datetime );
		if ( '' === $raw_datetime ) {
			return 0;
		}

		$date_utc = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $raw_datetime, new DateTimeZone( 'UTC' ) );
		if ( $date_utc instanceof DateTimeImmutable ) {
			return $date_utc->getTimestamp();
		}

		$fallback_ts = strtotime( $raw_datetime . ' UTC' );
		return $fallback_ts ? (int) $fallback_ts : 0;
	}

	/**
	 * Format UTC datetime for admin display in site timezone.
	 *
	 * @param string $raw_datetime Datetime value.
	 * @return string
	 */
	private function format_utc_datetime_for_display( string $raw_datetime ): string {
		$timestamp = $this->utc_datetime_to_timestamp( $raw_datetime );
		if ( $timestamp <= 0 ) {
			return $raw_datetime;
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$timestamp,
			wp_timezone()
		);
	}

	/**
	 * Render relative time label from UTC datetime input.
	 *
	 * @param string $raw_datetime Datetime value.
	 * @return string
	 */
	private function format_utc_relative_for_display( string $raw_datetime ): string {
		$timestamp = $this->utc_datetime_to_timestamp( $raw_datetime );
		if ( $timestamp <= 0 ) {
			return '';
		}

		$now   = time();
		$delta = $now - $timestamp;
		if ( $delta < 0 ) {
			return '';
		}

		if ( $delta < MINUTE_IN_SECONDS ) {
			return __( 'just now', 'advanced-passkey-login' );
		}

		return sprintf(
			/* translators: %s human time diff */
			__( '%s ago', 'advanced-passkey-login' ),
			human_time_diff( $timestamp, $now )
		);
	}

	/**
	 * Get combined audit/login rows across new and legacy log tables.
	 *
	 * @param int $limit  Row limit.
	 * @param int $offset Row offset.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_combined_audit_login_rows( int $limit, int $offset = 0 ): array {
		global $wpdb;

		$limit  = max( 1, min( 2000, absint( $limit ) ) );
		$offset = max( 0, min( 100000, absint( $offset ) ) );
		$tables = array( $wpdb->prefix . 'advapafo_logs' );

		$where_sql = '';
		$table_sql = '';
		foreach ( $tables as $table_name ) {
			if ( ! $this->table_exists( $table_name ) ) {
				continue;
			}

			$table_sql = $this->quote_table_name( $table_name );
			if ( '' === $table_sql ) {
				continue;
			}

			$where_sql = $this->build_audit_event_where_sql( $this->get_audit_event_types(), true );
			break;
		}

		if ( '' === $table_sql || '' === $where_sql ) {
			return array();
		}

		return $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table identifier is strict-validated by quote_table_name(); query parameters are prepared below.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name(); WHERE SQL is pre-prepared by build_audit_event_where_sql().
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and WHERE fragments are strict-validated/prepared before interpolation.
				'SELECT id, event_type, log_timestamp, log_data FROM ' . $table_sql . ' WHERE ' . $where_sql . ' ORDER BY log_timestamp DESC, id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and WHERE fragments are strict-validated/prepared before interpolation.
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Get combined audit/login rows for a trailing day range.
	 *
	 * @param int $days  Number of days.
	 * @param int $limit Row limit.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_combined_audit_login_rows_for_last_days( int $days, int $limit = 300 ): array {
		global $wpdb;

		$days       = max( 1, min( 365, absint( $days ) ) );
		$limit      = max( 1, min( 2000, absint( $limit ) ) );
		$cutoff_utc = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$tables    = array( $wpdb->prefix . 'advapafo_logs' );
		$where_sql = '';
		$table_sql = '';

		foreach ( $tables as $table_name ) {
			if ( ! $this->table_exists( $table_name ) ) {
				continue;
			}

			$table_sql = $this->quote_table_name( $table_name );
			if ( '' === $table_sql ) {
				continue;
			}

			$where_sql = $this->build_audit_event_where_sql( $this->get_audit_event_types(), true );
			break;
		}

		if ( '' === $table_sql || '' === $where_sql ) {
			return array();
		}

		return $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table identifier is strict-validated by quote_table_name(); query parameters are prepared below.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name(); WHERE SQL is pre-prepared by build_audit_event_where_sql().
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and WHERE fragments are strict-validated/prepared before interpolation.
				'SELECT id, event_type, log_timestamp, log_data FROM ' . $table_sql . ' WHERE ' . $where_sql . ' AND log_timestamp >= %s ORDER BY log_timestamp DESC, id DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and WHERE fragments are strict-validated/prepared before interpolation.
				$cutoff_utc,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Count combined audit/login rows across available log tables.
	 *
	 * @return int
	 */
	private function count_combined_audit_login_rows(): int {
		global $wpdb;
		$total = 0;

		$tables = array( $wpdb->prefix . 'advapafo_logs' );
		foreach ( $tables as $table_name ) {
			if ( ! $this->table_exists( $table_name ) ) {
				continue;
			}

			$table_sql = $this->quote_table_name( $table_name );
			if ( '' === $table_sql ) {
				continue;
			}

			$where_sql = $this->build_audit_event_where_sql( $this->get_audit_event_types(), true );
			$total    += (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- aggregate count over plugin-owned log tables.
				'SELECT COUNT(*) FROM ' . $table_sql . ' WHERE ' . $where_sql // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name(); WHERE SQL is pre-prepared by build_audit_event_where_sql().
			);
		}

		return $total;
	}

	/**
	 * Count a specific event type across combined log tables.
	 *
	 * @param string $event_type Event type key.
	 * @return int
	 */
	private function count_combined_login_event( string $event_type ): int {
		global $wpdb;
		$event_type = sanitize_key( $event_type );
		if ( '' === $event_type ) {
			return 0;
		}

		$total  = 0;
		$tables = array( $wpdb->prefix . 'advapafo_logs' );
		foreach ( $tables as $table_name ) {
			if ( ! $this->table_exists( $table_name ) ) {
				continue;
			}

			$table_sql = $this->quote_table_name( $table_name );
			if ( '' === $table_sql ) {
				continue;
			}

			$total += (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- aggregate count over plugin-owned log table.
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table_sql . ' WHERE event_type = %s', $event_type ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table identifier is strict-validated by quote_table_name().
			);
		}

		return $total;
	}

	/**
	 * Whether audit/activity logging is currently enabled.
	 *
	 * @return bool
	 */
	private function is_activity_logging_enabled(): bool {
		return (bool) advapafo_get_setting( 'activity_logging_enabled', true );
	}

	/**
	 * Resolve a displayable IP address from a decoded log payload, if present.
	 *
	 * @param array<string, mixed> $data Decoded log payload.
	 * @return string
	 */
	private function resolve_audit_ip_for_display( array $data ): string {
		$candidate_keys = array( 'ip_masked', 'ip_address', 'client_ip', 'remote_addr', 'ip' );

		foreach ( $candidate_keys as $candidate_key ) {
			if ( ! isset( $data[ $candidate_key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( (string) $data[ $candidate_key ] );
			if ( '' === $value ) {
				continue;
			}

			if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
				return $value;
			}
		}

		return '—';
	}

	/**
	 * List all event types included in audit reporting.
	 *
	 * @return array<int, string>
	 */
	private function get_audit_event_types(): array {
		return array(
			'login_success',
			'login_password_success',
			'login_password_blocked_passkey_only',
			'login_rate_limited',
			'login_failed',
			'login_credential_mismatch',
			'login_begin_failed',
			'login_bypass_cookie_used',
			'attestation_policy_pass',
			'attestation_policy_dry_run_block',
			'attestation_policy_blocked',
			'attestation_policy_aaguid_dry_run_block',
			'attestation_policy_aaguid_blocked',
			'trusted_device_first_seen',
			'trusted_device_match',
			'trusted_device_mismatch',
			'trusted_device_marked_trusted',
			'trusted_device_revoked',
			'session_hardening_other_sessions_terminated',
		);
	}

	/**
	 * Build a prepared WHERE SQL fragment for selected audit event filters.
	 *
	 * @param array<int, string> $events Event keys.
	 * @param bool               $include_magic_recovery_prefixes Include magic/recovery prefixed events.
	 * @return string
	 */
	private function build_audit_event_where_sql( array $events, bool $include_magic_recovery_prefixes ): string {
		global $wpdb;

		if ( empty( $events ) ) {
			return '1=0';
		}

		$placeholders = implode( ',', array_fill( 0, count( $events ), '%s' ) );
		$where_sql    = 'event_type IN (' . $placeholders . ')';
		$params       = array_values( $events );

		if ( $include_magic_recovery_prefixes ) {
			$where_sql = '(' . $where_sql . ' OR event_type LIKE %s OR event_type LIKE %s)';
			$params[]  = 'magic_link_%';
			$params[]  = 'recovery_code_%';
		}

		return (string) $wpdb->prepare( $where_sql, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic placeholder template is built from fixed literals and placeholder lists.
	}

	/**
	 * Map an event type to success/blocked/failed trend bucket.
	 *
	 * @param string $event_type Event type key.
	 * @return string
	 */
	private function classify_audit_event_for_activity_trend( string $event_type ): string {
		if ( '' === $event_type ) {
			return '';
		}

		if ( in_array( $event_type, array( 'login_success', 'login_password_success', 'login_bypass_cookie_used', 'magic_link_success', 'recovery_code_success' ), true ) ) {
			return 'success';
		}

		if ( in_array( $event_type, array( 'login_password_blocked_passkey_only', 'login_rate_limited', 'recovery_code_rate_limited', 'magic_link_rate_limited' ), true ) ) {
			return 'blocked';
		}

		if ( in_array( $event_type, array( 'login_failed', 'login_credential_mismatch', 'login_begin_failed', 'magic_link_invalid_or_expired', 'magic_link_replay_denied', 'recovery_code_invalid_or_used' ), true ) ) {
			return 'failed';
		}

		return '';
	}

	/**
	 * Map an event type to feed badge metadata.
	 *
	 * @param string $event_type Event type key.
	 * @return array{key:string,label:string}
	 */
	private function classify_audit_event_for_activity_feed( string $event_type ): array {
		if ( '' === $event_type ) {
			return array(
				'key'   => 'info',
				'label' => __( 'Info', 'advanced-passkey-login' ),
			);
		}

		if ( in_array( $event_type, array( 'login_success', 'login_password_success', 'login_bypass_cookie_used', 'magic_link_success', 'recovery_code_success', 'attestation_policy_pass', 'trusted_device_match', 'trusted_device_first_seen', 'trusted_device_marked_trusted', 'session_hardening_other_sessions_terminated' ), true ) ) {
			return array(
				'key'   => 'success',
				'label' => __( 'Successful', 'advanced-passkey-login' ),
			);
		}

		if ( in_array( $event_type, array( 'login_password_blocked_passkey_only', 'login_rate_limited', 'magic_link_rate_limited', 'recovery_code_rate_limited', 'attestation_policy_dry_run_block', 'attestation_policy_aaguid_dry_run_block', 'attestation_policy_blocked', 'attestation_policy_aaguid_blocked', 'trusted_device_mismatch', 'trusted_device_revoked' ), true ) ) {
			return array(
				'key'   => 'blocked',
				'label' => __( 'Blocked', 'advanced-passkey-login' ),
			);
		}

		if ( in_array( $event_type, array( 'login_failed', 'login_credential_mismatch', 'login_begin_failed', 'magic_link_invalid_or_expired', 'magic_link_replay_denied', 'magic_link_send_failed', 'magic_link_recaptcha_failed', 'magic_link_user_not_found', 'magic_link_signing_error', 'magic_link_bot_challenge_failed', 'recovery_code_invalid_or_used', 'recovery_code_user_not_found', 'recovery_code_alert_failed', 'recovery_stepup_failed', 'recovery_stepup_magic_link_send_failed' ), true ) ) {
			return array(
				'key'   => 'failed',
				'label' => __( 'Failed', 'advanced-passkey-login' ),
			);
		}

		if ( 0 === strpos( $event_type, 'magic_link_' ) || 0 === strpos( $event_type, 'recovery_code_' ) ) {
			return array(
				'key'   => 'failed',
				'label' => __( 'Failed', 'advanced-passkey-login' ),
			);
		}

		return array(
			'key'   => 'info',
			'label' => __( 'Info', 'advanced-passkey-login' ),
		);
	}

	/**
	 * Normalize authenticator/provider labels for reporting.
	 *
	 * @param string $label Raw label.
	 * @return string
	 */
	private function resolve_authenticator_provider_for_reporting( string $label = '' ): string {
		$meta = $this->resolve_authenticator_metadata_for_reporting( '', $label );
		return (string) ( $meta['label'] ?? 'Unknown Authenticator' );
	}

	/**
	 * Build normalized authenticator metadata for consistent labels and icons.
	 *
	 * @param string $provider Raw provider value from logs.
	 * @param string $label Raw credential label.
	 * @param string $aaguid Raw AAGUID value.
	 * @return array{label:string,key:string}
	 */
	private function resolve_authenticator_metadata_for_reporting( string $provider = '', string $label = '', string $aaguid = '' ): array {
		$provider = trim( $provider );
		$label    = trim( $label );
		$aaguid   = $this->normalize_authenticator_aaguid( $aaguid );
		$resolved = $this->resolve_authenticator_provider_label_from_signals( $provider, $label, $aaguid );

		if ( '' === $resolved ) {
			$fallback = '' !== $provider ? $provider : $label;
			$resolved = $this->is_generic_authenticator_placeholder( $fallback ) ? '' : $fallback;
		}

		if ( '' === $resolved || stripos( $resolved, 'unknown' ) !== false || stripos( $resolved, 'platform authenticator' ) !== false ) {
			$resolved = $this->infer_unknown_authenticator_label( $provider, $label );
		}

		$resolved = (string) advapafo_get_setting(
			'authenticator_provider_label',
			$resolved,
			array(
				'provider' => '',
				'label'    => '' !== $label ? $label : $provider,
			)
		);
		if ( '' === trim( $resolved ) ) {
			$resolved = 'Unknown Authenticator';
		}

		return array(
			'label' => $resolved,
			'key'   => $this->normalize_authenticator_provider_key( $resolved ),
		);
	}

	/**
	 * Determine whether a provider/label is only a generic placeholder.
	 *
	 * @param string $label Provider or label candidate.
	 * @return bool
	 */
	private function is_generic_authenticator_placeholder( string $label ): bool {
		$normalized = $this->normalize_provider_string_for_matching( $label );
		if ( '' === $normalized ) {
			return true;
		}

		return in_array(
			$normalized,
			array(
				'passkey',
				'passkeys',
				'pass key',
				'authenticator',
				'unknown authenticator',
				'platform authenticator',
				'security key',
				'this device',
				'device',
			),
			true
		);
	}

	/**
	 * Get default AAGUID-to-provider mappings from generated data file.
	 *
	 * @return array<string,string>
	 */
	private function get_default_authenticator_aaguid_map(): array {
		static $cached_map = null;
		if ( is_array( $cached_map ) ) {
			return $cached_map;
		}

		$defaults = array(
			'08987058-cadc-4b81-b6e1-30de50dcbe96' => 'Windows Hello',
			'2fc0579f-8113-47ea-b116-bb5a8db9202a' => 'YubiKey',
		);

		$map_file = ADVAPAFO_PLUGIN_DIR . 'includes/data/aaguid-provider-map.php';
		if ( ! is_string( $map_file ) || '' === $map_file || ! file_exists( $map_file ) ) {
			$cached_map = $defaults;

			return $cached_map;
		}

		$loaded = require $map_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- local plugin path and static filename.
		if ( ! is_array( $loaded ) ) {
			$cached_map = $defaults;

			return $cached_map;
		}

		$normalized = array();
		foreach ( $loaded as $aaguid => $provider ) {
			$key  = $this->normalize_authenticator_aaguid( (string) $aaguid );
			$name = trim( (string) $provider );
			if ( '' === $key || '' === $name ) {
				continue;
			}

			$normalized[ $key ] = sanitize_text_field( $name );
		}

		if ( empty( $normalized ) ) {
			$cached_map = $defaults;

			return $cached_map;
		}

		$cached_map = array_merge( $defaults, $normalized );

		return $cached_map;
	}

	/**
	 * Get provider-key icon assets generated from upstream AAGUID JSON.
	 *
	 * @return array<string,array{icon_light:string,icon_dark:string}>
	 */
	private function get_authenticator_provider_icon_asset_map(): array {
		static $cached_map = null;
		if ( is_array( $cached_map ) ) {
			return $cached_map;
		}

		$map_file = ADVAPAFO_PLUGIN_DIR . 'includes/data/provider-icon-map.php';
		if ( ! is_string( $map_file ) || '' === $map_file || ! file_exists( $map_file ) ) {
			$cached_map = array();

			return $cached_map;
		}

		$loaded = require $map_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- local plugin path and static filename.
		if ( ! is_array( $loaded ) ) {
			$cached_map = array();

			return $cached_map;
		}

		$normalized = array();
		foreach ( $loaded as $provider_key => $icons ) {
			$key = sanitize_key( (string) $provider_key );
			if ( '' === $key || ! is_array( $icons ) ) {
				continue;
			}

			$icon_light = isset( $icons['icon_light'] ) ? trim( (string) $icons['icon_light'] ) : '';
			$icon_dark  = isset( $icons['icon_dark'] ) ? trim( (string) $icons['icon_dark'] ) : '';

			if ( '' !== $icon_light && ! str_starts_with( $icon_light, 'data:image/' ) ) {
				$icon_light = '';
			}
			if ( '' !== $icon_dark && ! str_starts_with( $icon_dark, 'data:image/' ) ) {
				$icon_dark = '';
			}

			if ( '' === $icon_light && '' === $icon_dark ) {
				continue;
			}

			$normalized[ $key ] = array(
				'icon_light' => $icon_light,
				'icon_dark'  => $icon_dark,
			);
		}

		$cached_map = $normalized;

		return $cached_map;
	}

	/**
	 * Resolve a canonical provider label from raw provider/label signals.
	 *
	 * @param string $provider Raw provider value from logs.
	 * @param string $label Raw credential label.
	 * @param string $aaguid Canonical AAGUID value.
	 * @return string
	 */
	private function resolve_authenticator_provider_label_from_signals( string $provider = '', string $label = '', string $aaguid = '' ): string {
		if ( '' !== $aaguid ) {
			static $filtered_aaguid_map = null;
			if ( ! is_array( $filtered_aaguid_map ) ) {
				$aaguid_map = $this->get_default_authenticator_aaguid_map();

				/**
				 * Filter known AAGUID to provider label mappings.
				 *
				 * @param array<string,string> $aaguid_map Existing AAGUID map.
				 */
				$filtered_aaguid_map = (array) advapafo_get_setting( 'authenticator_aaguid_map', $aaguid_map );
			}

			$aaguid_map = $filtered_aaguid_map;
			if ( isset( $aaguid_map[ $aaguid ] ) ) {
				$mapped = trim( (string) $aaguid_map[ $aaguid ] );
				if ( '' !== $mapped ) {
					return $mapped;
				}
			}
		}

		$signal = $this->normalize_provider_string_for_matching( trim( $provider . ' ' . $label ) );
		if ( '' === $signal ) {
			return '';
		}

		$known_providers = array(
			'iCloud Keychain'         => array( 'icloud', 'i cloud', 'apple password', 'apple passwords', 'apple passkey', 'apple keychain' ),
			'Bitwarden'               => array( 'bitwarden', 'bitwarded' ),
			'Google Password Manager' => array( 'google password manager', 'google passkey', 'google', 'chrome password', 'chrome passkey', 'chrome', 'android credential manager' ),
			'Samsung Pass'            => array( 'samsung pass', 'samsung passkey', 'samsung' ),
			'LastPass'                => array( 'lastpass' ),
			'1Password'               => array( '1password', 'onepassword' ),
			'Dashlane'                => array( 'dashlane' ),
			'NordPass'                => array( 'nordpass' ),
			'Proton Pass'             => array( 'proton pass', 'protonpass' ),
			'Windows Hello'           => array( 'windows hello', 'microsoft authenticator', 'microsoft password manager' ),
			'YubiKey'                 => array( 'yubikey', 'yubico' ),
			'Keeper'                  => array( 'keeper' ),
			'Enpass'                  => array( 'enpass' ),
			'KeePassXC'               => array( 'keepassxc', 'keepass dx', 'keepassdx', 'keepass passkey' ),
			'RoboForm'                => array( 'roboform' ),
		);

		foreach ( $known_providers as $canonical => $needles ) {
			foreach ( $needles as $needle ) {
				if ( strpos( $signal, (string) $needle ) !== false ) {
					return $canonical;
				}
			}
		}

		return '';
	}

	/**
	 * Infer unknown authenticator category from weaker hints.
	 *
	 * @param string $provider Raw provider value from logs.
	 * @param string $label Raw credential label.
	 * @return string
	 */
	private function infer_unknown_authenticator_label( string $provider = '', string $label = '' ): string {
		$signal = $this->normalize_provider_string_for_matching( trim( $provider . ' ' . $label ) );

		if ( strpos( $signal, 'security key' ) !== false || strpos( $signal, 'fido' ) !== false || strpos( $signal, 'u2f' ) !== false || strpos( $signal, 'hardware key' ) !== false || strpos( $signal, 'usb key' ) !== false ) {
			return 'Unknown Security Key';
		}

		if ( strpos( $signal, 'cross device' ) !== false || strpos( $signal, 'cross-device' ) !== false || strpos( $signal, 'hybrid' ) !== false || strpos( $signal, 'cable' ) !== false || strpos( $signal, 'phone passkey' ) !== false || strpos( $signal, 'qr passkey' ) !== false ) {
			return 'Unknown Cross-Device Authenticator';
		}

		if ( strpos( $signal, 'platform authenticator' ) !== false || strpos( $signal, 'platform passkey' ) !== false || strpos( $signal, 'this device' ) !== false || strpos( $signal, 'touch id' ) !== false || strpos( $signal, 'face id' ) !== false || strpos( $signal, 'biometric' ) !== false ) {
			return 'Unknown Platform Authenticator';
		}

		return 'Unknown Authenticator';
	}

	/**
	 * Render a styled authenticator provider badge.
	 *
	 * @param string              $provider Provider label.
	 * @param array<string,mixed> $options Optional render options.
	 * @return string
	 */
	private function render_authenticator_provider_badge( string $provider, array $options = array() ): string {
		$provider = trim( $provider );
		if ( '' === $provider ) {
			$provider = 'Unknown Authenticator';
		}

		$provider_key = isset( $options['provider_key'] ) ? sanitize_key( (string) $options['provider_key'] ) : '';
		if ( '' === $provider_key ) {
			$provider_key = $this->normalize_authenticator_provider_key( $provider );
		}
		$icon_markup = $this->get_authenticator_provider_icon_svg( $provider_key );

		$html = '<span class="wpkpro-provider-chip wpkpro-provider-chip--' . esc_attr( $provider_key ) . '">';
		if ( '' !== $icon_markup ) {
			$html .= '<span class="wpkpro-provider-icon" aria-hidden="true">' . $icon_markup . '</span>';
		}
		$html .= '<span class="wpkpro-provider-label">' . esc_html( $provider ) . '</span>';
		$html .= '</span>';

		// Markup is assembled from sanitized text/key plus static internal SVG strings.
		return $html;
	}

	/**
	 * Allowed HTML tags/attributes for authenticator badge markup.
	 *
	 * @return array<string,array<string,bool>>
	 */
	private function get_authenticator_provider_badge_allowed_html(): array {
		return array(
			'span'    => array(
				'class'       => true,
				'aria-hidden' => true,
			),
			'picture' => array(
				'class' => true,
			),
			'source'  => array(
				'srcset' => true,
				'media'  => true,
			),
			'img'     => array(
				'class'    => true,
				'src'      => true,
				'alt'      => true,
				'width'    => true,
				'height'   => true,
				'loading'  => true,
				'decoding' => true,
			),
			'svg'     => array(
				'viewBox'         => true,
				'width'           => true,
				'height'          => true,
				'fill'            => true,
				'aria-hidden'     => true,
				'focusable'       => true,
				'role'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'fill-opacity'    => true,
				'xmlns'           => true,
			),
			'path'    => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'fill-opacity'    => true,
			),
			'circle'  => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'rect'    => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
		);
	}

	/**
	 * Normalize provider label to a stable key.
	 *
	 * @param string $provider Provider label.
	 * @return string
	 */
	private function normalize_authenticator_provider_key( string $provider ): string {
		$provider_lc = $this->normalize_provider_string_for_matching( $provider );

		if ( strpos( $provider_lc, 'bitwarden' ) !== false || strpos( $provider_lc, 'bitwarded' ) !== false ) {
			return 'bitwarden';
		}
		if ( strpos( $provider_lc, 'icloud' ) !== false || strpos( $provider_lc, 'apple' ) !== false ) {
			return 'icloud';
		}
		if ( strpos( $provider_lc, '1password' ) !== false || strpos( $provider_lc, 'onepassword' ) !== false ) {
			return 'onepassword';
		}
		if ( strpos( $provider_lc, 'lastpass' ) !== false ) {
			return 'lastpass';
		}
		if ( strpos( $provider_lc, 'google' ) !== false || strpos( $provider_lc, 'chrome' ) !== false ) {
			return 'google';
		}
		if ( strpos( $provider_lc, 'keepassxc' ) !== false || strpos( $provider_lc, 'keepassdx' ) !== false || strpos( $provider_lc, 'keepass' ) !== false ) {
			return 'unknown-security-key';
		}
		if ( strpos( $provider_lc, 'samsung' ) !== false ) {
			return 'samsung';
		}
		if ( strpos( $provider_lc, 'windows hello' ) !== false || strpos( $provider_lc, 'microsoft authenticator' ) !== false || strpos( $provider_lc, 'microsoft password manager' ) !== false ) {
			return 'windows-hello';
		}
		if ( strpos( $provider_lc, 'yubikey' ) !== false || strpos( $provider_lc, 'yubico' ) !== false ) {
			return 'yubikey';
		}
		if ( strpos( $provider_lc, 'keeper' ) !== false ) {
			return 'keeper';
		}
		if ( strpos( $provider_lc, 'enpass' ) !== false ) {
			return 'enpass';
		}
		if ( strpos( $provider_lc, 'roboform' ) !== false ) {
			return 'roboform';
		}
		if ( strpos( $provider_lc, 'dashlane' ) !== false ) {
			return 'dashlane';
		}
		if ( strpos( $provider_lc, 'nordpass' ) !== false ) {
			return 'nordpass';
		}
		if ( strpos( $provider_lc, 'proton' ) !== false ) {
			return 'proton-pass';
		}
		if ( strpos( $provider_lc, 'unknown platform' ) !== false || strpos( $provider_lc, 'platform authenticator' ) !== false ) {
			return 'unknown-platform';
		}
		if ( strpos( $provider_lc, 'unknown security key' ) !== false || strpos( $provider_lc, 'security key' ) !== false || strpos( $provider_lc, 'u2f' ) !== false || strpos( $provider_lc, 'fido' ) !== false ) {
			return 'unknown-security-key';
		}
		if ( strpos( $provider_lc, 'unknown cross device' ) !== false || strpos( $provider_lc, 'cross device' ) !== false || strpos( $provider_lc, 'cross-device' ) !== false || strpos( $provider_lc, 'hybrid' ) !== false || strpos( $provider_lc, 'cable' ) !== false ) {
			return 'unknown-cross-device';
		}

		return 'unknown';
	}

	/**
	 * Normalize provider text for robust matching across log payload variants.
	 *
	 * @param string $provider Raw provider label.
	 * @return string
	 */
	private function normalize_provider_string_for_matching( string $provider ): string {
		$normalized = strtolower( wp_strip_all_tags( trim( $provider ) ) );
		$normalized = str_replace( array( '-', '_', '/', '\\', '.', ',', ':', ';', '|', '(', ')', '[', ']' ), ' ', $normalized );
		$normalized = preg_replace( '/[^a-z0-9\s]+/u', ' ', $normalized );
		$normalized = preg_replace( '/\s+/u', ' ', $normalized );

		return is_string( $normalized ) ? trim( $normalized ) : '';
	}

	/**
	 * Return SVG icon markup for known provider keys.
	 *
	 * @param string $provider_key Provider key.
	 * @return string
	 */
	private function get_authenticator_provider_icon_svg( string $provider_key ): string {
		$assets = $this->get_authenticator_provider_icon_asset_map();
		if ( isset( $assets[ $provider_key ] ) && is_array( $assets[ $provider_key ] ) ) {
			$icon_light = isset( $assets[ $provider_key ]['icon_light'] ) ? trim( (string) $assets[ $provider_key ]['icon_light'] ) : '';
			$icon_dark  = isset( $assets[ $provider_key ]['icon_dark'] ) ? trim( (string) $assets[ $provider_key ]['icon_dark'] ) : '';

			if ( '' === $icon_light && '' === $icon_dark ) {
				return '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" aria-hidden="true" focusable="false" role="img"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.4"/><path d="M6.8 6.4a1.2 1.2 0 1 1 2.4 0c0 .8-1.2 1-1.2 2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="11.2" r="0.7" fill="currentColor"/></svg>';
			}

			if ( '' !== $icon_dark && '' !== $icon_light && $icon_dark !== $icon_light ) {
				return '<picture class="wpkpro-provider-icon-image-wrap"><source media="(prefers-color-scheme: dark)" srcset="' . esc_attr( $icon_dark ) . '" /><img class="wpkpro-provider-icon-image" src="' . esc_attr( $icon_light ) . '" alt="" width="16" height="16" loading="lazy" decoding="async" /></picture>';
			}

			$single = '' !== $icon_light ? $icon_light : $icon_dark;
			if ( '' !== $single ) {
				return '<img class="wpkpro-provider-icon-image" src="' . esc_attr( $single ) . '" alt="" width="16" height="16" loading="lazy" decoding="async" />';
			}
		}

		return '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" aria-hidden="true" focusable="false" role="img"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.4"/><path d="M6.8 6.4a1.2 1.2 0 1 1 2.4 0c0 .8-1.2 1-1.2 2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="11.2" r="0.7" fill="currentColor"/></svg>';
	}

	/**
	 * Render advanced settings tab.
	 */
	private function render_advanced_tab() {
		$show_separator              = (bool) advapafo_get_setting( 'show_separator', true );
		$conditional_ui_enabled      = (bool) advapafo_get_setting( 'conditional_ui_enabled', false );
		$activity_logging_enabled    = (bool) advapafo_get_setting( 'activity_logging_enabled', true );
		$activity_logging_overridden = advapafo_is_setting_overridden( 'activity_logging_enabled' );
		if ( $conditional_ui_enabled ) {
			$show_separator = false;
		}
		$button_style                          = advapafo_get_setting( 'button_style', 'black' );
		$rp_name                               = advapafo_get_setting( 'rp_name', '' );
		$rp_id                                 = advapafo_get_setting( 'rp_id', '' );
		$login_challenge_ttl                   = absint( advapafo_get_setting( 'login_challenge_ttl', 300 ) );
		$registration_challenge_ttl            = absint( advapafo_get_setting( 'registration_challenge_ttl', 300 ) );
		$window                                = absint( advapafo_get_setting( 'rate_limit_window', 300 ) );
		$max_failures                          = absint( advapafo_get_setting( 'rate_limit_max_failures', 5 ) );
		$lockout                               = absint( advapafo_get_setting( 'rate_limit_lockout', 900 ) );
		$show_separator_overridden             = advapafo_is_setting_overridden( 'show_separator' );
		$conditional_ui_enabled_overridden     = advapafo_is_setting_overridden( 'conditional_ui_enabled' );
		$button_style_overridden               = advapafo_is_setting_overridden( 'button_style' );
		$rp_name_overridden                    = advapafo_is_setting_overridden( 'rp_name' );
		$rp_id_overridden                      = advapafo_is_setting_overridden( 'rp_id' );
		$login_challenge_ttl_overridden        = advapafo_is_setting_overridden( 'login_challenge_ttl' ) || advapafo_is_setting_overridden( 'challenge_ttl' );
		$registration_challenge_ttl_overridden = advapafo_is_setting_overridden( 'registration_challenge_ttl' ) || advapafo_is_setting_overridden( 'challenge_ttl' );
		$window_overridden                     = advapafo_is_setting_overridden( 'rate_limit_window' );
		$max_failures_overridden               = advapafo_is_setting_overridden( 'rate_limit_max_failures' );
		$lockout_overridden                    = advapafo_is_setting_overridden( 'rate_limit_lockout' );
		?>
		<section class="advapafo-section-header">
			<div>
				<p class="advapafo-eyebrow"><?php esc_html_e( 'Advanced', 'advanced-passkey-login' ); ?></p>
				<h2><?php esc_html_e( 'Technical configuration', 'advanced-passkey-login' ); ?></h2>
			</div>
		</section>

		<div class="advapafo-card advapafo-card--setting<?php echo esc_attr( $conditional_ui_enabled ? ' advapafo-card--setting-disabled' : '' ); ?>">
			<div class="advapafo-setting-copy">
				<h3><?php esc_html_e( 'Show login OR separator', 'advanced-passkey-login' ); ?><?php advapafo_render_managed_setting_badge( 'show_separator' ); ?></h3>
				<p><?php esc_html_e( 'Display the centered OR divider above the passkey button on wp-login.php.', 'advanced-passkey-login' ); ?></p>
				<?php if ( $conditional_ui_enabled ) : ?>
					<p><small><?php esc_html_e( 'Disabled while Conditional UI is enabled to keep a single, native autofill login path.', 'advanced-passkey-login' ); ?></small></p>
				<?php endif; ?>
			</div>
			<label class="advapafo-switch">
				<input type="checkbox" name="advapafo_show_separator" value="1" <?php checked( $show_separator ); ?> <?php disabled( $conditional_ui_enabled || $show_separator_overridden ); ?> />
				<span class="advapafo-switch__track"><span class="advapafo-switch__thumb"></span></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Show login OR separator', 'advanced-passkey-login' ); ?></span>
			</label>
			<?php if ( $conditional_ui_enabled || $show_separator_overridden ) : ?>
				<input type="hidden" name="advapafo_show_separator" value="<?php echo esc_attr( $show_separator ? '1' : '0' ); ?>" />
			<?php endif; ?>
		</div>

		<div class="advapafo-card advapafo-card--setting">
			<div class="advapafo-setting-copy">
				<h3><?php esc_html_e( 'Enable passkey autofill (Conditional UI)', 'advanced-passkey-login' ); ?><?php advapafo_render_managed_setting_badge( 'conditional_ui_enabled' ); ?></h3>
				<p><?php esc_html_e( 'When supported by the browser, passkeys appear in the native autofill list when focusing the username field.', 'advanced-passkey-login' ); ?></p>
				<?php if ( $conditional_ui_enabled ) : ?>
					<p><small><?php esc_html_e( 'Conditional UI is active, so the manual "Sign in with Passkey" button is hidden on wp-login.php and the OR separator is automatically turned off to avoid duplicate login prompts.', 'advanced-passkey-login' ); ?></small></p>
				<?php endif; ?>
			</div>
			<label class="advapafo-switch">
				<input type="checkbox" name="advapafo_conditional_ui_enabled" value="1" <?php checked( $conditional_ui_enabled ); ?> <?php disabled( $conditional_ui_enabled_overridden ); ?> />
				<span class="advapafo-switch__track"><span class="advapafo-switch__thumb"></span></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Enable passkey autofill (Conditional UI)', 'advanced-passkey-login' ); ?></span>
			</label>
			<?php if ( $conditional_ui_enabled_overridden ) : ?>
				<input type="hidden" name="advapafo_conditional_ui_enabled" value="<?php echo esc_attr( $conditional_ui_enabled ? '1' : '0' ); ?>" />
			<?php endif; ?>
		</div>

		<div class="advapafo-card">
			<div class="advapafo-field">
				<div class="advapafo-label-row">
					<label for="advapafo_button_style"><?php esc_html_e( 'Passkey button style', 'advanced-passkey-login' ); ?></label>
					<?php advapafo_render_managed_setting_badge( 'button_style' ); ?>
				</div>
				<select id="advapafo_button_style" name="advapafo_button_style" <?php disabled( $button_style_overridden ); ?>>
					<option value="black" <?php selected( $button_style, 'black' ); ?>><?php esc_html_e( 'Default black', 'advanced-passkey-login' ); ?></option>
					<option value="light_grey" <?php selected( $button_style, 'light_grey' ); ?>><?php esc_html_e( 'Light grey', 'advanced-passkey-login' ); ?></option>
				</select>
				<?php if ( $button_style_overridden ) : ?>
					<input type="hidden" name="advapafo_button_style" value="<?php echo esc_attr( (string) $button_style ); ?>" />
				<?php endif; ?>
			</div>
		</div>

		<div class="advapafo-card advapafo-grid-2">
			<div class="advapafo-field">
				<div class="advapafo-label-row">
					<label for="advapafo_rp_name"><?php esc_html_e( 'Relying Party Name', 'advanced-passkey-login' ); ?></label>
					<?php advapafo_render_managed_setting_badge( 'rp_name' ); ?>
				</div>
				<input id="advapafo_rp_name" class="regular-text" type="text" name="advapafo_rp_name" value="<?php echo esc_attr( $rp_name ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" <?php wp_readonly( $rp_name_overridden ); ?> />
				<p><?php esc_html_e( 'The name users see in their passkey prompt. Leave blank to use the site name.', 'advanced-passkey-login' ); ?></p>
			</div>
			<div class="advapafo-field">
				<div class="advapafo-label-row">
					<label for="advapafo_rp_id"><?php esc_html_e( 'Relying Party ID', 'advanced-passkey-login' ); ?></label>
					<?php advapafo_render_managed_setting_badge( 'rp_id' ); ?>
				</div>
				<input id="advapafo_rp_id" class="regular-text" type="text" name="advapafo_rp_id" value="<?php echo esc_attr( $rp_id ); ?>" placeholder="<?php echo esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>" <?php wp_readonly( $rp_id_overridden ); ?> />
				<p><?php esc_html_e( 'Usually your root domain. Leave blank unless you know you need to customize it.', 'advanced-passkey-login' ); ?></p>
			</div>
		</div>

		<div class="advapafo-card">
			<div class="advapafo-card__header">
				<div>
					<h3><?php esc_html_e( 'Passkey challenge timeouts', 'advanced-passkey-login' ); ?></h3>
					<p><?php esc_html_e( 'Control how long users have to complete passkey login or registration after a challenge is issued.', 'advanced-passkey-login' ); ?></p>
				</div>
				<span class="advapafo-badge"><?php esc_html_e( 'Seconds', 'advanced-passkey-login' ); ?></span>
			</div>
			<div class="advapafo-grid-2">
				<div class="advapafo-field">
					<div class="advapafo-label-row">
						<label for="advapafo_login_challenge_ttl"><?php esc_html_e( 'Login challenge timeout', 'advanced-passkey-login' ); ?></label>
						<?php advapafo_render_managed_setting_badge( array( 'login_challenge_ttl', 'challenge_ttl' ) ); ?>
					</div>
					<input id="advapafo_login_challenge_ttl" type="number" min="30" max="1200" name="advapafo_login_challenge_ttl" value="<?php echo esc_attr( $login_challenge_ttl ); ?>" <?php wp_readonly( $login_challenge_ttl_overridden ); ?> />
					<p><?php esc_html_e( 'How long a user has to complete passkey sign-in.', 'advanced-passkey-login' ); ?></p>
				</div>
				<div class="advapafo-field">
					<div class="advapafo-label-row">
						<label for="advapafo_registration_challenge_ttl"><?php esc_html_e( 'Registration challenge timeout', 'advanced-passkey-login' ); ?></label>
						<?php advapafo_render_managed_setting_badge( array( 'registration_challenge_ttl', 'challenge_ttl' ) ); ?>
					</div>
					<input id="advapafo_registration_challenge_ttl" type="number" min="30" max="1200" name="advapafo_registration_challenge_ttl" value="<?php echo esc_attr( $registration_challenge_ttl ); ?>" <?php wp_readonly( $registration_challenge_ttl_overridden ); ?> />
					<p><?php esc_html_e( 'How long a user has to finish passkey registration.', 'advanced-passkey-login' ); ?></p>
				</div>
			</div>
		</div>

		<div class="advapafo-card">
			<div class="advapafo-card__header">
				<div>
					<h3><?php esc_html_e( 'Rate limiting', 'advanced-passkey-login' ); ?></h3>
					<p><?php esc_html_e( 'Protect authentication endpoints from repeated failed attempts.', 'advanced-passkey-login' ); ?></p>
				</div>
				<span class="advapafo-badge advapafo-badge--success"><?php esc_html_e( 'Protected', 'advanced-passkey-login' ); ?></span>
			</div>
			<div class="advapafo-grid-3">
				<div class="advapafo-field">
					<div class="advapafo-label-row">
						<label for="advapafo_rate_limit_window"><?php esc_html_e( 'Failure window', 'advanced-passkey-login' ); ?></label>
						<?php advapafo_render_managed_setting_badge( 'rate_limit_window' ); ?>
					</div>
					<input id="advapafo_rate_limit_window" type="number" min="60" max="3600" name="advapafo_rate_limit_window" value="<?php echo esc_attr( $window ); ?>" <?php wp_readonly( $window_overridden ); ?> />
					<p><?php esc_html_e( 'Seconds.', 'advanced-passkey-login' ); ?></p>
				</div>
				<div class="advapafo-field">
					<div class="advapafo-label-row">
						<label for="advapafo_rate_limit_max_failures"><?php esc_html_e( 'Max failures', 'advanced-passkey-login' ); ?></label>
						<?php advapafo_render_managed_setting_badge( 'rate_limit_max_failures' ); ?>
					</div>
					<input id="advapafo_rate_limit_max_failures" type="number" min="1" max="50" name="advapafo_rate_limit_max_failures" value="<?php echo esc_attr( $max_failures ); ?>" <?php wp_readonly( $max_failures_overridden ); ?> />
					<p><?php esc_html_e( 'Attempts before lockout.', 'advanced-passkey-login' ); ?></p>
				</div>
				<div class="advapafo-field">
					<div class="advapafo-label-row">
						<label for="advapafo_rate_limit_lockout"><?php esc_html_e( 'Lockout duration', 'advanced-passkey-login' ); ?></label>
						<?php advapafo_render_managed_setting_badge( 'rate_limit_lockout' ); ?>
					</div>
					<input id="advapafo_rate_limit_lockout" type="number" min="60" max="86400" name="advapafo_rate_limit_lockout" value="<?php echo esc_attr( $lockout ); ?>" <?php wp_readonly( $lockout_overridden ); ?> />
					<p><?php esc_html_e( 'Seconds.', 'advanced-passkey-login' ); ?></p>
				</div>
			</div>
		</div>

		<div class="advapafo-card advapafo-card--setting">
			<div class="advapafo-setting-copy">
				<h3><?php esc_html_e( 'Activity &amp; audit logging', 'advanced-passkey-login' ); ?><?php advapafo_render_managed_setting_badge( 'activity_logging_enabled' ); ?></h3>
				<p><?php esc_html_e( 'Powers the Dashboard and Audit Log tabs. Login events are recorded with a pseudonymized user reference and a privacy-safe masked IP address (the full IP is never stored). Turn this off to stop recording new events entirely.', 'advanced-passkey-login' ); ?></p>
			</div>
			<label class="advapafo-switch">
				<input type="checkbox" name="advapafo_activity_logging_enabled" value="1" <?php checked( $activity_logging_enabled ); ?> <?php disabled( $activity_logging_overridden ); ?> />
				<span class="advapafo-switch__track"><span class="advapafo-switch__thumb"></span></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Activity & audit logging', 'advanced-passkey-login' ); ?></span>
			</label>
			<?php if ( $activity_logging_overridden ) : ?>
				<input type="hidden" name="advapafo_activity_logging_enabled" value="<?php echo esc_attr( $activity_logging_enabled ? '1' : '0' ); ?>" />
			<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Render shortcode reference/help tab.
	 */
	private function render_shortcodes_tab() {
		$shortcodes = array(
			array(
				'title'       => __( 'Login Form', 'advanced-passkey-login' ),
				'code'        => '[advapafo_login_button]',
				'description' => __( 'Display a passkey login form on any page.', 'advanced-passkey-login' ),
				'placement'   => __( 'Best for custom login pages.', 'advanced-passkey-login' ),
			),
			array(
				'title'       => __( 'Register Button', 'advanced-passkey-login' ),
				'code'        => '[advapafo_register_button]',
				'description' => __( 'Let signed-in users register a new passkey.', 'advanced-passkey-login' ),
				'placement'   => __( 'Best for account and onboarding pages.', 'advanced-passkey-login' ),
			),
			array(
				'title'       => __( 'Account Passkeys', 'advanced-passkey-login' ),
				'code'        => '[advapafo_passkey_profile]',
				'description' => __( 'Show a user-facing passkey management area.', 'advanced-passkey-login' ),
				'placement'   => __( 'Best for profile or dashboard pages.', 'advanced-passkey-login' ),
			),
			array(
				'title'       => __( 'Conditional Prompt', 'advanced-passkey-login' ),
				'code'        => '[advapafo_passkey_prompt]',
				'description' => __( 'Prompt eligible users to set up passwordless login.', 'advanced-passkey-login' ),
				'placement'   => __( 'Best after login or checkout.', 'advanced-passkey-login' ),
			),
		);

		if ( class_exists( 'ADVAPAFO_Integration_Manager' ) && method_exists( 'ADVAPAFO_Integration_Manager', 'get_integration_shortcodes' ) ) {
			$integration_shortcodes = ADVAPAFO_Integration_Manager::get_integration_shortcodes();

			foreach ( $integration_shortcodes as $integration_shortcode ) {
				if ( empty( $integration_shortcode['title'] ) || empty( $integration_shortcode['code'] ) ) {
					continue;
				}

				$shortcodes[] = array(
					'title'       => sanitize_text_field( (string) $integration_shortcode['title'] ),
					'code'        => sanitize_text_field( (string) $integration_shortcode['code'] ),
					'description' => __( 'Integration-specific passkey entry point.', 'advanced-passkey-login' ),
					'placement'   => __( 'Shown only when the related plugin is active.', 'advanced-passkey-login' ),
				);
			}
		}
		?>
		<section class="advapafo-section-header">
			<div>
				<p class="advapafo-eyebrow"><?php esc_html_e( 'Shortcodes', 'advanced-passkey-login' ); ?></p>
				<h2><?php esc_html_e( 'Drop-in passkey experiences', 'advanced-passkey-login' ); ?></h2>
				<p class="advapafo-shortcode-tab-note"><?php esc_html_e( 'Prefer visual editing? Use matching Gutenberg blocks for login, registration, profile prompts, and active integrations or drop in shortcodes wherever you need them.', 'advanced-passkey-login' ); ?></p>
			</div>
		</section>

		<div class="advapafo-shortcode-grid">
			<?php foreach ( $shortcodes as $shortcode ) : ?>
				<article class="advapafo-shortcode-card">
					<h3><?php echo esc_html( $shortcode['title'] ); ?></h3>
					<p><?php echo esc_html( $shortcode['description'] ); ?></p>
					<code><?php echo esc_html( $shortcode['code'] ); ?></code>
					<span><?php echo esc_html( $shortcode['placement'] ); ?></span>
				</article>
			<?php endforeach; ?>
		</div>

		<article class="advapafo-shortcode-helper-card" aria-label="<?php esc_attr_e( 'Shortcode quick start guide', 'advanced-passkey-login' ); ?>">
			<header class="advapafo-shortcode-helper-card__header">
				<h3><?php esc_html_e( 'Quick start: shortcode guide', 'advanced-passkey-login' ); ?></h3>
				<p><?php esc_html_e( 'Paste a shortcode into any page, post, or block that supports shortcodes. Then add options to control labels, redirects, and behavior.', 'advanced-passkey-login' ); ?></p>
			</header>

			<div class="advapafo-shortcode-helper-grid">
				<section>
					<h4><?php esc_html_e( 'How to add one', 'advanced-passkey-login' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Open the page where you want passkey UI to appear.', 'advanced-passkey-login' ); ?></li>
						<li><?php esc_html_e( 'Add a Shortcode block (or paste into classic content).', 'advanced-passkey-login' ); ?></li>
						<li><?php esc_html_e( 'Paste a shortcode from the cards above and update the page.', 'advanced-passkey-login' ); ?></li>
					</ol>
				</section>

				<section>
					<h4><?php esc_html_e( 'Most useful options', 'advanced-passkey-login' ); ?></h4>
					<ul class="advapafo-shortcode-helper-list">
						<li><code>label</code> <?php esc_html_e( 'Change button text.', 'advanced-passkey-login' ); ?></li>
						<li><code>redirect_to</code> <?php esc_html_e( 'Send users to a specific URL after sign-in.', 'advanced-passkey-login' ); ?></li>
						<li><code>class</code> <?php esc_html_e( 'Add your own CSS class for styling.', 'advanced-passkey-login' ); ?></li>
						<li><code>allow_multiple</code> <?php esc_html_e( 'Allow more than one login button on a page (0 or 1).', 'advanced-passkey-login' ); ?></li>
						<li><code>button_label</code> <?php esc_html_e( 'Set prompt CTA text for passkey setup prompts.', 'advanced-passkey-login' ); ?></li>
					</ul>
				</section>
			</div>

			<div class="advapafo-shortcode-examples">
				<h4><?php esc_html_e( 'Copy-and-paste examples', 'advanced-passkey-login' ); ?></h4>
				<div class="advapafo-shortcode-examples__grid">
					<div>
						<p><?php esc_html_e( 'Custom login button + redirect', 'advanced-passkey-login' ); ?></p>
						<code>[advapafo_login_button label="Sign in securely" redirect_to="/my-account/"]</code>
					</div>
					<div>
						<p><?php esc_html_e( 'Multiple login buttons on one page', 'advanced-passkey-login' ); ?></p>
						<code>[advapafo_login_button allow_multiple="1" class="my-passkey-login"]</code>
					</div>
					<div>
						<p><?php esc_html_e( 'Custom register button label', 'advanced-passkey-login' ); ?></p>
						<code>[advapafo_register_button label="Add this device"]</code>
					</div>
					<div>
						<p><?php esc_html_e( 'Prompt users to set up passkeys', 'advanced-passkey-login' ); ?></p>
						<code>[advapafo_passkey_prompt title="Secure your account" button_label="Set up passkey"]</code>
					</div>
				</div>
			</div>
		</article>
		<?php
	}

	/**
	 * Render settings sidebar cards.
	 *
	 * @param string $active_tab Currently active tab key.
	 */
	private function render_sidebar_cards( $active_tab ) {
		$dismiss_redirect = wp_nonce_url(
			add_query_arg(
				'tab',
				sanitize_key( $active_tab ),
				admin_url( 'options-general.php?page=' . $this->page_slug )
			),
			'advapafo_tab_' . sanitize_key( $active_tab ),
			'advapafo_tab_nonce'
		);
		?>
		<section class="advapafo-side-card">
			<div class="advapafo-side-card__header">
				<h2><?php esc_html_e( 'Quick setup', 'advanced-passkey-login' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="advapafo_dismiss_quick_setup" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $dismiss_redirect ); ?>" />
					<?php wp_nonce_field( 'advapafo_dismiss_quick_setup' ); ?>
					<button type="submit" class="advapafo-icon-button" aria-label="<?php esc_attr_e( 'Dismiss quick setup', 'advanced-passkey-login' ); ?>">
						<span aria-hidden="true">&times;</span>
					</button>
				</form>
			</div>
			<ol class="advapafo-checklist">
				<li><?php esc_html_e( 'Activate the plugin', 'advanced-passkey-login' ); ?></li>
				<li><?php esc_html_e( 'Enable passkeys in Settings', 'advanced-passkey-login' ); ?></li>
				<li><?php esc_html_e( 'Choose eligible roles', 'advanced-passkey-login' ); ?></li>
				<li><?php esc_html_e( 'Register your first passkey in Your Profile', 'advanced-passkey-login' ); ?></li>
				<li><?php esc_html_e( 'Sign out and test the login button', 'advanced-passkey-login' ); ?></li>
			</ol>
		</section>

		<?php
		if ( class_exists( 'ADVAPAFO_Integration_Manager' ) && method_exists( 'ADVAPAFO_Integration_Manager', 'get_available_integrations' ) ) {
			$available_integrations = ADVAPAFO_Integration_Manager::get_available_integrations();
			if ( ! empty( $available_integrations ) ) {
				?>
				<section class="advapafo-side-card">
					<h2><?php esc_html_e( 'Active integrations', 'advanced-passkey-login' ); ?></h2>
					<p><?php esc_html_e( 'Passkey modules, shortcodes, and Gutenberg blocks are available for these detected plugins.', 'advanced-passkey-login' ); ?></p>
					<ul>
						<?php foreach ( $available_integrations as $integration_label ) : ?>
							<li><?php echo esc_html( $integration_label ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
				<?php
			}
		}
		?>

		<?php
	}

	/**
	 * Normalize checkbox value to int flag.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_checkbox( $value ) {
		return ! empty( $value ) ? 1 : 0;
	}

	/**
	 * Sanitize separator toggle and force OFF while Conditional UI is enabled.
	 *
	 * @param mixed $value Raw separator checkbox value.
	 * @return int
	 */
	public function sanitize_show_separator( $value ) {
		$posted_conditional = null;
		$has_valid_nonce    = false;
		$nonce_input        = filter_input( INPUT_POST, '_wpnonce', FILTER_DEFAULT );
		if ( is_string( $nonce_input ) && '' !== $nonce_input ) {
			$nonce           = sanitize_text_field( $nonce_input );
			$has_valid_nonce = (bool) wp_verify_nonce( $nonce, $this->option_group . '-options' );
		}

		$conditional_input = filter_input( INPUT_POST, 'advapafo_conditional_ui_enabled', FILTER_DEFAULT );
		if ( $has_valid_nonce && is_string( $conditional_input ) ) {
			$raw_conditional    = sanitize_text_field( $conditional_input );
			$posted_conditional = '' !== $raw_conditional && '0' !== $raw_conditional;
		}

		$conditional_enabled = null !== $posted_conditional
			? (bool) $posted_conditional
			: (bool) get_option( 'advapafo_conditional_ui_enabled', false );

		if ( $conditional_enabled ) {
			return 0;
		}

		return ! empty( $value ) ? 1 : 0;
	}

	/**
	 * Sanitize eligible roles list.
	 *
	 * @param mixed $roles Submitted roles.
	 * @return array<int, string>
	 */
	public function sanitize_roles( $roles ) {
		if ( ! is_array( $roles ) ) {
			return array( 'administrator' );
		}

		$valid_roles = array_keys( wp_roles()->roles );
		$sanitized   = array_values( array_intersect( array_map( 'sanitize_key', $roles ), $valid_roles ) );
		if ( empty( $sanitized ) ) {
			return array( 'administrator' );
		}

		return $sanitized;
	}

	/**
	 * Sanitize max passkeys setting.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_max_passkeys( $value ) {
		return min( 999999, max( 0, absint( $value ) ) );
	}

	/**
	 * Sanitize user verification mode.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_user_verification( $value ) {
		$allowed = array( 'required', 'preferred', 'discouraged' );
		return in_array( $value, $allowed, true ) ? $value : 'required';
	}

	/**
	 * Sanitize button style setting.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_button_style( $value ) {
		$allowed = array( 'black', 'light_grey' );
		$value   = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : 'black';
	}

	/**
	 * Sanitize relying party ID.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_rp_id( $value ) {
		$value = strtolower( sanitize_text_field( $value ) );
		return preg_replace( '/[^a-z0-9.-]/', '', $value );
	}

	/**
	 * Sanitize rate-limit window setting.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_rate_limit_window( $value ) {
		return min( 3600, max( 60, absint( $value ) ) );
	}

	/**
	 * Sanitize max failures setting.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_rate_limit_max_failures( $value ) {
		return min( 50, max( 1, absint( $value ) ) );
	}

	/**
	 * Sanitize lockout duration setting.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_rate_limit_lockout( $value ) {
		return min( 86400, max( 60, absint( $value ) ) );
	}

	/**
	 * Sanitize challenge timeout setting.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_challenge_ttl( $value ) {
		return min( 1200, max( 30, absint( $value ) ) );
	}
}
