<?php
/**
 * The front-end calculator: shortcode, block render and assets.
 *
 * Assets are registered on every request but only enqueued when a page
 * actually shows the calculator.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Renders the calculator and loads its assets.
 */
final class MSVCalc_Frontend {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'msvcalc_calculator';

	/**
	 * Whether the shared configuration was already printed.
	 *
	 * @var bool
	 */
	private static $config_added = false;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Registers the scripts and styles.
	 */
	public static function register_assets(): void {
		wp_register_style( 'msvcalc-calculator', MSVCALC_URL . 'assets/css/calculator.css', array(), MSVCALC_VERSION );
		wp_register_script(
			'msvcalc-icons',
			MSVCALC_URL . 'assets/js/icons.js',
			array(),
			MSVCALC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_register_script(
			'msvcalc-calculator',
			MSVCALC_URL . 'assets/js/calculator.js',
			array( 'msvcalc-icons' ),
			MSVCALC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Registers the block from its block.json.
	 */
	public static function register_block(): void {
		register_block_type(
			MSVCALC_DIR . 'blocks/calculator',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( array $attributes ): string {
		$atts = array(
			'units'  => (string) ( $attributes['units'] ?? '' ),
			'theme'  => (string) ( $attributes['theme'] ?? '' ),
			'quote'  => isset( $attributes['showQuote'] ) ? ( $attributes['showQuote'] ? 'yes' : 'no' ) : '',
			'title'  => (string) ( $attributes['title'] ?? '' ),
			'intro'  => (string) ( $attributes['intro'] ?? '' ),
			'accent' => (string) ( $attributes['accent'] ?? '' ),
		);

		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes( array( 'class' => 'msvcalc-block' ) ) : 'class="msvcalc-block"';

		return '<div ' . $wrapper . '>' . self::render( $atts ) . '</div>';
	}

	/**
	 * Shortcode callback.
	 *
	 * Attributes: units (m3|ft3), theme (light|dark|auto), quote (yes|no),
	 * title, intro, accent (hex colour).
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'units'  => '',
				'theme'  => '',
				'quote'  => '',
				'title'  => '',
				'intro'  => '',
				'accent' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);
		return self::render( $atts );
	}

	/**
	 * Renders one calculator. The markup is a server-rendered shell that the
	 * script fills in; without JavaScript a short message is shown.
	 *
	 * @param array<string, string> $atts Instance options.
	 * @return string
	 */
	public static function render( array $atts ): string {
		$settings = MSVCalc_Settings::all();

		$units  = in_array( $atts['units'] ?? '', array( 'm3', 'ft3' ), true ) ? $atts['units'] : $settings['units'];
		$theme  = in_array( $atts['theme'] ?? '', array( 'light', 'dark', 'auto' ), true ) ? $atts['theme'] : $settings['theme'];
		$quote  = '' === ( $atts['quote'] ?? '' ) ? (bool) $settings['quote_enabled'] : ( 'yes' === $atts['quote'] && $settings['quote_enabled'] );
		$title  = '' !== ( $atts['title'] ?? '' ) ? sanitize_text_field( $atts['title'] ) : MSVCalc_Settings::text( 'title' );
		$intro  = '' !== ( $atts['intro'] ?? '' ) ? sanitize_text_field( $atts['intro'] ) : MSVCalc_Settings::text( 'intro' );
		$accent = sanitize_hex_color( $atts['accent'] ?? '' );
		$accent = $accent ? $accent : $settings['accent'];

		self::enqueue();

		$style = sprintf(
			'--msvcalc-accent:%1$s;--msvcalc-accent-2:%2$s;--msvcalc-radius:%3$dpx;',
			$accent,
			$settings['accent_2'],
			(int) $settings['radius']
		);

		$options = array(
			'units' => $units,
			'quote' => $quote,
		);

		$classes = array( 'msvcalc', 'msvcalc--' . $theme );
		if ( ! $settings['animations'] ) {
			$classes[] = 'msvcalc--still';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>" data-msvcalc="<?php echo esc_attr( (string) wp_json_encode( $options ) ); ?>">
			<div class="msvcalc__head">
				<span class="msvcalc__mark" aria-hidden="true"><?php MSVCalc_Icons::render( 'cube', 26 ); ?></span>
				<div>
					<h2 class="msvcalc__title"><?php echo esc_html( $title ); ?></h2>
					<?php if ( '' !== $intro ) : ?>
						<p class="msvcalc__intro"><?php echo esc_html( $intro ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="msvcalc__app" aria-live="polite">
				<div class="msvcalc__loading" role="status">
					<span class="msvcalc__loader" aria-hidden="true"><span class="msvcalc__loader-ring"></span><span class="msvcalc__loader-mark"><?php MSVCalc_Icons::render( 'cube', 24 ); ?></span></span>
					<span class="msvcalc__loading-text"><?php esc_html_e( 'Loading the calculator…', 'moving-storage-volume-calculator' ); ?></span>
				</div>
			</div>
			<noscript><p class="msvcalc__noscript"><?php esc_html_e( 'Please turn on JavaScript to use the volume calculator.', 'moving-storage-volume-calculator' ); ?></p></noscript>
			<?php if ( MSVCalc_Settings::get( 'show_credit' ) ) : ?>
				<p class="msvcalc__credit">
					<a href="<?php echo esc_url( 'https://wordpress.org/plugins/moving-storage-volume-calculator/' ); ?>" target="_blank" rel="noopener">
						<?php MSVCalc_Icons::render( 'cube', 14 ); ?>
						<span><?php esc_html_e( 'Powered by Moving & Storage Volume Calculator', 'moving-storage-volume-calculator' ); ?></span>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Enqueues the assets and prints the shared configuration once.
	 */
	public static function enqueue(): void {
		wp_enqueue_style( 'msvcalc-calculator' );
		wp_enqueue_script( 'msvcalc-calculator' );

		if ( self::$config_added ) {
			return;
		}
		self::$config_added = true;

		wp_add_inline_script(
			'msvcalc-calculator',
			'window.msvcalcConfig = ' . wp_json_encode( self::config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';',
			'before'
		);
	}

	/**
	 * Everything the script needs: catalogue, tables, settings and strings.
	 *
	 * @return array<string, mixed>
	 */
	public static function config(): array {
		$settings  = MSVCalc_Settings::all();
		$catalogue = MSVCalc_Catalogue::get();
		$tables    = MSVCalc_Catalogue::region_tables( (string) $settings['region'] );

		$field = static function ( string $key ) use ( $settings ): string {
			return (string) $settings[ $key ];
		};

		return array(
			'version'    => MSVCALC_VERSION,
			'rest'       => esc_url_raw( rest_url( MSVCalc_REST::ROUTE_NS ) ),
			'nonce'      => wp_create_nonce( MSVCalc_REST::NONCE_ACTION ),
			'catalogue'  => $catalogue,
			'groups'     => MSVCalc_Catalogue::groups(),
			'iconGroups' => $settings['show_custom'] ? MSVCalc_Icons::picker_groups() : array(),
			'tables'     => $tables,
			'settings'   => array(
				'unitToggle'  => (bool) $settings['unit_toggle'],
				'margin'      => (int) $settings['margin'],
				'presets'     => (bool) $settings['show_presets'],
				'search'      => (bool) $settings['show_search'],
				'custom'      => (bool) $settings['show_custom'],
				'boxes'       => (bool) $settings['show_boxes'],
				'weight'      => (bool) $settings['show_weight'],
				'vehicle'     => (bool) $settings['show_vehicle'],
				'container'   => (bool) $settings['show_container'],
				'storage'     => (bool) $settings['show_storage'],
				'print'       => (bool) $settings['show_print'],
				'remember'    => (bool) $settings['remember'],
				'animations'  => (bool) $settings['animations'],
				'fields'      => array(
					'phone'     => $field( 'field_phone' ),
					'addresses' => $field( 'field_addresses' ),
					'date'      => $field( 'field_date' ),
					'service'   => $field( 'field_service' ),
					'access'    => $field( 'field_access' ),
					'extras'    => $field( 'field_extras' ),
				),
				'services'    => MSVCalc_Leads::services(),
				'floors'      => MSVCalc_Leads::floors(),
				'parking'     => MSVCalc_Leads::parking(),
				'extras'      => MSVCalc_Leads::extras(),
				'cta'         => MSVCalc_Settings::text( 'cta_text' ),
				'consent'     => MSVCalc_Settings::text( 'consent_text' ),
				'privacyUrl'  => esc_url_raw( (string) get_privacy_policy_url() ),
				'today'       => wp_date( 'Y-m-d' ),
				'locale'      => str_replace( '_', '-', determine_locale() ),
				'storageKey'  => 'msvcalc_' . substr( md5( home_url() ), 0, 8 ),
				'companyName' => MSVCalc_Settings::text( 'company_name' ),
				'siteName'    => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
				'showCredit'  => (bool) $settings['show_credit'],
			),
			'i18n'       => self::strings() + self::box_choices(),
		);
	}

	/**
	 * Answer labels for the box estimator.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function box_choices(): array {
		return array(
			'boxHomeChoices'      => array(
				__( 'Studio', 'moving-storage-volume-calculator' ),
				__( '1 bed', 'moving-storage-volume-calculator' ),
				__( '2 bed', 'moving-storage-volume-calculator' ),
				__( '3 bed', 'moving-storage-volume-calculator' ),
				__( '4 bed', 'moving-storage-volume-calculator' ),
				__( '5+ bed', 'moving-storage-volume-calculator' ),
			),
			'boxCupboardsChoices' => array(
				__( 'Fairly empty', 'moving-storage-volume-calculator' ),
				__( 'Average', 'moving-storage-volume-calculator' ),
				__( 'Packed full', 'moving-storage-volume-calculator' ),
			),
			'boxBooksChoices'     => array(
				__( 'A few', 'moving-storage-volume-calculator' ),
				__( 'Some', 'moving-storage-volume-calculator' ),
				__( 'Lots', 'moving-storage-volume-calculator' ),
			),
			'boxKitchenChoices'   => array(
				__( 'Rarely cook', 'moving-storage-volume-calculator' ),
				__( 'Average', 'moving-storage-volume-calculator' ),
				__( 'Keen cook', 'moving-storage-volume-calculator' ),
			),
			'boxLoftChoices'      => array(
				__( 'None', 'moving-storage-volume-calculator' ),
				__( 'Some', 'moving-storage-volume-calculator' ),
				__( 'Full', 'moving-storage-volume-calculator' ),
			),
		);
	}

	/**
	 * Translated interface strings for the script.
	 *
	 * @return array<string, string>
	 */
	public static function strings(): array {
		return array(
			'm3'              => __( 'm³', 'moving-storage-volume-calculator' ),
			'ft3'             => __( 'ft³', 'moving-storage-volume-calculator' ),
			'kg'              => __( 'kg', 'moving-storage-volume-calculator' ),
			'lb'              => __( 'lb', 'moving-storage-volume-calculator' ),
			'cubicMetres'     => __( 'Cubic metres', 'moving-storage-volume-calculator' ),
			'cubicFeet'       => __( 'Cubic feet', 'moving-storage-volume-calculator' ),
			'units'           => __( 'Units', 'moving-storage-volume-calculator' ),
			'quickStart'      => __( 'Quick start', 'moving-storage-volume-calculator' ),
			/* translators: %d: number of hidden quick start options. */
			'morePresets'     => __( '+%d more', 'moving-storage-volume-calculator' ),
			'fewerPresets'    => __( 'Fewer', 'moving-storage-volume-calculator' ),
			'quickStartHelp'  => __( 'Pick your home size to fill in a typical inventory, then adjust it.', 'moving-storage-volume-calculator' ),
			'presetApplied'   => __( 'Typical inventory added. Adjust the quantities to match your home.', 'moving-storage-volume-calculator' ),
			'presetConfirm'   => __( 'Replace your current list with a typical inventory?', 'moving-storage-volume-calculator' ),
			'rooms'           => __( 'Rooms', 'moving-storage-volume-calculator' ),
			'groups'          => __( 'Categories', 'moving-storage-volume-calculator' ),
			'chooseIcon'      => __( 'Choose an icon', 'moving-storage-volume-calculator' ),
			'findIcon'        => __( 'Find an icon', 'moving-storage-volume-calculator' ),
			'noIcons'         => __( 'No icons match.', 'moving-storage-volume-calculator' ),
			'iconLabel'       => __( 'Icon', 'moving-storage-volume-calculator' ),
			'allIcons'        => __( 'All', 'moving-storage-volume-calculator' ),
			'fromFloor'       => __( 'Floor (moving from)', 'moving-storage-volume-calculator' ),
			'toFloor'         => __( 'Floor (moving to)', 'moving-storage-volume-calculator' ),
			'lift'            => __( 'There is a lift', 'moving-storage-volume-calculator' ),
			'parking'         => __( 'Parking for the van', 'moving-storage-volume-calculator' ),
			'extras'          => __( 'Extra services', 'moving-storage-volume-calculator' ),
			'share'           => __( 'Share list', 'moving-storage-volume-calculator' ),
			/* translators: %s: total volume. */
			'shareText'       => __( 'My moving inventory: %s', 'moving-storage-volume-calculator' ),
			'linkCopied'      => __( 'Link copied. Anyone with it can open this list.', 'moving-storage-volume-calculator' ),
			'copyLink'        => __( 'Copy this link:', 'moving-storage-volume-calculator' ),
			'loadShared'      => __( 'Open the shared list? It replaces the list you have here.', 'moving-storage-volume-calculator' ),
			'cleared'         => __( 'List cleared.', 'moving-storage-volume-calculator' ),
			'sharedLoaded'    => __( 'Shared list opened. Adjust it as you like.', 'moving-storage-volume-calculator' ),
			'restored'        => __( 'List restored.', 'moving-storage-volume-calculator' ),
			'undo'            => __( 'Undo', 'moving-storage-volume-calculator' ),
			'forgotTitle'     => __( 'Anything from these areas?', 'moving-storage-volume-calculator' ),
			'forgotHelp'      => __( 'You have not added anything from them yet. Forgotten items are the main reason estimates come out too small.', 'moving-storage-volume-calculator' ),
			'boxTitle'        => __( 'How many boxes do I need?', 'moving-storage-volume-calculator' ),
			'boxHelp'         => __( 'Answer a few quick questions for a suggested box count.', 'moving-storage-volume-calculator' ),
			'boxHome'         => __( 'Home size', 'moving-storage-volume-calculator' ),
			'boxCupboards'    => __( 'How full are your cupboards and wardrobes?', 'moving-storage-volume-calculator' ),
			'boxBooks'        => __( 'Books, games and records', 'moving-storage-volume-calculator' ),
			'boxKitchen'      => __( 'Kitchen', 'moving-storage-volume-calculator' ),
			'boxLoft'         => __( 'Loft, garage or shed contents', 'moving-storage-volume-calculator' ),
			/* translators: %s: number of boxes. */
			'boxTotal'        => __( 'About %s boxes', 'moving-storage-volume-calculator' ),
			'boxApply'        => __( 'Use these box counts', 'moving-storage-volume-calculator' ),
			/* translators: %s: number of boxes. */
			'boxAdded'        => __( '%s boxes set in your list.', 'moving-storage-volume-calculator' ),
			/* translators: %d: number of items. */
			'groupItems'      => __( '%d items', 'moving-storage-volume-calculator' ),
			'search'          => __( 'Search items, e.g. sofa, fridge, boxes', 'moving-storage-volume-calculator' ),
			'searchLabel'     => __( 'Search all items', 'moving-storage-volume-calculator' ),
			'noResults'       => __( 'No items match. Add it as a custom item below.', 'moving-storage-volume-calculator' ),
			/* translators: %d: number of matching items. */
			'results'         => __( '%d matching items', 'moving-storage-volume-calculator' ),
			'showAll'         => __( 'Show all items', 'moving-storage-volume-calculator' ),
			'showLess'        => __( 'Show common items only', 'moving-storage-volume-calculator' ),
			/* translators: %s: item name. */
			'add'             => __( 'Add one %s', 'moving-storage-volume-calculator' ),
			/* translators: %s: item name. */
			'remove'          => __( 'Remove one %s', 'moving-storage-volume-calculator' ),
			/* translators: %s: item name. */
			'quantityOf'      => __( 'Quantity of %s', 'moving-storage-volume-calculator' ),
			'customTitle'     => __( 'Something not listed?', 'moving-storage-volume-calculator' ),
			'customHelp'      => __( 'Add it with its width, depth and height.', 'moving-storage-volume-calculator' ),
			'customName'      => __( 'Item name', 'moving-storage-volume-calculator' ),
			'length'          => __( 'Length', 'moving-storage-volume-calculator' ),
			'width'           => __( 'Width', 'moving-storage-volume-calculator' ),
			'height'          => __( 'Height', 'moving-storage-volume-calculator' ),
			'cm'              => __( 'cm', 'moving-storage-volume-calculator' ),
			'in'              => __( 'in', 'moving-storage-volume-calculator' ),
			'quantity'        => __( 'Qty', 'moving-storage-volume-calculator' ),
			'addCustom'       => __( 'Add item', 'moving-storage-volume-calculator' ),
			'customRoom'      => __( 'Custom items', 'moving-storage-volume-calculator' ),
			'customInvalid'   => __( 'Enter a name and all three measurements.', 'moving-storage-volume-calculator' ),
			'summary'         => __( 'Your move', 'moving-storage-volume-calculator' ),
			'totalVolume'     => __( 'Total volume', 'moving-storage-volume-calculator' ),
			/* translators: %d: packing margin percentage. */
			'margin'          => __( 'Includes %d%% for packing and gaps.', 'moving-storage-volume-calculator' ),
			'items'           => __( 'Items', 'moving-storage-volume-calculator' ),
			'boxes'           => __( 'Boxes', 'moving-storage-volume-calculator' ),
			'weight'          => __( 'Est. weight', 'moving-storage-volume-calculator' ),
			'vehicle'         => __( 'Recommended vehicle', 'moving-storage-volume-calculator' ),
			'container'       => __( 'Shipping container', 'moving-storage-volume-calculator' ),
			'storage'         => __( 'Storage unit', 'moving-storage-volume-calculator' ),
			/* translators: %d: percentage of capacity used. */
			'fill'            => __( '%d%% full', 'moving-storage-volume-calculator' ),
			'empty'           => __( 'Your list is empty. Add items from the rooms, or use a quick start.', 'moving-storage-volume-calculator' ),
			'yourList'        => __( 'Your list', 'moving-storage-volume-calculator' ),
			'reset'           => __( 'Start over', 'moving-storage-volume-calculator' ),
			'resetConfirm'    => __( 'Clear your whole list?', 'moving-storage-volume-calculator' ),
			'print'           => __( 'Print', 'moving-storage-volume-calculator' ),
			'edit'            => __( 'Back to my items', 'moving-storage-volume-calculator' ),
			'quoteTitle'      => __( 'Get your quote', 'moving-storage-volume-calculator' ),
			'quoteHelp'       => __( 'Send us your inventory and we will reply with a quote.', 'moving-storage-volume-calculator' ),
			'name'            => __( 'Full name', 'moving-storage-volume-calculator' ),
			'email'           => __( 'Email', 'moving-storage-volume-calculator' ),
			'phone'           => __( 'Phone', 'moving-storage-volume-calculator' ),
			'from'            => __( 'Moving from', 'moving-storage-volume-calculator' ),
			'to'              => __( 'Moving to', 'moving-storage-volume-calculator' ),
			'fromPlaceholder' => __( 'Town or postcode', 'moving-storage-volume-calculator' ),
			'date'            => __( 'Moving date', 'moving-storage-volume-calculator' ),
			'service'         => __( 'Service', 'moving-storage-volume-calculator' ),
			'choose'          => __( 'Choose…', 'moving-storage-volume-calculator' ),
			'notes'           => __( 'Anything else we should know?', 'moving-storage-volume-calculator' ),
			'notesHelp'       => __( 'Stairs, lift, parking, fragile items…', 'moving-storage-volume-calculator' ),
			'optional'        => __( '(optional)', 'moving-storage-volume-calculator' ),
			'privacy'         => __( 'Privacy policy', 'moving-storage-volume-calculator' ),
			'sending'         => __( 'Sending…', 'moving-storage-volume-calculator' ),
			'required'        => __( 'This field is required.', 'moving-storage-volume-calculator' ),
			'invalidEmail'    => __( 'Please enter a valid email address.', 'moving-storage-volume-calculator' ),
			'emptyList'       => __( 'Please add at least one item before asking for a quote.', 'moving-storage-volume-calculator' ),
			'error'           => __( 'Something went wrong. Please try again.', 'moving-storage-volume-calculator' ),
			'sent'            => __( 'Request sent', 'moving-storage-volume-calculator' ),
			'websiteTrap'     => __( 'Leave this field empty', 'moving-storage-volume-calculator' ),
			'estimate'        => __( 'Volumes and weights are estimates based on typical item sizes.', 'moving-storage-volume-calculator' ),
			/* translators: %s: room name. */
			'roomTotal'       => __( '%s total', 'moving-storage-volume-calculator' ),
			'viewSummary'     => __( 'View summary', 'moving-storage-volume-calculator' ),
			'close'           => __( 'Close', 'moving-storage-volume-calculator' ),
			/* translators: %s: item name. */
			'added'           => __( '%s added', 'moving-storage-volume-calculator' ),
			'printTitle'      => __( 'Moving inventory', 'moving-storage-volume-calculator' ),
			/* translators: %s: date. */
			'printDate'       => __( 'Prepared on %s', 'moving-storage-volume-calculator' ),
			'printFits'       => __( 'What it fits in', 'moving-storage-volume-calculator' ),
			'printQty'        => __( 'Qty', 'moving-storage-volume-calculator' ),
			'printItem'       => __( 'Item', 'moving-storage-volume-calculator' ),
			'printVolume'     => __( 'Volume', 'moving-storage-volume-calculator' ),
			'printNet'        => __( 'Items', 'moving-storage-volume-calculator' ),
			/* translators: %d: packing margin percentage. */
			'printMargin'     => __( 'Packing and gaps (%d%%)', 'moving-storage-volume-calculator' ),
			/* translators: %s: web address of the calculator page. */
			'printSource'     => __( 'Made with the volume calculator at %s', 'moving-storage-volume-calculator' ),
			'poweredBy'       => __( 'Powered by Moving & Storage Volume Calculator', 'moving-storage-volume-calculator' ),
			'printCopy'       => __( 'Print a copy', 'moving-storage-volume-calculator' ),
			'editList'        => __( 'Change my list', 'moving-storage-volume-calculator' ),
			'startNew'        => __( 'Start a new list', 'moving-storage-volume-calculator' ),
			'shareTitle'      => __( 'Share your list', 'moving-storage-volume-calculator' ),
			'shareHelp'       => __( 'Anyone with this link sees the same list. It holds your items only, never your contact details.', 'moving-storage-volume-calculator' ),
			'shareLinkLabel'  => __( 'Link to your list', 'moving-storage-volume-calculator' ),
			'copy'            => __( 'Copy', 'moving-storage-volume-calculator' ),
			'copied'          => __( 'Copied', 'moving-storage-volume-calculator' ),
			'shareWhatsApp'   => __( 'WhatsApp', 'moving-storage-volume-calculator' ),
			'shareEmail'      => __( 'Email', 'moving-storage-volume-calculator' ),
			'shareSms'        => __( 'Text message', 'moving-storage-volume-calculator' ),
			'shareMore'       => __( 'More options', 'moving-storage-volume-calculator' ),
			'tooBig'          => __( 'More than one vehicle needed', 'moving-storage-volume-calculator' ),
		);
	}
}
