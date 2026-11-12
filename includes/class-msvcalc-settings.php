<?php
/**
 * Plugin settings: one option, a typed schema, defaults and sanitizing.
 *
 * @package MSVCalc
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and stores the plugin settings.
 */
final class MSVCalc_Settings {

	/**
	 * Option name for the settings array.
	 */
	const OPTION = 'msvcalc_settings';

	/**
	 * Settings group used by the Settings API.
	 */
	const GROUP = 'msvcalc_settings_group';

	/**
	 * Cached settings for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	/**
	 * Field schema: type and default for every setting.
	 *
	 * Types: bool, int (with min/max), enum (with choices), text, textarea, email, color, page.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		$visibility = array( 'required', 'optional', 'hidden' );

		return array(
			// Calculator.
			'units'               => array(
				'type'    => 'enum',
				'choices' => array( 'm3', 'ft3' ),
				'default' => 'm3',
			),
			'unit_toggle'         => array(
				'type'    => 'bool',
				'default' => true,
			),
			'region'              => array(
				'type'    => 'enum',
				'choices' => array( 'uk', 'eu', 'us', 'au' ),
				'default' => 'uk',
			),
			'margin'              => array(
				'type'    => 'int',
				'min'     => 0,
				'max'     => 50,
				'default' => 10,
			),
			'show_presets'        => array(
				'type'    => 'bool',
				'default' => true,
			),
			'show_search'         => array(
				'type'    => 'bool',
				'default' => true,
			),
			'show_custom'         => array(
				'type'    => 'bool',
				'default' => true,
			),
			'show_boxes'          => array(
				'type'    => 'bool',
				'default' => true,
			),
			'show_weight'         => array(
				'type'    => 'bool',
				'default' => true,
			),
			'show_vehicle'        => array(
				'type'    => 'bool',
				'default' => true,
			),
			'show_container'      => array(
				'type'    => 'bool',
				'default' => true,
			),
			'show_storage'        => array(
				'type'    => 'bool',
				'default' => true,
			),
			'show_print'          => array(
				'type'    => 'bool',
				'default' => true,
			),
			'remember'            => array(
				'type'    => 'bool',
				'default' => true,
			),
			'title'               => array(
				'type'    => 'text',
				'default' => '',
			),
			'intro'               => array(
				'type'    => 'textarea',
				'default' => '',
			),
			// Look.
			'theme'               => array(
				'type'    => 'enum',
				'choices' => array( 'light', 'dark', 'auto' ),
				'default' => 'light',
			),
			'accent'              => array(
				'type'    => 'color',
				'default' => '#0fb5ba',
			),
			'accent_2'            => array(
				'type'    => 'color',
				'default' => '#84cc16',
			),
			'radius'              => array(
				'type'    => 'int',
				'min'     => 0,
				'max'     => 28,
				'default' => 16,
			),
			'animations'          => array(
				'type'    => 'bool',
				'default' => true,
			),
			// Quote request.
			'quote_enabled'       => array(
				'type'    => 'bool',
				'default' => true,
			),
			'notify_email'        => array(
				'type'    => 'email',
				'default' => '',
			),
			'company_name'        => array(
				'type'    => 'text',
				'default' => '',
			),
			'send_copy'           => array(
				'type'    => 'bool',
				'default' => true,
			),
			'field_phone'         => array(
				'type'    => 'enum',
				'choices' => $visibility,
				'default' => 'required',
			),
			'field_addresses'     => array(
				'type'    => 'enum',
				'choices' => $visibility,
				'default' => 'optional',
			),
			'field_date'          => array(
				'type'    => 'enum',
				'choices' => $visibility,
				'default' => 'optional',
			),
			'field_service'       => array(
				'type'    => 'enum',
				'choices' => $visibility,
				'default' => 'optional',
			),
			'field_access'        => array(
				'type'    => 'enum',
				'choices' => $visibility,
				'default' => 'optional',
			),
			'field_extras'        => array(
				'type'    => 'enum',
				'choices' => array( 'optional', 'hidden' ),
				'default' => 'optional',
			),
			'cta_text'            => array(
				'type'    => 'text',
				'default' => '',
			),
			'consent_text'        => array(
				'type'    => 'textarea',
				'default' => '',
			),
			'success_message'     => array(
				'type'    => 'textarea',
				'default' => '',
			),
			'thank_you_page'      => array(
				'type'    => 'page',
				'default' => 0,
			),
			// Data and privacy.
			'store_leads'         => array(
				'type'    => 'bool',
				'default' => true,
			),
			'leads_access'        => array(
				'type'    => 'enum',
				'choices' => array( 'admins', 'editors' ),
				'default' => 'admins',
			),
			'retention_days'      => array(
				'type'    => 'int',
				'min'     => 0,
				'max'     => 3650,
				'default' => 365,
			),
			'delete_on_uninstall' => array(
				'type'    => 'bool',
				'default' => false,
			),
		);
	}

	/**
	 * Default values for all settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$defaults = array();
		foreach ( self::schema() as $key => $field ) {
			$defaults[ $key ] = $field['default'];
		}
		return $defaults;
	}

	/**
	 * All settings, merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			$stored      = is_array( $stored ) ? $stored : array();
			self::$cache = array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Clears the request cache, for example after the option changes.
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * A text setting, or its translated default when left empty. Defaults are
	 * not stored in the option so they follow the site language.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public static function text( string $key ): string {
		$value = (string) self::get( $key );
		if ( '' !== trim( $value ) ) {
			return $value;
		}
		switch ( $key ) {
			case 'title':
				return __( 'How much are you moving?', 'moving-storage-volume-calculator' );
			case 'intro':
				return __( 'Add your furniture and boxes room by room. We work out the volume, the right vehicle and the storage space you need.', 'moving-storage-volume-calculator' );
			case 'cta_text':
				return __( 'Get my free quote', 'moving-storage-volume-calculator' );
			case 'consent_text':
				return __( 'I agree that my details and inventory are stored and used to prepare my quote.', 'moving-storage-volume-calculator' );
			case 'success_message':
				return __( 'Thank you! Your inventory is on its way. We will be in touch shortly with your quote.', 'moving-storage-volume-calculator' );
			case 'company_name':
				return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
			case 'notify_email':
				return (string) get_option( 'admin_email' );
		}
		return '';
	}

	/**
	 * Registers the option with the Settings API.
	 */
	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitizes and validates a submitted settings array.
	 *
	 * Unknown keys are dropped. Checkbox fields missing from the submission
	 * are stored as false, because unchecked boxes are not posted.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$output = array();

