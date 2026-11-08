<?php
/**
 * Admin screens: overview, settings, items editor and capacity tables.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Builds the "Volume Calculator" admin menu and handles its forms.
 */
final class MSVCalc_Admin {

	/**
	 * Top-level menu slug (the overview page).
	 */
	const MENU_SLUG = 'msvcalc';

	/**
	 * Settings page slug.
	 */
	const SETTINGS_SLUG = 'msvcalc-settings';

	/**
	 * Items editor slug.
	 */
	const ITEMS_SLUG = 'msvcalc-items';

	/**
	 * Capacity tables slug.
	 */
	const TABLES_SLUG = 'msvcalc-tables';

	/**
	 * Leads page slug.
	 */
	const LEADS_SLUG = 'msvcalc-leads';

	/**
	 * Largest import file, in bytes.
	 */
	const MAX_IMPORT_BYTES = 2097152;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_msvcalc_save_catalogue', array( __CLASS__, 'save_catalogue' ) );
		add_action( 'admin_post_msvcalc_export_catalogue', array( __CLASS__, 'export_catalogue' ) );
		add_action( 'admin_post_msvcalc_import_catalogue', array( __CLASS__, 'import_catalogue' ) );
		add_action( 'admin_post_msvcalc_reset_catalogue', array( __CLASS__, 'reset_catalogue' ) );
		add_action( 'admin_post_msvcalc_save_tables', array( __CLASS__, 'save_tables' ) );
		add_action( 'admin_post_msvcalc_reset_tables', array( __CLASS__, 'reset_tables' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MSVCALC_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );
		add_filter( 'removable_query_args', array( __CLASS__, 'removable_args' ) );
	}

	/**
	 * Notice arguments WordPress drops from the address once shown.
	 *
	 * @param string[] $args Arguments.
	 * @return string[]
	 */
	public static function removable_args( $args ) {
		return array_merge( (array) $args, array( 'msvcalc_notice', 'msvcalc_count' ) );
	}

