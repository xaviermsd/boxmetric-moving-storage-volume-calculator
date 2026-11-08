<?php
/**
 * Plugin Name:       Moving & Storage Volume Calculator
 * Description:       A room-by-room removal, storage and moving volume calculator. Visitors add their items, see the total in cubic metres or cubic feet, get a van, container and storage unit suggestion, and send you a quote request.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Harsh Prajapati
 * Author URI:        https://github.com/xaviermsd
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       moving-storage-volume-calculator
 * Domain Path:       /languages
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'MSVCALC_VERSION', '1.0.0' );
define( 'MSVCALC_FILE', __FILE__ );
define( 'MSVCALC_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSVCALC_URL', plugin_dir_url( __FILE__ ) );

require_once MSVCALC_DIR . 'includes/class-msvcalc-settings.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-catalogue.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-calculator.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-icons.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-leads.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-xlsx.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-mailer.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-rest.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-frontend.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-privacy.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-admin.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-leads-admin.php';
require_once MSVCALC_DIR . 'includes/class-msvcalc-plugin.php';

register_activation_hook( __FILE__, array( 'MSVCalc_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MSVCalc_Plugin', 'deactivate' ) );

MSVCalc_Plugin::init();