		foreach ( self::schema() as $key => $field ) {
			$raw = $input[ $key ] ?? null;

			switch ( $field['type'] ) {
				case 'bool':
					$output[ $key ] = ( true === $raw || '1' === $raw || 1 === $raw );
					break;

				case 'int':
					$value          = is_numeric( $raw ) ? (int) $raw : (int) $field['default'];
					$output[ $key ] = max( (int) $field['min'], min( (int) $field['max'], $value ) );
					break;

				case 'enum':
					$value          = is_string( $raw ) ? sanitize_key( $raw ) : '';
					$output[ $key ] = in_array( $value, $field['choices'], true ) ? $value : $field['default'];
					break;

				case 'text':
					$output[ $key ] = is_string( $raw ) ? sanitize_text_field( $raw ) : '';
					break;

				case 'textarea':
					$output[ $key ] = is_string( $raw ) ? sanitize_textarea_field( $raw ) : '';
					break;

				case 'email':
					$value = is_string( $raw ) ? sanitize_email( $raw ) : '';
					if ( '' !== $value && ! is_email( $value ) ) {
						add_settings_error( self::OPTION, 'msvcalc_bad_email', __( 'The notification email address is not valid, so the site admin email will be used.', 'moving-storage-volume-calculator' ) );
						$value = '';
					}
					$output[ $key ] = $value;
					break;

				case 'color':
					$value          = is_string( $raw ) ? sanitize_hex_color( $raw ) : '';
					$output[ $key ] = $value ? $value : $field['default'];
					break;

				case 'page':
					$page_id        = absint( $raw );
					$output[ $key ] = ( $page_id && 'page' === get_post_type( $page_id ) ) ? $page_id : 0;
					break;
			}
		}

		self::flush();
		return $output;
	}
}