	/**
	 * Admin menu.
	 */
	public static function menu(): void {
		add_menu_page(
			__( 'Moving & Storage Volume Calculator', 'moving-storage-volume-calculator' ),
			__( 'Volume Calculator', 'moving-storage-volume-calculator' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'page_overview' ),
			self::menu_icon(),
			58
		);
		add_submenu_page( self::MENU_SLUG, __( 'Overview', 'moving-storage-volume-calculator' ), __( 'Overview', 'moving-storage-volume-calculator' ), 'manage_options', self::MENU_SLUG, array( __CLASS__, 'page_overview' ) );
		add_submenu_page( self::MENU_SLUG, __( 'Rooms & items', 'moving-storage-volume-calculator' ), __( 'Rooms & items', 'moving-storage-volume-calculator' ), 'manage_options', self::ITEMS_SLUG, array( __CLASS__, 'page_items' ) );
		add_submenu_page( self::MENU_SLUG, __( 'Vehicles & storage', 'moving-storage-volume-calculator' ), __( 'Vehicles & storage', 'moving-storage-volume-calculator' ), 'manage_options', self::TABLES_SLUG, array( __CLASS__, 'page_tables' ) );
		MSVCalc_Leads_Admin::add_page();
		add_submenu_page( self::MENU_SLUG, __( 'Settings', 'moving-storage-volume-calculator' ), __( 'Settings', 'moving-storage-volume-calculator' ), 'manage_options', self::SETTINGS_SLUG, array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * The admin menu icon: a filled version of the cube mark, as an SVG data
	 * URI so it follows the admin colour scheme.
	 *
	 * @return string
	 */
	public static function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.2 17.6 5.6 10 10 2.4 5.6z" opacity=".55"/><path fill="black" d="M2 6.4 9.4 10.7v8.6L2 15z"/><path fill="black" d="M18 6.4v8.6l-7.4 4.3v-8.6z" opacity=".8"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Data URI for the admin menu icon, as documented for add_menu_page().
	}

	/**
	 * Whether the current screen belongs to the plugin.
	 *
	 * @param string $hook Admin page hook suffix.
	 * @return bool
	 */
	private static function is_plugin_screen( string $hook ): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && MSVCalc_Leads::POST_TYPE === $screen->post_type ) {
			return true;
		}
		return false !== strpos( $hook, self::MENU_SLUG );
	}

	/**
	 * Admin assets, only on the plugin's own screens.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public static function assets( string $hook ): void {
		if ( ! self::is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style( 'msvcalc-admin', MSVCALC_URL . 'assets/css/admin.css', array(), MSVCALC_VERSION );
		wp_enqueue_script(
			'msvcalc-admin',
			MSVCALC_URL . 'assets/js/admin.js',
			array( 'msvcalc-icons' ),
			MSVCALC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which assets to load.

		if ( self::SETTINGS_SLUG === $page ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}

		if ( self::MENU_SLUG === $page ) {
			MSVCalc_Frontend::enqueue();
		}

		$data = array(
			'i18n' => self::editor_strings(),
		);

		if ( self::ITEMS_SLUG === $page ) {
			$data['catalogue']  = MSVCalc_Catalogue::get();
			$data['icons']      = MSVCalc_Icons::pickable();
			$data['groups']     = MSVCalc_Catalogue::groups();
			$data['iconGroups'] = MSVCalc_Icons::picker_groups();
			$data['units']      = (string) MSVCalc_Settings::get( 'units' );
		}

		if ( self::TABLES_SLUG === $page ) {
			$data['tables']  = MSVCalc_Catalogue::tables();
			$data['regions'] = self::regions();
			$data['units']   = (string) MSVCalc_Settings::get( 'units' );
		}

		wp_add_inline_script(
			'msvcalc-admin',
			'window.msvcalcAdmin = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';',
			'before'
		);
	}

	/**
	 * Region labels.
	 *
	 * @return array<string, string>
	 */
	public static function regions(): array {
		return array(
			'uk' => __( 'United Kingdom & Ireland', 'moving-storage-volume-calculator' ),
			'eu' => __( 'Europe', 'moving-storage-volume-calculator' ),
			'us' => __( 'United States & Canada', 'moving-storage-volume-calculator' ),
			'au' => __( 'Australia & New Zealand', 'moving-storage-volume-calculator' ),
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array<int|string, string> $links Links.
	 * @return array<int|string, string>
	 */
	public static function action_links( array $links ): array {
		$settings = sprintf( '<a href="%1$s">%2$s</a>', esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ), esc_html__( 'Settings', 'moving-storage-volume-calculator' ) );
		array_unshift( $links, $settings );
		return $links;
	}

	/**
	 * Footer note on the plugin's screens.
	 *
	 * @param string|mixed $text Footer text.
	 * @return string|mixed
	 */
	public static function footer_text( $text ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ( false !== strpos( (string) $screen->id, self::MENU_SLUG ) || MSVCalc_Leads::POST_TYPE === $screen->post_type ) ) {
			return sprintf(
				/* translators: 1: plugin name, 2: author name, 3: link to leave a review. */
				esc_html__( 'Thank you for using %1$s by %2$s. Enjoying it? %3$s', 'moving-storage-volume-calculator' ),
				'<strong>' . esc_html__( 'Moving & Storage Volume Calculator', 'moving-storage-volume-calculator' ) . '</strong>',
				esc_html__( 'Harsh Prajapati', 'moving-storage-volume-calculator' ),
				'<a href="' . esc_url( 'https://wordpress.org/support/plugin/moving-storage-volume-calculator/reviews/#new-post' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Leave a ★★★★★ review', 'moving-storage-volume-calculator' ) . '</a>'
			);
		}
		return $text;
	}

	/**
	 * Common page header, for the plugin's other admin screens.
	 *
	 * @param string $title Page title.
	 */
	public static function page_header( string $title ): void {
		self::header( $title );
	}

	/**
	 * Common page header with the logo.
	 *
	 * @param string $title Page title.
	 */
	private static function header( string $title ): void {
		echo '<div class="msvcalc-admin-head">';
		echo wp_kses( MSVCalc_Icons::logo( 40 ), MSVCalc_Icons::allowed_html() );
		echo '<div><h1>' . esc_html( $title ) . '</h1>';
		echo '<p>' . esc_html__( 'Moving & Storage Volume Calculator', 'moving-storage-volume-calculator' ) . ' <span class="msvcalc-version">' . esc_html( MSVCALC_VERSION ) . '</span></p></div>';
		echo '</div>';
		echo '<hr class="wp-header-end">';
	}

	/**
	 * Shows a notice from a redirect.
	 */
	private static function notices(): void {
		$notice = isset( $_GET['msvcalc_notice'] ) ? sanitize_key( wp_unslash( $_GET['msvcalc_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a fixed message.
		$map    = array(
			'saved'        => array( 'success', __( 'Changes saved.', 'moving-storage-volume-calculator' ) ),
			'imported'     => array( 'success', __( 'Items imported.', 'moving-storage-volume-calculator' ) ),
			'reset'        => array( 'success', __( 'Defaults restored.', 'moving-storage-volume-calculator' ) ),
			'invalid'      => array( 'error', __( 'Nothing was saved: the data was not valid.', 'moving-storage-volume-calculator' ) ),
			'bad_file'     => array( 'error', __( 'Please choose a JSON file exported from this plugin (2 MB or smaller).', 'moving-storage-volume-calculator' ) ),
			'empty_import' => array( 'error', __( 'That file has no valid rooms and items.', 'moving-storage-volume-calculator' ) ),
		);
		if ( isset( $map[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $notice ][0] ), esc_html( $map[ $notice ][1] ) );
		}
	}

	/**
	 * Overview page.
	 */
	public static function page_overview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$catalogue = MSVCalc_Catalogue::get();
		$counts    = wp_count_posts( MSVCalc_Leads::POST_TYPE );
		$leads     = isset( $counts->private ) ? (int) $counts->private : 0;
		$new       = count(
			get_posts(
				array(
					'post_type'      => MSVCalc_Leads::POST_TYPE,
					'post_status'    => 'private',
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => MSVCalc_Leads::STATUS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small admin-only count.
					'meta_value'     => 'new', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small admin-only count.
				)
			)
		);
		?>
		<div class="wrap msvcalc-wrap">
			<?php self::header( __( 'Overview', 'moving-storage-volume-calculator' ) ); ?>

			<div class="msvcalc-stats">
				<div class="msvcalc-stat">
					<?php MSVCalc_Icons::render( 'list', 22 ); ?>
					<strong><?php echo esc_html( number_format_i18n( count( $catalogue['items'] ) ) ); ?></strong>
					<span><?php esc_html_e( 'items in', 'moving-storage-volume-calculator' ); ?> <?php echo esc_html( number_format_i18n( count( $catalogue['rooms'] ) ) ); ?> <?php esc_html_e( 'rooms', 'moving-storage-volume-calculator' ); ?></span>
				</div>
				<div class="msvcalc-stat">
					<?php MSVCalc_Icons::render( 'mail', 22 ); ?>
					<strong><?php echo esc_html( number_format_i18n( $leads ) ); ?></strong>
					<span><?php esc_html_e( 'quote requests', 'moving-storage-volume-calculator' ); ?></span>
				</div>
				<div class="msvcalc-stat">
					<?php MSVCalc_Icons::render( 'sparkle', 22 ); ?>
					<strong><?php echo esc_html( number_format_i18n( $new ) ); ?></strong>
					<span><?php esc_html_e( 'waiting for a reply', 'moving-storage-volume-calculator' ); ?></span>
				</div>
			</div>

			<div class="msvcalc-columns">
				<div class="msvcalc-card">
					<h2><?php esc_html_e( 'Add the calculator to a page', 'moving-storage-volume-calculator' ); ?></h2>
					<p><?php esc_html_e( 'In the block editor, add the "Volume Calculator" block. Anywhere else (classic editor, page builders, widgets), paste this shortcode:', 'moving-storage-volume-calculator' ); ?></p>
					<div class="msvcalc-copy">
						<code id="msvcalc-shortcode">[<?php echo esc_html( MSVCalc_Frontend::SHORTCODE ); ?>]</code>
						<button type="button" class="button" data-msvcalc-copy="#msvcalc-shortcode"><?php esc_html_e( 'Copy', 'moving-storage-volume-calculator' ); ?></button>
					</div>
					<p class="msvcalc-muted"><?php esc_html_e( 'Optional attributes: units="m3" or "ft3", theme="light", "dark" or "auto", quote="no" to hide the quote form, title="…", intro="…", accent="#0fb5ba".', 'moving-storage-volume-calculator' ); ?></p>
				</div>
				<div class="msvcalc-card">
					<h2><?php esc_html_e( 'Make it yours', 'moving-storage-volume-calculator' ); ?></h2>
					<ul class="msvcalc-links">
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ); ?>"><?php MSVCalc_Icons::render( 'sparkle', 18 ); ?><?php esc_html_e( 'Colours, units, region and the quote form', 'moving-storage-volume-calculator' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::ITEMS_SLUG ) ); ?>"><?php MSVCalc_Icons::render( 'list', 18 ); ?><?php esc_html_e( 'Rooms, items, volumes and quick-start presets', 'moving-storage-volume-calculator' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::TABLES_SLUG ) ); ?>"><?php MSVCalc_Icons::render( 'truck', 18 ); ?><?php esc_html_e( 'Your vans, trucks, containers and storage units', 'moving-storage-volume-calculator' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::LEADS_SLUG ) ); ?>"><?php MSVCalc_Icons::render( 'mail', 18 ); ?><?php esc_html_e( 'Leads: follow-ups, notes, CSV and PDF export', 'moving-storage-volume-calculator' ); ?></a></li>
					</ul>
				</div>
			</div>

			<h2 class="msvcalc-preview-title"><?php esc_html_e( 'Live preview', 'moving-storage-volume-calculator' ); ?></h2>
			<p class="msvcalc-muted"><?php esc_html_e( 'This is how visitors see the calculator. Quote requests sent from this preview are real.', 'moving-storage-volume-calculator' ); ?></p>
			<div class="msvcalc-preview">
				<?php
				echo MSVCalc_Frontend::render( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by the plugin's own template, every value inside is escaped.
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Settings tabs.
	 *
	 * @return array<string, string>
	 */
	private static function tabs(): array {
		return array(
			'calculator' => __( 'Calculator', 'moving-storage-volume-calculator' ),
			'look'       => __( 'Look & feel', 'moving-storage-volume-calculator' ),
			'quote'      => __( 'Quote form', 'moving-storage-volume-calculator' ),
			'privacy'    => __( 'Data & privacy', 'moving-storage-volume-calculator' ),
		);
	}

	/**
	 * Field definitions for the settings screen.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function fields(): array {
		$visibility = array(
			'required' => __( 'Required', 'moving-storage-volume-calculator' ),
			'optional' => __( 'Optional', 'moving-storage-volume-calculator' ),
			'hidden'   => __( 'Hidden', 'moving-storage-volume-calculator' ),
		);

		return array(
			'units'               => array(
				'calculator',
				__( 'Default unit', 'moving-storage-volume-calculator' ),
				'select',
				array(
					'm3'  => __( 'Cubic metres (m³)', 'moving-storage-volume-calculator' ),
					'ft3' => __( 'Cubic feet (ft³)', 'moving-storage-volume-calculator' ),
				),
			),
			'unit_toggle'         => array( 'calculator', __( 'Unit switch', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Let visitors switch between m³ and ft³', 'moving-storage-volume-calculator' ) ),
			'region'              => array( 'calculator', __( 'Region', 'moving-storage-volume-calculator' ), 'select', self::regions(), __( 'Picks the vehicle and storage unit sizes used for suggestions.', 'moving-storage-volume-calculator' ) ),
			'margin'              => array( 'calculator', __( 'Packing margin', 'moving-storage-volume-calculator' ), 'number', '%', __( 'Extra space for packing, padding and gaps, added to the total. 10% is typical.', 'moving-storage-volume-calculator' ) ),
			'show_presets'        => array( 'calculator', __( 'Quick start', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Show ready-made lists (few items, studio, 1 to 5 bed, garage, storage unit, office, shop, café)', 'moving-storage-volume-calculator' ) ),
			'show_search'         => array( 'calculator', __( 'Search', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Show the item search', 'moving-storage-volume-calculator' ) ),
			'show_custom'         => array( 'calculator', __( 'Custom items', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Let visitors add items that are not listed', 'moving-storage-volume-calculator' ) ),
			'show_boxes'          => array( 'calculator', __( 'Box count', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Show the number of boxes', 'moving-storage-volume-calculator' ) ),
			'show_weight'         => array( 'calculator', __( 'Weight', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Show an estimated weight', 'moving-storage-volume-calculator' ) ),
			'show_vehicle'        => array( 'calculator', __( 'Vehicle', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Suggest a van or truck', 'moving-storage-volume-calculator' ) ),
			'show_container'      => array( 'calculator', __( 'Container', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Suggest a shipping container (for international moves)', 'moving-storage-volume-calculator' ) ),
			'show_storage'        => array( 'calculator', __( 'Storage unit', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Suggest a storage unit size', 'moving-storage-volume-calculator' ) ),
			'show_print'          => array( 'calculator', __( 'Print', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Show a print button for the inventory', 'moving-storage-volume-calculator' ) ),
			'remember'            => array( 'calculator', __( 'Remember the list', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Keep the visitor\'s list in their browser so they can come back to it', 'moving-storage-volume-calculator' ) ),
			'title'               => array( 'calculator', __( 'Heading', 'moving-storage-volume-calculator' ), 'text', __( 'How much are you moving?', 'moving-storage-volume-calculator' ) ),
			'intro'               => array( 'calculator', __( 'Introduction', 'moving-storage-volume-calculator' ), 'textarea', MSVCalc_Settings::text( 'intro' ) ),
			'theme'               => array(
				'look',
				__( 'Theme', 'moving-storage-volume-calculator' ),
				'select',
				array(
					'light' => __( 'Light', 'moving-storage-volume-calculator' ),
					'dark'  => __( 'Dark', 'moving-storage-volume-calculator' ),
					'auto'  => __( 'Follow the visitor\'s device', 'moving-storage-volume-calculator' ),
				),
			),
			'accent'              => array( 'look', __( 'Main colour', 'moving-storage-volume-calculator' ), 'color' ),
			'accent_2'            => array( 'look', __( 'Gradient colour', 'moving-storage-volume-calculator' ), 'color', __( 'Blended with the main colour in buttons, the total and the fill bars.', 'moving-storage-volume-calculator' ) ),
			'radius'              => array( 'look', __( 'Corner roundness', 'moving-storage-volume-calculator' ), 'number', 'px' ),
			'animations'          => array( 'look', __( 'Animations', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Use smooth transitions (always off for visitors who prefer reduced motion)', 'moving-storage-volume-calculator' ) ),
			'show_credit'         => array( 'look', __( 'Plugin credit', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Show a small "Powered by Moving & Storage Volume Calculator" link under the calculator and in customer emails. Off unless you turn it on; it helps other businesses find this free plugin.', 'moving-storage-volume-calculator' ) ),
			'quote_enabled'       => array( 'quote', __( 'Quote form', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Let visitors send their inventory for a quote', 'moving-storage-volume-calculator' ) ),
			'notify_email'        => array( 'quote', __( 'Send requests to', 'moving-storage-volume-calculator' ), 'email', (string) get_option( 'admin_email' ) ),
			'company_name'        => array( 'quote', __( 'Company name', 'moving-storage-volume-calculator' ), 'text', MSVCalc_Settings::text( 'company_name' ) ),
			'send_copy'           => array( 'quote', __( 'Customer copy', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Email the customer a copy of their inventory', 'moving-storage-volume-calculator' ) ),
			'field_phone'         => array( 'quote', __( 'Phone field', 'moving-storage-volume-calculator' ), 'select', $visibility ),
			'field_addresses'     => array( 'quote', __( 'From / to fields', 'moving-storage-volume-calculator' ), 'select', $visibility ),
			'field_date'          => array( 'quote', __( 'Moving date field', 'moving-storage-volume-calculator' ), 'select', $visibility ),
			'field_service'       => array( 'quote', __( 'Service field', 'moving-storage-volume-calculator' ), 'select', $visibility ),
			'field_access'        => array( 'quote', __( 'Floor, lift and parking', 'moving-storage-volume-calculator' ), 'select', $visibility ),
			'field_extras'        => array( 'quote', __( 'Extra services', 'moving-storage-volume-calculator' ), 'select', array_intersect_key( $visibility, array_flip( array( 'optional', 'hidden' ) ) ) ),
			'cta_text'            => array( 'quote', __( 'Button text', 'moving-storage-volume-calculator' ), 'text', MSVCalc_Settings::text( 'cta_text' ) ),
			'consent_text'        => array( 'quote', __( 'Consent text', 'moving-storage-volume-calculator' ), 'textarea', MSVCalc_Settings::text( 'consent_text' ) ),
			'success_message'     => array( 'quote', __( 'Thank-you message', 'moving-storage-volume-calculator' ), 'textarea', MSVCalc_Settings::text( 'success_message' ) ),
			'thank_you_page'      => array( 'quote', __( 'Thank-you page', 'moving-storage-volume-calculator' ), 'page', __( 'Optional. Send visitors to this page after a request, for example to track conversions.', 'moving-storage-volume-calculator' ) ),
			'store_leads'         => array( 'privacy', __( 'Store requests', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Keep quote requests in WordPress (Volume Calculator > Leads) as well as emailing them', 'moving-storage-volume-calculator' ) ),
			'leads_access'        => array(
				'privacy',
				__( 'Who can manage leads', 'moving-storage-volume-calculator' ),
				'select',
				array(
					'admins'  => __( 'Administrators only', 'moving-storage-volume-calculator' ),
					'editors' => __( 'Administrators and Editors', 'moving-storage-volume-calculator' ),
				),
				__( 'Editors see only the Leads screen, never the plugin settings. A role editor plugin can also give the msvcalc_manage_leads capability to any other role.', 'moving-storage-volume-calculator' ),
			),
			'retention_days'      => array( 'privacy', __( 'Keep requests for', 'moving-storage-volume-calculator' ), 'number', __( 'days', 'moving-storage-volume-calculator' ), __( 'Older requests are deleted automatically. 0 keeps them until you delete them.', 'moving-storage-volume-calculator' ) ),
			'delete_on_uninstall' => array( 'privacy', __( 'Remove data', 'moving-storage-volume-calculator' ), 'checkbox', __( 'Delete all settings, edited items and quote requests when the plugin is deleted', 'moving-storage-volume-calculator' ) ),
		);
	}

	/**
	 * Registers the setting, sections and fields.
	 */
	public static function register_settings(): void {
		MSVCalc_Settings::register();

		foreach ( self::tabs() as $tab => $label ) {
			add_settings_section( 'msvcalc_' . $tab, '', '__return_false', self::SETTINGS_SLUG . '-' . $tab );
		}

		foreach ( self::fields() as $key => $field ) {
			add_settings_field(
				'msvcalc_' . $key,
				$field[1],
				array( __CLASS__, 'render_field' ),
				self::SETTINGS_SLUG . '-' . $field[0],
				'msvcalc_' . $field[0],
				array(
					'key'       => $key,
					'field'     => $field,
					'label_for' => in_array( $field[2], array( 'checkbox' ), true ) ? null : 'msvcalc-field-' . $key,
				)
			);
		}
	}

	/**
	 * Renders one settings field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public static function render_field( array $args ): void {
		$key   = (string) $args['key'];
		$field = (array) $args['field'];
		$type  = (string) $field[2];
		$value = MSVCalc_Settings::get( $key );
		$name  = MSVCalc_Settings::OPTION . '[' . $key . ']';
		$id    = 'msvcalc-field-' . $key;
		$help  = '';

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="hidden" name="%2$s" value="0"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html( (string) $field[3] )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( (array) $field[3] as $option => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( (string) $option ), selected( (string) $value, (string) $option, false ), esc_html( (string) $label ) );
				}
				echo '</select>';
				$help = (string) ( $field[4] ?? '' );
				break;

			case 'number':
				$schema = MSVCalc_Settings::schema()[ $key ];
				printf(
					'<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$d" min="%4$d" max="%5$d" step="1"> %6$s',
					esc_attr( $id ),
					esc_attr( $name ),
					(int) $value,
					(int) $schema['min'],
					(int) $schema['max'],
					esc_html( (string) ( $field[3] ?? '' ) )
				);
				$help = (string) ( $field[4] ?? '' );
				break;

			case 'text':
			case 'email':
				printf(
					'<input type="%1$s" class="regular-text" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s">',
					esc_attr( 'email' === $type ? 'email' : 'text' ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $field[3] ?? '' ) )
				);
				break;

			case 'textarea':
				printf(
					'<textarea class="large-text" rows="3" id="%1$s" name="%2$s" placeholder="%3$s">%4$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) ( $field[3] ?? '' ) ),
					esc_textarea( (string) $value )
				);
				echo '<p class="description">' . esc_html__( 'Leave empty to use the default text shown in grey.', 'moving-storage-volume-calculator' ) . '</p>';
				break;

			case 'color':
				printf(
					'<input type="text" class="msvcalc-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="%4$s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( (string) MSVCalc_Settings::schema()[ $key ]['default'] )
				);
				$help = (string) ( $field[3] ?? '' );
				break;

			case 'page':
				wp_dropdown_pages(
					array(
						'name'              => esc_attr( $name ),
						'id'                => esc_attr( $id ),
						'selected'          => (int) $value,
						'show_option_none'  => esc_html__( '— Stay on the calculator —', 'moving-storage-volume-calculator' ),
						'option_none_value' => '0',
					)
				);
				$help = (string) ( $field[3] ?? '' );
				break;
		}

		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
	}

	/**
	 * Settings page.
	 */
	public static function page_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = self::tabs();
		?>
		<div class="wrap msvcalc-wrap">
			<?php self::header( __( 'Settings', 'moving-storage-volume-calculator' ) ); ?>
			<?php settings_errors(); ?>
			<form method="post" action="options.php" class="msvcalc-settings">
				<?php settings_fields( MSVCalc_Settings::GROUP ); ?>
				<nav class="nav-tab-wrapper msvcalc-tabs" role="tablist">
					<?php
					$first = true;
					foreach ( $tabs as $tab => $label ) {
						printf(
							'<a href="#msvcalc-tab-%1$s" class="nav-tab%2$s" role="tab" aria-controls="msvcalc-tab-%1$s" aria-selected="%3$s" data-msvcalc-tab="%1$s">%4$s</a>',
							esc_attr( $tab ),
							$first ? ' nav-tab-active' : '',
							$first ? 'true' : 'false',
							esc_html( $label )
						);
						$first = false;
					}
					?>
				</nav>
				<?php
				$first = true;
				foreach ( $tabs as $tab => $label ) {
					printf( '<div class="msvcalc-tab-panel" id="msvcalc-tab-%1$s" role="tabpanel"%2$s>', esc_attr( $tab ), $first ? '' : ' hidden' );
					echo '<h2 class="screen-reader-text">' . esc_html( $label ) . '</h2>';
					do_settings_sections( self::SETTINGS_SLUG . '-' . $tab );
					echo '</div>';
					$first = false;
				}
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Rooms & items editor page. The editor itself is built by admin.js from
	 * the catalogue data; the form posts it back as one JSON field.
	 */
	public static function page_items(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap msvcalc-wrap">
			<?php self::header( __( 'Rooms & items', 'moving-storage-volume-calculator' ) ); ?>
			<?php self::notices(); ?>
			<p class="msvcalc-lead-text">
				<?php esc_html_e( 'Rename, add or remove rooms and items, tune volumes to match your own surveys, and choose which items show first. Volumes are entered in your default unit.', 'moving-storage-volume-calculator' ); ?>
				<?php if ( MSVCalc_Catalogue::is_customised() ) : ?>
					<span class="msvcalc-badge"><?php esc_html_e( 'Customised', 'moving-storage-volume-calculator' ); ?></span>
				<?php endif; ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="msvcalc-items-form">
				<input type="hidden" name="action" value="msvcalc_save_catalogue">
				<?php wp_nonce_field( 'msvcalc_save_catalogue', 'msvcalc_nonce' ); ?>
				<textarea name="msvcalc_catalogue_json" id="msvcalc-catalogue-json" hidden></textarea>
				<div id="msvcalc-items-editor" class="msvcalc-editor" data-editor="items">
					<p class="msvcalc-muted"><?php esc_html_e( 'Loading the editor…', 'moving-storage-volume-calculator' ); ?></p>
				</div>
				<div class="msvcalc-savebar">
					<?php submit_button( __( 'Save rooms & items', 'moving-storage-volume-calculator' ), 'primary', 'submit', false ); ?>
					<span class="msvcalc-dirty" hidden><?php esc_html_e( 'You have unsaved changes.', 'moving-storage-volume-calculator' ); ?></span>
				</div>
			</form>

			<div class="msvcalc-columns msvcalc-tools">
				<div class="msvcalc-card">
					<h2><?php esc_html_e( 'Export', 'moving-storage-volume-calculator' ); ?></h2>
					<p><?php esc_html_e( 'Download your rooms, items and presets as a JSON file, to back them up or copy them to another site.', 'moving-storage-volume-calculator' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="msvcalc_export_catalogue">
						<?php wp_nonce_field( 'msvcalc_export_catalogue', 'msvcalc_nonce' ); ?>
						<?php submit_button( __( 'Download JSON', 'moving-storage-volume-calculator' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
				<div class="msvcalc-card">
					<h2><?php esc_html_e( 'Import', 'moving-storage-volume-calculator' ); ?></h2>
					<p><?php esc_html_e( 'Replace the current rooms, items and presets with a file exported from this plugin.', 'moving-storage-volume-calculator' ); ?></p>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="msvcalc_import_catalogue">
						<?php wp_nonce_field( 'msvcalc_import_catalogue', 'msvcalc_nonce' ); ?>
						<input type="file" name="msvcalc_import" accept=".json,application/json" required>
						<?php submit_button( __( 'Import', 'moving-storage-volume-calculator' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
				<div class="msvcalc-card">
					<h2><?php esc_html_e( 'Restore defaults', 'moving-storage-volume-calculator' ); ?></h2>
					<p><?php esc_html_e( 'Go back to the rooms, items and presets that ship with the plugin. Your edits are removed.', 'moving-storage-volume-calculator' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-msvcalc-confirm="<?php esc_attr_e( 'Restore the default rooms and items? Your edits will be lost.', 'moving-storage-volume-calculator' ); ?>">
						<input type="hidden" name="action" value="msvcalc_reset_catalogue">
						<?php wp_nonce_field( 'msvcalc_reset_catalogue', 'msvcalc_nonce' ); ?>
						<?php submit_button( __( 'Restore defaults', 'moving-storage-volume-calculator' ), 'delete', 'submit', false ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Vehicles & storage page.
	 */
	public static function page_tables(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap msvcalc-wrap">
			<?php self::header( __( 'Vehicles & storage', 'moving-storage-volume-calculator' ) ); ?>
			<?php self::notices(); ?>
			<p class="msvcalc-lead-text"><?php esc_html_e( 'The calculator suggests the smallest vehicle, container and storage unit that holds the visitor\'s load. Enter usable (load) capacity, not the gross body size. Add your own fleet and unit sizes for an exact match.', 'moving-storage-volume-calculator' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="msvcalc-tables-form">
				<input type="hidden" name="action" value="msvcalc_save_tables">
				<?php wp_nonce_field( 'msvcalc_save_tables', 'msvcalc_nonce' ); ?>
				<textarea name="msvcalc_tables_json" id="msvcalc-tables-json" hidden></textarea>
				<div id="msvcalc-tables-editor" class="msvcalc-editor" data-editor="tables">
					<p class="msvcalc-muted"><?php esc_html_e( 'Loading the editor…', 'moving-storage-volume-calculator' ); ?></p>
				</div>
				<div class="msvcalc-savebar">
					<?php submit_button( __( 'Save vehicles & storage', 'moving-storage-volume-calculator' ), 'primary', 'submit', false ); ?>
					<span class="msvcalc-dirty" hidden><?php esc_html_e( 'You have unsaved changes.', 'moving-storage-volume-calculator' ); ?></span>
				</div>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msvcalc-reset-form" data-msvcalc-confirm="<?php esc_attr_e( 'Restore the default vehicles, containers and storage units?', 'moving-storage-volume-calculator' ); ?>">
				<input type="hidden" name="action" value="msvcalc_reset_tables">
				<?php wp_nonce_field( 'msvcalc_reset_tables', 'msvcalc_nonce' ); ?>
				<?php submit_button( __( 'Restore defaults', 'moving-storage-volume-calculator' ), 'delete', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Stops unless the current user can manage the calculator. Each admin-post
	 * handler calls this, then checks its own nonce with check_admin_referer().
	 */
	private static function require_capability(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change the volume calculator.', 'moving-storage-volume-calculator' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Redirects back to a plugin page with a notice.
	 *
	 * @param string $page   Page slug.
	 * @param string $notice Notice key.
	 */
	private static function back( string $page, string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => $page,
					'msvcalc_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Decodes a posted JSON document. The decoded data is sanitized value by
	 * value by the caller.
	 *
	 * @param mixed $raw Raw posted value.
	 * @return array<mixed>|null
	 */
	private static function decode_json( $raw ): ?array {
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > self::MAX_IMPORT_BYTES ) {
			return null;
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Saves the items editor.
	 */
	public static function save_catalogue(): void {
		self::require_capability();
		check_admin_referer( 'msvcalc_save_catalogue', 'msvcalc_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- A JSON document: it is decoded below and every value is sanitized by MSVCalc_Catalogue::sanitize().
		$raw  = isset( $_POST['msvcalc_catalogue_json'] ) ? wp_unslash( $_POST['msvcalc_catalogue_json'] ) : '';
		$data = self::decode_json( $raw );
		if ( null === $data ) {
			self::back( self::ITEMS_SLUG, 'invalid' );
		}
		$clean = MSVCalc_Catalogue::sanitize( $data );
		if ( ! $clean['items'] ) {
			self::back( self::ITEMS_SLUG, 'invalid' );
		}
		MSVCalc_Catalogue::save( $clean );
		self::back( self::ITEMS_SLUG, 'saved' );
	}

	/**
	 * Downloads the catalogue as JSON.
	 */
	public static function export_catalogue(): void {
		self::require_capability();
		check_admin_referer( 'msvcalc_export_catalogue', 'msvcalc_nonce' );
		$payload = array(
			'plugin'  => 'moving-storage-volume-calculator',
			'version' => MSVCALC_VERSION,
			'units'   => 'm3',
		) + MSVCalc_Catalogue::get();

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=volume-calculator-items-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Imports a catalogue JSON file.
	 */
	public static function import_catalogue(): void {
		self::require_capability();
		check_admin_referer( 'msvcalc_import_catalogue', 'msvcalc_nonce' );

		$file = isset( $_FILES['msvcalc_import'] ) && is_array( $_FILES['msvcalc_import'] ) ? $_FILES['msvcalc_import'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each part is validated below; the file is only parsed as JSON and never stored.

		$tmp   = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		$name  = isset( $file['name'] ) && is_string( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$size  = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_uploaded_file( $tmp ) || $size < 2 || $size > self::MAX_IMPORT_BYTES || 'json' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			self::back( self::ITEMS_SLUG, 'bad_file' );
		}

		$data = wp_json_file_decode( $tmp, array( 'associative' => true ) );
		if ( ! is_array( $data ) ) {
			self::back( self::ITEMS_SLUG, 'bad_file' );
		}

		// Files are exported in cubic metres; accept files in feet too.
		if ( isset( $data['units'] ) && 'ft3' === $data['units'] && isset( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $index => $item ) {
				if ( is_array( $item ) && isset( $item['m3'] ) && is_numeric( $item['m3'] ) ) {
					$data['items'][ $index ]['m3'] = (float) $item['m3'] / MSVCalc_Calculator::FT3_PER_M3;
				}
			}
		}

		$clean = MSVCalc_Catalogue::sanitize( $data );
		if ( ! $clean['rooms'] || ! $clean['items'] ) {
			self::back( self::ITEMS_SLUG, 'empty_import' );
		}
		MSVCalc_Catalogue::save( $clean );
		self::back( self::ITEMS_SLUG, 'imported' );
	}

	/**
	 * Restores the default catalogue.
	 */
	public static function reset_catalogue(): void {
		self::require_capability();
		check_admin_referer( 'msvcalc_reset_catalogue', 'msvcalc_nonce' );
		MSVCalc_Catalogue::reset();
		self::back( self::ITEMS_SLUG, 'reset' );
	}

	/**
	 * Saves the capacity tables.
	 */
	public static function save_tables(): void {
		self::require_capability();
		check_admin_referer( 'msvcalc_save_tables', 'msvcalc_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- A JSON document: it is decoded below and every value is sanitized by MSVCalc_Catalogue::sanitize_tables().
		$raw  = isset( $_POST['msvcalc_tables_json'] ) ? wp_unslash( $_POST['msvcalc_tables_json'] ) : '';
		$data = self::decode_json( $raw );
		if ( null === $data ) {
			self::back( self::TABLES_SLUG, 'invalid' );
		}
		MSVCalc_Catalogue::save_tables( $data );
		self::back( self::TABLES_SLUG, 'saved' );
	}

	/**
	 * Restores the default capacity tables.
	 */
	public static function reset_tables(): void {
		self::require_capability();
		check_admin_referer( 'msvcalc_reset_tables', 'msvcalc_nonce' );
		MSVCalc_Catalogue::reset_tables();
		self::back( self::TABLES_SLUG, 'reset' );
	}

	/**
	 * Strings for the admin editors.
	 *
	 * @return array<string, string>
	 */
	private static function editor_strings(): array {
		return array(
			'rooms'         => __( 'Rooms', 'moving-storage-volume-calculator' ),
			'addRoom'       => __( 'Add room', 'moving-storage-volume-calculator' ),
			'roomName'      => __( 'Room name', 'moving-storage-volume-calculator' ),
			'roomGroup'     => __( 'Category', 'moving-storage-volume-calculator' ),
			'chooseIcon'    => __( 'Choose icon', 'moving-storage-volume-calculator' ),
			'findIcon'      => __( 'Find an icon', 'moving-storage-volume-calculator' ),
			'noIcons'       => __( 'No icons match.', 'moving-storage-volume-calculator' ),
			'newRoom'       => __( 'New room', 'moving-storage-volume-calculator' ),
			'deleteRoom'    => __( 'Delete room', 'moving-storage-volume-calculator' ),
			'deleteRoomQ'   => __( 'Delete this room and all its items?', 'moving-storage-volume-calculator' ),
			'moveUp'        => __( 'Move up', 'moving-storage-volume-calculator' ),
			'moveDown'      => __( 'Move down', 'moving-storage-volume-calculator' ),
			'icon'          => __( 'Icon', 'moving-storage-volume-calculator' ),
			'item'          => __( 'Item', 'moving-storage-volume-calculator' ),
			'items'         => __( 'Items', 'moving-storage-volume-calculator' ),
			'addItem'       => __( 'Add item', 'moving-storage-volume-calculator' ),
			'newItem'       => __( 'New item', 'moving-storage-volume-calculator' ),
			'volume'        => __( 'Volume', 'moving-storage-volume-calculator' ),
			'weight'        => __( 'Weight (kg)', 'moving-storage-volume-calculator' ),
			'aliases'       => __( 'Search words', 'moving-storage-volume-calculator' ),
			'aliasesHelp'   => __( 'Comma separated, e.g. couch, settee', 'moving-storage-volume-calculator' ),
			'popular'       => __( 'Common', 'moving-storage-volume-calculator' ),
			'popularHelp'   => __( 'Shown before "Show all items"', 'moving-storage-volume-calculator' ),
			'box'           => __( 'Box', 'moving-storage-volume-calculator' ),
			'boxHelp'       => __( 'Counted as a packing box', 'moving-storage-volume-calculator' ),
			'delete'        => __( 'Delete', 'moving-storage-volume-calculator' ),
			'filter'        => __( 'Filter items', 'moving-storage-volume-calculator' ),
			'presets'       => __( 'Quick-start presets', 'moving-storage-volume-calculator' ),
			'presetsHelp'   => __( 'Typical inventories visitors can start from. Set the quantity of each item.', 'moving-storage-volume-calculator' ),
			'preset'        => __( 'Preset', 'moving-storage-volume-calculator' ),
			'addPreset'     => __( 'Add preset', 'moving-storage-volume-calculator' ),
			'newPreset'     => __( 'New preset', 'moving-storage-volume-calculator' ),
			'deletePreset'  => __( 'Delete preset', 'moving-storage-volume-calculator' ),
			'presetTotal'   => __( 'Preset total', 'moving-storage-volume-calculator' ),
			'addToPreset'   => __( 'Add an item to this preset…', 'moving-storage-volume-calculator' ),
			'qty'           => __( 'Qty', 'moving-storage-volume-calculator' ),
			'name'          => __( 'Name', 'moving-storage-volume-calculator' ),
			'note'          => __( 'Note', 'moving-storage-volume-calculator' ),
			'capacity'      => __( 'Usable capacity', 'moving-storage-volume-calculator' ),
			'addRow'        => __( 'Add row', 'moving-storage-volume-calculator' ),
			'vehicles'      => __( 'Vans & trucks', 'moving-storage-volume-calculator' ),
			'containers'    => __( 'Shipping containers', 'moving-storage-volume-calculator' ),
			'storage'       => __( 'Storage units', 'moving-storage-volume-calculator' ),
			'region'        => __( 'Region', 'moving-storage-volume-calculator' ),
			'density'       => __( 'Average density', 'moving-storage-volume-calculator' ),
			'densityHelp'   => __( 'kg per m³ of household goods, used for the weight estimate when an item has no weight. 150–200 is typical.', 'moving-storage-volume-calculator' ),
			'unsaved'       => __( 'You have unsaved changes.', 'moving-storage-volume-calculator' ),
			'copied'        => __( 'Copied', 'moving-storage-volume-calculator' ),
			'working'       => __( 'Working on it…', 'moving-storage-volume-calculator' ),
			'saving'        => __( 'Saving…', 'moving-storage-volume-calculator' ),
			'loading'       => __( 'Loading…', 'moving-storage-volume-calculator' ),
			'preparingCsv'  => __( 'Preparing your download…', 'moving-storage-volume-calculator' ),
			'preparingPdf'  => __( 'Preparing your PDF…', 'moving-storage-volume-calculator' ),
			'failed'        => __( 'That did not work. Please try again.', 'moving-storage-volume-calculator' ),
			'none'          => __( 'Nothing here yet.', 'moving-storage-volume-calculator' ),
			/* translators: %d: number of items. */
			'itemCount'     => __( '%d items', 'moving-storage-volume-calculator' ),
			'invalidVolume' => __( 'Enter a volume above zero.', 'moving-storage-volume-calculator' ),
		);
	}
}
