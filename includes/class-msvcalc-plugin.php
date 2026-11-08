<?php
/**
 * Bootstrap: wires the parts together.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Plugin entry point.
 */
final class MSVCalc_Plugin {

	/**
	 * Hooks every part of the plugin.
	 */
	public static function init(): void {
		MSVCalc_Leads::init();
		MSVCalc_REST::init();
		MSVCalc_Frontend::init();
		MSVCalc_Privacy::init();

		if ( is_admin() ) {
			MSVCalc_Admin::init();
			MSVCalc_Leads_Admin::init();
		}

		add_action( 'update_option_' . MSVCalc_Settings::OPTION, array( 'MSVCalc_Settings', 'flush' ) );
		add_action( 'update_option_' . MSVCalc_Settings::OPTION, array( 'MSVCalc_Leads', 'sync_roles' ), 20 );
		add_action( 'add_option_' . MSVCalc_Settings::OPTION, array( 'MSVCalc_Leads', 'sync_roles' ), 20 );
	}

	/**
	 * Activation: default settings and the daily clean-up.
	 */
	public static function activate(): void {
		if ( false === get_option( MSVCalc_Settings::OPTION ) ) {
			add_option( MSVCalc_Settings::OPTION, MSVCalc_Settings::defaults() );
		}
		MSVCalc_Leads::schedule();
		MSVCalc_Leads::sync_roles();
	}

	/**
	 * Deactivation: removes the scheduled clean-up. Data stays until the
	 * plugin is deleted.
	 */
	public static function deactivate(): void {
		MSVCalc_Leads::unschedule();
	}
}
