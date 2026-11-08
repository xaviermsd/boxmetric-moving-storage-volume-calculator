<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * The scheduled clean-up is always removed. Settings, edited items and quote
 * requests are only deleted when "Remove data" is ticked in
 * Volume Calculator > Settings > Data & privacy.
 *
 * @package MSVCalc
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the plugin's data from the current site.
 */
function msvcalc_uninstall_site() {
	wp_clear_scheduled_hook( 'msvcalc_purge_leads' );

	foreach ( array( 'administrator', 'editor' ) as $msvcalc_role_name ) {
		$msvcalc_role = get_role( $msvcalc_role_name );
		if ( $msvcalc_role ) {
			$msvcalc_role->remove_cap( 'msvcalc_manage_leads' );
		}
	}

	$settings = get_option( 'msvcalc_settings', array() );
	if ( ! is_array( $settings ) || empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	do {
		$ids = get_posts(
			array(
				'post_type'      => 'msvcalc_lead',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
		$msvcalc_batch = count( $ids );
	} while ( 100 === $msvcalc_batch );

	delete_option( 'msvcalc_settings' );
	delete_option( 'msvcalc_catalogue' );
	delete_option( 'msvcalc_tables' );
	delete_option( 'msvcalc_leads_db' );
	delete_option( 'msvcalc_leads_db_page' );
}

if ( is_multisite() ) {
	$msvcalc_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $msvcalc_sites as $msvcalc_site ) {
		switch_to_blog( (int) $msvcalc_site );
		msvcalc_uninstall_site();
		restore_current_blog();
	}
} else {
	msvcalc_uninstall_site();
}
