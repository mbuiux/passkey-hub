<?php
/**
 * Runs when the plugin is deleted from the WordPress admin (Plugins > Delete).
 * Removes all plugin data: DB tables and wp_options entries.
 *
 * This file is only executed when the user explicitly deletes the plugin and
 * has opted in via the standard WordPress uninstall process.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

/**
 * Remove all plugin data from the current blog context.
 */
function wpk_uninstall_cleanup_current_blog(): void {
    global $wpdb;

    // Drop custom tables
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpk_credentials' );    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpk_rate_limits' );    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpk_logs' );           // phpcs:ignore WordPress.DB.DirectDatabaseQuery

    // Remove all plugin options
    $options = array(
        'wpk_enabled',
        'wpk_show_separator',
        'wpk_show_setup_notice',
        'wpk_eligible_roles',
        'wpk_max_passkeys_per_user',
        'wpk_user_verification',
        'wpk_rp_id',
        'wpk_rate_limit_window',
        'wpk_rate_limit_max_failures',
        'wpk_rate_limit_lockout',
        'wpk_rate_window',
        'wpk_rate_max_attempts',
        'wpk_rate_lockout',
        'wpk_challenge_ttl',
        'wpk_login_redirect',
        'wpk_log_retention_days',
        'wpk_rp_name',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // Remove per-user dismissed-notice meta
    $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpk\_notice\_dismissed\_%'"
    );

    // Remove any transients left behind
    $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpk_%' OR option_name LIKE '_transient_timeout_wpk_%'"
    );
}

if ( is_multisite() ) {
    $current_blog_id = get_current_blog_id();

    $page     = 1;
    $per_page = 200;

    do {
        $site_ids = get_sites( array(
            'fields' => 'ids',
            'number' => $per_page,
            'paged'  => $page,
        ) );

        foreach ( $site_ids as $site_id ) {
            switch_to_blog( (int) $site_id );
            wpk_uninstall_cleanup_current_blog();
            restore_current_blog();
        }

        $page++;
    } while ( ! empty( $site_ids ) );

    if ( get_current_blog_id() !== (int) $current_blog_id ) {
        switch_to_blog( (int) $current_blog_id );
        restore_current_blog();
    }
} else {
    wpk_uninstall_cleanup_current_blog();
}
