<?php
/**
 * Uninstall routine.
 *
 * Plugin settings and the site's own data customisations are removed. Saved
 * projects, case studies and assignments are deliberately left alone: they are
 * work people did, and deleting them because a plugin was removed would be the
 * wrong default. They can be cleared from the Projects and Assignments screens
 * before uninstalling, or with WP-CLI afterwards.
 *
 * @package DesignProjectGenerator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dpg_settings' );
delete_option( 'dpg_custom_data' );
delete_option( 'dpg_disabled_data' );
delete_option( 'dpg_version' );

// Multisite: the same three options exist per site.
if ( is_multisite() ) {
	$sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );

		delete_option( 'dpg_settings' );
		delete_option( 'dpg_custom_data' );
		delete_option( 'dpg_disabled_data' );
		delete_option( 'dpg_version' );

		restore_current_blog();
	}
}
